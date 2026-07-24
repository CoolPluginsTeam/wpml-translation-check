import React, { useEffect, useState } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { selectTranslatePostInfo, selectProgressStatus, selectCountInfo, selectPendingPosts, selectServiceProvider, selectErrorPostsInfo, selectTargetLanguages } from '../redux-store/features/selectors';
import { __, sprintf } from '@wordpress/i18n';
import ErrorModalBox from '../components/error-modal-box';
import { store } from '../redux-store/store';
import DOMPurify from 'dompurify';
import { updatePendingPosts, updateCountInfo, updateTranslatePostInfo, unsetPendingPost, updateProgressStatus } from '../redux-store/features/actions';

import { queuePosts, pollUntilDone, summarise, retryJob } from '../../shared/queue-client';

const StatusModal = ({ postIds, selectedLanguages, prefix, onDestory }) => {

    const storeDispatch = useDispatch();
    const [isLoading, setIsLoading] = useState(true);
    const [errorModal, setErrorModal] = useState(false);
    const [errorModalData, setErrorModalData] = useState(false);
    const translatePostInfo = useSelector(selectTranslatePostInfo);
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

    /**
     * Map a server job state onto the status strings the JSX already renders.
     */
    const mapState = (state) => ({
        waiting: 'in-queue',
        claimed: 'running',
        sent: 'running',
        writing: 'in-progress',
        done: 'completed',
        failed: 'error',
        stopped: 'error',
    }[state] || 'pending');

    const mapClass = (state) => ({
        done: 'success',
        failed: 'error',
        stopped: 'error',
        waiting: 'warning',
    }[state] || 'in-progress');

    /**
     * Push one poll payload into the store.
     *
     * Reads progress from the store rather than the render-scoped variable so
     * repeated polls do not compound a stale value.
     */
    const applyStatus = (status) => {
        if (!status || !Array.isArray(status.jobs)) {
            return;
        }

        let translated = 0;

        status.jobs.forEach(job => {
            const key = `${job.source_id}_${job.to_lang}`;

            const update = {
                status: mapState(job.state),
                messageClass: mapClass(job.state),
            };

            if (job.state === 'done') {
                translated++;
                update.targetPostId = job.result_id;
                update.targetPostTitle = job.result_title || __('N/A', 'wpml-translation-check');
                update.postLink = job.view_link;
                update.postEditLink = job.edit_link;
            }

            if (job.state === 'failed' || job.state === 'stopped') {
                update.errorMessage = __('Translation failed.', 'wpml-translation-check');
                update.errorHtml = `<div class="automlp-wpml-error-html">${job.error || ''}</div>`;
                update.jobId = job.job_id;
                update.aiError = job.state === 'failed';
                update.parentPostId = job.source_id;
                update.targetLanguage = job.to_lang;
            }

            if (job.closed) {
                storeDispatch(unsetPendingPost(key));
            }

            storeDispatch(updateTranslatePostInfo({ [key]: update }));
        });

        const { percent } = summarise(status);
        const current = store.getState().progressStatus || 0;

        storeDispatch(updateProgressStatus(percent - current));
        storeDispatch(updateCountInfo({ postsTranslated: translated }));
    };

    useEffect(() => {
        let cancelled = false;
    
        const run = async () => {
            try {
                const { queued, skipped, errors, jobs, message } = await queuePosts(postIds, selectedLanguages, serviceProvider);
    
                if (!queued) {
                    setEmptyPostMessage(
                        message
                            ? message
                            : skipped
                            ? sprintf(
                                  __('Translations already exist for all selected %s in the chosen languages.', 'wpml-translation-check'),
                                  automlp_wpml_bulk_translate_object.post_label
                              )
                            : Object.values(errors || {})[0] || __('Nothing could be queued.', 'wpml-translation-check')
                    );
                    setIsLoading(false);
                    setProgressBarVisibility(false);
                    return;
                }

                // Queued, but the server returned nothing to watch. Do not
                // fall through to the empty-state message, which would show
                // the misleading "already translated" default.
                if (!Array.isArray(jobs) || jobs.length === 0) {
                    setEmptyPostMessage(
                        __('Translation was queued but no jobs came back. Open the Translation Queue screen to check status.', 'wpml-translation-check')
                    );
                    setIsLoading(false);
                    setProgressBarVisibility(false);
                    return;
                }
    
                // Seed the store so rows appear immediately, before the first poll.
                const seen = [];
    
                jobs.forEach(job => {
                    const key = `${job.source_id}_${job.to_lang}`;
                    const lang = automlp_wpml_bulk_translate_object.languageObject[job.to_lang] || {};
    
                    const firstPostLanguage = !seen.includes(job.source_id);
                    if (firstPostLanguage) seen.push(job.source_id);
    
                    storeDispatch(updatePendingPosts([key]));
                    storeDispatch(updateTranslatePostInfo({
                        [key]: {
                            jobId: job.job_id,
                            parentPostId: job.source_id,
                            parentPostTitle: job.source_title,
                            targetPostId: null,
                            targetLanguage: job.to_lang,
                            postLink: null,
                            status: 'in-queue',
                            messageClass: 'warning',
                            firstPostLanguage,
                            flagUrl: lang.flag,
                            languageName: lang.name,
                        },
                    }));
                });
    
                storeDispatch(updateCountInfo({ totalPosts: jobs.length }));
                setIsLoading(false);
    
                await pollUntilDone({
                    jobIds: jobs.map(job => job.job_id),
                    shouldStop: () => cancelled,
                    onUpdate: applyStatus,
                });
    
                storeDispatch(updateCountInfo({ endTime: new Date().getTime() }));
            } catch (error) {
                setEmptyPostMessage(
                    error && error.message
                        ? error.message
                        : __('Translation could not be started.', 'wpml-translation-check')
                );
                setIsLoading(false);
                setProgressBarVisibility(false);
            }
        };
    
        run();
    
        return () => {
            cancelled = true;
        };
    }, []);

    const handleErrorModal = (data) => {
        setErrorModalData(data);
        setErrorModal(true);
    }

    const closeErrorModal = (e) => {
        setErrorModal(false);
        setErrorModalData(false);
    }

    const onModalClose = (e) => {
        // Closing stops polling only. Queued translations keep running on the
        // server, so there is nothing to abort here.
        onDestory(e);

        if (countInfo.postsTranslated > 0 && !pendingPosts.length) {
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

    const AIErrorBtnHandler = async (e) => {
        const btnType = e.target.dataset.status;

        // "Continue" no longer skips anything: whatever succeeded was already
        // saved, so the only action left is to close the dialog.
        if (btnType !== 'translateAgain') {
            closeErrorModal();
            return;
        }

        const jobId = errorModalData.jobId;

        closeErrorModal();

        if (!jobId) {
            return;
        }

        const key = `${errorModalData.parentPostId}_${errorModalData.targetLanguage}`;

        storeDispatch(updatePendingPosts([key]));
        storeDispatch(updateTranslatePostInfo({
            [key]: { status: 'in-queue', messageClass: 'warning', errorHtml: '', errorMessage: '' },
        }));

        try {
            await retryJob(jobId);

            await pollUntilDone({
                jobIds: [jobId],
                onUpdate: applyStatus,
            });
        } catch (error) {
            storeDispatch(updateTranslatePostInfo({
                [key]: {
                    status: 'error',
                    messageClass: 'error',
                    errorMessage: __('Translation failed.', 'wpml-translation-check'),
                    errorHtml: `<div class="automlp-wpml-error-html">${error.message}</div>`,
                },
            }));
        }
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
                            {__("STEP 3 OF 3", "wpml-translation-check")}
                        </span>
                        <h2 className={`${prefix}-bulk-status-heading ${bulkStatus}`}>
                            {sprintf(
                                __("Bulk Translation %s", "wpml-translation-check"),
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
