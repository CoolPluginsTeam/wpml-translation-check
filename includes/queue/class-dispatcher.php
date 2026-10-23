<?php
/**
 * Cron worker that drains the translation queue.
 *
 * Replaces the browser-driven loop: jobs survive a closed tab, retry on
 * failure, and cannot be processed twice by parallel cron runs.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Queue;

use AUTOMLP_WPML\Includes\Providers\AI_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatcher
 */
class Dispatcher {

	const HOOK         = 'automlp_run_queue';
	const SCHEDULE     = 'automlp_every_minute';
	const LOCK         = 'automlp_queue_lock';
	const HEARTBEAT    = 'automlp_queue_last_run';
	const CLEANUP_FLAG = 'automlp_queue_last_cleanup';
	const TICK_ACTION  = 'automlp_queue_tick';
	const TUNING       = 'automlp_queue_tuning';
	const HEALTH       = 'automlp_queue_health';

	/**
	 * Default ceiling of parallel queue processes. The queue starts at
	 * START_SLOTS and climbs one slot after HEALTHY_RUNS healthy runs.
	 */
	const MAX_SLOTS = 4;

	/**
	 * Slots used before any health is measured.
	 */
	const START_SLOTS = 2;

	/**
	 * Hard ceiling for the slot filter and tuning option.
	 */
	const SLOTS_LIMIT = 6;

	/**
	 * Consecutive healthy runs before one more slot is allowed.
	 */
	const HEALTHY_RUNS = 3;

	/**
	 * Seconds a health verdict stays valid without being re-measured.
	 */
	const HEALTH_TTL = 180;

	/**
	 * A spawned request that waited less than this many seconds for a PHP
	 * worker shows spare capacity.
	 */
	const HEALTHY_DELAY = 1;

	/**
	 * Seconds a spawned request may wait for a PHP worker before the site is
	 * considered busy.
	 */
	const SATURATION_DELAY = 3;


	/**
	 * Seconds after which one AI call counts as slow.
	 */
	const SLOW_CALL = 45;

	/**
	 * Jobs handled per cron tick. Keeps any single run well inside
	 * max_execution_time on shared hosting.
	 */
	const JOBS_PER_RUN = 1;

	/**
	 * Attempts before a job is abandoned.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Lock lifetime in seconds. Longer than the worst single job step (one AI
	 * batch, capped by the gateway timeout, plus the WPML save) and refreshed
	 * between jobs, so a live run never loses its lock.
	 */
	const LOCK_TTL = 600;

	/**
	 * Minutes a claimed/sent/writing row may sit untouched before it is
	 * treated as orphaned. Must exceed LOCK_TTL.
	 */
	const STALE_MINUTES = 15;

	/**
	 * Default seconds a single run may keep claiming new jobs.
	 */
	const RUN_BUDGET = 25;

	/**
	 * Exception code for failures that retrying cannot fix.
	 */
	const PERMANENT_FAILURE = 1;

	/**
	 * Token identifying the lock held by this request, if any.
	 *
	 * @var string
	 */
	private static $lock_token = '';

	/**
	 * Option name of the slot held by this request, if any.
	 *
	 * @var string
	 */
	private static $lock_name = '';

	/**
	 * Whether a job failed during this request.
	 *
	 * @var bool
	 */
	private static $had_failure = false;

	/**
	 * Whether this request backed off for any reason.
	 *
	 * @var bool
	 */
	private static $strained = false;

	/**
	 * Whether this request translated at least one batch.
	 *
	 * @var bool
	 */
	private static $did_work = false;

	/**
	 * Seconds a spawned request waited for a PHP worker; null for cron.
	 *
	 * @var float|null
	 */
	private static $tick_delay = null;

	/**
	 * Loopback runs this request already started early.
	 *
	 * @var int
	 */
	private static $early_spawns = 0;

