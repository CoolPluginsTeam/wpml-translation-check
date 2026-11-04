<?php
/**
 * Saves finished translations back through WPML.
 *
 * This is the only place that writes translated content. Everything goes
 * through wpml_save_translation_data, which means WPML creates the translated
 * post, links it via trid, rebuilds page-builder layouts and marks its own job
 * complete. No post is inserted directly.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writer
 */
class Writer {

	/**
	 * Guard against reacting to our own save.
	 *
	 * The wpml_save_translation_data action triggers wpml_translation_job_saved, which
	 * Job_Listener also listens to. Without this flag the listener would
	 * overwrite the row we are in the middle of finishing.
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * Value of WPML's review mode "Wait for review before publishing".
	 */
	const REVIEW_BEFORE_PUBLISH = 'before-publish';

	/**
	 * Attach the writer to the dispatcher's seam.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'automlp_write_translation', array( __CLASS__, 'write' ), 10, 4 );
		add_filter( 'wpml_pre_save_pro_translation', array( __CLASS__, 'hold_for_review' ), 10, 2 );
	}

	/**
	 * Keep a new translation as a draft when WPML is set to review before publishing.
	 *
	 * WPML applies "After auto-translation > Wait for review before publishing"
	 * only to its own automatic jobs, and the jobs saved here are not flagged as
	 * automatic. Without this, WPML publishes the new translation as soon as the
	 * original is published. This follows what WPML does for its own jobs: only a
	 * new translation is held, an existing one keeps its status.
	 *
	 * Reads WPML's \WPML\Setup\Option, which is not a documented API (verified
	 * against WPML 5.0.0), so it is guarded and does nothing if it is missing.
	 *
	 * @param array       $postarr Post array WPML is about to save.
	 * @param object|null $job     WPML translation job.
	 * @return array
	 */
	public static function hold_for_review( $postarr, $job = null ) {
		if ( ! self::$writing || ! is_array( $postarr ) || ! empty( $postarr['ID'] ) ) {
			return $postarr;
		}

		if ( ! is_callable( array( '\WPML\Setup\Option', 'getReviewMode' ) ) || self::REVIEW_BEFORE_PUBLISH !== \WPML\Setup\Option::getReviewMode() ) {
			return $postarr;
		}

		if ( self::is_excluded_from_review( $job ) ) {
			return $postarr;
		}

		$postarr['post_status'] = 'draft';

		return $postarr;
	}

	/**
	 * Is this job left out of review? Same exclusions as WPML's own review:
	 * the posts page, string and package jobs, and what a site filters out.
	 *
	 * @param object|null $job WPML translation job.
	 * @return bool
	 */
	private static function is_excluded_from_review( $job ) {
		if ( ! is_object( $job ) ) {
			return false;
		}

		$original_id = isset( $job->original_doc_id ) ? (int) $job->original_doc_id : 0;

		if ( $original_id && (int) get_option( 'page_for_posts' ) === $original_id ) {
			return true;
		}

		$type     = isset( $job->original_post_type ) ? (string) $job->original_post_type : '';
		$excluded = 0 === strpos( $type, 'st-batch' ) || 0 === strpos( $type, 'package' );

		/** This filter is documented in sitepress-multilingual-cms/classes/ATE/Review/ApplyJob.php */
		return (bool) apply_filters( 'wpml_tm_skip_element_type_from_review', $excluded, $type );
	}

	/**
	 * Are we currently inside a save?
	 *
	 * @return bool
	 */
	public static function is_writing() {
		return self::$writing;
	}

