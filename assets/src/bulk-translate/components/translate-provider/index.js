import { sprintf, __ } from '@wordpress/i18n';

/**
 * Provider metadata for the picker in step 2.
 *
 * Under the background flow this is presentation only: the server chooses and
 * calls the provider, so there is no `Provider` class here any more. The slug
 * is passed through to the queue so the user's choice is still honoured.
 */
export default (props) => {
	props = props || {};

	const { Service = false, prefix = '' } = props;

	const adminUrl = window.automlp_wpml_bulk_translate_object.admin_url;

	const settingsLink = adminUrl + 'admin.php?page=automlp_ai_dashboard&tab=settings';

	const addKeyLink = (
		<a
			className={`${prefix}-provider-error`}
			href={settingsLink}
			target="_blank"
			rel="noopener noreferrer"
		>
			{__('Add API Key', 'wpml-translation-check')}
		</a>
	);

	const Services = {
		openai_ai: {
			slug: 'openai',
			title: 'OpenAI Model',
			SettingBtnText: 'Translate',
			serviceLabel: 'OpenAI',
			heading: sprintf(
				__('Translate Using %s Model', 'wpml-translation-check'),
				'OpenAI'
			),
			Docs: 'https://docs.coolplugins.net/doc/openai-translation-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_openai',
			BetaEnabled: true,
			ButtonDisabled: props.openai_aiButtonDisabled,
			ErrorMessage: props.openai_aiButtonDisabled ? addKeyLink : <></>,
			Logo: 'openai.png',
		},
		google_ai: {
			slug: 'google',
			title: 'Gemini Model',
			SettingBtnText: 'Translate',
			serviceLabel: 'Gemini',
			heading: sprintf(
				__('Translate Using %s Model', 'wpml-translation-check'),
				'Gemini'
			),
			Docs: 'https://docs.coolplugins.net/doc/gemini-ai-translation-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=bulk_translate_gemini',
			BetaEnabled: true,
			ButtonDisabled: props.google_aiButtonDisabled,
			ErrorMessage: props.google_aiButtonDisabled ? addKeyLink : <></>,
			Logo: 'gemini.png',
		},
		localAiTranslator: {
			slug: '',
			title: 'Chrome Built-in AI',
			SettingBtnText: 'Translate',
			serviceLabel: 'Chrome AI Translator',
			heading: sprintf(
				__('Translate Using %s', 'wpml-translation-check'),
				'Chrome built-in API'
			),
			Docs: 'https://docs.coolplugins.net/doc/chrome-ai-translation-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=popup_chrome',
			BetaEnabled: true,
			ButtonDisabled: true,
			ErrorMessage: (
				<a
					className="atfp-provider-error"
					href="https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=bulk_translate_chrome"
					target="_blank"
					rel="noopener noreferrer"
				>
					{__('Buy Pro', 'wpml-translation-check')}
				</a>
			),
			Logo: 'chrome.png',
		},
	};

	if (!Service) {
		return Services;
	}

	return Services[Service];
};
