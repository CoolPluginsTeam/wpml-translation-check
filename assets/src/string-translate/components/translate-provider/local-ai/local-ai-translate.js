class ChromeAiTranslator {
    // Static method to create an instance of ChromeAiTranslator and return extra data
    static Object = (options) => {
        const selfObject = new this(options);
        return selfObject.extraData();
    };

    // Constructor to initialize the translator with options
    constructor(options) {
        this.textContentObject = options.textContentObject;
        this.onStartTranslationProcess = options.onStartTranslationProcess || (() => { }); // Callback for when translation starts
        this.onComplete = options.onComplete || (() => { }); // Callback for when translation completes
        this.onLanguageError = options.onLanguageError || (() => { }); // Callback for language errors
        this.onBeforeTranslate = options.onBeforeTranslate || (() => { }); // Callback for before translation
        this.onAfterTranslate = options.onAfterTranslate || (() => { }); // Callback for after translation
        this.sourceLanguage = options.sourceLanguage || "en"; // Default source language
        this.targetLanguage = options.targetLanguage || "hi"; // Default target language
        this.sourceLanguageLabel = options.sourceLanguageLabel || "English"; // Default source language label
        this.targetLanguageLabel = options.targetLanguageLabel || "Hindi"; // Default target language label
    }

    // Method to check language support and return relevant data
    extraData = async () => {
        // Check if the language is supported
        const langSupportedStatus = await ChromeAiTranslator.languageSupportedStatus(this.sourceLanguage, this.targetLanguage, this.targetLanguageLabel, this.sourceLanguageLabel);

        if (langSupportedStatus !== true) {
            this.onLanguageError(langSupportedStatus); // Handle language error
            return {}; // Return empty object if language is not supported
        }

        this.defaultLang = this.targetLanguage; // Set default language

        // Return methods for translation control
        return {
            continueTranslation: this.continueTranslation,
            stopTranslation: this.stopTranslation,
            startTranslation: this.startTranslation,
            reInit: this.reInit,
            init: this.init
        };
    }

    static supportedLanguages = ['en', 'es', 'ja', 'ar', 'de', 'bn', 'fr', 'hi', 'it', 'ko', 'nl', 'pl', 'pt', 'ru', 'th', 'tr', 'vi', 'zh', 'zh-hant', 'bg', 'cs', 'da', 'el', 'fi', 'hr', 'hu', 'id', 'iw', 'lt', 'no', 'ro', 'sk', 'sl', 'sv', 'uk', 'kn', 'ta', 'te', 'mr'].map(lang => lang.toLowerCase());

    /**
     * Checks if the specified source and target languages are supported by the Local Translator AI modal.
     * 
     * @param {string} sourceLanguage - The language code for the source language (e.g., "en" for English).
     * @param {string} targetLanguage - The language code for the target language (e.g., "hi" for Hindi).
     * @param {string} targetLanguageLabel - The label for the target language (e.g., "Hindi").
     * @returns {Promise<boolean|jQuery>} - Returns true if the languages are supported, or a jQuery message if not.
     */
    static languageSupportedStatus = async (sourceLanguage, targetLanguage, targetLanguageLabel, sourceLanguageLabel) => {
        const supportedLanguages =  ChromeAiTranslator.supportedLanguages;

        const safeBrowser = window.location.protocol === 'https:';
        const browserContentSecure = window?.isSecureContext;

        // Browser check
        if (!window.hasOwnProperty('chrome') || !navigator.userAgent.includes('Chrome') || navigator.userAgent.includes('Edg')) {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Important Notice:</strong>
                <ol>
                    <li>The Translator API, which leverages Chrome local AI models, is designed specifically for use with the Chrome browser.</li>
                    <li>For comprehensive information about the Translator API, <a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank">click here</a>.</li>
                </ol>
                <p>Please ensure you are using the Chrome browser for optimal performance and compatibility.</p>
            </span>`);
            return { html: message, message: 'Browser not supported', type: 'browser-not-supported' };
        }

        if (!('translation' in self && 'createTranslator' in self.translation) && !('ai' in self && 'translator' in self.ai) && !("Translator" in self && "create" in self.Translator) && !safeBrowser && !browserContentSecure) {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Important Notice:</strong>
                <ol>
                    <li>
                        The Translator API is not functioning due to an insecure connection.
                    </li>
                    <li>
                        Please switch to a secure connection (HTTPS) or add this URL to the list of insecure origins treated as secure by visiting 
                        <span data-clipboard-text="chrome://flags/#unsafely-treat-insecure-origin-as-secure" target="_blank" class="chrome-ai-translator-flags">
                            chrome://flags/#unsafely-treat-insecure-origin-as-secure ${ChromeAiTranslator.svgIcons('copy')}
                        </span>.
                        Click on the URL to copy it, then open a new window and paste this URL to access the settings.
                    </li>
                </ol>
            </span>`);
            return { html: message, message: 'Browser not supported', type: 'browser-not-supported' };
        }

        // Check if the translation API is available
        if (!('translation' in self && 'createTranslator' in self.translation) && !('ai' in self && 'translator' in self.ai) && !("Translator" in self && "create" in self.Translator)) {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Steps to Enable the Translator AI Modal:</h4>
                <ol>
                    <li>Open this URL in a new Chrome tab: <strong><span data-clipboard-text="chrome://flags/#translation-api" target="_blank" class="chrome-ai-translator-flags">chrome://flags/#translation-api ${ChromeAiTranslator.svgIcons('copy')}</span></strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure that the <strong>Experimental translation API</strong> option is set to <strong>Enabled</strong>.</li>
                    <li>Click on the <strong>Save</strong> button to apply the changes.</li>
                    <li>The Translator AI modal should now be enabled and ready for use.</li>
                </ol>
                <p>For more information, please refer to the <a href="https://developer.chrome.com/docs/ai/translator-api" target="_blank">documentation</a>.</p>   
                <p>If the issue persists, please ensure that your browser is up to date and restart your browser.</p>
                <p>If you continue to experience issues after following the above steps, please <a href="https://my.coolplugins.net/account/support-tickets/" target="_blank" rel="noopener">open a support ticket</a> with our team. We are here to help you resolve any problems and ensure a smooth translation experience.</p>
            </span>`);
            return { html: message, message: 'Translation API not available', type: 'translation-api-not-available' };
        }

        // Check if the target language is supported
        if (!supportedLanguages.includes(targetLanguage.toLowerCase())) {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Language Support Information:</strong>
                <ol>
                    <li>The current version of Chrome AI Translator does not support the Target Language <strong>${targetLanguageLabel} (${targetLanguage})</strong></li>
                    <li>To view the list of supported languages, please <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure your Chrome browser is updated to the latest version for optimal performance.</li>
                </ol>
            </span>`);
            return { html: message, message: `Target Language not supported: ${targetLanguageLabel} (${targetLanguage})`, type: 'language-not-supported' };
        }

        // Check if the source language is supported
        if (!supportedLanguages.includes(sourceLanguage.toLowerCase())) {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <strong>Language Support Information:</strong>
                <ol>
                    <li>The current version of Chrome AI Translator does not support the Source Language <strong>${sourceLanguageLabel} (${sourceLanguage})</strong></li>
                    <li>To view the list of supported languages, please <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>Ensure your Chrome browser is updated to the latest version for optimal performance.</li>
                </ol>
            </span>`);
            return { html: message, message: `Source Language not supported: ${sourceLanguageLabel} (${sourceLanguage})`, type: 'language-not-supported' };
        }

        // Check if translation can be performed
        const status = await ChromeAiTranslator.languagePairAvality(sourceLanguage, targetLanguage);

        // Handle case for language pack after download
        if (status === "after-download" || status === "downloadable" || status === "unavailable") {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Installation Instructions for Language Packs:</h4>
                <ol>
                    <li>
                        To proceed, please install the language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong>.
                    </li>
                    <li>
                        After installing the language pack, add this language to your browser's system languages in Chrome settings.<br>
                        Go to <strong>Settings &gt; Languages &gt; Add languages</strong> and add <strong>${targetLanguageLabel}</strong> or <strong>${sourceLanguageLabel}</strong> to your preferred languages list & reload the page.
                    </li>
                    <li>
                        You can install it by visiting the following link: 
                        <strong>
                            <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">
                                chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}
                            </span>
                        </strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.
                    </li>
                    <li>
                        Please check if both your source <strong>(<span style="color:#2271b1">${sourceLanguage}</span>)</strong> and target <strong>(<span style="color:#2271b1">${targetLanguage}</span>)</strong> languages are available in the language packs list.
                    </li>
                    <li>
                        You need to install both language packs for translation to work. You can search for each language by its language code: <strong>${sourceLanguage}</strong> and <strong>${targetLanguage}</strong>.
                    </li>
                    <li>For more help, refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a>.</li>
                </ol>
            </span>`);
            return { html: message, message: `Language pack not installed: ${targetLanguageLabel} (${targetLanguage}) or ${sourceLanguageLabel} (${sourceLanguage})`, type: 'language-pack-not-installed' };
        }

        // Handle case for language pack downloading
        if (status === "downloading") {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Language Pack Download In Progress:</h4>
                <ol>
                    <li>
                        The language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong> is already being downloaded.
                    </li>
                    <li>
                        <strong>You do not need to start the download again.</strong> Please wait for the download to complete. Once finished, the translation feature will become available automatically.
                    </li>
                    <li>
                        You can check the download progress by opening:
                        <strong>
                            <span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">
                                chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}
                            </span>
                        </strong>
                        . Click on the URL to copy it, then open a new window and paste this URL in Chrome to view the status.
                    </li>
                    <li>
                        <strong>What to do next:</strong>
                        <ul style="margin-top: .5em;">
                            <li>Wait for the download to finish. The status will change to <strong>Ready</strong> or <strong>Installed</strong> in the <strong>Language Packs</strong> section.</li>
                            <li>After the language pack is installed, you may need to <strong>reload</strong> or <strong>restart</strong> your browser for the changes to take effect.</li>
                        </ul>
                    </li>
                    <li>
                        For more help, refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a>.
                    </li>
                </ol>
                <div style="text-align: right;">
                    <button onclick="location.reload()" class="automlp-wpml-error-reload-btn">Reload Page</button>
                </div>
            </span>`);
            return { html: message, message: `Language pack downloading please wait or try again...`, type: 'language-pack-downloading' };
        }

        // Handle case for language pack not readily available
        if (status !== 'readily' && status !== 'available') {
            const message = jQuery(`<span style="color: #ff4646; display: inline-block;">
                <h4>Language Pack Installation Required</h4>
                <ol>
                    <li>Please ensure that the language pack for <strong>${targetLanguageLabel} (${targetLanguage})</strong> or <strong>${sourceLanguageLabel} (${sourceLanguage})</strong> is installed and set as a preferred language in your browser.</li>
                    <li>To install the language pack, visit <strong><span data-clipboard-text="chrome://on-device-translation-internals" target="_blank" class="chrome-ai-translator-flags">chrome://on-device-translation-internals ${ChromeAiTranslator.svgIcons('copy')}</span></strong>. Click on the URL to copy it, then open a new window and paste this URL to access the settings.</li>
                    <li>If you encounter any issues, please refer to the <a href="https://developer.chrome.com/docs/ai/translator-api#supported-languages" target="_blank">documentation to check supported languages</a> for further assistance.</li>
                </ol>
            </span>`);
            return { html: message, message: `Language pack missing for ${targetLanguageLabel} (${targetLanguage}) or ${sourceLanguageLabel} (${sourceLanguage})`, type: 'language-pack-missing' };
        }

        return true;
    }

    static languagePairAvality = async (source, target) => {
        if (('translation' in self && 'createTranslator' in self.translation)) {
            const status = await self.translation.canTranslate({
                sourceLanguage: source,
                targetLanguage: target,
            });

            return status;
        } else if (('ai' in self && 'translator' in self.ai)) {
            const translatorCapabilities = await self.ai.translator.capabilities();
            const status = await translatorCapabilities.languagePairAvailable(source, target);

            return status;
        } else if ("Translator" in self && "create" in self.Translator) {

            let status = await self.Translator.availability({
                sourceLanguage: source,
                targetLanguage: target,
            });
        
            if (status !== "available") {
                try {
                    // MUST be triggered by a user gesture (button click)
                    const translator = await self.Translator.create({
                        sourceLanguage: source,
                        targetLanguage: target,
                        monitor(monitor) {
                            monitor.addEventListener("downloadprogress", (e) => {
                                console.log(`Downloaded ${Math.round(e.loaded * 100)}%`);
                            });
                        },
                    });
        
                    // Re-check availability AFTER model creation
                    status = await self.Translator.availability({
                        sourceLanguage: source,
                        targetLanguage: target,
                    });
        
                } catch (err) {
                    console.warn(`Translator init for ${source} to ${target} error:`, err);

                    if(status.includes('Requires a user gesture when availability')){
                        return 'requires-user-gesture';
                    }

                   return status;
                }
            }
        
            return status;
        }

        return false;
    }

    AITranslator = async (targetLanguage) => {
        if (('translation' in self && 'createTranslator' in self.translation)) {
            const translator = await self.translation.createTranslator({
                sourceLanguage: this.sourceLanguage,
                targetLanguage,
            });

            return translator;
        } else if (('ai' in self && 'translator' in self.ai)) {
            const translator = await self.ai.translator.create({
                sourceLanguage: this.sourceLanguage,
                targetLanguage,
            });

            return translator;
        } else if ("Translator" in self && "create" in self.Translator) {
            const translator = await self.Translator.create({
                sourceLanguage: this.sourceLanguage,
                targetLanguage,
            });

            return translator;
        }

        return false;
    }

    // Method to initialize the translation process
    init = async (textContentArray) => {
        this.textContent = textContentArray;
        this.textContentKeys = Object.keys(this.textContent);
        this.translationStart = false; // Flag to indicate if translation has started
        this.completedTranslateIndex = 0;
        this.completedCharacterCount = 0; // Count of characters translated
    };

    // Method to start the translation process
    startTranslationProcess = async () => {
        this.onStartTranslationProcess(); // Call the start translation callback
        const langCode = this.defaultLang; // Get the default language code

        this.translationStart = true; // Set translation start flag

        // Create a translator instance
        this.translator = await this.AITranslator(langCode);

        if (this.textContentKeys.length > 0 && this.textContentKeys.length > this.completedTranslateIndex) {
            await this.stringTranslation(this.completedTranslateIndex);
        }
    };

    // Method to translate a specific string at the given index
    stringTranslation = async (index) => {
        if (!this.translateStatus) return; // Exit if translation is stopped

        let ele = document.createElement('div'); // Get the element to translate
        ele.innerHTML = this.textContent[this.textContentKeys[index]];
        this.onBeforeTranslate(ele); // Call the before translation callback
        const orignalText = ele.innerText;
        let originalString = [];

        if (ele.childNodes.length > 0 && !ele.querySelector('.notranslate')) {
            ele.childNodes.forEach(child => {
                if (
                    child.nodeType === 3 &&
                    child.nodeValue.trim() !== '' &&
                    !/^\d+$/.test(child.nodeValue.trim())
                ) {
                    originalString.push(child);
                } else if (child.classList && !child.classList.contains('notranslate')) {
                    this.filterInnerTextNodes(child, originalString);
                }
            });
        } else if (ele.querySelector('.notranslate')) {
            ele.childNodes.forEach(child => {
                if (
                    child.nodeType === 3 &&
                    child.nodeValue.trim() !== '' &&
                    !/^\d+$/.test(child.nodeValue.trim())
                ) {
                    originalString.push(child);
                } else if (child.classList && !child.classList.contains('notranslate')) {
                    this.filterInnerTextNodes(child, originalString);
                }
            });
        }

        if (originalString.length > 0) {
            await this.stringTranslationBatch(originalString, 0);
        }

        this.completedTranslateIndex = index;
        this.completedCharacterCount += orignalText.length; // Update character count

        this.textContent[this.textContentKeys[index]] = ele.innerText;

        this.onAfterTranslate(this.textContentKeys[index], this.textContent[this.textContentKeys[index]]); // Call the after translation callback

        ele.remove();
        ele = null;

        if (this.textContentKeys.length > this.completedTranslateIndex + 1) {
            await this.stringTranslation(this.completedTranslateIndex + 1);
        }

        if (index === this.textContentKeys.length - 1) {
            this.onComplete({ characterCount: this.completedCharacterCount }); // Call the complete callback
        }
    };

    filterInnerTextNodes = (ele, updatedArray) => {
        const childElements = ele.childNodes;

        if (ele.classList && ele.classList.contains('notranslate')) return;

        childElements.forEach(child => {
            if (child.nodeType === 3 && child.nodeValue.trim() !== '') {
                updatedArray.push(child);
            } else if (child.classList && !child.classList.contains('notranslate')) {
                this.filterInnerTextNodes(child, updatedArray);
            }
        });
    }

    translateChildNodes = async (ele, originalString) => {
        if (ele.childNodes.length > 0 && !ele.querySelector('.notranslate')) {
            ele.childNodes.forEach(child => {
                if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
                    originalString.push(child);
                } else if (child.childNodes.length > 0) {
                    this.translateChildNodes(child, originalString);
                }
            });
        } else if (ele.querySelector('.notranslate')) {
            ele.childNodes.forEach(child => {
                if (child.nodeType === 3 && child.nodeValue.trim() !== '' && !/^\d+$/.test(child.nodeValue.trim())) {
                    originalString.push(child);
                } else if (child.childNodes.length > 0) {
                    this.translateChildNodes(child, originalString);
                }
            });
        }
    }

    stringTranslationBatch = async (originalString, index) => {
        const translatedString = await this.translator.translate(originalString[index].nodeValue); // Translate the string

        if (translatedString && '' !== translatedString) {
            originalString[index].nodeValue = translatedString; // Set the translated string
        }

        if (index < originalString.length - 1) {
            await this.stringTranslationBatch(originalString, index + 1);
        }

        return true;
    }

    // Method to stop the translation process
    stopTranslation = () => {
        this.translateStatus = false; // Set translation status to false
    }

    // Method to reinitialize button events
    reInit = () => {
        this.translateBtnEvents(); // Re-setup button events
    }

    // Method to start translation from the current index
    startTranslation = async () => {
        this.translateStatus = true; // Set translation status to true
        await this.startTranslationProcess(); // Start translation process
    }


    static svgIcons = (iconName) => {
        const Icons = {
            'copy': `<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" height="16px" width="16px" xmlns="http://www.w3.org/2000/svg" fill="#2271b1"><path d="M433.941 65.941l-51.882-51.882A48 48 0 0 0 348.118 0H176c-26.51 0-48 21.49-48 48v48H48c-26.51 0-48 21.49-48 48v320c0 26.51 21.49 48 48 48h224c26.51 0 48-21.49 48-48v-48h80c26.51 0 48-21.49 48-48V99.882a48 48 0 0 0-14.059-33.941zM266 464H54a6 6 0 0 1-6-6V150a6 6 0 0 1 6-6h74v224c0 26.51 21.49 48 48 48h96v42a6 6 0 0 1-6 6zm128-96H182a6 6 0 0 1-6-6V54a6 6 0 0 1 6-6h106v88c0 13.255 10.745 24 24 24h88v202a6 6 0 0 1-6 6zm6-256h-64V48h9.632c1.591 0 3.117.632 4.243 1.757l48.368 48.368a6 6 0 0 1 1.757 4.243V112z"></path></svg>`
        }

        return Icons[iconName] || '';
    }
}

