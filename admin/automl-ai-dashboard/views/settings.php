<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpml-translation-check' ) );
}

$automl_wpml_wizard_lang         = get_option( 'automl_ai_wizard_selected_language', array() );
$automl_wpml_wizard_language_set = is_array( $automl_wpml_wizard_lang ) && ! empty( $automl_wpml_wizard_lang['code'] );
?>
<div class="automl_ai_dashboard-settings">
	<div class="automl_ai_dashboard-settings-container">
		<div class="header">
			<h1><?php echo esc_html__( 'AutoML AI Translate Settings', 'wpml-translation-check' ); ?></h1>
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
		<div id="automl-ai-settings-validation-notice" class="notice notice-error" style="margin: 1rem 0; display: none;" role="alert">
			<p></p>
		</div>

		<?php if ( ! $automl_wpml_wizard_language_set ) : ?>
			<div class="notice notice-warning" style="margin: 1rem 0;">
				<p>
					<strong>
						<?php esc_html_e( 'Please select a translation language first.', 'wpml-translation-check' ); ?>
					</strong>
					<?php esc_html_e( 'Complete the Setup Wizard and choose a language in the Languages step. Until then, API key settings are disabled.', 'wpml-translation-check' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'automl_ai_wizard&step=languages' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Open Setup Wizard', 'wpml-translation-check' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>
			<div class="automl_ai_dashboard-api-settings">
				<form id="automl-ai-settings-credentials-form" method="post" action="#">
					<?php

					$get_providers_key=WPML_AT_Helper::get_providers_key(array('openai', 'google'), true);

					$automl_wpml_ai_credentials = array();

					if(isset($get_providers_key['openai']) && !empty($get_providers_key['openai'])){
						$automl_wpml_ai_credentials['openai']=$get_providers_key['openai'];
					}

					if(isset($get_providers_key['google']) && !empty($get_providers_key['google'])){
						$automl_wpml_ai_credentials['google']=$get_providers_key['google'];
					}
					// Current AI SDK credentials.
					$is_ConnectorsAi             = function_exists('_wp_register_default_connector_settings');
					$is_openai_provider_installed = $is_ConnectorsAi && class_exists( 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider' ) ? true : false;
					$is_google_provider_installed = $is_ConnectorsAi && class_exists( 'WordPress\GoogleAiProvider\Provider\GoogleProvider' ) ? true : false;

					// Current selected models (saved by the addon).
					$automl_wpml_current_models       = get_option( 'automl_ai_translation_models', array() );
					$automl_wpml_current_openai_model = isset( $automl_wpml_current_models['openai'] ) ? $automl_wpml_current_models['openai'] : 'gpt-4o-mini';
					$automl_wpml_current_google_model = isset( $automl_wpml_current_models['google'] ) ? $automl_wpml_current_models['google'] : 'gemini-2.5-flash';
					$automl_wpml_openai_api_key       = isset( $automl_wpml_ai_credentials['openai'] ) ? $automl_wpml_ai_credentials['openai'] : '';
					$automl_wpml_google_api_key       = isset( $automl_wpml_ai_credentials['google'] ) ? $automl_wpml_ai_credentials['google'] : '';

					// Helper function to mask API keys for display
					if ( ! function_exists( 'automl_mask_api_key' ) ) {
						function automl_mask_api_key( $api_key ) {
							return WPML_AT_Helper::mask_api_key( $api_key );
						}
					}

					if ( empty( $automl_wpml_current_openai_model ) && ! empty( $automl_wpml_openai_api_key ) ) {
						$automl_wpml_current_openai_model = 'gpt-4o-mini';
						update_option( 'automl_ai_translation_models', array( 'openai' => $automl_wpml_current_openai_model ) );
					}
					if ( empty( $automl_wpml_current_google_model ) && ! empty( $automl_wpml_google_api_key ) ) {
						$automl_wpml_current_google_model = 'gemini-2.5-flash';
						update_option( 'automl_ai_translation_models', array( 'google' => $automl_wpml_current_google_model ) );
					}

					$automl_wpml_openai_models = array();
					$automl_wpml_google_models = array();

					// Use "has API key" instead of isProviderConfigured() to avoid HTTP requests on every page load.
					$automl_wpml_has_openai_key = ! empty( $automl_wpml_ai_credentials['openai'] );
					$automl_wpml_has_google_key = ! empty( $automl_wpml_ai_credentials['google'] );

					if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
						$automl_wpml_registry = \WordPress\AiClient\AiClient::defaultRegistry();

						// OpenAI text-generation models (cached to avoid repeated API requests).
						if ( $automl_wpml_has_openai_key ) {
							$automl_wpml_cache_key = 'automl_wpml_openai_models';
							$automl_wpml_cached    = get_transient( $automl_wpml_cache_key );
							if ( false !== $automl_wpml_cached && is_array( $automl_wpml_cached ) ) {
								$automl_wpml_openai_models = $automl_wpml_cached;
							} else {
								try {
									if (
										class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) &&
										class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
									) {
										$automl_requirements     = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
											array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
											array()
										);
										$automl_models_metadata = $automl_wpml_registry->findProviderModelsMetadataForSupport( 'openai', $automl_requirements );

										$automl_wpml_openai_models = array_map(
											static function ( $model ) {
												/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
												return $model->getId();
											},
											$automl_models_metadata
										);
										
										// Filter out non-translation models (audio, transcribe, search, codex, etc.)
										$automl_wpml_openai_models = array_filter( $automl_wpml_openai_models, function( $model_id ) {
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
										set_transient( $automl_wpml_cache_key, $automl_wpml_openai_models, 24 * HOUR_IN_SECONDS );
									}
								} catch ( \Throwable $e ) {
									$automl_wpml_openai_models = array();
								}
							}
						} else {
							$automl_wpml_cache_key = 'automl_wpml_openai_models';
							delete_transient( $automl_wpml_cache_key );
						}

						// Google / Gemini text-generation models (cached to avoid repeated API requests).
						if ( $automl_wpml_has_google_key ) {
							$automl_wpml_cache_key = 'automl_wpml_google_models';
							$automl_wpml_cached    = get_transient( $automl_wpml_cache_key );
							if ( false !== $automl_wpml_cached && is_array( $automl_wpml_cached ) ) {
								$automl_wpml_google_models = $automl_wpml_cached;
							} else {
								try {
									if (
										class_exists( '\WordPress\AiClient\Providers\Models\DTO\ModelRequirements' ) &&
										class_exists( '\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum' )
									) {
										$automl_requirements     = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
											array( \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ),
											array()
										);
										$automl_models_metadata = $automl_wpml_registry->findProviderModelsMetadataForSupport( 'google', $automl_requirements );

										$automl_wpml_google_models = array_map(
											static function ( $model ) {
												/** @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model */
												return $model->getId();
											},
											$automl_models_metadata
										);
										
										// Filter out non-translation models (audio, transcribe, search, etc.)
										$automl_wpml_google_models = array_filter( $automl_wpml_google_models, function( $model_id ) {
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
										set_transient( $automl_wpml_cache_key, $automl_wpml_google_models, 24 * HOUR_IN_SECONDS );
									}
								} catch ( \Throwable $e ) {
									$automl_wpml_google_models = array();
								}
							}
						} else {
							$automl_wpml_cache_key = 'automl_wpml_google_models';
							delete_transient( $automl_wpml_cache_key );
						}

						// If we have a key but model list is empty (e.g. fetch failed), show selector with default so user can still save.
						if ( $automl_wpml_has_openai_key && empty( $automl_wpml_openai_models ) ) {
							$automl_wpml_openai_models = array( 'gpt-4o-mini' );
						}
						if ( $automl_wpml_has_google_key && empty( $automl_wpml_google_models ) ) {
							$automl_wpml_google_models = array( 'gemini-2.5-flash' );
						}
					}
					?>
						<?php
						// Providers shown in the UI.
						$automl_wpml_api_settings = array(
							'openai' => array(
								'name'        => 'OpenAI',
								'doc_url'     => 'https://docs.coolplugins.net/doc/generate-openai-api-key/',
								'placeholder' => 'sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
							'google' => array(
								'name'        => 'Google Gemini',
								'doc_url'     => 'https://docs.coolplugins.net/doc/generate-gemini-ai-api-key/',
								'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
							),
						);

						foreach ( $automl_wpml_api_settings as $automl_wpml_api_key => $automl_wpml_settings ) :
							?>
							<label for="<?php echo esc_attr( $automl_wpml_api_key ); ?>-api">
								<?php
								printf(
									// translators: %s: API name.
									esc_html__( 'Add %s API key', 'wpml-translation-check' ),
									esc_html( $automl_wpml_settings['name'] )
								);
								?>
							</label>
							<div class="input-group">
								<?php
								$automl_wpml_has_existing_key = isset( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] ) && ! empty( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] );
								$automl_wpml_masked_key = $automl_wpml_has_existing_key ? automl_mask_api_key( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] ) : '';

								?>
								<div style="display: flex; align-items: center; gap: 8px; width: 100%;">
									<input
										type="text"
										id="<?php echo esc_attr( $automl_wpml_api_key ); ?>-api"
										name="wp_ai_client_provider_credentials[<?php echo esc_attr( $automl_wpml_api_key ); ?>]"
										value="<?php echo $automl_wpml_has_existing_key ? esc_attr( $automl_wpml_masked_key ) : ''; ?>"
										placeholder="<?php echo ! $automl_wpml_has_existing_key ? esc_attr( $automl_wpml_settings['placeholder'] ) : ''; ?>"
										autocomplete="new-password"
										<?php echo $automl_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
										data-has-key="<?php echo $automl_wpml_has_existing_key ? '1' : '0'; ?>"
										data-original-masked="<?php echo $automl_wpml_has_existing_key ? esc_attr( $automl_wpml_masked_key ) : ''; ?>"
										style="flex: 1;"
										<?php echo $automl_wpml_has_existing_key ? 'disabled="disabled"' : ''; ?>
									/>
									<?php
