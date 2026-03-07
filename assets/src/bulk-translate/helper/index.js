import { select } from '@wordpress/data';
import { store } from '../redux-store/store';
import { availableContentTypes } from "../redux-store/features/selectors";

export const updateTranslateData = ({ provider, sourceLang, targetLang, parentPostId, currentPostId, editorType, updateTranslateDataNonce, extraData = {} }) => {
    if (!updateTranslateDataNonce || !currentPostId || !parentPostId || !provider || !sourceLang || !targetLang || !editorType) return;

    const parentPostInfo = store.getState().parentPostsInfo[parentPostId];
    const translateData = store.getState().translatePostInfo[parentPostId + '_' + targetLang];

    let sourceCount={
        wordsCount: parentPostInfo.wordsCount || 0,
        charactersCount: parentPostInfo.charactersCount || 0,
        stringsCount: parentPostInfo.stringsCount || 0
    }

    const totalStringCount = translateData.stringsTranslated || 0;
    const totalWordCount = translateData.wordsTranslated || 0;
    const totalCharacterCount = translateData.charactersTranslated || 0;
    const timeTaken = (translateData.duration || 0) / 1000;
    const sourceWordCount = sourceCount.wordsCount;
    const sourceCharacterCount = sourceCount.charactersCount;
    const sourceStringCount = sourceCount.stringsCount;
    const date = new Date().toISOString();

    const data = { provider, totalStringCount, totalWordCount, totalCharacterCount, editorType, date, sourceStringCount, sourceWordCount, sourceCharacterCount, sourceLang, targetLang, timeTaken, action: automl_wpml_bulk_translate_object.update_translate_data, translate_data_nonce: updateTranslateDataNonce, post_id: currentPostId, ajax_url: automl_wpml_bulk_translate_object.ajax_url, extraData: JSON.stringify(extraData), bulk_translate: true };

    fetch(automl_wpml_bulk_translate_object.ajax_url, {
        method: 'POST',
        headers: {
            'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
        },
        body: new URLSearchParams(data)
    }).then().catch(error => {
        console.error(error);
    });
}

export const AITranslationRequest = async ({ controller, Strings, slug, source_language, target_language }) => {

    const data = {
        automl_wpml_nonce: automl_wpml_bulk_translate_object.ai_translate_nonce,
        action: 'automl_wpml_ai_translation',
        strings: JSON.stringify(Strings),
        source_language: source_language,
        target_language: target_language,
        service_slug: slug
    }

    const response = await fetch(`${automl_wpml_bulk_translate_object.bulkTranslateRouteUrl}/${slug}/translate-text`, {
        method: 'POST',
        headers: {
            'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-WP-Nonce': automl_wpml_bulk_translate_object.ai_translate_route_nonce
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

export const getContentCount=(content)=>{
    const data={charactersCount:0, wordsCount:0, stringsCount:0};

    if(content && content.trim() !== ''){
        data.charactersCount=typeof content === 'string' ? content.length : 0;
        data.wordsCount=typeof content === 'string' ? content.split(/\s+/).filter(word => /[^\p{L}\p{N}]/.test(word)).length : 0;
        data.stringsCount=typeof content === 'string' ? content.split(/(?<=[.!?]+)\s+/).length : 0;
    }

    return data;
}

export const translateFieldNameSort=(fieldNames=[])=>{

    if(!fieldNames || fieldNames.length === 0){
        return [];
    }

    const sortedFieldNames=['title', 'excerpt', 'content'];

     return sortedFieldNames.filter(field =>
        fieldNames.includes(field)
    );
}

export const reTranslationFieldMatch=(old_keys, reTranslationFieldTypes)=>{
    return old_keys.join('_') === reTranslationFieldTypes.join('_');
}