import { filterContent, updateFilterContent } from './components/filter-content';
import { updatePendingPosts, unsetPendingPost, updateCompletedPosts, updateTranslatePostInfo, updateCountInfo, updateSourceContent, updateParentPostsInfo, updateTargetContent, updateTargetLanguages, updateProgressStatus, updateErrorPostsInfo } from './redux-store/features/actions';
import { store } from './redux-store/store';
import { __, sprintf } from '@wordpress/i18n';
import Provider from './components/translate-provider';
import { updateTranslateData, translateFieldNameSort, getContentCount } from './helper';
import LoopCallback from './components/loop-callback';
import {selectTranslatePostInfo } from './redux-store/features/selectors';

const initBulkTranslate = async (postKeys = [], nonce, storeDispatch, prefix, updateDestoryHandler) => {
    const pendingPosts = store.getState().pendingPosts;

    if (pendingPosts.length < 1) {
        return;
    }

    let modalClosed = false;

    updateDestoryHandler(
        () => {
            modalClosed = true;
        }
    )

    const translatePost = async (index) => {
        const postId = postKeys[index];

        if (!postId || modalClosed) {
            return;
        }

        const postContent = store.getState().parentPostsInfo[postId];

        if (postContent) {
            const { originalContent: { title, content, post_name, excerpt }, languages, editorType, sourceLanguage } = postContent;

            if (!languages || languages.length === 0) {
                console.log(`All target languages for post ${postId} already exist. Skipping translation.`);
                return;
            }

            if (!['block', 'Elementor'].includes(editorType)) {
                for (const lang of languages) {
                    storeDispatch(unsetPendingPost(postId + '_' + lang));
                    storeDispatch(updateProgressStatus(100 / pendingPosts.length));
                    storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: { status: 'error', messageClass: 'error', errorMessage: __('This post editor type is not supported for translation', 'wpml-translation-check') } }));
                }
            }else{
                const source = { title: title, content: JSON.parse(JSON.stringify(content)), post_name: post_name, excerpt: excerpt };
                
                await translateContent({ sourceLang: sourceLanguage, targetLangs: languages, totalPosts: pendingPosts.length, storeDispatch, prefix, postId, source, editorType, createTranslatePostNonce: nonce, updateDestoryHandler });
            }

        }

        index++;

        if (index > postKeys.length - 1 || modalClosed) {
            return;
        }

        await translatePost(index);
    }

    await translatePost(0);
}

const translateContent = async ({ sourceLang, targetLangs, totalPosts, storeDispatch, postId, prefix, source, editorType, createTranslatePostNonce, updateDestoryHandler }) => {

    const activeProvider = store.getState().serviceProvider;
    const providerDetails = Provider({ Service: activeProvider });

    if (providerDetails && providerDetails.Provider) {

        const updateContentCallback = async (lang) => { await updateContent({ source, postId, sourceLang, lang, createTranslatePostNonce, storeDispatch, editorType }) };

        const data = { sourceLang, targetLangs, totalPosts, storeDispatch, postId, createTranslatePostNonce, updateContent: updateContentCallback, prefix, updateDestoryHandler };

        const provider = new providerDetails.Provider(data);

        await provider.initTranslation();
    }
}

