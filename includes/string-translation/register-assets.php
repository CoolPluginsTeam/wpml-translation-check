<?php

namespace AUTOML_WPML\Includes\String_Translation;

if ( ! defined( 'ABSPATH' ) ) exit;

use WPML_AT_Helper;
/**
 * Register_Assets
 *
 * @package AUTOML_WPML\Includes\String_Translation
 */
class Register_Assets {
	public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

		/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets($hook)
	{
		// Sanitize and validate page parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic, not processing form data.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		$is_string_translation     = ! empty($page) && strpos($page, 'wpml-string-translation/menu/string-translation.php') !== false;
		$needs_ai_services         = $is_string_translation;
		$available_ai_services     = array();

		// Enqueue translation dashboard/post list scripts.
			if ( $needs_ai_services ) {
				// Build from saved credentials so button shows "Add API Key" when key is missing/empty.
				if(class_exists( ( \WordPress\AI_Client\AI_Client::class ) ) ){
				$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
				}else{
					$openai_key = get_option( 'connectors_ai_openai_api_key', '' );
					$google_key = get_option( 'connectors_ai_google_api_key', '' );
					$credentials = array(
						'openai' => $openai_key,
						'google' => $google_key,
					);
				}
				if ( is_array( $credentials ) ) {
					foreach ( array( 'openai', 'google' ) as $provider_id ) {
						if ( ! empty( $credentials[ $provider_id ] ) && is_string( $credentials[ $provider_id ] ) ) {
							$available_ai_services[] = $provider_id;
						}
					}
				}
			}


			$selected_language = get_option( 'automl_ai_wizard_selected_language', array() );
			$languages = WPML_AT_Helper::get_wpml_languages();
			$lang_object = array();
			foreach ($languages as $lang) {
				$lang_object[$lang['code']] = array('name' => $lang['name'], 'flag' => $lang['flag_url']);
			}
			$default_language = WPML_AT_Helper::get_default_language();
			$selected_lang_object = array();
			if ( ! empty( $selected_language['code'] ) ) {
				$selected_lang_object[ $selected_language['code'] ] = array(
					'name' => isset( $selected_language['name'] ) ? $selected_language['name'] : '',
					'flag' => isset( $selected_language['flag_url'] ) ? $selected_language['flag_url'] : '',
				);
			}
			wp_localize_script(
				'automl-wpml-auto-translate-admin',
				'AUTOML_WPML_AUTO_TRANSLATE',
				array(
					'ajax'      => esc_url(admin_url('admin-ajax.php')),
					'nonce'     => wp_create_nonce('automl_wpml_auto_translate_nonce'),
					'languages' => $selected_language,
					'admin_url' => esc_url(admin_url()),
					'i18n'      => array(
						'errorPageId'      => esc_html__('Could not detect page ID for this row.', 'wpml-translation-check'),
						'errorNoSelection' => esc_html__('Please select at least one post to translate.', 'wpml-translation-check'),
						'errorNoLanguage'  => esc_html__('Please select a target language.', 'wpml-translation-check'),
						'errorInvalidData' => esc_html__('Invalid post ID or language.', 'wpml-translation-check'),
						'errorNoStrings'   => esc_html__('No translation strings found.', 'wpml-translation-check'),
						'errorAjax'        => esc_html__('AJAX error while loading content.', 'wpml-translation-check'),
						'errorAjaxSave'    => esc_html__('AJAX error while saving.', 'wpml-translation-check'),
						'errorUnknown'     => esc_html__('Unknown error occurred.', 'wpml-translation-check'),
					),
				)
			);

			// Enqueue bulk translate build files on string translation page.
			if ($is_string_translation) {

				$asset_file = include AUTOML_AI_PLUGIN_DIR . 'assets/bulk-string-translate/index.asset.php';

				wp_enqueue_script(
					'automl-ai-bulk-translate',
					AUTOML_AI_PLUGIN_URL . 'assets/bulk-string-translate/index.js',
					$asset_file['dependencies'],
					$asset_file['version'],
					true
				);

				wp_enqueue_style(
					'automl-ai-bulk-translate',
					AUTOML_AI_PLUGIN_URL . 'assets/bulk-string-translate/index.css',
					array(),
					$asset_file['version']
				);

				// Localize script with necessary data for string translation
				wp_localize_script(
					'automl-ai-bulk-translate',
					'automl_wpml_bulk_translate_object',
					array(
						'taxonomy_page'          => '',
						'languageObject'         => $lang_object,
						'selected_language_object' => $selected_lang_object,
						'ajax_url'               => esc_url( admin_url( 'admin-ajax.php' ) ),  
						'nonce'                  => wp_create_nonce('automl_wpml_auto_translate_nonce'),
						'default_language_slug'  => $default_language,
						'update_translate_data'   => 'automl_wpml_update_translate_data',     
						'update_translate_data_nonce' => wp_create_nonce( 'automl_wpml_update_translate_data' ), 
						'bulkTranslateRouteUrl' => get_rest_url(null, 'automl-bulk-translate'),
						'bulkTranslatePrivateKey' => wp_create_nonce('automl_wpml_bulk_translate_entries_nonce'),
						'automl_wpml_url'           => esc_url(AUTOML_AI_PLUGIN_URL),
						'AIServices' => $available_ai_services,
						'admin_url' => admin_url(),
						'ai_translate_route_url' => get_rest_url(null, 'automl-bulk-translate'),
						'ai_translate_route_nonce' => wp_create_nonce('wp_rest'),
						'ai_translate_nonce' => wp_create_nonce('automl_wpml_ai_translate_nonce'),
						'get_glossary_validate' => wp_create_nonce('automl_wpml_get_glossary_private'),
					)
				);
			}
		}
}

new Register_Assets();