<?php

namespace AUTOMLP_WPML\Includes\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;

if ( ! class_exists( 'Bulk_Translation_Route' ) ) :
	/**
	 * Bulk_Translation_Route
	 *
	 * @package AUTOMLP_WPML\AI_Translate\Services\API\Helpers
	 */
	class Bulk_Translation_Route {
		/**
		 * The base name of the route.
		 *
		 * @var string
		 */
		private $base_name;

		/**
		 * Constructor
		 *
		 * @param string $base_name The base name of the route.
		 */
		public function __construct( $base_name ) {
			$this->base_name = $base_name;
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the routes
		 */
		public function register_routes() {

			register_rest_route(
				$this->base_name,
				'/wizard-save-credentials',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'wizard_save_credentials' ),
					'permission_callback' => array( $this, 'permission_manage_options' ),
					'args'                => array(
						'openai_key'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'google_key'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'openai_model'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'google_model'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'automlp_bulk_post_status'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);
			register_rest_route(
				$this->base_name,
				'/wizard-save-language',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'wizard_save_language' ),
					'permission_callback' => array( $this, 'permission_manage_options' ),
					'args'                => array(
						'selected_language' => array(
							'type'              => 'object',
							'required' => false,
							'properties' => array(
								'code'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
								'name'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
								'flag_url' => array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ),
							),
						),
					),
				)
			);
			register_rest_route(
				$this->base_name,
				'/wizard-complete',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'wizard_complete' ),
					'permission_callback' => array( $this, 'permission_manage_options' ),
				)
			);
		}

			/**
	 * Mark setup wizard as complete (persists across plugin reinstall – do not delete this option in uninstall).
	 *
	 * @return \WP_REST_Response
	 */
	public function wizard_complete() {
		update_option( 'automlp_ai_setup_complete', true );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

		public function permission_manage_options( $request ) {
			return $this->check_rest_permission( $request, 'manage_options' );
		}

		private function check_rest_permission( $request, $capability ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new \WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'wpml-translation-check' ), array( 'status' => 403 ) );
			}

			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 401 ) );
			}

			if ( ! current_user_can( $capability ) ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 403 ) );
			}

			return true;
		}
		
		/**
	 * Save wizard API credentials to the same option used by settings (wp_ai_client_provider_credentials).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_save_credentials( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Unauthorized.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		$openai_key   = $request->get_param( 'openai_key' );
		$google_key   = $request->get_param( 'google_key' );
		$is_reset     = $request->get_param( 'is_reset' );  // Flag for reset operations
		// Basic format validation 
		if(! $is_reset){
		if ( $openai_key !== null ) {
			$key_trimmed =  $openai_key;
			if ( strlen( $key_trimmed ) < 10 ) {
				return new \WP_Error(
					'automlp_invalid_api_key',
					__( 'Invalid OpenAI API key.', 'wpml-translation-check' ),
					array( 'status' => 400 )
				);
			}
			if ( preg_match( '/[<>"\']/', $key_trimmed ) ) {
				return new \WP_Error(
					'automlp_invalid_api_key',
					__( 'Invalid OpenAI API key format. Please check your credentials.', 'wpml-translation-check' ),
					array( 'status' => 400 )
				);
			}
			if ( ! preg_match( '/^sk-[a-zA-Z0-9_-]{20,}$/', $key_trimmed ) ) {
				return new \WP_Error(
					'automlp_invalid_api_key',
					__( 'Invalid OpenAI API key format.', 'wpml-translation-check' ),
					array( 'status' => 400 )
				);
			}
		}
		if ( $google_key !== null ) {
			$key_trimmed =  $google_key;
			if ( strlen( $key_trimmed ) < 10 ) {
				return new \WP_Error(
					'automlp_invalid_api_key',
					__( 'Invalid Google API key.', 'wpml-translation-check' ),
					array( 'status' => 400 )
				);
			}
			if ( preg_match( '/[<>"\']/', $key_trimmed ) ) {
				return new \WP_Error(
					'automlp_invalid_api_key',
					__( 'Invalid Google API key format. Please check your credentials.', 'wpml-translation-check' ),
					array( 'status' => 400 )
				);
			}
		}
	}
		$openai_model = $request->get_param( 'openai_model' );
		$google_model = $request->get_param( 'google_model' );
		$is_wizard    = $request->get_param( 'is_wizard' ); // Explicit flag from frontend
		$automlp_feedback_opt_in = $request->get_param( 'automlp_feedback_opt_in' );
		$automlp_bulk_post_status = $request->get_param( 'automlp_bulk_post_status' );
		if (get_option('cpfm_opt_in_choice_cool_automlp_translations')) {
			update_option('automlp_feedback_opt_in', $automlp_feedback_opt_in);
		}
		if ( $automlp_bulk_post_status !== null ) {
			$status = in_array( $automlp_bulk_post_status, array( 'draft', 'publish' ), true ) ? $automlp_bulk_post_status : 'draft';
			update_option( 'automlp_bulk_post_status', $status );
		}
		// If user opted out, clear the scheduled cron.
      $normalized_opt_in = is_string( $automlp_feedback_opt_in ) ? strtolower( $automlp_feedback_opt_in ) : $automlp_feedback_opt_in;

	  if ( $normalized_opt_in === 'yes' || $normalized_opt_in === '1' || $normalized_opt_in === 1 || $normalized_opt_in === true ) {
		if ( ! wp_next_scheduled( 'automlp_extra_data_update' ) ) {
			wp_schedule_event( time(), 'every_30_days', 'automlp_extra_data_update' );
		}
	} else {
		// Any other value ("no", empty, etc.) → clear scheduled cron.
		if ( wp_next_scheduled( 'automlp_extra_data_update' ) ) {
			wp_clear_scheduled_hook( 'automlp_extra_data_update' );
		}
	}
		// Flags: what the user is actually enabling in THIS request.
		$has_openai = ( $openai_key !== null && trim( $openai_key ) !== '' );
		$has_google = ( $google_key !== null && trim( $google_key ) !== '' );
		if ( ! $has_openai && ! $has_google && ! $is_reset && ! $is_wizard && ! $openai_model && ! $google_model) {
			return new \WP_Error(
				'automlp_no_api_key',
				__( 'Please enter at least one API key (OpenAI or Google).', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		$automlp_update_data=array();
	
		// Keep previous values so we can restore if validation fails.
		$previous_models = get_option( 'automlp_ai_translation_models', array() );
		$previous_providers_key=WPML_AT_Helper::get_providers_key(array('openai', 'google'), true);

		if ( ! is_array( $previous_models ) ) {
			$previous_models = array();
		}
	
		$models      = $previous_models;

		// OpenAI: if user typed something -> set; if they cleared field -> unset.
		if ( $openai_key !== null ) {
			$automlp_update_data['openai']=array('status'=>'updated');
			if ( $has_openai ) {
				$automlp_update_data['openai']['key'] = sanitize_text_field( $openai_key );
			} else {
				$automlp_update_data['openai']['key'] = '';
			}
		}
		
		// Google: same logic.
		if ( $google_key !== null ) {
			$automlp_update_data['google']=array('status'=>'updated');
			if ( $has_google ) {
				$automlp_update_data['google']['key'] = sanitize_text_field( $google_key );
			} else {
				$automlp_update_data['google']['key'] = '';
			}
		}
		
		// Check if this is a wizard request (requires at least one key)
		$is_wizard_request = $is_wizard === true || $is_wizard === 'true';
		$is_reset_request = $is_reset === true || $is_reset === 'true';
		
		// Require at least one provider for wizard, but allow deletion in settings and reset operations
		if ( $is_wizard_request && ! $is_reset_request && !isset($automlp_update_data['openai']) && !isset($automlp_update_data['google']) && !isset($previous_providers_key['openai']) && !isset($previous_providers_key['google']) ) {
			return new \WP_Error(
				'automlp_no_api_key',
				__( 'Please enter at least one API key (OpenAI or Google).', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}
	
		// === Update models (optional) ===
	
		if ( $openai_model !== null ) {
			if ( trim( $openai_model ) !== '' ) {
				$models['openai'] = $openai_model;
			} else {
				unset( $models['openai'] );
			}
		}
	
		if ( $google_model !== null ) {
			if ( trim( $google_model ) !== '' ) {
				$models['google'] = $google_model;
			} else {
				unset( $models['google'] );
			}
		}
	
		if ( ! empty( $models ) ) {
			update_option( 'automlp_ai_translation_models', $models );
		} else {
			delete_option( 'automlp_ai_translation_models' );
		}

		$errors=array();
		$updated_providers_key=$previous_providers_key;
		foreach ($automlp_update_data as $provider => $data) {
			if ( isset( $data['key'] ) || empty($data['key']) ) {
				if(!empty($data['key'])) {
					$automlp_validation_result=$this->validate_provider_api_key( $provider, $data['key'] );
					if ( is_array( $automlp_validation_result ) && ! empty( $automlp_validation_result['message'] ) ) {
						$errors[$provider] = $automlp_validation_result['message'];
						continue; // Skip updating the key for this provider since it's invalid.
					}
					$updated_providers_key[$provider]=$data['key'];
				}else{
					if(function_exists('_wp_register_default_connector_settings')){
						delete_option('connectors_ai_'.$provider.'_api_key');
						unset($updated_providers_key[$provider]);
					}else{
						unset($updated_providers_key[$provider]);
					}
				}
			}
		}
	
		if ( ! empty( $errors ) ) {
			if ( ! empty( $previous_models ) ) {
				update_option( 'automlp_ai_translation_models', $previous_models );
			} else {
				delete_option( 'automlp_ai_translation_models' );
			}
	
			return new \WP_Error(
				'automlp_invalid_api_key',
				__( 'One of the API keys is invalid.', 'wpml-translation-check' ),
				array(
					'status' => 400,
					'errors' => $errors, // ['openai' => '...', 'google' => '...']
				)
			);
		}
		if(function_exists('_wp_register_default_connector_settings')){
			foreach ($updated_providers_key as $provider => $key) {
				update_option('connectors_ai_'.$provider.'_api_key', $key);
			}
		}else{
			update_option('wp_ai_client_provider_credentials', $updated_providers_key);
		}
	
		// Clear model list cache so Settings page refetches and shows model selectors after reload.
		delete_transient( 'automlp_wpml_openai_models' );
		delete_transient( 'automlp_wpml_google_models' );

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
 * Validate a provider API key by doing a tiny test call.
 *
 * @param string $provider_id Provider ID as used by the WP AI SDK (e.g. 'openai', 'google').
 * @param string $api_key     API key to test.
 * @return true|array         true on success, or ['message' => 'error text'] on failure.
 */
