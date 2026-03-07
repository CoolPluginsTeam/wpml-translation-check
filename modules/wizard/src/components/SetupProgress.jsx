import React from 'react';
import { __ } from '@wordpress/i18n';
import VideoIntro from './VideoIntro';
import Languages from './Languages';
import AiTranslation from './AiTranslation';

const STEPS = [
	{ key: 'video_intro', label: __( 'Intro', 'automl-ai-translation-for-wpml' ) },
	{ key: 'languages', label: __( 'Languages', 'automl-ai-translation-for-wpml' ) },
	{ key: 'ai_translation', label: __( 'AI Translation', 'automl-ai-translation-for-wpml' ) },
];

const SetupProgress = ({ currentStep, setCurrentStep, onGetStarted, onFinish }) => {
	const currentIndex = STEPS.findIndex( ( s ) => s.key === currentStep );

	return (
		<>
			<div className="automl-ai-wizard-steps">
				{ STEPS.map( ( step, index ) => {
					const isActive = step.key === currentStep;
					const isPast = index < currentIndex;
					const circleClass = isActive ? 'active' : isPast ? 'past' : 'inactive';
					const labelClass = isActive ? 'active' : isPast ? 'past' : 'inactive';

					return (
						<React.Fragment key={ step.key }>
							<div className="automl-ai-wizard-step-item">
								<span className={ `automl-ai-wizard-step-circle ${ circleClass }` }>
									{ isPast ? '✓' : index + 1 }
								</span>
								<span className={ `automl-ai-wizard-step-label ${ labelClass }` }>
									{ step.label }
								</span>
							</div>

							{ index < STEPS.length - 1 && (
								<div
									className={ `automl-ai-wizard-step-connector${ currentIndex > index ? ' automl-ai-wizard-step-connector--filled' : '' }` }
									aria-hidden="true"
								/>
							) }
						</React.Fragment>
					);
				} ) }
			</div>
				{ currentStep === 'video_intro' && (
					<VideoIntro onGetStarted={ onGetStarted } />
				) }

				{ currentStep === 'languages' && (
					<Languages
						onBack={ () => setCurrentStep( 'video_intro' ) }
						onContinue={ () => setCurrentStep( 'ai_translation' ) }
					/>
				) }

				{ currentStep === 'ai_translation' && (
					<AiTranslation
						onBack={ () => setCurrentStep( 'languages' ) }
						onContinue={ onFinish }
					/>
				) }
		</>
	);
};

export default SetupProgress;