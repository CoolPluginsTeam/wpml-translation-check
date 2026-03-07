import {updateSourceContent, updateTargetContent, updateParentPostsInfo} from '../../redux-store/features/actions';
import { store } from '../../redux-store/store';
import { getContentCount } from '../../helper';

const storeSourceString=(postId, uniqueKey, value, targetContent, storeDispatch)=>{
    storeDispatch(updateSourceContent({postId, uniqueKey, value}));
    storeDispatch(updateTargetContent({postId, uniqueKey, value: targetContent}));
    
    const previousParentPostsInfo=store.getState().parentPostsInfo[postId];
    
    const contentCounts = getContentCount(value);
    
    const charactersCount=(previousParentPostsInfo?.charactersCount || 0) + contentCounts.charactersCount;
    const wordsCount=(previousParentPostsInfo?.wordsCount || 0) + contentCounts.wordsCount;
    const stringsCount=(previousParentPostsInfo?.stringsCount || 0) + contentCounts.stringsCount;

    storeDispatch(updateParentPostsInfo({postId, data: {charactersCount, wordsCount, stringsCount}}));
}

export default storeSourceString;
