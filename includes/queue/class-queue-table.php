<?php
/**
 * Queue storage for background translation jobs.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue_Table
 *
 * Owns the wp_automlp_jobs table: schema and all reads/writes.
 * Payload columns (source_map, request_payload, response_payload) are written
 * only when debug mode is on, and are cleared when a job completes.
 */
class Queue_Table {

	const DB_VERSION_OPTION = 'automlp_jobs_db_version';
	const DB_VERSION        = '1.0.0';
	const CACHE_GROUP       = 'automlp_jobs';

	/* Job states. */
	const STATE_WAITING = 'waiting';
	const STATE_CLAIMED = 'claimed';
	const STATE_SENT    = 'sent';
	const STATE_WRITING = 'writing';
	const STATE_DONE    = 'done';
	const STATE_FAILED  = 'failed';
	const STATE_STOPPED = 'stopped';

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'automlp_jobs';
	}

	/* ------------------------------------------------------------------
	 *  Schema
	 * ------------------------------------------------------------------ */

	/**
	 * Create the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "CREATE TABLE {$table} (
			job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wpml_job_id bigint(20) unsigned DEFAULT NULL,
			wpml_rid bigint(20) unsigned DEFAULT NULL,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			result_id bigint(20) unsigned DEFAULT NULL,
			kind varchar(24) NOT NULL DEFAULT 'post',
			from_lang varchar(16) NOT NULL DEFAULT '',
			to_lang varchar(16) NOT NULL DEFAULT '',
			state varchar(16) NOT NULL DEFAULT 'waiting',
			provider varchar(32) DEFAULT NULL,
			model varchar(64) DEFAULT NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			field_count int(11) NOT NULL DEFAULT 0,
			fields_translated int(11) NOT NULL DEFAULT 0,
			char_count bigint(20) unsigned NOT NULL DEFAULT 0,
			source_map longtext DEFAULT NULL,
			request_payload longtext DEFAULT NULL,
			response_payload longtext DEFAULT NULL,
			queued_at datetime NOT NULL,
			touched_at datetime NOT NULL,
			closed_at datetime DEFAULT NULL,
			PRIMARY KEY  (job_id),
			KEY state_lookup (state, job_id),
			KEY wpml_job_id (wpml_job_id),
			KEY wpml_rid (wpml_rid),
			KEY source_id (source_id),
			KEY queued_at (queued_at)
		) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop the table. Called from uninstall.php only.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Run install() when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '0' ), self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/* ------------------------------------------------------------------
	 *  Cache helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Invalidate every cached read.
	 *
	 * @return void
	 */
	public static function bump_cache() {
		wp_cache_set( 'gen', microtime( true ), self::CACHE_GROUP );
	}

	/**
	 * Current cache generation token.
	 *
	 * @return string
	 */
	private static function cache_gen() {
		$gen = wp_cache_get( 'gen', self::CACHE_GROUP );
		if ( false === $gen ) {
			$gen = microtime( true );
			wp_cache_set( 'gen', $gen, self::CACHE_GROUP );
		}
		return (string) $gen;
	}

	/**
	 * Is debug logging on? Controls whether payloads are persisted.
	 *
	 * @return bool
	 */
	public static function debug_enabled() {
		return 'yes' === get_option( 'automlp_debug_mode', 'no' );
	}

	/* ------------------------------------------------------------------
	 *  CRUD
	 * ------------------------------------------------------------------ */

	/**
	 * Insert a new waiting job.
	 *
	 * @param array $args Column values.
	 * @return int Inserted job id, or 0 on failure.
	 */
	public static function add( array $args ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = wp_parse_args(
			$args,
			array(
				'wpml_job_id'       => null,
				'wpml_rid'          => null,
				'source_id'         => 0,
				'kind'              => 'post',
				'from_lang'         => '',
				'to_lang'           => '',
				'state'             => self::STATE_WAITING,
				'field_count'       => 0,
				'fields_translated' => 0,
				'char_count'        => 0,
				'source_map'        => null,
				'queued_at'         => $now,
				'touched_at'        => $now,
			)
		);

		if ( is_array( $row['source_map'] ) ) {
			$row['source_map'] = wp_json_encode( $row['source_map'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( self::table(), $row );

		if ( ! $ok ) {
			return 0;
		}

		self::bump_cache();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a job by id.
	 *
	 * @param int   $job_id Job id.
	 * @param array $args   Column values.
	 * @return bool
	 */
	public static function edit( $job_id, array $args ) {
		global $wpdb;

		$args['touched_at'] = current_time( 'mysql', true );

		foreach ( array( 'source_map', 'request_payload', 'response_payload' ) as $json_col ) {
			if ( isset( $args[ $json_col ] ) && is_array( $args[ $json_col ] ) ) {
				$args[ $json_col ] = wp_json_encode( $args[ $json_col ] );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$done = $wpdb->update( self::table(), $args, array( 'job_id' => (int) $job_id ), null, array( '%d' ) );

		self::bump_cache();
		return false !== $done;
	}

	/**
	 * Update rows matched by WPML job id.
	 *
	 * @param int   $wpml_job_id icl_translate_job.job_id.
	 * @param array $args        Column values.
	 * @return bool
	 */
	public static function edit_by_wpml_job( $wpml_job_id, array $args ) {
		global $wpdb;

		$args['touched_at'] = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$done = $wpdb->update( self::table(), $args, array( 'wpml_job_id' => (int) $wpml_job_id ), null, array( '%d' ) );

		self::bump_cache();
		return false !== $done;
	}

	/**
	 * Fetch a single job.
	 *
	 * @param int $job_id Job id.
	 * @return array|null
	 */
	public static function get( $job_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE job_id = %d', self::table(), (int) $job_id ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Decode a job's stored source map.
	 *
	 * @param array $row Job row.
	 * @return array field_name => array{tid:int,text:string}
	 */
	public static function read_source_map( array $row ) {
		if ( empty( $row['source_map'] ) ) {
			return array();
		}
		$decoded = json_decode( $row['source_map'], true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/* ------------------------------------------------------------------
	 *  State transitions
	 * ------------------------------------------------------------------ */

	/**
	 * Atomically take ownership of a waiting job.
	 *
	 * Returns true only for the process that won the race, so parallel cron
	 * runs cannot send the same job twice.
	 *
	 * @param int $job_id Job id.
	 * @return bool
	 */
	public static function claim( $job_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET state = %s, touched_at = %s WHERE job_id = %d AND state = %s',
				self::table(),
				self::STATE_CLAIMED,
				current_time( 'mysql', true ),
				(int) $job_id,
				self::STATE_WAITING
			)
		);

		$won = $wpdb->rows_affected > 0;

		if ( $won ) {
			self::bump_cache();
		}

		return $won;
	}

	/**
	 * Mark a job finished and drop its payload columns.
	 *
	 * @param int   $job_id Job id.
	 * @param array $extra  Additional columns to set.
	 * @return bool
	 */
	public static function finish( $job_id, array $extra = array() ) {
		$args = array_merge(
			$extra,
			array(
				'state'            => self::STATE_DONE,
				'closed_at'        => current_time( 'mysql', true ),
				'source_map'       => null,
				'request_payload'  => null,
				'response_payload' => null,
			)
		);

		return self::edit( $job_id, $args );
	}

	/**
	 * Record a failure, retrying while attempts remain.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $message     Error text.
	 * @param int    $max_retries Attempt ceiling.
	 * @return void
	 */
	public static function fail( $job_id, $message, $max_retries = 3 ) {
		$row = self::get( $job_id );

		if ( ! $row ) {
			return;
		}

		$attempts = (int) $row['attempts'] + 1;

		if ( $attempts <= (int) $max_retries ) {
			self::edit(
				$job_id,
				array(
					'state'             => self::STATE_WAITING,
					'attempts'          => $attempts,
					'last_error'        => $message,
					'fields_translated' => 0,
				)
			);
			return;
		}

		self::edit(
			$job_id,
			array(
				'state'             => self::STATE_FAILED,
				'attempts'          => $attempts,
				'last_error'        => $message,
				'fields_translated' => 0,
				'closed_at'         => current_time( 'mysql', true ),
				'request_payload'   => null,
				'response_payload'  => null,
			)
		);
	}

	/* ------------------------------------------------------------------
	 *  Progress
	 * ------------------------------------------------------------------ */

	/**
	 * Persist batch progress after each AI batch.
	 *
	 * @param int $job_id            Job id.
	 * @param int $fields_translated Fields translated so far.
	 * @param int $fields_total      Total fields in this job.
	 * @return bool
	 */
	public static function update_progress( $job_id, $fields_translated, $fields_total ) {
		$fields_total      = max( 1, (int) $fields_total );
		$fields_translated = min( max( 0, (int) $fields_translated ), $fields_total );

		return self::edit(
			$job_id,
			array(
				'fields_translated' => $fields_translated,
				'field_count'       => $fields_total,
			)
		);
	}

	/**
	 * Percent complete for UI progress bars (0–100).
	 *
	 * Derived from fields_translated / field_count at read time.
	 *
	 * @param array $row Queue row.
	 * @return int
	 */
	public static function progress_percent( array $row ) {
		$state = isset( $row['state'] ) ? (string) $row['state'] : self::STATE_WAITING;

		if ( self::STATE_DONE === $state ) {
			return 100;
		}

		if ( in_array( $state, array( self::STATE_FAILED, self::STATE_STOPPED ), true ) ) {
			return 0;
		}

		$total = isset( $row['field_count'] ) ? (int) $row['field_count'] : 0;
		$done  = isset( $row['fields_translated'] ) ? (int) $row['fields_translated'] : 0;

		if ( $total < 1 || $done < 1 ) {
			return 0;
		}

		return min( 100, max( 0, (int) round( ( $done / $total ) * 100 ) ) );
	}

	/* ------------------------------------------------------------------
	 *  Recovery
	 * ------------------------------------------------------------------ */

	/**
	 * Return jobs stuck mid-flight back to waiting.
	 *
	 * A crashed cron run leaves rows in 'claimed' or 'sent' forever. Anything
	 * untouched for longer than the timeout is assumed abandoned.
	 *
	 * @param int $minutes Staleness threshold.
	 * @return int Rows recovered.
	 */
	public static function recover_stale( $minutes = 15 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( absint( $minutes ) * MINUTE_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET state = %s, touched_at = %s, fields_translated = 0
				 WHERE state IN (%s, %s) AND touched_at < %s',
				self::table(),
				self::STATE_WAITING,
				current_time( 'mysql', true ),
				self::STATE_CLAIMED,
				self::STATE_SENT,
				$cutoff
			)
		);

		$count = (int) $wpdb->rows_affected;

		if ( $count > 0 ) {
			self::bump_cache();
		}

		return $count;
	}

	/* ------------------------------------------------------------------
	 *  Queries
	 * ------------------------------------------------------------------ */

	/**
	 * Cheap existence check so the cron can bail before doing real work.
	 *
	 * @return bool
	 */
	public static function has_open_jobs() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hit = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT job_id FROM %i WHERE state IN (%s, %s, %s, %s) LIMIT 1',
				self::table(),
				self::STATE_WAITING,
				self::STATE_CLAIMED,
				self::STATE_SENT,
				self::STATE_WRITING
			)
		);

		return ! empty( $hit );
	}

	/**
	 * Oldest waiting jobs, ready to be claimed.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,array>
	 */
	public static function waiting( $limit ) {
		global $wpdb;

		$limit = max( 1, (int) $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE state = %s ORDER BY job_id ASC LIMIT %d',
				self::table(),
				self::STATE_WAITING,
				$limit
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Snapshot of queue state for the admin UI.
	 *
	 * @return array<string,int>
	 */
	public static function summary() {
		$key    = 'summary:' . self::cache_gen();
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT state, COUNT(*) AS total FROM %i GROUP BY state', self::table() ),
			ARRAY_A
		);

		$out = array(
			self::STATE_WAITING => 0,
			self::STATE_CLAIMED => 0,
			self::STATE_SENT    => 0,
			self::STATE_WRITING => 0,
			self::STATE_DONE    => 0,
			self::STATE_FAILED  => 0,
			self::STATE_STOPPED => 0,
		);

		foreach ( (array) $rows as $row ) {
			$out[ $row['state'] ] = (int) $row['total'];
		}

		wp_cache_set( $key, $out, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $out;
	}

	/**
	 * Jobs for the admin queue view, newest first.
	 *
	 * @param array $args page, per_page, states.
	 * @return array{rows:array,total:int,pages:int}
	 */
	public static function browse( array $args = array() ) {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$states   = isset( $args['states'] ) && is_array( $args['states'] ) ? $args['states'] : array();
		$offset   = ( $page - 1 ) * $per_page;

		$key    = 'browse:' . md5( wp_json_encode( array( $page, $per_page, $states ) ) ) . ':' . self::cache_gen();
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$where  = '1=1';
		$params = array( self::table() );

		if ( ! empty( $states ) ) {
			$slots  = implode( ', ', array_fill( 0, count( $states ), '%s' ) );
			$where  = "state IN ({$slots})";
			$params = array_merge( $params, $states );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", $params )
		);

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT job_id, wpml_job_id, source_id, result_id, kind, from_lang, to_lang,
				        state, provider, model, attempts, last_error, field_count,
				        fields_translated, char_count, queued_at, closed_at
				 FROM %i WHERE {$where} ORDER BY job_id DESC LIMIT %d OFFSET %d",
				$params
			),
			ARRAY_A
		);

		$out = array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);

		wp_cache_set( $key, $out, self::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

		return $out;
	}

	/**
	 * In-flight jobs keyed by source id, for painting the post list.
	 *
	 * @param array $source_ids Post or term ids.
	 * @return array<int,array<string,string>> source_id => lang => state
	 */
	public static function in_flight_for( array $source_ids ) {
		if ( empty( $source_ids ) ) {
			return array();
		}

		global $wpdb;

		$source_ids = array_values( array_unique( array_map( 'absint', $source_ids ) ) );
		$slots      = implode( ', ', array_fill( 0, count( $source_ids ), '%d' ) );

		$params = array_merge(
			array( self::table() ),
			$source_ids,
			array( self::STATE_WAITING, self::STATE_CLAIMED, self::STATE_SENT, self::STATE_WRITING )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT source_id, to_lang, state FROM %i
				 WHERE source_id IN ({$slots}) AND state IN (%s, %s, %s, %s)",
				$params
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['source_id'] ][ $row['to_lang'] ] = $row['state'];
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 *  Housekeeping
	 * ------------------------------------------------------------------ */

	/**
	 * Strip payloads from old finished jobs and delete very old rows.
	 *
	 * @param int $slim_after_days  Days before payloads are cleared.
	 * @param int $purge_after_days Days before rows are deleted.
	 * @return void
	 */
	public static function prune( $slim_after_days = 30, $purge_after_days = 180 ) {
		global $wpdb;

		$slim_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( absint( $slim_after_days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET source_map = NULL, request_payload = NULL, response_payload = NULL
				 WHERE state IN (%s, %s, %s) AND closed_at IS NOT NULL AND closed_at < %s',
				self::table(),
				self::STATE_DONE,
				self::STATE_FAILED,
				self::STATE_STOPPED,
				$slim_cutoff
			)
		);

		$purge_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( absint( $purge_after_days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE state IN (%s, %s, %s) AND closed_at IS NOT NULL AND closed_at < %s',
				self::table(),
				self::STATE_DONE,
				self::STATE_FAILED,
				self::STATE_STOPPED,
				$purge_cutoff
			)
		);

		self::bump_cache();
	}

	/**
	 * Delete all finished rows (done/failed/stopped).
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_finished() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE state IN (%s, %s, %s)',
				self::table(),
				self::STATE_DONE,
				self::STATE_FAILED,
				self::STATE_STOPPED
			)
		);

		$count = (int) $wpdb->rows_affected;

		if ( $count > 0 ) {
			self::bump_cache();
		}

		return $count;
	}

	/**
	 * Claimed rows that never reached the AI step (fields_translated still zero).
	 *
	 * @param int $seconds Minimum age before treating as orphaned.
	 * @return array<int,array> Matching rows (job_id, wpml_job_id).
	 */
	public static function orphan_claimed_rows( $seconds = 60 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $seconds ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT job_id, wpml_job_id FROM %i
				 WHERE state = %s AND fields_translated = 0 AND touched_at < %s',
				self::table(),
				self::STATE_CLAIMED,
				$cutoff
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Delete claimed rows that never reached the AI step.
	 *
	 * @param int $seconds Minimum age before treating as orphaned.
	 * @return int Rows deleted.
	 */
	public static function delete_orphan_claimed( $seconds = 60 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $seconds ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE state = %s AND fields_translated = 0 AND touched_at < %s',
				self::table(),
				self::STATE_CLAIMED,
				$cutoff
			)
		);

		$count = (int) $wpdb->rows_affected;

		if ( $count > 0 ) {
			self::bump_cache();
		}

		return $count;
	}
}