	/**
	 * Wire up cron.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
		add_action( 'wp_ajax_' . self::TICK_ACTION, array( __CLASS__, 'handle_tick' ) );
		add_action( 'wp_ajax_nopriv_' . self::TICK_ACTION, array( __CLASS__, 'handle_tick' ) );
	}

	/**
	 * Register the one-minute interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		if ( ! isset( $schedules[ self::SCHEDULE ] ) ) {
			$schedules[ self::SCHEDULE ] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => __( 'Every minute (AutoMLP queue)', 'wpml-translation-check' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule the event if it is missing.
	 *
	 * @return void
	 */
	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * Remove the event. Called on deactivation.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron entry point.
	 *
	 * @param int|null $limit Optional cap on jobs for this run. Null uses JOBS_PER_RUN.
	 * @return void
	 */
	public static function run( $limit = null ) {
		update_option( self::HEARTBEAT, time(), false );

		// Cheap check first: on an idle site this is the only query we run.
		if ( ! Queue_Table::has_open_jobs() ) {
			self::maybe_cleanup();
			return;
		}

		if ( ! self::acquire_lock() ) {
			return;
		}

		try {
			// Rows are touched after every AI batch, so only a run that died
			// leaves one untouched for STALE_MINUTES; parallel slots are safe.
			Queue_Table::recover_stale( self::STALE_MINUTES );
			self::process_batch( $limit );
		} catch ( \Throwable $e ) {
			self::log( 'Queue run aborted: ' . $e->getMessage() );
		} finally {
			self::release_lock();
		}

		self::maybe_cleanup();
		self::record_health();
		self::spawn();
	}

	/**
	 * Start queue workers now (non-blocking loopback), e.g. right after jobs
	 * were queued, instead of waiting for the next cron tick or a poll.
	 *
	 * @return void
	 */
	public static function kick() {
		self::spawn();
	}

	/**
	 * Read one advanced tuning value.
	 *
	 * Stored in the non-autoloaded automlp_queue_tuning option so it can be
	 * changed without code; filters still have the last word.
	 *
	 * @param string $key     Setting key.
	 * @param int    $fallback Value used when the setting is absent.
	 * @return int
	 */
	public static function tuning( $key, $fallback ) {
		$tuning = get_option( self::TUNING, array() );

		return ( is_array( $tuning ) && isset( $tuning[ $key ] ) && is_numeric( $tuning[ $key ] ) ) ? (int) $tuning[ $key ] : (int) $fallback;
	}

	/**
	 * Ceiling of parallel processes.
	 *
	 * @return int
	 */
	private static function max_slots() {
		/**
		 * Filter the maximum number of parallel queue processes.
		 *
		 * Each process holds one PHP worker while it runs. The queue climbs
		 * towards this ceiling while the site is healthy and drops back at
		 * once when strain is detected.
		 *
		 * @param int $slots Maximum slots, 1 to 6.
		 */
		$max = (int) apply_filters( 'automlp_queue_max_slots', self::tuning( 'max_slots', self::MAX_SLOTS ) );

		return max( 1, min( self::SLOTS_LIMIT, $max ) );
	}

	/**
	 * Slots usable right now: the measured level, within the ceiling.
	 *
	 * The level lives in a short-lived transient, so a stale "healthy"
	 * verdict expires and the queue falls back to START_SLOTS.
	 *
	 * @return int
	 */
	private static function slots() {
		$health = self::health_state();

		return max( 1, min( self::max_slots(), $health['slots'] ) );
	}

	/**
	 * Current health record.
	 *
	 * @return array{slots:int,streak:int}
	 */
	private static function health_state() {
		$health = get_transient( self::HEALTH );

		if ( ! is_array( $health ) || ! isset( $health['slots'], $health['streak'] ) ) {
			return array(
				'slots'  => self::START_SLOTS,
				'streak' => 0,
			);
		}

		return array(
			'slots'  => (int) $health['slots'],
			'streak' => (int) $health['streak'],
		);
	}

	/**
	 * Store the health record with a fresh short TTL.
	 *
	 * @param int $slots  Slot level.
	 * @param int $streak Consecutive healthy runs at this level.
	 * @return void
	 */
	private static function save_health( $slots, $streak ) {
		set_transient(
			self::HEALTH,
			array(
				'slots'  => max( 1, min( self::SLOTS_LIMIT, (int) $slots ) ),
				'streak' => max( 0, (int) $streak ),
			),
			self::HEALTH_TTL
		);
	}

