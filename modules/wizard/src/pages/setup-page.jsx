import React from 'react';
import { __ } from '@wordpress/i18n';
import SetupProgress from '../components/SetupProgress';

const STEP_KEYS = [ 'video_intro', 'languages', 'ai_translation' ];

function getStepFromUrl() {
	if ( typeof window === 'undefined' ) return 'video_intro';
	const params = new URLSearchParams( window.location.search );
	const step = params.get( 'step' );
	return STEP_KEYS.includes( step ) ? step : 'video_intro';
}

function setStepInUrl( step ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'step', step );
	window.history.replaceState( {}, '', url.toString() );
}

const SetupPage = () => {
	const [currentStep, setCurrentStep] = React.useState( getStepFromUrl );
	const [showReady, setShowReady] = React.useState( false );

	React.useEffect( () => {
		setStepInUrl( currentStep );
	}, [ currentStep ] );

	const data = window.wpml_at_setup || {};
	const dashboardUrl = data.dashboard_url || ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?page=automl_ai_dashboard' );

	const handleGetStarted = () => {
		setCurrentStep( 'languages' );
	};

	const handleFinish = () => {
		setShowReady( true );
	};

	if ( showReady ) {
		return (
				<div className="automl-ai-wizard-card" style={{ maxWidth: 600, margin: '0 auto', padding: 20 }}>
					<h2 style={{ marginTop: 0 }}>{ __( "You're ready to translate with AI", 'wpml-translation-check' ) }</h2>
					<p style={{ color: '#6b7280', marginBottom: 24 }}>
						{ __( "Use the AUTOML AI Translate dashboard to translate your posts and strings with AI.", 'wpml-translation-check' ) }
					</p>
					<a href={ dashboardUrl } className="button button-primary button-hero">
						{ __( 'Open AUTOML AI Translate', 'wpml-translation-check' ) }
					</a>
				</div>
		);
	}

	return (
		<>
			<h1 style={{ textAlign: 'center', paddingTop: 30, marginBottom: 16 }}>
				{ __( 'AutoML – AI Translation for WPML', 'wpml-translation-check' ) }
			</h1>
			<SetupProgress
				currentStep={ currentStep }
				setCurrentStep={ setCurrentStep }
				onGetStarted={ handleGetStarted }
				onFinish={ handleFinish }
			/>
		</>
	);
};

export default SetupPage;