import FilterGutenbergContent from './gutenberg';
import updateGutenbergContent from './gutenberg/update-content';
import Provider from '../translate-provider';

import {selectSourceEntries, selectServiceProvider} from '../../redux-store/features/selectors';

import {store} from '../../redux-store/store';

/**
 * @param {Object} content The content to filter
 * @param {string} editorType The editor type
 * @param {string} service The service provider
 * @param {string} postId The post ID
 * @param {Object} storeDispatch The store dispatch
 * @param {Object} blockParseRules The block parse rules
 * @returns {Object} The filtered content
 */
const filterContent =async ({content, editorType, service, postId, storeDispatch, blockParseRules=null, sourceLanguage=null}) => {

    const filters={
        'block':FilterGutenbergContent,
    }

    const data={content, service, postId, storeDispatch, sourceLanguage};
    data.filterHtmlContent=Provider({Service: service}).filterHtmlContent;

    if(blockParseRules){
        data.blockParseRules=blockParseRules;
    }

    if(filters[editorType] && data.content && data.content !== ''){
        return await filters[editorType](data);
    }

    return content;
}

const updateFilterContent=async ({source, postId, lang, editorType})=>{
    const updateContens={
        'block':updateGutenbergContent,
    }

    const serviceProvider=selectServiceProvider(store.getState());

    const translatedContent=selectSourceEntries(store.getState(), postId);

    return await updateContens[editorType]({source, lang, translatedContent, serviceProvider, postId});
}

export {filterContent, updateFilterContent};
