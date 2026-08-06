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
	 * Large WPML fields are split into smaller translation units so the queue
	 * can report real progress between provider calls.
	 */
	const CHARS_PER_CHUNK = 2000;

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

		$units = $this->translation_units( $fields );

		if ( empty( $units ) ) {
			return new \WP_Error( 'automlp_empty_batch', __( 'Nothing to translate.', 'wpml-translation-check' ) );
		}

		$partial          = array();
		$failures         = array();
		$units_total      = count( $units );
		$units_translated = 0;

		foreach ( $this->split_units_into_batches( $units ) as $batch ) {
			$result = $this->translate_units( $batch, $from_lang, $to_lang );

			if ( is_wp_error( $result ) ) {
				$failures[] = $result->get_error_message();
				continue;
			}

			foreach ( $result as $field => $chunks ) {
				if ( ! isset( $partial[ $field ] ) || ! is_array( $partial[ $field ] ) ) {
					$partial[ $field ] = array();
				}

				foreach ( (array) $chunks as $index => $value ) {
					$partial[ $field ][ (int) $index ] = (string) $value;
				}
			}

			$units_translated = $this->count_translated_units( $units, $partial );

			if ( is_callable( $on_progress ) ) {
				call_user_func( $on_progress, $units_translated, $units_total );
			}
		}

		// Every batch failed: surface the first error so the job can retry.
		if ( empty( $partial ) && ! empty( $failures ) ) {
			return new \WP_Error( 'automlp_provider_failed', $failures[0] );
		}

		return $this->reassemble_units( $fields, $units, $partial );
	}

	/**
	 * Build ordered translation units from a WPML field map.
	 *
	 * Each unit keeps its parent field and chunk index so translated chunks can
	 * be reassembled without relying on model-generated field names.
	 *
	 * @param array $fields field_name => source text.
	 * @return array<int,array{field:string,index:int,text:string}>
	 */
	public function translation_units( array $fields ) {
		$units = array();

		foreach ( $fields as $field => $text ) {
			$field  = (string) $field;
			$chunks = $this->split_text_into_chunks( (string) $text );

			foreach ( $chunks as $index => $chunk ) {
				$units[] = array(
					'field' => $field,
					'index' => (int) $index,
					'text'  => (string) $chunk,
				);
			}
		}

		return $units;
	}

	/**
	 * Return the next provider-sized unit batch starting at an offset.
	 *
	 * @param array $units  Ordered units from translation_units().
	 * @param int   $offset Number of units already translated.
	 * @return array<int,array{field:string,index:int,text:string}>
	 */
	public function next_unit_batch( array $units, $offset = 0 ) {
		$offset = max( 0, (int) $offset );
		$slice  = array_slice( $units, $offset );

		$batches = $this->split_units_into_batches( $slice );

		return empty( $batches ) ? array() : $batches[0];
	}

	/**
	 * Translate a single unit batch.
	 *
	 * @param array  $units     Units to translate.
	 * @param string $from_lang Source language.
	 * @param string $to_lang   Target language.
	 * @return array|\WP_Error field => chunk_index => translated text.
	 */
	public function translate_units( array $units, $from_lang, $to_lang ) {
		if ( empty( $units ) ) {
			return array();
		}

		$ready = $this->ensure_ready();

		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$wire = array();
		$map  = array();

		foreach ( $units as $unit_key => $unit ) {
			if ( empty( $unit['field'] ) || ! array_key_exists( 'text', $unit ) ) {
				continue;
			}

			$key          = (string) $unit_key;
			$wire[ $key ] = (string) $unit['text'];
			$map[ $key ]  = array(
				'field' => (string) $unit['field'],
				'index' => isset( $unit['index'] ) ? (int) $unit['index'] : 0,
			);
		}

		if ( empty( $wire ) ) {
			return array();
		}

		$result = $this->send_batch( $wire, $from_lang, $to_lang );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$out = array();

		foreach ( $result as $key => $value ) {
			if ( ! isset( $map[ $key ] ) ) {
				continue;
			}

			$field = $map[ $key ]['field'];
			$index = $map[ $key ]['index'];

			if ( ! isset( $out[ $field ] ) ) {
				$out[ $field ] = array();
			}

			$out[ $field ][ $index ] = (string) $value;
		}

		return $out;
	}

	/**
	 * Rebuild field translations from completed unit chunks.
	 *
	 * Fields are returned only when every one of their chunks is present.
	 *
	 * @param array $fields  Original field map.
	 * @param array $units   Ordered units from translation_units().
	 * @param array $partial field => chunk_index => translated text.
	 * @return array field_name => translated text.
	 */
	public function reassemble_units( array $fields, array $units, array $partial ) {
		$out = array();

		foreach ( $fields as $field => $_text ) {
			$field_chunks = array_filter(
				$units,
				static function ( $unit ) use ( $field ) {
					return isset( $unit['field'] ) && (string) $unit['field'] === (string) $field;
				}
			);

			if ( empty( $field_chunks ) ) {
				continue;
			}

			$text     = '';
			$complete = true;

			foreach ( $field_chunks as $unit ) {
				$index = isset( $unit['index'] ) ? (int) $unit['index'] : 0;

				if ( ! isset( $partial[ $field ] ) || ! is_array( $partial[ $field ] ) || ! array_key_exists( $index, $partial[ $field ] ) ) {
					$complete = false;
					break;
				}

				$text .= (string) $partial[ $field ][ $index ];
			}

			if ( $complete ) {
				$out[ $field ] = $text;
			}
		}

		return $out;
	}

	/**
	 * Group units into batches under the token ceiling.
	 *
	 * A single unit longer than the ceiling gets its own batch rather than
	 * being dropped.
	 *
	 * @param array $units Ordered translation units.
	 * @return array<int,array<int,array{field:string,index:int,text:string}>>
	 */
	private function split_units_into_batches( array $units ) {
		$batches = array();
		$current = array();
		$tokens  = 0;

		foreach ( $units as $unit ) {
			$text = isset( $unit['text'] ) ? (string) $unit['text'] : '';
			$cost = (int) ceil( mb_strlen( $text ) / self::CHARS_PER_TOKEN );

			if ( ! empty( $current ) && ( $tokens + $cost ) > self::TOKENS_PER_BATCH ) {
				$batches[] = $current;
				$current   = array();
				$tokens    = 0;
			}

			$current[] = $unit;
			$tokens   += $cost;
		}

		if ( ! empty( $current ) ) {
			$batches[] = $current;
		}

		return $batches;
	}

	/**
	 * Count translated units present in a partial response.
	 *
	 * @param array $units   Ordered units.
	 * @param array $partial field => chunk_index => translated text.
	 * @return int
	 */
	private function count_translated_units( array $units, array $partial ) {
		$count = 0;

		foreach ( $units as $unit ) {
			$field = isset( $unit['field'] ) ? (string) $unit['field'] : '';
			$index = isset( $unit['index'] ) ? (int) $unit['index'] : 0;

			if ( '' !== $field && isset( $partial[ $field ] ) && is_array( $partial[ $field ] ) && array_key_exists( $index, $partial[ $field ] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Split a large field into progress-reportable chunks.
	 *
	 * Structured payloads are kept atomic because splitting them could produce
	 * invalid JSON or serialized data. Regular content is split on Gutenberg,
	 * HTML, sentence, then whitespace boundaries.
	 *
	 * @param string $text Source text.
	 * @return array<int,string>
	 */
	private function split_text_into_chunks( $text ) {
		$limit = (int) apply_filters( 'automlp_translation_chunk_chars', self::CHARS_PER_CHUNK );
		$limit = max( 500, $limit );

		if ( mb_strlen( $text ) <= $limit || $this->is_atomic_text( $text ) ) {
			return array( $text );
		}

		$segments = preg_split(
			'/(<!--\s*\/wp:[^>]+-->\s*|<\/(?:p|div|section|article|li|h[1-6]|blockquote|tr|td|th)>\s*)/i',
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		if ( ! is_array( $segments ) || empty( $segments ) ) {
			return $this->split_long_segment( $text, $limit );
		}

		$blocks = array();

		foreach ( $segments as $segment ) {
			if ( preg_match( '/^(?:<!--\s*\/wp:[^>]+-->|<\/(?:p|div|section|article|li|h[1-6]|blockquote|tr|td|th)>)/i', ltrim( $segment ) ) && ! empty( $blocks ) ) {
				$blocks[ count( $blocks ) - 1 ] .= $segment;
				continue;
			}

			$blocks[] = $segment;
		}

		return $this->pack_segments( $blocks, $limit );
	}

	/**
	 * Keep JSON/serialized structures together.
	 *
	 * @param string $text Source text.
	 * @return bool
	 */
	private function is_atomic_text( $text ) {
		$trimmed = trim( $text );

		if ( '' === $trimmed ) {
			return true;
		}

		if ( ( 0 === strpos( $trimmed, '{' ) && substr( $trimmed, -1 ) === '}' ) || ( 0 === strpos( $trimmed, '[' ) && substr( $trimmed, -1 ) === ']' ) ) {
			return true;
		}

		return 1 === preg_match( '/^(?:a|O|s|i|b|d):\d+[:;]/', $trimmed );
	}

	/**
	 * Pack logical segments into chunks.
	 *
	 * @param array $segments Source segments.
	 * @param int   $limit    Character ceiling.
	 * @return array<int,string>
	 */
	private function pack_segments( array $segments, $limit ) {
		$chunks  = array();
		$current = '';

		foreach ( $segments as $segment ) {
			$segment = (string) $segment;

			if ( mb_strlen( $segment ) > $limit ) {
				if ( '' !== $current ) {
					$chunks[] = $current;
					$current  = '';
				}

				$chunks = array_merge( $chunks, $this->split_long_segment( $segment, $limit ) );
				continue;
			}

			if ( '' !== $current && mb_strlen( $current . $segment ) > $limit ) {
				$chunks[] = $current;
				$current  = $segment;
				continue;
			}

			$current .= $segment;
		}

		if ( '' !== $current ) {
			$chunks[] = $current;
		}

		return empty( $chunks ) ? array( implode( '', $segments ) ) : $chunks;
	}

	/**
	 * Split an oversized segment on the best available safe boundary.
	 *
	 * @param string $text  Source text.
	 * @param int    $limit Character ceiling.
	 * @return array<int,string>
	 */
	private function split_long_segment( $text, $limit ) {
		$chunks = array();

		while ( mb_strlen( $text ) > $limit ) {
			$window = mb_substr( $text, 0, $limit );
			$cut    = $this->last_boundary( $window, $limit );

			$chunks[] = mb_substr( $text, 0, $cut );
			$text     = mb_substr( $text, $cut );
		}

		if ( '' !== $text ) {
			$chunks[] = $text;
		}

		return $chunks;
	}

	/**
	 * Best boundary before the chunk ceiling.
	 *
	 * @param string $window Candidate chunk.
	 * @param int    $limit  Character ceiling.
	 * @return int
	 */
	private function last_boundary( $window, $limit ) {
		$minimum    = (int) floor( $limit * 0.35 );
		$boundaries = array( "\n\n", "\n", '. ', '! ', '? ', '; ', ', ', ' ' );

		foreach ( $boundaries as $boundary ) {
			$pos = mb_strrpos( $window, $boundary );

			if ( false !== $pos && $pos >= $minimum ) {
				return $pos + mb_strlen( $boundary );
			}
		}

		return $limit;
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
