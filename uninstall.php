<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/queue/class-queue-table.php';
\AUTOMLP_WPML\Includes\Queue\Queue_Table::uninstall();

delete_option( 'automlp_jobs_db_version' );
delete_option( 'automlp_queue_last_run' );
delete_option( 'automlp_queue_last_cleanup' );
delete_option( 'automlp_debug_mode' );
// Deliberately kept so a reinstall doesn't re-run the wizard:
// automlp_ai_setup_complete