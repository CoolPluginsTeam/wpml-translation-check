import { createSlice } from '@reduxjs/toolkit';

const bulkTranslateStore = createSlice({
  name: 'automl-wpml-bulk-translate',
  initialState: {
    completedPosts: [],
    pendingPosts: [],
    parentPostsInfo: {},
    translatePostInfo: [],
    progressStatus: 0,
    targetLanguages: [],
    countInfo: {
      totalPosts: 0,
      postsTranslated: 0,
      stringsTranslated: 0,
      charactersTranslated: 0,
      errorPosts: 0,
    },
    translatedContent: {},
    serviceProvider: '',
    blockParseRules: {},
    errorPostsInfo: {},
  },
  reducers: {
    updateCompletedPosts: (state, action) => {
      state.completedPosts = [...state.completedPosts || [], ...action.payload];
    },
    updatePendingPosts: (state, action) => {
      state.pendingPosts = [...state.pendingPosts || [], ...action.payload];
    },
    unsetPendingPost: (state, action) => {
      state.pendingPosts = state.pendingPosts.filter(post => post !== action.payload);
    },
    updateTranslatePostInfo: (state, action) => {
      // Improved: Merge translatePostInfo with new info, supporting both single and multiple updates
      if (action.payload && typeof action.payload === 'object') {
        // If payload is a map of postId -> info
        if (!Array.isArray(action.payload)) {
          state.translatePostInfo = {
            ...state.translatePostInfo,
            ...Object.keys(action.payload).reduce((acc, postId) => {
              acc[postId] = {
                ...(state.translatePostInfo?.[postId] || {}),
                ...action.payload[postId]
              };
              return acc;
            }, {})
          };
        }
      }
    },

    updateProgressStatus: (state, action) => {
      state.progressStatus += action.payload;
    },

    updateCountInfo: (state, action) => {
      state.countInfo = {
        ...state.countInfo,
        ...action.payload
      };
    },

    updateSourceContent: (state, action) => {
      state.translatedContent = { ...state.translatedContent, [action.payload.postId]: { ...(state.translatedContent?.[action.payload.postId] || {}), [action.payload.uniqueKey]: {...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}), ...{source: action.payload.value}} } };
    },

    updateTargetContent: (state, action) => {
      state.translatedContent = { ...state.translatedContent, [action.payload.postId]: { ...(state.translatedContent?.[action.payload.postId] || {}), [action.payload.uniqueKey]: {...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}), ...{targetContent: action.payload.value}} } };
    },

    updateTranslatedContent: (state, action) => {
      state.translatedContent = { ...state.translatedContent, [action.payload.postId]: { ...(state.translatedContent?.[action.payload.postId] || {}), [action.payload.uniqueKey]: {...(state.translatedContent?.[action.payload.postId]?.[action.payload.uniqueKey] || {}), ...{translation: {...(action.payload.translation || {}), [action.payload.provider]: {...(action.payload.translation?.[action.payload.provider] || {}), [action.payload.key]: action.payload.value}}}} } };
    },

    updateParentPostsInfo: (state, action)=>{
      state.parentPostsInfo = {
        ...state.parentPostsInfo,
        [action.payload.postId]: {
          ...(state.parentPostsInfo?.[action.payload.postId] || {}),
          ...action.payload.data
        }
      };
    },

    updateServiceProvider: (state, action) => {
      if(['localAiTranslator','google', 'google_ai', 'openai_ai'].includes(action.payload)){
        state.serviceProvider = action.payload;
      }
    },

    updateTargetLanguages: (state, action) => {
      if(action.payload.lang && action.payload.lang.length > 0){
        state.targetLanguages = [...state.targetLanguages, ...action.payload.lang];
      }
    },

    updateBlockParseRules: (state, action) => {
      state.blockParseRules = action.payload;
    },

    updateErrorPostsInfo: (state, action) => {
      state.errorPostsInfo = {...state.errorPostsInfo, ...{[action.payload.postId]: action.payload.data}};
    },

    resetStore: (state)=>{
      state.pendingPosts = [];
      state.progressStatus = 0;
      state.completedPosts = [];
      state.translatePostInfo = [];
      state.targetLanguages = [];
      state.serviceProvider = '';
      state.parentPostsInfo = {};
      state.countInfo = {
        totalPosts: 0,
        postsTranslated: 0,
        stringsTranslated: 0,
        charactersTranslated: 0,
        errorPosts: 0,
      };
      state.translatedContent = {};
      state.blockParseRules = {};
      state.errorPostsInfo = {};
    }
  },
});

export const { updateTranslationsLanguages, updateCompletedPosts, updatePendingPosts, unsetPendingPost, updateTranslatePostInfo, updateProgressStatus, updateCountInfo, updateSourceContent, updateTranslatedContent, resetStore, updateTargetContent, updateParentPostsInfo, updateServiceProvider, updateTargetLanguages, updateBlockParseRules, updateErrorPostsInfo } = bulkTranslateStore.actions;

// Export reducer
export default bulkTranslateStore.reducer;
