import React, { useEffect, useRef, useState } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { selectTranslatePostInfo, selectProgressStatus, selectCountInfo, selectPendingPosts, selectServiceProvider, selectErrorPostsInfo, selectTargetLanguages } from '../redux-store/features/selectors';
import { __, sprintf } from '@wordpress/i18n';
import ErrorModalBox from '../components/error-modal-box';
import { store } from '../redux-store/store';
import DOMPurify from 'dompurify';
import { updatePendingPosts, updateCountInfo, updateTranslatePostInfo, unsetPendingPost, updateProgressStatus } from '../redux-store/features/actions';

import { queuePosts, pollUntilDone, retryJob, jobProgressPercent } from '../../shared/queue-client';

/**
 * Posts per /queue/posts request. WPML needs a few seconds to build each job,
 * so small sequential requests keep the modal responsive.
 */

/**
 * Row states that need no more work.
 */
const TERMINAL_ROW = ['completed', 'error', 'skipped'];

const escapeHtml = (value) =>
    String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

const StatusModal = ({ postIds, selectedLanguages, prefix, onDestory }) => {

    const storeDispatch = useDispatch();
    // Every selected post x language, rendered before the server answers.
    const rowKeysRef = useRef([]);
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
        retrying: 'retrying',
    }[state] || 'pending');

    const mapClass = (state) => ({
        done: 'success',
        failed: 'error',
        stopped: 'error',
        waiting: 'warning',
        retrying: 'warning',
    }[state] || 'in-progress');

    const displayState = (job) => {
        const progress = jobProgressPercent(job);

        // A failed attempt waits for its automatic retry: not terminal yet.
        if (job.state === 'waiting' && Number(job.attempts) > 0 && !job.closed) {
            return 'retrying';
        }

        return job.state === 'waiting' && progress > 0 ? 'sent' : job.state;
    };

    /**
     * Overall percent across every selected row, not just the jobs created
     * so far: a row still being queued counts as 0%, a finished or skipped
     * one as 100%.
     */
    const overallPercent = (status) => {
        const keys = rowKeysRef.current;

        if (!keys.length) {
            return 0;
        }

        const byKey = {};
        (status.jobs || []).forEach((job) => {
            byKey[`${job.source_id}_${job.to_lang}`] = job;
        });

        const info = store.getState().translatePostInfo || {};
        let sum = 0;

        keys.forEach((key) => {
            const job = byKey[key];

            if (job) {
                sum += job.closed ? 100 : jobProgressPercent(job);
            } else if (info[key] && TERMINAL_ROW.includes(info[key].status)) {
                sum += 100;
            }
        });

        return sum / keys.length;
    };

    /**
     * Push one poll payload into the store.
     *
     * While jobs are still running, only the progress bar moves — individual
     * rows stay in an active state so completions do not flash one-by-one.
     * When the batch finishes, every row and the 100% bar update together.
     */
    const applyStatus = (status) => {
        if (!status || !Array.isArray(status.jobs)) {
            return;
        }

        const current = store.getState().progressStatus || 0;
        const percent = overallPercent(status);

        if (!status.finished) {
            const displayPercent = Math.min(Math.max(current, percent), 99);
            storeDispatch(updateProgressStatus(displayPercent - current));

            const TERMINAL = ['done', 'failed', 'stopped'];

            status.jobs.forEach((job) => {
                const key = `${job.source_id}_${job.to_lang}`;
                const progress = jobProgressPercent(job);
                const state = displayState(job);

                if (job.closed && TERMINAL.includes(job.state)) {
                    const stringCount = Number(job.string_count ?? job.field_count) || 0;
                    const charCount = Number(job.char_count) || 0;

                    const update = {
                        status: mapState(state),
                        messageClass: mapClass(state),
                        stringCount,
                        charCount,
                        jobId: job.job_id,
                        progress,
                    };

                    if (job.state === 'done') {
                        update.targetPostId = job.result_id;
                        update.targetPostTitle = job.result_title || __('N/A', 'wpml-translation-check');
                        update.postLink = job.view_link;
                        update.postEditLink = job.edit_link;
                    }

                    if (job.state === 'failed' || job.state === 'stopped') {
                        update.errorMessage = __('Translation failed.', 'wpml-translation-check');
                        update.errorHtml = `<div class="automlp-wpml-error-html">${job.error || ''}</div>`;
                        update.aiError = job.state === 'failed';
                        update.parentPostId = job.source_id;
                        update.targetLanguage = job.to_lang;
                    }

                    storeDispatch(unsetPendingPost(key));
                    storeDispatch(updateTranslatePostInfo({ [key]: update }));
                    return;
                }

                storeDispatch(updateTranslatePostInfo({
                    [key]: {
                        status: mapState(state),
                        messageClass: mapClass(state),
                        jobId: job.job_id,
                        progress,
                    },
                }));
            });

            return;
        }

        // Batch finished — paint every row and jump to 100% in one pass.
        let translated = 0;
        let stringsTranslated = 0;
        let charactersTranslated = 0;

        if (status.totals) {
            translated = Number(status.totals.done) || 0;
            stringsTranslated = Number(status.totals.strings) || 0;
            charactersTranslated = Number(status.totals.characters) || 0;
        }

        status.jobs.forEach((job) => {
            const key = `${job.source_id}_${job.to_lang}`;
            const stringCount = Number(job.string_count ?? job.field_count) || 0;
            const charCount = Number(job.char_count) || 0;

            const update = {
                status: mapState(job.state),
                messageClass: mapClass(job.state),
                stringCount,
                charCount,
                jobId: job.job_id,
                progress: jobProgressPercent(job),
            };

            if (job.state === 'done') {
                if (!status.totals) {
                    translated++;
                    stringsTranslated += stringCount;
                    charactersTranslated += charCount;
                }
                update.targetPostId = job.result_id;
                update.targetPostTitle = job.result_title || __('N/A', 'wpml-translation-check');
                update.postLink = job.view_link;
                update.postEditLink = job.edit_link;
            }

            if (job.state === 'failed' || job.state === 'stopped') {
                update.errorMessage = __('Translation failed.', 'wpml-translation-check');
                update.errorHtml = `<div class="automlp-wpml-error-html">${job.error || ''}</div>`;
                update.aiError = job.state === 'failed';
                update.parentPostId = job.source_id;
                update.targetLanguage = job.to_lang;
            }

            if (job.closed) {
                storeDispatch(unsetPendingPost(key));
            }

            storeDispatch(updateTranslatePostInfo({ [key]: update }));
        });

        storeDispatch(updateProgressStatus(100 - current));
        storeDispatch(updateCountInfo({
            postsTranslated: translated,
            stringsTranslated,
            charactersTranslated,
        }));
    };

    useEffect(() => {
        let cancelled = false;
    
        const run = async () => {
            const languageObject = automlp_wpml_bulk_translate_object.languageObject || {};
            const rowKeys = [];
            const jobIds = [];
            const seen = [];
            let skippedRows = 0;
            let adding = true;
            let polling = null;

            const rowTitle = (postId) => {
                const row = document.getElementById(`post-${postId}`) || document.getElementById(`tag-${postId}`);
                const title = row ? row.querySelector('.row-title') : null;
                const text = title ? title.textContent.trim() : '';

                return text || sprintf(__('#%s', 'wpml-translation-check'), postId);
            };

            // 1. Show every selected post x language at once.
            postIds.forEach((rawId) => {
                const postId = Number(rawId);

                selectedLanguages.forEach((langCode) => {
                    const key = `${postId}_${langCode}`;
                    const lang = languageObject[langCode] || {};
                    const firstPostLanguage = !seen.includes(postId);

                    if (firstPostLanguage) seen.push(postId);

                    rowKeys.push(key);
                    storeDispatch(updatePendingPosts([key]));
                    storeDispatch(updateTranslatePostInfo({
                        [key]: {
                            jobId: null,
                            parentPostId: postId,
                            parentPostTitle: rowTitle(postId),
                            targetPostId: null,
                            targetLanguage: langCode,
                            postLink: null,
                            status: 'queuing',
                            messageClass: 'warning',
                            firstPostLanguage,
                            flagUrl: lang.flag,
                            languageName: lang.name,
                            progress: 0,
                        },
                    }));
                });
            });

            rowKeysRef.current = rowKeys;
            storeDispatch(updateCountInfo({
                totalPosts: rowKeys.length,
                startTime: new Date().getTime(),
            }));
            setIsLoading(false);

            const closeRow = (key, update) => {
                storeDispatch(updateTranslatePostInfo({ [key]: update }));
                storeDispatch(unsetPendingPost(key));
            };

            const skipRow = (key, message) => {
                skippedRows++;
                closeRow(key, { status: 'skipped', messageClass: 'warning', errorMessage: message, errorHtml: '' });
                storeDispatch(updateCountInfo({ totalPosts: rowKeys.length - skippedRows }));
            };

            const failRow = (key, postId, langCode, message) => {
                closeRow(key, {
                    status: 'error',
                    messageClass: 'error',
                    errorMessage: __('Could not be queued.', 'wpml-translation-check'),
                    errorHtml: `<div class="automlp-wpml-error-html">${escapeHtml(message || __('Could not be queued.', 'wpml-translation-check'))}</div>`,
                    aiError: false,
                    parentPostId: postId,
                    targetLanguage: langCode,
                });
            };

            const reportProgress = () => {
                const current = store.getState().progressStatus || 0;
                const percent = Math.min(overallPercent({ jobs: [] }), 99);

                if (percent > current) {
                    storeDispatch(updateProgressStatus(percent - current));
                }
            };

            // 2. Merge each request's outcome into its pre-rendered rows.
            const onChunk = (chunk, res, err) => {
                if (cancelled) {
                    return;
                }

                const jobsByKey = {};
                ((res && res.jobs) || []).forEach((job) => {
                    jobsByKey[`${job.source_id}_${job.to_lang}`] = job;
                });

                const items = (res && res.items) || null;
                const errors = (res && res.errors) || {};

                chunk.forEach((rawId) => {
                    const postId = Number(rawId);

                    selectedLanguages.forEach((langCode) => {
                        const key = `${postId}_${langCode}`;
                        const job = jobsByKey[key];

                        if (err) {
                            failRow(key, postId, langCode, err.message || String(err));
                            return;
                        }

                        if (job) {
                            jobIds.push(job.job_id);
                            storeDispatch(updateTranslatePostInfo({
                                [key]: {
                                    jobId: job.job_id,
                                    parentPostTitle: job.source_title || rowTitle(postId),
                                    status: 'in-queue',
                                    messageClass: 'warning',
                                },
                            }));
                            return;
                        }

                        const outcome = items ? items[key] : (errors[key] ? 'error' : 'already_translated');

                        if (outcome === 'already_translated') {
                            skipRow(key, errors[key] || __('Already translated. Re-translation is available in the Pro version.', 'wpml-translation-check'));
                        } else if (outcome === 'no_permission') {
                            skipRow(key, errors[key] || __('You are not allowed to translate this post.', 'wpml-translation-check'));
                        } else if (outcome === 'unsupported_editor') {
                            skipRow(key, errors[key] || __('Not supported in the free version (Classic editor / other page builders / Elementor templates).', 'wpml-translation-check'));
                        } else if (outcome === 'ate_active') {
                            skipRow(key, errors[key] || __('Skipped: this page already has an open WPML translation job for this language. Finish or cancel it in WPML first.', 'wpml-translation-check'));
                        } else {
                            failRow(key, postId, langCode, errors[key] || (res && res.message) || '');
                        }
                    });
                });

                reportProgress();

                if (!polling && jobIds.length) {
                    polling = pollUntilDone({
                        jobIds: () => jobIds,
                        shouldStop: () => cancelled,
                        isAdding: () => adding,
                        onUpdate: applyStatus,
                    });
                }
            };

            let result = null;

            try {
                result = await queuePosts(postIds, selectedLanguages, serviceProvider, {
                    // One request queues every selected post, so nothing is
                    // lost if the tab closes while jobs are being created.
                    perRequest: Math.max(1, postIds.length),
                    onChunk,
                });
            } catch (error) {
                // Every request failed; rows were already marked by onChunk.
                result = { queued: 0, skipped: 0, message: error && error.message ? error.message : '' };
            } finally {
                adding = false;
            }

            if (cancelled) {
                return;
            }

            try {
                if (!polling) {
                    // Nothing to watch: every row is skipped or failed.
                    if (skippedRows === rowKeys.length) {
                        setEmptyPostMessage(
                            result.message
                                ? result.message
                                : sprintf(
                                      __('Translations already exist for all selected %s in the chosen language. Re-translation is available in the Pro version.', 'wpml-translation-check'),
                                      automlp_wpml_bulk_translate_object.post_label
                                  )
                        );
                        setProgressBarVisibility(false);
                    }

                    const current = store.getState().progressStatus || 0;
                    storeDispatch(updateProgressStatus(100 - current));
                    storeDispatch(updateCountInfo({ endTime: new Date().getTime() }));
                    return;
                }

                await polling;

                storeDispatch(updateCountInfo({ endTime: new Date().getTime() }));
            } catch (error) {
                // Polling timed out: work continues on the server.
                setEmptyPostMessage(
                    error && error.message
                        ? error.message
                        : __('Translation could not be started.', 'wpml-translation-check')
                );
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
                // Only mark completed if at least one job succeeded; otherwise
                // stay at 'pending' so the heading does not say Completed when
                // every job failed.
                const infos = Object.values(translatePostInfo);
                const anyDone = infos.some((info) => info.status === 'completed');
                const anyError = infos.some((info) => info.status === 'error');
                const anySkipped = infos.some((info) => info.status === 'skipped');
                // Every row is terminal here (done, failed/cancelled or
                // skipped): failures always read "Completed with errors".
                if (anyError) {
                    updateBulkStatus('partial');
                } else if (anyDone) {
                    updateBulkStatus(anySkipped ? 'completed-skipped' : 'completed');
                } else {
                    updateBulkStatus('pending');
                }
                return;
            }

            let error = false;
            let running = false;

            const runLoop = (items, index) => {
                const status = translatePostInfo[items[index]].status;

                if (status === 'running' || status === 'in-progress' || status === 'pending' || status === 'in-queue' || status === 'queuing') {
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
            case 'partial':
                return __('Completed with errors', 'wpml-translation-check');
            case 'completed-skipped':
                return __('Completed – some items were skipped', 'wpml-translation-check');
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

            const hideTimer = setTimeout(() => {
                setProgressBarVisibility(false);
            }, 2000);

            return () => clearTimeout(hideTimer);
        }
    }, [pendingPosts, progressStatus, countInfo.postsTranslated]);

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
            // A missing, finished or replaced WPML job is retried as a new
            // queue job: watch that one, not the closed original.
            const retried = await retryJob(jobId);
            const nextId = (retried && retried.job && retried.job.job_id) || jobId;

            storeDispatch(updateTranslatePostInfo({ [key]: { jobId: nextId } }));

            await pollUntilDone({
                jobIds: [nextId],
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

    // Returns true only when every job that shares this source post has
    // reached a terminal state. Derived directly from translatePostInfo so
    // the queue-based flow (which never populates targetLanguages) works too.
    const allPostStatus = (postId) => {
        const siblingKeys = Object.keys(translatePostInfo).filter(
            (k) => translatePostInfo[k].parentPostId === postId
        );

        if (!siblingKeys.length) {
            return true;
        }

        const ACTIVE = ['pending', 'in-progress', 'running', 'in-queue', 'queuing'];
        return siblingKeys.every(
            (k) => !ACTIVE.includes(translatePostInfo[k].status)
        );
    };

    const getPostStatus = (type) => {
        switch (type) {
            case 'pending':
                return __('Pending', 'wpml-translation-check');
            case 'completed':
                return __('Completed', 'wpml-translation-check');
            case 'in-queue':
                return __('In Queue', 'wpml-translation-check');
            case 'queuing':
                return __('Preparing', 'wpml-translation-check');
            case 'skipped':
                return __('Skipped', 'wpml-translation-check');
            case 'retrying':
                return __('Retrying', 'wpml-translation-check');
            case 'error':
                return __('Failed', 'wpml-translation-check');
            case 'running':
            case 'in-progress':
                return __('In Progress', 'wpml-translation-check');
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
                            countInfo.postsTranslated > 0 && (
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
                                        <span className={`${prefix}-char-number`}>
                                            {Math.max(0, Math.round(((countInfo.endTime || Date.now()) - (countInfo.startTime || Date.now())) / 1000))} {__('seconds', 'wpml-translation-check')}
                                        </span>
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
                                    const workingStatus = ['running', 'in-progress'].includes(info.status);
                                    const rowProgress = Math.min(100, Math.max(0, Number(info.progress) || 0));
                                    return (
                                        <div className={`${prefix}-status-inner-item`} key={`group-title-${info.parentPostId || key}`}>
                                            <div className={`${prefix}-status-parent-post-title`}>{info.parentPostTitle || __('Untitled', 'wpml-translation-check')}</div>
                                            <div className={`${prefix}-status-target-post`}>
                                                <div className={`${prefix}-status-target-post-flag`}>
                                                    {info.flagUrl && <img src={info.flagUrl} width="20" alt={info.targetLanguage} />}
                                                    {info.languageName || info.targetLanguage}
                                                </div>
                                                {info.status === 'skipped' ?
                                                    <>
                                                        <div className={`${prefix}-status-target-post-status`}>
                                                            <span className="neutral skipped">{getPostStatus('skipped')}</span>
                                                        </div>
                                                        <div className={`${prefix}-status-skip-reason`} style={{ gridColumn: 'span 3' }}>
                                                            {info.errorMessage}
                                                        </div>
                                                    </> :
                                                (info.status === 'error') ?
                                                    <>
                                                        <div className={`${prefix}-status-target-post-status`}>
                                                            <span className="error">{getPostStatus('error')}</span>
                                                        </div>
                                                        {!info.errorHtml ?
                                                            <div className={`${prefix}-status-target-post-error ${prefix}-error-message`} style={{ gridColumn: 'span 3' }}>
                                                                <div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(info.errorMessage) }}></div>
                                                            </div> :
                                                            <div className={`${prefix}-status-target-post-error-button`} style={{ gridColumn: 'span 3' }}>
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
                                                                        strokeDasharray={`${rowProgress}, 100`}
                                                                        d="M18 2.0845
                                                            a 15.9155 15.9155 0 0 1 0 31.831
                                                            a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                                </svg>
                                                                <div className={`${prefix}-percentage`}>{rowProgress}%</div>
                                                            </div>}
                                                        </div>
                                                        <div className={`${prefix}-status-target-post-title`} style={{ gridColumn: 'span 2' }}>
                                                            <>
                                                                {info.status === 'completed' ?
                                                                    <a href={info.postLink} target="_blank" rel="noopener noreferrer">{info.targetPostTitle}</a> :
                                                                    (workingStatus ?
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
                                                                (workingStatus ?
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
