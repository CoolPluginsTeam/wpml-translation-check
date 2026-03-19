import {updateCountInfo, updateTranslatePostInfo} from '../../redux-store/features/actions';
import {store} from '../../redux-store/store';
import { selectTranslatePostInfo } from '../../redux-store/features/selectors';
import { getContentCount } from '../../helper';


const updateTranslationCount=({postId,key,lang, storeDispatch})=>{
    const sourceText=store.getState().translatedContent[postId]?.[key]?.source;

    if(sourceText){
        const contentCounts = getContentCount(sourceText);
        const stringCount = contentCounts.stringsCount;
        const wordCount = contentCounts.wordsCount;
        const characterCount = contentCounts.charactersCount;

        const previousPostInfo=store.getState().translatePostInfo[postId+'_'+lang];
        const previousStringCount=previousPostInfo?.stringsTranslated || 0;
        const previousCharacterCount=previousPostInfo?.charactersTranslated || 0;
        const previousWordCount=previousPostInfo?.wordsTranslated || 0;

        storeDispatch(updateTranslatePostInfo({[postId+'_'+lang]: {
            stringsTranslated: previousStringCount + stringCount,
            charactersTranslated: previousCharacterCount + characterCount,
            wordsTranslated: previousWordCount + wordCount,
        }}));

        storeDispatch(updateCountInfo({
            stringsTranslated: store.getState().countInfo.stringsTranslated + stringCount,
            charactersTranslated: store.getState().countInfo.charactersTranslated + characterCount,
        }));
    }
}


export default updateTranslationCount;