export const updateContent = async ({ source, postId, sourceLang, lang, editorType, createTranslatePostNonce, storeDispatch }) => {

    const service = store.getState().serviceProvider;

    
    const deepCloneSource = JSON.parse(JSON.stringify(source));
    
    const updateContent = await updateFilterContent({ source: deepCloneSource, postId, lang, service });

    const bulkTranslateRouteUrl = automlp_wpml_bulk_translate_object.bulkTranslateRouteUrl;
    const nonce = automlp_wpml_bulk_translate_object.nonce;
    
    storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: { status: 'in-progress', messageClass: 'in-progress' } }));

    let endPoint = 'create-translate-post';

    let body = {
        target_language: lang,
        editor_type: editorType,
        privateKey: createTranslatePostNonce,
        source_language: sourceLang,
    }

    if (editorType === 'taxonomy') {
        endPoint = 'create-translate-taxonomy';
        body.term_id = postId;
        body.taxonomy_name = updateContent.title || '';
        body.taxonomy_description = updateContent.content || '';
        body.taxonomy = automlp_wpml_bulk_translate_object.taxonomy_page;

        if (updateContent.post_name && updateContent.post_name.trim() !== '') {
            body.taxonomy_slug = updateContent.post_name;
        }
    } else {
        body.post_id = postId;
        body.post_title = updateContent.title || '';
        body.post_name = updateContent.post_name || '';
        body.post_content = updateContent.content ? JSON.stringify(updateContent.content) : '';
        body.post_excerpt = updateContent.excerpt || '';
    }

    await fetch(bulkTranslateRouteUrl + `/${postId}/${endPoint}`, {
        method: 'POST',
        body: new URLSearchParams(body),
        headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
        }
    }).then(async response => {
        const data = await response.json();

        let updateData = {};

        if (data.success && data.data.post_id) {

            const extraData = {};

            if (editorType === 'taxonomy') {
                extraData.taxonomy = automlp_wpml_bulk_translate_object.taxonomy_page;
            }

            updateTranslateData({ provider: service, sourceLang, targetLang: lang, currentPostId: data.data.post_id, parentPostId: postId, editorType, updateTranslateDataNonce: data?.data?.update_translate_data_nonce, extraData });

            data.data.post_title = '' === data.data.post_title ? __('N/A', 'wpml-translation-check') : data.data.post_title;
            updateData = { targetPostId: data.data.post_id, targetPostTitle: data.data.post_title, targetLanguage: lang, postLink: data.data.post_link, postEditLink: data.data.post_edit_link, status: 'completed', messageClass: 'success' };
            storeDispatch(updateCountInfo({ postsTranslated: store.getState().countInfo.postsTranslated + 1 }));
        } else {
            if (data.data && data.data.error) {
                let errorHtml = 'Error Code:' + (data.data.status);

                if (typeof data.data.error === 'string') {
                    errorHtml += '<br>Error Message:' + data.data.error + '(' + data.data.error + ')';
                }

                if (typeof data.data.error === 'object') {
                    errorHtml += '<br>Error Message:' + JSON.stringify(data.data.error);
                }

                updateData = { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + errorHtml + '</div>' };
            } else if (data.code && data.message) {
                updateData = { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + data.message + '</div>' };
            } else if (!data.success || data.data) {
                updateData = { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + data.data + '</div>' };
            } else if (!data.data.post_id) {
                updateData = { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + data.data + '</div>' };
            } else if (typeof data === 'string') {
                updateData = { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + data + '</div>' };
            }
        }

        storeDispatch(unsetPendingPost(postId + '_' + lang));
        storeDispatch(updateCompletedPosts([postId + '_' + lang]));
        storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: updateData }));

    }).catch(error => {
        console.log(error);
        storeDispatch(unsetPendingPost(postId + '_' + lang));
        storeDispatch(updateCompletedPosts([postId + '_' + lang]));
        let errorHtml = error;

        if (error.message) {
            errorHtml = error.message;
        }

        if (error.data && error.data.status) {
            errorHtml = 'Error Code:' + (error.data.status);

            if (typeof error.data.error === 'string') {
                errorHtml += '<br>Error Message:' + error.data.error;
            }

            if (typeof error.data.error === 'object') {
                errorHtml += '<br>Error Message:' + JSON.stringify(error.data.error);
            }
        }

        storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: { status: 'error', messageClass: 'error', errorMessage: __('Post not created. Please try again.', 'wpml-translation-check'), errorHtml: '<div class="automlp-wpml-error-html">' + errorHtml + '</div>' } }));
    })
}

