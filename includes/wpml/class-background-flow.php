<?php
/**
 * Wires the background translation flow together.
 *
 * Everything runs behind a feature flag so the new pipeline can be enabled on
 * staging while the existing browser-driven flow stays untouched in production.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

use AUTOMLP_WPML\Includes\Queue\Dispatcher;
use AUTOMLP_WPML\Includes\Queue\Queue_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background_Flow
 */
class Background_Flow {

	const FLAG = 'automlp_background_flow';

	/**
	 * Is the background flow switched on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Filter whether the queue-based flow is active.
		 *
		 * Define AUTOMLP_BACKGROUND_FLOW in wp-config.php to force it on for
		 * testing without touching the option.
		 *
		 * @param bool $enabled Current state.
		 */
		$enabled = ( defined( 'AUTOMLP_BACKGROUND_FLOW' ) && AUTOMLP_BACKGROUND_FLOW )
			|| 'yes' === get_option( self::FLAG, 'no' );

		return (bool) apply_filters( 'automlp_background_flow_enabled', $enabled );
	}

	/**
	 * Boot the pipeline.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! self::enabled() ) {
			return;
		}

		Bot_Translator::boot();
		Job_Listener::boot();
		Writer::boot();

		add_action( 'admin_init', array( __CLASS__, 'ensure_translator' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * Keep the translator account registered.
	 *
	 * Cheap after the first run: register_with_wpml() short-circuits on a
	 * signature match.
	 *
	 * @return void
	 */
	public static function ensure_translator() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		Bot_Translator::ensure();
	}

	/**
	 * Warn when the queue cannot drain.
	 *
	 * @return void
	 */
	public static function render_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || false === strpos( $screen->id, 'automlp' ) ) {
			return;
		}

		$health = Dispatcher::health();

		if ( 'ok' === $health['state'] ) {
			return;
		}

		$pending = (int) $health['queue_counts'][ Queue_Table::STATE_WAITING ];

		if ( $health['wp_cron_off'] ) {
			$message = __( 'WP-Cron is disabled on this site, so queued translations will not run automatically. Add a real cron job that calls wp-cron.php, or use the Process now button.', 'wpml-translation-check' );
		} elseif ( ! $health['next_run'] ) {
			$message = __( 'The translation queue is not scheduled. Deactivate and reactivate AutoMLP to restore it.', 'wpml-translation-check' );
		} else {
			$message = __( 'The translation queue has not run recently. Queued translations may be delayed.', 'wpml-translation-check' );
		}

		if ( $pending > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of waiting jobs */
				_n( '%d job is waiting.', '%d jobs are waiting.', $pending, 'wpml-translation-check' ),
				$pending
			);
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'AutoMLP:', 'wpml-translation-check' ),
			esc_html( $message )
		);
	}
}
