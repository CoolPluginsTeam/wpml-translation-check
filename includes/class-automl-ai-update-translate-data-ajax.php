<?php
/**
 * AJAX handler for recording translation stats to the dashboard (Auto Translation Status).
 * Used by both post translation and string translation when updateTranslateData() is called from the frontend.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and handle wp_ajax_automl_wpml_update_translate_data.
 */
class AUTOML_AI_Update_Translate_Data_Ajax {

	/**
	 * Initialize: register the AJAX action.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_automl_wpml_update_translate_data', array( __CLASS__, 'handle_update_translate_data' ) );
	}

	/**
	 * Handle the update translate data request: verify nonce, map POST to store_options, save.
	 *
	 * @return void
	 */
	public static function handle_update_translate_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => __( 'Unauthorized', 'wpml-translation-check' ) ), 403 );
		}

		$nonce = isset( $_POST['automl_wpml_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['automl_wpml_nonce'] ) ) : '';
if ( empty( $nonce ) && isset( $_POST['translate_data_nonce'] ) ) {
	$nonce = sanitize_text_field( wp_unslash( $_POST['translate_data_nonce'] ) );
}
if ( ! wp_verify_nonce( $nonce, 'automl_wpml_update_translate_data' ) ) {
	wp_send_json_error( array( 'msg' => __( 'Invalid nonce', 'wpml-translation-check' ) ), 400 );
}
    
		$post_id        = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
		$provider       = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$source_lang    = isset( $_POST['sourceLang'] ) ? sanitize_text_field( wp_unslash( $_POST['sourceLang'] ) ) : '';
		$target_lang    = isset( $_POST['targetLang'] ) ? sanitize_text_field( wp_unslash( $_POST['targetLang'] ) ) : '';
		$string_count   = isset( $_POST['totalStringCount'] ) ? absint( $_POST['totalStringCount'] ) : 0;
		$char_count     = isset( $_POST['totalCharacterCount'] ) ? absint( $_POST['totalCharacterCount'] ) : 0;
		$time_taken     = isset($_POST['timeTaken']) ? absint($_POST['timeTaken']) : 0;
		$total_word_count    = isset( $_POST['totalWordCount'] ) ? absint( $_POST['totalWordCount'] ) : 0;
		$editor_type    = isset( $_POST['editorType'] ) ? sanitize_text_field( wp_unslash( $_POST['editorType'] ) ) : '';
		$date           = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$source_string_count = isset( $_POST['sourceStringCount'] ) ? absint( $_POST['sourceStringCount'] ) : 0;
		$source_word_count  = isset( $_POST['sourceWordCount'] ) ? absint( $_POST['sourceWordCount'] ) : 0;
		$source_char_count  = isset( $_POST['sourceCharacterCount'] ) ? absint( $_POST['sourceCharacterCount'] ) : 0;
		$extra_data     = isset( $_POST['extraData'] ) ? sanitize_text_field( wp_unslash( $_POST['extraData'] ) ) : '';
		$bulk_translate = isset( $_POST['bulk_translate'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_translate'] ) ) : '';

		if ( empty( $post_id ) || empty( $provider ) || empty( $source_lang ) || empty( $target_lang ) ) {
			wp_send_json_error( array( 'msg' => __( 'Missing required fields', 'wpml-translation-check' ) ), 400 );
		}
    
		if ( class_exists( 'AUTOML_Ai_Cpt_Dashboard' ) ) {
			$data = array(
				'post_id'              => $post_id,
				'service_provider'     => $provider,
				'source_language'      => $source_lang,
				'target_language'      => $target_lang,
				'string_count'         => (string) $string_count,
				'character_count'      => (string) $char_count,
				'time_taken'           => $time_taken,
				'date_time'            => ! empty( $date ) ? gmdate( 'Y-m-d H:i:s', strtotime( $date ) ) : current_time( 'Y-m-d H:i:s' ),
				'total_word_count'     => (string) $total_word_count,
				'editor_type'          => $editor_type,
				'source_string_count'  => (string) $source_string_count,
				'source_word_count'    => (string) $source_word_count,
				'source_character_count' => (string) $source_char_count,
				'extra_data'           => $extra_data,
				'bulk_translate'       => $bulk_translate,
			);
			AUTOML_Ai_Cpt_Dashboard::store_options( 'automl_ai', 'post_id', 'update', $data );
		}

		wp_send_json_success();
	}
}
