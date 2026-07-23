import { select } from '@wordpress/data';
import { store } from '../redux-store/store';
import { availableContentTypes } from "../redux-store/features/selectors";

export const AITranslationRequest = async ({ controller, Strings, slug, source_language, target_language }) => {

    const data = {
        automlp_wpml_nonce: automlp_wpml_bulk_translate_object.ai_translate_nonce,
        action: 'automlp_wpml_ai_translation',
        strings: JSON.stringify(Strings),
        source_language: source_language,
        target_language: target_language,
        service_slug: slug
    }

    const response = await fetch(`${automlp_wpml_bulk_translate_object.bulkTranslateRouteUrl}/${slug}/translate-text`, {
        method: 'POST',
        headers: {
            'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-WP-Nonce': automlp_wpml_bulk_translate_object.ai_translate_route_nonce
        },
        signal: controller.signal,
        body: new URLSearchParams(data)
    });

    if (!response.ok) {
        const error = await response.json();
        console.error('WordPress Error:', error);
    }

    const responseData = await response.json();
    return responseData;
}