<?php
/**
 * Helper functions for AUTOMLP Ai Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class.
 */
class WPML_AT_Helper {

	/**
	 * Get WPML active languages.
	 *
	 * @return array Array of language codes and names.
	 */
	public static function get_wpml_languages() {
		$languages = array();

		if ( function_exists( 'icl_get_languages' ) ) {
			$wpml_languages = icl_get_languages( 'skip_missing=0' );
			if ( ! empty( $wpml_languages ) && is_array( $wpml_languages ) ) {
				foreach ( $wpml_languages as $lang ) {
					$languages[] = array(
						'code' => $lang['code'],
						'name' => $lang['native_name'],
						'flag_url' => $lang['country_flag_url'],
						'locale' => $lang['default_locale'],
					);
				}
			}
		} elseif ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			if ( $sitepress ) {
				$active_languages = $sitepress->get_active_languages();
				if ( ! empty( $active_languages ) && is_array( $active_languages ) ) {
					foreach ( $active_languages as $code => $lang ) {
						$languages[] = array(
							'code' => $code,
							'name' => $lang['native_name'],
							'flag_url' => $lang['country_flag_url'],
							'locale' => $lang['default_locale'],
						);
					}
				}
			}
		}

		return $languages;
	}

	public static function get_default_language() {
		$default_language = null;
		if ( function_exists( 'icl_get_default_language' ) ) {
			$default_language = icl_get_default_language();
		} elseif ( class_exists( 'SitePress' ) ) {
			global $sitepress;
			$default_language = $sitepress->get_default_language();
		}
		return $default_language;
	}

	/**
	 * Get source language for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string Language code.
	 */
	public static function get_post_source_language( $post_id, $post_type ) {
		global $sitepress;
		$source_lang = null;

		if ( $sitepress ) {
			$source_lang = $sitepress->get_language_for_element( $post_id, 'post_' . $post_type );
		}

		if ( ! $source_lang ) {
			$source_lang = apply_filters( 'wpml_default_language', 'en' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}

		return $source_lang;
	}

	/**
	 * Get translations for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return array Translations array.
	 */
	public static function get_post_translations( $post_id, $post_type ) {
		$trid = apply_filters( 'wpml_element_trid', null, $post_id, 'post_' . $post_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( ! $trid ) {
			return array();
		}

		return apply_filters('wpml_get_element_translations', array(),$trid,'post_' . $post_type); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Extract language code and element ID from translation object/array.
	 *
	 * @param object|array $translation Translation object or array.
	 * @return array Array with 'code' and 'element_id'.
	 */
	public static function extract_translation_info( $translation ) {
		if ( is_object( $translation ) ) {
			return array(
				'code'       => isset( $translation->language_code ) ? $translation->language_code : '',
				'element_id' => isset( $translation->element_id ) ? (int) $translation->element_id : 0,
			);
		}

		return array(
			'code'       => isset( $translation['language_code'] ) ? $translation['language_code'] : '',
			'element_id' => isset( $translation['element_id'] ) ? (int) $translation['element_id'] : 0,
		);
	}

	/**
 * Mask an API key for display.
 * Already-masked values (containing * or •) are returned as-is to avoid double-masking.
 *
 * @param string $api_key The API key to mask.
 * @return string Masked API key.
 */
public static function mask_api_key( $api_key ) {
    if ( empty( $api_key ) ) {
        return $api_key;
    }
    if ( strpos( $api_key, '*' ) !== false || strpos( $api_key, '•' ) !== false ) {
        return $api_key;
    }
    if ( strlen( $api_key ) < 12 ) {
        return $api_key;
    }
    $start         = substr( $api_key, 0, 6 );
    $end           = substr( $api_key, -6 );
    $middle_length = strlen( $api_key ) - 12;
    $masked_middle = str_repeat( '*', min( max( $middle_length, 0 ), 24 ) );
    return $start . $masked_middle . $end;
}

	/**
	 * Get existing translation ID for a specific language.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $post_type   Post type.
	 * @param string $target_lang Target language code.
	 * @return int Translation post ID or 0 if not found.
	 */
	public static function get_existing_translation_id( $post_id, $post_type, $target_lang ) {
		$translations = self::get_post_translations( $post_id, $post_type );

		if ( empty( $translations ) || ! is_array( $translations ) ) {
			return 0;
		}

		foreach ( $translations as $lang_code => $translation ) {
			$info = self::extract_translation_info( $translation );

			if ( $info['code'] === $target_lang && $info['element_id'] ) {
				return $info['element_id'];
			}
		}

		return 0;
	}

	/**
	 * Whether WPML Advanced Translation Editor already owns an open job
	 * for this source post and target language.
	 *
	 * Clicking "+" in the language column can create an ATE job (gear icon)
	 * before any translated post exists. AutoMLP must not queue those.
	 *
	 * @param int    $post_id     Source post ID.
	 * @param string $post_type   Post type.
	 * @param string $target_lang Target language code.
	 * @return bool
	 */
	public static function has_open_ate_job( $post_id, $post_type, $target_lang ) {
		global $wpdb;

		$post_id     = absint( $post_id );
		$target_lang = sanitize_text_field( (string) $target_lang );
		$post_type   = sanitize_key( (string) $post_type );

		if ( ! $post_id || '' === $target_lang || '' === $post_type ) {
			return false;
		}

		if ( ! defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) || ! defined( 'ICL_TM_IN_PROGRESS' ) ) {
			return false;
		}

		$element_type = apply_filters( 'wpml_element_type', $post_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$trid         = apply_filters( 'wpml_element_trid', false, $post_id, $element_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( ! $trid ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$editor = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT j.editor
				FROM {$wpdb->prefix}icl_translate_job j
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				WHERE t.trid = %d
					AND t.language_code = %s
					AND ts.status IN (%d, %d)
				ORDER BY j.job_id DESC
				LIMIT 1",
				(int) $trid,
				$target_lang,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			)
		);

		return 'ate' === $editor;
	}

	/**
	 * Bulk translation supported
	 *
	 * @param object $current_screen The current screen object.
	 * @return bool True if bulk translation should be rendered, false otherwise.
	 */
	public static function tranlastable_post_type( $current_screen ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		if ( ( isset( $current_screen->action ) && $current_screen->action === 'add' ) || ( isset( $_GET['post'] ) && isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'edit' ) ) {
			return false;
		}

		if ( ! isset( $current_screen->post_type ) || empty( $current_screen->post_type ) ) {
			return false;
		}

		if ( isset( $current_screen->post_type ) && $current_screen->post_type === 'attachment' ) {
			return false;
		}

		if ( ! in_array( $current_screen->post_type, array( 'page', 'post' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the single target language code allowed by the setup wizard, if any.
	 * When the wizard has been completed with a language selection, only that language
	 * is allowed for string/bulk translation; backend must reject other languages.
	 *
	 * @return string|null Language code (e.g. 'fr') or null if wizard has not restricted language.
	 */
	public static function get_wizard_allowed_language_code() {
		$option = get_option( 'automlp_ai_wizard_selected_language', array() );
		if ( ! is_array( $option ) || empty( $option['code'] ) ) {
			return null;
		}
		return sanitize_text_field( $option['code'] );
	}

	public static function get_providers_key($providers=array(), $removed_mask_filter=false) {
		$api_key = array();

		if(empty($providers) || ! class_exists( '\WordPress\AiClient\AiClient'  )) {
			$providers = array();
		}

		$allowed_providers = array('openai', 'google');

		$providers = array_intersect($providers, $allowed_providers);

		if(function_exists('_wp_register_default_connector_settings')){
			foreach ($providers as $provider) {
				$ai_client_option_name='connectors_ai_'.$provider.'_api_key';

				if(true === $removed_mask_filter){
					remove_filter( "option_{$ai_client_option_name}", '_wp_connectors_mask_api_key' );
				}
				$client_api_key=get_option($ai_client_option_name, '');

				if(true === $removed_mask_filter){
					add_filter( "option_{$ai_client_option_name}", '_wp_connectors_mask_api_key' );
				}

				if(!empty($client_api_key) && is_string($client_api_key)) {
					$api_key[$provider] = $client_api_key;
				}
			}
		}else{
			$wp_ai_providers_options=get_option('wp_ai_client_provider_credentials', array());

			foreach ($wp_ai_providers_options as $provider => $value) {
				if(!empty($value) && is_string($value)) {
					$api_key[$provider] = $value;
				}
			}
		}

		return $api_key;
	}
}

