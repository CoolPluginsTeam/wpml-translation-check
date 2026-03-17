/**
 * Get nonce from localized script data.
 */
export const getNonce = () => {
	return window.automlp_ai_setup?.nonce || '';
};