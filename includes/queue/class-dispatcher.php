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

	const HOOK          = 'automlp_run_queue';
	const SCHEDULE      = 'automlp_every_minute';
	const LOCK          = 'automlp_queue_lock';
	const HEARTBEAT     = 'automlp_queue_last_run';
	const CLEANUP_FLAG  = 'automlp_queue_last_cleanup';

	/**
	 * Jobs handled per cron tick. Keeps any single run well inside
	 * max_execution_time on shared hosting.
	 */
	const JOBS_PER_RUN = 5;

	/**
	 * Attempts before a job is abandoned.
	 */
	const MAX_RETRIES = 3;

	/**
	 * Wire up cron.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
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

		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 10 * MINUTE_IN_SECONDS );

		try {
			Queue_Table::recover_stale( 15 );
			self::process_batch( $limit );
		} catch ( \Throwable $e ) {
			self::log( 'Queue run aborted: ' . $e->getMessage() );
		} finally {
			delete_transient( self::LOCK );
		}

		self::maybe_cleanup();
	}

	/**
	 * Force a run outside cron, for the "process now" admin button.
	 *
	 * @param int|null $limit Optional cap on jobs for this run.
	 * @param bool     $force Clear an existing lock before starting.
	 * @return void
	 */
	public static function run_now( $limit = null, $force = true ) {
		if ( $force ) {
			delete_transient( self::LOCK );
		}

		self::run( $limit );
	}

	/**
	 * Claim and process up to $limit (or JOBS_PER_RUN) jobs.
	 *
	 * @param int|null $limit Optional cap.
	 * @return void
	 */
	private static function process_batch( $limit = null ) {
		$max  = null === $limit ? self::JOBS_PER_RUN : max( 1, (int) $limit );
		$jobs = Queue_Table::waiting( $max );

		if ( empty( $jobs ) ) {
			return;
		}

		$gateways = array();

		foreach ( $jobs as $job ) {
			// Another process may have taken this row between the read and now.
			if ( ! Queue_Table::claim( (int) $job['job_id'] ) ) {
				continue;
			}

			// Honour the provider chosen when the job was queued, falling back
			// to whichever provider is configured.
			$slug = isset( $job['provider'] ) ? (string) $job['provider'] : '';

			if ( ! isset( $gateways[ $slug ] ) ) {
				$gateways[ $slug ] = new AI_Gateway( $slug );
			}

			self::handle_job( $job, $gateways[ $slug ] );
		}
	}

	/**
	 * Translate one job and hand the result back to WPML.
	 *
	 * @param array      $job     Job row.
	 * @param AI_Gateway $gateway Provider gateway.
	 * @return void
	 */
	private static function handle_job( array $job, AI_Gateway $gateway ) {
		$job_id  = (int) $job['job_id'];
		$started = microtime( true );

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

			Queue_Table::edit(
				$job_id,
				array(
					'state'             => Queue_Table::STATE_SENT,
					'provider'          => $gateway->provider(),
					'model'             => $gateway->model(),
					'field_count'       => $units_total,
					'fields_translated' => $units_translated,
				)
			);

			if ( Queue_Table::debug_enabled() ) {
				Queue_Table::edit( $job_id, array( 'request_payload' => $fields ) );
			}

			if ( $units_translated < $units_total ) {
				$batch = $gateway->next_unit_batch( $units, $units_translated );

				if ( empty( $batch ) ) {
					throw new \RuntimeException( __( 'The next translation batch could not be prepared.', 'wpml-translation-check' ) );
				}

				$result = $gateway->translate_units(
					$batch,
					$job['from_lang'],
					$job['to_lang']
				);

				if ( is_wp_error( $result ) ) {
					throw new \RuntimeException( $result->get_error_message() );
				}

				self::merge_partial_response( $partial, $batch, $result );
				$units_translated = self::count_translated_units( $units, $partial );

				if ( $units_translated < $units_total ) {
					Queue_Table::edit(
						$job_id,
						array(
							'state'             => Queue_Table::STATE_WAITING,
							'field_count'       => $units_total,
							'fields_translated' => $units_translated,
							'response_payload'  => $partial,
							'last_error'        => null,
						)
					);
					return;
				}
			}

			$translated = $gateway->reassemble_units( $fields, $units, $partial );

			if ( empty( $translated ) ) {
				throw new \RuntimeException( __( 'The AI provider returned no usable translations.', 'wpml-translation-check' ) );
			}

			if ( count( $translated ) < count( $fields ) ) {
				throw new \RuntimeException( __( 'The AI provider did not return every required translation chunk.', 'wpml-translation-check' ) );
			}

			Queue_Table::edit(
				$job_id,
				array(
					'state'             => Queue_Table::STATE_WRITING,
					'field_count'       => $units_total,
					'fields_translated' => $units_total,
					'response_payload'  => $partial,
				)
			);

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
				throw new \RuntimeException( $written->get_error_message() );
			}

			if ( null === $written ) {
				throw new \RuntimeException( __( 'No writer is registered to save this translation.', 'wpml-translation-check' ) );
			}

			$stats = array(
				'field_count'       => count( $translated ),
				'fields_translated' => count( $translated ),
				'char_count'        => array_sum( array_map( 'mb_strlen', array_map( 'strval', $translated ) ) ),
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
			Queue_Table::fail( $job_id, $e->getMessage(), self::MAX_RETRIES );
			self::log( sprintf( 'Job #%d failed: %s', $job_id, $e->getMessage() ) );
		}
	}

	/**
	 * Merge one translated unit batch into the persisted partial payload.
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

			if ( '' === $field || ! isset( $result[ $field ] ) || ! is_array( $result[ $field ] ) || ! array_key_exists( $index, $result[ $field ] ) ) {
				throw new \RuntimeException( __( 'The AI provider skipped part of the translation response.', 'wpml-translation-check' ) );
			}

			$value = (string) $result[ $field ][ $index ];

			if ( '' === trim( $value ) ) {
				throw new \RuntimeException( __( 'The AI provider returned an empty translation chunk.', 'wpml-translation-check' ) );
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