private function validate_provider_api_key( $provider_id, $api_key ) {
    if ( ! $provider_id || ! $api_key ) {
        return array( 'message' => __( 'Provider and API key are required.', 'wpml-translation-check' ) );
    }
	// Basic format validation - reject obviously invalid keys before API call.
$key_trimmed = trim( $api_key );
if ( strlen( $key_trimmed ) < 10 ) {
    return array( 'message' => __( 'API key appears to be invalid or too short.', 'wpml-translation-check' ) );
}
// Reject keys with HTML/script characters or obvious junk.
if ( preg_match( '/[<>"\']/', $key_trimmed ) ) {
    return array( 'message' => __( 'Invalid API key format. Please check your credentials.', 'wpml-translation-check' ) );
}
// OpenAI keys must start with sk-
if ( 'openai' === strtolower( $provider_id ) && ! preg_match( '/^sk-[a-zA-Z0-9_-]{20,}$/', $key_trimmed ) ) {
    return array( 'message' => __( 'OpenAI API keys must start with sk- and be in the correct format.', 'wpml-translation-check' ) );
}

    if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
        return array( 'message' => __( 'AI client is not available.', 'wpml-translation-check' ) );
    }

    $registry = \WordPress\AiClient\AiClient::defaultRegistry();
    if ( ! $registry->hasProvider( $provider_id ) ) {
        return array( 'message' => __( 'Invalid AI provider.', 'wpml-translation-check' ) );
    }

    $is_gemini = ( 'google' === strtolower( $provider_id ) ) || str_contains( strtolower( $provider_id ), 'gemini' );
    $cooldown  = $is_gemini ? 60 : 5;
    $lock_key  = 'automlp_ai_test_lock_' . md5( $provider_id . '|' . $api_key );

    if ( get_transient( $lock_key ) ) {
        return array(
            'message' => $is_gemini
                ? __( 'Gemini rate limit reached. Please wait a minute and try again.', 'wpml-translation-check' )
                : __( 'Please wait a few seconds before testing again.', 'wpml-translation-check' ),
        );
    }

    // Inject the test API key into the registry (same as the REST controller does).
    $auth_class = 'WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication';
    $registry->setProviderRequestAuthentication(
        $provider_id,
        new $auth_class( $api_key )
    );

    set_transient( $lock_key, 1, $cooldown );

    try {
        $provider_classname       = $registry->getProviderClassName( $provider_id );
        $provider_availability    = $provider_classname::availability();

        if ( ! $provider_availability->isConfigured() ) {
            return array( 'message' => __( 'API key is not configured for this provider.', 'wpml-translation-check' ) );
        }

        $model_metadata_directory = $provider_classname::modelMetadataDirectory();
        $model_metadata_directory->listModelMetadata(); // throws on invalid key

    } catch ( \Exception $e ) {
        $msg = $e->getMessage();
        if ( str_contains( strtolower( $msg ), '429' ) ) {
            return array(
                'message' => $is_gemini
                    ? __( 'Gemini free tier rate limit exceeded. Please wait and try again.', 'wpml-translation-check' )
                    : __( 'Rate limit exceeded. Please try again later.', 'wpml-translation-check' ),
            );
        }
        return array( 'message' => __( 'Invalid API key. Please check your credentials.', 'wpml-translation-check' ) );
    }

    return true;
}

	/**
	 * Save the language selected in the wizard (used e.g. for string translation).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_save_language( $request ) {
		$param = $request->get_param( 'selected_language' );
		$stored = array();
		if ( is_array( $param ) && ! empty( $param['code'] ) ) {
			$stored = array(
				'code'     => sanitize_text_field( $param['code'] ),
				'name'     => isset( $param['name'] ) ? sanitize_text_field( $param['name'] ) : '',
				'flag_url' => isset( $param['flag_url'] ) ? esc_url_raw( $param['flag_url'] ) : '',
			);
		} elseif ( is_string( $param ) && $param !== '' ) {
			$stored = array( 'code' => sanitize_text_field( $param ), 'name' => '', 'flag_url' => '' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Unauthorized.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		update_option( 'automlp_ai_wizard_selected_language', $stored );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}
	}
endif;
