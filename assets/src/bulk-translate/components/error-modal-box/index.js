import CopyClipboard from "../copy-clipboard";
import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import DOMPurify from 'dompurify';

const ErrorModalBox = ({ message, onClose, Title, prefix, children }) => {

    let dummyElement = jQuery('<div>').append(message);
    const stringifiedMessage = dummyElement.html();
    dummyElement.remove();
    dummyElement = null;

    useEffect(() => {
        const clipboardElements = document.querySelectorAll('.chrome-ai-translator-flags');

        if (clipboardElements.length > 0) {
            clipboardElements.forEach(element => {

                element.classList.add(`${prefix}-tooltip-element`);

                element.addEventListener('click', (e) => {
                    e.preventDefault();
                    const toolTipExists = element.querySelector(`.${prefix}-tooltip`);
                    
                    if(toolTipExists){
                        return;
                    }

                    let toolTipElement = document.createElement('span');
                    toolTipElement.textContent = "Text to be copied.";
                    toolTipElement.className = `${prefix}-tooltip`;
                    element.appendChild(toolTipElement);

                    CopyClipboard({ text: element.getAttribute('data-clipboard-text'), startCopyStatus: () => {
                        toolTipElement.classList.add(`${prefix}-tooltip-active`);
                    }, endCopyStatus: () => {
                        setTimeout(() => {
                            toolTipElement.remove();
                        }, 800);
                    } });
                });
            });

            return () => {
                clipboardElements.forEach(element => {
                    element.removeEventListener('click', () => { });
                });
            };
        }
    }, []);

    return (
        <div className={`${prefix}-error-modal-box-container`}>
            <div className={`${prefix}-error-modal-box`}>
                <div className={`${prefix}-error-modal-box-header`}>
                    {Title && <h3>{Title}</h3>}
                    <button type="button" aria-label={__('Close', 'wpml-translation-check')} className={`${prefix}-error-modal-box-close`} onClick={onClose}>&times;</button>
                </div>
                <div className={`${prefix}-error-modal-box-body`}>
                    <p dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(stringifiedMessage) }} />
                    {children}
                </div>
                <div className={`${prefix}-error-modal-box-footer`}>
                    <button className={`${prefix}-error-modal-box-close button button-primary`} onClick={onClose}>{__('Close', 'wpml-translation-check')}</button>
                </div>
            </div>
        </div>
    );
};

export default ErrorModalBox;
