<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpml-translation-check' ) );
}

$automlp_wpml_wizard_lang         = get_option( 'automlp_ai_wizard_selected_language', array() );
$automlp_wpml_wizard_language_set = is_array( $automlp_wpml_wizard_lang ) && ! empty( $automlp_wpml_wizard_lang['code'] );
?>
<div class="automlp_ai_dashboard-settings">
	<div class="automlp_ai_dashboard-settings-container">
		<div class="header">
			<h1><?php echo esc_html__( 'AutoMLP AI Translate Settings', 'wpml-translation-check' ); ?></h1>
		</div>

		<p class="description">
			<?php
			echo esc_html__(
				'Configure your AI providers and translation models here. Keys are stored via the WP AI Client option and models via the WPML addon model option.',
				'wpml-translation-check'
			);
			?>
		</p>

		<?php settings_errors( 'wp-ai-client-settings' ); ?>
		<div id="automlp-ai-settings-validation-notice" class="notice notice-error" style="margin: 1rem 0; display: none;" role="alert">
			<p></p>
		</div>

		<?php if ( ! $automlp_wpml_wizard_language_set ) : ?>
			<div class="notice notice-warning" style="margin: 1rem 0;">
				<p>
					<strong>
						<?php esc_html_e( 'Please select a translation language first.', 'wpml-translation-check' ); ?>
					</strong>
					<?php esc_html_e( 'Complete the Setup Wizard and choose a language in the Languages step. Until then, API key settings are disabled.', 'wpml-translation-check' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'automlp_ai_wizard&step=languages' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Open Setup Wizard', 'wpml-translation-check' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>
			<div class="automlp_ai_dashboard-api-settings">
				<form id="automlp-ai-settings-credentials-form" method="post" action="#">
					<?php

					$automlp_get_providers_key=WPML_AT_Helper::get_providers_key(array('openai', 'google'), true);

					$automlp_wpml_ai_credentials = array();

					if(isset($automlp_get_providers_key['openai']) && !empty($automlp_get_providers_key['openai'])){
						$automlp_wpml_ai_credentials['openai']=$automlp_get_providers_key['openai'];
					}

					if(isset($automlp_get_providers_key['google']) && !empty($automlp_get_providers_key['google'])){
						$automlp_wpml_ai_credentials['google']=$automlp_get_providers_key['google'];
					}
					// Current AI SDK credentials.
					$automlp_is_ConnectorsAi             = function_exists('_wp_register_default_connector_settings');
					$automlp_iis_openai_provider_installed = $automlp_is_ConnectorsAi && class_exists( 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider' ) ? true : false;
					$automlp_is_google_provider_installed  = $automlp_is_ConnectorsAi && class_exists( 'WordPress\GoogleAiProvider\Provider\GoogleProvider' ) ? true : false;

					// Current selected models (saved by the addon).
					$automlp_wpml_current_models       = get_option( 'automlp_ai_translation_models', array() );
					$automlp_wpml_current_openai_model = isset( $automlp_wpml_current_models['openai'] ) ? $automlp_wpml_current_models['openai'] : 'gpt-4o-mini';
					$automlp_wpml_current_google_model = isset( $automlp_wpml_current_models['google'] ) ? $automlp_wpml_current_models['google'] : 'gemini-2.5-flash';
					$automlp_wpml_openai_api_key       = isset( $automlp_wpml_ai_credentials['openai'] ) ? $automlp_wpml_ai_credentials['openai'] : '';
					$automlp_wpml_google_api_key       = isset( $automlp_wpml_ai_credentials['google'] ) ? $automlp_wpml_ai_credentials['google'] : '';

					// Helper function to mask API keys for display
					if ( ! function_exists( 'automlp_mask_api_key' ) ) {
						function automlp_mask_api_key( $api_key ) {
							return WPML_AT_Helper::mask_api_key( $api_key );
						}
					}

					if ( empty( $automlp_wpml_current_openai_model ) && ! empty( $automlp_wpml_openai_api_key ) ) {
						$automlp_wpml_current_openai_model = 'gpt-4o-mini';
						update_option( 'automlp_ai_translation_models', array( 'openai' => $automlp_wpml_current_openai_model ) );
					}
					if ( empty( $automlp_wpml_current_google_model ) && ! empty( $automlp_wpml_google_api_key ) ) {
						$automlp_wpml_current_google_model = 'gemini-2.5-flash';
						update_option( 'automlp_ai_translation_models', array( 'google' => $automlp_wpml_current_google_model ) );
					}

					$automlp_wpml_openai_models = array();
					$automlp_wpml_google_models = array();

					// Use "has API key" instead of isProviderConfigured() to avoid HTTP requests on every page load.
					$automlp_wpml_has_openai_key = ! empty( $automlp_wpml_ai_credentials['openai'] );
					$automlp_wpml_has_google_key = ! empty( $automlp_wpml_ai_credentials['google'] );

					if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
						$automlp_wpml_registry = \WordPress\AiClient\AiClient::defaultRegistry();

						// OpenAI text-generation models (cached to avoid repeated API requests).
						if ( $automlp_wpml_has_openai_key ) {
							$automlp_wpml_cache_key = 'automlp_wpml_openai_models';
							$automlp_wpml_cached    = get_transient( $automlp_wpml_cache_key );
							if ( false !== $automlp_wpml_cached && is_array( $automlp_wpml_cached ) ) {
								$automlp_wpml_openai_models = $automlp_wpml_cached;
							} else {
								try {
									if (
										class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) &&
										class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
									) {
										$automlp_requirements     = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
											array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
											array()
										);
										$automlp_models_metadata = $automlp_wpml_registry->findProviderModelsMetadataForSupport( 'openai', $automlp_requirements );

										$automlp_wpml_openai_models = array_map(
											static function ( $model ) {
												/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
												return $model->getId();
											},
											$automlp_models_metadata
										);
										
										// Filter out non-translation models (audio, transcribe, search, codex, etc.)
										$automlp_wpml_openai_models = array_filter( $automlp_wpml_openai_models, function( $model_id ) {
											$excluded_patterns = array(
												'audio',
												'transcribe',
												'search',
												'codex',
												'diarize',
												'whisper',
												'tts',
												'dall-e',
												'embedding',
												'image'
											);
											
											$model_lower = strtolower( $model_id );
											foreach ( $excluded_patterns as $pattern ) {
												if ( strpos( $model_lower, $pattern ) !== false ) {
													return false;
												}
											}
											return true;
										});
										set_transient( $automlp_wpml_cache_key, $automlp_wpml_openai_models, 24 * HOUR_IN_SECONDS );
									}
								} catch ( \Throwable $e ) {
									$automlp_wpml_openai_models = array();
								}
							}
						} else {
							$automlp_wpml_cache_key = 'automlp_wpml_openai_models';
							delete_transient( $automlp_wpml_cache_key );
						}

						// Google / Gemini text-generation models (cached to avoid repeated API requests).
						if ( $automlp_wpml_has_google_key ) {
							$automlp_wpml_cache_key = 'automlp_wpml_google_models';
							$automlp_wpml_cached    = get_transient( $automlp_wpml_cache_key );
							if ( false !== $automlp_wpml_cached && is_array( $automlp_wpml_cached ) ) {
								$automlp_wpml_google_models = $automlp_wpml_cached;
							} else {
								try {
									if (
										class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) &&
										class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
									) {
										$automlp_requirements     = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
											array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
											array()
										);
										$automlp_models_metadata = $automlp_wpml_registry->findProviderModelsMetadataForSupport( 'google', $automlp_requirements );

										$automlp_wpml_google_models = array_map(
											static function ( $model ) {
												/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
												return $model->getId();
											},
											$automlp_models_metadata
										);
										
										// Filter out non-translation models (audio, transcribe, search, etc.)
										$automlp_wpml_google_models = array_filter( $automlp_wpml_google_models, function( $model_id ) {
											$excluded_patterns = array(
												'audio',
												'transcribe',
												'search',
												'vision',
												'embedding',
												'code',
												'imagen',
												'musiclm',
												'image'
											);
											
											$model_lower = strtolower( $model_id );
											foreach ( $excluded_patterns as $pattern ) {
												if ( strpos( $model_lower, $pattern ) !== false ) {
													return false;
												}
											}
											return true;
										});
										set_transient( $automlp_wpml_cache_key, $automlp_wpml_google_models, 24 * HOUR_IN_SECONDS );
									}
								} catch ( \Throwable $e ) {
									$automlp_wpml_google_models = array();
								}
							}
						} else {
							$automlp_wpml_cache_key = 'automlp_wpml_google_models';
							delete_transient( $automlp_wpml_cache_key );
						}

						// If we have a key but model list is empty (e.g. fetch failed), show selector with default so user can still save.
						if ( $automlp_wpml_has_openai_key && empty( $automlp_wpml_openai_models ) ) {
							$automlp_wpml_openai_models = array( 'gpt-4o-mini' );
						}
						if ( $automlp_wpml_has_google_key && empty( $automlp_wpml_google_models ) ) {
							$automlp_wpml_google_models = array( 'gemini-2.5-flash' );
						}
					}
					?>
						<?php
						// Providers shown in the UI.
						$automlp_wpml_api_settings = array(
							'openai' => array(
								'name'        => 'OpenAI',
								'doc_url'     => 'https://docs.coolplugins.net/doc/generate-openai-api-key/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_api_key',
								'placeholder' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
							'google' => array(
								'name'        => 'Google Gemini',
								'doc_url'     => 'https://docs.coolplugins.net/doc/generate-gemini-ai-api-key/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_api_key',
								'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
						);

						foreach ( $automlp_wpml_api_settings as $automlp_wpml_api_key => $automlp_wpml_settings ) :
							?>
							<label for="<?php echo esc_attr( $automlp_wpml_api_key ); ?>-api">
								<?php
								printf(
									// translators: %s: API name.
									esc_html__( 'Add %s API key', 'wpml-translation-check' ),
									esc_html( $automlp_wpml_settings['name'] )
								);
								?>
							</label>
							<div class="input-group">
								<?php
								$automlp_wpml_has_existing_key = isset( $automlp_wpml_ai_credentials[ $automlp_wpml_api_key ] ) && ! empty( $automlp_wpml_ai_credentials[ $automlp_wpml_api_key ] );
								$automlp_wpml_masked_key = $automlp_wpml_has_existing_key ? automlp_mask_api_key( $automlp_wpml_ai_credentials[ $automlp_wpml_api_key ] ) : '';

								?>
								<div style="display: flex; align-items: center; gap: 8px; width: 100%;">
									<input
										type="text"
										id="<?php echo esc_attr( $automlp_wpml_api_key ); ?>-api"
										name="wp_ai_client_provider_credentials[<?php echo esc_attr( $automlp_wpml_api_key ); ?>]"
										value="<?php echo $automlp_wpml_has_existing_key ? esc_attr( $automlp_wpml_masked_key ) : ''; ?>"
										placeholder="<?php echo ! $automlp_wpml_has_existing_key ? esc_attr( $automlp_wpml_settings['placeholder'] ) : ''; ?>"
										autocomplete="new-password"
										<?php echo $automlp_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
										data-has-key="<?php echo $automlp_wpml_has_existing_key ? '1' : '0'; ?>"
										data-original-masked="<?php echo $automlp_wpml_has_existing_key ? esc_attr( $automlp_wpml_masked_key ) : ''; ?>"
										style="flex: 1;"
										<?php echo $automlp_wpml_has_existing_key ? 'disabled="disabled"' : ''; ?>
									/>
									<?php
$automlp_provider_installed = ( 'openai' === $automlp_wpml_api_key )
    ? $automlp_iis_openai_provider_installed
    : $automlp_is_google_provider_installed ;
$automlp_connectors_url = admin_url( 'options-connectors.php' );
 if ( $automlp_wpml_has_existing_key ) : ?>
    <span style="color: #46b450; font-size: 14px; margin-right: 4px;">✓</span>
    <button
        type="button"
        class="button button-primary automlp-reset-key-btn"
        data-provider="<?php echo esc_attr( $automlp_wpml_api_key ); ?>"
        title="<?php esc_attr_e( 'Reset API key', 'wpml-translation-check' ); ?>"
        <?php echo $automlp_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
    >
        <?php esc_html_e( 'Reset', 'wpml-translation-check' ); ?>
    </button>
<?php endif; ?>
								</div>
							</div>
							<div id="automlp-ai-settings-message-<?php echo esc_attr( $automlp_wpml_api_key ); ?>" class="automlp_ai_dashboard-settings-message" style="margin-top: 4px; margin-bottom: 12px; display: none; color: #b32d2e; font-size: 13px;" role="alert"></div>

							<?php
							$automlp_wpml_has_key = ! empty( $automlp_wpml_ai_credentials[ $automlp_wpml_api_key ] );

							// OpenAI model selector.
							if ( 'openai' === $automlp_wpml_api_key && $automlp_wpml_has_key && ! empty( $automlp_wpml_openai_models ) ) :
								?>
								<div class="automlp_ai_dashboard-api-settings-openai-model">
									<label for="automlp_ai_selected_openai_model" class="api-settings-label">
										<?php esc_html_e( 'Select OpenAI Model', 'wpml-translation-check' ); ?>
									</label>
									<select
										id="automlp_ai_selected_openai_model"
										name="automlp_ai_translation_models[openai]"
										class="automlp-openai-model-select"
										<?php echo $automlp_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
									>
									<?php
									$automlp_preferred_models = array(
										'gpt-5.4'             => __( 'gpt-5.4 (Best Quality)', 'wpml-translation-check' ),
										'gpt-5.4-pro'         => __( 'gpt-5.4-pro (Highest Accuracy)', 'wpml-translation-check' ),
										'gpt-5.3-chat-latest' => __( 'gpt-5.3-chat-latest (Recommended)', 'wpml-translation-check' ),
										'gpt-5.2'             => __( 'gpt-5.2 (Balanced)', 'wpml-translation-check' ),
										'gpt-5-mini'          => __( 'gpt-5-mini (Fast)', 'wpml-translation-check' ),
										'gpt-5-nano'          => __( 'gpt-5-nano (Cheapest)', 'wpml-translation-check' ),
										'gpt-4o-mini'         => __( 'gpt-4o-mini (Fast & Cheap)', 'wpml-translation-check' ),
									);

									$automlp_filtered_models = array_intersect_key(
										$automlp_preferred_models,
										array_flip( $automlp_wpml_openai_models )
									);

									$automlp_display_models = ! empty( $automlp_filtered_models )
										? $automlp_filtered_models
										: array_combine( $automlp_wpml_openai_models, $automlp_wpml_openai_models );

									foreach ( $automlp_display_models as $automlp_model_id => $automlp_model_label ) :
									?>
										<option value="<?php echo esc_attr( $automlp_model_id ); ?>" <?php selected( $automlp_wpml_current_openai_model, $automlp_model_id ); ?>>
											<?php echo esc_html( $automlp_model_label ); ?>
										</option>
									<?php endforeach; ?>
									</select>
								</div>
								<?php
							endif;

							// Google / Gemini model selector.
							if ( 'google' === $automlp_wpml_api_key && $automlp_wpml_has_key && ! empty( $automlp_wpml_google_models ) ) :
								?>
								<div class="automlp_ai_dashboard-api-settings-google-model">
									<label for="automlp_ai_selected_google_model" class="api-settings-label">
										<?php esc_html_e( 'Select Gemini Model', 'wpml-translation-check' ); ?>
									</label>
									<select
										id="automlp_ai_selected_google_model"
										name="automlp_ai_translation_models[google]"
										class="automlp-google-model-select"
										<?php echo $automlp_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
									>
									<?php
									$automlp_preferred_google_models = array(
										'gemini-3.1-pro-preview'        => __( 'gemini-3.1-pro-preview (Best Quality)', 'wpml-translation-check' ),
										'gemini-3.1-flash-lite-preview' => __( 'gemini-3.1-flash-lite-preview (Fast & Cheap)', 'wpml-translation-check' ),
										'gemma-3n-e4b-it'               => __( 'gemma-3n-e4b-it (Cheapest)', 'wpml-translation-check' ),
										'gemini-2.5-pro'                => __( 'gemini-2.5-pro (Best Overall)', 'wpml-translation-check' ),
										'gemini-2.5-flash'              => __( 'gemini-2.5-flash (Balanced)', 'wpml-translation-check' ),
										'gemini-3-flash-preview'        => __( 'gemini-3-flash-preview (Recommended)', 'wpml-translation-check' ),
										'gemini-2.5-pro-preview-tts'    => __( 'gemini-2.5-pro-preview-tts (High Accuracy)', 'wpml-translation-check' ),
									);

									$automlp_filtered_google_models = array_intersect_key(
										$automlp_preferred_google_models,
										array_flip( $automlp_wpml_google_models )
									);

									$automlp_display_google_models = ! empty( $automlp_filtered_google_models )
										? $automlp_filtered_google_models
										: array_combine( $automlp_wpml_google_models, $automlp_wpml_google_models );

									foreach ( $automlp_display_google_models as $automlp_model_id => $automlp_model_label ) :
									?>
										<option value="<?php echo esc_attr( $automlp_model_id ); ?>" <?php selected( $automlp_wpml_current_google_model, $automlp_model_id ); ?>>
											<?php echo esc_html( $automlp_model_label ); ?>
										</option>
									<?php endforeach; ?>
									</select>
								</div>
								<?php
							endif;

							printf(
								// translators: 1: Click here link, 2: API name.
								esc_html__( '%1$s to see how to configure %2$s in the AI SDK.', 'wpml-translation-check' ),
								'<a href="' . esc_url( $automlp_wpml_settings['doc_url'] ) . '" target="_blank">' . esc_html__( 'Click here', 'wpml-translation-check' ) . '</a>',
								esc_html( $automlp_wpml_settings['name'] )
							);
							echo '<br/><br/>';
						endforeach;
						if ( get_option( 'automlp_ai_credentials_migrated_to_wp70' ) ) : ?>
							<div class="automlp_ai_dashboard-api-note">
								<p>
									<em>
										<?php esc_html_e(
											'Note: API keys configured here are linked with the',
											'wpml-translation-check'
										); ?>
										<a href="<?php echo esc_url( $automlp_connectors_url ); ?>" target="_blank" rel="noopener noreferrer">
											<strong>
												<?php esc_html_e( 'AI → Connectors', 'wpml-translation-check' ); ?>
											</strong>
										</a>
										<?php esc_html_e(
											'settings in WordPress.',
											'wpml-translation-check'
										); ?>
									</em>
								</p>
							</div>
						<?php endif; ?>
						<hr>
						<?php
						 // Handle feedback checkbox
    $automlp_feedback_opt_in = null;


        // If user opted out, remove the cron job
        if ($automlp_feedback_opt_in === 'no' && wp_next_scheduled('automlp-ai_extra_data_update')) {
            wp_clear_scheduled_hook('automlp-ai_extra_data_update');
        }

    if ($automlp_feedback_opt_in === 'yes' && !wp_next_scheduled('automlp-ai_extra_data_update')) {

            wp_schedule_event(time(), 'every_30_days', 'automlp-ai_extra_data_update');   

    }
					   if (get_option('cpfm_opt_in_choice_cool_automlp_translations')) : ?>
                            <div class="automlp-ai-dashboard-feedback-container">
                                <div class="automlp-ai-dashboard-feedback-row">
                                    <input type="checkbox" 
                                        id="automlp_ai_dashboard_feedback_checkbox" 
                                        name="automlp_ai_dashboard_feedback_checkbox"
                                        <?php checked(get_option('automlp_feedback_opt_in'), 'yes'); ?>>
                                    <p><?php echo esc_html__('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'wpml-translation-check'); ?></p>
                                    <a href="#" class="cpfm-see-terms">[See terms]</a>
                                </div>
                                <div id="termsBox" style="display: none;padding-left: 20px; margin-top: 10px; font-size: 12px; color: #999;">
                                        <p><?php echo esc_html__("Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We'll collect:", 'wpml-translation-check'); ?> <a href="https://my.coolplugins.net/terms/usage-tracking/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=terms&utm_content=dashboard" target="_blank"><?php echo esc_html__('Click Here', 'wpml-translation-check'); ?></a></p>
                                        <ul style="list-style-type:auto;">
                                            <li><?php echo esc_html__('Your website home URL and WordPress admin email.', 'wpml-translation-check'); ?></li>
                                            <li><?php echo esc_html__('To check plugin compatibility, we will collect the following: list of active plugins and themes, server type, MySQL version, WordPress version, memory limit, site language and database prefix.', 'wpml-translation-check'); ?></li>
                                        </ul>
                                </div>
                            </div>
                        <?php endif; ?>
						<div class="automlp_ai_dashboard-save-btn-container">
							<?php submit_button( __( 'Save', 'wpml-translation-check' ), 'primary', 'submit', true, $automlp_wpml_wizard_language_set ? array() : array( 'disabled' => 'disabled' ) ); ?>
						</div>
				</form>
			</div>
	</div>
</div>
<?php
// Submit settings via same REST endpoint as wizard so validate_provider_api_key is used.
if ( $automlp_wpml_wizard_language_set ) :
	?>
<script>
(function() {
	var form = document.getElementById('automlp-ai-settings-credentials-form');
	var msgOpenai = document.getElementById('automlp-ai-settings-message-openai');
	var msgGoogle = document.getElementById('automlp-ai-settings-message-google');
	var validationNotice = document.getElementById('automlp-ai-settings-validation-notice');
	if ( ! form || ! msgOpenai || ! msgGoogle ) {
		return;
	}
	function clearMessages() {
		msgOpenai.textContent = '';
		msgOpenai.style.display = 'none';
		msgGoogle.textContent = '';
		msgGoogle.style.display = 'none';
		if ( validationNotice ) {
			validationNotice.style.display = 'none';
			var noticeP = validationNotice.querySelector( 'p' );
			if ( noticeP ) {
				noticeP.textContent = '';
			}
		}
	}
	
	// Handle input field clicks - make editable when clicked
	document.addEventListener('click', function(e) {
		if (e.target.type === 'text' && e.target.getAttribute('data-has-key') === '1') {
			var input = e.target;
			var originalMasked = input.getAttribute('data-original-masked');
			
			// If showing masked key, clear it and make editable
			if (input.value === originalMasked) {
				input.value = '';
				input.type = 'password';
				input.removeAttribute('disabled');
				input.placeholder = '<?php echo esc_js( __( 'Enter new key to update', 'wpml-translation-check' ) ); ?>';
				input.focus();
			}
		}
	});
	
	// Handle input field blur - restore masked view if empty
	document.addEventListener('blur', function(e) {
		if (e.target.type === 'password' && e.target.getAttribute('data-has-key') === '1') {
			var input = e.target;
			var originalMasked = input.getAttribute('data-original-masked');
			
			// If field is empty, restore masked view
			if (input.value.trim() === '') {
				input.value = originalMasked;
				input.type = 'text';
				input.setAttribute('disabled', 'disabled');
				input.placeholder = '';
			}
		}
	}, true);
	
	// Handle reset button clicks - immediately delete the API key
	document.addEventListener('click', function(e) {
		if (e.target.classList.contains('automlp-reset-key-btn')) {
			e.preventDefault();
			var provider = e.target.getAttribute('data-provider');
			
			// Disable the button during request
			e.target.disabled = true;
			e.target.textContent = '<?php echo esc_js( __( 'Deleting...', 'wpml-translation-check' ) ); ?>';
			
			// Prepare delete request - send empty string to remove the key
			var deleteData = {};
			deleteData[provider + '_key'] = '';
			deleteData['is_reset'] = true;
			
			// Send delete request
			fetch('<?php echo esc_js( rest_url( 'automlp-bulk-translate/' ) ); ?>wizard-save-credentials', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
				},
				body: JSON.stringify(deleteData)
			})
			.then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }, function() { return { ok: res.ok, data: {} }; }); })
			.then(function(result) {
				if (result.ok && result.data && result.data.success) {
					// Success - reload page to show updated state
					window.location.reload();
				} else {
					// Error - show error message
					var err = result.data || {};
					var errorMsg = (err.message) ? err.message : '<?php echo esc_js( __( 'Failed to delete API key. Please try again.', 'wpml-translation-check' ) ); ?>';
					var msgElement = document.getElementById('automlp-ai-settings-message-' + provider);
					if (msgElement) {
						msgElement.textContent = errorMsg;
						msgElement.style.display = 'block';
						msgElement.style.color = '#d63638';
					}
					// Re-enable button
					e.target.disabled = false;
					e.target.textContent = '<?php echo esc_js( __( 'Reset', 'wpml-translation-check' ) ); ?>';
				}
			})
			.catch(function() {
				// Network error
				var msgElement = document.getElementById('automlp-ai-settings-message-' + provider);
				if (msgElement) {
					msgElement.textContent = '<?php echo esc_js( __( 'Network error. Please try again.', 'wpml-translation-check' ) ); ?>';
					msgElement.style.display = 'block';
					msgElement.style.color = '#d63638';
				}
				// Re-enable button
				e.target.disabled = false;
				e.target.textContent = '<?php echo esc_js( __( 'Reset', 'wpml-translation-check' ) ); ?>';
			});
		}
	});
	
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		var openaiInput = document.getElementById('openai-api');
		var googleInput = document.getElementById('google-api');
		var openaiKey = '';
		var googleKey = '';
		
		// Handle OpenAI key - don't send if it's the masked version
		if (openaiInput) {
			var openaiMasked = openaiInput.getAttribute('data-original-masked');
			var openaiValue = openaiInput.value.trim();
			if (openaiValue !== '' && openaiValue !== openaiMasked) {
				openaiKey = openaiValue;
			} else if (openaiValue === '' && openaiInput.getAttribute('data-has-key') === '1') {
				// Empty field with existing key = reset request
				openaiKey = '';
			}
		}
		
		// Handle Google key - don't send if it's the masked version  
		if (googleInput) {
			var googleMasked = googleInput.getAttribute('data-original-masked');
			var googleValue = googleInput.value.trim();
			if (googleValue !== '' && googleValue !== googleMasked) {
				googleKey = googleValue;
			} else if (googleValue === '' && googleInput.getAttribute('data-has-key') === '1') {
				// Empty field with existing key = reset request
				googleKey = '';
			}
		}
		
		var openaiModel = document.getElementById('automlp_ai_selected_openai_model') ? document.getElementById('automlp_ai_selected_openai_model').value : '';
		var googleModel = document.getElementById('automlp_ai_selected_google_model') ? document.getElementById('automlp_ai_selected_google_model').value : '';
		var feedbackCheckbox = document.getElementById('automlp_ai_dashboard_feedback_checkbox');
       var feedbackOptIn = feedbackCheckbox && feedbackCheckbox.checked ? 'yes' : 'no';
		// Build request data - only include keys that should be updated
		var requestData = {
			openai_model: openaiModel || null,
			google_model: googleModel || null,
			automlp_feedback_opt_in: feedbackOptIn
		};
		
		if (openaiInput && (openaiKey !== '' || (openaiInput.getAttribute('data-has-key') === '1' && openaiInput.value.trim() === ''))) {
			requestData.openai_key = openaiKey;
		}
		if (googleInput && (googleKey !== '' || (googleInput.getAttribute('data-has-key') === '1' && googleInput.value.trim() === ''))) {
			requestData.google_key = googleKey;
		}
		
		var submitBtn = form.querySelector('input[type="submit"]');
		if (submitBtn) submitBtn.disabled = true;
		clearMessages();
		fetch('<?php echo esc_js( rest_url( 'automlp-bulk-translate/' ) ); ?>wizard-save-credentials', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
			},
			body: JSON.stringify(requestData)
		})
		.then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }, function() { return { ok: res.ok, data: {} }; }); })
		.then(function(result) {
			if (result.ok && result.data && result.data.success) {
				window.location.reload();
				return;
			}
			var err = result.data || {};
			var errors = (err.data && err.data.errors) ? err.data.errors : {};
			// Show REST API error message (e.g. automlp_no_api_key) in the top notice area.
			if ( err.message !== 'One of the API keys is invalid.' && validationNotice ) {
				var noticeP = validationNotice.querySelector( 'p' );
				if ( noticeP ) {
					noticeP.textContent = err.message;
				}
				validationNotice.style.display = 'block';
			}
			if (errors.openai) {
				msgOpenai.textContent = errors.openai;
				msgOpenai.style.display = 'block';
			}
			if (errors.google) {
				msgGoogle.textContent = errors.google;
				msgGoogle.style.display = 'block';
			}
		})
		.catch(function() {
			var fallback = '<?php echo esc_js( __( 'Request failed. Please try again.', 'wpml-translation-check' ) ); ?>';
			if ( validationNotice ) {
				var noticeP = validationNotice.querySelector( 'p' );
				if ( noticeP ) {
					noticeP.textContent = fallback;
				}
				validationNotice.style.display = 'block';
			} else {
			msgOpenai.textContent = fallback;
			msgOpenai.style.display = 'block';
			}
		})
		.finally(function() {
			if (submitBtn) submitBtn.disabled = false;
		});
	});
})();
</script>
<?php endif; ?>