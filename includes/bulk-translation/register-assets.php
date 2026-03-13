<?php

namespace AUTOMLP_WPML\Includes\Bulk_Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;

/**
 * Register_Assets
 *
 * @package AUTOMLP_WPML\Includes\Bulk_Translation
 */
class Register_Assets {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_translate_assets' ) );
	}

	public function enqueue_bulk_translate_assets() {
		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;

		if ( ! $current_screen ) {
			return;
		}

		if ( ! class_exists( WPML_AT_Helper::class ) || ! WPML_AT_Helper::tranlastable_post_type( $current_screen ) ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';

		if ( 'trash' === $post_status ) {
			return;
		}

		$automlp_current_language=apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();

		wp_enqueue_script( 'automlp-admin', AUTOMLP_AI_PLUGIN_URL . 'assets/js/automlp-admin.min.js', array(), AUTOMLP_AI_VERSION, true );
		wp_localize_script(
			'automlp-admin',
			'automlp_wpml_admin_object',
			array(
				'wizardLanguage' => $wizard_lang,
				'currentLanguage' => $automlp_current_language,
			)
		);


		if(isset($automlp_current_language) && !empty($automlp_current_language)) {
			if($automlp_current_language === $wizard_lang) {
				return;
			}
		}

		$post_label    = __( 'Pages', 'wpml-translation-check' );
		$post_singular_label = '';
		$taxonomy_page = false;

		if ( isset( $current_screen->post_type ) ) {
			$post_type = $current_screen->post_type;

			if ( isset( get_post_type_object( $post_type )->label ) && ! empty( get_post_type_object( $post_type )->label ) ) {
				$post_object = get_post_type_object( $post_type );
				$post_label = $post_object->label;

				if(isset($post_object->labels->singular_name) && !empty($post_object->labels->singular_name)) {
					$post_singular_label = $post_object->labels->singular_name;
				}

				$post_object=null;
			}

			if ( isset( $current_screen->taxonomy ) && ! empty( $current_screen->taxonomy ) ) {
				$taxonomy_page   = $current_screen->taxonomy;
				$taxonomy_object = get_taxonomy( $current_screen->taxonomy );

				if ( isset( $taxonomy_object->label ) && ! empty( $taxonomy_object->label ) ) {
					$post_label = $taxonomy_object->label;

					if ( isset( $taxonomy_object->labels->singular_name ) && ! empty( $taxonomy_object->labels->singular_name ) ) {
						$post_label = $taxonomy_object->labels->singular_name;
					}
				}
			}
		}

		$slug_translation_option = get_option( 'automlp_wpml_slug_translation_option', 'title_translate' );

		$editor_script_asset = include AUTOMLP_AI_PLUGIN_DIR . 'assets/bulk-translate/index.asset.php';

		$rtl      = function_exists( 'is_rtl' ) ? is_rtl() : false;
		$css_file = $rtl ? 'index-rtl.css' : 'index.css';

		wp_enqueue_script( 'automlp-wpml-bulk-translate', AUTOMLP_AI_PLUGIN_URL . 'assets/bulk-translate/index.js', $editor_script_asset['dependencies'], $editor_script_asset['version'], true );
		wp_enqueue_style( 'automlp-wpml-bulk-translate', AUTOMLP_AI_PLUGIN_URL . 'assets/bulk-translate/' . $css_file, array(), $editor_script_asset['version'] );

		$languages            = WPML_AT_Helper::get_wpml_languages();
		$selected_language    = get_option( 'automlp_ai_wizard_selected_language', array() );
		$selected_lang_object = array();
		if ( ! empty( $selected_language['code'] ) ) {
			$selected_lang_object[ $selected_language['code'] ] = array(
				'name' => isset( $selected_language['name'] ) ? $selected_language['name'] : '',
				'flag' => isset( $selected_language['flag_url'] ) ? $selected_language['flag_url'] : '',
			);
		}
		$lang_object = array();

		$default_language      = WPML_AT_Helper::get_default_language();
		$default_language_slug = $default_language;

		foreach ( $languages as $lang ) {
			$lang_object[ $lang['code'] ] = array(
				'name'   => $lang['name'],
				'flag'   => $lang['flag_url'],
				'locale' => $lang['locale'],
			);
		}

		$available_ai_services = array();

		$get_providers_key=WPML_AT_Helper::get_providers_key(array('openai', 'google'));
		$credentials = array();

		if(isset($get_providers_key['openai']) && !empty($get_providers_key['openai'])){
			$credentials['openai']=true;
		}

		if(isset($get_providers_key['google']) && !empty($get_providers_key['google'])){
			$credentials['google']=true;
		}

		if ( is_array( $credentials ) ) {
			foreach ( array( 'openai', 'google' ) as $provider_id ) {
				if ( ! empty( $credentials[ $provider_id ] ) && true === $credentials[ $provider_id ] ) {
					$available_ai_services[] = $provider_id;
				}
			}
		}

		$extra_data = array();

		$ai_max_tokens = get_option( 'automlp_wpml_ai_request_token_per_request', 500 );
		$ai_batch_size = get_option( 'automlp_wpml_ai_request_batch_size', 5 );

		wp_localize_script(
			'automlp-wpml-bulk-translate',
			'automlp_wpml_bulk_translate_object',
			array_merge(
				array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'languageObject'             => $lang_object,
					'selected_language_object'   => $selected_lang_object,
					'nonce'                      => wp_create_nonce( 'wp_rest' ),
					'bulkTranslateRouteUrl'      => get_rest_url( null, 'automlp-bulk-translate' ),
					'bulkTranslatePrivateKey'    => wp_create_nonce( 'automlp_wpml_bulk_translate_entries_nonce' ),
					'pendingPostsIdsKey'         => wp_create_nonce( 'automlp_wpml_pending_posts_ids_nonce' ),
					'automlp_wpml_url'            => esc_url( AUTOMLP_AI_PLUGIN_URL ),
					'AIServices'                 => $available_ai_services,
					'admin_url'                  => admin_url(),
					'ai_translate_route_nonce'   => wp_create_nonce( 'wp_rest' ),
					'ai_translate_nonce'         => wp_create_nonce( 'automlp_wpml_ai_translate_nonce' ),
					'get_glossary_validate'      => wp_create_nonce( 'automlp_wpml_get_glossary_private' ),
					'post_label'                 => $post_label,
					'post_singular_label'        => $post_singular_label && !empty($post_singular_label) ? $post_singular_label : $post_label,
					'update_translate_data'      => 'automlp_wpml_update_translate_data',
					'slug_translation_option'    => $slug_translation_option,
					'taxonomy_page'              => $taxonomy_page,
					'AIRequestMaxTokens'         => $ai_max_tokens,
					'AIRequestBatchSize'         => $ai_batch_size,
					'automlp_wpml_glossary_nonce' => wp_create_nonce( 'automlp_wpml_glossary_nonce' ),
					'default_language_slug'      => $default_language_slug,
				),
				$extra_data
			)
		);
	}
}

new Register_Assets();
