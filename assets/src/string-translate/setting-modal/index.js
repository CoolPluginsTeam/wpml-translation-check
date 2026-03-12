import React, { useState } from "react";
import ReactDOM from "react-dom/client";
import SettingModalHeader from "./header";
import SettingModalBody from "./body";
import SettingModalFooter from "./footer";
import { __ } from "@wordpress/i18n";
import ErrorModalBox from "../components/error-modal-box";

const SettingModal = (props) => {
    const prefix=props.prefix || 'automlp-wpml-bulk-translate';
    const imgFolder = automlp_wpml_bulk_translate_object.automlp_wpml_url + 'assets/images/';
    const [errorModal, setErrorModal] = useState(false);
    const [selectedProvider, setSelectedProvider] = useState(null);

    const onSelectProvider = (service) => {
        setSelectedProvider(service);
    };

    const onStartTranslation = () => {
        if (selectedProvider) {
            props.updateProviderHandler(selectedProvider);
        }
    };

    const errorModalHandler = (msg) => {
        setErrorModal(msg);
    }

    const closeErrorModal = () => {
        setErrorModal(false);
    }

    return (
        <>
            {errorModal ? <ErrorModalBox message={errorModal} onDestroy={props.onDestory} onClose={closeErrorModal} Title='AutoPoly - AI Translation For Polylang (Pro)' prefix={prefix} /> :
            <div id={`${prefix}-setting-modal-container`}>
                <div className={`${prefix}-setting-modal-content`}>
                    <SettingModalHeader
                        setSettingVisibility={props.onDestory}
                        prefix={prefix}
                    />
                                        <SettingModalBody
                        imgFolder={imgFolder}
                        prefix={prefix}
                        localAiModalError={props.localAiModalError}
                        errorModalHandler={errorModalHandler}
                        selectedProvider={selectedProvider}
                        onSelectProvider={onSelectProvider}
                    />
                    <SettingModalFooter
                        setSettingVisibility={props.onCloseHandler}
                        prefix={prefix}
                        selectedProvider={selectedProvider}
                        onStartTranslation={onStartTranslation}
                    />
                </div>
            </div>}
        </>
    );
};

export default SettingModal;