	/**
	 * Re-measure health at the end of every run that did work.
	 *
	 * Healthy means no failure, no slow AI call and, when spawned, a PHP
	 * worker almost at once. After HEALTHY_RUNS such runs in a row the
	 * level rises by one. Strain is handled at once by backoff().
	 *
	 * @return void
	 */
	private static function record_health() {
		if ( ! self::$did_work || self::$strained || self::$had_failure ) {
			return;
		}

		$health = self::health_state();

		// Neither good nor bad: the run waited a little for a worker.
		if ( null !== self::$tick_delay && self::$tick_delay >= self::HEALTHY_DELAY ) {
			self::save_health( $health['slots'], 0 );
			return;
		}

		$streak = $health['streak'] + 1;
		$slots  = min( self::max_slots(), $health['slots'] );

		if ( $streak >= self::HEALTHY_RUNS && $slots < self::max_slots() ) {
			++$slots;
			$streak = 0;
			self::log( sprintf( 'Queue healthy, raising to %d slots.', $slots ) );
		}

		self::save_health( $slots, $streak );
	}

	/**
	 * Drop straight to one slot. Runs already in progress finish within
	 * their budget; spawn() starts nothing new while the level is low.
	 *
	 * @param string $reason Why, for the debug log.
	 * @return void
	 */
	private static function backoff( $reason ) {
		self::$strained = true;
		self::save_health( 1, 0 );
		self::log( sprintf( 'Queue strained (%s), dropping to 1 slot.', $reason ) );
	}
	/**
	 * Start the next queue runs without waiting for cron.
	 *
	 * Same technique as WordPress core's spawn_cron(): a non-blocking
	 * loopback request that returns at once. Spawns no more requests than
	 * there are free slots and waiting jobs, so it never fills every worker.
	 *
	 * Called early, right after this run claims its job, so the next worker
	 * starts while this one waits on the AI; and again at the end for any
	 * slot still free. Runs started early may not hold their lock yet, so
	 * the end-of-run call does not count them as free.
	 *
	 * @param bool $early True when called before this run's AI work.
	 * @return void
	 */
	private static function spawn( $early = false ) {
		/**
		 * Filter whether the queue chains its own runs between cron ticks.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'automlp_queue_self_spawn', true ) || wp_installing() ) {
			return;
		}

		// A failed job is held back for RETRY_DELAY_SECONDS per attempt by
		// Queue_Table::waiting(), so chaining on after a failure moves on to
		// the next job instead of burning the failed one's retries. backoff()
		// has already dropped to one slot, so a failing provider is not hammered.

		$free = self::slots() - self::busy_slots();

		if ( ! $early ) {
			$free -= self::$early_spawns;
		}

		$count = $free > 0 ? min( $free, Queue_Table::count_waiting( $free ) ) : 0;

		if ( $early ) {
			self::$early_spawns += $count;
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$time  = (string) microtime( true );
			$nonce = wp_generate_password( 20, false );

			wp_remote_post(
				admin_url( 'admin-ajax.php' ),
				array(
					'timeout'   => 0.01,
					'blocking'  => false,
					/** This filter is documented in wp-includes/class-wp-http-streams.php */
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
					'body'      => array(
						'action' => self::TICK_ACTION,
						'time'   => $time,
						'nonce'  => $nonce,
						'token'  => self::tick_token( $time, $nonce ),
					),
				)
			);
		}
	}

	/**
	 * Signature for a spawned request, built from the site's secret salts.
	 *
	 * A per-request nonce makes every token unique, so handle_tick() can
	 * accept each one only once without ever rejecting a legitimate spawn.
	 *
	 * @param string $time  Spawn time.
	 * @param string $nonce Per-request nonce.
	 * @return string
	 */
	private static function tick_token( $time, $nonce = '' ) {
		$payload = self::TICK_ACTION . '|' . $time;

		if ( '' !== $nonce ) {
			$payload .= '|' . $nonce;
		}

		return wp_hash( $payload, 'nonce' );
	}

	/**
	 * Count live lock slots.
	 *
	 * @return int
	 */
	private static function busy_slots() {
		global $wpdb;

		$names = array();

		for ( $slot = 1; $slot <= self::SLOTS_LIMIT; $slot++ ) {
			$names[] = self::slot_name( $slot );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $names ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders only; lock state must be read fresh.
		$values = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)", $names ) );

		$busy = 0;

		foreach ( (array) $values as $value ) {
			$parts = explode( '|', (string) $value );

			if ( isset( $parts[1] ) && (int) $parts[1] > time() ) {
				++$busy;
			}
		}

		return $busy;
	}

	/**
	 * Entry point for a spawned loopback request.
	 *
	 * Accepts only requests signed by this site within the last minute. If
	 * the request waited too long for a PHP worker, the server is busy, so
	 * the queue backs off to one slot.
	 *
	 * @return void
	 */
	public static function handle_tick() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Signed with tick_token(); a WordPress nonce is user-bound and this request has no user.
		$time  = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $time || ! is_numeric( $time ) || ! hash_equals( self::tick_token( $time, $nonce ), $token ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		// Single use: spawn() signs every tick with a fresh time and nonce, so
		// a repeat can only be a replay. add_option() is an atomic insert, and
		// the marker outlives the 60s window. A replay gets an empty 200.
		$used_key = 'automlp_tick_used_' . md5( $token );

		if ( ! add_option( $used_key, (string) ( time() + 2 * MINUTE_IN_SECONDS ), '', false ) ) {
			wp_die( '', '', array( 'response' => 200 ) );
		}

		$delay = microtime( true ) - (float) $time;

		if ( $delay < -5 || $delay > MINUTE_IN_SECONDS ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		if ( $delay > self::SATURATION_DELAY ) {
			self::backoff( sprintf( 'spawned run waited %.1fs for a PHP worker', $delay ) );
		}

		self::$tick_delay = max( 0.0, $delay );

		ignore_user_abort( true );
		self::run();
		wp_die( '', '', array( 'response' => 200 ) );
	}

	/**
	 * Run outside cron, for the "process now" button and the modal poller.
	 *
	 * A live lock is never removed: deleting it would let two runs translate
	 * and save the same job. An expired lock is taken over by run() itself.
	 *
	 * @param int|null $limit Optional cap on jobs for this run.
	 * @param bool     $force Kept for backward compatibility; no longer clears a live lock.
	 * @return void
	 */
	public static function run_now( $limit = null, $force = true ) {
		unset( $force );

		self::run( $limit );
	}

	/**
	 * Take a free queue slot.
	 *
	 * Each slot lets one PHP process work on one job. Extra slots are only
	 * offered while the site shows no sign of strain; see slots().
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		$slots = self::slots();

		for ( $slot = 1; $slot <= $slots; $slot++ ) {
			if ( self::acquire_slot( self::slot_name( $slot ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Option name of a lock slot. Slot 1 keeps the original name.
	 *
	 * @param int $slot Slot number, 1-based.
	 * @return string
	 */
	private static function slot_name( $slot ) {
		return 1 === (int) $slot ? self::LOCK : self::LOCK . '_' . (int) $slot;
	}

	/**
	 * Take one lock slot.
	 *
	 * Stored as "token|expires" in a non-autoloaded option. INSERT IGNORE and
	 * compare-and-swap updates make acquisition atomic, which transients and
	 * add_option() (an upsert) cannot guarantee.
	 *
	 * @param string $name Slot option name.
	 * @return bool
	 */
	private static function acquire_slot( $name ) {
		global $wpdb;

		self::$lock_name = (string) $name;

		$token = wp_generate_password( 20, false );
		$value = $token . '|' . ( time() + self::LOCK_TTL );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::$lock_name,
				$value
			)
		);

		if ( ! $inserted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$current = (string) $wpdb->get_var(
				$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::$lock_name )
			);

			$parts   = explode( '|', $current );
			$expires = isset( $parts[1] ) ? (int) $parts[1] : 0;

			if ( $expires > time() ) {
				self::$lock_name = '';
				return false;
			}

			// Expired: its owner died. Take over only if nobody beat us to it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$swapped = $wpdb->update(
				$wpdb->options,
				array( 'option_value' => $value ),
				array(
					'option_name'  => self::$lock_name,
					'option_value' => $current,
				)
			);

			if ( ! $swapped ) {
				self::$lock_name = '';
				return false;
			}
		}

		wp_cache_delete( self::$lock_name, 'options' );
		self::$lock_token = $token;

		return true;
	}

	/**
	 * Extend our lock between jobs.
	 *
	 * @return bool False when the lock is no longer ours.
	 */
	private static function refresh_lock() {
		global $wpdb;

		if ( '' === self::$lock_token ) {
			return false;
		}

		$like = $wpdb->esc_like( self::$lock_token . '|' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value LIKE %s",
				self::$lock_token . '|' . ( time() + self::LOCK_TTL ),
				self::$lock_name,
				$like
			)
		);

		// rows_affected is 0 when the value is unchanged within the same second.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::$lock_name )
		);

		return 0 === strpos( $current, self::$lock_token . '|' );
	}

	/**
	 * Release the lock, only if this request still owns it.
	 *
	 * @return void
	 */
	private static function release_lock() {
		global $wpdb;

		if ( '' === self::$lock_token ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s",
				self::$lock_name,
				$wpdb->esc_like( self::$lock_token . '|' ) . '%'
			)
		);

		wp_cache_delete( self::$lock_name, 'options' );
		self::$lock_token = '';
		self::$lock_name  = '';
	}

	/**
	 * Seconds this run may keep starting jobs.
	 *
	 * Stays well inside max_execution_time when PHP enforces one.
	 *
	 * @return int
	 */
	private static function run_budget() {
		$budget = self::tuning( 'run_budget', self::RUN_BUDGET );
		$max    = (int) ini_get( 'max_execution_time' );

		if ( $max > 0 ) {
			$budget = min( $budget, (int) floor( $max * 0.5 ) );
		}

		/**
		 * Filter how long one queue run may keep claiming jobs.
		 *
		 * @param int $budget Seconds.
		 */
		return max( 5, (int) apply_filters( 'automlp_queue_run_budget', $budget ) );
	}

	/**
	 * Claim and process up to $limit (or JOBS_PER_RUN) jobs.
	 *
	 * @param int|null $limit Optional cap.
	 * @return void
	 */
	private static function process_batch( $limit = null ) {
		/**
		 * Filter how many jobs one run may process.
		 *
		 * Defaults to 1: one job at a time, translated in as many AI calls as
		 * the run budget allows, so a small host keeps a PHP worker free.
		 *
		 * @param int $concurrency Jobs per run.
		 */
		$concurrency = max( 1, (int) apply_filters( 'automlp_queue_concurrency', self::JOBS_PER_RUN ) );
		$max         = null === $limit ? $concurrency : min( $concurrency, max( 1, (int) $limit ) );
		$jobs        = Queue_Table::waiting( $max );

		if ( empty( $jobs ) ) {
			return;
		}

		$gateways = array();
		$started  = time();
		$budget   = self::run_budget();
		$first    = true;

		foreach ( $jobs as $job ) {
			// Leave the rest for the next run rather than risk a PHP timeout,
			// and stop at once if our lock was lost.
			if ( ! $first && ( ( time() - $started ) >= $budget || ! self::refresh_lock() ) ) {
				break;
			}

			$first = false;

			// Another process may have taken this row between the read and now.
			if ( ! Queue_Table::claim( (int) $job['job_id'] ) ) {
				continue;
			}

			// Start the next worker now rather than after this job's AI calls.
			self::spawn( true );

			// Honour the provider chosen when the job was queued, falling back
			// to whichever provider is configured.
			$slug = isset( $job['provider'] ) ? (string) $job['provider'] : '';

			if ( ! isset( $gateways[ $slug ] ) ) {
				$gateways[ $slug ] = new AI_Gateway( $slug );
			}

			self::handle_job( $job, $gateways[ $slug ], $started + $budget );
		}
	}

	/**
	 * Translate one job and hand the result back to WPML.
	 *
	 * Keeps sending batches until the job is translated or the run budget is
	 * spent, then saves to WPML in the same run when time allows. Progress is
	 * stored after every batch, so a killed run resumes where it stopped.
	 *
	 * @param array      $job      Job row.
	 * @param AI_Gateway $gateway  Provider gateway.
	 * @param float      $deadline Unix time after which no new AI call starts.
	 * @return void
	 */
	private static function handle_job( array $job, AI_Gateway $gateway, $deadline = 0.0 ) {
		$job_id  = (int) $job['job_id'];
		$started = microtime( true );

		if ( $deadline <= 0 ) {
			$deadline = $started + self::run_budget();
		}

		try {
			$source_map = Queue_Table::read_source_map( $job );

			if ( empty( $source_map ) ) {
				throw new \RuntimeException( __( 'This job has no source strings.', 'wpml-translation-check' ) );
			}

			// field_name => plain text, skipping anything empty.
			$fields = array();

			foreach ( $source_map as $field_name => $entry ) {
				$text = isset( $entry['text'] ) ? (string) $entry['text'] : '';

				if ( '' === trim( $text ) ) {
					continue;
				}

				$fields[ (string) $field_name ] = $text;
			}

			if ( empty( $fields ) ) {
				throw new \RuntimeException( __( 'Every field in this job was empty.', 'wpml-translation-check' ) );
			}

			$units = $gateway->translation_units( $fields );

			if ( empty( $units ) ) {
				throw new \RuntimeException( __( 'No translation units could be prepared for this job.', 'wpml-translation-check' ) );
			}

			$units_total      = count( $units );
			$partial          = Queue_Table::read_response_payload( $job );
			$units_translated = self::count_translated_units( $units, $partial );

			// Conditional: the job may have been cancelled since it was claimed.
			$claimed = Queue_Table::edit_if_state(
				$job_id,
				Queue_Table::STATE_CLAIMED,
				array(
					'state'             => Queue_Table::STATE_SENT,
					'provider'          => $gateway->provider(),
					'model'             => $gateway->model(),
					'field_count'       => $units_total,
					'fields_translated' => $units_translated,
				)
			);

			if ( ! $claimed ) {
				return;
			}

			if ( Queue_Table::debug_enabled() ) {
				Queue_Table::edit( $job_id, array( 'request_payload' => $fields ) );
			}

			$last_call = 0.0;

			while ( $units_translated < $units_total ) {
				$now = microtime( true );

				// The first call always runs. After that, start another only when
				// the remaining budget fits a call as long as the previous one.
				if ( $last_call > 0 && ( $deadline - $now ) < max( 5.0, $last_call * 1.2 ) ) {
					self::park( $job_id, $units_total, $units_translated, $partial );
					return;
				}

				// Cancelled from the Queue screen or in WPML: stop before paying for
				// another AI call. Nothing is saved.
				if ( Queue_Table::STATE_SENT !== Queue_Table::state( $job_id ) ) {
					return;
				}

				$batch = $gateway->next_unit_batch( self::pending_units( $units, $partial ), 0 );

				if ( empty( $batch ) ) {
					throw new \RuntimeException( __( 'The next translation batch could not be prepared.', 'wpml-translation-check' ) );
				}

				$call_start = microtime( true );
				$result     = $gateway->translate_units_resilient( $batch, $job['from_lang'], $job['to_lang'], $deadline );
				$last_call  = microtime( true ) - $call_start;

				self::$did_work = true;

				// A call this slow means the provider or the server is struggling.
				if ( $last_call > self::SLOW_CALL ) {
					self::backoff( sprintf( 'AI call took %.1fs', $last_call ) );
				}

				if ( is_wp_error( $result ) ) {
					// Keep what earlier batches produced; the retry resumes from it.
					self::park( $job_id, $units_total, $units_translated, $partial );
					throw new \RuntimeException( $result->get_error_message() );
				}

				$before = $units_translated;

				self::merge_partial_response( $partial, $batch, $result );
				$units_translated = self::count_translated_units( $units, $partial );

				// Store progress after every batch. Conditional: a cancel during
				// the AI call must not be undone.
				$kept = Queue_Table::edit_if_state(
					$job_id,
					Queue_Table::STATE_SENT,
					array(
						'field_count'       => $units_total,
						'fields_translated' => $units_translated,
						'response_payload'  => $partial,
						'last_error'        => null,
					)
				);

				if ( ! $kept ) {
					return;
				}

				if ( $units_translated === $before ) {
					throw new \RuntimeException( __( 'The AI provider skipped part of the translation response.', 'wpml-translation-check' ) );
				}
			}

			// Translated: save now unless the budget is gone, in which case the
			// next run saves without another AI call.
			if ( $last_call > 0 && microtime( true ) >= $deadline ) {
				self::park( $job_id, $units_total, $units_translated, $partial );
				return;
			}

			$translated = $gateway->reassemble_units( $fields, $units, $partial );

			if ( empty( $translated ) ) {
				throw new \RuntimeException( __( 'The AI provider returned no usable translations.', 'wpml-translation-check' ) );
			}

			if ( count( $translated ) < count( $fields ) ) {
				throw new \RuntimeException( __( 'The AI provider did not return every required translation chunk.', 'wpml-translation-check' ) );
			}

			// Re-check right before writing: the job may have been cancelled
			// in WPML or our queue while the AI call ran.
			$moved = Queue_Table::edit_if_state(
				$job_id,
				Queue_Table::STATE_SENT,
				array(
					'state'             => Queue_Table::STATE_WRITING,
					'field_count'       => $units_total,
					'fields_translated' => $units_total,
					'response_payload'  => $partial,
				)
			);

			if ( ! $moved ) {
				return;
			}

			if ( Queue_Table::debug_enabled() ) {
				Queue_Table::edit( $job_id, array( 'response_payload' => $translated ) );
			}

			/**
			 * Hand translated fields back to whatever writes them.
			 *
			 * @param array $translated field_name => translated text.
			 * @param array $job        Job row.
			 * @param array $source_map field_name => array{tid:int,text:string}.
			 */
			$written = apply_filters( 'automlp_write_translation', null, $translated, $job, $source_map );

			if ( is_wp_error( $written ) ) {
				// WPML refusing the save will refuse it again: do not pay for
				// another AI round trip.
				$permanent = 'automlp_save_rejected' === $written->get_error_code();

				throw new \RuntimeException( $written->get_error_message(), $permanent ? self::PERMANENT_FAILURE : 0 );
			}

			if ( null === $written ) {
				throw new \RuntimeException( __( 'No writer is registered to save this translation.', 'wpml-translation-check' ) );
			}

			$stats = array(
				'field_count'       => count( $translated ),
				'fields_translated' => count( $translated ),
				// char_count stays the source length captured when the job was
				// queued, matching how the modal and dashboard have always counted.
			);

			if ( is_array( $written ) && ! empty( $written['result_id'] ) ) {
				$stats['result_id'] = (int) $written['result_id'];
			}

			Queue_Table::finish( $job_id, $stats );

			$job['provider'] = $gateway->provider();
			$job['model']    = $gateway->model();

			/**
			 * Fires after a job is written successfully.
			 *
			 * @param int   $job_id Job id.
			 * @param array $job    Job row.
			 * @param array $ctx    Completion context (translated, source_map, written, time_taken, provider).
			 */
			do_action(
				'automlp_job_completed',
				$job_id,
				$job,
				array(
					'translated' => $translated,
					'source_map' => $source_map,
					'written'    => is_array( $written ) ? $written : array(),
					'time_taken' => (int) max( 0, round( microtime( true ) - $started ) ),
					'provider'   => $gateway->provider(),
				)
			);

		} catch ( \Throwable $e ) {
			$retries = self::PERMANENT_FAILURE === (int) $e->getCode() ? 0 : self::MAX_RETRIES;

			self::backoff( 'job failed' );
			self::$had_failure = true;

			Queue_Table::fail( $job_id, $e->getMessage(), $retries );
			self::log( sprintf( 'Job #%d failed: %s', $job_id, $e->getMessage() ) );
		}
	}

	/**
	 * Hand a partly translated job back to the queue with its progress.
	 *
	 * Conditional on the row still being ours, so a cancel is never undone.
	 *
	 * @param int   $job_id           Job id.
	 * @param int   $units_total      Units in the job.
	 * @param int   $units_translated Units done.
	 * @param array $partial          field => chunk_index => translated text.
	 * @return void
	 */
	private static function park( $job_id, $units_total, $units_translated, array $partial ) {
		Queue_Table::edit_if_state(
			$job_id,
			Queue_Table::STATE_SENT,
			array(
				'state'             => Queue_Table::STATE_WAITING,
				'field_count'       => (int) $units_total,
				'fields_translated' => (int) $units_translated,
				'response_payload'  => $partial,
			)
		);
	}

	/**
	 * Units that still have no translation, in order.
	 *
	 * @param array $units   Ordered units.
	 * @param array $partial field => chunk_index => translated text.
	 * @return array
	 */
	private static function pending_units( array $units, array $partial ) {
		$pending = array();

		foreach ( $units as $unit ) {
			$field = isset( $unit['field'] ) ? (string) $unit['field'] : '';
			$index = isset( $unit['index'] ) ? (int) $unit['index'] : 0;

			if ( '' !== $field && ! ( isset( $partial[ $field ] ) && is_array( $partial[ $field ] ) && array_key_exists( $index, $partial[ $field ] ) ) ) {
				$pending[] = $unit;
			}
		}

		return $pending;
	}

	/**
	 * Merge translated units into the persisted partial payload.
	 *
	 * Only non-empty translations are kept; anything the provider skipped
	 * stays pending and is sent again.
	 *
	 * @param array $partial Existing partial payload.
	 * @param array $batch   Units sent to the provider.
	 * @param array $result  field => chunk_index => translated text.
	 * @return void
	 */
	private static function merge_partial_response( array &$partial, array $batch, array $result ) {
		foreach ( $batch as $unit ) {
			$field = isset( $unit['field'] ) ? (string) $unit['field'] : '';
			$index = isset( $unit['index'] ) ? (int) $unit['index'] : 0;

			if ( '' === $field || ! isset( $result[ $field ][ $index ] ) ) {
				continue;
			}

			$value = (string) $result[ $field ][ $index ];

			if ( '' === trim( $value ) ) {
				continue;
			}

			if ( ! isset( $partial[ $field ] ) || ! is_array( $partial[ $field ] ) ) {
				$partial[ $field ] = array();
			}

			$partial[ $field ][ $index ] = $value;
		}
	}

	/**
	 * Count completed units in a partial payload.
	 *
	 * @param array $units   Ordered units.
	 * @param array $partial field => chunk_index => translated text.
	 * @return int
	 */
	private static function count_translated_units( array $units, array $partial ) {
		$count = 0;

		foreach ( $units as $unit ) {
			$field = isset( $unit['field'] ) ? (string) $unit['field'] : '';
			$index = isset( $unit['index'] ) ? (int) $unit['index'] : 0;

			if ( '' !== $field && isset( $partial[ $field ] ) && is_array( $partial[ $field ] ) && array_key_exists( $index, $partial[ $field ] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Run housekeeping once a day.
	 *
	 * @return void
	 */
	private static function maybe_cleanup() {
		$last = (int) get_option( self::CLEANUP_FLAG, 0 );

		if ( ( time() - $last ) < DAY_IN_SECONDS ) {
			return;
		}

		update_option( self::CLEANUP_FLAG, time(), false );

		$slim  = (int) apply_filters( 'automlp_queue_slim_after_days', 30 );
		$purge = (int) apply_filters( 'automlp_queue_purge_after_days', 180 );

		Queue_Table::prune( $slim, $purge );

		global $wpdb;

		// Expired single-use tick markers, see handle_tick().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off bulk purge of plugin rows.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST( option_value AS UNSIGNED ) < %d", $wpdb->esc_like( 'automlp_tick_used_' ) . '%', time() ) );
	}

	/**
	 * Health snapshot for the dashboard.
	 *
	 * @return array
	 */
	public static function health() {
		$next     = wp_next_scheduled( self::HOOK );
		$last     = (int) get_option( self::HEARTBEAT, 0 );
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		if ( ! $next ) {
			$state = 'error';
		} elseif ( $last && ( time() - $last ) < ( 10 * MINUTE_IN_SECONDS ) ) {
			$state = 'ok';
		} elseif ( $disabled ) {
			$state = 'warning';
		} elseif ( $next < ( time() - ( 5 * MINUTE_IN_SECONDS ) ) ) {
			$state = 'warning';
		} else {
			$state = 'ok';
		}

		return array(
			'state'        => $state,
			'wp_cron_off'  => $disabled,
			'next_run'     => $next ? (int) $next : 0,
			'last_run'     => $last,
			'queue_counts' => Queue_Table::summary(),
		);
	}

	/**
	 * Write to the PHP error log when debugging is on.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function log( $message ) {
		if ( ! Queue_Table::debug_enabled() ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[AutoMLP] ' . $message );
	}
}
