/**
 * Get nonce from localized script data.
 */
export const getNonce = () => {
	return window.wpml_at_setup?.nonce || '';
};