<?php
/**
 * Uninstall cleanup: removes the queue table and plugin options.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Free-only bookkeeping.
wp_clear_scheduled_hook( 'automlp_extra_data_update' );
wp_clear_scheduled_hook( 'automlp-ai_extra_data_update' );
delete_option( 'automlp_ai_activation_time' );
delete_option( 'automlp_ai_install_date' );
delete_option( 'automlp_ai_initial_save_version' );
delete_option( 'AUTOMLP_AI_VERSION' );

// CPFM feedback framework: the review-ask state of this plugin. Shared flags that other
// Cool Plugins products also read (the usage consent choice) are left alone.
delete_option( 'cpfm_review_state_automlp' );
$automlp_review_quiet = get_option( 'cpfm_review_last_shown' );
if ( is_array( $automlp_review_quiet ) && isset( $automlp_review_quiet['id'] ) && 'automlp' === sanitize_key( $automlp_review_quiet['id'] ) ) {
	delete_option( 'cpfm_review_last_shown' );
}

// Pro shares this queue, bot user and options. Keep them while Pro is still
// installed so switching to Pro does not lose queued jobs.
if ( file_exists( WP_PLUGIN_DIR . '/automlp-pro/automlp-pro.php' ) ) {
	return;
}

require_once __DIR__ . '/includes/queue/class-queue-table.php';
\AUTOMLP_WPML\Includes\Queue\Queue_Table::uninstall();

delete_option( 'automlp_jobs_db_version' );
delete_option( 'automlp_queue_last_run' );
delete_option( 'automlp_queue_last_cleanup' );
delete_option( 'automlp_queue_lock' );
foreach ( array( 2, 3, 4, 5, 6 ) as $automlp_slot ) {
	delete_option( 'automlp_queue_lock_' . $automlp_slot );
}
delete_option( 'automlp_queue_tuning' );
delete_transient( 'automlp_queue_health' );
delete_transient( 'automlp_queue_backoff' );
wp_clear_scheduled_hook( 'automlp_run_queue' );
delete_transient( 'automlp_queue_lock' );
delete_transient( 'automlp_pending_provider' );
delete_option( 'automlp_debug_mode' );
// Removed setting: translations now follow WPML's translated document status.
delete_option( 'automlp_bulk_post_status' );

require_once __DIR__ . '/includes/wpml/class-bot-translator.php';
\AUTOMLP_WPML\Includes\Wpml\Bot_Translator::remove();

delete_option( 'automlp_background_flow' );
delete_option( 'automlp_bot_user_id' );
delete_option( 'automlp_bot_pairs_signature' );

// One-time loopback tokens used by the queue dispatcher.
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'automlp_tick_used_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
