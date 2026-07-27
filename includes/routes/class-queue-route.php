<?php
/**
 * REST endpoints for the background translation flow.
 *
 * Three routes replace the four the browser-driven flow needed. The browser no
 * longer extracts content, calls the AI provider or creates posts; it queues
 * work and polls for progress.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Routes;

use AUTOMLP_WPML\Includes\Queue\Dispatcher;
use AUTOMLP_WPML\Includes\Queue\Queue_Table;
use AUTOMLP_WPML\Includes\Wpml\Job_Sender;
use AUTOMLP_WPML\Includes\Wpml\Job_Listener;
use WPML_AT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue_Route
 */
class Queue_Route {

	const NAMESPACE_ROOT = 'automlp/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		register_rest_route(
			self::NAMESPACE_ROOT,
			'/queue/posts',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'queue_posts' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'args'                => array(
					'post_ids'  => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'languages' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
					),
					'provider'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ROOT,
			'/queue/strings',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'queue_strings' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'string_ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'language'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'provider'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ROOT,
			'/queue/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'args'                => array(
					'job_ids'  => array(
						'required' => false,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'page'     => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 1,
					),
					'per_page' => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 20,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ROOT,
			'/queue/retry',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'retry' ),
				'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
				'args'                => array(
					'job_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ROOT,
			'/queue/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'run_now' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Capability check for translation actions.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_edit_posts() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'automlp_forbidden',
				__( 'You are not allowed to do this.', 'wpml-translation-check' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Capability check for administrative actions.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_manage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'automlp_forbidden',
				__( 'You are not allowed to do this.', 'wpml-translation-check' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Queue posts for translation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function queue_posts( $request ) {
		$post_ids = array_filter( array_map( 'absint', (array) $request->get_param( 'post_ids' ) ) );
		$langs    = array_filter( array_map( 'sanitize_text_field', (array) $request->get_param( 'languages' ) ) );

		if ( empty( $post_ids ) ) {
			return new \WP_Error(
				'automlp_no_posts',
				__( 'Select at least one item to translate.', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		$langs = self::filter_languages( $langs );

		if ( empty( $langs ) ) {
			return new \WP_Error(
				'automlp_no_languages',
				__( 'Select at least one target language.', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		$provider = self::clean_provider( $request->get_param( 'provider' ) );

		$provider = self::clean_provider( $request->get_param( 'provider' ) );

		Job_Listener::reset_created();

		$result = Job_Sender::send_posts( $post_ids, $langs, $provider );

		$created = Job_Listener::created_ids();
		$skipped = Job_Listener::skipped();

		if ( 0 === $result['queued'] && $result['skipped'] > 0 ) {
			$skip_reasons = isset( $result['skip_reasons'] ) ? $result['skip_reasons'] : array();
			$ate_skipped  = ! empty( $skip_reasons['ate_active'] ) ? (int) $skip_reasons['ate_active'] : 0;
			$done_skipped = ! empty( $skip_reasons['already_translated'] ) ? (int) $skip_reasons['already_translated'] : 0;

			if ( $ate_skipped > 0 && 0 === $done_skipped ) {
				$message = __( 'Skipped: WPML Advanced Translation Editor already has an active job for the selected page(s) and language(s). Finish or cancel those ATE jobs first.', 'wpml-translation-check' );
			} elseif ( $ate_skipped > 0 ) {
				$message = __( 'Nothing was queued. Some items are already translated; others already have an active WPML Advanced Translation Editor job.', 'wpml-translation-check' );
			} else {
				$message = __( 'Everything selected is already translated into the chosen languages.', 'wpml-translation-check' );
			}

			return new \WP_REST_Response(
				array(
					'queued'       => 0,
					'skipped'      => $result['skipped'],
					'skip_reasons' => $skip_reasons,
					'errors'       => isset( $result['errors'] ) ? $result['errors'] : array(),
					'jobs'         => array(),
					'message'      => $message,
				),
				200
			);
		}

		if ( 0 === $result['queued'] && ! empty( $result['errors'] ) ) {
			return new \WP_Error(
				'automlp_queue_failed',
				reset( $result['errors'] ),
				array(
					'status' => 500,
					'errors' => $result['errors'],
				)
			);
		}

		// WPML dispatched the batch but nothing reached the queue. Report the
		// reason rather than a success the caller cannot act on.
		if ( empty( $created ) ) {
			$reason = ! empty( $skipped )
				? $skipped[0]['reason']
				: __( 'Translation jobs were created in WPML but none reached the queue. Check that the AutoMLP translator is registered under WPML, Translation Management, Translators.', 'wpml-translation-check' );

			return new \WP_Error(
				'automlp_nothing_queued',
				$reason,
				array(
					'status'  => 409,
					'skipped' => $skipped,
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'queued'  => count( $created ),
				'skipped' => $result['skipped'],
				'errors'  => $result['errors'],
				'jobs'    => self::jobs_by_id( $created ),
			),
			200
		);
	}

	/**
	 * Queue String Translation strings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function queue_strings( $request ) {
		$string_ids = array_filter( array_map( 'absint', (array) $request->get_param( 'string_ids' ) ) );
		$language   = sanitize_text_field( $request->get_param( 'language' ) );

		if ( empty( $string_ids ) ) {
			return new \WP_Error(
				'automlp_no_strings',
				__( 'Select at least one string to translate.', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		$allowed = self::filter_languages( array( $language ) );

		if ( empty( $allowed ) ) {
			return new \WP_Error(
				'automlp_language_not_allowed',
				__( 'That target language is not available on your plan.', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		$provider = self::clean_provider( $request->get_param( 'provider' ) );

		Job_Listener::reset_created();

		$sent = Job_Sender::send_strings( $string_ids, $language, '', $provider );

		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		$created = Job_Listener::created_ids();

		if ( empty( $created ) ) {
			$skipped = Job_Listener::skipped();

			return new \WP_Error(
				'automlp_nothing_queued',
				! empty( $skipped )
					? $skipped[0]['reason']
					: __( 'The string batch was created in WPML but did not reach the queue.', 'wpml-translation-check' ),
				array( 'status' => 409 )
			);
		}

		return new \WP_REST_Response(
			array(
				'queued' => count( $created ),
				'jobs'   => self::jobs_by_id( $created ),
			),
			200
		);
	}

	/**
	 * Report queue progress.
	 *
	 * The modal polls this instead of driving translation itself.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function status( $request ) {
		$job_ids = array_filter( array_map( 'absint', (array) $request->get_param( 'job_ids' ) ) );

		if ( ! empty( $job_ids ) ) {
			$rows = array();

			foreach ( $job_ids as $job_id ) {
				$row = Queue_Table::get( $job_id );

				if ( $row ) {
					$rows[] = self::shape( $row );
				}
			}

			$counts = Queue_Table::summary();

			return new \WP_REST_Response(
				array(
					'jobs'     => $rows,
					'counts'   => $counts,
					'totals'   => self::job_totals( $rows ),
					'finished' => self::all_closed( $rows ),
					'health'   => Dispatcher::health(),
				),
				200
			);
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$browse = Queue_Table::browse(
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		return new \WP_REST_Response(
			array(
				'jobs'   => array_map( array( __CLASS__, 'shape' ), $browse['rows'] ),
				'total'  => $browse['total'],
				'pages'  => $browse['pages'],
				'counts' => Queue_Table::summary(),
				'health' => Dispatcher::health(),
			),
			200
		);
	}

	/**
	 * Send a failed job back to the queue.
	 *
	 * Retry is only possible while the source map survives. It is cleared on
	 * completion and by pruning, so an old failure has to be re-queued from
	 * the post list instead.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function retry( $request ) {
		$job_id = absint( $request->get_param( 'job_id' ) );
		$row    = Queue_Table::get( $job_id );

		if ( ! $row ) {
			return new \WP_Error(
				'automlp_job_missing',
				__( 'That job no longer exists.', 'wpml-translation-check' ),
				array( 'status' => 404 )
			);
		}

		if ( Queue_Table::STATE_FAILED !== $row['state'] ) {
			return new \WP_Error(
				'automlp_job_not_failed',
				__( 'Only failed jobs can be retried.', 'wpml-translation-check' ),
				array( 'status' => 400 )
			);
		}

		if ( 'post' === $row['kind'] && (int) $row['source_id'] && ! current_user_can( 'edit_post', (int) $row['source_id'] ) ) {
			return new \WP_Error(
				'automlp_forbidden',
				__( 'You are not allowed to translate that content.', 'wpml-translation-check' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( empty( $row['source_map'] ) ) {
			return new \WP_Error(
				'automlp_source_cleared',
				__( 'This job can no longer be retried because its source content is no longer stored. Start the translation again from the content list.', 'wpml-translation-check' ),
				array( 'status' => 409 )
			);
		}

		$done = Queue_Table::edit(
			$job_id,
			array(
				'state'      => Queue_Table::STATE_WAITING,
				'attempts'   => 0,
				'last_error' => null,
				'closed_at'  => null,
			)
		);

		if ( ! $done ) {
			return new \WP_Error(
				'automlp_retry_failed',
				__( 'That job could not be added back to the queue.', 'wpml-translation-check' ),
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response(
			array(
				'retried' => true,
				'job'     => self::shape( Queue_Table::get( $job_id ) ),
			),
			200
		);
	}

	/**
	 * Drain the queue immediately.
	 *
	 * Useful when WP-Cron is disabled or a site owner is impatient.
	 *
	 * @return \WP_REST_Response
	 */
	public static function run_now() {
		Dispatcher::run_now();

		return new \WP_REST_Response(
			array(
				'ran'    => true,
				'counts' => Queue_Table::summary(),
			),
			200
		);
	}

	/**
	 * Normalise a provider slug from the request.
	 *
	 * The picker sends keys like 'openai_ai'; the gateway wants 'openai'.
	 * Anything unrecognised falls back to empty, which lets the gateway pick.
	 *
	 * @param mixed $provider Raw value.
	 * @return string
	 */
	private static function clean_provider( $provider ) {
		if ( ! is_string( $provider ) || '' === $provider ) {
			return '';
		}

		$slug = sanitize_key( str_replace( '_ai', '', $provider ) );

		return in_array( $slug, array( 'openai', 'google' ), true ) ? $slug : '';
	}

	/**
	 * Restrict target languages to what the plan allows.
	 *
	 * @param array $langs Requested language codes.
	 * @return array
	 */
	private static function filter_languages( array $langs ) {
		$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$codes  = is_array( $active ) ? array_keys( $active ) : array();

		$langs = array_values( array_intersect( $langs, $codes ) );

		$wizard = class_exists( 'WPML_AT_Helper' )
			? WPML_AT_Helper::get_wizard_allowed_language_code()
			: null;

		if ( null === $wizard ) {
			return $langs;
		}

		/**
		 * Filter the languages the queue will accept.
		 *
		 * Shared with Job_Listener so both entry points agree.
		 *
		 * @param array  $languages Allowed language codes.
		 * @param string $context   Where the check ran.
		 */
		$allowed = apply_filters( 'automlp_allowed_target_languages', array( $wizard ), 'rest' );

		return array_values( array_intersect( $langs, (array) $allowed ) );
	}

	/**
	 * Fetch specific jobs by id, in the order given.
	 *
	 * Replaces the previous "newest N rows" approach, which could return
	 * unrelated rows or nothing at all depending on cache state.
	 *
	 * @param array $job_ids Queue row ids.
	 * @return array
	 */
	private static function jobs_by_id( array $job_ids ) {
		$out = array();

		foreach ( array_map( 'absint', $job_ids ) as $job_id ) {
			if ( ! $job_id ) {
				continue;
			}

			$row = Queue_Table::get( $job_id );

			if ( $row ) {
				$out[] = self::shape( $row );
			}
		}

		return $out;
	}

	/**
	 * Convert a queue row into the shape the UI expects.
	 *
	 * @param array $row Queue row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$source_id = (int) $row['source_id'];
		$result_id = isset( $row['result_id'] ) ? (int) $row['result_id'] : 0;

		$out = array(
			'job_id'        => (int) $row['job_id'],
			'source_id'     => $source_id,
			'result_id'     => $result_id,
			'kind'          => $row['kind'],
			'from_lang'     => $row['from_lang'],
			'to_lang'       => $row['to_lang'],
			'state'         => $row['state'],
			'label'         => self::state_label( $row['state'] ),
			'closed'        => in_array(
				$row['state'],
				array( Queue_Table::STATE_DONE, Queue_Table::STATE_FAILED, Queue_Table::STATE_STOPPED ),
				true
			),
			'attempts'      => (int) $row['attempts'],
			'error'         => ! empty( $row['last_error'] ) ? (string) $row['last_error'] : '',
			'field_count'   => (int) $row['field_count'],
			'string_count'  => (int) $row['field_count'],
			'char_count'    => (int) $row['char_count'],
			'provider'      => isset( $row['provider'] ) ? (string) $row['provider'] : '',
			'queued_at'     => $row['queued_at'],
			'closed_at'     => ! empty( $row['closed_at'] ) ? $row['closed_at'] : '',
		);

		if ( 'post' === $row['kind'] && $source_id ) {
			$out['source_title'] = html_entity_decode( get_the_title( $source_id ) );
		}

		if ( $result_id ) {
			$out['result_title'] = html_entity_decode( get_the_title( $result_id ) );
			$out['view_link']    = get_permalink( $result_id );
			$out['edit_link']    = html_entity_decode( (string) get_edit_post_link( $result_id, 'raw' ) );
		}

		return $out;
	}

	/**
	 * Aggregate stats for the jobs currently being polled.
	 *
	 * @param array $jobs Shaped jobs.
	 * @return array{done:int,failed:int,strings:int,characters:int}
	 */
	private static function job_totals( array $jobs ) {
		$done       = 0;
		$failed     = 0;
		$strings    = 0;
		$characters = 0;

		foreach ( $jobs as $job ) {
			$state = isset( $job['state'] ) ? (string) $job['state'] : '';

			if ( Queue_Table::STATE_DONE === $state ) {
				++$done;
				$strings    += isset( $job['string_count'] ) ? (int) $job['string_count'] : (int) $job['field_count'];
				$characters += isset( $job['char_count'] ) ? (int) $job['char_count'] : 0;
			} elseif ( in_array( $state, array( Queue_Table::STATE_FAILED, Queue_Table::STATE_STOPPED ), true ) ) {
				++$failed;
			}
		}

		return array(
			'done'       => $done,
			'failed'     => $failed,
			'strings'    => $strings,
			'characters' => $characters,
		);
	}

	/**
	 * Did every job in the set reach a terminal state?
	 *
	 * @param array $jobs Shaped jobs.
	 * @return bool
	 */
	private static function all_closed( array $jobs ) {
		if ( empty( $jobs ) ) {
			return true;
		}

		foreach ( $jobs as $job ) {
			if ( empty( $job['closed'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Translated label for a job state.
	 *
	 * @param string $state State key.
	 * @return string
	 */
	private static function state_label( $state ) {
		$labels = array(
			Queue_Table::STATE_WAITING => __( 'Queued', 'wpml-translation-check' ),
			Queue_Table::STATE_CLAIMED => __( 'Starting', 'wpml-translation-check' ),
			Queue_Table::STATE_SENT    => __( 'Translating', 'wpml-translation-check' ),
			Queue_Table::STATE_WRITING => __( 'Saving', 'wpml-translation-check' ),
			Queue_Table::STATE_DONE    => __( 'Completed', 'wpml-translation-check' ),
			Queue_Table::STATE_FAILED  => __( 'Failed', 'wpml-translation-check' ),
			Queue_Table::STATE_STOPPED => __( 'Cancelled', 'wpml-translation-check' ),
		);

		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}
}
