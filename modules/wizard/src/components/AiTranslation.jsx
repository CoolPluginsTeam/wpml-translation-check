import React from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import SetupContinueButton, { SetupBackButton } from './SetupContinueButton';
import { getNonce } from '../utils';

const AiTranslation = ({ onBack, onContinue }) => {
	const data = window.wpml_at_setup || {};
	const dashboardUrl =
		data.dashboard_url ||
		( ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?automl_ai_dashboard&tab=settings' ) );
	const saved_models = data.saved_models || {};
	const savedCreds   = data.saved_credentials || {};

	// Helper function to mask API keys
	const maskApiKey = (apiKey) => {
		if (!apiKey || apiKey.length < 12) {
			return apiKey;
		}
		const start = apiKey.substring(0, 6);
		const end = apiKey.substring(apiKey.length - 6);
		const middleLength = apiKey.length - 12;
		const maskedMiddle = '*'.repeat(Math.min(Math.max(middleLength, 0), 24));
		return start + maskedMiddle + end;
	};

	// Check if we have existing keys
	const hasExistingOpenaiKey = !!(savedCreds.openai_key && savedCreds.openai_key.trim());
	const hasExistingGoogleKey = !!(savedCreds.google_key && savedCreds.google_key.trim());

	const [openaiKey, setOpenaiKey] = React.useState( hasExistingOpenaiKey ? maskApiKey(savedCreds.openai_key) : '' );
	const [googleKey, setGoogleKey] = React.useState( hasExistingGoogleKey ? maskApiKey(savedCreds.google_key) : '' );
	const [openaiModel, setOpenaiModel]   = React.useState( saved_models.openai_model || '' );
	const [googleModel, setGoogleModel]   = React.useState( saved_models.google_model || '' );
	const [saving, setSaving]             = React.useState( false );
	const [openaiMessage, setOpenaiMessage]   = React.useState( null ); // error under OpenAI
	const [googleMessage, setGoogleMessage]   = React.useState( null ); // error under Google
	const [generalMessage, setGeneralMessage] = React.useState( null ); // success / general error
	const [isError, setIsError]               = React.useState( false );

	// Track if fields are in edit mode
	const [openaiEditMode, setOpenaiEditMode] = React.useState(!hasExistingOpenaiKey);
	const [googleEditMode, setGoogleEditMode] = React.useState(!hasExistingGoogleKey);

	// Store original masked values
	const openaiMasked = hasExistingOpenaiKey ? maskApiKey(savedCreds.openai_key) : '';
	const googleMasked = hasExistingGoogleKey ? maskApiKey(savedCreds.google_key) : '';

	// Check if at least one API key is available (either existing or newly entered)
	const hasValidApiKey = () => {
		// Check OpenAI key
		const hasOpenaiKey = hasExistingOpenaiKey || (openaiEditMode && openaiKey.trim() !== '' && openaiKey !== openaiMasked);
		// Check Google key
		const hasGoogleKey = hasExistingGoogleKey || (googleEditMode && googleKey.trim() !== '' && googleKey !== googleMasked);
		
		return hasOpenaiKey || hasGoogleKey;
	};

	const handleSave = async () => {
		setSaving( true );
		setOpenaiMessage( null );
		setGoogleMessage( null );
		setGeneralMessage( null );
		setIsError( false );

		// Prepare request data - only send keys that have been changed
		const requestData = {
			openai_model: openaiModel || null,
			google_model: googleModel || null,
			is_wizard: true, // Flag to indicate this is from wizard
		};

		// Only send OpenAI key if it's been edited and is not the masked version
		if (openaiEditMode && openaiKey.trim() !== '' && openaiKey !== openaiMasked) {
			requestData.openai_key = openaiKey;
		} else if (openaiEditMode && openaiKey.trim() === '' && hasExistingOpenaiKey) {
			// Empty field with existing key = reset request
			requestData.openai_key = '';
		}

		// Only send Google key if it's been edited and is not the masked version
		if (googleEditMode && googleKey.trim() !== '' && googleKey !== googleMasked) {
			requestData.google_key = googleKey;
		} else if (googleEditMode && googleKey.trim() === '' && hasExistingGoogleKey) {
			// Empty field with existing key = reset request
			requestData.google_key = '';
		}

		try {
			await apiFetch( {
				path: 'automl-bulk-translate/wizard-save-credentials',
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getNonce(),
				},
				body: JSON.stringify( requestData ),
			} );

			// Success: show a single general success message
			setGeneralMessage( __( 'API keys saved.', 'automl-ai-translation-for-wpml' ) );
			setIsError( false );
			return true;
		} catch ( err ) {
			const fieldErrors = err?.data?.errors || {};

			// Field‑specific errors from PHP
			if ( fieldErrors.openai ) {
				setOpenaiMessage( fieldErrors.openai );
			}
			if ( fieldErrors.google ) {
				setGoogleMessage( fieldErrors.google );
			}

			// No key at all
			if ( err?.code === 'automl_no_api_key' && ! fieldErrors.openai && ! fieldErrors.google ) {
				setGeneralMessage( err?.message );
			}

			// Fallback general error if nothing else
			if ( ! fieldErrors.openai && ! fieldErrors.google && ! generalMessage && err?.message ) {
				setGeneralMessage( err.message );
			}

			setIsError( true );
			return false;
		} finally {
			setSaving( false );
		}
	};

	// Handle input field clicks - make editable when showing masked key
	const handleInputClick = (provider) => {
		if (provider === 'openai' && !openaiEditMode && openaiKey === openaiMasked) {
			setOpenaiEditMode(true);
			setOpenaiKey('');
		} else if (provider === 'google' && !googleEditMode && googleKey === googleMasked) {
			setGoogleEditMode(true);
			setGoogleKey('');
		}
	};

	// Handle input blur - restore masked view if empty
	const handleInputBlur = (provider) => {
		if (provider === 'openai' && openaiEditMode && openaiKey.trim() === '' && hasExistingOpenaiKey) {
			setOpenaiEditMode(false);
			setOpenaiKey(openaiMasked);
		} else if (provider === 'google' && googleEditMode && googleKey.trim() === '' && hasExistingGoogleKey) {
			setGoogleEditMode(false);
			setGoogleKey(googleMasked);
		}
	};

	// Handle reset button clicks - immediately delete the API key
	const handleReset = async (provider) => {
		const requestData = {
			is_reset: true, // Flag to indicate this is a reset operation - bypass validation
		};
		requestData[provider + '_key'] = '';

		try {
			await apiFetch({
				path: 'automl-bulk-translate/wizard-save-credentials',
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': getNonce(),
				},
				body: JSON.stringify(requestData),
			});

			// Success - update state to reflect deletion
			if (provider === 'openai') {
				setOpenaiKey('');
				setOpenaiEditMode(true);
			} else if (provider === 'google') {
				setGoogleKey('');
				setGoogleEditMode(true);
			}

			// Reload to get fresh data
			window.location.reload();
		} catch (err) {
			// Show error message
			const errorMsg = __('Failed to delete API key. Please try again.', 'automl-ai-translation-for-wpml');
			if (provider === 'openai') {
				setOpenaiMessage(errorMsg);
			} else if (provider === 'google') {
				setGoogleMessage(errorMsg);
			}
		}
	};

	return (
		<>
			<div
				className="automl-ai-wizard-card"
			>
				<div className="automl-ai-wizard-language-container" style={{ flex: 1 }}>
					<h2 style={{ marginTop: 0 }}>
						{ __( 'Connect AI Provider', 'automl-ai-translation-for-wpml' ) }
					</h2>
					<p style={{ marginBottom: 12, color: '#6b7280' }}>
						{ __(
							'To start using AI translation, connect at least one AI provider below. Add your AI provider API key to start translating your website with AutoML.',
							'automl-ai-translation-for-wpml'
						) }
					</p>

					{ generalMessage && (
						<p
							style={{
								color: isError ? '#b32d2e' : '#00a32a',
								marginBottom: 12,
								fontSize: 14,
							}}
						>
							{ generalMessage }
						</p>
					) }

					<div className="automl-ai-wizard-api-key-container" style={{ marginBottom: 16, color: '#6b7280' }}>
						<label
							htmlFor="automl-ai-wizard-openai-key"
							style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}
						>
							{ __( 'OpenAI API key', 'automl-ai-translation-for-wpml' ) }
						</label>
						<div style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }}>
							<input
								id="automl-ai-wizard-openai-key"
								type={ openaiEditMode ? 'password' : 'text' }
								value={ openaiKey }
								onChange={ ( e ) => setOpenaiKey( e.target.value ) }
								onClick={ () => handleInputClick('openai') }
								onBlur={ () => handleInputBlur('openai') }
								disabled={ !openaiEditMode }
								placeholder={ openaiEditMode ? __( 'Enter OpenAI API key', 'automl-ai-translation-for-wpml' ) : '' }
								style={{ 
									flex: 1, 
									padding: '8px 12px', 
									fontSize: 14,
									cursor: !openaiEditMode ? 'pointer' : 'text'
								}}
							/>
							{ hasExistingOpenaiKey && !openaiEditMode && (
								<>
									<span style={{ color: '#46b450', fontSize: '14px', marginRight: '4px' }}>✓</span>
									<button
										type="button"
										onClick={ () => handleReset('openai') }
										className="button button-primary automl-reset-key-btn"
									>
										{ __( 'Reset', 'automl-ai-translation-for-wpml' ) }
									</button>
								</>
							) }
						</div>
						{ openaiMessage && (
							<p
								style={{
									color: '#b32d2e',
									marginTop: 4,
									marginBottom: 12,
									fontSize: 13,
								}}
							>
								{ openaiMessage }
							</p>
						) }
						<a href="https://docs.coolplugins.net/doc/generate-openai-api-key/" target="_blank">{ __( 'Click here', 'automl-ai-translation-for-wpml' ) }</a> { ' ' }
						{ __( ' to see how to configure OpenAI in the AI SDK.', 'automl-ai-translation-for-wpml' ) }
					</div>

					<div className="automl-ai-wizard-api-key-container" style={{ marginBottom: 16, color: '#6b7280' }}>
						<label
							htmlFor="automl-ai-wizard-google-key"
							style={{ display: 'block', marginBottom: 6, fontWeight: 500 }}
						>
							{ __( 'Google / Gemini API key', 'automl-ai-translation-for-wpml' ) }
						</label>
						<div style={{ display: 'flex', alignItems: 'center', gap: '8px', width: '100%' }}>
							<input
								id="automl-ai-wizard-google-key"
								type={ googleEditMode ? 'password' : 'text' }
								value={ googleKey }
								onChange={ ( e ) => setGoogleKey( e.target.value ) }
								onClick={ () => handleInputClick('google') }
								onBlur={ () => handleInputBlur('google') }
								disabled={ !googleEditMode }
								placeholder={ googleEditMode ? __( 'Enter Google API key', 'automl-ai-translation-for-wpml' ) : '' }
								style={{ 
									flex: 1, 
									padding: '8px 12px', 
									fontSize: 14,
									cursor: !googleEditMode ? 'pointer' : 'text'
								}}
							/>
							{ hasExistingGoogleKey && !googleEditMode && (
								<>
									<span style={{ color: '#46b450', fontSize: '14px', marginRight: '4px' }}>✓</span>
									<button
										type="button"
										onClick={ () => handleReset('google') }
										className="button button-primary automl-reset-key-btn"
									>
										{ __( 'Reset', 'automl-ai-translation-for-wpml' ) }
									</button>
								</>
							) }
						</div>
						{ googleMessage && (
							<p
								style={{
									color: '#b32d2e',
									marginTop: 4,
									marginBottom: 12,
									fontSize: 13,
								}}
							>
								{ googleMessage }
							</p>
						) }
						<a href="https://docs.coolplugins.net/doc/generate-gemini-ai-api-key/" target="_blank">{ __( 'Click here', 'automl-ai-translation-for-wpml' ) }</a> { ' ' }
						{ __( ' to see how to configure Google Gemini in the AI SDK.', 'automl-ai-translation-for-wpml' ) }
					</div>

					<div className="automl-ai-wizard-card-language-footer">
						<span
							className="automl-ai-wizard-card-language-footer-icon"
							aria-hidden="true"
						>
							<img
								src={
									( data.home_url || '' ) +
									'/wp-content/plugins/wpml-translation-check/assets/images/star-icons.png'
								}
								alt=""
								width={ 18 }
								height={ 18 }
								style={{ display: 'block' }}
							/>
						</span>
						<div className="automl-ai-wizard-card-language-footer-content">
							<p>
								<strong>
									{ __( 'Chrome Built-in AI', 'automl-ai-translation-for-wpml' ) }
								</strong>
								{ ' — ' }
								{ __(
									'Translate on your device using local AI, no API key required, unlimited translations, save 100% on API usage costs.',
									'automl-ai-translation-for-wpml'
								) }
							</p>
							<a
								href={ data.upgrade_url || '#' }
								target="_blank"
								rel="noopener noreferrer"
								className="automl-ai-wizard-card-language-footer-link"
							>
								{ __( 'Upgrade to Pro →', 'automl-ai-translation-for-wpml' ) }
							</a>
						</div>
					</div>

					<p className="automl-ai-wizard-api-note">
						{ __(
							'API keys are saved securely and can be updated anytime in WPML → AutoML AI → Settings.',
							'automl-ai-translation-for-wpml'
						) }
					</p>
				</div>

				<div
					className="automl-ai-wizard-footer"
					style={{ display: 'flex', justifyContent: 'space-between', marginTop: 24 }}
				>
					<SetupBackButton onClick={ onBack } />
					<SetupContinueButton
						onClick={ async () => {
							const saved = await handleSave();
							if ( ! saved ) return;
							try {
								await apiFetch( {
									path: 'automl-bulk-translate/wizard-complete',
									method: 'POST',
									headers: { 'X-WP-Nonce': getNonce() },
								} );
							} catch ( e ) {
								// ignore, we'll still redirect
							}
							window.location.href = dashboardUrl;
						} }
						label={ __( 'Finish setup', 'automl-ai-translation-for-wpml' ) }
						disabled={ saving || !hasValidApiKey() }
					/>
				</div>
			</div>

			<div className="automl-ai-wizard-card-footer">
				{ __( 'Need help? Visit our', 'automl-ai-translation-for-wpml' ) }{ ' ' }
				<a href={ data.doc_url || '#' } target="_blank" rel="noopener noreferrer">
					{ __( 'Documentation', 'automl-ai-translation-for-wpml' ) }
				</a>
			</div>
		</>
	);
};

export default AiTranslation;