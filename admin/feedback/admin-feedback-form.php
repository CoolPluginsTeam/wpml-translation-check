<?php
namespace automlp\feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class automlp_feedback {

	private $plugin_url     = AUTOMLP_AI_PLUGIN_URL;
	private $plugin_version = AUTOMLP_AI_VERSION;
	private $plugin_name    = 'AutoMLP – AI Translation for WPML';
	private $plugin_slug    = 'automlp';
	private $feedback_url   = AUTOMLP_AI_FEEDBACK_API . 'wp-json/coolplugins-feedback/v1/feedback';

	/*
	|-----------------------------------------------------------------|
	|   Use this constructor to fire all actions and filters          |
	|-----------------------------------------------------------------|
	*/
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_feedback_scripts' ) );
		add_action( 'admin_head', array( $this, 'show_deactivate_feedback_popup' ) );
		add_action( 'wp_ajax_' . $this->plugin_slug . '_submit_deactivation_response', array( $this, 'submit_deactivation_response' ) );
	}

	/*
	|-----------------------------------------------------------------|
	|   Enqueue all scripts and styles to required page only          |
	|-----------------------------------------------------------------|
	*/
	function enqueue_feedback_scripts() {
		$screen = get_current_screen();
		if ( isset( $screen ) && $screen->id == 'plugins' ) {
			wp_enqueue_script(
				__NAMESPACE__ . 'feedback-script',
				$this->plugin_url . 'admin/feedback/js/admin-feedback.js',
				array( 'jquery' ),
				$this->plugin_version,
				true
			);
			wp_enqueue_style( 'cool-plugins-feedback-css', $this->plugin_url . 'admin/feedback/css/admin-feedback.css', null, $this->plugin_version );
		}
	}

	/*
	|-----------------------------------------------------------------|
	|   HTML for creating feedback popup form                         |
	|-----------------------------------------------------------------|
	*/
	public function show_deactivate_feedback_popup() {
		$screen = get_current_screen();
		if ( ! isset( $screen ) || $screen->id != 'plugins' ) {
			return;
		}
		$deactivate_reasons = array(
			'didnt_work_as_expected'         => array(
				'title'             => __( 'The plugin didn\'t work as expected', 'wpml-translation-check' ),
				'input_placeholder' => 'What did you expect?',
			),
			'found_a_better_plugin'          => array(
				'title'             => __( 'I found a better plugin', 'wpml-translation-check' ),
				'input_placeholder' => __( 'Please share which plugin', 'wpml-translation-check' ),
			),
			'couldnt_get_the_plugin_to_work' => array(
				'title'             => __( 'The plugin is not working', 'wpml-translation-check' ),
				'input_placeholder' => 'Please share your issue. So we can fix that for other users.',
			),
			'temporary_deactivation'         => array(
				'title'             => __( 'It\'s a temporary deactivation', 'wpml-translation-check' ),
				'input_placeholder' => '',
			),
			'other'                          => array(
				'title'             => __( 'Other', 'wpml-translation-check' ),
				'input_placeholder' => __( 'Please share the reason', 'wpml-translation-check' ),
			),
		);

		?>
		<div id="cool-plugins-deactivate-feedback-dialog-wrapper" class="hide-feedback-popup" data-slug="<?php echo esc_attr( $this->plugin_slug ); ?>">
			<div class="cool-plugins-deactivation-response">
				<div id="cool-plugins-deactivate-feedback-dialog-header">
					<span id="cool-plugins-feedback-form-title"><?php echo esc_html__( 'Quick Feedback', 'wpml-translation-check' ); ?></span>
				</div>
				<div id="cool-plugins-loader-wrapper">
					<div class="cool-plugins-loader-container">
						<img class="cool-plugins-preloader" src="<?php echo esc_url( $this->plugin_url ); ?>admin/feedback/images/cool-plugins-preloader.gif">
					</div>
				</div>
				<div id="cool-plugins-form-wrapper" class="cool-plugins-form-wrapper-cls">
					<form id="cool-plugins-deactivate-feedback-dialog-form" method="post">
						<?php
						wp_nonce_field( '_cool-plugins_deactivate_feedback_nonce' );
						?>
						<input type="hidden" name="action" value="cool-plugins_deactivate_feedback" />
						<div id="cool-plugins-deactivate-feedback-dialog-form-caption"><?php echo esc_html__( 'If you have a moment, please share why you are deactivating this plugin.', 'wpml-translation-check' ); ?></div>
						<div id="cool-plugins-deactivate-feedback-dialog-form-body">
							<?php foreach ( $deactivate_reasons as $reason_key => $reason ) : ?>
								<div class="cool-plugins-deactivate-feedback-dialog-input-wrapper">
									<input id="cool-plugins-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="cool-plugins-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="<?php echo esc_attr( $reason_key ); ?>" />
									<label for="cool-plugins-deactivate-feedback-<?php echo esc_attr( $reason_key ); ?>" class="cool-plugins-deactivate-feedback-dialog-label"><?php echo esc_html( $reason['title'] ); ?></label>
									<?php if ( ! empty( $reason['input_placeholder'] ) ) : ?>
										<textarea class="cool-plugins-feedback-text" type="textarea" name="reason_<?php echo esc_attr( $reason_key ); ?>" placeholder="<?php echo esc_attr( $reason['input_placeholder'] ); ?>"></textarea>
									<?php endif; ?>
									<?php if ( ! empty( $reason['alert'] ) ) : ?>
										<div class="cool-plugins-feedback-text"><?php echo esc_html( $reason['alert'] ); ?></div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
							<input class="cool-plugins-GDPR-data-notice" id="cool-plugins-GDPR-data-notice" type="checkbox"><label for="cool-plugins-GDPR-data-notice"><?php echo esc_html__( 'I agree to share anonymous usage data and basic site details (such as server, PHP, and WordPress versions) to support AutoMLP improvement efforts. Additionally, I allow Cool Plugins to store all information provided through this form and to respond to my inquiry.', 'wpml-translation-check' ); ?></label>
						</div>
						<div class="cool-plugin-popup-button-wrapper">
							<a class="cool-plugins-button button-deactivate" id="cool-plugin-submitNdeactivate">Submit and Deactivate</a>
							<a class="cool-plugins-button" id="cool-plugin-skipNdeactivate">Skip and Deactivate</a>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	function automlp_get_user_info() {
		global $wpdb;

		$server_info = array(
			'server_software'        => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
			'mysql_version'          => $wpdb ? sanitize_text_field( $wpdb->db_version() ) : 'N/A',
			'php_version'            => sanitize_text_field( phpversion() ?: 'N/A' ),
			'wp_version'             => sanitize_text_field( get_bloginfo( 'version' ) ?: 'N/A' ),
			'wp_debug'               => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled',
			'wp_memory_limit'        => sanitize_text_field( ini_get( 'memory_limit' ) ?: 'N/A' ),
			'wp_max_upload_size'     => sanitize_text_field( ini_get( 'upload_max_filesize' ) ?: 'N/A' ),
			'wp_permalink_structure' => sanitize_text_field( get_option( 'permalink_structure' ) ?: 'Default' ),
			'wp_multisite'           => is_multisite() ? 'Enabled' : 'Disabled',
			'wp_language'            => sanitize_text_field( get_option( 'WPLANG' ) ?: get_locale() ),
			'wp_prefix'              => isset( $wpdb->prefix ) ? sanitize_key( $wpdb->prefix ) : 'N/A',
		);

		$theme         = wp_get_theme();
		$theme_data    = array(
			'name'      => sanitize_text_field( $theme->get( 'Name' ) ),
			'version'   => sanitize_text_field( $theme->get( 'Version' ) ),
			'theme_uri' => esc_url( $theme->get( 'ThemeURI' ) ),
		);

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data     = array();
		$active_plugins  = get_option( 'active_plugins', array() );

		foreach ( $active_plugins as $plugin_path ) {
			$plugin_info = get_plugin_data( WP_PLUGIN_DIR . '/' . sanitize_text_field( $plugin_path ) );
			$author_url  = ( isset( $plugin_info['AuthorURI'] ) && ! empty( $plugin_info['AuthorURI'] ) ) ? esc_url( $plugin_info['AuthorURI'] ) : 'N/A';
			$plugin_url  = ( isset( $plugin_info['PluginURI'] ) && ! empty( $plugin_info['PluginURI'] ) ) ? esc_url( $plugin_info['PluginURI'] ) : 'N/A';
			$plugin_data[] = array(
				'name'       => sanitize_text_field( $plugin_info['Name'] ),
				'version'    => sanitize_text_field( $plugin_info['Version'] ),
				'plugin_uri' => ! empty( $plugin_url ) ? $plugin_url : $author_url,
			);
		}

		return array(
			'server_info'   => $server_info,
			'extra_details' => array(
				'wp_theme'       => $theme_data,
				'active_plugins' => $plugin_data,
			),
		);
	}

	function submit_deactivation_response() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), '_cool-plugins_deactivate_feedback_nonce' ) ) {
			wp_send_json_error();
		} else {
			$reason             = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
			$deactivate_reasons = array(
				'didnt_work_as_expected'         => array(
					'title'             => __( 'The plugin didn\'t work as expected', 'wpml-translation-check' ),
					'input_placeholder' => 'What did you expect?',
				),
				'found_a_better_plugin'          => array(
					'title'             => __( 'I found a better plugin', 'wpml-translation-check' ),
					'input_placeholder' => __( 'Please share which plugin.', 'wpml-translation-check' ),
				),
				'couldnt_get_the_plugin_to_work' => array(
					'title'             => __( 'The plugin is not working', 'wpml-translation-check' ),
					'input_placeholder' => 'Please share your issue. So we can fix that for other users.',
				),
				'temporary_deactivation'         => array(
					'title'             => __( 'It\'s a temporary deactivation.', 'wpml-translation-check' ),
					'input_placeholder' => '',
				),
				'other'                          => array(
					'title'             => __( 'Other', 'wpml-translation-check' ),
					'input_placeholder' => __( 'Please share the reason.', 'wpml-translation-check' ),
				),
			);

			$plugin_initial      = get_option( 'automlp_ai_initial_save_version' );
			$reason               = isset( $reason ) ? sanitize_key( $reason ) : '';
			$deativation_reason   = array_key_exists( $reason, $deactivate_reasons ) ? $reason : 'other';
			$sanitized_message    = isset( $_POST['message'] ) && sanitize_text_field( wp_unslash( $_POST['message'] ) ) === '' ? 'N/A' : sanitize_text_field( wp_unslash( $_POST['message'] ) );
			$admin_email          = sanitize_email( get_option( 'admin_email' ) );
			$site_url             = esc_url( site_url() );
			$install_date         = get_option( 'automlp_ai_install_date' );
			$unique_key           = '61';
			$site_id              = $site_url . '-' . $install_date . '-' . $unique_key;

			$response = wp_remote_post(
				$this->feedback_url,
				array(
					'timeout' => 30,
					'body'    => array(
						'server_info'     => serialize( $this->automlp_get_user_info()['server_info'] ),
						'extra_details'   => serialize( $this->automlp_get_user_info()['extra_details'] ),
						'plugin_initial'  => isset( $plugin_initial ) ? sanitize_text_field( $plugin_initial ) : 'N/A',
						'plugin_version'   => $this->plugin_version,
						'plugin_name'      => $this->plugin_name,
						'reason'           => $deativation_reason,
						'review'           => $sanitized_message,
						'email'            => $admin_email,
						'domain'           => $site_url,
						'site_id'          => md5( $site_id ),
					),
				)
			);

			die( wp_json_encode( array( 'response' => $response ) ) );
		}
	}
}
new automlp_feedback();