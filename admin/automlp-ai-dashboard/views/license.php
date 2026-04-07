<?php
if(!defined('ABSPATH')){
    exit;
}
?>
<div class="automlp_ai_dashboard-license">
    <div class="automlp_ai_dashboard-license-container">
    <div class="header">
        <h1><?php esc_html_e('License Key', 'wpml-translation-check'); ?></h1>
        <div class="automlp_ai_dashboard-status">
            <span><?php esc_html_e('Free', 'wpml-translation-check'); ?></span>
            <a href="<?php echo esc_url('https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=license'); ?>" class='automlp_btn' target="_blank">
              <img src="<?php echo esc_url(AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/upgrade-now.svg'); ?>" alt="<?php esc_html_e('Upgrade Now', 'wpml-translation-check'); ?>">
                <?php esc_html_e('Upgrade Now', 'wpml-translation-check'); ?>
            </a>
        </div>
    </div>
    <p><?php esc_html_e('Your license key provides access to pro version updates and support.', 'wpml-translation-check'); ?></p>
    
    <p>
        <?php
        // translators: 1: AutoPoly - AI Translation For Polylang (free) plugin name in strong tag
        echo sprintf(esc_html__('You\'re using %s - no license needed. Enjoy! 😊', 'wpml-translation-check'), '<strong>'.esc_html__('AutoMLP AI Translation For WPML (free)', 'wpml-translation-check').'</strong>'); ?>
    </p>

    <div class="automlp_ai_dashboard-upgrade-box">
        <p>
            <?php esc_html_e('To unlock more features, consider', 'wpml-translation-check'); ?>
            <a href="<?php echo esc_url('https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=license'); ?>" target="_blank"><?php esc_html_e('upgrading to Pro', 'wpml-translation-check'); ?></a>.
        </p>
        <em><?php esc_html_e('As a valued user, you automatically receive an exclusive discount on the Annual License and an even greater discount on the POPULAR Lifetime License at checkout!', 'wpml-translation-check'); ?></em>
    </div>
    </div>
    <?php require_once AUTOMLP_AI_PLUGIN_DIR . $file_prefix . 'footer.php'; ?>
</div>
