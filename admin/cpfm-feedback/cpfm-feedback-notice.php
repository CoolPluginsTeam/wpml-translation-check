<?php
if ( ! defined( 'ABSPATH' )) exit;

if ( ! class_exists( 'CPFM_Feedback_Notice' ) ) :

class CPFM_Feedback_Notice {

    private static $registered_notices = [];

    /**
     * Panel-wide chrome text (header, consent copy, buttons) - NOT per
     * category, since one panel renders every registered notice. First
     * registrant's i18n array wins and is never overwritten, matching how
     * title/message already work per-category. Left empty, cpfm_text()
     * falls back to developer English rather than calling __() in here:
     * a shared module that translates on its own behalf binds every host's
     * copy to whichever domain THIS FILE happens to declare, which is wrong
     * for every host except one - see the sibling CPFM_* classes' own
     * "the host passes translated strings" contract.
     *
     * @var array<string, string>
     */
    private static $panel_i18n = [];

    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Wire the instance hooks. Split out of the constructor to match the
     * boot/init pattern the sibling CPFM classes use.
     *
     * @return void
     */
    private function register_hooks() {
        add_action('admin_init', [ $this, 'cpfm_listen_for_external_notice_registration' ]);
        add_action('admin_enqueue_scripts', [ $this, 'cpfm_enqueue_assets' ]);
        add_action('wp_ajax_cpfm_handle_opt_in', [ $this, 'cpfm_handle_opt_in_choice' ]);

        add_action('admin_footer', [ $this, 'cpfm_render_notice_panel' ]);
    }
    
    public static function cpfm_register_notice($key, $args) {
        
        if (!current_user_can('manage_options')) {

            return;
        }

        if (empty(self::$panel_i18n) && isset($args['i18n']) && is_array($args['i18n'])) {
            self::$panel_i18n = $args['i18n'];
        }

        if (!isset(self::$registered_notices[$key])) {
            self::$registered_notices[$key] = wp_parse_args($args, [
                'title'   => '',
                'message' => '',
                'pages'   => [],
                'always_show_on' => [],
            ]);
        } else {
            /*
             * MERGE the page lists instead of keeping only the first registrant's.
             *
             * A consent category is shared by every addon in the family, but the
             * screens it must appear on are not: each plugin owns its own
             * settings page. Freezing the category config on first registration
             * meant whichever plugin happened to load first decided the page
             * list for everyone, and every sibling's settings screen silently
             * lost the opt-in popup - it registered its pages and they were
             * thrown away.
             *
             * Title and message still come from the first registrant: the panel
             * shows one heading for the whole category, so last-writer-wins there
             * would just make the copy depend on plugin load order.
             */
            foreach (['pages', 'always_show_on'] as $cpfm_list) {
                $cpfm_existing = isset(self::$registered_notices[$key][$cpfm_list]) ? (array) self::$registered_notices[$key][$cpfm_list] : [];
                $cpfm_incoming = isset($args[$cpfm_list]) ? (array) $args[$cpfm_list] : [];

                self::$registered_notices[$key][$cpfm_list] = array_values(
                    array_unique(array_merge($cpfm_existing, $cpfm_incoming))
                );
            }
        }
        if(!isset(self::$registered_notices[$key]['plugins'])){
            self::$registered_notices[$key]['plugins'] = array();
        }

        self::$registered_notices[$key]['plugins'][] = $args;
    }
    
