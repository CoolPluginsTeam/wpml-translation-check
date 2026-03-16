<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'automlp_admin_notices' ) ) {
    class automlp_admin_notices {
        public function __construct() {
			// register actions
			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'automlp_admin_notice_for_reviews' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'automlp_enqueue_scripts_styles' ) );
				add_action( 'wp_ajax_automlp_dismiss_notice', array( $this, 'automlp_dismiss_notice' ) );
			}
		}

        /**
		 * Enqueue scripts and styles
		 */
        public function automlp_enqueue_scripts_styles() {
            wp_enqueue_script( 'automlp-admin-feedback-notice', AUTOMLP_AI_PLUGIN_URL . '/assets/js/automlp-admin-feedback-notice.js', array( 'jquery' ), AUTOMLP_AI_VERSION, true );
            wp_enqueue_style( 'automlp-admin-feedback-notice', AUTOMLP_AI_PLUGIN_URL . '/assets/css/automlp-admin-feedback-notice.css', array(), AUTOMLP_AI_VERSION );
        }

        public function automlp_dismiss_notice() {
          
            // Check for nonce security
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'automlp_review_notice_nonce' ) ) {
                wp_send_json_error( 'You don\'t have permission to hide notice.' );
                return;
            }
            
            // Verify user capability to dismiss admin notices
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'You don\'t have permission to dismiss admin notices.' );
                return;
            }
           
            update_option( 'automlp_already_rated', 'yes' );
            
            wp_send_json_success( array( 'message' => 'Notice dismissed successfully' ) );
        }

        public function automlp_admin_notice_for_reviews() {
            if(!current_user_can('update_plugins')){
                return;
            }
          // Check if the user has already rated the plugin
          $already_rated = get_option('automlp_already_rated','no');
          if($already_rated === 'yes'){
            return;
          }

         // Get installation date and compare it with the current date
         $installation_date = get_option('automlp_ai_install_date');
         if (empty($installation_date)) {
             return;
         }

         $install_date = new DateTime($installation_date);
         $current_date = new DateTime();
         $diff = $install_date->diff($current_date);
         $diff_days = $diff->days;

         if($diff_days >= 3){
            echo wp_kses_post( $this->automlp_create_notice_content() );
         }
        }

        public function automlp_create_notice_content(){
            $ajax_url           = esc_url( admin_url( 'admin-ajax.php' ) );
            $ajax_callback      = esc_attr( 'automlp_dismiss_notice' );
            $wrap_cls           = esc_attr( 'notice notice-info is-dismissible' );
            $img_path           = esc_url( AUTOMLP_AI_PLUGIN_URL . '/admin/automlp-ai-dashboard/images/automlp-ai-logo.png' );
            $p_name             = esc_html__( 'AutoMLP – AI Translation for WPML', 'wpml-translation-check' );
            $like_it_text       = esc_html__( 'Rate Now! ★★★★★', 'wpml-translation-check' );
            $already_rated_text = esc_html__( 'I already rated it', 'wpml-translation-check' );
            $not_like_it_text   = esc_html__( 'No, not good enough, I do not like to rate it!', 'wpml-translation-check' );
            $not_interested     = esc_html__( 'Not Interested', 'wpml-translation-check' );
            $p_link             = esc_url( 'https://wordpress.org/support/plugin/wpml-translation-check/reviews/#new-post' );
        
            $nonce   = wp_create_nonce( 'automlp_review_notice_nonce' );
            $message = sprintf(
                'Thanks for using <b>%s</b> WordPress plugin. We hope it meets your expectations!<br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href="https://coolplugins.net/?utm_source=lge_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list" target="_blank"><strong>Cool Plugins</strong></a>!',
                $p_name
            );
        
            $html = '<div data-ajax-url="%s" data-ajax-callback="%s" 
                data-nonce="%s" 
                class="cool-feedback-notice-wrapper %s">
                <div class="message_container">%s
                    <div class="callto_action">
                        <ul>
                            <li class="love_it"><a href="%s" class="like_it_btn button button-primary" target="_new" title="%s">%s</a></li>
                            <li class="already_rated"><a href="#" class="already_rated_btn button automlp_dismiss_notice" title="%s">%s</a></li>
                            <li class="already_rated"><a href="#" class="already_rated_btn button automlp_dismiss_notice" title="%s">%s</a></li>
                        </ul>
                        <div class="clrfix"></div>
                    </div>
                </div>
            </div>';
        
            return sprintf(
                $html,
                $ajax_url,
                $ajax_callback,
                $nonce,
                $wrap_cls,
                $message,
                $p_link,
                $like_it_text,
                $like_it_text,
                $already_rated_text,
                $already_rated_text,
                $not_interested,
                $not_interested
            );
        }
} //class end
}
          