	/**
	 * Save translated fields against their WPML tids.
	 *
	 * @param mixed $result     Unused incoming filter value.
	 * @param array $translated field_name => translated text.
	 * @param array $job        Queue row.
	 * @param array $source_map field_name => array{tid:int,text:string}.
	 * @return array|\WP_Error
	 */
	public static function write( $result, $translated, $job, $source_map ) {
		unset( $result );

		$wpml_job_id = isset( $job['wpml_job_id'] ) ? (int) $job['wpml_job_id'] : 0;

		if ( ! $wpml_job_id ) {
			return new \WP_Error(
				'automlp_no_wpml_job',
				__( 'This queue row is not linked to a WPML job.', 'wpml-translation-check' )
			);
		}

		// A recovered job may already have been saved before its run died.
		if ( self::is_completed_in_wpml( $wpml_job_id ) ) {
			$incomplete = self::verify_translated_post( $job );

			if ( is_wp_error( $incomplete ) ) {
				return $incomplete;
			}

			return array(
				'result_id'    => self::translated_id( $job ),
				'saved_fields' => 0,
			);
		}

		$fields = self::map_to_tids( $translated, $source_map );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		if ( empty( $fields ) ) {
			return new \WP_Error(
				'automlp_no_tid_match',
				__( 'None of the translated fields matched a WPML field id.', 'wpml-translation-check' )
			);
		}

		$validation_errors = array();

		/*
		 * wpml_save_translation_data is an action, so a rejected save looks like
		 * success. WPML reports validation failures through this hook; capture
		 * them for this call only.
		 */
		$capture = static function ( $validation_results ) use ( &$validation_errors ) {
			$messages = is_array( $validation_results ) && isset( $validation_results['messages'] )
				? (array) $validation_results['messages']
				: array();

			if ( empty( $messages ) ) {
				$messages = array( __( 'Submitted data is not valid.', 'wpml-translation-check' ) );
			}

			foreach ( $messages as $message ) {
				$validation_errors[] = wp_strip_all_tags( (string) $message );
			}
		};

		add_action( 'wpml_translation_validation_failed', $capture, 10, 1 );

		self::$writing = true;

		try {
			/*
			 * WPML compresses field data itself, so values must be passed as
			 * plain text. Encoding them first would store literal base64 in
			 * post_title and post_content.
			 */
			$payload = array(
				'job_id'   => $wpml_job_id,
				'fields'   => $fields,
				'complete' => 1,
			);

			if ( function_exists( 'wpml_tm_save_data' ) ) {
				// Same handler the wpml_save_translation_data action runs, but
				// without WPML's editor redirect (a Location header would leak
				// into REST and cron responses) and with its return value.
				if ( false === wpml_tm_save_data( $payload, false ) && empty( $validation_errors ) ) {
					$validation_errors[] = __( 'WPML did not accept the submitted translation.', 'wpml-translation-check' );
				}
			} else {
				do_action( 'wpml_save_translation_data', $payload );
			}
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'automlp_save_failed', $e->getMessage() );
		} finally {
			self::$writing = false;
			remove_action( 'wpml_translation_validation_failed', $capture, 10 );
		}

		$failure = self::save_failure( $wpml_job_id, $validation_errors );

		if ( '' !== $failure ) {
			return new \WP_Error(
				'automlp_save_rejected',
				sprintf(
					/* translators: 1: WPML job id, 2: reason reported by WPML */
					__( 'WPML rejected the translation for job #%1$d: %2$s', 'wpml-translation-check' ),
					$wpml_job_id,
					$failure
				)
			);
		}

		$incomplete = self::verify_translated_post( $job );

		if ( is_wp_error( $incomplete ) ) {
			return $incomplete;
		}