    public function cpfm_listen_for_external_notice_registration() {
        

        if (!current_user_can('manage_options')) {

            return;
        }

        /**
         * Allow other plugins to register notices dynamically.
         * Example usage in other plugins:
         * add_action('cpfm_register_notice', function() {
         *     CPFM_Feedback_Notice::cpfm_register_notice('events', [
         *         'title'   => 'Events Plugin Notice',
         *         'message' => 'This is a events dashboard setup notice.',
         *         'pages'   => ['dashboard'],
         *     ]);
         * });
         */
        do_action('cpfm_register_notice');//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }

    public function cpfm_enqueue_assets() {

        if (!current_user_can('manage_options')) {

            return;

        }
       

       $current_page   = isset($_GET['page'])? sanitize_key(wp_unslash($_GET['page'])):''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check, no state change.

        // Early return if not needed. Mirrors cpfm_render_notice_panel()'s own
        // "anything actually unread" check exactly - checking only page
        // membership (the old $allowed_pages list) let this load the panel's
        // CSS/JS on every visit to an eligible page even after the admin had
        // already answered every registered notice there, since that coarser
        // check never looked at the per-notice cpfm_opt_in_choice_* option.
        if (!self::cpfm_has_unread($current_page)) {
            return;
        }
        wp_enqueue_style(
            'cpfm-common-review-style',
            self::cpfm_url( 'css/cpfm-admin-feedback.css' ),
            [],
            self::cpfm_version( 'css/cpfm-admin-feedback.css' )
        );

        wp_enqueue_script(
            'cpfm-common-review-script',
            self::cpfm_url( 'js/cpfm-admin-feedback.js' ),
            ['jquery'],
            self::cpfm_version( 'js/cpfm-admin-feedback.js' ),
            true

        );
        wp_localize_script('cpfm-common-review-script', 'cpfmAdminNotice', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cpfm_dismiss_admin_notice'),
            'autoShowPages' => array_unique(
                array_merge(
                    [],
                    ...array_filter(
                        array_column(self::$registered_notices, 'always_show_on'),
                        function($pages) { return !empty($pages); }
                    )
                )
                    ),
        ]);
    }

    /**
     * URL to a file under this module's own folder (CPFM_URL from the loader).
     *
     * @param string $relative Path relative to admin/cpfm-feedback/.
     * @return string
     */
    private static function cpfm_url( $relative ) {
        return CPFM_URL . ltrim( $relative, '/' );
    }

    /**
     * Cache-busting version: the asset's own mtime, so a vendored copy
     * always advertises its own freshness with no version constant needed.
     *
     * @param string $relative Path relative to admin/cpfm-feedback/.
     * @return string
     */
    private static function cpfm_version( $relative ) {
        $path  = CPFM_DIR . ltrim( $relative, '/' );
        $mtime = file_exists( $path ) ? filemtime( $path ) : false;
        return $mtime ? (string) $mtime : '1.0.0';
    }

    /**
     * Panel chrome text: the first registrant's i18n value, or a
     * developer-English fallback (a visible prompt that a host forgot to
     * supply it, never a silent binding to some other plugin's domain).
     *
     * @param string $text_key Copy key.
     * @param string $default  Fallback.
     * @return string
     */
    private static function cpfm_text($text_key, $default) {
        if (isset(self::$panel_i18n[$text_key]) && is_string(self::$panel_i18n[$text_key]) && '' !== self::$panel_i18n[$text_key]) {
            return self::$panel_i18n[$text_key];
        }
        return $default;
    }

