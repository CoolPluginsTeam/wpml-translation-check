import {updateTranslatedContent} from '../../redux-store/features/actions';
import updateTranslationCount from '../update-translation-count';

const storeTranslateString=(postId, uniqueKey, key, value, provider, lang, storeDispatch)=>{
    updateTranslationCount({postId, key: uniqueKey, lang, storeDispatch});
    storeDispatch(updateTranslatedContent({postId, uniqueKey, key, provider,value}));
}

export default storeTranslateString;
