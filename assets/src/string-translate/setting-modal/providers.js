import TranslateService from "../components/translate-provider";
import { __ } from "@wordpress/i18n";

const Providers = (props) => {
    const service = props.Service;
    const prefix = props.prefix;
  
    const buttonDisable = props[service + "Disabled"];
    const ActiveService = TranslateService({ Service: service, [service + "ButtonDisabled"]: buttonDisable, openErrorModalHandler: props.openErrorModalHandler, prefix });
  
      const isSelected = props.selectedProvider === service;
      const isDisabled = buttonDisable;
      const handleCardClick = () => {
          if (!isDisabled && props.onSelectProvider) {
              props.onSelectProvider(service);
          }
      };
      return (
          <div
              className={`${prefix}-provider-card ${isDisabled ? `${prefix}-provider-card--disabled` : ""} ${isSelected ? `${prefix}-provider-card--selected` : ""}`}
              data-service={service}
              onClick={handleCardClick}
              onKeyDown={(e) => { if ((e.key === "Enter" || e.key === " ") && !isDisabled) { e.preventDefault(); handleCardClick(); } }}
              role="button"
              tabIndex={isDisabled ? -1 : 0}
              aria-pressed={isSelected}
          >
              <div className={`${prefix}-provider-card-body`}>
              <span className={`${prefix}-provider-card-icon`} aria-hidden="true">
                  <img src={`${props.imgFolder}${ActiveService.Logo}`} alt="" />
              </span>
              <span className={`${prefix}-provider-card-name`}>{ActiveService.title}</span>
              <span className={`${prefix}-provider-card-check`} aria-hidden="true" />
              </div>
              <div className={`${prefix}-provider-card-actions`}>
                  <a href={ActiveService.Docs} target="_blank" rel="noopener noreferrer" className={`${prefix}-provider-card-docs`} title={ActiveService.Docs} onClick={(e) => e.stopPropagation()}>
                      {__('Docs', 'wpml-translation-check')}
                  </a>
                  {isDisabled && (
                      <div className={`${prefix}-provider-card-error`}>{ActiveService.ErrorMessage}</div>
                  )}
              </div>
          </div>
      );
  };

export default Providers;