$automl_provider_installed = ( 'openai' === $automl_wpml_api_key )
    ? $is_openai_provider_installed
    : $is_google_provider_installed;
$automl_connectors_url = admin_url( 'options-connectors.php' );
if ( $is_ConnectorsAi ) :
    if ( ! $automl_provider_installed ) :
        $automl_connector_btn_label = __( 'Install', 'wpml-translation-check' );
    elseif ( ! $automl_wpml_has_existing_key ) :
        $automl_connector_btn_label = __( 'Connect', 'wpml-translation-check' );
    else :
        $automl_connector_btn_label = __( 'Edit', 'wpml-translation-check' );
    endif;
    ?>
    <?php if ( $automl_wpml_has_existing_key ) : ?>
        <span style="color: #46b450; font-size: 14px; margin-right: 4px;">✓</span>
    <?php endif; ?>
	<a
    href="<?php echo esc_url( $automl_connectors_url ); ?>"
    class="button button-primary"
    target="_blank"
    rel="noopener noreferrer"
>
    <?php echo esc_html( $automl_connector_btn_label ); ?>
</a>
<?php else : ?>
    <?php if ( $automl_wpml_has_existing_key ) : ?>
        <span style="color: #46b450; font-size: 14px; margin-right: 4px;">✓</span>
        <button
            type="button"
            class="button button-primary automl-reset-key-btn"
            data-provider="<?php echo esc_attr( $automl_wpml_api_key ); ?>"
            title="<?php esc_attr_e( 'Reset API key', 'wpml-translation-check' ); ?>"
            <?php echo $automl_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
        >
            <?php esc_html_e( 'Reset', 'wpml-translation-check' ); ?>
        </button>
    <?php endif; ?>