/*
 * Example Usage of the ChromeAiTranslator.init method.
 * This method initializes the Chrome AI Translator with a comprehensive set of configuration options to facilitate the translation process.
 * 
 * Configuration Options:
 * 
 * - mainWrapperSelector: A CSS selector for the main wrapper element that encapsulates all translation-related elements.
 * - btnSelector: A CSS selector for the button that initiates the translation process.
 * - btnClass: A custom class for styling the translation button.
 * - btnText: The text displayed on the translation button.
 * - stringSelector: A CSS selector for the elements that contain the strings intended for translation.
 * - progressBarSelector: A CSS selector for the progress bar element that visually represents the translation progress.
 * - sourceLanguage: The language code representing the source language (e.g., "es" for Spanish).
 * - targetLanguage: The language code representing the target language (e.g., "fr" for French).
 * - onStartTranslationProcess: A callback function that is executed when the translation process begins.
 * - onBeforeTranslate: A callback function that is executed prior to each individual translation.
 * - onAfterTranslate: A callback function that is executed following each translation.
 * - onComplete: A callback function that is executed upon the completion of the translation process.
 * - onLanguageError: A callback function that is executed when a language-related error occurs.
 */

// Example for checking language support status
// ChromeAiTranslator.languageSupportedStatus("en", "fr", "French");

// const chromeAiTranslatorObject = ChromeAiTranslator.Object(
//     {
//         mainWrapperSelector: ".main-wrapper", // CSS selector for the main wrapper element
//         btnSelector: ".translator-container .translator-button", // CSS selector for the translation button
//         btnClass: "Btn_custom_class", // Custom class for button styling
//         btnText: "Translate To French", // Text displayed on the translation button
//         stringSelector: ".translator-body .translation-item", // CSS selector for translation string elements
//         progressBarSelector: ".translator-progress-bar", // CSS selector for the progress bar
//         sourceLanguage: "es", // Language code for the source language
//         targetLanguage: "fr", // Language code for the target language
//         onStartTranslationProcess: () => { console.log("Translation process started."); }, // Callback for translation start
//         onBeforeTranslate: () => { console.log("Before translation."); }, // Callback before each translation
//         onAfterTranslate: () => { console.log("After translation."); }, // Callback after each translation
//         onComplete: () => { console.log("Translation completed."); }, // Callback for completion
//         onLanguageError: () => { console.error("Language error occurred."); } // Callback for language errors
//     }
// );
// chromeAiTranslatorObject.init();

export default ChromeAiTranslator;