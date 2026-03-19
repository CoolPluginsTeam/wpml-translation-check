// selector.js — Pure selector functions
export const selectServiceProvider = (state) => state.serviceProvider;
export const selectPendingPosts = (state) => state.pendingPosts;
export const selectCompletedPosts = (state) => state.completedPosts;
export const selectTranslatePostInfo = (state) => state.translatePostInfo;
export const selectProgressStatus = (state) => state.progressStatus;
export const selectCountInfo = (state) => state.countInfo;
export const selectErrorPostsInfo = (state) => state.errorPostsInfo;
export const selectTranslatedContent=(state, postId, uniqueKey, key, provider)=>{
  return state.translatedContent[postId]?.[uniqueKey]?.translation?.[provider]?.[key] || state.translatedContent[postId]?.[uniqueKey]?.source;
}
export const selectSourceContent=(state, postId, uniqueKey)=>{
  return state.translatedContent[postId]?.[uniqueKey]?.source;
}

export const targetLanguages=state=>state.targetLanguages;

/**
 * @param {Object} state
 * @param {string} postId
 * @returns {Object}
 */
export const selectSourceEntries=(state, postId)=>{
  const source={};

  Object.keys(state.translatedContent[postId]).forEach(key=>{
      if(state.translatedContent[postId][key]?.source){
          source[key]=state.translatedContent[postId][key]?.source;
      }
  });

  return source;
}

export const selectTargetContent=(state, postId, fields=false)=>{
    const filteredObject={};

    if(state.translatedContent[postId] && Object.keys(state.translatedContent[postId]).length > 0){
        Object.keys(state.translatedContent[postId]).forEach(key=>{
            if(state.translatedContent[postId][key]?.targetContent && (!fields || fields && typeof fields === 'object' && fields.includes(key.split('_')[0]))){
              filteredObject[key]=state.translatedContent[postId][key]?.targetContent;
            }
        });
    }

    return filteredObject;
}

export const availableContentTypes=(state, postId)=>{
    const content=state.parentPostsInfo[postId]?.originalContent;
    
    if(content){

      const contentTypes=[];
        Object.keys(content).forEach(key=>{
            if(typeof content[key] === 'object' && Object.keys(content[key]).length > 0 || typeof content[key] === 'string' && '' !== content[key]) {
                contentTypes.push(key);
            }
        });

        return contentTypes;
    }

    return [];
}

export const selectTargetLanguages=(state, postId)=>{
  return state.parentPostsInfo[postId]?.languages;
}