const bulkTranslateEntries = async ({ ids, langs, storeDispatch }) => {

    const bulkTranslateRouteUrl = automlp_wpml_bulk_translate_object.bulkTranslateRouteUrl;
    const bulkTranslatePrivateKey = automlp_wpml_bulk_translate_object.bulkTranslatePrivateKey;
    const nonce = automlp_wpml_bulk_translate_object.nonce;

    const body = {
        ids: JSON.stringify(ids),
        lang: JSON.stringify(langs),
        privateKey: bulkTranslatePrivateKey,
    }

    let postUrl = 'automlp_wpmlp/bulk-translate-entries';

    if (automlp_wpml_bulk_translate_object.taxonomy_page && '' !== automlp_wpml_bulk_translate_object.taxonomy_page) {
        body.taxonomy = automlp_wpml_bulk_translate_object.taxonomy_page;
        postUrl = 'automlp_wpmlp/bulk-translate-taxonomy-entries';
    }

    const untranslatedPosts = await fetch(bulkTranslateRouteUrl + '/' + postUrl, {
        method: 'POST',
        body: new URLSearchParams(body),
        headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
        }
    })

    const untranslatedPostsData = await untranslatedPosts.json();

    if (!untranslatedPostsData.success && !untranslatedPostsData.code && untranslatedPostsData.data && untranslatedPostsData.data.message) {
        return { success: false, message: untranslatedPostsData.data.message };
    } else if (!untranslatedPostsData.success && !untranslatedPostsData.message && untranslatedPostsData.data.error) {
        return { success: false, message: JSON.stringify(untranslatedPostsData.data.error) };
    } else if (!untranslatedPostsData.success && untranslatedPostsData.message && untranslatedPostsData.data.trace && untranslatedPostsData.data.message) {
        // trace key aur uska data hatao, phir pura object stringify karo
        if (untranslatedPostsData.data && untranslatedPostsData.data.trace) {
            delete untranslatedPostsData.data.trace;
        }
        return { success: false, message: JSON.stringify(untranslatedPostsData.data) };
    }else if(!untranslatedPostsData.success && untranslatedPostsData.data.error && untranslatedPostsData.data.error.message) {
        return { success: false, message: untranslatedPostsData.data.error.message };
    } else if (!untranslatedPostsData.success && untranslatedPostsData.message) {
        return { success: false, message: untranslatedPostsData.message };
    }

    if (!untranslatedPostsData) {
        return { success: false, message: __('No posts to translate data undefined', 'wpml-translation-check') };
    }

    if (!untranslatedPostsData.success) {
        return { success: false, message: untranslatedPostsData.message };
    }

    if (!untranslatedPostsData.data) {
        return { success: false, message: __('No posts to translate untranslated data not found', 'wpml-translation-check') };
    }

    if (!untranslatedPostsData.data.posts || Object.keys(untranslatedPostsData.data.posts).length === 0) {
        return { success: false, message: sprintf(__('Translations already exist for all selected %s in the chosen languages. There are no new %s to translate.', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label, automlp_wpml_bulk_translate_object.post_label) };
    }

    if (!untranslatedPostsData.data.CreateTranslatePostNonce) {
        return { success: false, message: __('No create translate post nonce', 'wpml-translation-check') };
    }

    const posts = untranslatedPostsData.data.posts;

    const postKeys = Object.keys(posts);


    if (postKeys.length > 0) {
        let allTargetLanguages = {};

        const postIdExist = new Array();
        const existsPostInPendingPosts = Object.keys(store.getState().translatePostInfo);

        
        postKeys.forEach(postId => {
            postId = parseInt(postId);
            const languages = posts[postId].languages;
            const parentPostTitle = posts[postId].title;

            allTargetLanguages[posts[postId].sourceLanguage] = { languages: [...(allTargetLanguages[posts[postId].sourceLanguage]?.languages || []), ...(posts[postId].languages || [])] };

            if (languages && languages.length > 0) {
                languages.forEach(language => {

                    if (existsPostInPendingPosts.includes(postId + '_' + language)) {
                        return;
                    }

                    let firstPostLanguage = false;

                    if (!postIdExist.includes(postId) && !existsPostInPendingPosts.includes(postId + '_' + language)) {
                        postIdExist.push(postId);
                        firstPostLanguage = true;
                    }

                    const flagUrl = automlp_wpml_bulk_translate_object.languageObject[language].flag;
                    const languageName = automlp_wpml_bulk_translate_object.languageObject[language].name;
                    storeDispatch(updatePendingPosts([postId + '_' + language]));
                    storeDispatch(updateTranslatePostInfo({ [postId + '_' + language]: { parentPostId: postId, targetPostId: null, targetLanguage: language, postLink: null, status: 'pending', parentPostTitle, firstPostLanguage, flagUrl, languageName, messageClass: 'warning' } }));
                });
            }
        });

        const storeSourceContent = async (index, translatePostsCount) => {

            const postId = postKeys[index];
            const activeProvider = store.getState().serviceProvider;
            const { title, post_name, languages, editor_type, sourceLanguage, excerpt = null } = posts[postId];
            
            let content=posts[postId] && posts[postId].content ? posts[postId].content : {};

            if (!sourceLanguage) {
                const postTitle = title || 'N/A';
                let titleLink = false;
                let postLink = false;
                if (posts[postId]?.post_link) {
                    postLink = posts[postId].post_link;
                    titleLink = postLink;
                }

                const errorInfo = {
                    title: title,
                    editorType: editor_type,
                    sourceLanguage,
                    errorMessage: sprintf(
                        __('Set source language for this %s %s before translating.', 'wpml-translation-check'),
                        titleLink ? '<a href="' + titleLink + '" target="_blank" rel="noopener noreferrer">' + postTitle + '</a>' : postTitle,
                        window?.automlp_wpml_bulk_translate_object?.taxonomy_page || window?.automlp_wpml_bulk_translate_object?.post_label
                    )
                }

                if (posts[postId]?.post_link) {
                    errorInfo.postLink = postLink;
                }

                storeDispatch(updateErrorPostsInfo({
                    postId,
                    data: errorInfo
                }));

                storeDispatch(updateCountInfo({ errorPosts: store.getState().countInfo.errorPosts + 1 }));

                index++;
                if (index > postKeys.length - 1) {
                    return;
                }

                await storeSourceContent(index, translatePostsCount);

                return;
            }

            if (languages && languages.length > 0) {
                let targetLang=languages;

                storeDispatch(updateTargetLanguages({ lang: targetLang }));

                const data = { content, editorType: editor_type, service: activeProvider, postId, storeDispatch, sourceLanguage };
                
                if (content && content !== '') {
                    await filterContent(data);
                }


                    if (title && title.trim() !== '') {
                        let filteredTitle = title;
                        storeDispatch(updateSourceContent({ postId, uniqueKey: 'title', value: title }));
                        storeDispatch(updateTargetContent({ postId, uniqueKey: 'title', value: filteredTitle }));
                    }

                    if (post_name && post_name.trim() !== '') {
                        let filteredPostName = post_name;
                        storeDispatch(updateSourceContent({ postId, uniqueKey: 'post_name', value: post_name }));
                        storeDispatch(updateTargetContent({ postId, uniqueKey: 'post_name', value: filteredPostName }));
                    }

                    if (excerpt && excerpt.trim() !== '') {
                        let filteredExcerpt = excerpt;
                        storeDispatch(updateSourceContent({ postId, uniqueKey: 'excerpt', value: excerpt }));
                        storeDispatch(updateTargetContent({ postId, uniqueKey: 'excerpt', value: filteredExcerpt }));
                    }

                    const previousParentPostsInfo = store.getState().parentPostsInfo[postId];

                    let charactersCount = (previousParentPostsInfo?.charactersCount || 0);
                    let wordsCount = (previousParentPostsInfo?.wordsCount || 0);
                    let stringsCount = (previousParentPostsInfo?.stringsCount || 0);

                    const originalContent = {};

                    if (title && title.trim() !== '') {
                        const titleCounts = getContentCount(title);
                        charactersCount += titleCounts.charactersCount;
                        wordsCount += titleCounts.wordsCount;
                        stringsCount += titleCounts.stringsCount;
                        originalContent.title = title;
                    }

                    if (content) {
                        originalContent.content = content;
                    } else {
                        originalContent.content = {};
                    }

                    if (post_name && post_name.trim() !== '') {
                        originalContent.post_name = post_name;
                    }

                    if (excerpt && excerpt.trim() !== '') {
                        originalContent.excerpt = excerpt;
                    }

                    storeDispatch(updateParentPostsInfo({ postId, data: { editorType: editor_type, originalContent, languages: targetLang, sourceLanguage, charactersCount, wordsCount, stringsCount } }));
            } else {
                console.log(`All target languages for post ${postId} already exist. Skipping translation.`);
            }

            index++;
            if (index > postKeys.length - 1) {
                return;
            }

            await storeSourceContent(index, translatePostsCount);
        }

        const translatePostsCount = store.getState().pendingPosts.length;

        await storeSourceContent(0, translatePostsCount);

        return { postKeys, nonce: untranslatedPostsData.data.CreateTranslatePostNonce };
    }
}

export { bulkTranslateEntries, initBulkTranslate };
