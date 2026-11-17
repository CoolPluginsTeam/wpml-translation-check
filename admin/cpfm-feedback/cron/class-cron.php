<?php
/**
 * CPFM Usage Cron — shared 30-day usage-data cron for Cool Plugins products.
 *
 * FRAMEWORK USAGE (any plugin, one call):
 *
 *   CPFM_Usage_Cron::cpfm_register( array(
 *       'id'                       => 'tecc',
 *       'plugin_name'              => 'Event Countdown for The Events Calendar',
 *       'version'                  => TECC_VERSION_CURRENT,
 *       'api'                      => TECC_FEEDBACK_API,        // https://feedback.coolplugins.net/
 *       'cron_hook'                => 'tecc_extra_data_update', // THIS plugin's own hook name
 *       'consent_override_option'  => 'tecc-cpfm-data-sharing',       // THIS plugin's own checkbox
 *       'consent_master_option'    => 'cpfm_opt_in_choice_cool_events', // THIS plugin's product family's shared flag
 *       'install_date_option'      => 'tecc-install-date',
 *       'initial_version_option'   => 'tecc_initial_save_version',
 *   ) );
 *
 * Consent is two levels: a master applies to every addon WITHIN ONE Cool
 * Plugins product family at once (cpfm_opt_in_choice_cool_events for the
 * Events Addons family - a different family has its own, DIFFERENT master
 * option); each plugin's own override option - a DIFFERENT option name per
 * plugin, e.g. `tecc-cpfm-data-sharing` - may turn just that one plugin's
 * data off (or on) regardless of the master. With no override recorded the
 * master is inherited. Both option names are REQUIRED - there is no default
 * for either, because a default master option would silently borrow another
 * product family's consent flag for a plugin that was never asked (see
 * cpfm_register()'s own comment). A host with consent logic that does not
 * fit this shape at all may pass `consent_callback` (a callable returning
 * bool) to bypass both options entirely.
 *
 * The class is UNPREFIXED-by-plugin on purpose: it is shared across every
 * Cool Plugins addon, so the FIRST plugin to load it owns the single guarded
 * instance and every sibling just registers its own config onto it (same
 * pattern as CPFM_Deactivation_Feedback). Each plugin keeps its OWN cron
 * hook name, so one plugin's consent withdrawal only clears ITS OWN
 * schedule, never a sibling's.
 *
 * @package CoolPlugins\Shared
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Usage_Cron' ) ) {

	final class CPFM_Usage_Cron {

		/**
		 * Registered plugin configs, keyed by id.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private static $plugins = array();

		/**
		 * Ids whose cron hook is already wired, so a repeat cpfm_register()
		 * call for the same id never double-attaches the action.
		 *
		 * @var array<string, bool>
		 */
		private static $wired = array();

		/**
		 * Wire the shared cron interval once. Safe to call from any plugin.
		 *
		 * @return void
		 */
		public static function cpfm_boot() {
			static $schedules_wired = false;

			if ( $schedules_wired ) {
				return;
			}

			$schedules_wired = true;
			add_filter( 'cron_schedules', array( __CLASS__, 'cpfm_cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		}

		/**
		 * Schedule a plugin's usage cron hook if it is not already scheduled.
		 *
		 * @param string $cron_hook Plugin-specific cron hook name.
		 * @return void
		 */
		public static function cpfm_schedule_event( $cron_hook ) {
			if ( '' === $cron_hook ) {
				return;
			}

			self::cpfm_boot();

			if ( ! wp_next_scheduled( $cron_hook ) ) {
				wp_schedule_event( time(), 'every_30_days', $cron_hook );
			}
		}

		/**
		 * Register a plugin's usage cron. Safe to call from any plugin, safe
		 * to call more than once for the same id.
		 *
		 * @param array<string, mixed> $config See the file docblock.
		 * @return void
		 */
		public static function cpfm_register( $config ) {
			$config = wp_parse_args(
				(array) $config,
				array(
					'id'                     => '',
					'plugin_name'            => '',
					'version'                => '',
					'api'                    => '',
					'cron_hook'              => '',

					// Two-level consent. Neither option name gets a default: the
					// "shared master" is only shared WITHIN one Cool Plugins
					// product family (e.g. cpfm_opt_in_choice_cool_events for
					// the Events Addons family) - a plugin from a DIFFERENT
					// family (a Timeline addon, say) has its own master option
					// entirely. Defaulting to cool_events here would have a
					// plugin outside that family silently inherit the Events
					// family's consent flag - exactly the "phone home without
					// real consent" guardrail #6 forbids. Left empty, both
					// options simply read as "not set" (get_option('') is
					// falsy), so cpfm_consented() fails closed until the host
					// supplies its own.
					'consent_master_option'   => '',
					'consent_override_option' => '',

					// Escape hatch for a host whose consent logic does not fit
					// master+override at all. Bypasses both options entirely.
					'consent_callback'       => null,

					// Per-host option names. A SHARED module must never hardcode
					// one plugin's keys — vendored into a sibling they would read
					// the wrong row, or nothing at all.
					'install_date_option'    => '',
					'initial_version_option' => '',
					'onboarding_data'        => '',
					'site_key'               => '',
				)
			);

			if ( '' === $config['id'] || '' === $config['cron_hook'] || '' === $config['api'] ) {
				return;
			}

			self::$plugins[ $config['id'] ] = $config;

			self::cpfm_boot();

			if ( isset( self::$wired[ $config['id'] ] ) ) {
				return;
			}
			self::$wired[ $config['id'] ] = true;

			add_action( $config['cron_hook'], array( __CLASS__, 'cpfm_run' ) );
		}

		/**
		 * Two-level consent check: this plugin's own override when one is
		 * recorded, else the shared master. Native so a host does not have to
		 * write its own version of this logic just to get a per-plugin
		 * opt-out - see the file docblock.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @return bool
		 */
		private static function cpfm_consented( $config ) {
			if ( is_callable( $config['consent_callback'] ) ) {
				return (bool) call_user_func( $config['consent_callback'] );
			}

			$override = $config['consent_override_option'] ? get_option( $config['consent_override_option'] ) : false;
			if ( in_array( $override, array( 'yes', 'no' ), true ) ) {
				return ( 'yes' === $override );
			}

			return ( 'yes' === get_option( $config['consent_master_option'] ) );
		}

		/**
		 * Cron dispatcher: one static callback shared by every registered
		 * plugin's hook. current_action() says which one just fired, since
		 * WP's cron system passes no arguments of its own here.
		 *
		 * @return void
		 */
		public static function cpfm_run() {
			$hook = current_action();

			foreach ( self::$plugins as $id => $config ) {
				if ( $config['cron_hook'] === $hook ) {
					self::cpfm_send_data( $id );
					return;
				}
			}
		}

		/**
		 * Send this plugin's usage snapshot, or self-unschedule if consent is
		 * no longer present.
		 *
		 * Guardrail (BL-6): NEVER phone home without fresh, explicit consent.
		 * Read the shared opt-in flag straight from the DB on every run (never
		 * a cached/constructor value). Effective consent = this plugin's own
		 * override when one is recorded, else the shared master - so a
		 * per-plugin opt-out stops OUR data and nobody else's, and a global
		 * withdrawal is still respected by a plugin with no override of its
		 * own. A host with unusual needs may supply consent_callback instead,
		 * which bypasses this entirely.
		 *
		 * @param string $id Plugin id.
		 * @return void
		 */
		private static function cpfm_send_data( $id ) {
			$config = isset( self::$plugins[ $id ] ) ? self::$plugins[ $id ] : null;
			if ( ! $config ) {
				return;
			}

			$consented = self::cpfm_consented( $config );

			if ( ! $consented ) {
				wp_clear_scheduled_hook( $config['cron_hook'] );
				return;
			}

			if ( ! class_exists( 'CPFM_Environment' ) ) {
				$file = CPFM_DIR . 'class-cpfm-environment.php';
				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}

			if ( ! class_exists( 'CPFM_Environment' ) ) {
				return;
			}

			$env = CPFM_Environment::cpfm_environment();

			if ( ! is_array( $env ) ) {
				return;
			}

			$server_info   = isset( $env['server_info'] ) && is_array( $env['server_info'] ) ? $env['server_info'] : array();
			$extra_details = isset( $env['extra_details'] ) && is_array( $env['extra_details'] ) ? $env['extra_details'] : array();

			if ( ! empty( $config['onboarding_data'] ) ) {
				$onboarding = get_option( (string) $config['onboarding_data'], array() );
				$extra_details['onboarding_data'] = ( is_array( $onboarding ) && $onboarding ) ? $onboarding : array();
			} else {
				$extra_details['onboarding_data'] = array();
			}

			$site_url      = get_site_url();

			$install_date = $config['install_date_option'] ? get_option( $config['install_date_option'] ) : '';
			$site_id      = $site_url . '-' . $install_date . '-' . $config['site_key'];

			$initial_version = $config['initial_version_option'] ? get_option( $config['initial_version_option'] ) : '';
			// $initial_version = is_string( $initial_version ) && '' !== $initial_version ? sanitize_text_field( $initial_version ) : 'N/A';

			$plugin_version = '' !== $config['version'] ? (string) $config['version'] : 'N/A';
			$admin_email    = sanitize_email( get_option( 'admin_email' ) ?: 'N/A' );

			$post_data = array(
				'site_id'        => md5( $site_id ),
				'plugin_version' => $plugin_version,
				'plugin_name'    => $config['plugin_name'],
				'plugin_initial' => $initial_version,
				'email'          => $admin_email,
				'site_url'       => esc_url_raw( $site_url ),
				'server_info'    => $server_info,
				'extra_details'  => $extra_details,
			);

			// Fire-and-forget with a short timeout. This also runs synchronously
			// from the opt-in AJAX ("Yes, I agree"), so a slow/unreachable
			// endpoint must not make the user sit and wait on our server.
			wp_remote_post(
				trailingslashit( $config['api'] ) . 'wp-json/coolplugins-feedback/v1/site',
				array(
					'method'   => 'POST',
					'timeout'  => 5,
					'blocking' => false,
					'headers'  => array(
						'Content-Type' => 'application/json',
					),
					'body'     => wp_json_encode( $post_data ),
				)
			);

			// Reschedule regardless of the send result — a failed/unacknowledged
			// send must not stop future runs. (There is no response body to read
			// with blocking=false.)
			self::cpfm_schedule_event( $config['cron_hook'] );
		}

		/**
		 * Cron status schedule(s). Shared across every registered plugin — the
		 * interval is identical for all of them, so re-adding it is a no-op.
		 *
		 * @param array<string, array<string, mixed>> $schedules Core schedules.
		 * @return array<string, array<string, mixed>>
		 */
		public static function cpfm_cron_schedules( $schedules ) {
			if ( ! isset( $schedules['every_30_days'] ) ) {
				$schedules['every_30_days'] = array(
					'interval' => 30 * 24 * 60 * 60, // 2,592,000 seconds.
					// Only ever seen in wp-admin's own cron/Site Health debug
					// UI, never by a site visitor - not worth a text domain.
					'display'  => 'Once every 30 days',
				);
			}

			return $schedules;
		}

		/**
		 * Reset the registry. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {
			self::$plugins = array();
			self::$wired   = array();
		}
	}
}

if ( class_exists( 'CPFM_Usage_Cron' ) ) {
	CPFM_Usage_Cron::cpfm_boot();
}
