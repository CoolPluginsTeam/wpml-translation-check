import { AITranslationRequest } from "../../../helper";
import storeTranslateString from "../../store-translate-strings";
import Provider from '..';
import { updateProgressStatus, updateTranslatePostInfo } from "../../../redux-store/features/actions";


import { sprintf, __ } from "@wordpress/i18n";
import { selectServiceProvider, selectTargetContent, selectPendingPosts, selectProgressStatus, selectTranslatePostInfo, availableContentTypes } from "../../../redux-store/features/selectors";
import { store } from "../../../redux-store/store";

import {translateFieldNameSort} from "../../../helper/index";

class AIService {
    constructor({ postId = '', sourceLang = '', targetLangs = [], totalPosts = 0, prefix = '', createTranslatePostNonce = '', storeDispatch = () => { }, updateDestoryHandler = () => { }, previousCompletedStrings = 0 }) {
        this.CONCURRENCY_LIMIT = window?.automlp_wpml_bulk_translate_object?.AIRequestBatchSize || 5;
        this.MAX_TOKENS=window?.automlp_wpml_bulk_translate_object?.AIRequestMaxTokens || 500;

        this.activePostId = postId;
        this.activeTargetLangs = '';
        this.sourceLang = sourceLang;
        this.targetLangs = targetLangs;
        this.totalPosts = totalPosts;
        this.prefix = prefix;
        this.storeDispatch = storeDispatch;
        this.createTranslatePostNonce = createTranslatePostNonce;
        this.APIcontroller = [];
        this.stopProcess = false;
        this.aiProviders = Object.keys(Provider()).filter(provider => provider.endsWith('_ai'));
        this.previousCompletedStrings = previousCompletedStrings;
        this.uniqueIds = [];
        this.modalClosed = false;

        const aiModal = selectServiceProvider(store.getState());
        this.service = typeof aiModal === 'string' ? aiModal.replace('_ai', '') : aiModal;
        this.Entries = selectTargetContent(store.getState(), postId);

        this.availableContentTypes=translateFieldNameSort(availableContentTypes(store.getState(), postId));

        updateDestoryHandler(
            () => {
                this.modalClosed = true;
                this.service && this.abortAllRequests('Modal Closed')
            }
        )
    }

    initTranslation = async () => {
        this.abortAllRequests('Restart Translation');
        const currentPostInfo = selectPendingPosts(store.getState());

        const runLoop = async (lang, index) => {
            if (index >= this.targetLangs.length) return;
            this.completedPostStatus = selectProgressStatus(store.getState());

            const beforeInit=await this.beforeInit(lang);

            if (currentPostInfo.includes(this.activePostId + '_' + lang) && beforeInit) {
                this.storeDispatch(updateTranslatePostInfo({ [this.activePostId + '_' + lang]: { status: 'running', messageClass: '' } }));

                this.activeTargetLangs = lang;
                this.uniqueIds = [];

                this.completedStrings = this.previousCompletedStrings;

                this.allStrings = this.allStringsFilter(this.activeTargetLangs);
                this.stringsBatches = this.calculateTokensInBatches(this.allStrings);
                this.totalStrings = Object.keys(this.allStrings).length + this.previousCompletedStrings;

                this.stopProcess = false;
                this.pendingStrings = false;
                this.errorCount = 0;
                this.requestIndex = 0;
                this.errorMessage = "";

                this.scrollDebounce = null;
                this.isProcessing = false;

                this.stringCount = 0;
                this.wordCount = 0;
                this.characterCount = 0;
                this.emptyBatches = 0;

                if(this.previousCompletedStrings > 0){
                    await new Promise(resolve => setTimeout(resolve, 100));
                    this.updateProgressBar({totalStrings: this.totalStrings, completedStrings: this.completedStrings, previousCompletedStrings: this.previousCompletedStrings, completedPostStatus: this.completedPostStatus});
                    await new Promise(resolve => setTimeout(resolve, 400));
                }

                await this.processChunkBatch();
            }

            if(!this.modalClosed){
                index++;
                await runLoop(this.targetLangs[index], index);
            }
        }

        if (this.targetLangs.length > 0) {
            await runLoop(this.targetLangs[0], 0);
        }
    };

