<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automl_ai_dashboard-left-section">

	<div class="automl_ai_dashboard-get-started">
		<div class="automl_ai_dashboard-get-started-container">
			<h3><?php echo esc_html__( 'Get Started', 'automl-ai-translation-for-wpml' ); ?></h3>

			<div class="automl_ai_dashboard-get-started-grid">
				<div class="automl_ai_dashboard-get-started-grid-content">
					<h2><?php echo esc_html__( 'Automate the Translation Process :-', 'automl-ai-translation-for-wpml' ); ?></h2>
					<iframe
						title="Automate the Translation Process with AUTOML Ai Translate Addon"
						src="https://www.youtube.com/embed/ecHsOyIL_J4?feature=oembed"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe>
					<ul>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Go to %1$sWPML > Translation Management%2$s in your WordPress dashboard. Choose the content you want to translate.',
									'automl-ai-translation-for-wpml'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Use the %1$sWPML translation dashboard%2$s to select jobs and send them for automatic translation.',
									'automl-ai-translation-for-wpml'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li><?php echo esc_html__( 'Select Google Translate as your translation engine through this addon.', 'automl-ai-translation-for-wpml' ); ?></li>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Click the %1$sTranslate%2$s button. The addon will automatically generate translations using Google.',
									'automl-ai-translation-for-wpml'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Review the translated content, make any manual edits if needed, then click %1$sSave%2$s.',
									'automl-ai-translation-for-wpml'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
					</ul>
				</div>
			</div>

			<div class="automl_ai_dashboard-get-started-grid">
				<div class="automl_ai_dashboard-get-started-grid-content">
					<h2><?php echo esc_html__( 'Preview with Google Website Translator :-', 'automl-ai-translation-for-wpml' ); ?></h2>
					<iframe
						title="Preview Translations with Google Website Translator"
						src="https://www.youtube.com/embed/bmmc-Ynwj8w?feature=oembed"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe>
					<ul>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Enable the %1$sGoogle Website Translator widget%2$s from this addon’s settings.',
									'automl-ai-translation-for-wpml'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li>
							<?php
							echo esc_html__(
									'Use the widget on the front-end to quickly preview how your site looks in different languages.',
									'automl-ai-translation-for-wpml'
								);
							?>
						</li>
						<li><?php echo esc_html__( 'Compare preview translations with saved WPML translations to refine your content.', 'automl-ai-translation-for-wpml' ); ?></li>
						<li>
							<?php
							echo esc_html__(
									'When you are satisfied with the results, update your WPML translation jobs accordingly.',
									'automl-ai-translation-for-wpml'
								);
							?>
						</li>
					</ul>
				</div>
			</div>

		</div>
	</div>

</div>