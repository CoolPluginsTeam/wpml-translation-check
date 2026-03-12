import App from './App';
import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';
import './index.css';
import { store } from './redux-store/store';
import { Provider } from 'react-redux';
import { __ } from '@wordpress/i18n';
import LocalAITranslate from './components/translate-provider/local-ai/local-ai-translate';

(() => {
    const BulkTranslate = (props) => {
        const [modalVisible, setModalVisible] = useState(false);
        const [postIds, setPostIds] = useState([]);
        const prefix = props.prefix;
        const wrapper = document.getElementById(`${prefix}-wrapper`);
        let localAiCheckInProgres = false;

        const handleModalVisibility = (e) => {
            e.preventDefault();

            setModalVisible(prev => !prev);
            destroyGoogleWidget();

        }

        // 1️⃣ Clear old cached data on page load
        const clearOldTranslatorCacheOnLoad = () => {
            const loadKey = 'AUTOMLP_WPML_LOCAL_AI_PAGE_LOADED';

            if (sessionStorage.getItem(loadKey)) {
                return;
            }

            localStorage.removeItem('AUTOMLP_WPML_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES');
            sessionStorage.setItem(loadKey, '1');
        };

        // 2️⃣ Language pack availability check (gesture-based)
        const checkLanguagePackAvailability = async () => {
            const languagesObj = { ...automlp_wpml_bulk_translate_object.languageObject };
            const supportedLanguages = LocalAITranslate.supportedLanguages || [];

            delete languagesObj.en;

            let savedLanguages = [];
            try {
                savedLanguages = JSON.parse(
                    localStorage.getItem('AUTOMLP_WPML_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES')
                ) || [];
            } catch {
                savedLanguages = [];
            }

            savedLanguages.forEach(lang => delete languagesObj[lang]);

            if (!Object.keys(languagesObj).length) return;


            const processNextLanguage = async () => {
                if (localAiCheckInProgres || Object.keys(languagesObj).length === 0) return;

                localAiCheckInProgres = true;
                const targetLang = Object.keys(languagesObj)[0];

                if(supportedLanguages.includes(targetLang) || supportedLanguages.includes(targetLang.split('-')[0])){
                    try {
                        const status = await LocalAITranslate.languagePairAvality('en', targetLang);
    
                        if (['available', 'readily'].includes(status)) {
                            delete languagesObj[targetLang] ;
                            savedLanguages.push(targetLang);
                            localStorage.setItem(
                                'AUTOMLP_WPML_AVAILABLE_LOCAL_AI_TRANSLATOR_LANGUAGES',
                                JSON.stringify(savedLanguages)
                            );
                        }
                    } catch (err) {
                        console.error('Language availability check failed:', targetLang, err);
                    }
                }else{
                    delete languagesObj[targetLang];
                }

                localAiCheckInProgres = false;

                if (Object.keys(languagesObj).length === 0) {
                    document.removeEventListener('mousemove', onMouseMove);

                    const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
                    doActionsBtn.forEach(btn => {
                        btn.removeEventListener('mousemove', checkLanguagePackAvailability);
                        btn.removeEventListener('mouseleave', checkLanguagePackAvailability);
                        btn.removeEventListener('mouseenter', checkLanguagePackAvailability);
                    });

                }
            };

            const onMouseMove = () => {
                processNextLanguage();
            };

            document.addEventListener('mousemove', onMouseMove);
        };

        const bulkTranslationHandler = (e) => {
            e.preventDefault();

            let checkboxClass = 'table.widefat input[name="post[]"]:checked';

            if (automlp_wpml_bulk_translate_object.taxonomy_page && '' !== automlp_wpml_bulk_translate_object.taxonomy_page) {
                checkboxClass = 'table.widefat input[name="delete_tags[]"]:checked';
            }

            const selectedPostIds = document.querySelectorAll(checkboxClass);
            const postIds = Array.from(selectedPostIds).map(postId => postId.value);

            checkLanguagePackAvailability();

            setPostIds(postIds);
            handleModalVisibility(e);
        }

        const destroyGoogleWidget = () => {
            const googleWidget = document.querySelector('.skiptranslate iframe[id=":1.container"]');
            document.body.classList.remove(prefix + '-google-translate');

            if (googleWidget) {
                const closeButton = googleWidget.contentDocument.querySelector('a[id=":1.close"][title="Close"] img');
                if (closeButton) {
                    closeButton.click();
                }
            }
        }

        useEffect(() => {
            const doActionsBtn = document.querySelectorAll(`.${prefix}-btn`);
            
            if (doActionsBtn) {
                clearOldTranslatorCacheOnLoad();
                doActionsBtn.forEach(btn => {
                    btn.addEventListener('click', bulkTranslationHandler);
                    btn.addEventListener('mousemove', checkLanguagePackAvailability);
                    btn.addEventListener('mouseleave', checkLanguagePackAvailability);
                    btn.addEventListener('mouseenter', checkLanguagePackAvailability);
                });
            }
        }, []);

        useEffect(() => {
            const mainWrapper = document.getElementById(`${prefix}-wrapper`);
            if (mainWrapper) {
                mainWrapper.classList.toggle(`${prefix}-active`, modalVisible);
            }
        }, [modalVisible]);

        return (
            modalVisible ? (
                <App onDestory={handleModalVisibility} prefix={prefix} postIds={postIds} />
            ) : null
        );
    }

    


    window.addEventListener('load', async () => {
        const prefix = 'automlp-wpml-bulk-translate';

        await new Promise(resolve => setTimeout(resolve, 500));

        ReactDOM.createRoot(document.getElementById(`${prefix}-wrapper`)).render(
            <Provider store={store}>
                <BulkTranslate prefix={prefix} />
            </Provider>
        );
    });
})();
