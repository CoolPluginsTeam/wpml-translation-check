<?php
/**
 * CPFM Deactivation Feedback — a shared, drop-in deactivation survey for
 * Cool Plugins products.
 *
 * FRAMEWORK USAGE (any plugin, one call):
 *
 *   if ( ! class_exists( 'CPFM_Deactivation_Feedback' ) ) {
 *       require_once __DIR__ . '/cpfm-feedback/cpfm-deactivation-feedback.php';
 *   }
 *   CPFM_Deactivation_Feedback::cpfm_register( array(
 *       'id'          => 'tecc',                               // short unique key
 *       'slug'        => 'countdown-for-the-events-calendar',  // plugin folder slug
 *       'plugin_name' => 'Event Countdown for The Events Calendar',
 *       'version'     => TECC_VERSION_CURRENT,
 *       'api'         => TECC_FEEDBACK_API,                    // https://feedback.coolplugins.net/
 *   ) );
 *
 * The class is UNPREFIXED on purpose: it is shared across every Cool Plugins
 * addon, so the FIRST plugin to load it owns the single guarded instance and
 * all the others just register their config onto it (exactly like
 * CPFM_Feedback_Notice). One modal per registered plugin is rendered on
 * plugins.php; one shared AJAX action serves them all.
 *
 * @package CoolPlugins\Shared
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Deactivation_Feedback' ) ) {

	class CPFM_Deactivation_Feedback {

		/**
		 * Registered plugin configs, keyed by id.
		 *
		 * @var array<string,array>
		 */
		private static $plugins = array();

		/**
		 * Whether the single instance's hooks are wired.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Register a plugin's deactivation survey. Safe to call from any plugin.
		 *
		 * @param array $config id, slug, plugin_name, version, api, site_key.
		 * @return void
		 */
		public static function cpfm_register( $config ) {
			$config = wp_parse_args(
				(array) $config,
				array(
					'id'          => '',
					'slug'        => '',
					'plugin_name' => '',
					'version'     => '',
					'api'         => '',
					'site_key'    => '',

					// Per-host option names. A SHARED module must never hardcode
					// one plugin's keys — vendored into a sibling they would read
					// the wrong row, or nothing at all.
					'install_date_option'    => '',
					'initial_version_option' => '',
					'onboarding_data'        => '',


					/*
					 * Host-supplied, ALREADY TRANSLATED copy. The framework never
					 * calls __(): a shared module that translates on the host's
					 * behalf binds the strings to the wrong text domain, and if it
					 * resolves the domain at runtime it makes them unextractable
					 * to `wp i18n make-pot` entirely.
					 *
					 * 'reasons' => array( key => array( title, placeholder ) )
					 * Omit to fall back to developer English.
					 */
					'reasons'                => array(),
					'i18n'                   => array(),
				)
			);

			if ( '' === $config['id'] || '' === $config['slug'] ) {
				return;
			}

			self::$plugins[ $config['id'] ] = $config;
			self::cpfm_boot();
		}

		/**
		 * Wire the shared hooks exactly once.
		 *
		 * @return void
		 */
		private static function cpfm_boot() {
			if ( self::$booted ) {
				return;
			}
			self::$booted = true;

			$instance = new self();
			add_action( 'admin_enqueue_scripts', array( $instance, 'cpfm_enqueue' ) );
			add_action( 'admin_footer-plugins.php', array( $instance, 'cpfm_render' ) );
			add_action( 'wp_ajax_cpfm_deactivation_feedback', array( $instance, 'cpfm_handle' ) );
		}

		/**
		 * The default reason set (shared across plugins, translatable).
		 *
		 * @return array<string,array{title:string,placeholder:string}>
		 */
		private function cpfm_reasons( $cfg = array() ) {
			// Keys are the framework's contract (they are stored and reported on);
			// the TEXT belongs to the host. A host that supplies nothing gets
			// developer English, which is a visible prompt to supply copy - not a
			// silent binding to some other plugin's text domain.
			$defaults = array(
				'not_working'  => array(
					'title'       => "The plugin isn't working",
					'placeholder' => 'Which problem did you run into? We read every reply.',
				),
				'not_expected' => array(
					'title'       => "It didn't do what I expected",
					'placeholder' => 'What were you hoping it would do?',
				),
				'found_better' => array(
					'title'       => 'I found a better plugin',
					'placeholder' => 'Mind sharing which one?',
				),
				'temporary'    => array(
					'title'       => "It's a temporary deactivation",
					'placeholder' => '',
				),
				'other'        => array(
					'title'       => 'Another reason',
					'placeholder' => 'Please tell us more',
				),
			);

			$host = ( isset( $cfg['reasons'] ) && is_array( $cfg['reasons'] ) ) ? $cfg['reasons'] : array();

			foreach ( $defaults as $key => $row ) {
				if ( ! isset( $host[ $key ] ) || ! is_array( $host[ $key ] ) ) {
					continue;
				}
				if ( isset( $host[ $key ]['title'] ) && is_string( $host[ $key ]['title'] ) && '' !== $host[ $key ]['title'] ) {
					$defaults[ $key ]['title'] = $host[ $key ]['title'];
				}
				if ( isset( $host[ $key ]['placeholder'] ) && is_string( $host[ $key ]['placeholder'] ) ) {
					$defaults[ $key ]['placeholder'] = $host[ $key ]['placeholder'];
				}
			}

			return $defaults;
		}

		/**
		 * Host-supplied UI string, falling back to developer English.
		 *
		 * @param array  $cfg     Plugin config.
		 * @param string $key     Copy key.
		 * @param string $default Fallback.
		 * @return string
		 */
		private function cpfm_text( $cfg, $key, $default ) {
			if ( isset( $cfg['i18n'][ $key ] ) && is_string( $cfg['i18n'][ $key ] ) && '' !== $cfg['i18n'][ $key ] ) {
				return $cfg['i18n'][ $key ];
			}
			return $default;
		}

		/**
		 * Disclosure line for the modal.
		 *
		 * Submitting is not consent-gated, so this must describe the FULL payload
		 * — reason and note, admin email, site URL, environment and the active
		 * plugin list. Keep this wording in step with the $body built in handle():
		 * an inaccurate disclosure here is the thing that turns a legitimate
		 * survey into a privacy problem.
		 *
		 * @param array $cfg Plugin config.
		 * @return string
		 */
		private function cpfm_consent_line( $cfg ) {
			return $this->cpfm_text(
				$cfg,
				'consent',
				'Submitting shares your reason plus your site URL, admin email and basic environment details (PHP, WordPress, active plugins). Skip & Deactivate sends nothing.'
			);
		}

		/**
		 * Enqueue the modal assets + config on plugins.php only.
		 *
		 * @param string $hook Current admin screen hook.
		 * @return void
		 */
		public function cpfm_enqueue( $hook ) {
			if ( 'plugins.php' !== $hook || ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$ver = self::cpfm_first_version();

			wp_enqueue_style( 'cpfm-deactivation-feedback', CPFM_URL . 'css/cpfm-deactivation-feedback.css', array(), $ver );
			wp_enqueue_script( 'cpfm-deactivation-feedback', CPFM_URL . 'js/cpfm-deactivation-feedback.js', array(), $ver, true );

			// Labels are PER PLUGIN, not global: each host supplies its own
			// translated copy, so one shared set would hand every plugin whichever
			// one happened to register last.
			$map  = array();
			$i18n = array();
			foreach ( self::$plugins as $pid => $pcfg ) {
				$map[ $pid ]  = $pcfg['slug'];
				$i18n[ $pid ] = array(
					'pickReason'   => $this->cpfm_text( $pcfg, 'pick_reason', 'Please choose a reason.' ),
					'deactivating' => $this->cpfm_text( $pcfg, 'deactivating', 'Deactivating…' ),
				);
			}

			wp_localize_script(
				'cpfm-deactivation-feedback',
				'cpfmDeactivation',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'cpfm_deactivation_feedback' ),
					'plugins' => $map,
					'i18n'    => $i18n,
				)
			);
		}

		/**
		 * Render one modal per registered plugin.
		 *
		 * @return void
		 */
		public function cpfm_render() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			foreach ( self::$plugins as $id => $cfg ) {
				// Resolved INSIDE the loop: reason copy is per-plugin, so hoisting
				// this out would give every modal one host's wording (and read an
				// undefined $cfg on the first pass).
				$reasons = $this->cpfm_reasons( $cfg );

				$titleid = 'cpfm-df-title-' . sanitize_html_class( $id );
				?>
				<div class="cpfm-df" id="cpfm-df-<?php echo esc_attr( $id ); ?>" data-cpfm-df="<?php echo esc_attr( $id ); ?>" aria-hidden="true">
					<div class="cpfm-df__backdrop" data-cpfm-df-close></div>
					<div class="cpfm-df__dialog" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="<?php echo esc_attr( $titleid ); ?>">
						<button type="button" class="cpfm-df__x" data-cpfm-df-close aria-label="<?php echo esc_attr( $this->cpfm_text( $cfg, 'close_label', 'Close' ) ); ?>">&times;</button>
						<div class="cpfm-df__head">
							<h2 class="cpfm-df__title" id="<?php echo esc_attr( $titleid ); ?>"><?php echo esc_html( $this->cpfm_text( $cfg, 'title', 'Before you go…' ) ); ?></h2>
							<p class="cpfm-df__sub">
								<?php
								/* translators: %s: plugin name. */
								printf(
									/* translators: %s: plugin name (bold). */
									esc_html( $this->cpfm_text( $cfg, 'intro', 'What made you deactivate %s? Your answer helps us fix it.' ) ),
									'<strong>' . esc_html( $cfg['plugin_name'] ) . '</strong>'
								);
								?>
							</p>
						</div>

						<form class="cpfm-df__form">
							<?php
							/*
							 * The row wrapper is a DIV, not a LABEL: a <label> may only label its
							 * FIRST labelable descendant, so nesting the follow-up <textarea>
							 * inside it left the textarea ambiguously associated and folded its
							 * placeholder into the radio's accessible name. The inner block-level
							 * label keeps the whole reason line clickable.
							 */
							?>
							<?php foreach ( $reasons as $key => $reason ) : ?>
								<div class="cpfm-df__option">
									<label class="cpfm-df__row">
										<input type="radio" name="cpfm_df_reason" value="<?php echo esc_attr( $key ); ?>" class="cpfm-df__radio" />
										<span class="cpfm-df__reason"><?php echo esc_html( $reason['title'] ); ?></span>
									</label>
									<?php if ( '' !== $reason['placeholder'] ) : ?>
										<textarea class="cpfm-df__note" rows="2" data-reason="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>"></textarea>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>

							<p class="cpfm-df__error" role="alert" hidden></p>

							<div class="cpfm-df__actions">
								<button type="button" class="cpfm-df__submit" data-cpfm-df-submit>
									<span class="cpfm-df__spinner" aria-hidden="true"></span>
									<span class="cpfm-df__submit-label"><?php echo esc_html( $this->cpfm_text( $cfg, 'submit', 'Submit & Deactivate' ) ); ?></span>
								</button>
								<a href="#" class="cpfm-df__skip" data-cpfm-df-skip><?php echo esc_html( $this->cpfm_text( $cfg, 'skip', 'Skip & Deactivate' ) ); ?></a>
							</div>

							<p class="cpfm-df__consent">
								<?php echo esc_html( $this->cpfm_consent_line( $cfg ) ); ?>
							</p>
						</form>

						<div class="cpfm-df__by">
							<?php
							printf(
								/* translators: %s: Cool Plugins link. */
								esc_html( $this->cpfm_text( $cfg, 'byline', 'A plugin by %s' ) ),
								'<a href="' . esc_url( 'https://coolplugins.net/?utm_source=' . rawurlencode( $id ) . '_plugin&utm_medium=deactivation_feedback' ) . '" target="_blank" rel="noopener">Cool Plugins</a>'
							);
							?>
						</div>
					</div>
				</div>
				<?php
			}
		}

		/**
		 * Handle the AJAX submit: send the reason to the plugin's feedback API.
		 *
		 * @return void
		 */
		public function cpfm_handle() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_send_json_error( 'Unauthorized', 403 );
			}

			check_ajax_referer( 'cpfm_deactivation_feedback', 'nonce' );

			$id = isset( $_POST['plugin_id'] ) ? sanitize_key( wp_unslash( $_POST['plugin_id'] ) ) : '';
			if ( '' === $id || ! isset( self::$plugins[ $id ] ) ) {
				wp_send_json_error( 'Unknown plugin.' );
			}
			$cfg = self::$plugins[ $id ];

			$reason  = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : 'other';
			$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
			$reasons = $this->cpfm_reasons( $cfg );
			if ( ! array_key_exists( $reason, $reasons ) ) {
				$reason = 'other';
			}

			if ( '' === $cfg['api'] ) {
				wp_send_json_success(); // Nothing to send — let the deactivation proceed.
			}

			$site_url = esc_url_raw( home_url() );

			// Per-host key. This module is vendored into every Cool Plugins addon,
			// so reading one plugin's option name here would silently produce a
			// different (or empty) hash in every sibling.
			$install = '';
			if ( ! empty( $cfg['install_date_option'] ) ) {
				$install = (string) get_option( $cfg['install_date_option'] );
			}
			$site_id = md5( $site_url . '-' . $install . '-' . $cfg['site_key'] );

			/*
			 * NOT consent-gated (owner decision, 2026-07-28). The submission is an
			 * explicit, deliberate act — the user picked a reason and pressed
			 * "Submit & Deactivate" — so the whole payload goes every time, as it
			 * always has.
			 *
			 * The obligation that survives: the disclosure line in the modal must
			 * keep describing exactly this. If the payload changes, that copy
			 * changes with it. "Skip & Deactivate" sends nothing at all, which is
			 * what makes submitting a genuine choice.
			 */
			$env = CPFM_Environment::cpfm_environment();

			$extra_details = isset( $env['extra_details'] ) && is_array( $env['extra_details'] ) ? $env['extra_details'] : array();

			if ( ! empty( $cfg['onboarding_data'] ) ) {
				$onboarding = get_option( (string) $cfg['onboarding_data'], array() );
				$extra_details['onboarding_data'] = ( is_array( $onboarding ) && $onboarding ) ? $onboarding : array();
			} else {
				$extra_details['onboarding_data'] = array();
			}

			$body = array(
				'plugin_name'    => sanitize_text_field( $cfg['plugin_name'] ),
				'plugin_version' => sanitize_text_field( (string) $cfg['version'] ),
				'reason'         => $reason,
				'review'         => '' === $message ? 'N/A' : $message,
				'email'          => sanitize_email( get_option( 'admin_email' ) ),
				'domain'         => $site_url,
				'site_id'        => $site_id,
				'server_info'    => wp_json_encode( $env['server_info'] ),
				'extra_details'  => wp_json_encode( $extra_details ),
			);

			// Which version they FIRST installed - the 30-day cron already sends
			// this, so without it the two payloads from one plugin disagree in
			// shape and "what did they start on before quitting" is unanswerable.
			if ( ! empty( $cfg['initial_version_option'] ) ) {
				$body['plugin_initial'] = sanitize_text_field( (string) get_option( $cfg['initial_version_option'] ) );
			}

			// Answer the browser and CLOSE the connection BEFORE calling our own
			// server. This is the part that actually decouples the user from our
			// endpoint: `blocking => false` does NOT do it (WP's cURL transport
			// still runs curl_exec() synchronously and merely skips parsing the
			// response), so without this the caller waits for the full timeout.
			self::cpfm_close_connection();

			$response = wp_remote_post(
				trailingslashit( $cfg['api'] ) . 'wp-json/coolplugins-feedback/v1/feedback',
				array(
					// Nobody is waiting now, so use a normal blocking request —
					// the response is worth having for the debug log.
					'timeout' => 8,
					'body'    => $body,
				)
			);

			// A transport error is logged, never surfaced: the user's deactivation
			// must not be blocked or shown an error because OUR endpoint had a bad
			// day (the response was already sent by close_connection()).
			if ( is_wp_error( $response )) {

				return;
			}

			exit;
		}

		/**
		 * Send a success response and release the browser, then let PHP carry on
		 * in the background to actually deliver the feedback.
		 *
		 * PHP-FPM and LiteSpeed can genuinely close the client connection;
		 * elsewhere we flush what we can (mod_php keeps the connection until the
		 * script ends, which is why the caller also has its own deadline).
		 *
		 * @since 2.1.0
		 * @return void
		 */
		private static function cpfm_close_connection() {
			
			$payload = wp_json_encode( array( 'success' => true ) );

			if ( ! headers_sent() ) {
				header( 'Content-Type: application/json; charset=utf-8' );
				header( 'Content-Length: ' . strlen( $payload ) );
				header( 'Connection: close' );
			}

			echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built by wp_json_encode.

			// Empty every output buffer so the bytes actually leave PHP.
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
			flush();

			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} elseif ( function_exists( 'litespeed_finish_request' ) ) {
				litespeed_finish_request();
			}
		}

		/**
		 * The earliest registered plugin version (asset cache-bust).
		 *
		 * @return string
		 */
		private static function cpfm_first_version() {
			foreach ( self::$plugins as $cfg ) {
				if ( ! empty( $cfg['version'] ) ) {
					return (string) $cfg['version'];
				}
			}
			return '1.0.0';
		}

		
	}
}
