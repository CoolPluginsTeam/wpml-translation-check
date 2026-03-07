import React from 'react';
import { __ } from '@wordpress/i18n';

const VideoIntro = ({ onGetStarted }) => {
	const data = window.wpml_at_setup || {};
	const videoUrl = data.video_url || 'https://www.youtube.com/embed/dst_bf7uiTc';

	return (
		<>
		<div className="automl-ai-wizard-card">
			<div style={{ textAlign: 'center', marginBottom: 24 }}>
				<h3 className="automl-ai-wizard-card-title h3" style={{ fontSize: '1.5rem', marginBottom: 12, paddingTop: 24 }}>
					{ __( 'Watch Setup Guide', 'automl-ai-translation-for-wpml' ) }
				</h3>
				<p style={{ color: '#6b7280', marginBottom: 24, textAlign: 'center' }}>
					{ __( 'Learn how to set up AutoML and start translating your pages', 'automl-ai-translation-for-wpml' ) }
					<br />
					{ __( 'automatically using AI.', 'automl-ai-translation-for-wpml' ) }
				</p>
			</div>
			<div style={{ position: 'relative', width: '100%', paddingBottom: '56.25%', marginBottom: 24 }}>
				<iframe
					title={ __( 'AutoML Setup Guide', 'automl-ai-translation-for-wpml' ) }
					style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', borderRadius: 8 }}
					src={ videoUrl }
					frameBorder="0"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowFullScreen
				/>
			</div>
			<div style={{ display: 'flex', justifyContent: 'center', padding: '1.5em' }}>
				<button type="button" className="button button-primary button-hero" onClick={ onGetStarted }>
					{ __( 'Get Started', 'automl-ai-translation-for-wpml' ) }
				</button>
			</div>
		</div>
		<div className="automl-ai-wizard-card-footer">
			{ __( 'Need help? Visit our', 'automl-ai-translation-for-wpml' ) }{ ' ' }
			<a href={ '#' } target="_blank" rel="noopener noreferrer">
				{ __( 'Documentation', 'automl-ai-translation-for-wpml' ) }
			</a>
		</div>
		</>
	);
};

export default VideoIntro;