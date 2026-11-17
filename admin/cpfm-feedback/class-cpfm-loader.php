<?php
/**
 * CPFM Feedback — single entry point for the shared framework folder.
 *
 * Requires every module's class definition, each still guarded by its own
 * class_exists() check (so calling this twice, or a sibling plugin loading
 * a module first, is always safe). Loading a class does NOT wire any hooks
 * by itself - every module only activates once the host calls its own
 * cpfm_register()/cpfm_init(), same as before this file existed. A host
 * that vendored a PARTIAL copy of this folder degrades gracefully: a
 * missing module file is simply skipped, never fatal.
 *
 * INTEGRATION (any plugin, one call, from an is_admin() code path only):
 *
 *   if ( is_admin() ) {
 *       require_once __DIR__ . '/cpfm-feedback/class-cpfm-loader.php';
 *       CPFM_Loader::load();
 *   }
 *
 * DELIBERATE TRADE-OFF, host's own choice: every module here — including
 * the cron class — is loaded admin-only. A front-end page view never pays
 * for parsing any of this, but it also means an actual WP-Cron run
 * (wp-cron.php) is NOT an is_admin() request, so CPFM_Usage_Cron never
 * gets defined there either. A host that registers a scheduled event with
 * it (wp_schedule_event()) will find that event fires with no handler
 * attached — nothing happens, no error, no data sent. If a host needs its
 * cron to actually run in the background, call CPFM_Loader::load()
 * unconditionally instead (or only require cron/class-cron.php directly,
 * unconditionally, alongside this admin-gated call for everything else).
 *
 * This replaces the require_once + class_exists boilerplate that used to
 * be repeated at every module's own registration call site - it does NOT
 * replace those calls themselves. Each module's cpfm_register()/cpfm_init()
 * still needs the host's own config (translated strings, option names,
 * screens, hook names...) and stays exactly where it already is, deferred
 * to `init` where i18n requires that.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CPFM_DIR' ) ) {
	define( 'CPFM_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CPFM_URL' ) ) {
	define( 'CPFM_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! class_exists( 'CPFM_Loader' ) ) {

	/**
	 * Requires every cpfm-feedback module file, once.
	 */
	final class CPFM_Loader {

		/**
		 * Require every module's class definition. Safe to call more than
		 * once, and from more than one plugin.
		 *
		 * A host may vendor a partial copy of this folder; a missing module
		 * file is skipped, never fatal.
		 *
		 * @return void
		 */
		public static function load() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			$files = array(
				'class-cpfm-environment.php',
				'class-cpfm-review.php',
				'class-cpfm-welcome-notice.php',
				'cpfm-feedback-notice.php',
				'cpfm-deactivation-feedback.php',
				'cron/class-cron.php',
			);

			foreach ( $files as $file ) {
				$path = CPFM_DIR . $file;
				if ( file_exists( $path ) ) {
					require_once $path;
				}
			}
		}
	}
}
