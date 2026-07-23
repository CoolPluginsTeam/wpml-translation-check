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
	 * Queue one post for one target language.
	 *
	 * @param int    $post_id   Source post id.
	 * @param string $to_lang   Target language code.
	 * @param string $from_lang Source language code. Detected when empty.
	 * @return true|\WP_Error
	 */
	public static function send_post( $post_id, $to_lang, $from_lang = '' ) {
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

		if ( ! self::classes_available() ) {
			return new \WP_Error( 'automlp_no_wpml_tm', __( 'WPML Translation Management is not available.', 'wpml-translation-check' ) );
		}

		self::flag_build();

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
		}

		return true;
	}

	/**
	 * Queue several posts across several languages.
	 *
	 * @param array $post_ids Source post ids.
	 * @param array $langs    Target language codes.
	 * @return array{queued:int,skipped:int,errors:array<string,string>}
	 */
	public static function send_posts( array $post_ids, array $langs ) {
		$queued  = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( array_map( 'absint', $post_ids ) as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$from_lang = WPML_AT_Helper::get_post_source_language( $post_id, get_post_type( $post_id ) );

			foreach ( array_map( 'sanitize_text_field', $langs ) as $to_lang ) {
				// Already translated: nothing to do.
				if ( WPML_AT_Helper::get_existing_translation_id( $post_id, get_post_type( $post_id ), $to_lang ) ) {
					++$skipped;
					continue;
				}

				$sent = self::send_post( $post_id, $to_lang, $from_lang );

				if ( is_wp_error( $sent ) ) {
					$errors[ $post_id . '_' . $to_lang ] = $sent->get_error_message();
					continue;
				}

				++$queued;
			}
		}

		return array(
			'queued'  => $queued,
			'skipped' => $skipped,
			'errors'  => $errors,
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
	 * @return true|\WP_Error
	 */
	public static function send_strings( array $string_ids, $to_lang, $from_lang = '' ) {
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

			do_action( 'wpml_tm_send_st-batch_jobs', $batch, 'st-batch', null );

		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_batch_failed', $e->getMessage() );
		}

		return true;
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