    allStringsFilter = (activeLang) => {
        const strings = {};
        const translatedEntries = store.getState().translatedContent[this.activePostId];

        Object.keys(this.Entries).forEach((key, index) => {

            const value = this.Entries[key];
            let shortcode = value.trim().startsWith('[') && value.trim().endsWith(']');

            this.uniqueIds.push(key);

            if (shortcode && !translatedEntries[key]?.translation?.[this.service + '_ai']?.[activeLang]) {
                const id = key;

                if (value && id && value !== '' && id !== '') {
                    this.aiProviders.forEach(provider => {
                        this.targetLangs.forEach(targetLang => {
                            storeTranslateString(this.activePostId, key, targetLang, value, provider, targetLang, this.storeDispatch);
                        });
                    });
                }
            } else if (!translatedEntries[key]?.translation?.[this.service + '_ai']?.[activeLang]) {
                strings[index] = value;
            }
        });

        return strings;
    }

    calculateTokensInBatches = (strings) => {

        let selectedStringsBatch = {};
        let totalTokensBatch = 0;
        let selectedStringsBatches = [];
        const entries = Object.entries(strings);

        // Loop through each string to calculate tokens and organize them into batches
        for (let i = 0; i < entries.length; i++) {
            const [key, value] = entries[i];
            const length = value.length;
            const tokens = Math.ceil(length / 4);

            // Add the string to the current batch if it doesn't exceed the maximum tokens
            if (totalTokensBatch + tokens <= this.MAX_TOKENS) {
                selectedStringsBatch[key] = value;
                totalTokensBatch += tokens;
            } else {
                // If adding the string exceeds the maximum tokens, start a new batch
                selectedStringsBatches.push(selectedStringsBatch);
                selectedStringsBatch = { [key]: value };
                totalTokensBatch = tokens;
            }
        }

        // Add the last batch if it contains any strings
        if (Object.keys(selectedStringsBatch).length > 0) {
            selectedStringsBatches.push(selectedStringsBatch);
        }
        return selectedStringsBatches;
    };

    processChunkBatch = async () => {
        if (this.isProcessing) return;
        this.isProcessing = true;
        const stringsBatches = [...this.stringsBatches];

        if (stringsBatches.length === 0) {
            // this.translateStatusHandler(false);
            return;
        }

        const timeStart = new Date();

        try {
            await this.runRequest();
            this.processCompleteHandler(timeStart);
        } catch (error) {
            this.pendingStrings = true;

            this.processCompleteHandler(timeStart);
            if (error.name === 'AbortError') {
                const errorMessage = this.errorMessage && this.errorMessage.includes('You exceeded your current quota') ? __('You have exceeded you current plan limit. that\'s why the request is aborted.', 'wpml-translation-check') : error;
                console.log(errorMessage);
            } else {
                console.log('An error occurred during the AJAX processing:', error);
            }
        }


    };

    processCompleteHandler = async (startTime) => {
        const timeStart = startTime || new Date();
        const endTime = new Date();
        const duration=endTime - timeStart;
        const previousDuration=selectTranslatePostInfo(store.getState())?.[this.activePostId+'_'+this.activeTargetLangs]?.duration || 0;

        this.storeDispatch(updateTranslatePostInfo({[this.activePostId+'_'+this.activeTargetLangs]: { duration: previousDuration + duration}}));

        const pendingStrings = this.pendingStrings;

        this.isProcessing = false;
        
        if (pendingStrings) {
            this.updateTotalProgressStatus(this.totalStrings, this.completedStrings);
            this.pendingRequest();
            return;
        }

        this.updateTotalProgressStatus(this.totalStrings, this.completedStrings);

    }

    makeAjaxRequest = async (batch) => {

        const controller = new AbortController();
        this.APIcontroller.push(controller);

        const keys=Object.keys(batch);
        const strings=Object.assign({}, Object.values(batch));

        if(Object.values(batch).length === 0){
            return;
        }

        const response = await AITranslationRequest({
            controller: controller,
            Strings: strings,
            slug: this.service,
            source_language: this.sourceLang,
            target_language: this.activeTargetLangs,
        });

        if (response.success && response.data && response.data.translate_data) {
            if (Object.keys(response.data.translate_data).length > 0) {
                this.errorCount = 0;
                const firstKey = Object.keys(response.data.translate_data)[0];
                await this.updateData(firstKey, response.data.translate_data, keys);
            }
        } else {

            this.pendingStrings = true;

            if (response.success && !response.data.translate_data && response.data.text) {
                console.group('Automatic Translation Error');
                console.warn('Empty response');
                console.log(batch);
                console.groupEnd();
            }

            if (!response.success) {
                this.errorMessage = response.data;

                console.group('Automatic Translation Error');
                console.log('%c' + response.data, 'color: red; font-weight: bold; font-size: 1.2rem;');
                console.groupEnd();

                this.errorCount++;

                if (this.errorCount >= 5 || response.data.includes('service is not available.')) {
                    this.stopProcess = true;
                    this.abortAllRequests('Error Count Exceeded');
                }

                if (response.data.includes('You exceeded your current quota')) {
                    this.stopProcess = true;
                    this.abortAllRequests('Quota Exceeded');
                }
            }
        }
    };

