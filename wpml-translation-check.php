<?php
/**
 * Plugin Name: AutoMLP – AI Translation for WPML
 * Description: AutoMLP – AI translation addon for WPML that helps translate WordPress pages and posts content faster and more accurately.
 * Version: 1.2.0
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
	define( 'AUTOMLP_AI_VERSION', '1.2.0' );
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
if( !defined( 'AUTOMLP_AI_FEEDBACK_API' ) ) {
	define( 'AUTOMLP_AI_FEEDBACK_API', 'https://feedback.coolplugins.net/' );
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
		$this->init();
		$this->init_feedback_notice();
		$this->init_cron();
	}


	/**
		 * Initialize the cron job for the plugin.
		 */
		public function init_cron(){
			// if (is_admin()) {
			require_once AUTOMLP_AI_PLUGIN_DIR . '/admin/cpfm-feedback/cron/automlp-cron.php';
			$cron = new AUTOMLP_cronjob();
			$cron->automlp_cron_init_hooks();
			// }
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

	public function init_feedback_notice() {

		if (is_admin()) {
			add_action('admin_enqueue_scripts', array($this, 'automlp_ai_load_scripts'));
			if(!class_exists('CPFM_Feedback_Notice')){
				require_once AUTOMLP_AI_PLUGIN_DIR . '/admin/cpfm-feedback/cpfm-common-notice.php';

			}

		add_action('cpfm_register_notice', function () {
			if (!class_exists('CPFM_Feedback_Notice') || !current_user_can('manage_options')) {
				return;
			}
			
			$notice = [
				'title' => __('AutoMLP – AI Translation for WPML', 'wpml-translation-check'),
				'message' => __('Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'wpml-translation-check'),
				'pages' => ['automlp_ai_dashboard'],
				'always_show_on' => ['automlp_ai_dashboard'], // This enables auto-show
				'plugin_name'=>'wpml-translation-check'
			];
			CPFM_Feedback_Notice::cpfm_register_notice('cool_automlp_translations', $notice);
				if (!isset($GLOBALS['cool_plugins_feedback'])) {
					$GLOBALS['cool_plugins_feedback'] = [];
				}
				$GLOBALS['cool_plugins_feedback']['cool_automlp_translations'][] = $notice;
		});

		add_action('cpfm_after_opt_in_wpml-translation-check', function($category) {
			if ($category === 'cool_automlp_translations') {
				AUTOMLP_cronjob::automlp_send_data();
				$options = get_option('automlp_feedback_opt_in');
				$options = 'yes';
				update_option('automlp_feedback_opt_in', $options);	
			}
			});
		}
	}


	public function automlp_ai_load_scripts() {
		if(!wp_script_is( 'automlp-data-share-setting.js' )){
			$screen = get_current_screen(); 
			if (strpos($screen->id, 'wpml_page_automlp_ai_dashboard') !== false) {
			  wp_enqueue_script('automlp-data-share-setting', AUTOMLP_AI_PLUGIN_URL . 'assets/js/automlp-data-share-setting.js', array('jquery'), AUTOMLP_AI_VERSION, true);
		  }
	  }
	}

		/*
		|------------------------------------------------------------------------
		|  Get user info
		|------------------------------------------------------------------------
		*/

		public static function automlp_get_user_info() {
			global $wpdb;
			$server_info = [
			'server_software'        => sanitize_text_field($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'),
			'mysql_version'          => sanitize_text_field($wpdb->get_var("SELECT VERSION()")),
			'php_version'            => sanitize_text_field(phpversion()),
			'wp_version'             => sanitize_text_field(get_bloginfo('version')),
			'wp_debug'               => sanitize_text_field(defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled'),
			'wp_memory_limit'        => sanitize_text_field(ini_get('memory_limit')),
			'wp_max_upload_size'     => sanitize_text_field(ini_get('upload_max_filesize')),
			'wp_permalink_structure' => sanitize_text_field(get_option('permalink_structure', 'Default')),
			'wp_multisite'           => sanitize_text_field(is_multisite() ? 'Enabled' : 'Disabled'),
			'wp_language'            => sanitize_text_field(get_option('WPLANG', get_locale()) ?: get_locale()),
			'wp_prefix'              => sanitize_key($wpdb->prefix), // Sanitizing database prefix
			];
			$theme_data = [
			'name'      => sanitize_text_field(wp_get_theme()->get('Name')),
			'version'   => sanitize_text_field(wp_get_theme()->get('Version')),
			'theme_uri' => esc_url(wp_get_theme()->get('ThemeURI')),
			];
			if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = array_map(function ($plugin) {
			$plugin_info = get_plugin_data(WP_PLUGIN_DIR . '/' . sanitize_text_field($plugin));
			$author_url = ( isset( $plugin_info['AuthorURI'] ) && !empty( $plugin_info['AuthorURI'] ) ) ? esc_url( $plugin_info['AuthorURI'] ) : 'N/A';
			$plugin_url = ( isset( $plugin_info['PluginURI'] ) && !empty( $plugin_info['PluginURI'] ) ) ? esc_url( $plugin_info['PluginURI'] ) : '';
			return [
				'name'       => sanitize_text_field($plugin_info['Name']),
				'version'    => sanitize_text_field($plugin_info['Version']),
				'plugin_uri' => !empty($plugin_url) ? $plugin_url : $author_url,
			];
			}, get_option('active_plugins', []));
			return [
				'server_info' => $server_info,
				'extra_details' => [
					'wp_theme' => $theme_data,
					'active_plugins' => $plugin_data,
				]
			];
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
		if ( ! get_option( 'automlp_ai_setup_complete' ) ) {
			$setup_url = add_query_arg(
				array(
					'page' => 'automlp_ai_wizard',
					'step' => 'video_intro',
				),
				admin_url( 'admin.php' )
			);
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $setup_url ),
				esc_html__( 'Setup', 'wpml-translation-check' )
			);
			array_unshift( $links, $settings_link ); // Put Settings first (before Deactivate).
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
			'includes/helper/sanitized-content.php',
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
			'includes/modules/wizard/load.php',
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
		$this->load_dependencies();
		add_filter(
			'plugin_action_links_' . AUTOMLP_AI_PLUGIN_BASENAME,
			array( $this, 'add_settings_action_link' )
		);
		add_action( 'init', array($this, 'register_ai_client') );
		add_action( 'admin_init', array( $this, 'register_ai_model_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_automlp_ai_dashboard_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'hide_wp_ai_client_menu' ), 99 );
		add_action( 'admin_init', array( $this, 'automlp_feedback_form' ) );

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
	
	public function activate() {
		update_option( 'AUTOMLP_AI_VERSION', AUTOMLP_AI_VERSION );
		update_option( 'AUTOMLP_AI_TYPE', 'FREE' );
		add_option( 'automlp_ai_activation_time', gmdate( 'Y-m-d h:i:s' ) );
		add_option( 'automlp_ai_initial_save_version', AUTOMLP_AI_VERSION );
	
		if ( ! get_option( 'automlp_ai_install_date' ) ) {
			add_option( 'automlp_ai_install_date', gmdate( 'Y-m-d h:i:s' ) );
		}
		$get_opt_in = get_option('automlp_feedback_opt_in');

			if ($get_opt_in =='yes' && !wp_next_scheduled('automlp_extra_data_update')) {

				wp_schedule_event(time(), 'every_30_days', 'automlp_extra_data_update');
			}
	}

	/**
 * Check if required WPML plugins are active.
 *
 * Requires BOTH:
 * - WPML Multilingual CMS
 * - WPML String Translation
 *
 * @return bool
 */
private function is_wpml_active() {
    // WPML Multilingual CMS (core).
    $has_wpml_core = defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );

    // WPML String Translation.
    $has_wpml_st = defined( 'WPML_ST_VERSION' ) || class_exists( 'WPML_String_Translation' );

    return $has_wpml_core && $has_wpml_st;
}

public function automlp_feedback_form() {
	if(is_admin()){
		require_once __DIR__ . '/admin/feedback/admin-feedback-form.php';
		AUTOMLP_Ai_Cpt_Dashboard::review_notice(
			'automlp_ai',
			'AutoMLP – AI Translation for WPML',
			'https://wordpress.org/support/plugin/wpml-translation-check/reviews/#new-post'
		);
	}

	if(class_exists('automlp_admin_notices')){
		new automlp_admin_notices();
	}
}

	/**
 * Display notice if required WPML plugins are not active.
 *
 * @return void
 */
public function wpml_missing_notice() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $has_wpml_core = defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
    $has_wpml_st   = defined( 'WPML_ST_VERSION' ) || class_exists( 'WPML_String_Translation' );

    $links = array();

    if ( ! $has_wpml_core ) {
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( 'https://wpml.org/purchase' ),
            esc_html__( 'WPML Multilingual CMS', 'wpml-translation-check' )
        );
    }

    if ( ! $has_wpml_st ) {
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url( 'https://wpml.org/purchase' ),
            esc_html__( 'WPML String Translation', 'wpml-translation-check' )
        );
    }

    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'AutoMLP – AI Translation for WPML:', 'wpml-translation-check' ); ?></strong>
            <?php
            if ( count( $links ) === 2 ) {
                printf(
                    /* translators: 1: WPML Multilingual CMS link, 2: WPML String Translation link */
                    esc_html__( 'This plugin requires both %1$s and %2$s to be installed and activated.', 'wpml-translation-check' ),
                    $links[0],
                    $links[1]
                );
            } elseif ( count( $links ) === 1 ) {
                printf(
                    /* translators: %s: Missing plugin link */
                    esc_html__( 'This plugin requires %s to be installed and activated.', 'wpml-translation-check' ),
                    $links[0]
                );
            }
            ?>
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

// Put this in the global scope (NOT inside the class).
register_activation_hook( __FILE__, 'automlp_ai_on_activation' );
register_deactivation_hook( __FILE__, 'automlp_ai_on_deactivation' );

function automlp_ai_on_activation() {
    // 1) Run the existing wizard activation.
	if ( class_exists( '\AUTOMLP_WPML\Modules\Wizard\AUTOMLP_Ai_Wizard' ) ) {
		\AUTOMLP_WPML\Modules\Wizard\AUTOMLP_Ai_Wizard::start_wizard( false );
	}

    // 2) Call your plugin's activate() method.
    if ( function_exists( 'automlp_ai_translate_addon' ) ) {
        $plugin = automlp_ai_translate_addon();
        if ( $plugin instanceof AUTOMLP_Ai_Translate_Addon ) {
            $plugin->activate();
        }
    }
}

function automlp_ai_on_deactivation() {
    wp_clear_scheduled_hook('automlp_extra_data_update');
}


// Start the plugin.
automlp_ai_translate_addon();
