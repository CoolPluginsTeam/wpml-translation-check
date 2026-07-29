<?php
/**
 * Single entry point for talking to an AI provider.
 *
 * Wraps the WordPress AI Client SDK so the rest of the plugin never needs to
 * know which provider is configured. Batching that used to happen in the
 * browser now happens here.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Providers;

use WPML_AT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI_Gateway
 */
class AI_Gateway {

	/**
	 * Approximate token ceiling per request. Mirrors the value the browser
	 * used so translation quality does not change.
	 */
	const TOKENS_PER_BATCH = 500;

	/**
	 * Rough characters-per-token ratio used for batch sizing.
	 */
	const CHARS_PER_TOKEN = 4;

	/**
	 * Seconds to wait for a provider response.
	 */
	const TIMEOUT = 120;

	/**
	 * Fallback model per provider.
	 *
	 * @var array<string,string>
	 */
	private static $fallback_models = array(
		'openai' => 'gpt-4o-mini',
		'google' => 'gemini-2.5-flash',
	);

	/**
	 * Provider slug, e.g. 'openai'.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Model id.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Constructor.
	 *
	 * @param string $provider Provider slug. Empty selects the first configured one.
	 */
	public function __construct( $provider = '' ) {
		$this->provider = $provider ? sanitize_key( $provider ) : $this->first_configured_provider();
		$this->model    = $this->resolve_model( $this->provider );
	}

	/**
	 * Provider slug in use.
	 *
	 * @return string
	 */
	public function provider() {
		return $this->provider;
	}

	/**
	 * Model id in use.
	 *
	 * @return string
	 */
	public function model() {
		return $this->model;
	}

	/**
	 * Translate a map of field name => plain text.
	 *
	 * Splits the map into token-sized batches, sends each one, and merges the
	 * results. Keys in the returned array match the keys passed in; any field
	 * the provider failed to return is simply absent.
	 *
	 * @param array  $fields           field_name => source text.
	 * @param string $from_lang        Source language code.
	 * @param string $to_lang          Target language code.
	 * @param callable|null $on_progress Called after each batch with
	 *                                   ( fields_translated, fields_total ).
	 * @return array|\WP_Error field_name => translated text.
	 */
	public function translate_fields( array $fields, $from_lang, $to_lang, $on_progress = null ) {
		if ( empty( $fields ) ) {
			return new \WP_Error( 'automlp_empty_batch', __( 'Nothing to translate.', 'wpml-translation-check' ) );
		}

		$ready = $this->ensure_ready();

		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$translated        = array();
		$failures          = array();
		$fields_total      = count( $fields );
		$fields_translated = 0;

		foreach ( $this->split_into_batches( $fields ) as $batch ) {
			$result = $this->send_batch( $batch, $from_lang, $to_lang );

			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();
				continue;
			}

			$translated = array_merge( $translated, $result );
			$fields_translated += count( $result );

			if ( is_callable( $on_progress ) ) {
				call_user_func( $on_progress, $fields_translated, $fields_total );
			}
		}

		// Every batch failed: surface the first error so the job can retry.
		if ( empty( $translated ) && ! empty( $failures ) ) {
			return new \WP_Error( 'automlp_provider_failed', $failures[0] );
		}

