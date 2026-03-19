<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automlp_ai_dashboard-left-section">

	<div class="automlp_ai_dashboard-get-started">
		<div class="automlp_ai_dashboard-get-started-container">
			<h3><?php echo esc_html__( 'Automate the Translation Process', 'wpml-translation-check' ); ?></h3>

			<div class="automlp_ai_dashboard-get-started-grid">
				<div class="automlp_ai_dashboard-get-started-grid-content">
					<!-- <iframe
						title="Automate the Translation Process with AUTOMLP Ai Translate Addon"
						src="https://www.youtube.com/embed/ecHsOyIL_J4?feature=oembed"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe> -->
					<a href="https://docs.coolplugins.net/plugin/ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=setup" target="_blank" rel="noopener noreferrer">
					<img
						src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . '/assets/images/Install & Setup AutoMLP.png' ); ?>"
						alt="<?php esc_attr_e( 'Translate Pages/Posts', 'wpml-translation-check' ); ?>"
						width="100%"
						height="337"
						style="border-radius: 8px; display: block;"
					/>
				</a>
					<h2><?php echo esc_html__( 'Translate Pages/Posts :-', 'wpml-translation-check' ); ?></h2>
					<p><?php esc_html_e( 'Follow the steps below to automatically translate your webpage content using Gemini AI or OpenAI:', 'wpml-translation-check' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Navigate to the Pages or Posts section from your WordPress dashboard.', 'wpml-translation-check' ); ?></li>
						<li><?php esc_html_e( 'Use the checkboxes to select one or multiple pages/posts you want to translate.', 'wpml-translation-check' ); ?></li>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Once selected, click the %1$sAI Translate%2$s button.',
									'wpml-translation-check'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Pop-up will open, select the language for translation.', 'wpml-translation-check' ); ?></li>
						<li><?php esc_html_e( 'Next, choose your preferred AI translation provider (Gemini AI or OpenAI).', 'wpml-translation-check' ); ?></li>
						<li>
							<?php
							echo sprintf(
								// translators: %1$s: strong tag, %2$s: strong tag.
								esc_html__(
									'Click the %1$sTranslate%2$s button.',
									'wpml-translation-check'
								),
								'<strong>',
								'</strong>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'The translation process will begin.', 'wpml-translation-check' ); ?></li>
						<li><?php esc_html_e( 'That\'s it, after a few minutes your pages will be translated into the selected language.', 'wpml-translation-check' ); ?></li>
					</ul>

<h2><?php esc_html_e( 'String Translation', 'wpml-translation-check' ); ?></h2>
<p><?php esc_html_e( 'Follow the steps below to automatically translate your theme or plugin strings via AI:', 'wpml-translation-check' ); ?></p>
<ul>
    <li>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag.
            esc_html__(
                'From your WordPress dashboard, navigate to %1$sWPML > String Translation%2$s.',
                'wpml-translation-check'
            ),
            '<strong>',
            '</strong>'
        );
        ?>
    </li>
    <li><?php esc_html_e( 'Select one or more strings you want to translate using checkboxes.', 'wpml-translation-check' ); ?></li>
    <li>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag.
            esc_html__(
                'Next, click the %1$sAI Translate%2$s button at the top.',
                'wpml-translation-check'
            ),
            '<strong>',
            '</strong>'
        );
        ?>
    </li>
    <li><?php esc_html_e( 'The pop-up window will open, select the language for translation.', 'wpml-translation-check' ); ?></li>
    <li><?php esc_html_e( 'Choose your preferred AI provider (Gemini AI or OpenAI).', 'wpml-translation-check' ); ?></li>
    <li>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag.
            esc_html__(
                'Click the %1$sTranslate%2$s button, the translation process will begin.',
                'wpml-translation-check'
            ),
            '<strong>',
            '</strong>'
        );
        ?>
    </li>
    <li><?php esc_html_e( 'That\'s it, after a few minutes your strings will be translated automatically into the selected language.', 'wpml-translation-check' ); ?></li>
</ul>
				</div>
			</div>
		</div>
	</div>

</div>