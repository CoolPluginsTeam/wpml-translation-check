<?php
/**
 * Plugin Name: AutoMLP – AI Translation for WPML
 * Description: AutoMLP – AI translation addon for WPML that helps translate WordPress pages and posts content faster and more accurately.
 * Version: 1.0.0
 * Author: Cool Plugins
 * Text Domain: wpml-translation-check
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WPML_Auto_Translate
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'AUTOMLP_AI_VERSION' ) ) {
	define( 'AUTOMLP_AI_VERSION', '1.0.0' );
}
if ( ! defined( 'AUTOMLP_AI_PLUGIN_DIR' ) ) {
	define( 'AUTOMLP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AUTOMLP_AI_PLUGIN_URL' ) ) {
	define( 'AUTOMLP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'AUTOMLP_AI_PLUGIN_BASENAME' ) ) {
	define( 'AUTOMLP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

use AUTOMLP_WPML\Includes\Routes\Bulk_Translation_Route;


/**
 * Main plugin class.
 */
final class AUTOMLP_Ai_Translate_Addon {

	/**
	 * Plugin instance.
	 *
	 * @var AUTOMLP_Ai_Translate_Addon
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return AUTOMLP_Ai_Translate_Addon
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
	public function __construct() {
		$this->load_dependencies();
		$this->init();
		add_action( 'init', array($this, 'register_ai_client') );
		add_filter(
			'plugin_action_links_' . AUTOMLP_AI_PLUGIN_BASENAME,
			array( $this, 'add_settings_action_link' )
		);
	
		add_action( 'admin_init', array( $this, 'register_ai_model_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_automlp_ai_dashboard_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'hide_wp_ai_client_menu' ), 99 );
	}

	public function register_ai_client() {
		$is_wp70 = function_exists( 'wp_ai_client_prompt' );
		if ( ! $is_wp70 ) {
			$sdk_autoload = AUTOMLP_AI_PLUGIN_DIR . 'vendor/wordpress/wp-ai-client/autoload.php';
			if ( file_exists( $sdk_autoload ) ) {
				require_once $sdk_autoload;
			}
			if ( ! class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
				return;
			}
		}else{
			if(!get_option('automlp_ai_credentials_migrated_to_wp70')){
				$credentials = get_option('wp_ai_client_provider_credentials', array());
				if ( ! is_array( $credentials ) ) {
					$credentials = array();
				}
				
				$allowed_providers = array('openai', 'google');
				$providers = array_intersect($allowed_providers, array_keys($credentials));
				foreach ($providers as $provider) {
				 update_option('connectors_ai_'.$provider.'_api_key', $credentials[$provider]);	
				}
				update_option( 'automlp_ai_credentials_migrated_to_wp70', true );
				}
		}
		
	
		$providers_autoload = AUTOMLP_AI_PLUGIN_DIR . 'ai-providers/vendor/autoload.php';
		if ( file_exists( $providers_autoload ) ) {
			require_once $providers_autoload;
		}
	
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();
		if ( class_exists( 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider' )
			&& ! $registry->hasProvider( 'openai' )
		) {
			$registry->registerProvider( \WordPress\OpenAiAiProvider\Provider\OpenAiProvider::class );
		}
		if ( class_exists( 'WordPress\GoogleAiProvider\Provider\GoogleProvider' )
			&& ! $registry->hasProvider( 'google' )
		) {
			$registry->registerProvider( \WordPress\GoogleAiProvider\Provider\GoogleProvider::class );
		}
	
		if ( ! $is_wp70 ) {
			\WordPress\AI_Client\AI_Client::init();
	
			try {
				$http_transporter = \WordPress\AiClient\Providers\Http\HttpTransporterFactory::createTransporter();
				$registry->setHttpTransporter( $http_transporter );
			} catch ( \Exception $e ) {
			}
		}
	}


	public function register_ai_model_setting() {
		register_setting(
			'wp-ai-client-settings',              // option group (matches settings_fields in settings.php)
			'automlp_ai_translation_models',      // option name
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$out = array();
					foreach ( $value as $provider_id => $model_id ) {
						if ( is_string( $provider_id ) && is_string( $model_id ) && $provider_id !== '' && $model_id !== '' ) {
							$out[ sanitize_key( $provider_id ) ] = sanitize_text_field( $model_id );
						}
					}
					return $out;
				},
			)
		);
	}

		/**
	 * Add Settings link on Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_settings_action_link( $links ) {
		// Only show settings link if wizard setup is complete
		if ( ! get_option( 'automlp_ai_setup_complete', false ) ) {
			return $links;
		}

		$url  = add_query_arg(
			array(
				'page' => 'automlp_ai_dashboard',
				'tab'  => 'settings',
			),
			admin_url( 'admin.php' )
		);

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Settings', 'wpml-translation-check' )
		);

		array_unshift( $links, $settings_link ); // Put Settings first (before Deactivate).
		return $links;
	}

	public function register_automlp_ai_dashboard_menu() {
		if ( ! get_option( 'automlp_ai_setup_complete' ) ) {
			return;
		}
		global $menu;
	
		// Fallback parent slug if we can't detect WPML explicitly.
		$parent_slug = 'sitepress-multilingual-cms/menu/languages.php';
	
		// Try to find the actual WPML top-level slug from $menu.
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				// $item[0] is menu title, $item[2] is menu slug.
				if ( isset( $item[0], $item[2] ) && stripos( $item[0], 'WPML' ) !== false ) {
					$parent_slug = $item[2];
					break;
				}
			}
		}
	
		add_submenu_page(
			$parent_slug, // parent (WPML) menu slug
			__( 'AUTOMLP AI Translate', 'wpml-translation-check' ), // page title
			__( 'AutoMLP AI', 'wpml-translation-check' ),      // menu title
			'manage_options',       	                                  // capability
			'automlp_ai_dashboard',                                    // menu slug
			array( \AUTOMLP_Ai_Dashboard::get_instance(), 'automlp_ai_render_dashboard_page' ) // callback
		);
	}


	public function hide_wp_ai_client_menu() {
		// Remove "AI Credentials" submenu under Settings.
		remove_submenu_page( 'options-general.php', 'wp-ai-client' );
	
		// In case wp-ai-client ever added a top-level menu (defensive).
		remove_menu_page( 'wp-ai-client' );
	}
	
	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			'helper/helper.php',
			'helper/sanitized-content.php',
			'includes/wpml/builder/gutenberg/update-block-config.php',
			'includes/wpml/get-package-content.php',
			'includes/wpml/builder/content-update-base.php',
			'includes/wpml/builder/elementor/elementor-update.php',
			'includes/wpml/builder/gutenberg/gutenberg-update.php',
			'includes/wpml/create-translated-post.php',
			'includes/bulk-translation/bulk-translation.php',
			'includes/string-translation/string-translation.php',
			'includes/bulk-translation/register-assets.php',
			'includes/string-translation/register-assets.php',
			'includes/class-automlp-ai-helper.php',
			'includes/class-automlp-ai-strings-ajax.php',
			'includes/class-automlp-ai-update-translate-data-ajax.php',
			'includes/routes/bulk-translation-route.php',
			'admin/class-automlp_ai_dashboard.php',
			'admin/cpt_dashboard/cpt_dashboard.php',
			'modules/wizard/load.php',
		);

		foreach ( $files as $file ) {
			$file_path = AUTOMLP_AI_PLUGIN_DIR . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			}
		}
	}
	
  
	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	private function init() {
		// Check if WPML is active.
		if ( ! $this->is_wpml_active() ) {
			add_action( 'admin_notices', array( $this, 'wpml_missing_notice' ) );
			return;
		}

		// Initialize AJAX handlers.
		if ( class_exists( AUTOMLP_AI_Strings_Ajax::class ) ) {
			AUTOMLP_AI_Strings_Ajax::init();
		}
		if ( class_exists( 'AUTOMLP_AI_Update_Translate_Data_Ajax' ) ) {
			AUTOMLP_AI_Update_Translate_Data_Ajax::init();
		}
		if ( class_exists( Bulk_Translation_Route::class ) ) {
			new Bulk_Translation_Route( 'automlp-bulk-translate' );
		}
		
		// Initialize admin classes.
		if ( is_admin() ) {
			if ( class_exists( 'AUTOMLP_Ai_Dashboard' ) ) {
				AUTOMLP_Ai_Dashboard::get_instance();
			}
			if ( class_exists( 'AUTOMLP_Ai_Cpt_Dashboard' ) ) {
				new AUTOMLP_Ai_Cpt_Dashboard();
			}
		}
	}

	/**
	 * Check if WPML is active.
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
	}

	/**
	 * Display notice if WPML is not active.
	 *
	 * @return void
	 */
	public function wpml_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'AUTOMLP AI Translate Addon:', 'wpml-translation-check' ); ?></strong>
				<?php esc_html_e( 'This plugin requires WPML to be installed and activated.', 'wpml-translation-check' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Initialize the plugin.
 */
function automlp_ai_translate_addon() {
	return AUTOMLP_Ai_Translate_Addon::get_instance();
}

register_activation_hook( __FILE__, array( \AUTOMLP_WPML\Modules\Wizard\AUTOMLP_Ai_Wizard::class, 'start_wizard' ) );

// Start the plugin.
automlp_ai_translate_addon();
