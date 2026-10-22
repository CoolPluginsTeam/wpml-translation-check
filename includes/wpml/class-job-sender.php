<?php
/**
 * Sends content into WPML's job pipeline, assigned to the bot.
 *
 * This is the entry point that replaces the old REST extraction endpoints.
 * We never read the translation package ourselves: WPML builds it, and
 * Job_Listener picks up the result.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

use AUTOMLP_WPML\Includes\Queue\Queue_Table;
use WPML_AT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job_Sender
 */
class Job_Sender {

	/**
	 * Constant that tells Update_Block_Config to inject custom block rules.
	 *
	 * Extraction now happens inside WPML's job creation, so the plugin's two
	 * original constants are never set. Without this, the 162 configured
	 * blocks would be skipped and their attributes never translated.
	 */
	const BUILD_CONSTANT = 'DOING_AUTOMLP_WPML_JOB_BUILD';

	/**
	 * Provider requested for the batch being dispatched in this request.
	 *
	 * WPML creates jobs synchronously inside the send action, so request
	 * scope is enough. A shared transient let one admin's choice leak into
	 * another admin's concurrent batch.
	 *
	 * @var string
	 */
	private static $pending_provider = '';

	/**
	 * Queue one post for one target language.
	 *
	 * @param int    $post_id   Source post id.
	 * @param string $to_lang   Target language code.
	 * @param string $from_lang Source language code. Detected when empty.
	 * @param string $provider  Provider slug. Empty lets the gateway choose.
	 * @return true|\WP_Error
	 */
	public static function send_post( $post_id, $to_lang, $from_lang = '', $provider = '' ) {
		$post_id = absint( $post_id );
		$to_lang = sanitize_text_field( $to_lang );

		if ( ! $post_id || ! $to_lang ) {
			return new \WP_Error( 'automlp_bad_request', __( 'A post and a target language are required.', 'wpml-translation-check' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'automlp_forbidden', __( 'You are not allowed to translate this post.', 'wpml-translation-check' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'automlp_missing_post', __( 'That post no longer exists.', 'wpml-translation-check' ) );
		}

		if ( ! WPML_AT_Helper::is_supported_post( $post ) ) {
			return new \WP_Error( 'automlp_unsupported_editor', WPML_AT_Helper::unsupported_post_message() );
		}

		// Free never re-translates: every path (bulk, retry) ends here.
		if ( WPML_AT_Helper::get_existing_translation_id( $post_id, $post->post_type, $to_lang ) ) {
			return new \WP_Error( 'automlp_already_translated', WPML_AT_Helper::already_translated_message() );
		}

		$bot_id = Bot_Translator::ensure();

		if ( ! $bot_id ) {
			return new \WP_Error( 'automlp_no_bot', __( 'The translator account could not be created.', 'wpml-translation-check' ) );
		}

		if ( '' === $from_lang ) {
			$from_lang = WPML_AT_Helper::get_post_source_language( $post_id, $post->post_type );
		}

		if ( $from_lang === $to_lang ) {
			return new \WP_Error( 'automlp_same_language', __( 'Source and target languages are the same.', 'wpml-translation-check' ) );
		}

		if ( WPML_AT_Helper::has_open_ate_job( $post_id, $post->post_type, $to_lang ) ) {
			return new \WP_Error(
				'automlp_ate_in_progress',
				__( 'WPML Advanced Translation Editor already has an active job for this page and language. Finish or cancel that job first.', 'wpml-translation-check' )
			);
		}

		if ( ! self::classes_available() ) {
			return new \WP_Error( 'automlp_no_wpml_tm', __( 'WPML Translation Management is not available.', 'wpml-translation-check' ) );
		}

		self::flag_build();
		self::remember_provider( $provider );

		try {
			$element = new \WPML_TM_Translation_Batch_Element(
				$post_id,
				'post',
				$from_lang,
				array( $to_lang => \TranslationManagement::TRANSLATE_ELEMENT_ACTION )
			);

			$batch = new \WPML_TM_Translation_Batch(
				array( $element ),
				self::batch_name( $to_lang ),
				array( $to_lang => $bot_id )
			);

			do_action( 'wpml_tm_send_post_jobs', $batch, 'post', null );

		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_batch_failed', $e->getMessage() );
		} finally {
			self::remember_provider( '' );
		}

		return true;
	}

	/**
	 * Send several checked posts to WPML as one batch for one language.
	 *
	 * @param array  $posts    post_id => source language code.
	 * @param string $to_lang  Target language code.
	 * @param string $provider Provider slug.
	 * @return true|\WP_Error
	 */
	private static function send_batch( array $posts, $to_lang, $provider = '' ) {
		if ( ! self::classes_available() ) {
			return new \WP_Error( 'automlp_no_wpml_tm', __( 'WPML Translation Management is not available.', 'wpml-translation-check' ) );
		}

		$bot_id = Bot_Translator::ensure();

		if ( ! $bot_id ) {
			return new \WP_Error( 'automlp_no_bot', __( 'The translator account could not be created.', 'wpml-translation-check' ) );
		}

		self::flag_build();
		self::remember_provider( $provider );

		try {
			$elements = array();

			foreach ( $posts as $post_id => $from_lang ) {
				$elements[] = new \WPML_TM_Translation_Batch_Element(
					(int) $post_id,
					'post',
					(string) $from_lang,
					array( $to_lang => \TranslationManagement::TRANSLATE_ELEMENT_ACTION )
				);
			}

			$batch = new \WPML_TM_Translation_Batch(
				$elements,
				self::batch_name( $to_lang ),
				array( $to_lang => $bot_id )
			);

			do_action( 'wpml_tm_send_post_jobs', $batch, 'post', null );

		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_batch_failed', $e->getMessage() );
		} finally {
			self::remember_provider( '' );
		}

		return true;
	}

	/**
	 * Queue several posts across several languages.
	 *
	 * @param array  $post_ids Source post ids.
	 * @param array  $langs    Target language codes.
	 * @param string $provider Provider slug. Empty lets the gateway choose.
	 * @return array{queued:int,skipped:int,errors:array<string,string>,skip_reasons:array<string,int>}
	 */
	public static function send_posts( array $post_ids, array $langs, $provider = '' ) {
		$queued       = 0;
		$skipped      = 0;
		$errors       = array();
		$skip_reasons = array(
			'already_translated' => 0,
			'ate_active'         => 0,
			'no_permission'      => 0,
			'unsupported_editor' => 0,
		);

		// Outcome per post_lang key, so the modal can update each row.
		$items = array();

		// Posts that passed every check: to_lang => post_id => from_lang.
		$eligible = array();

		foreach ( array_map( 'absint', $post_ids ) as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$post_type = get_post_type( $post_id );
			$from_lang = WPML_AT_Helper::get_post_source_language( $post_id, $post_type );

			$supported = WPML_AT_Helper::is_supported_post( $post_id );

			foreach ( array_map( 'sanitize_text_field', $langs ) as $to_lang ) {
				// Classic editor, other builders and Elementor templates.
				if ( ! $supported ) {
					++$skipped;
					++$skip_reasons['unsupported_editor'];
					$errors[ $post_id . '_' . $to_lang ] = WPML_AT_Helper::unsupported_post_message();
					$items[ $post_id . '_' . $to_lang ]  = 'unsupported_editor';
					continue;
				}

				// Already translated: nothing to do.
				if ( WPML_AT_Helper::get_existing_translation_id( $post_id, $post_type, $to_lang ) ) {
					++$skipped;
					++$skip_reasons['already_translated'];
					$errors[ $post_id . '_' . $to_lang ] = WPML_AT_Helper::already_translated_message();
					$items[ $post_id . '_' . $to_lang ]  = 'already_translated';
					continue;
				}

				// ATE already owns this language (e.g. "+" opened ATE, gear icon, no post yet).
				if ( WPML_AT_Helper::has_open_ate_job( $post_id, $post_type, $to_lang ) ) {
					++$skipped;
					++$skip_reasons['ate_active'];
					$errors[ $post_id . '_' . $to_lang ] = WPML_AT_Helper::open_job_message();
					$items[ $post_id . '_' . $to_lang ]  = 'ate_active';
					continue;
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					++$skipped;
					++$skip_reasons['no_permission'];
					$errors[ $post_id . '_' . $to_lang ] = __( 'You are not allowed to translate this post.', 'wpml-translation-check' );
					$items[ $post_id . '_' . $to_lang ]  = 'no_permission';
					continue;
				}

				if ( $from_lang === $to_lang ) {
					$errors[ $post_id . '_' . $to_lang ] = __( 'Source and target languages are the same.', 'wpml-translation-check' );
					$items[ $post_id . '_' . $to_lang ]  = 'error';
					continue;
				}

				$eligible[ $to_lang ][ $post_id ] = $from_lang;
			}
		}

		// One WPML batch per language instead of one per post: WPML's per-batch
		// work (including its remote calls) then runs once, not per post.
		foreach ( $eligible as $to_lang => $posts ) {
			$before = count( Job_Listener::created_ids() );
			$sent   = self::send_batch( $posts, $to_lang, $provider );
			$rows   = array_slice( Job_Listener::created_ids(), $before );
			$made   = array();

			foreach ( $rows as $row_id ) {
				$row = Queue_Table::get( (int) $row_id );

				if ( $row ) {
					$made[ (int) $row['source_id'] ] = true;
				}
			}

			foreach ( array_keys( $posts ) as $post_id ) {
				$key = $post_id . '_' . $to_lang;

				if ( isset( $made[ $post_id ] ) ) {
					$items[ $key ] = 'queued';
					++$queued;
					continue;
				}

				$errors[ $key ] = is_wp_error( $sent ) ? $sent->get_error_message() : __( 'WPML did not create a translation job for this item.', 'wpml-translation-check' );
				$items[ $key ]  = 'error';
			}
		}

		return array(
			'queued'       => $queued,
			'skipped'      => $skipped,
			'errors'       => $errors,
			'skip_reasons' => $skip_reasons,
			'items'        => $items,
		);
	}

	/**
	 * Queue a batch of String Translation strings.
	 *
	 * String elements have to be repacked by ST's own factory filter before
	 * they can be dispatched, otherwise the batch cannot be iterated.
	 *
	 * @param array  $string_ids String ids.
	 * @param string $to_lang    Target language code.
	 * @param string $from_lang  Source language code.
	 * @param string $provider   Provider slug. Empty lets the gateway choose.
	 * @return true|\WP_Error
	 */
	public static function send_strings( array $string_ids, $to_lang, $from_lang = '', $provider = '' ) {
		$string_ids = array_values( array_filter( array_map( 'absint', $string_ids ) ) );
		$to_lang    = sanitize_text_field( $to_lang );

		if ( empty( $string_ids ) || ! $to_lang ) {
			return new \WP_Error( 'automlp_bad_request', __( 'Strings and a target language are required.', 'wpml-translation-check' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'automlp_forbidden', __( 'You are not allowed to translate strings.', 'wpml-translation-check' ) );
		}

		$bot_id = Bot_Translator::ensure();

		if ( ! $bot_id ) {
			return new \WP_Error( 'automlp_no_bot', __( 'The translator account could not be created.', 'wpml-translation-check' ) );
		}

		if ( ! self::classes_available() ) {
			return new \WP_Error( 'automlp_no_wpml_tm', __( 'WPML Translation Management is not available.', 'wpml-translation-check' ) );
		}

		if ( '' === $from_lang ) {
			$from_lang = WPML_AT_Helper::get_default_language();
		}

		self::flag_build();
		self::remember_provider( $provider );

		try {
			$elements = array();

			foreach ( $string_ids as $string_id ) {
				$elements[] = new \WPML_TM_Translation_Batch_Element(
					$string_id,
					'string',
					$from_lang,
					array( $to_lang => \TranslationManagement::TRANSLATE_ELEMENT_ACTION )
				);
			}

			$batch_name = self::batch_name( $to_lang );

			// String Translation converts 'string' elements into an
			// 'st-batch' element here. Skipping this filter produces a batch
			// that send_jobs cannot process.
			$elements = apply_filters( 'wpml_tm_batch_factory_elements', $elements, $batch_name );

			$batch = new \WPML_TM_Translation_Batch(
				$elements,
				$batch_name,
				array( $to_lang => $bot_id )
			);

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WPML's own hook name.
			do_action( 'wpml_tm_send_st-batch_jobs', $batch, 'st-batch', null );

		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_batch_failed', $e->getMessage() );
		} finally {
			self::remember_provider( '' );
		}

		return true;
	}

	/**
	 * Record the requested provider for the jobs about to be created.
	 *
	 * Job_Listener reads this while capturing, so the user's choice in the
	 * picker survives into the queue row.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private static function remember_provider( $provider ) {
		self::$pending_provider = is_string( $provider ) ? sanitize_key( $provider ) : '';
	}

	/**
	 * Provider requested for the batch currently being created.
	 *
	 * @return string
	 */
	public static function requested_provider() {
		return self::$pending_provider;
	}

	/**
	 * Define the build constant so custom block rules are injected.
	 *
	 * @return void
	 */
	private static function flag_build() {
		if ( ! defined( self::BUILD_CONSTANT ) ) {
			define( self::BUILD_CONSTANT, true );
		}
	}

	/**
	 * Are the WPML Translation Management classes loaded?
	 *
	 * @return bool
	 */
	private static function classes_available() {
		return class_exists( '\WPML_TM_Translation_Batch_Element' )
			&& class_exists( '\WPML_TM_Translation_Batch' )
			&& class_exists( '\TranslationManagement' );
	}

	/**
	 * Human-readable batch name shown in WPML's job list.
	 *
	 * @param string $to_lang Target language code.
	 * @return string
	 */
	private static function batch_name( $to_lang ) {
		return sprintf(
			/* translators: 1: language code, 2: date and time */
			__( 'AutoMLP AI (%1$s) %2$s', 'wpml-translation-check' ),
			$to_lang,
			gmdate( 'Y-m-d H:i:s' )
		);
	}
}