    /**
     * Does this one registered notice have no recorded opt-in choice yet AND
     * match $current_page?
     *
     * Shared by cpfm_has_unread() (the early enqueue check) and
     * cpfm_render_notice_panel() (the actual per-notice render), so the
     * "unread" rule can never drift between the two.
     *
     * @param string               $key          Notice category key.
     * @param array<string, mixed> $notice       Notice config.
     * @param string               $current_page Sanitized $_GET['page'].
     * @return bool
     */
    private static function cpfm_notice_is_unread($key, $notice, $current_page) {
        
        if (get_option("cpfm_opt_in_choice_{$key}") !== false) {
            return false;
        }
        foreach ((array) $notice['pages'] as $match) {
            if ($current_page === $match || strpos($current_page, $match) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when at least one registered notice is currently unread on
     * $current_page.
     *
     * Shared by cpfm_enqueue_assets() (decides whether to load the panel's
     * CSS/JS at all) and cpfm_render_notice_panel() (decides what to actually
     * put in it), so the two can never disagree about whether there is
     * anything to show.
     *
     * @param string $current_page Sanitized $_GET['page'].
     * @return bool
     */
    private static function cpfm_has_unread($current_page) {

        foreach (self::$registered_notices as $key => $notice) {
            if (self::cpfm_notice_is_unread($key, $notice, $current_page)) {
                return true;
            }
        }
        return false;
    }

    public function cpfm_handle_opt_in_choice() {

        if (!current_user_can('manage_options')) {

            wp_send_json_error('Unauthorized access.');
        }

        check_ajax_referer('cpfm_dismiss_admin_notice', 'nonce');

        $category   = isset($_POST['category']) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ): '';
        $opt_in_raw = isset($_POST['opt_in']) ? sanitize_text_field( wp_unslash( $_POST['opt_in'] ) ) : '';
        $opt_in = ($opt_in_raw === 'yes') ? 'yes' : 'no';

        if (!$category || !isset(self::$registered_notices[$category])) {
            wp_send_json_error('Invalid notice category.');
        }

        if(!isset(self::$registered_notices[$category]['plugins'])){
            wp_send_json_error('Invalid notice category plugins.');
        }

        update_option("cpfm_opt_in_choice_{$category}", $opt_in);

        $review_option = get_option("cpfm_opt_in_choice_{$category}");

        foreach (self::$registered_notices[$category]['plugins'] as $notice) {

            $plugin_name = isset($notice['plugin_name']) ? sanitize_key($notice['plugin_name']) : '';

            if (!$plugin_name) {
                continue;
            }

            if ($review_option === 'yes') {
                do_action('cpfm_after_opt_in_' . $plugin_name, $category);//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            } else {
                // Fire an opt-OUT action too, so every registered addon can stop
                // its cron / clear its mirror the moment consent is withdrawn
                // (previously only opt-in was signalled).
                do_action('cpfm_after_opt_out_' . $plugin_name, $category);//phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            }
        }

        wp_send_json_success();
    }


    public function cpfm_render_notice_panel() {
        
        if (!current_user_can('manage_options') || !function_exists('get_current_screen')) { 
            return;
        }

        $screen         = get_current_screen();
        $current_page   = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check, no state change.

       
        $unread_count   = 0;
        $auto_show      = false;
    
        foreach (self::$registered_notices as $notice) {

            if (!empty($notice['always_show_on']) && in_array($current_page, (array) $notice['always_show_on'])) {
                $auto_show = true;
                break;
            }
        }
    
        $output = '';
        $output .= '<div id="cpfNoticePanel" class="notice-panel ect-required-plugin-notice"' . ($auto_show ? ' data-auto-show="true"' : '') . '>';
        $output .= '<div class="notice-panel-header">' . esc_html(self::cpfm_text('panel_title', 'Help Improve Plugins')) . ' <span class="dashicons dashicons-no" id="cpfm_remove_notice"></span></div>';
        $output .= '<div class="notice-panel-content">';
    
        foreach (self::$registered_notices as $key => $notice) {

            if (!self::cpfm_notice_is_unread($key, $notice, $current_page)) {
                continue;
            }
            $unread_count++;
    
            $output .= '<div class="notice-item unread" data-notice-id="' . esc_attr($key) . '">';
            $output .= '<strong>' . esc_html($notice['title']) . '</strong>';
            
            $output .= '<div class="notice-message-with-toggle">';
            $output .= '<p>' . esc_html($notice['message']) . '<a href="#" class="cpf-toggle-extra">' . esc_html(self::cpfm_text('more_info', 'More info')) . '</a></p>';
            $output .= '</div>';

            $output .= '<div class="cpf-extra-info">';
            $output .= '<p>' . esc_html(self::cpfm_text('consent_intro', 'Opt in to receive email updates about security improvements, new features, helpful tutorials, and occasional special offers. We\'ll collect:')) . '</p>';
            $output .= '<ul>';
            $output .= '<li>' . esc_html(self::cpfm_text('consent_item_site', 'Your website home URL and WordPress admin email.')) . '</li>';
            $output .= '<li>' . esc_html(self::cpfm_text('consent_item_compat', 'To check plugin compatibility, we will collect the following: list of active plugins and themes, PHP, MySQL and WordPress versions, memory limit, whether the site is multisite, and the site language. '));
            $output .= esc_url('<a href="https://my.coolplugins.net/terms/usage-tracking/" target="_blank">' . esc_html(self::cpfm_text('consent_link', 'Click here')) . '</a></li>');
            $output .= '</ul>';

            $output .= '</div>';

            $output .= '<div class="notice-actions">';
            $output .= '<button class="button button-primary opt-in-yes" data-category="' . esc_attr($key) . '" id="yes-share-data" value="yes">' . esc_html(self::cpfm_text('yes_label', "Yes, it's OK")) . '</button>';
            $output .= '<button class="button opt-in-no" data-category="' . esc_attr($key) . '" id="no-share-data" value="no">' . esc_html(self::cpfm_text('no_label', 'No, Thanks')) . '</button>';
            $output .= '</div>';
            
            $output .= '</div>';
        }
    
        $output .= '</div>';
        $output .= '</div>'; 
     
        if ($unread_count > 0) {
            echo wp_kses_post($output);
        }
    }
}
new CPFM_Feedback_Notice();

endif;
