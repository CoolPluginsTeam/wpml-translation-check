<?php

namespace AUTOMLP_WPML\Includes\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;
use AUTOMLP_WPML\Includes\Wpml\Get_Package_Content;
use AUTOMLP_WPML\Includes\Wpml\Create_Translated_Post;

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
                '/(?P<slug>[\w-]+)/translate-text',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'ai_translation' ),
                    'permission_callback' => array( $this, 'permission_only_admins' ),
                    'args'                => array(
                        'slug'            => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_key',
                        ),
                        'automlp_wpml_nonce'      => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => array( $this, 'validate_automlp_wpml_ai_translate_nonce' ),
                        ),
                        'strings'         => array(
                            'type'     => 'string',
                            'required' => true,
                        ),
                        'target_language' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'source_language' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'action'          => array(
                            'type'     => 'string',
                            'required' => false,
                        ),
                    ),
                )
            );

			register_rest_route(
				$this->base_name,
				'/(?P<slug>[\w-]+)/pending-posts-ids',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'get_pending_posts_ids' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'privateKey' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_pending_posts_ids_request' ),
						),
						'ids' => array(
							'type'     => 'string',
							'required' => true,
						),
						'lang' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				)
			);

			register_rest_route(
				$this->base_name,
				'/(?P<slug>[\w-]+)/bulk-translate-entries',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'bulk_translate_entries' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'ids'        => array(
							'type'     => 'string',
							'required' => true,
						),
						'lang'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'privateKey' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_automlp_wpml_bulk_nonce' ),
						),
					),
				)
			);

			register_rest_route(
				$this->base_name,
				'/(?P<post_id>[\w-]+)/create-translate-post',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_translate_post' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
					'args'                => array(
						'privateKey'      => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_automlp_wpml_create_post_nonce' ),
						),
						'post_id'         => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'target_language' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'editor_type'     => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'source_language' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'post_title'      => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'post_content'    => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				)
			);
			register_rest_route(
				$this->base_name,
				'/wizard-save-credentials',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'wizard_save_credentials' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
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
					),
				)
			);
			register_rest_route(
				$this->base_name,
				'/wizard-save-language',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'wizard_save_language' ),
					'permission_callback' => array( $this, 'permission_only_admins' ),
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
					'permission_callback' => array( $this, 'permission_only_admins' ),
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

		public function permission_only_admins( $request ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );

			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'wpml-translation-check' ), array( 'status' => 403 ) );
			}

			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new \WP_Error( 'rest_forbidden', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 403 ) );
			}
			return true;
		}

		public function validate_automlp_wpml_ai_translate_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automlp_wpml_ai_translate_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'Invalid security token sent.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		public function validate_automlp_wpml_bulk_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automlp_wpml_bulk_translate_entries_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		public function validate_automlp_wpml_create_post_nonce( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automlp_wpml_create_translate_post_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'You are not authorized to perform this action.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		public function validate_pending_posts_ids_request( $value, $request, $param ) {
			return wp_verify_nonce( $value, 'automlp_wpml_pending_posts_ids_nonce' ) ? true : new \WP_Error( 'rest_invalid_param', __( 'Invalid security token sent.', 'wpml-translation-check' ), array( 'status' => 403 ) );
		}

		/**
		 * AI Translation
		 *
		 * @param WP_REST_Request $params The request parameters.
		 * @return WP_REST_Response The response.
		 */
        public function ai_translation( $params ) {
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
        
            $params = $params->get_params();
        
            $service_slug = isset( $params['slug'] ) ? sanitize_key( $params['slug'] ) : '';
            if ( ! $service_slug ) {
                wp_send_json_error( 'Invalid service slug.' );
            }
        
            if ( ! wp_verify_nonce( $params['automlp_wpml_nonce'] ?? '', 'automlp_wpml_ai_translate_nonce' ) ) {
                wp_send_json_error( 'You are not authorized to perform this action.' );
            }
        
            $strings_raw = $params['strings'] ?? '';
            $target_language = isset( $params['target_language'] ) ? sanitize_text_field( $params['target_language'] ) : '';
            $source_language = isset( $params['source_language'] ) ? sanitize_text_field( $params['source_language'] ) : 'en';
        
            if ( ! $target_language ) {
                wp_send_json_error( 'Invalid target language.' );
            }
			$wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();
            if ( $wizard_lang !== null && strtolower( $target_language ) !== strtolower( $wizard_lang ) ) {
                wp_send_json_error( __( 'This target language is not allowed. Only the language selected in the setup wizard can be used.', 'wpml-translation-check' ) );
            }
        
            // Decode numeric-key => text map, e.g. {"0":"text","1":"text"}
            $strings = is_string( $strings_raw ) ? json_decode( $strings_raw, true ) : $strings_raw;
            if ( ! is_array( $strings ) ) {
                $strings = array();
            }
        
                       // Get selected model for this provider from our option, or fallback to defaults.
					   $models   = get_option( 'automlp_ai_translation_models', array() );
					   $model_id = isset( $models[ $service_slug ] ) && $models[ $service_slug ] !== '' ? $models[ $service_slug ] : '';
					   if ( ! $model_id ) {
						   $default_models = array(
							   'openai' => 'gpt-4o-mini',
							   'google' => 'gemini-2.5-flash',
						   );
						   $model_id = isset( $default_models[ $service_slug ] ) ? $default_models[ $service_slug ] : '';
					   }
					   if ( ! $model_id ) {
						   wp_send_json_error( 'No AI model selected for this provider.' );
					   }
        
            if ( ! class_exists( '\WordPress\AiClient\AiClient'  ) ) {
                wp_send_json_error( 'AI SDK is not available.' );
            }
        
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if ( ! $registry->isProviderConfigured( $service_slug ) ) {
                wp_send_json_error( 'API key for this provider is not configured.' );
            }
        
            $provider_class = $registry->getProviderClassName( $service_slug );
			$website_context = array();
			if ( class_exists( '\WPML\TM\API\ATE\WebsiteContext' ) ) {
				$website_context = \WPML\TM\API\ATE\WebsiteContext::getWebsiteContext();
			}

            try {
                $model = $provider_class::model( $model_id );
            } catch ( \Throwable $e ) {
                wp_send_json_error( 'Invalid model: ' . $e->getMessage() );
            }

            // Extract site context fields to guide the AI translation tone/style.
            $site_topic    = ! empty( $website_context['site_topic'] )    ? sanitize_text_field( $website_context['site_topic'] )    : '';
            $site_purpose  = ! empty( $website_context['site_purpose'] )  ? sanitize_text_field( $website_context['site_purpose'] )  : '';
            $site_audience = ! empty( $website_context['site_audience'] ) ? sanitize_text_field( $website_context['site_audience'] ) : '';

            $context_instruction = '';
            if ( $site_topic || $site_purpose || $site_audience ) {
                $context_parts = array();
                if ( $site_topic )    $context_parts[] = 'Topic: ' . $site_topic;
                if ( $site_purpose )  $context_parts[] = 'Purpose: ' . $site_purpose;
                if ( $site_audience ) $context_parts[] = 'Audience: ' . $site_audience;
                $context_instruction = "\nInstruction 9: Use the following site context to guide tone and terminology — " . implode( ', ', $context_parts ) . '.';
            }

            // Build one prompt with JSON instructions (your existing $content template).
            $strings_for_prompt = json_encode( $strings );
            $content = sprintf(
                'Instruction 1: Translate visible text content semantically into %s language. Provide a proper meaning-based translation.
        Instruction 2: Do not translate or modify any content inside square brackets []. These are shortcodes or dynamic placeholders and must remain exactly as they are.
        Instruction 3: Preserve all HTML tags and their attributes such as class, id, data-*, etc. Do not alter any part of the HTML structure.
        Instruction 4: Return the translation in the format of a JSON object with the keys being numeric values (matching the source keys), and the values being the translated strings.
        Instruction 5: Do not escape double quotes with backslashes. Output must be valid JSON without extra slashes.
        Instruction 6: Translate the provided JSON array into %s language, regardless of whether the values are the same and Ensure the JSON is well-formed and complete.
        Instruction 7: Decode any &lt; and &gt; HTML entities back to < and > symbols in the output & preserve and maintain whitespace.
        Instruction 8: Return the output as a valid JSON object. Do not wrap the output in a string or markdown code block. Ensure the JSON is clean, parseable, and properly formatted. Please ensure that the output follows the format: {"key(numeric value)": "(translations of the strings in %s language)"}%s Strings are :- %s',
                $target_language,
                $target_language,
                $target_language,
                $context_instruction,
                $strings_for_prompt
            );
        
           // $content is your long instruction + JSON string
            try {
                $builder = null;

				add_filter('wp_ai_client_default_request_timeout', function($timeout){
					return 120;
				});

				if(function_exists('wp_ai_client_prompt')){
					$builder = wp_ai_client_prompt();
				}else{
					$builder = \WordPress\AI_Client\AI_Client::prompt();
				}
				
				if(is_null($builder)){
					wp_send_json_error( 'AI client is not available.' );
				}

                $raw     = $builder
                    ->using_model( $model )
					->using_provider( $service_slug )
                    ->with_text( $content )
                    ->generate_text();
            } catch ( \Throwable $e ) {
                wp_send_json_error( 'Error during text generation: ' . $e->getMessage() );
            }
           	// Clean the text
			$cleanText = preg_replace( '/(^```json\n|```$)/', '', $raw );

			// Replace the double backslashes with a single backslash
			$final_text = preg_replace( '/\\\\{2,}([\'"n])/', '\\\$1', $cleanText );

			$translated_text = json_decode( $final_text, true );
            if ( ! is_array( $translated_text ) ) {
                wp_send_json_error( 'AI response is not valid JSON.' );
            }
        
            // Frontend expects: { success: true, data: { translate_data: { "0": "...", "1": "..." } } }
            wp_send_json_success(
                array(
                    'translate_data' => $translated_text,
                )
            );
        }

		/**
	 * Save wizard API credentials to the same option used by settings (wp_ai_client_provider_credentials).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function wizard_save_credentials( $request ) {
		$openai_key   = $request->get_param( 'openai_key' );
		$google_key   = $request->get_param( 'google_key' );
		// Basic format validation 
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
		$openai_model = $request->get_param( 'openai_model' );
		$google_model = $request->get_param( 'google_model' );
		$is_wizard    = $request->get_param( 'is_wizard' ); // Explicit flag from frontend
		$is_reset     = $request->get_param( 'is_reset' );  // Flag for reset operations
		$automlp_feedback_opt_in = $request->get_param( 'automlp_feedback_opt_in' );
		if (get_option('cpfm_opt_in_choice_cool_automlp_translations')) {
			update_option('automlp_feedback_opt_in', $automlp_feedback_opt_in);
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
		update_option( 'automlp_ai_wizard_selected_language', $stored );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

		public function get_pending_posts_ids( $params ) {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! wp_verify_nonce( $params['privateKey'], 'automlp_wpml_pending_posts_ids_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! isset( $params['lang'] ) || empty( $params['lang'] ) ) {
				wp_send_json_error( 'Empty target language Select at least one language' );
			}
			
			if ( ! isset( $params['ids'] ) || empty( $params['ids'] ) ) {
				wp_send_json_error( 'Empty post IDs Select at least one post to translate' );
			}

			$post_ids        = json_decode( $params['ids'] );
			$post_ids        = array_map( 'absint', $post_ids );
			$target_language = json_decode( $params['lang'] );
			$target_language = array_map( 'sanitize_text_field', $target_language );

			$active_languages = apply_filters( 'wpml_active_languages', null, null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			$active_languages_slugs = array_column( $active_languages, 'code' );
			$valid_target_languages = array_intersect( $target_language, $active_languages_slugs );
            
			$wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();
			if ( $wizard_lang !== null ) {
				$valid_target_languages = array_intersect( $valid_target_languages, array( $wizard_lang ) );
			}
			
			$pending_posts_ids = array();

			foreach ( $post_ids as $post_id ) {
				$automlp_wpml_post_element_type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$automlp_wpml_trid = apply_filters( 'wpml_element_trid', null, $post_id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				$automlp_wpml_translations = apply_filters( 'wpml_get_element_translations', null, $automlp_wpml_trid, $automlp_wpml_post_element_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				
				$parent_post_set=false;

				foreach ( $automlp_wpml_translations as $automlp_wpml_translation ) {
					if ( $automlp_wpml_translation->element_id && array_key_exists( $automlp_wpml_translation->element_id, $pending_posts_ids ) ) {
						$parent_post_set = true;
						break;
					}
				}

				if ( ! $parent_post_set ) {
					$automlp_wpml_post_translated_languages = array_column( $automlp_wpml_translations, 'language_code' );

					$untranslated_languages = array_diff( $valid_target_languages, $automlp_wpml_post_translated_languages );

					$pending_posts_ids[ $post_id ] = array('languages' => array_values($untranslated_languages), 'title' => get_the_title( $post_id ));
				}
			}

			wp_send_json_success( $pending_posts_ids );
		}

		public function bulk_translate_entries( $params ) {
			// Check if the user is logged in and has the necessary capabilities
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			// Verify the nonce
			if ( ! wp_verify_nonce( $params['privateKey'], 'automlp_wpml_bulk_translate_entries_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! isset( $params['lang'] ) || empty( $params['lang'] ) ) {
				wp_send_json_error( 'Empty target language Select at least one language' );
			}

			if ( ! isset( $params['ids'] ) || empty( $params['ids'] ) ) {
				wp_send_json_error( 'Empty post IDs Select at least one post to translate' );
			}

			$active_languages = apply_filters( 'wpml_active_languages', null, null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			$active_languages_slugs = array_column( $active_languages, 'code' );

			$post_ids        = json_decode( $params['ids'] );
			$post_ids        = array_map( 'absint', $post_ids );
			$target_language = json_decode( $params['lang'] );
			$target_language = array_map( 'sanitize_text_field', $target_language );

			$valid_target_languages = array_intersect( $target_language, $active_languages_slugs );
   
			$wizard_lang = WPML_AT_Helper::get_wizard_allowed_language_code();
			if ( $wizard_lang !== null ) {
				$valid_target_languages = array_intersect( $valid_target_languages, array( $wizard_lang ) );
			}
			$automlp_wpml_content_translation = array();

			if ( ! defined( 'DOING_AUTOMLP_WPML_BULK_POST_TRANSLATION' ) ) {
				define( 'DOING_AUTOMLP_WPML_BULK_POST_TRANSLATION', true );
			}

			foreach ( $post_ids as $post_id ) {
				$post_data = get_post( $post_id );

				if ( ! $post_data ) {
					continue;
				}

				$automlp_wpml_post_element_type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				// Get the translation group ID (trid) of the post
				$automlp_wpml_trid = apply_filters( 'wpml_element_trid', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				// Get all translations of the element using the trid and element type
				$automlp_wpml_translations = apply_filters( 'wpml_get_element_translations', null, $automlp_wpml_trid, $automlp_wpml_post_element_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				$automlp_wpml_post_translated_languages = array_column( $automlp_wpml_translations, 'language_code' );

				$untranslated_languages = array_diff( $valid_target_languages, $automlp_wpml_post_translated_languages );

				if ( ! isset( $automlp_wpml_content_translation['posts'] ) ) {
					$automlp_wpml_content_translation['posts'] = array();
				}

				if ( count( $untranslated_languages ) > 0 ) {
					$automlp_wpml_content_translation['posts'][ $post_id ]['languages'] = array_values($untranslated_languages);
				}

				if(!isset($automlp_wpml_content_translation['posts'][ $post_id ]['languages']) || empty($automlp_wpml_content_translation['posts'][ $post_id ]['languages'])) {
					continue;
				}

				if(!isset($automlp_wpml_content_translation['CreateTranslatePostNonce'])) {
				$automlp_wpml_content_translation['CreateTranslatePostNonce'] = wp_create_nonce( 'automlp_wpml_create_translate_post_nonce' );
				}

				$source_lang          = WPML_AT_Helper::get_post_source_language( $post_id, get_post_type( $post_id ) );
				$get_package_content  = new Get_Package_Content( $post_id, $source_lang );
				$translatable_strings = $get_package_content->get_translatable_strings();

				$editor_type = has_blocks( $post_data->post_content ) ? 'block' : 'classic';

				$automlp_wpml_content_translation['posts'][ $post_id ]['sourceLanguage'] = $source_lang;
				$automlp_wpml_content_translation['posts'][ $post_id ]['title']          = $post_data->post_title;
				$automlp_wpml_content_translation['posts'][ $post_id ]['post_link']      = html_entity_decode( get_edit_post_link( $post_id ) );

				$automlp_wpml_content_translation['posts'][ $post_id ]['editor_type'] = $this->get_editor_type( $post_id, $editor_type );

				if ( isset( $translatable_strings['contents'] ) && ! empty( $translatable_strings['contents'] ) ) {
					$automlp_wpml_content_translation['posts'][ $post_id ]['content'] = $translatable_strings['contents'];
				}

				if ( isset( $translatable_strings['title'] ) && ! empty( $translatable_strings['title'] ) ) {
					$automlp_wpml_content_translation['posts'][ $post_id ]['title'] = $translatable_strings['title'];
				}
			}

			wp_send_json_success( $automlp_wpml_content_translation );
		}

		public function create_translate_post( $params ) {

			if ( ! isset( $params['source_language'] ) || empty( $params['source_language'] ) ) {
				wp_send_json_error( 'Invalid source language' );
			}
			if ( ! isset( $params['post_id'] ) || ! isset( $params['target_language'] ) || ( ! isset( $params['post_title'] ) && ! isset( $params['post_content'] ) ) ) {
				wp_send_json_error( 'Invalid request' );
			}
			if ( ! isset( $params['target_language'] ) && empty( $params['target_language'] ) ) {
				wp_send_json_error( 'Invalid target language' );
			}
			if ( ! wp_verify_nonce( $params['privateKey'], 'automlp_wpml_create_translate_post_nonce' ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			$params = $params->get_params();

			$post_id = intval( sanitize_text_field( $params['post_id'] ) );

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( 'You are not authorized to perform this action.' );
			}

			if ( ! isset( $params['post_title'] ) || empty( $params['post_title'] ) && ! isset( $params['post_content'] ) || empty( $params['post_content'] ) ) {
				wp_send_json_error( 'No post title or post content found' );
			}

			if ( ! defined( 'DOING_AUTOMLP_WPML_CREATE_TRANSLATED_POST' ) ) {
				define( 'DOING_AUTOMLP_WPML_CREATE_TRANSLATED_POST', true );
			}

			$target_language = sanitize_text_field( $params['target_language'] );
			$wizard_lang     = WPML_AT_Helper::get_wizard_allowed_language_code();
			if ( $wizard_lang !== null && strtolower( $target_language ) !== strtolower( $wizard_lang ) ) {
				wp_send_json_error( __( 'This target language is not allowed. Only the language selected in the setup wizard can be used.', 'wpml-translation-check' ) );
			}
			$editor_type     = sanitize_text_field( $params['editor_type'] );
			$source_language = sanitize_text_field( $params['source_language'] );
			$post_title      = isset( $params['post_title'] ) ? sanitize_text_field( $params['post_title'] ) : '';
			$post_excerpt    = isset( $params['post_excerpt'] ) ? wp_kses_post( $params['post_excerpt'] ) : '';
			$post_content    = isset( $params['post_content'] ) ? json_decode( $params['post_content'], true ) : '';
			$post_content = is_array($post_content) ? $post_content : array();

			$editor_type = isset( $editor_type ) && 'block' === $editor_type ? 'Gutenberg' : $editor_type;

			$create_translated_post = new Create_Translated_Post( $post_id, $post_content, $post_title, $post_excerpt, $source_language, $target_language, $editor_type );

			$translated_post_id = $create_translated_post->create_post();

			if ( is_wp_error( $translated_post_id ) ) {
				wp_send_json_error( $translated_post_id->get_error_message() );
			}

			$post_link      = html_entity_decode( get_the_permalink( $translated_post_id ) );
			$post_title     = html_entity_decode( get_the_title( $translated_post_id ) );
			$post_edit_link = html_entity_decode( get_edit_post_link( $translated_post_id ) );
				
			wp_send_json_success(
				array(
					'post_id'                     => $translated_post_id,
					'target_language'             => $target_language,
					'post_link'                   => $post_link,
					'post_title'                  => $post_title,
					'post_edit_link'              => $post_edit_link,
					'update_translate_data_nonce' => wp_create_nonce( 'automlp_wpml_update_translate_data' ),
				)
			);
		}

		public function get_editor_type( int $post_id, $default = 'block' ): string {
			$editor = $default;

			if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && defined( 'ELEMENTOR_VERSION' ) ) {
				$editor = 'Elementor';
			} elseif ( 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ) && defined( 'ET_CORE' ) ) {
				$editor = 'Divi';
			}

			return $editor;
		}
	}
endif;
