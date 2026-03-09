import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import StatusModal from './status-modal';
import { useDispatch, useSelector } from 'react-redux';
import { resetStore, updateServiceProvider, updateCountInfo } from './redux-store/features/actions';
import { selectCountInfo } from './redux-store/features/selectors';
import ChromeAiTranslator from './components/translate-provider/local-ai/local-ai-translate';
import ErrorModalBox from './components/error-modal-box';
import SettingModal from './setting-modal';
import DOMPurify from 'dompurify';
import Notice from './components/notice';
import RenderLanguage from './render-langauge';

const App = ({ onDestory, prefix, postIds }) => {
    const dispatch = useDispatch();
    const { languageObject = {}, selected_language_object = {} } = automl_wpml_bulk_translate_object || {};
    const wizardSelectedCode = Object.keys(selected_language_object)[0] || '';
    const wizardLanguagesUrl = (automl_wpml_bulk_translate_object?.admin_url || '').replace(/\/?$/, '') + '/admin.php?page=automl_ai_wizard&step=languages';
    const emptyPostIdsErrorMessage = sprintf(__('Please select at least one %s for translation.', 'wpml-translation-check'), automl_wpml_bulk_translate_object.post_label);
    const [selectedLanguages, setSelectedLanguages] = useState([]);
    const isStringTranslationPage = false;
    const [errorMessage, setErrorMessage] = useState(postIds.length === 0 && !isStringTranslationPage ? emptyPostIdsErrorMessage : '');
    const [settingModalVisibility, setSettingModalVisibility] = useState(false);
    const [statusModalVisibility, setStatusModalVisibility] = useState(false);
    const translatePostsCount = useSelector(selectCountInfo).totalPosts;
    const [isLoading, setIsLoading] = useState(true);
    const [errorModal, setErrorModal] = useState(false);
    const [localAiModalError, setLocalAiModalError] = useState(false);
    const targetLanguages = JSON.parse(JSON.stringify(languageObject));
    delete targetLanguages[automl_wpml_bulk_translate_object.default_language_slug];

    const destroyApp = (e) => {
        setStatusModalVisibility(false);
        setSettingModalVisibility(false);
        onDestory(e);
    };

    useEffect(() => {
        const checkStatus = async () => {
            const status = await ChromeAiTranslator.languageSupportedStatus('en', 'hi', 'English', 'Hindi');
            if (status.type === 'browser-not-supported' || status.type === 'translation-api-not-available' || status.type === 'browser-not-supported') {
                setLocalAiModalError(__(status.html[0].outerHTML, 'wpml-translation-check'));
            }
            setIsLoading(false);
        };
        checkStatus();
    }, [statusModalVisibility]);

    useEffect(() => {
        if (!statusModalVisibility && !settingModalVisibility) {
            dispatch(resetStore());
        }
    }, [statusModalVisibility, settingModalVisibility, dispatch]);

    const settingModalVisibilityHandler = async () => {
        if (selectedLanguages.length === 0 && !settingModalVisibility) {
            setErrorMessage(__('Please select at least one language', 'wpml-translation-check'));
            setErrorModal(true);
            return;
        }

        if(false === settingModalVisibility){
            dispatch(updateCountInfo({startTime: new Date().getTime()}));
        }

        setSettingModalVisibility((prev) => !prev);
    };

    const handleLanguageChange = (e) => {
        const { value } = e.target;
        const checked = e.target.checked;
        if (checked) {
            setSelectedLanguages([...selectedLanguages, value]);
        } else {
            setSelectedLanguages(selectedLanguages.filter((language) => language !== value));
        }
    };

    const setSelectedLanguagesHandler = (languages) => {
        setSelectedLanguages(languages);
    };

    const closeErrorModal = (e) => {
        setErrorModal(false);
    };

    const updateProviderHandler = (services) => {
        dispatch(updateServiceProvider(services));
        setSettingModalVisibility(false);
        setStatusModalVisibility(true);
        setIsLoading(true);
    };

    const containerCls = () => {
        let cls = [];
        if (statusModalVisibility) {
            cls.push(`${prefix}-status-modal-active`);
        }
        if (settingModalVisibility) {
            cls.push(`${prefix}-setting-modal-active`);
        }
        if (!translatePostsCount && !settingModalVisibility && statusModalVisibility) {
            cls.push(`${prefix}-empty-posts`);
        }
        return cls.join(' ');
    };

    const SelectLanguageNotice = () => {
        const notices = [];
        const noticeLength = notices.length;
        if (notices.length > 0) {
            return notices.map((notice, index) => <Notice className={notice.className} key={index} lastNotice={index === noticeLength - 1}>{notice.message}</Notice>);
        }
        return;
    };

    return (
        <div id={`${prefix}-container`} className={containerCls()}>
            {settingModalVisibility && (
                <SettingModal
                    prefix={prefix}
                    onDestory={destroyApp}
                    onCloseHandler={settingModalVisibilityHandler}
                    updateProviderHandler={updateProviderHandler}
                    localAiModalError={localAiModalError}
                />
            )}

            {statusModalVisibility && !settingModalVisibility && (isLoading ? (
                <div className={`${prefix}-skeleton-loader`}></div>
            ) : (
                <StatusModal
                    postIds={postIds}
                    selectedLanguages={selectedLanguages}
                    prefix={prefix}
                    onDestory={destroyApp}
                />
            ))}
            {!statusModalVisibility && !settingModalVisibility && (
                <div className={`${prefix}-language-container`}>
                    <div className={`${prefix}-header`}>
                        <div className={`${prefix}-modal-header-inner`}>
                            <span className={`${prefix}-step-label`}>{__('STEP 1 OF 3', 'wpml-translation-check')}</span>
                            <h2>{__('Select Languages', 'wpml-translation-check')}</h2>
                        </div>
                        <button
                            type="button"
                            className={`${prefix}-modal-close`}
                            onClick={destroyApp}
                            title={__('Close', 'wpml-translation-check')}
                            aria-label={__('Close', 'wpml-translation-check')}
                        >
                            &times;
                        </button>
                    </div>
                    {errorMessage && errorMessage !== '' ? (errorModal ? <ErrorModalBox message={errorMessage} onClose={closeErrorModal} /> : <div className={`${prefix}-error-message`} dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(errorMessage) }} />) : (
                        <>
                            <div className={`${prefix}-body`}>
                                <SelectLanguageNotice />
                                {wizardSelectedCode ?
                                    <div className={`${prefix}-languages`}>
                                       <div className={`${prefix}-languages-enabled-list`}>
                                        {/* <RenderLanguage
                                            language={wizardSelectedCode}
                                            selectedLanguages={selectedLanguages}
                                            setSelectedLanguages={setSelectedLanguagesHandler}
                                            prefix={prefix}
                                            languageObject={languageObject}
                                            wizardSelectedCode={wizardSelectedCode}
                                        />
                                        </div>
                                        <div className={`${prefix}-languages-disabled-lists`}>
                                        <p>{__('Multiple language translation available in Pro.', 'wpml-translation-check')}
                                        &nbsp;
                                        <a href='#' title={__('Buy Pro Version to Unlock All Languages', 'wpml-translation-check')} className={`${prefix}-buy-pro-version-link`}>{__('Upgrade now', 'wpml-translation-check')}</a>
                                        </p>
                                        <div> */}
                                            {Object.keys(targetLanguages).map((language) => (
                                                // language === wizardSelectedCode ? <></> : 
                                                <RenderLanguage
                                                    key={language}
                                                    language={language}
                                                    selectedLanguages={selectedLanguages}
                                                    setSelectedLanguages={setSelectedLanguagesHandler}
                                                    prefix={prefix}
                                                    languageObject={languageObject}
                                                    wizardSelectedCode={wizardSelectedCode}
                                                    />
                                                ))
                                            }
                                        </div>
                                        {/* </div> */}
                                    </div>: (
                                    <div className={`${prefix}-wizard-language-notice`} style={{ padding: '12px 24px', marginTop: 8, background: '#00000008', borderRadius: 4 }}>
                                        <p style={{ margin: '0 0 8px', fontSize: 14 }}>{__('Please select a translation language first.', 'wpml-translation-check')}</p>
                                        <a href={wizardLanguagesUrl} style={{ fontSize: 14 }}>{__('Select language in Setup Wizard (Languages step)', 'wpml-translation-check')}</a>
                                    </div>
                                )}
                            </div>
                            <div className={`${prefix}-footer`}>
                                <button className={`${prefix}-footer-button button button-primary`} onClick={destroyApp} title={!postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : ''}>{__('Cancel', 'wpml-translation-check')}</button>
                                <button
                                    className={`${prefix}-footer-button button button-primary`}
                                    onClick={settingModalVisibilityHandler}
                                    disabled={(!postIds.length && !isStringTranslationPage) || !selectedLanguages.length}
                                    title={!postIds.length && !isStringTranslationPage ? emptyPostIdsErrorMessage : !selectedLanguages.length ? __('Please select at least one language', 'wpml-translation-check') : ''}
                                >
                                    {__('Next', 'wpml-translation-check')} <span className={`${prefix}-next-arrow`}>&#8594;</span>
                                </button>
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
};

export default App;