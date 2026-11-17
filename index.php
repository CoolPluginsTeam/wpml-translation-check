<?php
/**
 * Plugin Name: AutoMLP – AI Translation for WPML
 * Description: AutoMLP – AI translation addon for WPML that helps translate WordPress pages and posts content faster and more accurately.
 * Version: 2.0.0
 * Author: Cool Plugins
 * Author URI: https://coolplugins.net/?utm_source=automlp_plugin&utm_medium=inside&utm_campaign=author_page&utm_content=plugins_list
 * Text Domain: wpml-translation-check
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
	define( 'AUTOMLP_AI_VERSION', '2.0.0' );
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

if ( ! defined( 'AUTOMLP_AI_PLUGIN_FILE' ) ) {
	define( 'AUTOMLP_AI_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AUTOMLP_AI_PLUGIN_NAME' ) ) {
	define( 'AUTOMLP_AI_PLUGIN_NAME', 'AutoMLP – AI Translation for WPML' );
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

		// Shared CPFM feedback framework: review ask, deactivation survey and usage opt-in.
		add_action( 'init', array( $this, 'register_cpfm_notices' ), 999 );
		$this->init_cpfm_cron();

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'automlp_ai_load_scripts' ) );
		}
	}

	public function register_ai_client() {
		if ( version_compare( get_option( 'AUTOMLP_AI_VERSION', '0' ), '1.3.0', '<' ) ) {
			\AUTOMLP_WPML\Includes\Wpml\Legacy_Bridge::purge_legacy_meta();
		}

		if(is_admin()){
			\AUTOMLP_WPML\Admin\Queue_Screen::boot();
		}

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

	/**
	 * Register the CPFM review ask, deactivation survey and usage opt-in notice.
	 *
	 * Runs on `init` (priority 999) so every string is translated after the text
	 * domain is loaded. The review ask and the deactivation survey are only for
	 * the free plugin; the Pro plugin has its own.
	 *
	 * @return void
	 */
	public function register_cpfm_notices() {
		if ( ! is_admin() ) {
			return;
		}

		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		$loader = AUTOMLP_AI_PLUGIN_DIR . 'admin/cpfm-feedback/class-cpfm-loader.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}

		require_once $loader;
		if ( class_exists( 'CPFM_Loader' ) ) {
			CPFM_Loader::load();
		}

		$name = AUTOMLP_AI_PLUGIN_NAME;
		$page = 'automlp_ai_dashboard';

		$dashboard_screens = array(
			'wpml_page_' . $page,
			'toplevel_page_' . $page,
		);

		if ( ! defined( 'AUTOMLP_AI_PRO_VERSION' ) ) {
			if ( class_exists( 'CPFM_Review' ) ) {
				CPFM_Review::cpfm_register(
					array(
						'id'          => 'automlp',
						'plugin_file' => AUTOMLP_AI_PLUGIN_FILE,
						'plugin_name' => $name,
						'review_url'  => 'https://wordpress.org/support/plugin/wpml-translation-check/reviews/#new-post',
						'capability'  => 'activate_plugins',
						'quiet_days'  => 0,
						'own_screens' => $dashboard_screens,
						'trigger'     => array(
							'type'  => 'install_age',
							'hours' => 24,
						),
						'notice'      => array(
							'enabled'        => true,
							'template'       => 'two_step',
							'screens'        => array( 'plugins' ),
							'inline_screens' => array(),
							'defer_screens'  => array(),
						),
						'row'         => array( 'enabled' => true ),
						'legacy'      => array(
							'done_options'  => array(
								'cpt_review_notice_dismissed' => array( 'in_key' => 'automlp_ai' ),
							),
							'install_dates' => array( 'automlp_ai_install_date' ),
						),
						'i18n'        => array(
							'like_question' => sprintf(
								/* translators: %s: plugin name. */
								__( 'Do you like the %s plugin?', 'wpml-translation-check' ),
								$name
							),
							'yes_button'    => __( 'Yes, I like it', 'wpml-translation-check' ),
							'dismiss_link'  => __( 'Not good, dismiss', 'wpml-translation-check' ),
							'later_link'    => __( 'Ask me later', 'wpml-translation-check' ),
							'thanks_line'   => __( 'That is great to hear! A quick review on WordPress.org would really help us.', 'wpml-translation-check' ),
							'submit_button' => __( 'Submit review', 'wpml-translation-check' ),
							'no_link'       => __( 'I do not like it, dismiss', 'wpml-translation-check' ),
							'row_question'  => __( 'Do you like this plugin?', 'wpml-translation-check' ),
							'inline_title'  => sprintf(
								/* translators: %s: plugin name. */
								__( 'Enjoying %s?', 'wpml-translation-check' ),
								$name
							),
							'inline_text'   => __( 'A short review helps other site owners find it.', 'wpml-translation-check' ),
							'close_label'   => __( 'Close', 'wpml-translation-check' ),
						),
					)
				);
			}

			if ( class_exists( 'CPFM_Deactivation_Feedback' ) ) {
				CPFM_Deactivation_Feedback::cpfm_register(
					array(
						'id'                     => 'automlp',
						'slug'                   => 'wpml-translation-check',
						'plugin_name'            => $name,
						'version'                => AUTOMLP_AI_VERSION,
						'api'                    => AUTOMLP_AI_FEEDBACK_API,
						'site_key'               => '61',
						'install_date_option'    => 'automlp_ai_install_date',
						'initial_version_option' => 'automlp_ai_initial_save_version',
						'reasons'                => array(
							'not_working'  => array(
								'title'       => __( "The plugin isn't working", 'wpml-translation-check' ),
								'placeholder' => __( 'Which problem did you run into? We read every reply.', 'wpml-translation-check' ),
							),
							'not_expected' => array(
								'title'       => __( "It didn't do what I expected", 'wpml-translation-check' ),
								'placeholder' => __( 'What were you hoping it would do?', 'wpml-translation-check' ),
							),
							'found_better' => array(
								'title'       => __( 'I found a better plugin', 'wpml-translation-check' ),
								'placeholder' => __( 'Mind sharing which one?', 'wpml-translation-check' ),
							),
							'temporary'    => array(
								'title'       => __( "It's a temporary deactivation", 'wpml-translation-check' ),
								'placeholder' => '',
							),
							'other'        => array(
								'title'       => __( 'Another reason', 'wpml-translation-check' ),
								'placeholder' => __( 'Please tell us more', 'wpml-translation-check' ),
							),
						),
						'i18n'                   => array(
							'title'        => __( 'Before you go…', 'wpml-translation-check' ),
							/* translators: %s: plugin name (bold). */
							'intro'        => __( 'What made you deactivate %s? Your answer helps us fix it.', 'wpml-translation-check' ),
							'submit'       => __( 'Submit & Deactivate', 'wpml-translation-check' ),
							'skip'         => __( 'Skip & Deactivate', 'wpml-translation-check' ),
							'deactivating' => __( 'Deactivating…', 'wpml-translation-check' ),
							'pick_reason'  => __( 'Please choose a reason.', 'wpml-translation-check' ),
							'close_label'  => __( 'Close', 'wpml-translation-check' ),
							/* translators: %s: company name. */
							'byline'       => __( 'A plugin by %s', 'wpml-translation-check' ),
							'consent'      => __( 'Submitting shares your reason plus your site URL, admin email and basic environment details (PHP, WordPress, active plugins). Skip & Deactivate sends nothing.', 'wpml-translation-check' ),
						),
					)
				);
			}
		}

		// Register the "Help Improve Plugins" opt-in popup via CPFM_Feedback_Notice.
		if ( class_exists( 'CPFM_Feedback_Notice' ) ) {
			CPFM_Feedback_Notice::cpfm_register_notice(
				'cool_automlp_translations',
				array(
					'title'          => $name,
					'message'        => __( 'Help us make this plugin more compatible with your site by sharing non-sensitive site data.', 'wpml-translation-check' ),
					'plugin_name'    => 'automlp',
					'pages'          => array( $page ),
					'always_show_on' => array( $page ),
					'i18n'           => array(
						'panel_title' => __( 'Help Improve Plugins', 'wpml-translation-check' ),
						'yes_label'   => __( "Yes, it's OK", 'wpml-translation-check' ),
						'no_label'    => __( 'No, Thanks', 'wpml-translation-check' ),
						'more_info'   => __( 'More info', 'wpml-translation-check' ),
					),
				)
			);

			// Schedule the usage cron when the user opts in.
			add_action(
				'cpfm_after_opt_in_automlp',
				function () {
					update_option( 'automlp_feedback_opt_in', 'yes' );
					if ( class_exists( 'CPFM_Usage_Cron' ) ) {
						CPFM_Usage_Cron::cpfm_schedule_event( 'automlp_extra_data_update' );
					}
				}
			);

			// Clear the usage cron when the user opts out.
			add_action(
				'cpfm_after_opt_out_automlp',
				function () {
					update_option( 'automlp_feedback_opt_in', 'no' );
					wp_clear_scheduled_hook( 'automlp_extra_data_update' );
				}
			);
		}

		// The plugin's own dashboard shows no review notice (it has the opt-in panel).
		add_action(
			'admin_notices',
			static function () {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( ! $screen || empty( $screen->id ) ) {
					return;
				}
				if ( false === strpos( (string) $screen->id, 'automlp_ai_dashboard' ) ) {
					return;
				}
				if ( class_exists( 'CPFM_Review_Notice' ) ) {
					remove_action( 'admin_notices', array( 'CPFM_Review_Notice', 'cpfm_maybe_render' ), 10 );
				}
			},
			0
		);
	}

	/**
	 * Boot and register the CPFM usage cron.
	 *
	 * Not admin-only on purpose: wp-cron.php is not an admin request, and the
	 * scheduled event needs its handler attached there.
	 *
	 * @return void
	 */
	public function init_cpfm_cron() {
		$cron_file = AUTOMLP_AI_PLUGIN_DIR . 'admin/cpfm-feedback/cron/class-cron.php';
		if ( file_exists( $cron_file ) ) {
			require_once $cron_file;
		}
		$env_file = AUTOMLP_AI_PLUGIN_DIR . 'admin/cpfm-feedback/class-cpfm-environment.php';
		if ( file_exists( $env_file ) ) {
			require_once $env_file;
		}

		if ( ! class_exists( 'CPFM_Usage_Cron' ) ) {
			return;
		}

		CPFM_Usage_Cron::cpfm_register(
			array(
				'id'                      => 'automlp',
				'plugin_name'             => AUTOMLP_AI_PLUGIN_NAME,
				'version'                 => AUTOMLP_AI_VERSION,
				'api'                     => AUTOMLP_AI_FEEDBACK_API,
				'cron_hook'               => 'automlp_extra_data_update',
				'consent_override_option' => 'automlp_feedback_opt_in',
				'consent_master_option'   => 'cpfm_opt_in_choice_cool_automlp_translations',
				'install_date_option'     => 'automlp_ai_install_date',
				'initial_version_option'  => 'automlp_ai_initial_save_version',
				'site_key'                => '61',
			)
		);

		// Family consent may already exist (a sibling was installed first): inherit it.
		// The cron is scheduled only on activation or on an explicit opt-in.
		$automlp_opt_in = get_option( 'automlp_feedback_opt_in' );
		if ( ! in_array( $automlp_opt_in, array( 'yes', 'no' ), true )
			&& 'yes' === get_option( 'cpfm_opt_in_choice_cool_automlp_translations' ) ) {
			update_option( 'automlp_feedback_opt_in', 'yes' );
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
			'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
			: 'N/A',
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
			'includes/wpml/builder/gutenberg/update-block-config.php',
			'includes/bulk-translation/bulk-translation.php',
			'includes/string-translation/string-translation.php',
			'includes/bulk-translation/register-assets.php',
			'includes/string-translation/register-assets.php',
			'includes/class-automlp-ai-helper.php',
			'includes/class-automlp-ai-update-translate-data-ajax.php',
			'includes/routes/bulk-translation-route.php',
			'admin/class-automlp_ai_dashboard.php',
			'admin/cpt_dashboard/cpt_dashboard.php',
			'includes/wizard/load.php',
			'includes/queue/class-queue-table.php',
			'includes/queue/class-dispatcher.php',
			'includes/providers/class-prompt-builder.php',
			'includes/providers/class-ai-gateway.php',
			'includes/wpml/class-bot-translator.php',
			'includes/wpml/class-writer.php',
			'includes/wpml/class-job-listener.php',
			'includes/wpml/class-job-sender.php',
			'includes/wpml/class-background-flow.php',
			'includes/routes/class-queue-route.php',
			'includes/wpml/class-legacy-bridge.php',
			'admin/queue/class-queue-screen.php',
			'includes/frontend-inspector/class-component-registry.php',
			'includes/frontend-inspector/class-option-index.php',
			'includes/frontend-inspector/class-js-strings.php',
			'includes/frontend-inspector/class-integration-resolvers.php',
			'includes/frontend-inspector/class-origin-collector.php',
			'includes/frontend-inspector/class-origin-resolver.php',
			'includes/frontend-inspector/class-container-markers.php',
			'includes/frontend-inspector/class-frontend-inspector.php',
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
	    if(defined('AUTOMLP_AI_PRO_VERSION')){
			return;
		}
		$this->load_dependencies();

		\AUTOMLP_WPML\Includes\Queue\Dispatcher::boot();
		\AUTOMLP_WPML\Includes\Wpml\Background_Flow::boot();
		\AUTOMLP_WPML\Includes\Routes\Queue_Route::boot();
		\AUTOMLP_WPML\Includes\Wpml\Legacy_Bridge::boot();
		\AUTOMLP_WPML\Includes\FrontendInspector\Frontend_Inspector::boot();

		add_action( 'init', array( '\AUTOMLP_WPML\Includes\Queue\Queue_Table', 'maybe_upgrade' ), 5 );

		add_filter(
			'plugin_action_links_' . AUTOMLP_AI_PLUGIN_BASENAME,
			array( $this, 'add_settings_action_link' )
		);
		add_action( 'init', array($this, 'register_ai_client') );
		add_action( 'admin_init', array( $this, 'register_ai_model_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_automlp_ai_dashboard_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'hide_wp_ai_client_menu' ), 99 );

		// Initialize AJAX handlers.
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
		$get_opt_in = get_option( 'automlp_feedback_opt_in' );

		if ( 'yes' === $get_opt_in ) {
			if ( class_exists( 'CPFM_Usage_Cron' ) ) {
				CPFM_Usage_Cron::cpfm_schedule_event( 'automlp_extra_data_update' );
			} elseif ( ! wp_next_scheduled( 'automlp_extra_data_update' ) ) {
				wp_schedule_event( time(), 'every_30_days', 'automlp_extra_data_update' );
			}
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
	$wpml_setup_complete = $this->is_wpml_setup_complete();

	return $has_wpml_core && $has_wpml_st && $wpml_setup_complete;
}
private function is_wpml_setup_complete() {
	// Prefer WPML's recovery-aware option getter when available.
	if ( class_exists( '\WPML\LIB\WP\Option' ) ) {
		$settings = \WPML\LIB\WP\Option::getOrAttemptRecovery( 'icl_sitepress_settings', [] );

		// Prefer WPML's Obj helper when available; otherwise fall back to array access.
		if ( class_exists( '\WPML\FP\Obj' ) ) {
			return is_array( $settings ) && (bool) \WPML\FP\Obj::prop( 'setup_complete', $settings );
		}

		return is_array( $settings ) && ! empty( $settings['setup_complete'] );
	}

	// Fallback if WPML libs aren't available.
	$settings = get_option( 'icl_sitepress_settings', [] );
	return is_array( $settings ) && ! empty( $settings['setup_complete'] );
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
	$wpml_setup_complete = $this->is_wpml_setup_complete();

	  // Case: plugins are active but WPML setup is not complete.
	  if ( $has_wpml_core && $has_wpml_st && ! $wpml_setup_complete ) {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e( 'AutoMLP – AI Translation for WPML Pro:', 'wpml-translation-check-pro' ); ?></strong>
                <?php
                printf(
                    /* translators: 1: WPML setup link */
                    esc_html__( 'WPML is active but setup is not complete. Please %1$s to finish configuring WPML before using this plugin.', 'wpml-translation-check-pro' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( 'sitepress-multilingual-cms/menu/setup.php' ) ) ) . '">' . esc_html__( 'complete the WPML setup', 'wpml-translation-check-pro' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
        return;
    }

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
					wp_kses_post( $links[0] ),
					wp_kses_post( $links[1] )
				);
			} elseif ( count( $links ) === 1 ) {
				printf(
					/* translators: %s: Missing plugin link */
					esc_html__( 'This plugin requires %s to be installed and activated.', 'wpml-translation-check' ),
					wp_kses_post( $links[0] )
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
	if ( ! class_exists( '\AUTOMLP_WPML\Includes\Queue\Queue_Table' ) ) {
		require_once AUTOMLP_AI_PLUGIN_DIR . 'includes/queue/class-queue-table.php';
	}
	\AUTOMLP_WPML\Includes\Queue\Queue_Table::install();

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
	if ( class_exists( '\AUTOMLP_WPML\Includes\Queue\Dispatcher' ) ) {
    	\AUTOMLP_WPML\Includes\Queue\Dispatcher::unschedule();
	}
}


// Start the plugin.
automlp_ai_translate_addon();
