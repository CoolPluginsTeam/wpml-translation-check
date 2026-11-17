<?php
/**
 * CPFM Welcome Notice — the one-time "major update is here" announcement.
 *
 * For EXISTING users only: the host's Schema sets the gate option to 'show'
 * when it detects an upgrade from a pre-2.0 install. A fresh install never sets
 * it, so it never sees this.
 *
 * INTEGRATION (any plugin, one call):
 *
 *   CPFM_Welcome_Notice::cpfm_register( array(
 *       'id'           => 'tecc',
 *       'option'       => 'tecc_v2_welcome',   // 'show' | 'done'
 *       'settings_url' => admin_url( 'admin.php?page=...' ),
 *       'screens'      => array( 'plugins', '<settings screen id>' ),
 *       'i18n'         => array( ... ),        // ALREADY TRANSLATED by the host
 *   ) );
 *
 * Three exits, deliberately different:
 *   "See new settings"  -> saves the dismissal AND goes to the panel
 *   "Dismiss"           -> saves the dismissal
 *   the x               -> soft close, saves NOTHING, so it returns next load
 *
 * As with CPFM_Review, this never calls __(): the host passes translated copy,
 * so the strings stay extractable in the host's own domain.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Welcome_Notice' ) ) {

	/**
	 * Registry + renderer for the one-time upgrade announcement.
	 */
	final class CPFM_Welcome_Notice {

		/**
		 * Registered plugin configs, keyed by id.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		private static $plugins = array();

		/**
		 * Whether a notice already rendered this request.
		 *
		 * @var bool
		 */
		private static $rendered = false;

		/**
		 * Whether the shared assets were printed this request.
		 *
		 * @var bool
		 */
		private static $printed = false;

		/**
		 * Register a plugin's welcome notice.
		 *
		 * @param array<string, mixed> $config See the file docblock.
		 * @return bool
		 */
		public static function cpfm_register( $config ) {
			$config = is_array( $config ) ? $config : array();

			$id = isset( $config['id'] ) ? sanitize_key( $config['id'] ) : '';
			if ( '' === $id || empty( $config['option'] ) ) {
				return false;
			}

			$config = array_merge(
				array(
					'id'             => $id,
					'option'         => '',
					'capability'     => 'manage_options',
					'settings_url'   => '',
					'screens'        => array(),
					// Opt out of core's notice relocation (custom full-bleed pages).
					'inline_screens' => array(),
					// Screens where the HOST renders it itself via
					// maybe_render( false ); the admin_notices hook stays quiet.
					'defer_screens'  => array(),
					'i18n'           => array(),
				),
				$config
			);

			$config['id']     = $id;
			$config['option'] = sanitize_key( $config['option'] );

			self::$plugins[ $id ] = $config;

			self::cpfm_boot();

			return true;
		}

		/**
		 * Wire the shared hooks exactly once, however many plugins register.
		 *
		 * @return void
		 */
		private static function cpfm_boot() {
			static $booted = false;
			if ( $booted ) {
				return;
			}
			$booted = true;

			if ( ! function_exists( 'add_action' ) ) {
				return;
			}
			add_action( 'admin_init', array( __CLASS__, 'cpfm_handle_dismiss' ) );
			add_action( 'admin_notices', array( __CLASS__, 'cpfm_maybe_render' ) );
		}

		/**
		 * Config for an id, or null.
		 *
		 * @param string $id Plugin id.
		 * @return array<string, mixed>|null
		 */
		public static function cpfm_config( $id ) {
			$id = sanitize_key( $id );
			return isset( self::$plugins[ $id ] ) ? self::$plugins[ $id ] : null;
		}

		/**
		 * Persistently dismiss via the nonce-guarded link, then clean the URL.
		 *
		 * @return void
		 */
		public static function cpfm_handle_dismiss() {
			if ( ! isset( $_GET['cpfm-welcome'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked below, after we know which plugin.
				return;
			}

			$id     = sanitize_key( wp_unslash( $_GET['cpfm-welcome'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ditto.
			$config = self::cpfm_config( $id );
			if ( ! $config ) {
				return;
			}

			check_admin_referer( 'cpfm_welcome_' . $id );

			if ( ! current_user_can( $config['capability'] ) ) {
				return;
			}

			update_option( $config['option'], 'done' );

			wp_safe_redirect( remove_query_arg( array( 'cpfm-welcome', '_wpnonce' ) ) );
			exit;
		}

		/**
		 * Render the first eligible plugin's notice on an allowed screen.
		 *
		 * @param bool $from_hook True when called by `admin_notices`. WP_Hook
		 *                        always supplies one argument (the empty string),
		 *                        so only a literal false counts as a direct call.
		 * @return void
		 */
		public static function cpfm_maybe_render( $from_hook = true ) {
			$from_hook = ( false !== $from_hook );

			if ( self::$rendered ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || empty( $screen->id ) ) {
				return;
			}

			foreach ( self::$plugins as $id => $config ) {
				if ( ! current_user_can( $config['capability'] ) ) {
					continue;
				}
				if ( 'show' !== get_option( $config['option'] ) ) {
					continue;
				}
				if ( ! in_array( $screen->id, (array) $config['screens'], true ) ) {
					continue;
				}
				if ( $from_hook && in_array( $screen->id, (array) $config['defer_screens'], true ) ) {
					continue;
				}

				self::cpfm_render( $config, $screen );
				self::$rendered = true;
				return;
			}
		}

		/**
		 * Host-supplied string with a developer-English fallback.
		 *
		 * @param array<string, mixed> $config  Plugin config.
		 * @param string               $key     Copy key.
		 * @param string               $default Fallback.
		 * @return string
		 */
		private static function cpfm_text( $config, $key, $default ) {
			if ( isset( $config['i18n'][ $key ] ) && is_string( $config['i18n'][ $key ] ) && '' !== $config['i18n'][ $key ] ) {
				return $config['i18n'][ $key ];
			}
			return $default;
		}

		/**
		 * Emit the notice.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @param WP_Screen            $screen Current screen.
		 * @return void
		 */
		private static function cpfm_render( $config, $screen ) {

			$id     = $config['id'];
			$action = 'cpfm_welcome_' . $id;

			$settings = $config['settings_url'] ? $config['settings_url'] : admin_url();
			$explore  = wp_nonce_url( add_query_arg( 'cpfm-welcome', $id, $settings ), $action );
			$dismiss  = wp_nonce_url( add_query_arg( 'cpfm-welcome', $id ), $action );

			$inline = in_array( $screen->id, (array) $config['inline_screens'], true ) ? ' inline' : '';
			?>
			<div class="notice notice-info cpfm-welcome<?php echo esc_attr( $inline ); ?>" data-cpfm-welcome>
				<?php // The x only CLOSES it - nothing is saved, so it returns next load. ?>
				<button type="button" class="cpfm-welcome__x" data-cpfm-welcome-close
					aria-label="<?php echo esc_attr( self::cpfm_text( $config, 'close_label', 'Close' ) ); ?>">&times;</button>
				<p class="cpfm-welcome__row">
					<span class="dashicons dashicons-megaphone cpfm-welcome__icon" aria-hidden="true"></span>
					<span class="cpfm-welcome__text">
						<strong><?php echo esc_html( self::cpfm_text( $config, 'headline', 'A major update is here.' ) ); ?></strong>
						<?php echo esc_html( self::cpfm_text( $config, 'body', 'The plugin has been rewritten completely, with new features and designs.' ) ); ?>
					</span>
					<?php // Both of these SAVE the dismissal - unlike the x above. ?>
					<a class="button button-primary button-small" href="<?php echo esc_url( $explore ); ?>">
						<?php echo esc_html( self::cpfm_text( $config, 'cta', 'See new settings' ) ); ?>
					</a>
					<a class="cpfm-welcome__dismiss" href="<?php echo esc_url( $dismiss ); ?>">
						<?php echo esc_html( self::cpfm_text( $config, 'dismiss', 'Dismiss' ) ); ?>
					</a>
				</p>
			</div>
			<?php
			self::cpfm_enqueue_assets();
		}

		/**
		 * This surface's own CSS + JS (data-cpfm-welcome-close etc.) - NOT
		 * CPFM_Review_Assets, which is a different module's shared script for
		 * the unrelated data-cpfm-rv review surfaces.
		 *
		 * Called synchronously from cpfm_render(), on `admin_notices` - always
		 * before `admin_footer`/`wp_footer` - so a plain wp_enqueue_style()/
		 * wp_enqueue_script() call here is still picked up by WordPress's own
		 * automatic footer pass.
		 *
		 * @return void
		 */
		private static function cpfm_enqueue_assets() {
			if ( self::$printed ) {
				return;
			}
			self::$printed = true;

			wp_enqueue_style( 'cpfm-welcome-notice', self::cpfm_url( 'css/cpfm-welcome-notice.css' ), array(), self::cpfm_version( 'css/cpfm-welcome-notice.css' ) );
			wp_enqueue_script( 'cpfm-welcome-notice', self::cpfm_url( 'js/cpfm-welcome-notice.js' ), array(), self::cpfm_version( 'js/cpfm-welcome-notice.js' ), true );
		}

		/**
		 * URL to a file under this module's own folder (CPFM_URL from the loader).
		 *
		 * @param string $relative Path relative to admin/cpfm-feedback/.
		 * @return string
		 */
		private static function cpfm_url( $relative ) {
			return CPFM_URL . ltrim( $relative, '/' );
		}

		/**
		 * Cache-busting version: the asset's own mtime, so a vendored copy
		 * always advertises its own freshness with no version constant needed.
		 *
		 * @param string $relative Path relative to admin/cpfm-feedback/.
		 * @return string
		 */
		private static function cpfm_version( $relative ) {
			$path  = CPFM_DIR . ltrim( $relative, '/' );
			$mtime = file_exists( $path ) ? filemtime( $path ) : false;
			return $mtime ? (string) $mtime : '1.0.0';
		}

		/**
		 * Reset registry + per-request guards. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {
			self::$plugins  = array();
			self::$rendered = false;
			self::$printed  = false;
		}
	}
}
