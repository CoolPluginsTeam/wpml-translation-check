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

require_once __DIR__ . '/includes/wpml/class-bot-translator.php';
\AUTOMLP_WPML\Includes\Wpml\Bot_Translator::remove();

delete_option( 'automlp_background_flow' );
delete_option( 'automlp_bot_user_id' );
delete_option( 'automlp_bot_pairs_signature' );