<?php
/**
 * CPFM Review - shared review-ask framework for Cool Plugins products.
 *
 * Phase 1: registry, state model, legacy resolution and eligibility.
 * Surfaces (admin notice, plugins.php row, inline embed) are separate classes
 * that consume `is_due()` and call `record_answer()`; they are not required for
 * this file to be useful or testable.
 *
 * INTEGRATION
 * -----------
 *   CPFM_Review::cpfm_register( array(
 *       'id'          => 'tecc',
 *       'plugin_file' => TECC_PLUGIN_FILE,
 *       'plugin_name' => 'Event Countdown',
 *       'review_url'  => 'https://wordpress.org/support/plugin/<slug>/reviews/#new-post',
 *       'trigger'     => array( 'type' => 'install_age', 'hours' => 72 ),
 *       'legacy'      => array(
 *           'done_options'   => array( 'tecc-ratingDiv' => 'yes' ),
 *           'done_user_meta' => array( 'eca_review_dismissed' => '1' ),
 *           'install_dates'  => array( 'tecc-installDate', 'tecc-install-date' ),
 *           'mirror_write'   => array( 'tecc-ratingDiv' => 'yes' ),
 *       ),
 *       'i18n'        => array( ... ),   // ALREADY TRANSLATED by the host
 *   ) );
 *
 * The host passes translated strings; this file never calls __(). A shared
 * module that resolves a text domain at runtime makes every string
 * unextractable to `wp i18n make-pot` - see dev-notes/CPFM-FRAMEWORKS-PLAN.md R1.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Review' ) ) {

	/**
	 * Registry + state machine for the review ask.
	 */
	final class CPFM_Review {

		/**
		 * State schema version, stored inside each plugin's state option.
		 */
		const STATE_VERSION = 1;

		/**
		 * Shared option recording which plugin asked last, so a site with several
		 * Cool Plugins addons does not produce several simultaneous asks.
		 */
		const QUIET_OPTION = 'cpfm_review_last_shown';

		/**
		 * Default days another plugin's ask suppresses everyone else's.
		 */
		const QUIET_DAYS = 14;

		/**
		 * Registered plugin configs, keyed by id.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private static $plugins = array();

		/**
		 * Per-request state cache so repeated is_due() calls cost one read.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private static $cache = array();

		/**
		 * Register a plugin with the review framework.
		 *
		 * Safe to call on every admin request; it only stores config. No option
		 * is read or written here - state is resolved lazily on first use, so a
		 * plugin that never reaches a surface never touches the database.
		 *
		 * @param array<string, mixed> $config See the file docblock.
		 * @return bool True when accepted.
		 */
		public static function cpfm_register( $config ) {
			$config = is_array( $config ) ? $config : array();

			$id = isset( $config['id'] ) ? sanitize_key( $config['id'] ) : '';
			if ( '' === $id || empty( $config['review_url'] ) ) {
				return false;
			}

			$config = array_merge(
				array(
					'id'          => $id,
					'plugin_file' => '',
					'plugin_name' => '',
					'review_url'  => '',
					'capability'  => 'manage_options',
					'quiet_days'  => self::QUIET_DAYS,
					'trigger'     => array(
						'type'  => 'install_age',
						'hours' => 72,
					),
					/*
					 * Screens that BELONG to this plugin — its own settings panel.
					 * The cross-plugin quiet period does not apply there: that
					 * throttle exists to stop several addons piling asks onto
					 * SHARED screens (plugins.php, All Events), but on a plugin's
					 * own page an ask for that plugin is contextual and expected.
					 * Kept separate from notice.inline_screens on purpose: the two
					 * happen to coincide today, but one is about notice placement
					 * and this is about throttling.
					 */
					'own_screens' => array(),

					'notice'      => array(),
					'row'         => array(),
					'inline'      => array(),
					'legacy'      => array(),
					'i18n'        => array(),
				),
				$config
			);

			$config['row'] = array_merge(
				array( 'enabled' => false ),
				is_array( $config['row'] ) ? $config['row'] : array()
			);

			$config['inline'] = array_merge(
				array(
					// Master switch for CPFM_Review::cpfm_inline()/cpfm_render_inline().
					// A host places the actual call in its own template (there is
					// no "screens" list the way notice/row have, since the host
					// decides placement, not this framework) - this is the one
					// central place to turn that card off without touching the
					// template. Default true: preserves the pre-existing
					// behaviour of every host that registered before this key
					// existed, where the embed was unconditionally available.
					'enabled'   => true,
					// The embed is always available via CPFM_Review::cpfm_inline(); this
					// flag only controls whether a [cpfm_review] shortcode is also
					// registered, which most hosts will not want.
					'shortcode' => false,
				),
				isset( $config['inline'] ) && is_array( $config['inline'] ) ? $config['inline'] : array()
			);

			$config['notice'] = array_merge(
				array(
					'enabled'        => false,
					'template'       => 'two_step', // two_step | direct
					'screens'        => array(),
					// Screens where the `inline` class is added to opt out of
					// core's notice relocation. Only custom full-bleed pages need
					// it; on a standard WP screen the relocation is correct.
					'inline_screens' => array(),
					// Screens where the host renders the notice ITSELF, by calling
					// CPFM_Review_Notice::cpfm_maybe_render( false ) from inside its own
					// markup. The admin_notices hook stays quiet there, so the ask
					// lands where the host wants it rather than above its header.
					'defer_screens'  => array(),
					// Legacy escape hatch: forces `inline` on EVERY screen.
					'position'       => '',
				),
				is_array( $config['notice'] ) ? $config['notice'] : array()
			);

			if ( ! in_array( $config['notice']['template'], array( 'two_step', 'direct' ), true ) ) {
				$config['notice']['template'] = 'two_step';
			}

			$config['id']         = $id;
			$config['review_url'] = self::cpfm_sanitize_review_url( $config['review_url'] );
			if ( '' === $config['review_url'] ) {
				return false;
			}

			$config['legacy'] = array_merge(
				array(
					'done_options'   => array(),
					'done_user_meta' => array(),
					'install_dates'  => array(),
					'mirror_write'   => array(),
				),
				is_array( $config['legacy'] ) ? $config['legacy'] : array()
			);

			self::$plugins[ $id ] = $config;

			self::cpfm_boot();
			self::cpfm_load_surfaces( $config );

			return true;
		}

		/**
		 * Load only the surface classes this plugin actually enables.
		 *
		 * A host that only wants the plugin-row line never parses the notice
		 * surface, and vice versa.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @return void
		 */
		private static function cpfm_load_surfaces( $config ) {
			$wants_notice = ! empty( $config['notice']['enabled'] );
			$wants_row    = ! empty( $config['row']['enabled'] );

			if ( ! $wants_notice && ! $wants_row ) {
				return;
			}

			// Every surface shares one delegated click handler.
			$assets = CPFM_DIR . 'review/class-cpfm-review-assets.php';
			if ( file_exists( $assets ) ) {
				require_once $assets;
			}

			if ( $wants_notice ) {
				$notice = CPFM_DIR . 'review/class-cpfm-review-notice.php';
				if ( file_exists( $notice ) ) {
					require_once $notice;
				}
				if ( class_exists( 'CPFM_Review_Notice' ) ) {
					CPFM_Review_Notice::cpfm_init();
				}
			}

			if ( $wants_row ) {
				$row = CPFM_DIR . 'review/class-cpfm-review-row.php';
				if ( file_exists( $row ) ) {
					require_once $row;
				}
				if ( class_exists( 'CPFM_Review_Row' ) ) {
					CPFM_Review_Row::cpfm_init();
				}
			}

			if ( ! empty( $config['inline']['shortcode'] ) ) {
				self::cpfm_load_inline();
				if ( class_exists( 'CPFM_Review_Inline' ) ) {
					CPFM_Review_Inline::cpfm_init();
				}
			}
		}

		/**
		 * Lazy-load the inline surface and its shared handler.
		 *
		 * @return void
		 */
		private static function cpfm_load_inline() {
			$assets = CPFM_DIR . 'review/class-cpfm-review-assets.php';
			if ( file_exists( $assets ) ) {
				require_once $assets;
			}
			$inline = CPFM_DIR . 'review/class-cpfm-review-inline.php';
			if ( file_exists( $inline ) ) {
				require_once $inline;
			}
		}

		/**
		 * Build the inline embed markup for placing anywhere in host content.
		 *
		 * Returns '' when the user has answered, is snoozed, or lacks the
		 * capability - so a host can call it unconditionally.
		 *
		 * @param string               $id   Plugin id.
		 * @param array<string, mixed> $args style (card|bar|minimal), hours.
		 * @return string
		 */
		public static function cpfm_inline( $id, $args = array() ) {
			self::cpfm_load_inline();
			if ( ! class_exists( 'CPFM_Review_Inline' ) ) {
				return '';
			}
			return CPFM_Review_Inline::cpfm_get( $id, $args );
		}

		/**
		 * Echo the inline embed.
		 *
		 * @param string               $id   Plugin id.
		 * @param array<string, mixed> $args style|hours.
		 * @return void
		 */
		public static function cpfm_render_inline( $id, $args = array() ) {
			// cpfm_inline() escapes every interpolated value as it builds the markup.
			echo self::cpfm_inline( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Wire the shared hooks exactly once, however many plugins register.
		 *
		 * One AJAX action serves every registered plugin; the request names the
		 * plugin and the nonce is per-plugin, so one endpoint is not a shortcut
		 * that weakens anything.
		 *
		 * @return void
		 */
		private static function cpfm_boot() {
			static $booted = false;
			if ( $booted ) {
				return;
			}
			$booted = true;

			if ( function_exists( 'add_action' ) ) {
				add_action( 'wp_ajax_cpfm_review_answer', array( __CLASS__, 'cpfm_ajax_answer' ) );
			}
		}

		/**
		 * Nonce action for a plugin's review writes.
		 *
		 * @param string $id Plugin id.
		 * @return string
		 */
		public static function cpfm_nonce_action( $id ) {
			return 'cpfm_review_' . sanitize_key( $id );
		}

		/**
		 * Record an answer or a snooze from the browser.
		 *
		 * @return void
		 */
		public static function cpfm_ajax_answer() {
			$id     = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
			$config = self::cpfm_config( $id );

			if ( ! $config ) {
				wp_send_json_error( 'unknown_plugin', 400 );
			}

			check_ajax_referer( self::cpfm_nonce_action( $id ) );

			if ( ! current_user_can( $config['capability'] ) ) {
				wp_send_json_error( 'forbidden', 403 );
			}

			$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'answer';

			if ( 'snooze' === $what ) {
				$days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 30;
				$days = min( 365, max( 1, $days ) );
				self::cpfm_snooze( $id, $days * DAY_IN_SECONDS );
				wp_send_json_success( 'snoozed' );
			}

			// Everything else is a final answer. We deliberately do not record
			// WHICH answer - only that the user responded.
			self::cpfm_record_answer( $id );
			wp_send_json_success( 'recorded' );
		}

		/**
		 * Registered config for an id, or null.
		 *
		 * @param string $id Plugin id.
		 * @return array<string, mixed>|null
		 */
		public static function cpfm_config( $id ) {
			$id = sanitize_key( $id );
			return isset( self::$plugins[ $id ] ) ? self::$plugins[ $id ] : null;
		}

		/**
		 * All registered ids.
		 *
		 * @return string[]
		 */
		public static function cpfm_registered() {
			return array_keys( self::$plugins );
		}

		/**
		 * Only wordpress.org review URLs unless the host explicitly opts out.
		 *
		 * Stops a careless integration turning a shared module into an open
		 * redirect surface.
		 *
		 * @param string $url Candidate URL.
		 * @return string Empty string when rejected.
		 */
		private static function cpfm_sanitize_review_url( $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' === $url ) {
				return '';
			}
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! is_string( $host ) ) {
				return '';
			}
			$host = strtolower( $host );
			if ( 'wordpress.org' === $host || substr( $host, -14 ) === '.wordpress.org' ) {
				return $url;
			}
			return '';
		}

		/* ------------------------------------------------------------------
		 * State
		 * --------------------------------------------------------------- */

		/**
		 * Option name holding a plugin's review state.
		 *
		 * @param string $id Plugin id.
		 * @return string
		 */
		public static function cpfm_state_option( $id ) {
			return 'cpfm_review_state_' . sanitize_key( $id );
		}

		/**
		 * Read (and on first use, build) a plugin's state.
		 *
		 * @param string $id Plugin id.
		 * @return array{status:string, since:int, snooze_until:int, asked:int, v:int}
		 */
		public static function cpfm_state( $id ) {
			$id = sanitize_key( $id );

			if ( isset( self::$cache[ $id ] ) ) {
				return self::$cache[ $id ];
			}

			$stored = get_option( self::cpfm_state_option( $id ) );

			if ( ! is_array( $stored ) || ! isset( $stored['status'] ) ) {
				$stored = self::cpfm_bootstrap_state( $id );
			}

			$state = array(
				'status'       => ( isset( $stored['status'] ) && 'done' === $stored['status'] ) ? 'done' : 'pending',
				'since'        => isset( $stored['since'] ) ? (int) $stored['since'] : 0,
				'snooze_until' => isset( $stored['snooze_until'] ) ? (int) $stored['snooze_until'] : 0,
				'asked'        => isset( $stored['asked'] ) ? (int) $stored['asked'] : 0,
				'v'            => isset( $stored['v'] ) ? (int) $stored['v'] : self::STATE_VERSION,
			);

			self::$cache[ $id ] = $state;

			return $state;
		}

		/**
		 * Persist a state array.
		 *
		 * Autoload is OFF: this is read on admin screens only and must never
		 * ride along on every front-end request's alloptions query.
		 *
		 * @param string               $id    Plugin id.
		 * @param array<string, mixed> $state State.
		 * @return void
		 */
		private static function cpfm_save_state( $id, $state ) {
			$id                 = sanitize_key( $id );
			$state['v']         = self::STATE_VERSION;
			self::$cache[ $id ] = $state;

			$option = self::cpfm_state_option( $id );
			if ( false === get_option( $option ) ) {
				add_option( $option, $state, '', 'no' );
				return;
			}
			update_option( $option, $state, false );
		}

		/**
		 * Build initial state by resolving the host's legacy options ONCE.
		 *
		 * Everything in here is deliberately confined to first run. After this
		 * the framework answers from a single option; it never re-reads the
		 * legacy keys, and never queries users again.
		 *
		 * @param string $id Plugin id.
		 * @return array<string, mixed>
		 */
		private static function cpfm_bootstrap_state( $id ) {
			$config = self::cpfm_config( $id );
			$legacy = ( $config && isset( $config['legacy'] ) ) ? $config['legacy'] : array();

			$state = array(
				'status'       => 'pending',
				'since'        => 0,
				'snooze_until' => 0,
				'asked'        => 0,
				'v'            => self::STATE_VERSION,
			);

			// 1. Did this user ALREADY ANSWER under an old key?
			//    Only a recorded dismissal/review counts. A missing key, or a key
			//    holding "no"/""/0, means "never answered" -> still eligible.
			//    Matching is strict against the declared accept-list, never
			//    truthiness: `tecc-ratingDiv` is written 'no' when re-armed, so
			//    matching on existence alone would silence the entire install base.
			if ( self::cpfm_legacy_says_answered( $legacy ) ) {
				$state['status'] = 'done';
			}

			// 2. Inherit the real install clock, so a 3-year-old install is
			//    immediately past any install_age trigger rather than restarting.
			$state['since'] = self::cpfm_legacy_install_time( $legacy );
			if ( ! $state['since'] ) {
				$state['since'] = time();
			}

			self::cpfm_save_state( $id, $state );

			return $state;
		}

		/**
		 * Does a legacy option / user meta record an actual answer?
		 *
		 * @param array<string, mixed> $legacy Legacy config block.
		 * @return bool
		 */
		private static function cpfm_legacy_says_answered( $legacy ) {
			$options = isset( $legacy['done_options'] ) ? (array) $legacy['done_options'] : array();
			foreach ( $options as $key => $accept ) {
				$value = get_option( $key );
				if ( false === $value ) {
					continue; // Never set -> never answered.
				}
				if ( self::cpfm_answer_matches( $value, $accept ) ) {
					return true;
				}
			}

			$meta = isset( $legacy['done_user_meta'] ) ? (array) $legacy['done_user_meta'] : array();
			if ( $meta && function_exists( 'get_users' ) ) {
				// Any administrator may have dismissed it, not just the current one.
				$admins = get_users(
					array(
						'role'   => 'administrator',
						'fields' => 'ID',
						'number' => 50,
					)
				);
				foreach ( $meta as $key => $accept ) {
					foreach ( (array) $admins as $user_id ) {
						$value = get_user_meta( (int) $user_id, $key, true );
						if ( '' === $value || null === $value ) {
							continue;
						}
						if ( self::cpfm_answer_matches( $value, $accept ) ) {
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Does a stored legacy value record an answer?
		 *
		 * Handles two shapes:
		 *  - scalar, matched strictly against the declared accept-list;
		 *  - ARRAY, when the accept spec is array( 'in_key' => 'countdown' ).
		 *    Some shared modules store a per-addon map rather than a flag - the
		 *    ECA dashboard's `eca_review_dismissed` user meta is keyed by addon -
		 *    so a plain scalar comparison would silently never match and the
		 *    dismissal would be lost.
		 *
		 * @param mixed $value  Stored value.
		 * @param mixed $accept Accept spec.
		 * @return bool
		 */
		private static function cpfm_answer_matches( $value, $accept ) {
			if ( is_array( $value ) ) {
				if ( is_array( $accept ) && isset( $accept['in_key'] ) ) {
					$key = (string) $accept['in_key'];
					return ! empty( $value[ $key ] );
				}
				return false;
			}

			return self::cpfm_value_matches( $value, $accept );
		}

		/**
		 * Strict value match against a scalar or a list of accepted values.
		 *
		 * Compared as trimmed lowercase strings so 'Yes' and 'yes' agree, but
		 * '' / '0' / 'no' never accidentally count as an answer.
		 *
		 * @param mixed $value  Stored value.
		 * @param mixed $accept Accepted value or array of them.
		 * @return bool
		 */
		private static function cpfm_value_matches( $value, $accept ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				return false;
			}
			$value  = strtolower( trim( (string) $value ) );
			$accept = is_array( $accept ) ? $accept : array( $accept );

			foreach ( $accept as $candidate ) {
				if ( is_array( $candidate ) || is_object( $candidate ) ) {
					continue;
				}
				$candidate = strtolower( trim( (string) $candidate ) );
				if ( '' === $candidate ) {
					continue;
				}
				if ( $value === $candidate ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * First usable install timestamp from the declared legacy keys.
		 *
		 * Accepts a Unix timestamp, `Y-m-d H:i:s` or `Y-m-d`. Rejects anything in
		 * the future or absurdly old, so a corrupt value cannot make the ask due
		 * forever (or never).
		 *
		 * @param array<string, mixed> $legacy Legacy config block.
		 * @return int Unix timestamp, or 0.
		 */
		private static function cpfm_legacy_install_time( $legacy ) {
			$keys  = isset( $legacy['install_dates'] ) ? (array) $legacy['install_dates'] : array();
			$now   = time();
			$floor = 1420070400; // 2015-01-01; older than any Cool Plugins install.

			foreach ( $keys as $key ) {
				$raw = get_option( $key );
				if ( false === $raw || '' === $raw || is_array( $raw ) ) {
					continue;
				}

				$ts = 0;
				if ( is_numeric( $raw ) ) {
					$ts = (int) $raw;
				} else {
					$parsed = strtotime( (string) $raw . ' UTC' );
					if ( false === $parsed ) {
						$parsed = strtotime( (string) $raw );
					}
					$ts = false === $parsed ? 0 : (int) $parsed;
				}

				if ( $ts >= $floor && $ts <= $now ) {
					return $ts;
				}
			}

			return 0;
		}

		/* ------------------------------------------------------------------
		 * Eligibility
		 * --------------------------------------------------------------- */

		/**
		 * Should this plugin ask for a review right now?
		 *
		 * @param string $id Plugin id.
		 * @return bool
		 */
		public static function cpfm_is_due( $id ) {
			$config = self::cpfm_config( $id );
			if ( ! $config ) {
				return false;
			}

			if ( ! current_user_can( $config['capability'] ) ) {
				return false;
			}

			$state = self::cpfm_state( $id );

			if ( 'done' === $state['status'] ) {
				return false;
			}

			if ( $state['snooze_until'] > time() ) {
				return false;
			}

			if ( ! self::cpfm_trigger_satisfied( $config, $state ) ) {
				return false;
			}

			if ( self::cpfm_quiet_period_active( $config ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Evaluate the configured trigger.
		 *
		 * Supports a single trigger, or `array( 'all' => array(...) )` /
		 * `array( 'any' => array(...) )` for composition.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @param array<string, mixed> $state  Current state.
		 * @return bool
		 */
		private static function cpfm_trigger_satisfied( $config, $state ) {
			$trigger = isset( $config['trigger'] ) ? $config['trigger'] : array();
			if ( ! is_array( $trigger ) ) {
				return true;
			}

			if ( isset( $trigger['all'] ) && is_array( $trigger['all'] ) ) {
				foreach ( $trigger['all'] as $sub ) {
					if ( ! self::cpfm_single_trigger( $sub, $config, $state ) ) {
						return false;
					}
				}
				return true;
			}

			if ( isset( $trigger['any'] ) && is_array( $trigger['any'] ) ) {
				foreach ( $trigger['any'] as $sub ) {
					if ( self::cpfm_single_trigger( $sub, $config, $state ) ) {
						return true;
					}
				}
				return false;
			}

			return self::cpfm_single_trigger( $trigger, $config, $state );
		}

		/**
		 * Evaluate one trigger definition.
		 *
		 * @param array<string, mixed> $trigger Trigger definition.
		 * @param array<string, mixed> $config  Plugin config.
		 * @param array<string, mixed> $state   Current state.
		 * @return bool
		 */
		private static function cpfm_single_trigger( $trigger, $config, $state ) {
			$type = isset( $trigger['type'] ) ? (string) $trigger['type'] : 'install_age';

			switch ( $type ) {
				case 'install_age':
					$hours = isset( $trigger['hours'] ) ? (int) $trigger['hours'] : 72;
					if ( $hours < 0 ) {
						$hours = 0;
					}
					if ( ! $state['since'] ) {
						return false;
					}
					return ( time() - $state['since'] ) >= ( $hours * HOUR_IN_SECONDS );

				case 'action':
					$need = isset( $trigger['count'] ) ? max( 1, (int) $trigger['count'] ) : 1;
					return self::cpfm_counter( $config['id'] ) >= $need;

				case 'option_saved':
					$option = isset( $trigger['option'] ) ? (string) $trigger['option'] : '';
					if ( '' === $option ) {
						return false;
					}
					$value = get_option( $option );
					if ( false === $value ) {
						return false;
					}
					if ( isset( $trigger['value'] ) ) {
						return self::cpfm_value_matches( $value, $trigger['value'] );
					}
					return true;

				case 'manual':
					return ! empty( $state['eligible'] ) || self::cpfm_counter( $config['id'] ) > 0;
			}

			return false;
		}

		/**
		 * Read the action counter for a plugin.
		 *
		 * @param string $id Plugin id.
		 * @return int
		 */
		public static function cpfm_counter( $id ) {
			$state = self::cpfm_state( $id );
			return isset( $state['count'] ) ? (int) $state['count'] : 0;
		}

		/**
		 * Increment the action counter (for the `action` trigger).
		 *
		 * Hosts call this from their own success hook, e.g. after a countdown
		 * renders or a setting is saved. Cheap and idempotent-safe.
		 *
		 * @param string $id Plugin id.
		 * @param int    $by Increment.
		 * @return void
		 */
		public static function cpfm_bump( $id, $by = 1 ) {
			$state = self::cpfm_state( $id );
			if ( 'done' === $state['status'] ) {
				return; // Never accumulate for someone who already answered.
			}
			$state['count'] = ( isset( $state['count'] ) ? (int) $state['count'] : 0 ) + max( 1, (int) $by );
			self::cpfm_save_state( $id, $state );
		}

		/**
		 * Mark a `manual` trigger satisfied.
		 *
		 * @param string $id Plugin id.
		 * @return void
		 */
		public static function cpfm_mark_eligible( $id ) {
			$state = self::cpfm_state( $id );
			if ( 'done' === $state['status'] ) {
				return;
			}
			$state['eligible'] = true;
			self::cpfm_save_state( $id, $state );
		}

		/* ------------------------------------------------------------------
		 * Answers
		 * --------------------------------------------------------------- */

		/**
		 * Record a permanent answer: reviewed, or dismissed. Never asked again.
		 *
		 * Both outcomes are stored identically on purpose - we do not track
		 * whether someone liked the plugin, only that they answered.
		 *
		 * @param string $id Plugin id.
		 * @return void
		 */
		public static function cpfm_record_answer( $id ) {
			$config = self::cpfm_config( $id );
			$state  = self::cpfm_state( $id );

			$state['status'] = 'done';
			self::cpfm_save_state( $id, $state );

			// Keep any still-installed sibling that reads the old key in sync.
			if ( $config && ! empty( $config['legacy']['mirror_write'] ) ) {
				foreach ( (array) $config['legacy']['mirror_write'] as $key => $value ) {
					if ( is_string( $key ) && '' !== $key && is_scalar( $value ) ) {
						update_option( $key, $value, false );
					}
				}
			}
		}

		/**
		 * Snooze the ask ("Ask me later").
		 *
		 * @param string $id      Plugin id.
		 * @param int    $seconds Snooze length. Default 30 days.
		 * @return void
		 */
		public static function cpfm_snooze( $id, $seconds = 0 ) {
			$seconds = $seconds > 0 ? (int) $seconds : ( 30 * DAY_IN_SECONDS );
			$state   = self::cpfm_state( $id );

			if ( 'done' === $state['status'] ) {
				return;
			}
			$state['snooze_until'] = time() + $seconds;
			self::cpfm_save_state( $id, $state );
		}

		/**
		 * Is the current screen one of this plugin's own?
		 *
		 * @param string $id Plugin id.
		 * @return bool
		 */
		public static function cpfm_is_own_screen( $id ) {
			$config = self::cpfm_config( $id );
			if ( ! $config || empty( $config['own_screens'] ) ) {
				return false;
			}

			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();
			if ( ! $screen || empty( $screen->id ) ) {
				return false;
			}

			return in_array( $screen->id, (array) $config['own_screens'], true );
		}

		/**
		 * Record that a surface actually rendered.
		 *
		 * @param string $id          Plugin id.
		 * @param bool   $claim_quiet Whether this impression claims the shared
		 *                            cross-plugin window. Pass false for an ask
		 *                            on the plugin's OWN screen: it was exempt
		 *                            from the throttle, so letting it start a new
		 *                            window would silence every sibling for two
		 *                            weeks on the strength of an ask they were
		 *                            never competing with.
		 * @return void
		 */
		public static function cpfm_record_impression( $id, $claim_quiet = true ) {
			// An ask on the plugin's own screen is exempt from the shared throttle,
			// so it renders on EVERY visit. Persisting anything here would mean an
			// options UPDATE on every one of those page views — and nothing reads
			// 'asked' to make a decision, so that write buys nothing. Only a render
			// that claims the quiet window earns a write.
			if ( ! $claim_quiet ) {
				return;
			}

			$id    = sanitize_key( $id );
			$state = self::cpfm_state( $id );

			$state['asked'] = (int) $state['asked'] + 1;
			self::cpfm_save_state( $id, $state );

			$quiet = get_option( self::QUIET_OPTION );
			$quiet = is_array( $quiet ) ? $quiet : array();
			$quiet['id']   = $id;
			$quiet['time'] = time();

			if ( false === get_option( self::QUIET_OPTION ) ) {
				add_option( self::QUIET_OPTION, $quiet, '', 'no' );
			} else {
				update_option( self::QUIET_OPTION, $quiet, false );
			}
		}

		/**
		 * Is another plugin's recent ask suppressing this one?
		 *
		 * A site with six Cool Plugins addons must not produce six asks. First
		 * one to render claims the window; the others simply re-check later.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @return bool
		 */
		private static function cpfm_quiet_period_active( $config ) {
			$days = isset( $config['quiet_days'] ) ? (int) $config['quiet_days'] : self::QUIET_DAYS;
			if ( $days <= 0 ) {
				return false;
			}

			// On this plugin's OWN screen the throttle does not apply. Without
			// this, someone sitting on the Events Search settings page sees no
			// Search ask because Event Countdown asked on plugins.php days ago —
			// which is the throttle working on a screen it was never meant to
			// police. It exists for shared screens.
			if ( self::cpfm_is_own_screen( $config['id'] ) ) {
				return false;
			}

			$quiet = get_option( self::QUIET_OPTION );
			if ( ! is_array( $quiet ) || empty( $quiet['id'] ) || empty( $quiet['time'] ) ) {
				return false;
			}

			$claimer = sanitize_key( $quiet['id'] );

			if ( $claimer === $config['id'] ) {
				return false; // Our own window never blocks us.
			}

			// The lock only works if that plugin can actually show an ask
			// this request. A sibling that asked last week and was then
			// deactivated must not silence everyone else for the rest of
			// the window — nothing is on screen to "use" that slot.
			if ( ! isset( self::$plugins[ $claimer ] ) ) {
				return false;
			}

			return ( time() - (int) $quiet['time'] ) < ( $days * DAY_IN_SECONDS );
		}

		/* ------------------------------------------------------------------
		 * Housekeeping
		 * --------------------------------------------------------------- */

		/**
		 * Remove this plugin's framework state. For the host's uninstall.php.
		 *
		 * Legacy keys are deliberately left alone - a sibling plugin may still
		 * read them.
		 *
		 * @param string $id Plugin id.
		 * @return void
		 */
		public static function cpfm_uninstall( $id ) {
			$id = sanitize_key( $id );
			delete_option( self::cpfm_state_option( $id ) );
			unset( self::$cache[ $id ] );

			$quiet = get_option( self::QUIET_OPTION );
			if ( is_array( $quiet ) && isset( $quiet['id'] ) && sanitize_key( $quiet['id'] ) === $id ) {
				delete_option( self::QUIET_OPTION );
			}
		}

		/**
		 * Reset in-memory registry + cache. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {
			self::$plugins = array();
			self::$cache   = array();
		}
	}
}
