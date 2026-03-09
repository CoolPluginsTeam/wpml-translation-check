import { __ } from '@wordpress/i18n';

export default function SetupContinueButton({ onClick, label, disabled }) {
	return (
		<button
			type="button"
			className="button button-primary automl-ai-wizard-continue"
			onClick={ onClick }
			disabled={ disabled }
			style={{ minWidth: 100 }}
		>
			{ label || __( 'Continue', 'wpml-translation-check' ) }
		</button>
	);
}

export function SetupBackButton({ onClick }) {
	return (
		<button
			type="button"
			className="button button-primary automl-ai-wizard-back-button"
			onClick={ onClick }
		>
			{ __( 'Back', 'wpml-translation-check' ) }
		</button>
	);
}