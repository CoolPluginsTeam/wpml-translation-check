<?php
if(!defined('ABSPATH')){
    exit;
}
?>
<div class="automlp_ai_dashboard-free-vs-pro">
    <div class="automlp_ai_dashboard-free-vs-pro-container">
    <div class="header">
        <h1><?php esc_html_e('Free VS Pro', 'wpml-translation-check'); ?></h1>
        <div class="automlp_ai_dashboard-status">
            <span class="status"><?php esc_html_e('Inactive', 'wpml-translation-check'); ?></span>
            <a href="<?php echo esc_url('https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=freevspro'); ?>" class='automlp_btn' target="_blank">
              <img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/upgrade-now.svg'); ?>" alt="<?php echo esc_attr_e('Upgrade Now', 'wpml-translation-check'); ?>">
                <?php echo esc_html_e('Upgrade Now', 'wpml-translation-check'); ?>
            </a>
        </div>
    </div>
    
    <p><?php echo esc_html(__('Compare the Free and Pro versions to choose the best option for your translation needs.', 'wpml-translation-check')); ?></p>

    <table>
        <thead>
            <tr>
                <th><?php echo esc_html(__('Dynamic Content', 'wpml-translation-check')); ?></th>
                <th><?php echo esc_html(__('Free', 'wpml-translation-check')); ?></th>
                <th><?php echo esc_html(__('Pro', 'wpml-translation-check')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
                $automlp_features = [
                    'Chrome Built-in AI Support' => [false, true],
                    'No API Key Required' => [false, true],
                    'Unlimited Translations' => [true, true],
                    'AI Translator (Gemini/OpenAI) Support' => [true, true],
                    'Custom Post Type Translation' => [false, true],
                    'Bulk Translation' => [true, true],
                    'Divi Translation' => [false, true],
                    'Elementor Template Translation' => [false, true],
                    'Premium Support' => [false, true],
                ];
             foreach ($automlp_features as $automlp_feature => $automlp_availability): ?>
                <tr>
                    <td><?php echo esc_html($automlp_feature); ?></td>
                    <td class="<?php echo $automlp_availability[0] ? 'check' : 'cross'; ?>">
                        <?php echo $automlp_availability[0] ? '✓' : '✗'; ?>
                    </td>
                    <td class="<?php echo $automlp_availability[1] ? 'check' : 'cross'; ?>">
                        <?php echo $automlp_availability[1] ? '✓' : '✗'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>