    runRequest = async () => {
        const batch = this.stringsBatches.slice(0, this.CONCURRENCY_LIMIT);
        this.requestIndex = this.CONCURRENCY_LIMIT - 1;

        const processRequest = async (item) => {
            await this.makeAjaxRequest(item);

            if (this.requestIndex < (this.stringsBatches.length - 1) && !this.stopProcess) {
                this.requestIndex++;
                return await processRequest(this.stringsBatches[this.requestIndex]);
            } else {
                return;
            }
        }

        await Promise.all(batch.map(async (item) => {
            await processRequest(item);
        }));

    }

    abortAllRequests = (reason = 'Unknown Reason') => {
        this.stopProcess = true;
        this.pendingStrings = true;

        try {
            this.APIcontroller.forEach(controller => {
                if (controller.signal && !controller.signal.aborted) {
                    controller.abort(reason);
                }
            });
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Request aborted');
            } else {
                console.log('An error occurred during the AJAX processing:', error);
            }
            return;
        }

        this.scrollDebounce && clearTimeout(this.scrollDebounce);
        this.APIcontroller = [];
    };

    updateProgressBar = ({totalStrings, completedStrings, completedPostStatus}) => {

        const status = (completedStrings / totalStrings) * 100;

        let completedPercentage = (Math.round(status * 10) / 10).toFixed(1);
        completedPercentage = Math.min(completedPercentage, 100);

        let currentPostStatus = completedPercentage;
        currentPostStatus = Math.round(currentPostStatus);
        currentPostStatus = Math.min(currentPostStatus, 100);

        
        const progressBarCircular = document.querySelector(`.${this.prefix}-progress-bar-circular[data-id="${this.activePostId}_${this.activeTargetLangs}"]`);

        if (progressBarCircular) {
            progressBarCircular.querySelector(`.${this.prefix}-percentage`).innerHTML = currentPostStatus + '%';
            progressBarCircular.querySelector(`.${this.prefix}-progress`).style.strokeDasharray = currentPostStatus + ', 100';
        }

        let previousCompletedStatus=(this.previousCompletedStrings / totalStrings) * 100;
        previousCompletedStatus = (Math.round(previousCompletedStatus * 10) / 10).toFixed(1);
        previousCompletedStatus = Math.min(previousCompletedStatus, 100);

        let totalProgress = completedPostStatus + ((completedPercentage - previousCompletedStatus) / this.totalPosts);

        const totalProgressBar = document.querySelector(`.${this.prefix}-overall-progress .${this.prefix}-progress`);
        if (totalProgressBar) {

            totalProgress = totalProgress.toFixed(2);
            totalProgress = Math.min(totalProgress, 100);
            totalProgressBar.style.width = totalProgress + '%';
            totalProgressBar.innerHTML = totalProgress + '%';
        }
    };

    updateTotalProgressStatus = (totalStrings, completedStrings) => {
        const status = (completedStrings / totalStrings) * 100;

        let completedPercentage = (Math.round(status * 10) / 10).toFixed(1);
        completedPercentage = Math.min(completedPercentage, 100);

        let previousCompletedStatus=(this.previousCompletedStrings / totalStrings) * 100;
        previousCompletedStatus = (Math.round(previousCompletedStatus * 10) / 10).toFixed(1);
        previousCompletedStatus = Math.min(previousCompletedStatus, 100);

        
        const singlePost=(100 / this.totalPosts);
        this.storeDispatch(updateProgressStatus(((completedPercentage - previousCompletedStatus) / 100) * singlePost));
    }

    updateData = async (index, data, keys) => {

        if(this.stopProcess) return;

        const entry = data[index];
        const key=keys[index];

        if (entry && entry !== '') {
            const id = this.uniqueIds[key];
            const value = data[index];

            this.completedStrings = this.completedStrings;
            ++this.completedStrings;

            this.updateProgressBar({totalStrings: this.totalStrings, completedStrings: this.completedStrings, completedPostStatus: this.completedPostStatus});

            if (value && value !== '') {
                storeTranslateString(this.activePostId, id, this.activeTargetLangs, value, this.service + '_ai', this.activeTargetLangs, this.storeDispatch);
            }
        }

        delete data[index];

        if (Object.keys(data).length > 0) {
            const firstKey = Object.keys(data)[0];
            await this.updateData(firstKey, data, keys);
        }
    }

    pendingRequest = () => {
        const totalStrings = this.totalStrings;
        const completedStrings = this.completedStrings;
        const status = (completedStrings / totalStrings) * 100;

        let completedPercent = (Math.round(status * 10) / 10).toFixed(1);
        completedPercent = Math.min(completedPercent, 100);

        let notCompletedPercent = (100 - completedPercent);
        notCompletedPercent = (Math.round(notCompletedPercent * 10) / 10).toFixed(1);
        notCompletedPercent = Math.min(notCompletedPercent, 100);

        let errorMessage = '';
        let translateBtnMessage = '';
        const limitExceeded = this.errorMessage && this.errorMessage.includes('You exceeded your current quota');

        if (limitExceeded) {
            errorMessage = `<p class="${this.prefix}-ai-pending-request-heading">` + __('You’ve exceeded your current plan limit.', 'wpml-translation-check') + '</p> ' + __('To continue, please check your plan details and update your API key.', 'wpml-translation-check');
            translateBtnMessage = __('Click "Translate" after updating your API key to re-translate the remaining strings.', 'wpml-translation-check');
        } else {
            errorMessage = `<p class="${this.prefix}-ai-pending-request-heading">` + __('Oops! Something went wrong during translation', 'wpml-translation-check') + '</p>';
            translateBtnMessage = __('Click "Translate" to re-translate the remaining strings.', 'wpml-translation-check');
        }

        const message = `<div class="${this.prefix}-ai-pending-request">
                    <div>${errorMessage}</div>
                    <p>${__('To see more details, open your browser’s developer console.', 'wpml-translation-check')}</p>

                <p>✅ ${sprintf(__('You’ve translated %s of the strings.', 'wpml-translation-check'), completedPercent + '%')}</p>
                <p>❌ ${sprintf(__('%s of the strings are still not translated.', 'wpml-translation-check'), notCompletedPercent + '%')}</p>

                <p><strong>${__('Next Steps:', 'wpml-translation-check')}</strong></p>

                <p>${translateBtnMessage}</p>
                <p><strong>${__('OR', 'wpml-translation-check')}</strong></p>
                <p>${__('Click "Continue" to proceed without translating the rest of the strings.', 'wpml-translation-check')}</p>
                </div>`;


        this.storeDispatch(updateTranslatePostInfo({ [this.activePostId + '_' + this.activeTargetLangs]: { status: 'error', messageClass: 'error', errorMessage: __('Translation failed.', 'wpml-translation-check'), errorHtml: message, aiError: true, nonce: this.createTranslatePostNonce, completedStrings, totalPosts: this.totalPosts} }));
    }

    static translateComplete = async({postId, targetLang, storeDispatch, prefix, updateDestoryHandler, nonce, closeErrorModal, totalPosts, completedStrings}) => {
        const postContent=store.getState().parentPostsInfo[postId];

        if(!postContent) return;

        const {originalContent: {title, content}, editorType, sourceLanguage} = postContent;

        const source = { title: title, content: JSON.parse(JSON.stringify(content)) };


        const aiService=new AIService({postId, targetLangs:[targetLang], sourceLang: sourceLanguage, totalPosts, storeDispatch, createTranslatePostNonce: nonce, prefix, updateDestoryHandler, totalPosts, previousCompletedStrings: completedStrings});

        const allStrings=aiService.allStringsFilter(targetLang);

        const completedPostStatus = selectProgressStatus(store.getState());

        aiService.updateProgressBar({totalStrings: Object.keys(allStrings).length + completedStrings, completedStrings: Object.keys(allStrings).length + completedStrings, completedPostStatus: completedPostStatus});
        aiService.updateTotalProgressStatus(Object.keys(allStrings).length + completedStrings, Object.keys(allStrings).length + completedStrings);
        
        closeErrorModal();

        await new Promise(resolve => setTimeout(resolve, 400));
    }

    static translateAgain =async ({postId, targetLang, storeDispatch, prefix, updateDestoryHandler, nonce, closeErrorModal, completedStrings, totalPosts}) => {

        const postContent=store.getState().parentPostsInfo[postId];

        if(!postContent) return;

        const {originalContent: {title, content}, languages, editorType, sourceLanguage} = postContent;

        const source = { title: title, content: JSON.parse(JSON.stringify(content)) };

        closeErrorModal();  

        await new Promise(resolve => setTimeout(resolve, 500));

        const aiService=new AIService({postId, targetLangs:[targetLang], sourceLang: sourceLanguage, totalPosts, storeDispatch, createTranslatePostNonce: nonce, prefix, updateDestoryHandler, totalPosts, previousCompletedStrings: completedStrings});
        
        await aiService.initTranslation();
    }

    beforeInit = async (targetLang) => {
        return true;
    }
}

export default AIService;
