<?php
/**
 * Main class for AutoML setup wizard.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOML_WPML\Modules\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for AutoML wizard.
 */
class AUTOML_Ai_Wizard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 21 );
		add_action( 'admin_init', array( $this, 'setup_wizard_page' ), 40 );
		add_action( 'admin_notices', array( $this, 'maybe_show_wizard_notice' ) );
	}

	/**
	 * Add admin menu item for the wizard (hidden from menu).
	 * Shown only when setup is not complete (new users).
	 *
	 * @return void
	 */
    public function add_admin_menu() {
		if ( get_option( 'automl_ai_setup_complete' ) && $this->is_wizard_language_set() ) {
			return;
		}
		$parent_slug = 'sitepress-multilingual-cms/menu/languages.php';
		if ( is_array( $GLOBALS['menu'] ) ) {
			foreach ( $GLOBALS['menu'] as $item ) {
				if ( isset( $item[0], $item[2] ) && stripos( $item[0], 'WPML' ) !== false ) {
					$parent_slug = $item[2];
					break;
				}
			}
		}
		add_submenu_page(
			$parent_slug,
			esc_html__( 'AutoML Setup Wizard', 'wpml-translation-check' ),
			esc_html__( 'AutoML Setup', 'wpml-translation-check' ),
			'manage_options',
			'automl_ai_wizard',
			array( $this, 'display_wizard_page' )
		);
	}

	/**
	 * Save an activation transient when plugin is activated to redirect to the wizard.
	 *
	 * @param bool $network_wide Whether activated for the network.
	 * @return void
	 */
	public static function start_wizard( $network_wide ) {
		if ( wp_doing_ajax() || $network_wide ) {
			return;
		}
		if ( get_option( 'automl_ai_setup_complete' ) ) {
			return;
		}
		set_transient( 'wpml_at_activation_redirect', 1, 30 );
	}

	/**
	 * Redirect to the wizard after activation when appropriate.
	 *
	 * @return void
	 */
	public function redirect_to_wizard() {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'plugins.php', 'index.php' ), true ) ) {
			return;
		}
		if ( ! get_transient( 'wpml_at_activation_redirect' ) ) {
			return;
		}
		if ( ( isset( $_GET['page'] ) && 'automl_ai_wizard' === sanitize_key( $_GET['page'] ) ) || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_transient( 'wpml_at_activation_redirect' );
			return;
		}
		delete_transient( 'wpml_at_activation_redirect' );
		wp_safe_redirect(
			add_query_arg( array( 'page' => 'automl_ai_wizard' ), admin_url( 'admin.php' ) )
		);
		exit;
	}

	/**
	 * Show a notice with link to wizard if setup is not complete.
	 *
	 * @return void
	 */
    public function maybe_show_wizard_notice() {
		if ( get_option( 'automl_ai_setup_complete' ) && $this->is_wizard_language_set() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'edit', 'upload', 'options-general', 'dashboard' ), true ) ) {
			return;
		}
		if ( isset( $_GET['page'] ) && 'automl_ai_wizard' === sanitize_key( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		ob_start();
		include __DIR__ . '/html-wizard-notice.php';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo ob_get_clean();
	}

	/**
	 * Setup the wizard page: redirect and enqueue assets when on wizard.
	 *
	 * @return void
	 */
	public function setup_wizard_page() {
		$this->redirect_to_wizard();
		if ( ! $this->is_wizard() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Whether the current request is the wizard page.
	 *
	 * @return bool
	 */
	public function is_wizard() {
		return isset( $_GET['page'] ) && 'automl_ai_wizard' === sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
    	/**
	 * Whether a translation language has been selected in the wizard.
	 *
	 * @return bool
	 */
	private function is_wizard_language_set() {
		$opt = get_option( 'automl_ai_wizard_selected_language', array() );
		return is_array( $opt ) && ! empty( $opt['code'] );
	}

	/**
	 * Display the wizard page.
	 * Redirect to dashboard if setup was already completed (e.g. after reinstall).
	 *
	 * @return void
	 */
	public function display_wizard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'wpml-translation-check' ) );
		}
        if ( get_option( 'automl_ai_setup_complete' ) && $this->is_wizard_language_set() ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'automl_ai_dashboard' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		include __DIR__ . '/view-wizard-page.php';
	}

	/**
	 * Enqueue scripts and styles for the wizard page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_wizard() ) {
			return;
		}
		$asset_path = AUTOML_AI_PLUGIN_DIR . 'admin/assets/frontend/setup/setup.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			wp_enqueue_style(
				'automl-ai-wizard',
				AUTOML_AI_PLUGIN_URL . 'modules/wizard/assets/wizard.css',
				array(),
				AUTOML_AI_VERSION
			);
			return;
		}
		$asset   = require $asset_path;
		$script  = AUTOML_AI_PLUGIN_URL . 'admin/assets/frontend/setup/setup.js';
		wp_enqueue_script(
			'wpml_at_setup',
			$script,
			$asset['dependencies'],
			$asset['version'],
			true
		);
		$wpml_languages = array();
		if ( class_exists( 'WPML_AT_Helper' ) ) {
			$wpml_languages = \WPML_AT_Helper::get_wpml_languages();
            $default_language = \WPML_AT_Helper::get_default_language();
		}
		$get_providers_key=\WPML_AT_Helper::get_providers_key(array('openai', 'google'));
		$saved_credentials = array();

		if(isset($get_providers_key['openai']) && !empty($get_providers_key['openai'])){
			$saved_credentials['openai']=$get_providers_key['openai'];
		}

		if(isset($get_providers_key['google']) && !empty($get_providers_key['google'])){
			$saved_credentials['google']=$get_providers_key['google'];
		}

		$home_url_with_lang = get_home_url();      // e.g. http://wpml-plugin.local/en/
		$lang_code          = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : '';
		$base_home_url      = $home_url_with_lang;
		
		if ( $lang_code ) {
			// Remove trailing /{lang}/ or /{lang} from the home URL.
			$base_home_url = preg_replace(
				'#/' . preg_quote( $lang_code, '#' ) . '/?$#',
				'/',
				untrailingslashit( $home_url_with_lang )
			);
		}

		$is_using_connectors_ai             = function_exists('_wp_register_default_connector_settings');
		$is_openai_provider_installed = $is_using_connectors_ai && class_exists( 'WordPress\OpenAiAiProvider\Provider\OpenAiProvider' ) ? true : false;
		$is_google_provider_installed = $is_using_connectors_ai && class_exists( 'WordPress\GoogleAiProvider\Provider\GoogleProvider' ) ? true : false;

		wp_localize_script(
            'wpml_at_setup',
            'wpml_at_setup',
            array(
                'api_url'        => rest_url( 'automl-bulk-translate/' ),
                'nonce'          => wp_create_nonce( 'wp_rest' ),
                'admin_url'      => get_admin_url( null, 'admin.php' ),
                'dashboard_url'  => add_query_arg( array( 'page' => 'automl_ai_dashboard&tab=settings' ), admin_url( 'admin.php' ) ),
                'home_url'       => $base_home_url,
                'wpml_languages' => $wpml_languages,
                'default_language' => $default_language,
                'saved_language' => get_option( 'automl_ai_wizard_selected_language', array() ),
                'saved_credentials' => array(
                    'openai_key' => isset( $saved_credentials['openai'] ) ? $saved_credentials['openai'] : '',
                    'google_key' => isset( $saved_credentials['google'] ) ? $saved_credentials['google'] : '',
                ),
				'is_connectors_ai'            => $is_using_connectors_ai,
				'is_openai_provider_installed' => $is_openai_provider_installed,
				'is_google_provider_installed' => $is_google_provider_installed,
				'connectors_url'              => admin_url( 'options-connectors.php' ),
            )
        );
		wp_enqueue_style(
			'automl-ai-wizard',
			AUTOML_AI_PLUGIN_URL . 'modules/wizard/assets/wizard.css',
			array(),
			AUTOML_AI_VERSION
		);
	}
}