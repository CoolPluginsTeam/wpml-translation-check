<?php
/**
 * Records translation stats for the Auto Translation Status dashboard.
 *
 * Every translation is a queue job saved through the background flow, so
 * stats are recorded on automlp_job_completed.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records queue job results for the CPT dashboard.
 */
class AUTOMLP_AI_Update_Translate_Data_Ajax {

	/**
	 * Initialize: record stats when a queue job completes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'automlp_job_completed', array( __CLASS__, 'record_queue_job' ), 10, 3 );
	}

	/**
	 * Persist one translation status row to the CPT dashboard option.
	 *
	 * @param array $data Dashboard row fields.
	 * @return bool
	 */
	public static function record( array $data ) {
		if ( ! class_exists( 'AUTOMLP_Ai_Cpt_Dashboard' ) ) {
			return false;
		}

		$required = array( 'post_id', 'service_provider', 'source_language', 'target_language' );

		foreach ( $required as $key ) {
			if ( empty( $data[ $key ] ) ) {
				return false;
			}
		}

		$row = array(
			'post_id'                => sanitize_text_field( (string) $data['post_id'] ),
			'service_provider'       => sanitize_text_field( (string) $data['service_provider'] ),
			'source_language'        => sanitize_text_field( (string) $data['source_language'] ),
			'target_language'        => sanitize_text_field( (string) $data['target_language'] ),
			'string_count'           => (string) absint( isset( $data['string_count'] ) ? $data['string_count'] : 0 ),
			'character_count'        => (string) absint( isset( $data['character_count'] ) ? $data['character_count'] : 0 ),
			'time_taken'             => absint( isset( $data['time_taken'] ) ? $data['time_taken'] : 0 ),
			'date_time'              => ! empty( $data['date_time'] ) ? sanitize_text_field( (string) $data['date_time'] ) : current_time( 'Y-m-d H:i:s' ),
			'total_word_count'       => (string) absint( isset( $data['total_word_count'] ) ? $data['total_word_count'] : 0 ),
			'editor_type'            => sanitize_text_field( isset( $data['editor_type'] ) ? (string) $data['editor_type'] : '' ),
			'source_string_count'    => (string) absint( isset( $data['source_string_count'] ) ? $data['source_string_count'] : 0 ),
			'source_word_count'      => (string) absint( isset( $data['source_word_count'] ) ? $data['source_word_count'] : 0 ),
			'source_character_count' => (string) absint( isset( $data['source_character_count'] ) ? $data['source_character_count'] : 0 ),
			'extra_data'             => sanitize_text_field( isset( $data['extra_data'] ) ? (string) $data['extra_data'] : '' ),
			'bulk_translate'         => sanitize_text_field( isset( $data['bulk_translate'] ) ? (string) $data['bulk_translate'] : '1' ),
		);

		AUTOMLP_Ai_Cpt_Dashboard::store_options( 'automlp_ai', 'post_id', 'update', $row );

		return true;
	}

	/**
	 * Record dashboard stats after a background queue job finishes.
	 *
	 * @param int   $job_id Queue job id.
	 * @param array $job    Queue row at dispatch time.
	 * @param array $ctx    Context: translated, source_map, written, time_taken, provider.
	 * @return void
	 */
	public static function record_queue_job( $job_id, $job, $ctx = array() ) {
		if ( empty( $job ) || ! is_array( $job ) ) {
			return;
		}

		$ctx        = is_array( $ctx ) ? $ctx : array();
		$translated = isset( $ctx['translated'] ) && is_array( $ctx['translated'] ) ? $ctx['translated'] : array();
		$source_map = isset( $ctx['source_map'] ) && is_array( $ctx['source_map'] ) ? $ctx['source_map'] : array();
		$written    = isset( $ctx['written'] ) && is_array( $ctx['written'] ) ? $ctx['written'] : array();
		$provider   = ! empty( $ctx['provider'] ) ? (string) $ctx['provider'] : ( isset( $job['provider'] ) ? (string) $job['provider'] : '' );
		$from_lang  = isset( $job['from_lang'] ) ? (string) $job['from_lang'] : '';
		$to_lang    = isset( $job['to_lang'] ) ? (string) $job['to_lang'] : '';
		$kind       = isset( $job['kind'] ) ? (string) $job['kind'] : 'post';
		$source_id  = isset( $job['source_id'] ) ? (int) $job['source_id'] : 0;
		$result_id  = ! empty( $written['result_id'] ) ? (int) $written['result_id'] : 0;
		$time_taken = isset( $ctx['time_taken'] ) ? absint( $ctx['time_taken'] ) : 0;

		if ( '' === $provider || '' === $from_lang || '' === $to_lang ) {
			return;
		}

		$source_texts = array();
		foreach ( $source_map as $entry ) {
			if ( isset( $entry['text'] ) && '' !== trim( (string) $entry['text'] ) ) {
				$source_texts[] = (string) $entry['text'];
			}
		}

		$translated_texts = array_map( 'strval', array_values( $translated ) );

		$source_string_count = count( $source_texts );
		$source_char_count   = self::count_chars( $source_texts );
		$source_word_count   = self::count_words( $source_texts );

		$string_count = count( $translated_texts );
		$char_count   = self::count_chars( $translated_texts );
		$word_count   = self::count_words( $translated_texts );

		if ( 'string' === $kind ) {
			$post_id     = 'strings_' . $to_lang . '_' . (int) $job_id;
			$editor_type = 'strings';
		} else {
			$post_id     = $result_id ? (string) $result_id : (string) $source_id;
			$editor_type = self::detect_editor_type( $source_id );
		}

		if ( '' === $post_id || '0' === $post_id ) {
			return;
		}

		self::record(
			array(
				'post_id'                => $post_id,
				'service_provider'       => $provider,
				'source_language'        => $from_lang,
				'target_language'        => $to_lang,
				'string_count'           => $string_count,
				'character_count'        => $source_char_count,
				'time_taken'             => $time_taken,
				'date_time'              => current_time( 'Y-m-d H:i:s' ),
				'total_word_count'       => $word_count,
				'editor_type'            => $editor_type,
				'source_string_count'    => $source_string_count,
				'source_word_count'      => $source_word_count,
				'source_character_count' => $source_char_count,
				'extra_data'             => wp_json_encode(
					array(
						'queue_job_id' => (int) $job_id,
						'source_id'    => $source_id,
						'kind'         => $kind,
					)
				),
				'bulk_translate'         => '1',
			)
		);
	}

	/**
	 * Detect page builder / editor for dashboard filtering.
	 *
	 * @param int $post_id Source post id.
	 * @return string
	 */
	private static function detect_editor_type( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return 'gutenberg';
		}

		if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}

		return 'gutenberg';
	}

	/**
	 * Count characters across text fragments.
	 *
	 * @param array $texts Texts.
	 * @return int
	 */
	private static function count_chars( array $texts ) {
		$total = 0;

		foreach ( $texts as $text ) {
			$total += function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
		}

		return $total;
	}

	/**
	 * Count whitespace-separated words across text fragments.
	 *
	 * @param array $texts Texts.
	 * @return int
	 */
	private static function count_words( array $texts ) {
		$total = 0;

		foreach ( $texts as $text ) {
			$text = trim( (string) $text );

			if ( '' === $text ) {
				continue;
			}

			$parts  = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
			$total += is_array( $parts ) ? count( $parts ) : 0;
		}

		return $total;
	}
}
