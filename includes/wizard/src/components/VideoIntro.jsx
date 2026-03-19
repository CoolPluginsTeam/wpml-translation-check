import React from "react";
import WizardHelpFooter from "./WizardHelpFooter";
import { __ } from "@wordpress/i18n";

const VideoIntro = ({ onGetStarted }) => {
  const data = window.automlp_ai_setup || {};
  const videoUrl =
    data.video_url || "https://www.youtube.com/embed/dst_bf7uiTc";
  const templateImage = data.template_image;
  return (
    <>
      <div
        className="automlp-ai-wizard-card"
        style={{ maxWidth: "600px", padding: "0 40px" }}
      >
        <div style={{ textAlign: "center", marginBottom: 24 }}>
          <h3
            className="automlp-ai-wizard-card-title h3"
            style={{ fontSize: "1.5rem", marginBottom: 12, paddingTop: 24 }}
          >
            {__("Watch Setup Guide Video", "wpml-translation-check")}
          </h3>
          <p
            style={{ color: "#6b7280", marginBottom: 24, textAlign: "center" }}
          >
            {__(
              "Learn how to set up AutoMLP and start translating your pages",
              "wpml-translation-check",
            )}
            <br />
            {__("automatically using AI.", "wpml-translation-check")}
          </p>
        </div>
        <div
          style={{
            position: "relative",
            width: "100%",
            // paddingBottom: "56.25%",
          }}
        >
          {/* <iframe
					title={ __( 'AutoMLP Setup Guide', 'wpml-translation-check' ) }
					style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', borderRadius: 8 }}
					src={ videoUrl }
					frameBorder="0"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowFullScreen
				/> */}

			<a href="https://docs.coolplugins.net/plugin/ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup"
  target="_blank"
  rel="noopener noreferrer"
>
  <img
    src={templateImage}
    alt={__("AutoMLP Setup Guide", "wpml-translation-check")}
    width={600}
    height={337}
    style={{ borderRadius: 8}}
  />
</a>
        </div>
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            padding: "2.5em",
          }}
        >
          <button
            type="button"
            className="button button-primary button-hero"
            onClick={onGetStarted}
          >
            {__("Get Started", "wpml-translation-check")}
          </button>
        </div>
      </div>
      <WizardHelpFooter />
    </>
  );
};

export default VideoIntro;
