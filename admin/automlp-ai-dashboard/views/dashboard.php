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
    <h2><?php esc_html_e( 'Translate Pages/Posts', 'wpml-translation-check' ); ?></h2>
    <p>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag.
            esc_html__(
                'Go to Pages or Posts, select items using checkboxes, and click %1$sAI Translate%2$s. Choose the language and AI provider (Gemini or OpenAI), then click %1$sTranslate%2$s. Your content will be translated shortly.',
                'wpml-translation-check'
            ),
            '<strong>',
            '</strong>'
        );
        ?>
    </p>

    <h2><?php esc_html_e( 'String Translation', 'wpml-translation-check' ); ?></h2>
    <p>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag, %3$s: strong tag, %4$s: strong tag.
            esc_html__(
                'Go to %1$sWPML → String Translation%2$s, select the strings, and click %1$sAI Translate%2$s. Choose the language and AI provider, then click %1$sTranslate%2$s to complete the process.',
                'wpml-translation-check'
            ),
            '<strong>',
            '</strong>'
        );
        ?>
    </p>
 <div class="automlp_ai_dashboard-get-started-grid-buttons">
  <button class="automlp_btn secondary" onclick="window.location.href='<?php echo esc_url( admin_url( 'admin.php?page=wpml-string-translation/menu/string-translation.php&automlp_translation' ) ); ?>'">
    <?php esc_html_e( 'String Translation', 'wpml-translation-check' ); ?>
  </button>
  <button class="automlp_btn primary" onclick="window.location.href='<?php echo esc_url( admin_url( 'edit.php?post_type=page&automlp_translation' ) ); ?>'">
    <?php esc_html_e( 'Page Translation', 'wpml-translation-check' ); ?>
  </button>
  </div>
</div>
				<div class="automlp_ai_dashboard-get-started-grid-content">
					<iframe
						title="Automate the Translation Process with AUTOMLP Ai Translate Addon"
						src="https://www.youtube.com/embed/ZcSbNup4efw"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
						referrerpolicy="strict-origin-when-cross-origin"
						allowfullscreen>
					</iframe>
</div>
			</div>
		</div>
	</div>

</div>