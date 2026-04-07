<?php
/**
 * Admin Dashboard for AUTOMLP Ai Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AUTOMLP_Ai_Dashboard' ) ) {

	/**
	 * Handles the custom admin dashboard page.
	 */
	class AUTOMLP_Ai_Dashboard {

		/**
		 * Instance.
		 *
		 * @var AUTOMLP_Ai_Dashboard|null
		 */
		protected static $instance = null;

		/**
		 * Get singleton instance.
		 *
		 * @return AUTOMLP_Ai_Dashboard
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'wpml_auto_enqueue_dashboard_assets' ) );
			add_action( 'admin_init', array( $this, 'suppress_admin_notices' ), 9999 );
			add_action( 'wp_ajax_automlp_update_enabled_providers', array( $this, 'update_enabled_providers' ) );
		}

		/**
		 * Suppress WordPress admin notices on the dashboard and wizard pages only.
		 */
		public function suppress_admin_notices() {
			global $pagenow;
		
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		
			// Hide ALL notices on wizard.
			if ( 'automlp_ai_wizard' === $page ) {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
				return;
			}
		
			// On dashboard, keep only THIS plugin's notices.
			if ( 'automlp_ai_dashboard' === $page ) {
				$this->remove_third_party_admin_notices();
			}

			$is_plugins_screen = ( 'plugins.php' === $pagenow );
		
			$is_posts_or_pages = (
				( 'edit.php' === $pagenow && in_array( $post_type, array( 'post', 'page' ), true ) ) ||
				( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) )
			);
		
			$is_wpml_page = ( 0 === strpos( $page, WPML_PLUGIN_FOLDER ) );
		
			$is_our_dashboard = ( 'automlp_ai_dashboard' === $page );
		
			$is_allowed = $is_plugins_screen || $is_posts_or_pages || $is_wpml_page || $is_our_dashboard;
		
			if ( ! $is_allowed ) {
				return;
			}

			add_action('admin_notices',function(){
				do_action('automlp_ai_admin_notices');
			});

		}

		private function remove_third_party_admin_notices() {
			global $wp_filter;
		
			$keep_dir = wp_normalize_path( AUTOMLP_AI_PLUGIN_DIR );
			$hooks    = array( 'admin_notices', 'all_admin_notices' );
		
			foreach ( $hooks as $hook ) {
				if ( empty( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
					continue;
				}
		
				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb ) {
						$fn   = $cb['function'];
						$file = null;
		
						try {
							if ( $fn instanceof Closure ) {
								$file = ( new ReflectionFunction( $fn ) )->getFileName();
							} elseif ( is_array( $fn ) && isset( $fn[0], $fn[1] ) ) {
								$file = ( new ReflectionMethod( $fn[0], $fn[1] ) )->getFileName();
							} elseif ( is_string( $fn ) && strpos( $fn, '::' ) !== false ) {
								$file = ( new ReflectionMethod( $fn ) )->getFileName();
							} elseif ( is_string( $fn ) && function_exists( $fn ) ) {
								$file = ( new ReflectionFunction( $fn ) )->getFileName();
							}
						} catch ( Exception $e ) {
							$file = null;
						}
		
						$file_norm = $file ? wp_normalize_path( $file ) : '';
						$is_ours   = ( $file_norm && strpos( $file_norm, $keep_dir ) === 0 );
		
						// If we can't resolve file OR it isn't our plugin, remove it.
						if ( ! $is_ours ) {
							remove_action( $hook, $fn, $priority );
						}
					}
				}
			}
		}

		public function update_enabled_providers() {

            if ( ! check_ajax_referer( 'automlp_update_enabled_providers', 'update_providers_key', false ) ) {
                wp_send_json_error( __( 'Invalid security token sent.', 'wpml-translation-check' ) );
                wp_die( '0', 400 );
                exit();
            }

			$enabled_providers = isset($_POST['enabled_providers']) ? sanitize_text_field(wp_unslash($_POST['enabled_providers'])) : '';
			if(json_last_error() !== JSON_ERROR_NONE){
				wp_send_json_error( __( 'Invalid JSON.', 'wpml-translation-check' ) );
				wp_die( '0', 400 );
				exit();
			}
			$enabled_providers = json_decode($enabled_providers, true);
			if(!is_array($enabled_providers)){
				wp_send_json_error( __( 'Invalid enabled providers.', 'wpml-translation-check' ) );
				wp_die( '0', 400 );
				exit();
			}
			
			$valid_providers = array('openai', 'google');

			$updated_providers = array();

			foreach($enabled_providers as $provider_key => $status){
				if(in_array($provider_key, $valid_providers) && $status === true){
					$updated_providers[] = sanitize_text_field($provider_key);
				}
			}

			update_option('automlp_enabled_providers', $updated_providers);
			wp_send_json_success( array( 'providers' => $updated_providers, 'message' => __( 'Enabled providers updated successfully.', 'wpml-translation-check' ) ) );
			exit;
        }

		/**
		 * Enqueue dashboard CSS/JS only on our page.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function wpml_auto_enqueue_dashboard_assets( $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
			$buy_pro_url = 'https://coolplugins.net/product/automlp-ai-translation-for-wpml/';
			if ( 'automlp_ai_dashboard' !== $page ) {
				return;
			}

			// Adjust paths if you place assets elsewhere.
			wp_enqueue_style(
				'automlp_ai_dashboard-style',
				AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/css/admin-styles.css',
				array(),
				AUTOMLP_AI_VERSION
			);

			wp_enqueue_script(
				'automlp_ai_dashboard-script',
				AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/js/wpml-auto-data-share-setting.js',
				array( 'jquery' ),
				AUTOMLP_AI_VERSION,
				true
			);
			if(empty($active_tab) || $active_tab === 'dashboard'){
				$dashboard_data=array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('automlp_update_enabled_providers'),
					'buy_pro_url' => $buy_pro_url,
					'dashboard_url' => admin_url('admin.php?page=automlp_ai_dashboard&tab=dashboard')
				);
				
				wp_localize_script('automlp_ai_dashboard-script', 'automlpSettingsScriptData', $dashboard_data);
			}
		}

		/**
		 * Render the main dashboard layout and load tab views.
		 */
		public function automlp_ai_render_dashboard_page() {
			// Folder for view files (you will create these files next).
			// Expected:
			// - admin/automlp-ai-dashboard/views/dashboard.php
			// - admin/automlp-ai-dashboard/views/settings.php
			// - admin/automlp-ai-dashboard/views/license.php
			// - admin/automlp-ai-dashboard/views/free-vs-pro.php
			// - admin/automlp-ai-dashboard/views/sidebar.php
			// - admin/automlp-ai-dashboard/views/footer.php
			$file_prefix = 'admin/automlp-ai-dashboard/views/';

			$valid_tabs = array(
				'dashboard'       => __( 'Dashboard', 'wpml-translation-check' ),
				'settings'        => __( 'Settings', 'wpml-translation-check' ),
				'license'         => __( 'License', 'wpml-translation-check' ),
				'free-vs-pro'     => __( 'Free vs Pro', 'wpml-translation-check' ),
			);

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
			$current_tab = array_key_exists( $tab, $valid_tabs ) ? $tab : 'dashboard';
			?>
			<div class="automlp_ai_dashboard-wrapper">
				<div class="automlp_header">
					<div class="automlp_header-left">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=automlp_ai_dashboard&tab=dashboard' ) ); ?>" class="automlp_header-logo-link">
							<img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/automlp-ai-logo.png' ); ?>" alt="<?php esc_attr_e( 'WPML Auto Logo', 'wpml-translation-check' ); ?>">
						</a>
						<div class="automlp_header-logo-text">AutoMLP</div>
						<div class="automlp_header-title">
							↳ <?php echo esc_html( $valid_tabs[ $current_tab ] ); ?>
						</div>
					</div>
					<div class="automlp_header-right">
						<span>AutoMLP – AI Translation for WPML</span>
						<a href="https://coolplugins.net/product/automlp-ai-translation-for-wpml/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=get_pro&utm_content=dashboard_header" class="automlp_btn" target="_blank" aria-label="premium">
							 <img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/upgrade-now.svg' ); ?>" alt="<?php esc_attr_e( 'Premium Icon', 'wpml-translation-check' ); ?>">
							 Unlock More Features
						</a>
					</div>
				</div>

				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wpml-translation-check' ); ?>">
					<?php foreach ( $valid_tabs as $tab_key => $tab_title ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=automlp_ai_dashboard&tab=' . $tab_key ) ); ?>"
							class="nav-tab <?php echo esc_attr( $tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
							<?php echo esc_html( $tab_title ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="tab-content">
					<?php
					// Main tab content.
					$view_file = AUTOMLP_AI_PLUGIN_DIR . $file_prefix . $current_tab . '.php';
					if ( file_exists( $view_file ) ) {
						require $view_file;
					} else {
						echo '<p>' . esc_html__( 'View file not found.', 'wpml-translation-check' ) . '</p>';
					}

					$sidebar_file = AUTOMLP_AI_PLUGIN_DIR . $file_prefix . 'sidebar.php';
					if ( file_exists( $sidebar_file ) ) {
						require $sidebar_file;
					}
					?>
				</div>

				<?php
				// Footer.
				$footer_file = AUTOMLP_AI_PLUGIN_DIR . $file_prefix . 'footer.php';
				if ( file_exists( $footer_file ) ) {
					require $footer_file;
				}
				?>
			</div>
			<?php
		}
	}

	// Bootstrap the dashboard class.
	AUTOMLP_Ai_Dashboard::get_instance();
}