<?php endif; ?>
								</div>
							</div>
							<div id="automl-ai-settings-message-<?php echo esc_attr( $automl_wpml_api_key ); ?>" class="automl_ai_dashboard-settings-message" style="margin-top: 4px; margin-bottom: 12px; display: none; color: #b32d2e; font-size: 13px;" role="alert"></div>

							<?php
							$automl_wpml_has_key = ! empty( $automl_wpml_ai_credentials[ $automl_wpml_api_key ] );

							// OpenAI model selector.
							if ( 'openai' === $automl_wpml_api_key && $automl_wpml_has_key && ! empty( $automl_wpml_openai_models ) ) :
								?>
								<div class="automl_ai_dashboard-api-settings-openai-model">
									<label for="automl_ai_selected_openai_model" class="api-settings-label">
										<?php esc_html_e( 'Select OpenAI Model', 'wpml-translation-check' ); ?>
									</label>
									<select
										id="automl_ai_selected_openai_model"
										name="automl_ai_translation_models[openai]"
										class="automl-openai-model-select"
										<?php echo $automl_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
									>
									<option value=""><?php esc_html_e( 'Select model...', 'wpml-translation-check' ); ?></option>
									<?php
									$automl_preferred_models = array(
										'gpt-5.4'             => __( 'gpt-5.4 (Best Quality)', 'wpml-translation-check' ),
										'gpt-5.4-pro'         => __( 'gpt-5.4-pro (Highest Accuracy)', 'wpml-translation-check' ),
										'gpt-5.3-chat-latest' => __( 'gpt-5.3-chat-latest (Recommended)', 'wpml-translation-check' ),
										'gpt-5.2'             => __( 'gpt-5.2 (Balanced)', 'wpml-translation-check' ),
										'gpt-5-mini'          => __( 'gpt-5-mini (Fast)', 'wpml-translation-check' ),
										'gpt-5-nano'          => __( 'gpt-5-nano (Cheapest)', 'wpml-translation-check' ),
										'gpt-4o-mini'         => __( 'gpt-4o-mini (Fast & Cheap)', 'wpml-translation-check' ),
									);

									$automl_filtered_models = array_intersect_key(
										$automl_preferred_models,
										array_flip( $automl_wpml_openai_models )
									);

									$automl_display_models = ! empty( $automl_filtered_models )
										? $automl_filtered_models
										: array_combine( $automl_wpml_openai_models, $automl_wpml_openai_models );

									foreach ( $automl_display_models as $automl_model_id => $automl_model_label ) :
									?>
										<option value="<?php echo esc_attr( $automl_model_id ); ?>" <?php selected( $automl_wpml_current_openai_model, $automl_model_id ); ?>>
											<?php echo esc_html( $automl_model_label ); ?>
										</option>
									<?php endforeach; ?>
									</select>
								</div>
								<?php
							endif;

							// Google / Gemini model selector.
							if ( 'google' === $automl_wpml_api_key && $automl_wpml_has_key && ! empty( $automl_wpml_google_models ) ) :
								?>
								<div class="automl_ai_dashboard-api-settings-google-model">
									<label for="automl_ai_selected_google_model" class="api-settings-label">
										<?php esc_html_e( 'Select Gemini Model', 'wpml-translation-check' ); ?>
									</label>
									<select
										id="automl_ai_selected_google_model"
										name="automl_ai_translation_models[google]"
										class="automl-google-model-select"
										<?php echo $automl_wpml_wizard_language_set ? '' : ' disabled="disabled"'; ?>
									>
									<option value=""><?php esc_html_e( 'Select model...', 'wpml-translation-check' ); ?></option>
									<?php
									$automl_preferred_google_models = array(
										'gemini-3.1-pro-preview'        => __( 'gemini-3.1-pro-preview (Best Quality)', 'wpml-translation-check' ),
										'gemini-3.1-flash-lite-preview' => __( 'gemini-3.1-flash-lite-preview (Fast & Cheap)', 'wpml-translation-check' ),
										'gemma-3n-e4b-it'               => __( 'gemma-3n-e4b-it (Cheapest)', 'wpml-translation-check' ),
										'gemini-2.5-pro'                => __( 'gemini-2.5-pro (Best Overall)', 'wpml-translation-check' ),
										'gemini-2.5-flash'              => __( 'gemini-2.5-flash (Balanced)', 'wpml-translation-check' ),
										'gemini-3-flash-preview'        => __( 'gemini-3-flash-preview (Recommended)', 'wpml-translation-check' ),
										'gemini-2.5-pro-preview-tts'    => __( 'gemini-2.5-pro-preview-tts (High Accuracy)', 'wpml-translation-check' ),
									);

									$automl_filtered_google_models = array_intersect_key(
										$automl_preferred_google_models,
										array_flip( $automl_wpml_google_models )
									);

									$automl_display_google_models = ! empty( $automl_filtered_google_models )
										? $automl_filtered_google_models
										: array_combine( $automl_wpml_google_models, $automl_wpml_google_models );

									foreach ( $automl_display_google_models as $automl_model_id => $automl_model_label ) :
									?>
										<option value="<?php echo esc_attr( $automl_model_id ); ?>" <?php selected( $automl_wpml_current_google_model, $automl_model_id ); ?>>
											<?php echo esc_html( $automl_model_label ); ?>
										</option>
									<?php endforeach; ?>
									</select>
								</div>
								<?php
							endif;

							printf(
								// translators: 1: Click here link, 2: API name.
								esc_html__( '%1$s to see how to configure %2$s in the AI SDK.', 'wpml-translation-check' ),
								'<a href="' . esc_url( $automl_wpml_settings['doc_url'] ) . '" target="_blank">' . esc_html__( 'Click here', 'wpml-translation-check' ) . '</a>',
								esc_html( $automl_wpml_settings['name'] )
							);
							echo '<br/><br/>';
						endforeach;
						?>

						<hr>
						<div class="automl_ai_dashboard-save-btn-container">
							<?php submit_button( __( 'Save', 'wpml-translation-check' ), 'primary', 'submit', true, $automl_wpml_wizard_language_set ? array() : array( 'disabled' => 'disabled' ) ); ?>
						</div>
				</form>
			</div>
	</div>
