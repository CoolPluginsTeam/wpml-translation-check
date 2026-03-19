import { __ } from '@wordpress/i18n';

export default function WizardHelpFooter({
	href = 'https://docs.coolplugins.net/plugin/ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup',
}) {
	return (
		<div className="automlp-ai-wizard-card-footer">
			{ __( 'Need help? Visit our', 'wpml-translation-check' ) }{' '}
			<a href={ href } target="_blank" rel="noopener noreferrer">
				{ __( 'Documentation', 'wpml-translation-check' ) }
			</a>
		</div>
	);
}