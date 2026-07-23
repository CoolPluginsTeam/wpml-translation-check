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
	 * wpml_save_translation_data triggers wpml_translation_job_saved, which
	 * Job_Listener also listens to. Without this flag the listener would
	 * overwrite the row we are in the middle of finishing.
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * Attach the writer to the dispatcher's seam.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'automlp_write_translation', array( __CLASS__, 'write' ), 10, 4 );
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

		$fields = self::map_to_tids( $translated, $source_map );

		if ( empty( $fields ) ) {
			return new \WP_Error(
				'automlp_no_tid_match',
				__( 'None of the translated fields matched a WPML field id.', 'wpml-translation-check' )
			);
		}

		self::$writing = true;

		try {
			/*
			 * WPML compresses field data itself inside this hook, so values
			 * must be passed as plain text. Encoding them first would store
			 * literal base64 in post_title and post_content.
			 */
			do_action(
				'wpml_save_translation_data',
				array(
					'job_id'   => $wpml_job_id,
					'fields'   => $fields,
					'complete' => 1,
				)
			);
		} catch ( \Throwable $e ) {
			self::$writing = false;

			return new \WP_Error( 'automlp_save_failed', $e->getMessage() );
		}

		self::$writing = false;

		return array(
			'result_id'    => self::translated_id( $job ),
			'saved_fields' => count( $fields ),
		);
	}

	/**
	 * Pair translated values with the tids captured at job time.
	 *
	 * Fields the provider skipped are simply absent; fields it invented are
	 * ignored because they have no tid.
	 *
	 * @param array $translated field_name => translated text.
	 * @param array $source_map field_name => array{tid:int,text:string}.
	 * @return array<string,array>
	 */
	private static function map_to_tids( array $translated, array $source_map ) {
		$fields = array();

		foreach ( $translated as $name => $value ) {
			$name = (string) $name;

			if ( empty( $source_map[ $name ]['tid'] ) ) {
				continue;
			}

			$value = (string) $value;

			if ( '' === trim( $value ) ) {
				continue;
			}

			$fields[ $name ] = array(
				'tid'      => (int) $source_map[ $name ]['tid'],
				'data'     => $value,
				'finished' => 1,
				'format'   => 'base64',
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