</div>
<?php
// Submit settings via same REST endpoint as wizard so validate_provider_api_key is used.
if ( $automl_wpml_wizard_language_set ) :
	?>
<script>
(function() {
	var form = document.getElementById('automl-ai-settings-credentials-form');
	var msgOpenai = document.getElementById('automl-ai-settings-message-openai');
	var msgGoogle = document.getElementById('automl-ai-settings-message-google');
	var validationNotice = document.getElementById('automl-ai-settings-validation-notice');
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
		if (e.target.classList.contains('automl-reset-key-btn')) {
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
			fetch('<?php echo esc_js( rest_url( 'automl-bulk-translate/' ) ); ?>wizard-save-credentials', {
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
					var msgElement = document.getElementById('automl-ai-settings-message-' + provider);
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
				var msgElement = document.getElementById('automl-ai-settings-message-' + provider);
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
		
		var openaiModel = document.getElementById('automl_ai_selected_openai_model') ? document.getElementById('automl_ai_selected_openai_model').value : '';
		var googleModel = document.getElementById('automl_ai_selected_google_model') ? document.getElementById('automl_ai_selected_google_model').value : '';
		
		// Build request data - only include keys that should be updated
		var requestData = {
			openai_model: openaiModel || null,
			google_model: googleModel || null
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
		fetch('<?php echo esc_js( rest_url( 'automl-bulk-translate/' ) ); ?>wizard-save-credentials', {
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
			// Show REST API error message (e.g. automl_no_api_key) in the top notice area.
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