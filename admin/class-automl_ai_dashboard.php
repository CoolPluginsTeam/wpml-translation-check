<?php
/**
 * Admin Dashboard for AUTOML Ai Translate Addon.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'AUTOML_Ai_Dashboard' ) ) {

	/**
	 * Handles the custom admin dashboard page.
	 */
	class AUTOML_Ai_Dashboard {

		/**
		 * Instance.
		 *
		 * @var AUTOML_Ai_Dashboard|null
		 */
		protected static $instance = null;

		/**
		 * Get singleton instance.
		 *
		 * @return AUTOML_Ai_Dashboard
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
		}

		/**
		 * Suppress WordPress admin notices on the dashboard and wizard pages only.
		 */
		public function suppress_admin_notices() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$no_notice_pages = array( 'automl_ai_dashboard', 'automl_ai_wizard' );
			if ( in_array( $page, $no_notice_pages, true ) ) {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
			}
		}

		/**
		 * Enqueue dashboard CSS/JS only on our page.
		 *
		 * @param string $hook Current admin page hook.
		 */
		public function wpml_auto_enqueue_dashboard_assets( $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			if ( 'automl_ai_dashboard' !== $page ) {
				return;
			}

			// Adjust paths if you place assets elsewhere.
			wp_enqueue_style(
				'automl_ai_dashboard-style',
				AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/css/admin-styles.css',
				array(),
				AUTOML_AI_VERSION
			);

			wp_enqueue_script(
				'automl_ai_dashboard-script',
				AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/js/wpml-auto-data-share-setting.js',
				array( 'jquery' ),
				AUTOML_AI_VERSION,
				true
			);
		}

		/**
		 * Render the main dashboard layout and load tab views.
		 */
		public function automl_ai_render_dashboard_page() {
			// Folder for view files (you will create these files next).
			// Expected:
			// - admin/automl-ai-dashboard/views/dashboard.php
			// - admin/automl-ai-dashboard/views/ai-translations.php
			// - admin/automl-ai-dashboard/views/settings.php
			// - admin/automl-ai-dashboard/views/license.php
			// - admin/automl-ai-dashboard/views/free-vs-pro.php
			// - admin/automl-ai-dashboard/views/sidebar.php
			// - admin/automl-ai-dashboard/views/footer.php
			$file_prefix = 'admin/automl-ai-dashboard/views/';

			$valid_tabs = array(
				'dashboard'       => __( 'Dashboard', 'automl-ai-translation-for-wpml' ),
				'ai-translations' => __( 'AI Translations', 'automl-ai-translation-for-wpml' ),
				'settings'        => __( 'Settings', 'automl-ai-translation-for-wpml' ),
			);

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
			$current_tab = array_key_exists( $tab, $valid_tabs ) ? $tab : 'dashboard';
			?>
			<div class="automl_ai_dashboard-wrapper">
				<div class="automl_ai_dashboard-header">
					<div class="automl_ai_dashboard-header-left">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=automl_ai_dashboard&tab=dashboard' ) ); ?>" class="automl_ai_dashboard-logo-link">
							<img src="<?php echo esc_url( AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/images/automl-ai-logo.png' ); ?>" alt="<?php esc_attr_e( 'WPML Auto Logo', 'automl-ai-translation-for-wpml' ); ?>">
						</a>
						<div>
							<span class="automl_ai_dashboard-logo-text">AutoML</span>
						</div>
						<div class="automl_ai_dashboard-tab-title">
							<span>↳</span> <?php echo esc_html( $valid_tabs[ $current_tab ] ); ?>
						</div>
					</div>
				</div>

				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'automl-ai-translation-for-wpml' ); ?>">
					<?php foreach ( $valid_tabs as $tab_key => $tab_title ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=automl_ai_dashboard&tab=' . $tab_key ) ); ?>"
							class="nav-tab <?php echo esc_attr( $tab === $tab_key ? 'nav-tab-active' : '' ); ?>">
							<?php echo esc_html( $tab_title ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="tab-content">
					<?php
					// Main tab content.
					$view_file = AUTOML_AI_PLUGIN_DIR . $file_prefix . $current_tab . '.php';
					if ( file_exists( $view_file ) ) {
						require $view_file;
					} else {
						echo '<p>' . esc_html__( 'View file not found.', 'automl-ai-translation-for-wpml' ) . '</p>';
					}

					$sidebar_file = AUTOML_AI_PLUGIN_DIR . $file_prefix . 'sidebar.php';
					if ( file_exists( $sidebar_file ) ) {
						require $sidebar_file;
					}
					?>
				</div>

				<?php
				// Footer.
				$footer_file = AUTOML_AI_PLUGIN_DIR . $file_prefix . 'footer.php';
				if ( file_exists( $footer_file ) ) {
					require $footer_file;
				}
				?>
			</div>
			<?php
		}
	}

	// Bootstrap the dashboard class.
	AUTOML_Ai_Dashboard::get_instance();
}