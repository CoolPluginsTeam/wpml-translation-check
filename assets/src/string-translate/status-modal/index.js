import React, { useEffect, useState } from "react";
import {
  bulkTranslateStrings,
  initBulkTranslateStrings,
} from "../bulk-translate";
import { useSelector, useDispatch } from "react-redux";
import {
  selectTranslatePostInfo,
  selectProgressStatus,
  selectCountInfo,
  selectPendingPosts,
  selectServiceProvider,
  selectErrorPostsInfo,
} from "../redux-store/features/selectors";
import { __, sprintf } from "@wordpress/i18n";
import ErrorModalBox from "../components/error-modal-box";
import AIService from "../components/translate-provider/ai-services";
import { store } from "../redux-store/store";
import DOMPurify from "dompurify";

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
  const [charactersCountVisibility, setCharactersCountVisibility] =
    useState(false);
  const [bulkStatus, setBulkStatus] = useState("status");
  const countInfo = useSelector(selectCountInfo);
  let [emptyPostMessage, setEmptyPostMessage] = useState(
    sprintf(
      __(
        "Translations already exist for all selected %s in the chosen languages. There are no new %s to translate.",
        "automlp-ai-translation-for-wpml",
      ),
      automlp_wpml_bulk_translate_object.post_label,
      automlp_wpml_bulk_translate_object.post_label,
    ),
  );
  let progressStatus = useSelector(selectProgressStatus);
  progressStatus = progressStatus.toFixed(1);
  progressStatus = Math.min(progressStatus, 100);
  const isStringTranslationPage = window.wpmlIsStringTranslationPage || false;

  useEffect(() => {
    const translateContent = async () => {
      const stringFilters = window.wpmlStringFilters || {};
      const selectedStrings = window.wpmlSelectedStrings || {};
      const stringLanguageStatus = window.wpmlStringLanguageStatus || {};

      // String translation flow only (no post/taxonomy flow in this component)
      const { bulkTranslateStrings } = await import("../bulk-translate");
      const response = await bulkTranslateStrings({
        langs: selectedLanguages,
        storeDispatch,
        stringFilters,
        selectedStrings,
        stringLanguageStatus,
      });

      setIsLoading(false);

      if (!response.success) {
        setEmptyPostMessage(
          response.message ||
            __(
              "No strings found to translate.",
              "automlp-ai-translation-for-wpml",
            ),
        );
        return;
      }

      initBulkTranslateStrings(
        response.stringKeys,
        response.stringsByLanguage,
        response.nonce,
        storeDispatch,
        prefix,
        updateDestoryHandler,
        response.totalPerLanguage || {},
      );
    };

    translateContent();
  }, []);

  const handleErrorModal = (data) => {
    setErrorModalData(data);
    setErrorModal(true);
  };

  const closeErrorModal = (e) => {
    setErrorModal(false);
    setErrorModalData(false);
  };

  const updateDestoryHandler = (callback) => {
    setDestroyHandlers((prev) => [...prev, callback]);
  };

  const onModalClose = (e) => {
    destroyHandlers.forEach(
      (callback) => typeof callback === "function" && callback(),
    );
    onDestory(e);
    const hasStrings = countInfo.stringsTranslated > 0;
    const hasErrors =
      countInfo.errorPosts > 0 ||
      (translatePostInfo &&
        Object.values(translatePostInfo).some(
          (info) => info?.status === "error",
        ));
    if (bulkStatus === "completed" && hasStrings && !hasErrors) {
      window.location.reload();
    }
  };

  useEffect(() => {
    if (countInfo.totalPosts < 1 && !isLoading && bulkStatus !== "status") {
      updateBulkStatus("status");
      return;
    }

    if (translatePostInfo && Object.keys(translatePostInfo).length > 0) {
      if (pendingPosts.length < 1) {
        updateBulkStatus("completed");
        return;
      }

      let error = false;
      let running = false;

      const runLoop = (items, index) => {
        const status = translatePostInfo[items[index]].status;

        if (
          status === "running" ||
          status === "in-progress" ||
          status === "pending"
        ) {
          running = true;
          bulkStatus !== "running" && updateBulkStatus("running");
          return;
        }

        if (status === "error") {
          error = true;
        }

        index++;
        if (index < items.length) {
          runLoop(items, index);
        }
      };

      runLoop(Object.keys(translatePostInfo), 0);

      if (running) return;

      if (error) {
        updateBulkStatus("pending");
      } else {
        updateBulkStatus("pending");
      }
    }
  }, [translatePostInfo]);

  const updateBulkStatus = (status) => {
    setBulkStatus(status);
  };

  const getBulkStatus = () => {
    switch (bulkStatus) {
      case "running":
        return __("In Progress", "automlp-ai-translation-for-wpml");
      case "pending":
        return __("Pending", "automlp-ai-translation-for-wpml");
      case "completed":
        return __("Completed", "automlp-ai-translation-for-wpml");
      default:
        return __("Status", "automlp-ai-translation-for-wpml");
    }
  };

  useEffect(() => {
    if (progressStatus >= 100 && pendingPosts.length < 1) {
      if (countInfo.postsTranslated < 1 && countInfo.stringsTranslated < 1) {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
        return;
      }

      if (countInfo.stringsTranslated > 0) {
        setTimeout(() => {
          setCharactersCountVisibility(true);
        }, 1000);
      }

      setTimeout(() => {
        setProgressBarVisibility(false);
        setCharactersCountVisibility(false);
      }, 1500);
    }
  }, [pendingPosts]);
  useEffect(() => {
    if (pendingPosts.length >= 1) return;
    const hasErrorPosts = countInfo.errorPosts > 0;
    const hasErrorInTranslateInfo =
      translatePostInfo &&
      Object.values(translatePostInfo).some((info) => info?.status === "error");
    if (hasErrorPosts || hasErrorInTranslateInfo) {
      setProgressBarVisibility(false);
    }
  }, [pendingPosts, countInfo.errorPosts, translatePostInfo]);

  const AIErrorBtnHandler = (e) => {
    const type = {
      translateAgain: AIService.translateAgain,
      continue: AIService.translateComplete,
    };

    const btnType = e.target.dataset.status;
    type[btnType]({
      postId: errorModalData.parentPostId,
      targetLang: errorModalData.targetLanguage,
      storeDispatch,
      prefix,
      updateDestoryHandler,
      nonce: errorModalData.nonce,
      closeErrorModal,
      completedStrings: errorModalData.completedStrings,
      totalPosts: errorModalData.totalPosts,
    });
  };

  const getServiceProviderLabel = () => {
    switch (serviceProvider) {
      case "google":
        return "Google Translate";
      case "localAiTranslator":
        return "Chrome AI Translator";
      case "openai_ai":
        return "OpenAI";
      case "google_ai":
        return "Gemini";
      case "openrouter_ai":
        return "OpenRouter";
      case "deepl_ai":
        return "DeepL";
      default:
        return "AI Translator";
    }
  };

  // When any string-aggregate row is completed, hide the header skeleton
  const hasCompletedStringAggregate =
    translatePostInfo &&
    Object.entries(translatePostInfo).some(
      ([key, info]) =>
        key.startsWith("strings_") && info.status === "completed",
    );
  return errorModal ? (
    <ErrorModalBox
      message={errorModalData.errorHtml}
      onClose={closeErrorModal}
      Title={__("Bulk Translation Error", "automlp-ai-translation-for-wpml")}
      prefix={prefix}
    >
      {errorModalData.aiError && (
        <div className={`${prefix}-ai-error-buttons`}>
          <button
            className={`${prefix}-ai-error-button button`}
            data-status="translateAgain"
            onClick={AIErrorBtnHandler}
          >
            {__("Translate", "automlp-ai-translation-for-wpml")}
          </button>
          <button
            className={`${prefix}-ai-error-button button`}
            data-status="continue"
            onClick={AIErrorBtnHandler}
          >
            {__("Continue", "automlp-ai-translation-for-wpml")}
          </button>
        </div>
      )}
    </ErrorModalBox>
  ) : (
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
          {bulkStatus !== "completed" &&
            !(
              countInfo.totalPosts < 1 &&
              countInfo.errorPosts < 1 &&
              countInfo.stringsTranslated < 1 &&
              !isLoading
            ) && (
              <p className={`${prefix}-modal-desc`}>
                {__(
                  "Your content is being translated. Please wait for a moment.",
                  "automlp-ai-translation-for-wpml",
                )}
              </p>
            )}
          {bulkStatus === "completed" &&
            countInfo.errorPosts < 1 &&
            !(
              translatePostInfo &&
              Object.values(translatePostInfo).some(
                (info) => info?.status === "error",
              )
            ) &&
            countInfo.stringsTranslated > 0 && (
              <p className={`${prefix}-modal-desc`}>
                {__(
                  "Your content has been translated successfully.",
                  "automlp-ai-translation-for-wpml",
                )}
              </p>
            )}
        </div>
        <span
          className={`${prefix}-modal-close`}
          onClick={(e) => onModalClose(e)}
        >
          &times;
        </span>
      </div>
      {countInfo.totalPosts < 1 &&
      countInfo.errorPosts < 1 &&
      countInfo.stringsTranslated < 1 &&
      !isLoading ? (
        <p>{emptyPostMessage}</p>
      ) : (
        <>
          {isLoading && <div className={`${prefix}-progress-skeleton`}></div>}
          {countInfo.totalPosts >= 1 &&
          progressBarVisibility &&
          !isLoading &&
          !(
            pendingPosts.length < 1 &&
            (countInfo.errorPosts > 0 ||
              (translatePostInfo &&
                Object.values(translatePostInfo).some(
                  (info) => info?.status === "error",
                )))
          ) ? (
            <>
              <div className={`${prefix}-overall-progress`}>
                <div className={`${prefix}-progress-bar`}>
                  <div
                    className={`${prefix}-progress`}
                    style={{ width: progressStatus + "%" }}
                  >
                    {progressStatus + "%"}
                  </div>
                </div>
                {/* Skeleton in same layout as count stats while progress bar is visible */}
                {!charactersCountVisibility && !hasCompletedStringAggregate && (
                  <div className={`${prefix}-td-pending`}>
                    <div className={`${prefix}-status-flag`}>
                      <div className={`${prefix}-status-flag-inner`}>
                        <div className={`${prefix}-status-flag-inner-left`}>
                          <span
                            className={`${prefix}-progress-skeleton`}
                            style={{
                              display: "inline-block",
                              width: 20,
                              height: 14,
                            }}
                          />
                        </div>
                        <div className={`${prefix}-status-flag-inner-right`}>
                          <span
                            className={`${prefix}-progress-skeleton`}
                            style={{
                              display: "inline-block",
                              width: 80,
                              height: 14,
                            }}
                          />
                        </div>
                      </div>
                    </div>

                    <div className={`${prefix}-status-content`}>
                      <div className={`${prefix}-status-content-inner`}>
                        <span className={`${prefix}-status`}>
                          <span
                            className={`${prefix}-progress-skeleton`}
                            style={{
                              display: "inline-block",
                              width: 70,
                              height: 14,
                            }}
                          />
                        </span>
                      </div>

                      <span className={`${prefix}-count-stat-value`}>
                        <span
                          className={`${prefix}-progress-skeleton`}
                          style={{
                            display: "inline-block",
                            width: 100,
                            height: 14,
                            marginTop: 4,
                          }}
                        />
                      </span>
                    </div>
                  </div>
                )}
              </div>
            </>
          ) : (
            countInfo.stringsTranslated > 0 && (
              <div className={`${prefix}-count-container`}>
                <div className={`${prefix}-count-stat-cell`}>
                  <span className={`${prefix}-count-stat-label`}>
                    {__("STRINGS", "automlp-ai-translation-for-wpml")}
                  </span>
                  <br />
                  <span className={`${prefix}-count-stat-value`}>
                    {countInfo.stringsTranslated}
                  </span>
                </div>
                <div className={`${prefix}-count-stat-cell`}>
                  <span className={`${prefix}-count-stat-label`}>
                    {__("CHARACTERS", "automlp-ai-translation-for-wpml")}
                  </span>
                  <br />
                  <span className={`${prefix}-count-stat-value`}>
                    {countInfo.charactersTranslated}
                  </span>
                </div>
                <div
                  className={`${prefix}-count-stat-cell ${prefix}-count-stat-cell--time`}
                >
                  <span className={`${prefix}-count-stat-label`}>
                    {__("TIME TAKEN", "automlp-ai-translation-for-wpml")}
                  </span>
                  <br />
                  <span className={`${prefix}-count-stat-value`}>
                    {(() => {
                      const time = countInfo.timeTaken ?? 0;
                      if (time > 0 && time < 1) {
                        return `${Math.round(time * 1000)} ${__("ms", "automlp-ai-translation-for-wpml")}`;
                      }
                      return `${Math.round(time)} ${__("seconds", "automlp-ai-translation-for-wpml")}`;
                    })()}
                  </span>
                </div>
              </div>
            )
          )}
          {!isLoading && pendingPosts.length === 0 && (
            <div className={`${prefix}-status-table-container`}>
              <div>
                <div className={`${prefix}-status-table`}>
                  <div className={`${prefix}-status-table-inner`}>
                    {isLoading && null}
                    {!isLoading &&
                      Object.keys(errorPostsInfo).length > 0 &&
                      Object.keys(errorPostsInfo).map((key, index) => {
                        return (
                          <React.Fragment key={key}>
                            <div
                              key={`group-title-${key}`}
                              className={`${prefix}-group-title`}
                            >
                              <div colSpan="5">
                                {errorPostsInfo[key]?.title ||
                                  __(
                                    "Untitled",
                                    "automlp-ai-translation-for-wpml",
                                  )}
                              </div>
                            </div>
                            <div key={key}>
                              <div
                                colSpan="4"
                                style={{ textAlign: "center", width: "100%" }}
                                className={`${prefix}-error-message`}
                                dangerouslySetInnerHTML={{
                                  __html: DOMPurify.sanitize(
                                    errorPostsInfo[key].errorMessage,
                                  ),
                                }}
                              ></div>
                            </div>
                          </React.Fragment>
                        );
                      })}
                    {!isLoading &&
                      Object.keys(translatePostInfo).map((key) => {
                        const info = translatePostInfo[key];
                        const isStringAggregate = key.startsWith("strings_");
                        const isStringDashboardOnly =
                          isStringAggregate &&
                          key.split("_").length === 3 &&
                          key.split("_")[1] === key.split("_")[2];

                        if (!isStringAggregate || isStringDashboardOnly) {
                          return null;
                        }

                        const total = info.total || 0;
                        const completed = info.completed || 0;
                        const pct =
                          total > 0
                            ? Math.min(
                                100,
                                Math.round((100 * completed) / total),
                              )
                            : 0;

                        return (
                          <div
                            key={key}
                            className={`${prefix}-td-${info.status}`}
                          >
                            <div className={`${prefix}-status-flag`}>
                              <div className={`${prefix}-status-flag-inner`}>
                                <div
                                  className={`${prefix}-status-flag-inner-left`}
                                >
                                  {info.flagUrl && (
                                    <img
                                      src={info.flagUrl}
                                      width="20"
                                      alt={info.targetLanguage}
                                    />
                                  )}
                                </div>
                                <div
                                  className={`${prefix}-status-flag-inner-right`}
                                >
                                  {info.languageName || info.targetLanguage}
                                </div>
                              </div>
                            </div>
                            <div className={`${prefix}-status-content`}>
                              <div className={`${prefix}-status-content-inner`}>
                                <span
                                  className={`${prefix}-status ${
                                    info.messageClass || ""
                                  } ${info.status || ""}`}
                                >
                                  {info.status === "completed" &&
                                    __(
                                      "Completed",
                                      "automlp-ai-translation-for-wpml",
                                    )}

                                  {info.status === "pending" &&
                                    __(
                                      "Pending",
                                      "automlp-ai-translation-for-wpml",
                                    )}

                                  {info.status === "error" && (
                                    <>
                                      <button
                                        type="button"
                                        className={`${prefix}-status-error-button`}
                                        onClick={() => handleErrorModal(info)}
                                      >
                                        {__(
                                          "Error Details",
                                          "automlp-ai-translation-for-wpml",
                                        )}
                                      </button>
                                    </>
                                  )}
                                </span>
                              </div>
                              {info.status === "completed"
                                ? `${total} ${__(
                                    "Strings",
                                    "automlp-ai-translation-for-wpml",
                                  )}`
                                : `${completed} / ${total}`}
                            </div>
                          </div>
                        );
                      })}
                  </div>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default StatusModal;
