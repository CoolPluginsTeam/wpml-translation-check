import React, { useEffect, useState } from 'react';
import { bulkTranslateEntries, initBulkTranslate } from '../bulk-translate';
import { useSelector, useDispatch } from 'react-redux';
import { selectTranslatePostInfo, selectProgressStatus, selectCountInfo, selectPendingPosts, selectServiceProvider, selectErrorPostsInfo, selectTargetLanguages } from '../redux-store/features/selectors';
import { __, sprintf } from '@wordpress/i18n';
import ErrorModalBox from '../components/error-modal-box';
import AIService from '../components/translate-provider/ai-services';
import { store } from '../redux-store/store';
import DOMPurify from 'dompurify';
import LoopCallback from '../components/loop-callback';
import { updatePendingPosts, updateCountInfo, updateTranslatePostInfo, unsetPendingPost } from '../redux-store/features/actions';

const StatusModal = ({ postIds, selectedLanguages, prefix, onDestory }) => {

    const storeDispatch = useDispatch();
    const [isLoading, setIsLoading] = useState(true);
    const [errorModal, setErrorModal] = useState(false);
    const [errorModalData, setErrorModalData] = useState(false);
    const translatePostInfo = useSelector(selectTranslatePostInfo);
    const [destroyHandlers, setDestroyHandlers] = useState([]);
    const errorPostsInfo = useSelector(selectErrorPostsInfo);
    const pendingPosts = useSelector(selectPendingPosts);
    const serviceProvider = useSelector(selectServiceProvider);
    const [progressBarVisibility, setProgressBarVisibility] = useState(true);
    const [bulkStatus, setBulkStatus] = useState('status');
    const countInfo = useSelector(selectCountInfo);
    let [emptyPostMessage, setEmptyPostMessage] = useState(sprintf(__('Translations already exist for all selected %s in the chosen languages. There are no new %s to translate.', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label, automlp_wpml_bulk_translate_object.post_label));
    let progressStatus = useSelector(selectProgressStatus);
    progressStatus = progressStatus.toFixed(1);
    progressStatus = Math.min(progressStatus, 100);

    useEffect(() => {
        let postFound = false;
        const postIdExist = new Array();

        const translatePosts = async (pendingPostsInfo) => {

            const processPostIds = async (postId, index) => {

                if(!pendingPostsInfo[postId]?.languages || pendingPostsInfo[postId]?.languages?.length < 1) {
                    return;
                }

                const response = await bulkTranslateEntries({ ids: [postId], langs: pendingPostsInfo[postId].languages, storeDispatch });

                if (!response.success && response.message && !postFound) {
                    pendingPostsInfo[postId].languages.forEach(lang => {
                        storeDispatch(unsetPendingPost(postId + '_' + lang));
                        storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: { status: 'error', messageClass: 'error', errorHtml: response.message } }));
                    });
                    setEmptyPostMessage(response.message);

                    if(index === Object.keys(pendingPostsInfo).length - 1 && progressStatus <= 0) {
                        setProgressBarVisibility(false);
                    }
                    return;
                }

                postFound = true;
                await initBulkTranslate(response.postKeys, response.nonce, storeDispatch, prefix, updateDestoryHandler);
            }

            await LoopCallback({ callback: processPostIds, loop: Object.keys(pendingPostsInfo), index: 0 });
        }

        const initBulkTranslation = async () => {
            const sendRequest = async () => {
                const response = await fetch(automlp_wpml_bulk_translate_object.bulkTranslateRouteUrl + '/automlp_wpmlp/pending-posts-ids', {
                    method: 'POST',
                    body: new URLSearchParams({ ids: JSON.stringify(postIds), lang: JSON.stringify(selectedLanguages), privateKey: automlp_wpml_bulk_translate_object.pendingPostsIdsKey }),
                    headers: {
                        'X-WP-Nonce': automlp_wpml_bulk_translate_object.nonce,
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'Accept': 'application/json'
                    }
                });

                const responseData = await response.json();

                if (responseData && responseData.success && responseData.data) {

                    Object.keys(responseData.data).forEach(postId => {
                        const langauges = responseData.data[postId].languages;
                        const parentPostTitle = responseData.data[postId].title;

                        if (langauges && langauges.length > 0) {
                            langauges.forEach(lang => {
                                const flagUrl = automlp_wpml_bulk_translate_object.languageObject[lang].flag;
                                const languageName = automlp_wpml_bulk_translate_object.languageObject[lang].name;

                                let firstPostLanguage = false;
                                if (!postIdExist.includes(postId)) {
                                    postIdExist.push(postId);
                                    firstPostLanguage = true;
                                }

                                storeDispatch(updatePendingPosts([postId + '_' + lang]));
                                storeDispatch(updateTranslatePostInfo({ [postId + '_' + lang]: { parentPostId: postId, targetPostId: null, targetLanguage: lang, postLink: null, status: 'in-queue', parentPostTitle, firstPostLanguage, flagUrl, languageName, messageClass: 'warning' } }));
                            });

                            storeDispatch(updateCountInfo({ totalPosts: store.getState().countInfo.totalPosts + langauges.length }));
                        }

                    });

                    setIsLoading(false);
                    await translatePosts(responseData.data);

                    storeDispatch(updateCountInfo({ endTime: new Date().getTime() }));
                } else {
                    setEmptyPostMessage(response.message);
                }
            }
            await sendRequest();
        }

        initBulkTranslation();
    }, []);

    const handleErrorModal = (data) => {
        setErrorModalData(data);
        setErrorModal(true);
    }

    const closeErrorModal = (e) => {
        setErrorModal(false);
        setErrorModalData(false);
    }

    const updateDestoryHandler = (callback) => {
        setDestroyHandlers(prev => [...prev, callback]);
    }

    const onModalClose = (e) => {
        destroyHandlers.forEach(callback => typeof callback === 'function' && callback());
        onDestory(e);

        if (countInfo.postsTranslated > 0 && !pendingPosts.length && !progressBarVisibility) {
            const reloadUrl = getTranslatedPostLink();
            window.location.href = reloadUrl;
        }
    }

    useEffect(() => {
        if (countInfo.totalPosts < 1 && !isLoading && bulkStatus !== 'status') {
            updateBulkStatus('status');
            return;
        }

        if (translatePostInfo && Object.keys(translatePostInfo).length > 0) {
            if (pendingPosts.length < 1) {
                updateBulkStatus('completed');
                return;
            }

            let error = false;
            let running = false;

            const runLoop = (items, index) => {
                const status = translatePostInfo[items[index]].status;

                if (status === 'running' || status === 'in-progress' || status === 'pending' || status === 'in-queue') {
                    running = true;
                    bulkStatus !== 'running' && updateBulkStatus('running');
                    return;
                }

                if (status === 'error') {
                    error = true;
                }

                index++;
                if (index < items.length) {
                    runLoop(items, index);
                }
            }

            runLoop(Object.keys(translatePostInfo), 0);

            if (running) return;

            if (error) {
                updateBulkStatus('pending');
            } else {
                updateBulkStatus('pending');
            }
        }
    }, [translatePostInfo]);

    const updateBulkStatus = (status) => {
        setBulkStatus(status);
    }

    const getBulkStatus = () => {
        switch (bulkStatus) {
            case 'in-queue':
                return __('In Queue', 'wpml-translation-check');
            case 'running':
                return __('In Progress', 'wpml-translation-check');
            case 'pending':
                return __('Pending', 'wpml-translation-check');
            case 'completed':
                return __('Completed', 'wpml-translation-check');
            default:
                return __('Status', 'wpml-translation-check');
        }
    }

    useEffect(() => {
        if (progressStatus >= 100 && pendingPosts.length < 1) {
            if (countInfo.postsTranslated < 1) {
                setProgressBarVisibility(false);
                return;
            }

            if (countInfo.stringsTranslated > 0) {
                setTimeout(() => {
                    setProgressBarVisibility(false);
                }, 2000);
            }
        }
    }, [pendingPosts]);

    const AIErrorBtnHandler = (e) => {
        const type = {
            'translateAgain': AIService.translateAgain,
            'continue': AIService.translateComplete
        }

        const btnType = e.target.dataset.status;
        type[btnType]({ postId: errorModalData.parentPostId, targetLang: errorModalData.targetLanguage, storeDispatch, prefix, updateDestoryHandler, nonce: errorModalData.nonce, closeErrorModal, completedStrings: errorModalData.completedStrings, totalPosts: errorModalData.totalPosts });
    }

    const getTranslatedPostLink = () => {
        const translatedLanguagesArr = Object.values(translatePostInfo).filter(post => post.status === 'completed' && post.targetLanguage);
        const translatedLangs = translatedLanguagesArr.map(post => post.targetLanguage).filter((lang, index, self) => self.indexOf(lang) === index);

        if (translatedLangs.length === 1) {
            const translatedLang = translatedLangs[0];
            // Get current query params
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);

            // Set or update the required params
            params.set('lang', translatedLang);
            params.set('orderby', 'date');
            params.set('order', 'desc');

            const newQuery = Object.fromEntries(params.entries());

            return window.location.href.split('?')[0] + '?' + new URLSearchParams(newQuery).toString();
        } else {
            return window.location.href;
        }

    }

    const getServiceProviderLabel = () => {
        switch (serviceProvider) {
            case 'localAiTranslator':
                return 'Chrome AI Translator';
            case 'openai_ai':
                return 'OpenAI';
            case 'google_ai':
                return 'Gemini';
            default:
                return 'AI Translator';
        }
    }

    const allPostStatus = (postId) => {
        const targetLangsArr = selectTargetLanguages(store.getState(), postId);
        let allPostStatus = true;

        if (!targetLangsArr || !targetLangsArr.length) {
            return true;
        }

        for (let i = 0; i < targetLangsArr.length; i++) {
            if (!translatePostInfo[postId + '_' + targetLangsArr[i]] || ['pending', 'in-progress', 'running', 'in-queue'].includes(translatePostInfo[postId + '_' + targetLangsArr[i]].status)) {
                allPostStatus = false;
                break;
            }
        };

        return allPostStatus;
    };

    const getPostStatus = (type) => {
        switch (type) {
            case 'pending':
                return __('Pending', 'wpml-translation-check');
            case 'completed':
                return __('Completed', 'wpml-translation-check');
            case 'in-queue':
                return __('In Queue', 'wpml-translation-check');
            default:
                return '';
        }
    };

    return (
        errorModal ? <ErrorModalBox message={errorModalData.errorHtml} onClose={closeErrorModal} Title={__('Bulk Translation Error', 'wpml-translation-check')} prefix={prefix} >
            {errorModalData.aiError && <div className={`${prefix}-ai-error-buttons`}>
                <button className={`${prefix}-ai-error-button button`} data-status="translateAgain" onClick={AIErrorBtnHandler}>{__('Translate', 'wpml-translation-check')}</button>
                <button className={`${prefix}-ai-error-button button`} data-status="continue" onClick={AIErrorBtnHandler}>{__('Continue', 'wpml-translation-check')}</button>
            </div>}
        </ErrorModalBox> :
            <div id={`${prefix}-status-modal-container`}>
                <div className={`${prefix}-header`}>
                    <div className={`${prefix}-modal-header-inner`}>
                        <span className={`${prefix}-step-label`}>
                            {__("STEP 3 OF 3", "automlp-ai-translation-for-wpml")}
                        </span>
                        <h2 className={`${prefix}-bulk-status-heading ${bulkStatus}`}>
                            {sprintf(
                                __("Bulk Translation %s", "automlp-ai-translation-for-wpml"),
                                getBulkStatus(),
                            )}
                            {bulkStatus === "running" && (
                                <span className={`${prefix}-bulk-status-running`}></span>
                            )}
                        </h2>
                        {bulkStatus === "completed" &&
                            countInfo.errorPosts < 1 &&
                            !(translatePostInfo && Object.values(translatePostInfo).some((info) => info?.status === "error")) &&
                            countInfo.stringsTranslated > 0 && (
                                <p className={`${prefix}-modal-desc`}>{__("Your content has been translated successfully.", 'wpml-translation-check')}</p>
                            )}
                    </div>
                    <button type="button" aria-label={__('Close', 'wpml-translation-check')} className={`${prefix}-modal-close`} onClick={(e) => onModalClose(e)}>&times;</button>
                </div>
                {(countInfo.totalPosts < 1 && countInfo.errorPosts < 1) && !isLoading ?
                    <p>{emptyPostMessage}</p> :
                    <>
                        {isLoading && <div className={`${prefix}-progress-skeleton`}></div>}
                        {(countInfo.totalPosts > 0) && progressBarVisibility && !isLoading ?
                                <div className={`${prefix}-overall-progress`}>
                                    <div className={`${prefix}-progress-bar`}>
                                        <div className={`${prefix}-progress`} style={{ width: progressStatus + '%' }}>{progressStatus + '%'}</div>
                                    </div>
                                </div> : (countInfo.postsTranslated > 0 &&
                                <div className={`${prefix}-count-container`}>
                                    <div className={`${prefix}-post-count`}>
                                        <span className={`${prefix}-count-text-heading`}>{__('Posts', 'wpml-translation-check')} </span><br />
                                        <span className={`${prefix}-post-translated-post`}>{countInfo.postsTranslated}/{countInfo.totalPosts}</span>
                                    </div>
                                    <div className={`${prefix}-string-count`}>
                                        <span className={`${prefix}-count-text-heading`}>{__('Characters', 'wpml-translation-check')} </span><br />
                                        <span className={`${prefix}-string-number`}>{countInfo.charactersTranslated}</span>
                                    </div>
                                    <div className={`${prefix}-char-count`}>
                                        <span className={`${prefix}-count-text-heading`}>{__('Time Taken', 'wpml-translation-check')} </span><br />
                                        <span className={`${prefix}-char-number`}>{Math.round((countInfo.endTime - countInfo.startTime) / 1000)} {__('seconds', 'wpml-translation-check')}</span>
                                    </div>
                                </div>
                            )
                        }

                        <div className={`${prefix}-status-table-container`}>
                            <div className={`${prefix}-status-inner`}>
                                {isLoading && 
                                <>
                                <div className={`${prefix}-status-header-container`}>
                                    <div className={`${prefix}-status-flag-th`}>
                                    <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                    </div>
                                    <div className={`${prefix}-status-status-th`}>
                                    <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                    </div>
                                    <div className={`${prefix}-status-title-th`}>
                                    <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                    </div>
                                    <div className={`${prefix}-status-actions-th`}>
                                    <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                    </div>
                                </div>
                                {postIds.map((postId) => (
                                    <div className={`${prefix}-status-inner-item`} key={postId}>
                                        <div className={`${prefix}-status-parent-post-title`}>
                                            <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                        </div>
                                        <div className={`${prefix}-status-target-post`}>
                                            <div className={`${prefix}-status-target-post-flag`}>
                                                <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                            </div>
                                            <div className={`${prefix}-status-target-post-status`}>
                                                <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                            </div>
                                            <div className={`${prefix}-status-target-post-title`} style={{ gridColumn: 'span 2' }}>
                                                <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginInline: 'auto', marginBottom: '0px' }}></div>
                                            </div>
                                            <div className={`${prefix}-status-target-post-actions`}>
                                                <div className={`${prefix}-progress-skeleton`} style={{ maxWidth: '80px', marginBottom: '0px' }}></div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                </>
                                }
                                {
                                    !isLoading && Object.keys(translatePostInfo).length > 0 &&
                                    <div className={`${prefix}-status-header-container`}>
                                        <div className={`${prefix}-status-flag-th`}>
                                            <span className={`${prefix}-status-header-label`}>{__('Language', 'wpml-translation-check')}</span>
                                        </div>
                                        <div className={`${prefix}-status-status-th`}>
                                            <span className={`${prefix}-status-header-label`}>{__('Status', 'wpml-translation-check')}</span>
                                        </div>
                                        <div className={`${prefix}-status-title-th`}>
                                            <span className={`${prefix}-status-header-label`}>{__('Preview', 'wpml-translation-check')}</span>
                                        </div>
                                        <div className={`${prefix}-status-actions-th`}>
                                            <span className={`${prefix}-status-header-label`}>{__('Actions', 'wpml-translation-check')}</span>
                                        </div>
                                    </div>
                                }
                                {!isLoading && Object.keys(errorPostsInfo).length > 0 &&
                                    Object.keys(errorPostsInfo).map((key, index) => {
                                        return (
                                            <div className={`${prefix}-status-inner-item`} key={key}>
                                                <div key={`group-title-${key}`} className={`${prefix}-group-title`}>
                                                    {errorPostsInfo[key]?.title || __('Untitled', 'wpml-translation-check')}
                                                </div>
                                                <div className={`${prefix}-status-inner-item ${prefix}-error-message`}>
                                                    <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(errorPostsInfo[key].errorMessage) }}></div>
                                                </div>
                                            </div>
                                        );
                                    })
                                }

                                {!isLoading && Object.keys(translatePostInfo).map((key, index) => {
                                    const info = translatePostInfo[key];
                                    const workingStatus = info.status === 'running' || info.status === 'in-progress' ? true : false;
                                    return (
                                        <div className={`${prefix}-status-inner-item`} key={`group-title-${info.parentPostId || key}`}>
                                            <div className={`${prefix}-status-parent-post-title`}>{info.parentPostTitle || __('Untitled', 'wpml-translation-check')}</div>
                                            <div className={`${prefix}-status-target-post`}>
                                                <div className={`${prefix}-status-target-post-flag`}>
                                                    {info.flagUrl && <img src={info.flagUrl} width="20" alt={info.targetLanguage} />}
                                                    {info.languageName || info.targetLanguage}
                                                </div>
                                                {info.status === 'error' ?
                                                    <>
                                                        {!info.errorHtml ?
                                                            <div className={`${prefix}-status-target-post-error ${prefix}-error-message`} style={{ gridColumn: 'span 4' }}>
                                                                <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(info.errorMessage) }}></div>
                                                            </div> :
                                                            <div className={`${prefix}-status-target-post-error-button`} style={{ gridColumn: 'span 4' }}>
                                                                {info.errorHtml && <div className={`${prefix}-status-target-post-error-button`} onClick={() => { handleErrorModal(info) }}><button className={`${prefix}-status-error-button`}>{__('Error Details', 'wpml-translation-check')}</button></div>}
                                                            </div>
                                                        }
                                                    </> :
                                                    <>
                                                        <div className={`${prefix}-status-target-post-status`}>
                                                            <span className={`${info.messageClass} ${info.status}`}>{getPostStatus(info.status)}</span>
                                                            {workingStatus && <div className={`${prefix}-progress-bar-circular`} data-id={info.parentPostId + '_' + info.targetLanguage}>
                                                                <svg className={`${prefix}-circle`} viewBox="0 0 36 36">
                                                                    <path className={`${prefix}-bg`} d="M18 2.0845
                                                            a 15.9155 15.9155 0 0 1 0 31.831
                                                            a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                                    <path className={`${prefix}-progress`}
                                                                        strokeDasharray="0, 100"
                                                                        d="M18 2.0845
                                                            a 15.9155 15.9155 0 0 1 0 31.831
                                                            a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                                </svg>
                                                                <div className={`${prefix}-percentage`}>0%</div>
                                                            </div>}
                                                        </div>
                                                        <div className={`${prefix}-status-target-post-title`} style={{ gridColumn: 'span 2' }}>
                                                            <>
                                                                {info.status === 'completed' ?
                                                                    <a href={info.postLink} target="_blank" rel="noopener noreferrer">{info.targetPostTitle}</a> :
                                                                    (info.status === 'in-progress' ?
                                                                        <div className={`${prefix}-${info.messageClass}-text`}>{__('In Progress', 'wpml-translation-check')}<span></span></div> :
                                                                        <div className={`${prefix}-progress-skeleton short`} style={{ marginInline: 'auto' }}></div>)
                                                                }
                                                            </>
                                                        </div>
                                                        <div className={`${prefix}-status-target-post-actions`}>
                                                            {info.status === 'completed' && info.targetPostId ?
                                                                <span className={`${prefix}-view-link`}>
                                                                    {allPostStatus(info.parentPostId) ? (
                                                                        <a
                                                                            href={info.postEditLink}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="button button-primary"
                                                                            title={sprintf(__('Open the translated %s for review', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label)}
                                                                        >
                                                                            {__('Review', 'wpml-translation-check')}
                                                                        </a>
                                                                    ) : (
                                                                        <button
                                                                            className="button disabled"
                                                                            disabled
                                                                            title={sprintf(__('Please wait until all translations for this %s are complete before reviewing.', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label)}
                                                                        >
                                                                            {__('Review', 'wpml-translation-check')}
                                                                        </button>
                                                                    )}
                                                                </span>
                                                                :
                                                                (info.status === 'in-progress' ?
                                                                    <div className={`${prefix}-${info.messageClass}-text`}>{__('In Progress', 'wpml-translation-check')}<span></span></div> :
                                                                    <div className={`${prefix}-progress-skeleton short`}></div>)
                                                            }
                                                        </div>
                                                    </>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        <div className={`${prefix}-status-footer`}>
                            {isLoading ?
                             <div className={`${prefix}-progress-skeleton`}></div> :
                             (!(countInfo.postsTranslated > 0 && !pendingPosts.length)) ? <div className={`${prefix}-progress-button button button-primary`} disabled>{sprintf(__('Check Translated %s', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label)}</div> :
                             <a className={`${prefix}-progress-button button button-primary`} href={getTranslatedPostLink()}>{sprintf(__('Check Translated %s', 'wpml-translation-check'), automlp_wpml_bulk_translate_object.post_label)}</a>
                            }
                        </div>
                    </>
                }

            </div>
    );
};

export default StatusModal;
