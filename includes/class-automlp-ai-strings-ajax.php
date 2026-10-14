<?php
/**
 * AJAX handlers for WPML String Translation (plugin/theme strings) via Google Translate.
 * Uses WPML ST APIs: icl_get_string_translations(), icl_add_string_translation().
 * Reuses the same nonce as post translation ('automlp_wpml_auto_translate_nonce').
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strings AJAX handler class.
 */
class AUTOMLP_AI_Strings_Ajax {


	/**
	 * Initialize AJAX handlers. Call only when WPML String Translation is active.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_automlp_wpml_google_auto_translate_save_string_translations', array( __CLASS__, 'save_string_translations' ) );
	}

	/**
	 * Save string translations through WPML String Translation's API (legacy fallback).
	 *
	 * @return void
	 */
	public static function save_string_translations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Insufficient permissions.', 'wpml-translation-check' ) ) );
		}

		// Read JSON data from request body to avoid max_input_vars limit.
		$raw_input = file_get_contents( 'php://input' );

		if ( ! is_string( $raw_input ) ) {
			wp_send_json_error( 'Invalid request body' );
		}

		if ( strlen( $raw_input ) > 1048576 ) { // 1MB
			wp_send_json_error( 'Payload too large' );
		}

		$json_data = json_decode( $raw_input, true );

		// Determine if we're using JSON or POST (backward compatibility).
		$use_json = ( json_last_error() === JSON_ERROR_NONE && ! empty( $json_data ) && isset( $json_data['action'] ) );

		if ( $use_json ) {
			// Extract nonce from JSON for verification.
			$nonce = isset( $json_data['nonce'] ) ? sanitize_text_field( $json_data['nonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'automlp_wpml_auto_translate_nonce' ) ) {
				wp_send_json_error( array( 'msg' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'wpml-translation-check' ) ) );
				return;
			}
			$target_lang        = isset( $json_data['target_lang'] ) ? sanitize_text_field( $json_data['target_lang'] ) : '';
			$translated_strings = isset( $json_data['translated_strings'] ) && is_array( $json_data['translated_strings'] ) ? $json_data['translated_strings'] : array();
		} else {
			// Fallback to POST (backward compatibility).
			if ( ! check_ajax_referer( 'automlp_wpml_auto_translate_nonce', 'nonce', false ) ) {
				wp_send_json_error( array( 'msg' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'wpml-translation-check' ) ) );
				return;
			}
			$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per item
			$translated_strings = isset( $_POST['translated_strings'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['translated_strings'] ) ), true ) : array();
		}

		if ( ! function_exists( 'icl_add_string_translation' ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'WPML String Translation is not active.', 'wpml-translation-check' ) ) );
		}

		if ( empty( $target_lang ) || empty( $translated_strings ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'Missing target language or translated strings.', 'wpml-translation-check' ) ) );
		}
		$wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();
		if ( null !== $wizard_lang && strtolower( (string) $target_lang ) !== strtolower( $wizard_lang ) ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'This target language is not allowed. Only the language selected in the setup wizard can be used.', 'wpml-translation-check' ) ) );
		}

		$status        = defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10;
		$translator_id = get_current_user_id();
		$allowed       = array(
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);

		$saved = 0;

		// Legacy fallback only: the string modal now uses the background queue.
		// Writes go through WPML String Translation's own API so its caches,
		// MO files and status bookkeeping stay correct.
		foreach ( $translated_strings as $row ) {
			$string_id = isset( $row['field_key'] ) ? absint( $row['field_key'] ) : 0;
			$value     = isset( $row['translated'] ) && is_scalar( $row['translated'] ) ? (string) $row['translated'] : '';

			if ( ! $string_id || '' === trim( $value ) ) {
				continue;
			}

			$value = wp_kses( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $allowed );

			if ( icl_add_string_translation( $string_id, $target_lang, $value, $status, $translator_id ) ) {
				++$saved;
			}
		}

		if ( 0 === $saved ) {
			wp_send_json_error( array( 'msg' => esc_html__( 'No valid strings to save.', 'wpml-translation-check' ) ) );
		}

		wp_send_json_success(
			array(
				'msg'   => sprintf(
					/* translators: %d: number of strings */
					esc_html__( '%d string(s) translated and saved.', 'wpml-translation-check' ),
					$saved
				),
				'saved' => $saved,
			)
		);
	}
}
