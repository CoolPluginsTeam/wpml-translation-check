<?php
/**
 * CPFM Review - shared front-end behaviour for every surface.
 *
 * All surfaces (notice, plugin row, inline embed) emit the same data-attribute
 * contract, so ONE delegated click handler drives all of them:
 *
 *   [data-cpfm-rv]            the container; carries -id and -nonce
 *   [data-cpfm-rv-yes]        two_step only: reveal step 2, record nothing
 *   [data-cpfm-rv-answer]     final answer: record, then hide
 *   [data-cpfm-rv-later]      server-side snooze
 *   [data-cpfm-rv-close]      browser-only hide (default 24h)
 *
 * Enqueued once per request, and only from a surface that actually rendered.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Review_Assets' ) ) {

	/**
	 * Shared asset enqueuer.
	 */
	final class CPFM_Review_Assets {

		/**
		 * Whether the shared script has already been emitted this request.
		 *
		 * @var bool
		 */
		private static $printed = false;

		/**
		 * Enqueue the delegated click handler exactly once.
		 *
		 * Called from each surface's own render path (not on `admin_enqueue_scripts`)
		 * because eligibility is only known once a surface actually renders - but
		 * that call always happens before `admin_footer`/`wp_footer`, so a plain
		 * `wp_enqueue_script()` (in_footer = true) is picked up by WordPress's own
		 * automatic footer pass (`_wp_footer_scripts()` -> `print_footer_scripts()`)
		 * without us having to force-print it. Verified against wp-includes'
		 * real WP_Scripts::do_item()/do_footer_items(): a footer-group handle
		 * enqueued at any point before that pass runs still gets printed, because
		 * the pass re-scans the live queue rather than a snapshot taken earlier.
		 *
		 * Do NOT hook this behind `admin_print_footer_scripts`/`wp_footer` -
		 * core's own `_wp_footer_scripts()` is registered on that same action
		 * during bootstrap, so it always runs before a plugin's later-registered
		 * callback on it; enqueuing from inside that hook is enqueuing AFTER the
		 * one pass that would have printed it, and the asset is silently dropped.
		 *
		 * @return void
		 */
		public static function cpfm_enqueue_js() {
			if ( self::$printed ) {
				return;
			}
			self::$printed = true;

			wp_enqueue_script( 'cpfm-review', self::cpfm_url( 'js/cpfm-review.js' ), array(), self::cpfm_version( 'js/cpfm-review.js' ), true );
		}

		/**
		 * Enqueue one of this surface's own stylesheets.
		 *
		 * Shared by the three review surfaces (notice, row, inline) so the
		 * register+enqueue pair lives in one place rather than being copied at
		 * each call site. See cpfm_enqueue_js() for why a plain wp_enqueue_style()
		 * call here - even from deep in a render path, well before admin_footer -
		 * is still printed: WordPress's automatic print_late_styles() footer pass
		 * catches any style enqueued before it runs, styles having no head/footer
		 * group split the way scripts do.
		 *
		 * @param string $handle Style handle, unique per surface.
		 * @param string $file   Filename under admin/cpfm-feedback/css/.
		 * @return void
		 */
		public static function cpfm_enqueue_css( $handle, $file ) {

			wp_enqueue_style( $handle, self::cpfm_url( 'css/' . $file ), array(), self::cpfm_version( 'css/' . $file ) );
		}

		/**
		 * URL to a file under admin/cpfm-feedback/ (CPFM_URL from the loader).
		 *
		 * @param string $relative Path relative to admin/cpfm-feedback/.
		 * @return string
		 */
		private static function cpfm_url( $relative ) {
			return CPFM_URL . ltrim( $relative, '/' );
		}

		/**
		 * Cache-busting version for a file under admin/cpfm-feedback/: the
		 * asset's own mtime, so a vendored copy always advertises its own
		 * freshness without needing any plugin-specific version constant.
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
		 * A surface's own CSS plus the shared click handler, in one call.
		 *
		 * The single place every surface (notice, row, inline) delegates to for
		 * "enqueue my styles and the shared JS" - each used to keep its own
		 * copy of this pairing, plus its own print-once guard, even though
		 * wp_enqueue_style()/wp_enqueue_script() already dedupe by handle for
		 * the life of the request (verified against the real WP_Dependencies
		 * `$done` set), so a per-surface guard was never load-bearing for
		 * correctness - only cpfm_enqueue_js()'s own guard is, since it is
		 * shared across all three surfaces.
		 *
		 * @param string $handle Style handle, unique per surface.
		 * @param string $file   Filename under admin/cpfm-feedback/css/.
		 * @return void
		 */
		public static function cpfm_enqueue( $handle, $file ) {
			
			self::cpfm_enqueue_css( $handle, $file );
			self::cpfm_enqueue_js();
		}

		/**
		 * The click-handler nonce every surface embeds identically.
		 *
		 * @param string $id Plugin id.
		 * @return string
		 */
		public static function cpfm_nonce( $id ) {

			return wp_create_nonce( CPFM_Review::cpfm_nonce_action( $id ) );
		}

		/**
		 * The five-star row every surface opens with.
		 *
		 * @return string
		 */
		public static function cpfm_stars() {

			return str_repeat( '<span class="dashicons dashicons-star-filled"></span>', 5 );
		}

		/**
		 * Host-supplied string with a developer-English fallback (plan R1).
		 *
		 * Shared by the three review surfaces (notice, row, inline) - each used
		 * to carry its own byte-identical copy.
		 *
		 * @param array<string, mixed> $config  Plugin config.
		 * @param string               $key     Copy key.
		 * @param string               $default Fallback (untranslated on purpose).
		 * @return string
		 */
		public static function cpfm_text( $config, $key, $default ) {

			if ( isset( $config['i18n'][ $key ] ) && is_string( $config['i18n'][ $key ] ) && '' !== $config['i18n'][ $key ] ) {
				return $config['i18n'][ $key ];
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {

				_doing_it_wrong(
					'CPFM_Review::cpfm_register',
					esc_html( sprintf( 'Missing translated i18n key "%s" for plugin "%s".', $key, $config['id'] ) ),
					'1.0.0'
				);
			}

			return $default;
		}

		/**
		 * Reset the per-request guard. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {
			
			self::$printed = false;
		}
	}
}
