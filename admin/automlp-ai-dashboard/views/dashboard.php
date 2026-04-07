<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$automlp_active_providers = get_option('automlp_enabled_providers', array('google', 'openai'));
?>
<div class="automlp_ai_dashboard-left-section">

	<div class="automlp_ai_dashboard-get-started">
		<div class="automlp_ai_dashboard-get-started-container">
			<div class= "header">
			<h1><?php echo esc_html__( 'Automate the Translation Process', 'wpml-translation-check' ); ?></h1>
			<div class="automlp-dashboard-status">
                        <span><?php echo esc_html__('Free', 'wpml-translation-check'); ?></span>
                        <a href="<?php echo esc_url('https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=dashboard'); ?>" class='automlp-dashboard-btn' target="_blank">
                            <img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/upgrade-now.svg'); ?>" alt="<?php esc_attr_e('Upgrade Now', 'wpml-translation-check'); ?>">
                            <?php echo esc_html__('Upgrade Now', 'wpml-translation-check'); ?>
                        </a>
            </div>
			</div>
			<div class="automlp_ai_dashboard-get-started-grid">
			<div class="automlp_ai_dashboard-get-started-grid-content">
    <h2><?php esc_html_e( 'Translate Pages/Posts', 'wpml-translation-check' ); ?></h2>
    <p>
        <?php
        echo sprintf(
            // translators: %1$s: strong tag, %2$s: strong tag.
            esc_html__(
                'Go to Pages or Posts, select items, click %1$sAI Translate%2$s, choose language and provider (Gemini/OpenAI), then click %1$sTranslate%2$s.',
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
                'Go to %1$sWPML → String Translation%2$s, select strings, click %1$sAI Translate%2$s, choose language and provider, then click %1$sTranslate%2$s.',
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
  <a class="automlp-dashboard-docs" href="<?php echo esc_url('https://docs.coolplugins.net/plugin/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard'); ?>" target="_blank"><img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/document.svg'); ?>" alt="document"> <span><?php echo esc_html__('Read Plugin Docs', 'wpml-translation-check'); ?></span></a>
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
    <div class="automlp-dashboard-translation-providers">
			<h3><?php echo esc_html__('AI Translation Providers', 'wpml-translation-check'); ?></h3>
			<div class="automlp-dashboard-providers-grid">

				<!-- OpenAI Provider Card -->
				<div class="automlp-dashboard-provider-card">
					<div class="automlp-dashboard-provider-header">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/openai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_openai'); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'assets/images/openai-translate-logo.png'); ?>" alt="<?php echo esc_attr__('OpenAI', 'wpml-translation-check'); ?>">
						</a>
						<div class="automlp-provider-switch-container">
							<label class="automlp-provider-switch">
								<input type="checkbox" class="automlp-provider-toggle" data-provider="openai" <?php checked(in_array('openai', $automlp_active_providers), true); ?>/>
								<span class="automlp-switch-slider"></span>
							</label>
						</div>
					</div>
					<ul>
						<li>✅ <?php echo esc_html__('Unlimited Free Translations', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Use Translation Modals', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Bulk Translation', 'wpml-translation-check'); ?></li>
					</ul>
					<div class="automlp-dashboard-provider-buttons">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/openai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_openai'); ?>" class="automlp-dashboard-btn" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Docs', 'wpml-translation-check'); ?></a>
					</div>
				</div>

				<!-- Gemini Provider Card -->
				<div class="automlp-dashboard-provider-card">
					<div class="automlp-dashboard-provider-header">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/gemini-ai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_gemini'); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'assets/images/geminiai-logo.png'); ?>" alt="<?php echo esc_attr__('Gemini', 'wpml-translation-check'); ?>">
						</a>
						<div class="automlp-provider-switch-container">
							<label class="automlp-provider-switch">
								<input type="checkbox" class="automlp-provider-toggle" data-provider="google" <?php checked(in_array('google', $automlp_active_providers), true); ?>/>
								<span class="automlp-switch-slider"></span>
							</label>
						</div>
					</div>
					<ul>
						<li>✅ <?php echo esc_html__('Unlimited Free Translations', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Use Translation Modals', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Bulk Translation', 'wpml-translation-check'); ?></li>
					</ul>
					<div class="automlp-dashboard-provider-buttons">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/gemini-ai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_gemini'); ?>" class="automlp-dashboard-btn" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Docs', 'wpml-translation-check'); ?></a>
					</div>
				</div>

                <!-- Chrome Built-in AI Provider Card -->
				<div class="automlp-dashboard-provider-card automlp-chrome-ai-card">
					<div class="automlp-dashboard-provider-header">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/chrome-ai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_chrome'); ?>" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'assets/images/chrome-built-in-ai-logo.png'); ?>" alt="<?php echo esc_attr__('Chrome Built-in AI', 'wpml-translation-check'); ?>">
						</a>
						<div class="automlp-provider-switch-container" data-provider="chrome-built-in-ai">
							<label class="automlp-provider-switch automlp-pro-provider">
								<input type="checkbox" class="automlp-provider-toggle" data-provider="chrome-built-in-ai" disabled="disabled"/>
								<span class="automlp-switch-slider"></span>
							</label>
						</div>
					</div>
					<ul>
						<li>✅ <?php echo esc_html__('Fast AI Translations in Browser', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Unlimited Free Translations', 'wpml-translation-check'); ?></li>
						<li>✅ <?php echo esc_html__('Bulk Translation (Pro)', 'wpml-translation-check'); ?></li>
					</ul>
					<div class="automlp-dashboard-provider-buttons">
						<a href="<?php echo esc_url('https://docs.coolplugins.net/doc/chrome-ai-translation-wpml/?utm_source=automlp_plugin&amp;utm_medium=inside&amp;utm_campaign=docs&amp;utm_content=dashboard_chrome'); ?>" class="automlp-dashboard-btn" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Docs', 'wpml-translation-check'); ?></a>
						<a class="automlp-chrome-configure-button automlp-dashboard-btn primary" href="<?php echo esc_url(admin_url('admin.php?page=polylang-automlp-dashboard&tab=settings')); ?>" style="display: none;"><?php esc_html_e('Configure', 'wpml-translation-check'); ?></a>
					</div>
				</div>

			</div>
		</div>

</div>