import ChromeAiTranslator from "./local-ai-translate";
import { updateProgressStatus, updateTranslatePostInfo, unsetPendingPost } from "../../../redux-store/features/actions";
import { selectProgressStatus, selectTargetContent, selectTranslatePostInfo, availableContentTypes } from "../../../redux-store/features/selectors";
import { store } from "../../../redux-store/store";
import storeTranslateString from "../../store-translate-strings";
import { __ } from '@wordpress/i18n';
import {translateFieldNameSort} from "../../../helper/index";

// Define a class for LocalAiTranslate
class LocalAiTranslate {
    constructor({ sourceLang = 'en', targetLangs = false, updateContent, totalPosts, storeDispatch, postId, prefix, updateDestoryHandler }) {
        this.textContentObject = selectTargetContent(store.getState(), postId);

        this.totalTranslatedLength = Object.keys(this.textContentObject).length;
        this.sourceLang = sourceLang;
        this.targetLangs = targetLangs;
        this.localAiTranslator = null;
        this.textContentObjectKeys = Object.keys(this.textContentObject);
        this.translateKeysLength = this.textContentObjectKeys.length;
        this.updateContent = updateContent;
        this.pendingPosts = totalPosts;
        this.totalPosts=Object.keys(selectTranslatePostInfo(store.getState())).length;
        this.storeDispatch = storeDispatch;
        this.completedPostStatus = 0;
        this.postId = postId;
        this.activeTargetLangs = '';
        this.prefix = prefix;
        this.serviceProvider = store.getState().serviceProvider;
        this.availableContentTypes=translateFieldNameSort(availableContentTypes(store.getState(), postId));
        updateDestoryHandler(() => {
            this.destroy();
        });
        this.stopTranslation = false;
    }

    destroy = () => {
        this.stopTranslation = true;
        if (this.localAiTranslator && this.localAiTranslator.hasOwnProperty('stopTranslation')) {
            this.localAiTranslator.stopTranslation();
        }
    }

    // Function to create Local AI Translator
    async createLocalAiTranslator(targetLang, index) {
        this.completedTranslateIndex = 0;
        this.localAiTranslator = null;

        if (this.stopTranslation) return;

        const languageObject = automl_wpml_bulk_translate_object.languageObject;
        this.completedPostStatus = selectProgressStatus(store.getState());

        this.activeTargetLangs = targetLang;
        this.localAiTranslator = await ChromeAiTranslator.Object({
            sourceLanguage: this.sourceLang,
            targetLanguage: targetLang,
            sourceLanguageLabel: languageObject[this.sourceLang].name,
            targetLanguageLabel: languageObject[targetLang].name,
            onAfterTranslate: this.onAfterTranslate,
            onComplete: this.onComplete,
            onLanguageError: this.onLanguageError,
        });

        if (this.localAiTranslator.hasOwnProperty('init')) {

            this.storeDispatch(updateTranslatePostInfo({ [this.postId + '_' + targetLang]: { status: 'running', messageClass: '' } }));
            await this.translateContent(0);

            if (!this.stopTranslation) {
                await this.updateContent(targetLang);
            }
        }

        if (index < this.targetLangs.length - 1 && !this.stopTranslation) {
            await this.createLocalAiTranslator(this.targetLangs[index + 1], index + 1);
        }
    }

    onLanguageError = (data) => {
        let html = false;
        if (data.html) {
            html = data.html[0]?.outerHTML;
        }

        this.storeDispatch(unsetPendingPost(this.postId + '_' + this.activeTargetLangs));
        this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
        this.storeDispatch(updateTranslatePostInfo({ [this.postId + '_' + this.activeTargetLangs]: { status: 'error', messageClass: 'error', errorMessage: data.message, errorHtml: html } }));
    }

    onAfterTranslate = (key, value) => {
        if (this.stopTranslation) return;
        storeTranslateString(this.postId, key, this.activeTargetLangs, value, this.serviceProvider, this.activeTargetLangs, this.storeDispatch);

        this.completedTranslateIndex++;
        let completedPercentage = this.completedTranslateIndex / this.totalTranslatedLength * 100;
        completedPercentage = completedPercentage.toFixed(2);
        completedPercentage = Math.min(completedPercentage, 100);

        let completedPostStatus = completedPercentage;
        completedPostStatus = Math.round(completedPostStatus);
        completedPostStatus = Math.min(completedPostStatus, 100);

        const progressBarCircular = document.querySelector(`.${this.prefix}-progress-bar-circular[data-id="${this.postId}_${this.activeTargetLangs}"]`);

        if (progressBarCircular) {
            progressBarCircular.querySelector(`.${this.prefix}-percentage`).innerHTML = completedPostStatus + '%';
            progressBarCircular.querySelector(`.${this.prefix}-progress`).style.strokeDasharray = completedPostStatus + ', 100';

        }

        let totalProgress = this.completedPostStatus + (completedPercentage / this.totalPosts);
        const totalProgressBar = document.querySelector(`.${this.prefix}-overall-progress .${this.prefix}-progress`);
        if (totalProgressBar) {

            totalProgress = totalProgress.toFixed(2);
            totalProgress = Math.min(totalProgress, 100);
            totalProgressBar.style.width = totalProgress + '%';
            totalProgressBar.innerHTML = totalProgress + '%';
        }
    }

    onComplete = () => {
        if (this.stopTranslation) return;

        if (this.completedTranslateIndex === this.totalTranslatedLength) {
            const endTime = new Date();
            const duration = endTime - this.startTime;

            const previousDuration = selectTranslatePostInfo(store.getState())?.[this.postId + '_' + this.activeTargetLangs]?.duration || 0;

            this.storeDispatch(updateTranslatePostInfo({ [this.postId + '_' + this.activeTargetLangs]: { duration: previousDuration + duration } }));

            this.storeDispatch(updateProgressStatus((100 / this.totalPosts)));
        }
    }

    // Function to translate content
    async translateContent(index) {
        const textObject = JSON.parse(JSON.stringify(this.textContentObject));

        if (Object.keys(textObject).length > 0 && !this.stopTranslation) {
            this.startTime = new Date();
            await this.localAiTranslator.init(textObject);
            await this.localAiTranslator.startTranslation();
        }
    }

    // Function to initialize translation if conditions are met
    async initTranslation() {
        if (this.textContentObject && Object.keys(this.textContentObject).length > 0 && this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
            await this.createLocalAiTranslator(this.targetLangs[0], 0);
        } else if (this.targetLangs && this.targetLangs.length > 0 && !this.stopTranslation) {
            this.targetLangs.forEach(lang => {
                this.storeDispatch(unsetPendingPost(this.postId + '_' + lang));
                this.storeDispatch(updateProgressStatus(100 / this.totalPosts));
                this.storeDispatch(updateTranslatePostInfo({ [this.postId + '_' + lang]: { status: 'error', messageClass: 'error', errorMessage: __('No content to translate', 'wpml-translation-check'), errorHtml: false } }));
            });
        }
    }
}

export default LocalAiTranslate;