		return array(
			'result_id'    => self::translated_id( $job ),
			'saved_fields' => count( $fields ),
		);
	}

	/**
	 * Confirm WPML created the translated post for a completed job.
	 *
	 * WPML applies custom fields and page builder data itself, following its
	 * Custom Fields Translation settings, so only its result is checked here.
	 *
	 * @param array $job Queue row.
	 * @return true|\WP_Error
	 */
	private static function verify_translated_post( array $job ) {
		$kind = isset( $job['kind'] ) ? (string) $job['kind'] : 'post';

		if ( 'post' !== $kind || self::translated_id( $job ) ) {
			return true;
		}

		return new \WP_Error(
			'automlp_no_translated_post',
			__( 'WPML accepted the translation but no translated post was found.', 'wpml-translation-check' )
		);
	}

	/**
	 * Has WPML already closed this job as translated?
	 *
	 * @param int $wpml_job_id WPML job id.
	 * @return bool
	 */
	private static function is_completed_in_wpml( $wpml_job_id ) {
		if ( ! function_exists( 'wpml_tm_load_job_factory' ) ) {
			return false;
		}

		$job = wpml_tm_load_job_factory()->get_translation_job( (int) $wpml_job_id, false, 0, false );

		return $job && is_object( $job ) && isset( $job->translated ) && 1 === (int) $job->translated;
	}

	/**
	 * Why WPML refused a save, or '' when it accepted it.
	 *
	 * Uses WPML's job factory rather than querying its tables directly.
	 *
	 * @param int      $wpml_job_id       WPML job id.
	 * @param string[] $validation_errors Messages captured from WPML validation.
	 * @return string
	 */
	private static function save_failure( $wpml_job_id, array $validation_errors ) {
		if ( ! empty( $validation_errors ) ) {
			return implode( ' | ', array_unique( $validation_errors ) );
		}

		if ( ! function_exists( 'wpml_tm_load_job_factory' ) ) {
			return '';
		}

		$job = wpml_tm_load_job_factory()->get_translation_job( (int) $wpml_job_id, false, 0, false );

		if ( ! $job || ! is_object( $job ) ) {
			return __( 'the job no longer exists.', 'wpml-translation-check' );
		}

		// Only judge when WPML exposes the flag; never fail a save on a guess.
		if ( isset( $job->translated ) && 1 !== (int) $job->translated ) {
			return __( 'the job was left open, so WPML did not apply the translation.', 'wpml-translation-check' );
		}

		return '';
	}

	/**
	 * Pair translated values with the tids captured at job time.
	 *
	 * Fields it invented are ignored because they have no tid. A source field
	 * with text that came back missing or empty fails the whole save, so a
	 * partial translation is never reported as done.
	 *
	 * @param array $translated field_name => translated text.
	 * @param array $source_map field_name => array{tid:int,text:string}.
	 * @return array<string,array>|\WP_Error
	 */
	private static function map_to_tids( array $translated, array $source_map ) {
		$fields = array();

		foreach ( $source_map as $name => $entry ) {
			$has_text = isset( $entry['text'] ) && '' !== trim( (string) $entry['text'] );

			if ( ! $has_text || empty( $entry['tid'] ) ) {
				continue;
			}

			if ( ! isset( $translated[ $name ] ) || '' === trim( (string) $translated[ $name ] ) ) {
				return new \WP_Error(
					'automlp_missing_field',
					sprintf(
						/* translators: %s: WPML field name */
						__( 'The translation for field "%s" is missing or empty.', 'wpml-translation-check' ),
						sanitize_text_field( (string) $name )
					)
				);
			}
		}

		foreach ( $translated as $name => $value ) {
			$name = (string) $name;

			if ( empty( $source_map[ $name ]['tid'] ) ) {
				continue;
			}

			$value = (string) $value;

			$fields[ $name ] = array(
				// WPML's own editors send field_type; handlers on
				// wpml_pro_translation_completed (WCML) read it.
				'field_type' => $name,
				'tid'        => (int) $source_map[ $name ]['tid'],
				'data'       => $value,
				'finished'   => 1,
				'format'     => 'base64',
			);
		}

		return $fields;
	}

	/**
	 * Resolve the post id WPML created for this job.
	 *
	 * @param array $job Queue row.
	 * @return int
	 */
	private static function translated_id( array $job ) {
		$source_id = isset( $job['source_id'] ) ? (int) $job['source_id'] : 0;
		$to_lang   = isset( $job['to_lang'] ) ? (string) $job['to_lang'] : '';
		$kind      = isset( $job['kind'] ) ? (string) $job['kind'] : 'post';

		if ( ! $source_id || ! $to_lang || 'post' !== $kind ) {
			return 0;
		}

		$post_type = get_post_type( $source_id );

		if ( ! $post_type ) {
			return 0;
		}

		$translated = apply_filters( 'wpml_object_id', $source_id, $post_type, false, $to_lang );

		return $translated ? (int) $translated : 0;
	}
}
