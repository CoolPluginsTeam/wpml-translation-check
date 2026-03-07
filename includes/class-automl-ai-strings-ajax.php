<?php

/**
 * AJAX handlers for WPML String Translation (plugin/theme strings) via Google Translate.
 * Uses WPML ST APIs: icl_get_string_translations(), icl_add_string_translation().
 * Reuses the same nonce as post translation ('automl_wpml_auto_translate_nonce').
 *
 * @package WPML_Auto_Translate
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Strings AJAX handler class.
 */
class AUTOML_AI_Strings_Ajax
{

	/**
	 * Initialize AJAX handlers. Call only when WPML String Translation is active.
	 *
	 * @return void
	 */
	public static function init()
	{
		add_action('wp_ajax_automl_wpml_google_auto_translate_save_string_translations', array(__CLASS__, 'save_string_translations'));
	}

	/**
	 * Save string translations into icl_string_translations via bulk database insert.
	 *
	 * @return void
	 */
	public static function save_string_translations()
	{
		global $wpdb;

		// Read JSON data from request body to avoid max_input_vars limit
		$raw_input = file_get_contents('php://input');
		$json_data = json_decode($raw_input, true);

		// Determine if we're using JSON or POST (backward compatibility)
		$use_json = (json_last_error() === JSON_ERROR_NONE && ! empty($json_data) && isset($json_data['action']));

		if ($use_json) {
			// Extract nonce from JSON for verification
			$nonce = isset($json_data['nonce']) ? sanitize_text_field($json_data['nonce']) : '';
			if (! wp_verify_nonce($nonce, 'automl_wpml_auto_translate_nonce')) {
				wp_send_json_error(array('msg' => esc_html__('Security check failed. Please refresh the page and try again.', 'automl-ai-translation-for-wpml')));
				return;
			}
			$target_lang = isset($json_data['target_lang']) ? sanitize_text_field($json_data['target_lang']) : '';
			$translated_strings = isset($json_data['translated_strings']) && is_array($json_data['translated_strings']) ? $json_data['translated_strings'] : array();
		} else {
			// Fallback to POST (backward compatibility)
			if (! check_ajax_referer('automl_wpml_auto_translate_nonce', 'nonce', false)) {
				wp_send_json_error(array('msg' => esc_html__('Security check failed. Please refresh the page and try again.', 'automl-ai-translation-for-wpml')));
				return;
			}
			$target_lang = isset($_POST['target_lang']) ? sanitize_text_field(wp_unslash($_POST['target_lang'])) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per item
			$translated_strings = isset($_POST['translated_strings']) ? json_decode(sanitize_text_field(wp_unslash($_POST['translated_strings'])), true) : array();
		}

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('msg' => esc_html__('Insufficient permissions.', 'automl-ai-translation-for-wpml')));
		}

		if (! function_exists('icl_add_string_translation')) {
			wp_send_json_error(array('msg' => esc_html__('WPML String Translation is not active.', 'automl-ai-translation-for-wpml')));
		}

		if (empty($target_lang) || empty($translated_strings)) {
			wp_send_json_error(array('msg' => esc_html__('Missing target language or translated strings.', 'automl-ai-translation-for-wpml')));
		}
		// $wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();
		// if ( $wizard_lang !== null && strtolower( (string) $target_lang ) !== strtolower( $wizard_lang ) ) {
		// 	wp_send_json_error(array('msg' => esc_html__('This target language is not allowed. Only the language selected in the setup wizard can be used.', 'automl-ai-translation-for-wpml')));
		// }

		$status = defined('ICL_TM_COMPLETE') ? ICL_TM_COMPLETE : 10;
		$translator_id = get_current_user_id();
		$translation_date = current_time('mysql');

		// Get the table name for icl_string_translations
		$table_name = $wpdb->prefix . 'icl_string_translations';

		// Prepare data for bulk insert
		$rows_data = array();
		$string_ids = array();

		foreach ($translated_strings as $row) {
			$string_id = isset($row['field_key']) ? absint($row['field_key']) : 0;
			$value     = isset($row['translated']) ? $row['translated'] : '';
			if (! $string_id) {
				continue;
			}

			// Sanitize the value
			$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$value = wp_kses_post($value);

			$string_ids[] = $string_id;
			$rows_data[] = array(
				'string_id' => $string_id,
				'language' => $target_lang,
				'value' => $value,
				'status' => $status,
				'translator_id' => $translator_id,
				'translation_date' => $translation_date,
			);
		}

		if (empty($rows_data)) {
			wp_send_json_error(array('msg' => esc_html__('No valid strings to save.', 'automl-ai-translation-for-wpml')));
			return;
		}

		// Build the bulk INSERT query with ON DUPLICATE KEY UPDATE
// This handles both new inserts and updates to existing translations
$values_sql = array();
foreach ($rows_data as $row_data) {
    $values_sql[] = $wpdb->prepare(
        '(%d, %s, %s, %d, %d, %s)',
        $row_data['string_id'],
        $row_data['language'],
        $row_data['value'],
        $row_data['status'],
        $row_data['translator_id'],
        $row_data['translation_date']
    );
}

// Build complete query in one statement with escaped table name
$query = sprintf(
    "INSERT INTO %s (string_id, language, value, status, translator_id, translation_date) VALUES %s ON DUPLICATE KEY UPDATE value = VALUES(value), status = VALUES(status), translator_id = VALUES(translator_id), translation_date = VALUES(translation_date)",
    esc_sql($table_name),
    implode(', ', $values_sql)
);

// Execute the bulk insert/update

$result = $wpdb->query($query); // phpcs:ignore
		$saved = count($string_ids);

		// Clear WPML cache to ensure translations are immediately available
		if (function_exists('icl_update_string_translation_cache')) {
			icl_update_string_translation_cache($string_ids, $target_lang);
		}

		wp_send_json_success(array(
			'msg'   => sprintf(
				/* translators: %d: number of strings */
				esc_html__('%d string(s) translated and saved.', 'automl-ai-translation-for-wpml'),
				$saved
			),
			'saved' => $saved,
		));
	}
}
