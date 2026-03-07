import Provider from '../translate-provider';
import storeFilteredContent from './store-filtered-content';
import updateFilteredContent from './update-filtered-content';
import {selectSourceEntries, selectServiceProvider} from '../../redux-store/features/selectors';


import {store} from '../../redux-store/store';

/**
 * @param {Object} content The content to filter
 * @param {string} editorType The editor type
 * @param {string} service The service provider
 * @param {string} postId The post ID
 * @param {Object} storeDispatch The store dispatch
 * @returns {Object} The filtered content
 */
const filterContent =async ({content, editorType, service, postId, storeDispatch, sourceLanguage=null}) => {
    const data={content, service, postId, storeDispatch, sourceLanguage};
    data.filterHtmlContent=Provider({Service: service}).filterHtmlContent;

    if(data.content && data.content !== ''){
        return await storeFilteredContent(data);
    }

    return content;
}

const updateFilterContent=async ({source, postId, lang, editorType})=>{

    const serviceProvider=selectServiceProvider(store.getState());

    const translatedContent=selectSourceEntries(store.getState(), postId);

    return await updateFilteredContent({source, lang, translatedContent, serviceProvider, postId});
}

export {filterContent, updateFilterContent};