		return $translated;
	}

	/**
	 * Group fields into batches under the token ceiling.
	 *
	 * A single field longer than the ceiling gets its own batch rather than
	 * being dropped.
	 *
	 * @param array $fields field_name => text.
	 * @return array<int,array<string,string>>
	 */
	private function split_into_batches( array $fields ) {
		$batches = array();
		$current = array();
		$tokens  = 0;

		foreach ( $fields as $name => $text ) {
			$cost = (int) ceil( mb_strlen( (string) $text ) / self::CHARS_PER_TOKEN );

			if ( ! empty( $current ) && ( $tokens + $cost ) > self::TOKENS_PER_BATCH ) {
				$batches[] = $current;
				$current   = array();
				$tokens    = 0;
			}

			$current[ $name ] = (string) $text;
			$tokens          += $cost;
		}

		if ( ! empty( $current ) ) {
			$batches[] = $current;
		}

		return $batches;
	}

	/**
	 * Send one batch and decode the response.
	 *
	 * Field names are replaced with numeric indexes on the wire so the model
	 * cannot mangle them, then mapped back on return.
	 *
	 * @param array  $batch     field_name => text.
	 * @param string $from_lang Source language.
	 * @param string $to_lang   Target language.
	 * @return array|\WP_Error
	 */
	private function send_batch( array $batch, $from_lang, $to_lang ) {
		$names   = array_keys( $batch );
		$indexed = array_values( $batch );

		$prompt = Prompt_Builder::build( $indexed, $from_lang, $to_lang );

		$raw = $this->run_prompt( $prompt );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$decoded = $this->decode_response( $raw );

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$out = array();

		foreach ( $decoded as $index => $value ) {
			$index = (int) $index;

			if ( ! isset( $names[ $index ] ) ) {
				continue;
			}

			if ( '' === $value || null === $value ) {
				continue;
			}

			$out[ $names[ $index ] ] = (string) $value;
		}

		return $out;
	}

	/**
	 * Execute a prompt through the AI SDK.
	 *
	 * @param string $prompt Prompt text.
	 * @return string|\WP_Error Raw model output.
	 */
	private function run_prompt( $prompt ) {
		$timeout_filter = static function () {
			return self::TIMEOUT;
		};

		add_filter( 'wp_ai_client_default_request_timeout', $timeout_filter );

		try {
			$registry       = \WordPress\AiClient\AiClient::defaultRegistry();
			$provider_class = $registry->getProviderClassName( $this->provider );
			$model          = $provider_class::model( $this->model );

			$builder = function_exists( 'wp_ai_client_prompt' )
				? wp_ai_client_prompt()
				: \WordPress\AI_Client\AI_Client::prompt();

			if ( null === $builder ) {
				return new \WP_Error( 'automlp_no_builder', __( 'AI client is not available.', 'wpml-translation-check' ) );
			}

			$raw = $builder
				->using_model( $model )
				->using_provider( $this->provider )
				->with_text( $prompt )
				->generate_text();

		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_generate_failed', $e->getMessage() );
		} finally {
			remove_filter( 'wp_ai_client_default_request_timeout', $timeout_filter );
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return new \WP_Error( 'automlp_empty_response', __( 'The AI provider returned an empty response.', 'wpml-translation-check' ) );
		}

		return $raw;
	}

	/**
	 * Turn raw model output into an array.
	 *
	 * Handles markdown fences and over-escaped backslashes, both of which
	 * models emit intermittently.
	 *
	 * @param string $raw Raw output.
	 * @return array|\WP_Error
	 */
	private function decode_response( $raw ) {
		$text = preg_replace( '/^\s*```(?:json)?\s*|\s*```\s*$/', '', trim( $raw ) );
		$text = preg_replace( '/\\\\{2,}([\'"n])/', '\\\\$1', $text );

		$decoded = json_decode( $text, true );

		// Some models wrap the object in a JSON string. Unwrap once.
		if ( is_array( $decoded ) && 1 === count( $decoded ) ) {
			$only  = reset( $decoded );
			$inner = is_string( $only ) ? json_decode( $only, true ) : null;

			if ( is_array( $inner ) ) {
				$decoded = $inner;
			}
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'automlp_bad_json',
				__( 'The AI provider returned a response that could not be parsed as JSON.', 'wpml-translation-check' )
			);
		}

		return $decoded;
	}

	/**
	 * Confirm the SDK is loaded and the provider has credentials.
	 *
	 * @return true|\WP_Error
	 */
	private function ensure_ready() {
		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return new \WP_Error( 'automlp_no_sdk', __( 'The AI SDK is not available.', 'wpml-translation-check' ) );
		}

		if ( ! $this->provider ) {
			return new \WP_Error( 'automlp_no_provider', __( 'No AI provider is configured. Add an API key in the plugin settings.', 'wpml-translation-check' ) );
		}

		if ( ! $this->model ) {
			return new \WP_Error( 'automlp_no_model', __( 'No AI model is selected for this provider.', 'wpml-translation-check' ) );
		}

		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( ! $registry->isProviderConfigured( $this->provider ) ) {
			return new \WP_Error(
				'automlp_provider_unconfigured',
				sprintf(
					/* translators: %s: provider slug, e.g. openai */
					__( 'The API key for %s is missing or invalid.', 'wpml-translation-check' ),
					$this->provider
				)
			);
		}

		return true;
	}

	/**
	 * First provider that has a stored key.
	 *
	 * @return string
	 */
	private function first_configured_provider() {
		$keys = class_exists( 'WPML_AT_Helper' )
			? WPML_AT_Helper::get_providers_key( array( 'openai', 'google' ), true )
			: array();

		foreach ( array( 'openai', 'google' ) as $slug ) {
			if ( ! empty( $keys[ $slug ] ) ) {
				return $slug;
			}
		}

		return '';
	}

	/**
	 * Model for a provider: saved choice, else fallback.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	private function resolve_model( $provider ) {
		if ( ! $provider ) {
			return '';
		}

		$saved = get_option( 'automlp_ai_translation_models', array() );

		if ( is_array( $saved ) && ! empty( $saved[ $provider ] ) ) {
			return (string) $saved[ $provider ];
		}

		return isset( self::$fallback_models[ $provider ] ) ? self::$fallback_models[ $provider ] : '';
	}
}
