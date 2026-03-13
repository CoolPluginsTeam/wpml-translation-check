import React from 'react';
import { __ } from '@wordpress/i18n';
import SetupProgress from '../components/SetupProgress';

const STEP_KEYS = [ 'video_intro', 'ai_translation', 'languages' ];

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

	React.useEffect( () => {
		setStepInUrl( currentStep );
	}, [ currentStep ] );

	const data = window.wpml_at_setup || {};
	const dashboardUrl = data.dashboard_url || ( data.admin_url || '' ).replace( 'admin.php', 'admin.php?page=automlp_ai_dashboard' );

	const handleGetStarted = () => {
		setCurrentStep( 'ai_translation' );
	};

	const handleFinish = () => {
		setShowReady( true );
	};

	return (
		<>
			<h1 style={{ textAlign: 'center', paddingTop: 30, marginBottom: 16 }}>
				{ __( 'AutoMLP – AI Translation for WPML', 'wpml-translation-check' ) }
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