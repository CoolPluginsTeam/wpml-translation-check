const RenderLanguage = (props) => {
    const { language, selectedLanguages, setSelectedLanguages, prefix, languageObject, wizardSelectedCode } = props;
    const isDisabled=language !== wizardSelectedCode;
    const isSelected=selectedLanguages.includes(language);

    return (
        <div
            key={language}
            className={`${prefix}-language ${isDisabled ? `${prefix}-language-item--disabled` : ''} ${isSelected ? `${prefix}-language-item--selected` : ''}`}
            title={languageObject[language].name}
            onClick={(e) => {
                if (e.target.closest('input') || e.target.closest('label')) return;
                if (isDisabled) return;
                if (isSelected) setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                else setSelectedLanguages([...selectedLanguages, language]);
            }}
            role="button"
            tabIndex={isDisabled ? -1 : 0}
            onKeyDown={(e) => {
                if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                    e.preventDefault();
                    if (isSelected) setSelectedLanguages(selectedLanguages.filter((l) => l !== language));
                    else setSelectedLanguages([...selectedLanguages, language]);
                }
            }}
        >
            <div className={`${prefix}-language-item`}>
                <input
                    type="checkbox"
                    name="languages"
                    id={language}
                    value={language}
                    onChange={(e) => isDisabled ? null : setSelectedLanguages([...selectedLanguages, language])}
                    disabled={isDisabled}
                    checked={isSelected}
                    className={`${prefix}-language-checkbox-input`}
                />
                <span className={`${prefix}-check-visual`} aria-hidden="true" />
                <label htmlFor={language} className={`${prefix}-language-label`} title={languageObject[language].name}>
                    <img src={languageObject[language].flag} alt={languageObject[language].name} />
                    &nbsp; {languageObject[language].name}
                </label>
            </div>
        </div>
    );
};

export default RenderLanguage;