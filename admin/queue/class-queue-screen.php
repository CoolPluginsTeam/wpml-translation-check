<?php
/**
 * Queue history screen.
 *
 * Once translation runs in the background, closing the modal leaves no record
 * of what happened. This screen is that record: job history, cron health, and
 * the controls to retry or clear.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Admin;

use AUTOMLP_WPML\Includes\Queue\Dispatcher;
use AUTOMLP_WPML\Includes\Queue\Queue_Table;
use AUTOMLP_WPML\Includes\Wpml\Background_Flow;
use AUTOMLP_WPML\Includes\Wpml\Job_Listener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue_Screen
 */
class Queue_Screen {

	const TAB      = 'queue';
	const PER_PAGE = 25;

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! Background_Flow::enabled() ) {
			return;
		}

		add_filter( 'automlp_dashboard_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'admin_post_automlp_queue_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_render_partial' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$active = self::active_count();
		$label  = __( 'Translation Queue', 'wpml-translation-check' );

		// The dashboard escapes tab titles, so the count goes in as plain
		// text rather than markup.
		if ( $active > 0 ) {
			$label = sprintf(
				/* translators: %d: number of jobs still running */
				__( 'Translation Queue (%d)', 'wpml-translation-check' ),
				$active
			);
		}

		$tabs[ self::TAB ] = $label;

		return $tabs;
	}

	/**
	 * Load the stylesheet on this tab only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		unset( $hook );

		if ( ! self::is_current_screen() ) {
			return;
		}

		wp_enqueue_style(
			'automlp-queue-screen',
			AUTOMLP_AI_PLUGIN_URL . 'admin/queue/queue-screen.css',
			array(),
			AUTOMLP_AI_VERSION
		);

		wp_enqueue_script(
			'automlp-queue-screen',
			AUTOMLP_AI_PLUGIN_URL . 'admin/queue/queue-screen.js',
			array(),
			AUTOMLP_AI_VERSION,
			true
		);

		// While work is in flight the page reloads so progress is visible without
		// a manual refresh, unless someone is selecting rows or typing a search.
		wp_localize_script(
			'automlp-queue-screen',
			'automlpQueue',
			array(
				'refreshMs'        => 15000,
				'loading'          => __( 'Loading jobs…', 'wpml-translation-check' ),
				'loaded'           => __( 'Jobs updated.', 'wpml-translation-check' ),
				'confirmDelete'    => __( 'Delete the selected jobs from the queue? They will not be translated.', 'wpml-translation-check' ),
				'confirmDeleteOne' => __( 'Delete this job from the queue? It will not be translated.', 'wpml-translation-check' ),
				'confirmClear'     => __( 'Remove all finished jobs from the history?', 'wpml-translation-check' ),
			)
		);
	}

	/**
	 * Answer the screen's own AJAX requests with just the queue markup.
	 *
	 * Sorting, filtering, paging and the row actions refresh the table in place, as WPML's
	 * own tables do. The same view renders the full page and this fragment, so the page keeps
	 * working without JavaScript.
	 *
	 * @return void
	 */
	public static function maybe_render_partial() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['automlp_partial'] ) || ! self::is_current_screen() ) {
			return;
		}

		// Same capability as the menu page itself.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wpml-translation-check' ), '', array( 'response' => 403 ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		require AUTOMLP_AI_PLUGIN_DIR . 'admin/queue/view-queue.php';

		exit;
	}

	/**
	 * Are we on this tab?
	 *
	 * @return bool
	 */
	private static function is_current_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return 'automlp_ai_dashboard' === $page && self::TAB === $tab;
	}

	/**
	 * Jobs in any non-terminal state.
	 *
	 * @return int
	 */
	private static function active_count() {
		$counts = Queue_Table::summary();

		return (int) $counts[ Queue_Table::STATE_WAITING ]
			+ (int) $counts[ Queue_Table::STATE_CLAIMED ]
			+ (int) $counts[ Queue_Table::STATE_SENT ]
			+ (int) $counts[ Queue_Table::STATE_WRITING ];
	}

	/**
	 * Handle the screen's form posts.
	 *
	 * @return void
	 */
	public static function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wpml-translation-check' ) );
		}

		check_admin_referer( 'automlp_queue_action' );

		$action = isset( $_POST['queue_action'] ) ? sanitize_key( wp_unslash( $_POST['queue_action'] ) ) : '';
		$notice = '';

		switch ( $action ) {
			case 'run':
				Dispatcher::run_now();
				$notice = 'ran';
				break;

			case 'retry':
				$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

				if ( $job_id && self::retry( $job_id ) ) {
					$notice = 'retried';
				}
				break;

			case 'cancel':
				$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
				$notice = ( $job_id && Job_Listener::cancel_queue_job( $job_id ) ) ? 'cancelled' : 'not_cancelled';
				break;

			case 'retry_all':
				$count  = self::retry_all_failed();
				$notice = $count > 0 ? 'retried_all' : 'nothing';
				break;

			case 'clear':
				self::clear_closed();
				$notice = 'cleared';
				break;

			case 'bulk':
				$bulk   = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
				$job_id = isset( $_POST['job_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['job_ids'] ) ) : array();
				$job_id = array_slice( array_unique( array_filter( $job_id ) ), 0, 100 );
				$count  = 0;

				foreach ( $job_id as $id ) {
					if ( 'retry' === $bulk && self::retry( $id ) ) {
						++$count;
					} elseif ( 'delete' === $bulk && Job_Listener::cancel_queue_job( $id ) ) {
						++$count;
					}
				}

				$notice = in_array( $bulk, array( 'retry', 'delete' ), true ) && ! empty( $job_id ) ? 'bulk_' . $bulk : 'bulk_none';
				break;
		}

		// Back to the same filtered view the action was taken from.
		$return = wp_get_referer();

		if ( ! $return || false === strpos( $return, 'page=automlp_ai_dashboard' ) ) {
			$return = admin_url( 'admin.php?page=automlp_ai_dashboard&tab=' . self::TAB );
		}

		$args = array( 'notice' => $notice );

		// The screen submitted this through AJAX: the redirect must lead to the fragment again.
		if ( isset( $_POST['automlp_partial'] ) ) {
			$args['automlp_partial'] = 1;
		}

		if ( isset( $count ) ) {
			$args['count'] = (int) $count;
		}

		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'notice', 'count' ), $return ) ) );

		exit;
	}

	/**
	 * Send a failed job back to the queue.
	 *
	 * Only possible while the source map survives: it is cleared on
	 * completion and by pruning.
	 *
	 * @param int $job_id Job id.
	 * @return bool
	 */
	private static function retry( $job_id ) {
		$row = Queue_Table::get( $job_id );

		if ( ! $row || Queue_Table::STATE_FAILED !== $row['state'] ) {
			return false;
		}

		if ( empty( $row['source_map'] ) ) {
			return false;
		}

		return Queue_Table::edit(
			$job_id,
			array(
				'state'      => Queue_Table::STATE_WAITING,
				'attempts'   => 0,
				'last_error' => null,
				'closed_at'  => null,
			)
		);
	}

	/**
	 * Retry every retryable failed job.
	 *
	 * @return int Jobs requeued.
	 */
	private static function retry_all_failed() {
		$browse = Queue_Table::browse(
			array(
				'page'     => 1,
				'per_page' => 100,
				'states'   => array( Queue_Table::STATE_FAILED ),
			)
		);

		$count = 0;

		foreach ( $browse['rows'] as $row ) {
			if ( self::retry( (int) $row['job_id'] ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Delete finished rows.
	 *
	 * @return void
	 */
	private static function clear_closed() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE state IN (%s, %s, %s)',
				Queue_Table::table(),
				Queue_Table::STATE_DONE,
				Queue_Table::STATE_FAILED,
				Queue_Table::STATE_STOPPED
			)
		);

		Queue_Table::bump_cache();
	}

	/**
	 * Everything the view needs.
	 *
	 * @return array
	 */
	public static function view_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';

		$states = array();

		// An unknown status behaves as "All", and the screen highlights it that way too.
		if ( 'active' !== $filter && ! in_array( $filter, self::known_states(), true ) ) {
			$filter = '';
		}

		if ( 'active' === $filter ) {
			$states = array(
				Queue_Table::STATE_WAITING,
				Queue_Table::STATE_CLAIMED,
				Queue_Table::STATE_SENT,
				Queue_Table::STATE_WRITING,
			);
		} elseif ( $filter && in_array( $filter, self::known_states(), true ) ) {
			$states = array( $filter );
		}

		// Filters that depend on what is in the queue are checked against it, so a stale or
		// hand-edited URL falls back to "all" instead of an empty page.
		$options = Queue_Table::filter_options();

		// `lang` is WPML's own admin language switch, so this filter has its own name.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang = isset( $_GET['target_lang'] ) ? sanitize_text_field( wp_unslash( $_GET['target_lang'] ) ) : '';
		$lang = in_array( $lang, $options['languages'], true ) ? $lang : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$type = ( 'string' === $type && $options['has_strings'] ) || in_array( $type, $options['post_types'], true ) ? $type : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		$orderby = in_array( $orderby, array( 'job_id', 'title', 'language', 'state', 'queued' ), true ) ? $orderby : 'job_id';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['order'] ) ) ? 'asc' : 'desc';

		$query  = array(
			'page'     => $page,
			'per_page' => self::PER_PAGE,
			'states'   => $states,
			'lang'     => $lang,
			'type'     => $type,
			'search'   => $search,
			'orderby'  => $orderby,
			'order'    => $order,
		);
		$browse = Queue_Table::browse( $query );

		// A page past the end (a stale link after jobs were removed) shows the last page, not an empty list.
		if ( empty( $browse['rows'] ) && $browse['total'] > 0 && $page > $browse['pages'] ) {
			$page          = max( 1, (int) $browse['pages'] );
			$query['page'] = $page;
			$browse        = Queue_Table::browse( $query );
		}

		$languages = array();

		foreach ( $options['languages'] as $code ) {
			$languages[ $code ] = self::language_label( $code );
		}

		$types = array();

		foreach ( $options['post_types'] as $post_type ) {
			$object              = get_post_type_object( $post_type );
			$types[ $post_type ] = $object ? $object->labels->singular_name : $post_type;
		}

		if ( $options['has_strings'] ) {
			$types['string'] = __( 'Strings', 'wpml-translation-check' );
		}

		return array(
			'rows'      => array_map( array( __CLASS__, 'decorate' ), $browse['rows'] ),
			'total'     => $browse['total'],
			'pages'     => $browse['pages'],
			'page'      => $page,
			'filter'    => $filter,
			'lang'      => $lang,
			'type'      => $type,
			'search'    => $search,
			'orderby'   => $orderby,
			'order'     => $order,
			'languages' => $languages,
			'types'     => $types,
			'counts'    => Queue_Table::summary(),
			'health'    => Dispatcher::health(),
			'notice'    => self::current_notice(),
			'per_page'  => self::PER_PAGE,
		);
	}

	/**
	 * Valid state filter values.
	 *
	 * @return array<int,string>
	 */
	private static function known_states() {
		return array(
			Queue_Table::STATE_WAITING,
			Queue_Table::STATE_CLAIMED,
			Queue_Table::STATE_SENT,
			Queue_Table::STATE_WRITING,
			Queue_Table::STATE_DONE,
			Queue_Table::STATE_FAILED,
			Queue_Table::STATE_STOPPED,
		);
	}

	/**
	 * Add display fields to a job row.
	 *
	 * @param array $row Queue row.
	 * @return array
	 */
	private static function decorate( array $row ) {
		$source_id = (int) $row['source_id'];
		$result_id = isset( $row['result_id'] ) ? (int) $row['result_id'] : 0;

		$row['state_label'] = self::state_label( $row['state'] );
		$row['state_class'] = self::state_class( $row['state'] );
		// can_retry requires both the failed state AND a stored source_map;
		// the REST retry endpoint rejects jobs whose source has been cleared.
		// browse() does not load the (large) source map, only whether one is stored.
		$row['can_retry']  = Queue_Table::STATE_FAILED === $row['state']
			&& ( ! empty( $row['source_map'] ) || ! empty( $row['has_source'] ) );
		$row['can_cancel'] = in_array( $row['state'], Job_Listener::cancellable_states(), true );

		$row['source_title'] = '';
		$row['source_link']  = '';

		if ( 'post' === $row['kind'] && $source_id ) {
			$title = get_the_title( $source_id );

			$row['source_title'] = $title
				? html_entity_decode( $title )
				: sprintf(
					/* translators: %d: post id */
					__( 'Post #%d', 'wpml-translation-check' ),
					$source_id
				);

			$row['source_link'] = (string) get_edit_post_link( $source_id, 'raw' );
		} elseif ( 'string' === $row['kind'] ) {
			$row['source_title'] = sprintf(
				/* translators: %d: number of strings */
				_n( '%d string', '%d strings', (int) $row['field_count'], 'wpml-translation-check' ),
				(int) $row['field_count']
			);
		}

		$wpml_job_id = isset( $row['wpml_job_id'] ) ? (int) $row['wpml_job_id'] : 0;
		$row['result_link'] = '';

		if ( Queue_Table::STATE_DONE === $row['state'] && ( $wpml_job_id || $result_id ) ) {
			$return_url = admin_url( 'admin.php?page=automlp_ai_dashboard&tab=queue' );
			$editor_url = \WPML_AT_Helper::get_translation_editor_url( $wpml_job_id, $return_url );

			$row['result_link'] = $editor_url
				? $editor_url
				: ( $result_id ? (string) get_edit_post_link( $result_id, 'raw' ) : '' );
		}
		$row['language']    = self::language_label( $row['to_lang'] );
		$row['languages']   = self::language_label( $row['from_lang'] ) . ' » ' . $row['language'];
		$row['queued_ago']  = self::ago( $row['queued_at'] );
		$row['queued_full'] = self::local_time( $row['queued_at'] );
		$row['type_label']  = self::type_label( $row['kind'], $source_id );
		$row['provider']    = self::provider_label( isset( $row['provider'] ) ? (string) $row['provider'] : '' );

		return $row;
	}

	/**
	 * Content type shown in the Type column.
	 *
	 * @param string $kind      Job kind: post or string.
	 * @param int    $source_id Source post id.
	 * @return string
	 */
	private static function type_label( $kind, $source_id ) {
		if ( 'string' === $kind ) {
			return __( 'Strings', 'wpml-translation-check' );
		}

		$object = $source_id ? get_post_type_object( (string) get_post_type( $source_id ) ) : null;

		return $object ? $object->labels->singular_name : '—';
	}

	/**
	 * Name of the AI provider that translated the job.
	 *
	 * @param string $provider Stored provider key.
	 * @return string
	 */
	private static function provider_label( $provider ) {
		$names = array(
			'openai' => 'OpenAI',
			'gemini' => 'Gemini',
			'chrome' => __( 'Chrome built-in AI', 'wpml-translation-check' ),
		);

		if ( '' === $provider ) {
			return '—';
		}

		return isset( $names[ strtolower( $provider ) ] ) ? $names[ strtolower( $provider ) ] : ucfirst( $provider );
	}

	/**
	 * Date and time in the site's timezone, for tooltips.
	 *
	 * @param string $datetime MySQL datetime in UTC.
	 * @return string
	 */
	private static function local_time( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime . ' UTC' );

		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '';
	}

	/**
	 * Human label for a state.
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

	/**
	 * CSS modifier for a state.
	 *
	 * @param string $state State key.
	 * @return string
	 */
	private static function state_class( $state ) {
		switch ( $state ) {
			case Queue_Table::STATE_DONE:
				return 'is-done';

			case Queue_Table::STATE_FAILED:
				return 'is-failed';

			case Queue_Table::STATE_STOPPED:
				return 'is-stopped';

			case Queue_Table::STATE_WAITING:
				return 'is-waiting';

			default:
				return 'is-running';
		}
	}

	/**
	 * Language name with fallback to the code.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	private static function language_label( $code ) {
		$name = apply_filters( 'wpml_translated_language_name', null, $code );

		return $name ? $name : $code;
	}

	/**
	 * Relative time from a UTC datetime.
	 *
	 * @param string $datetime MySQL datetime in UTC.
	 * @return string
	 */
	private static function ago( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime . ' UTC' );

		if ( ! $timestamp ) {
			return '';
		}

		return sprintf(
			/* translators: %s: human readable time difference */
			__( '%s ago', 'wpml-translation-check' ),
			human_time_diff( $timestamp, time() )
		);
	}

	/**
	 * Notice text for the current redirect, if any.
	 *
	 * @return array{type:string,text:string}|null
	 */
	private static function current_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

		if ( ! $key ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

		$map = array(
			'bulk_retry'    => $count
				? array(
					'success',
					sprintf(
						/* translators: %d: number of jobs */
						_n( '%d job was sent back to the queue.', '%d jobs were sent back to the queue.', $count, 'wpml-translation-check' ),
						$count
					),
				)
				: array( 'info', __( 'None of the selected jobs could be retried. Only failed jobs whose source content is still stored can be retried.', 'wpml-translation-check' ) ),
			'bulk_delete'   => $count
				? array(
					'success',
					sprintf(
						/* translators: %d: number of jobs */
						_n( '%d job was deleted from the queue and will not be translated.', '%d jobs were deleted from the queue and will not be translated.', $count, 'wpml-translation-check' ),
						$count
					),
				)
				: array( 'warning', __( 'None of the selected jobs could be deleted. They may have finished or be saving right now.', 'wpml-translation-check' ) ),
			'bulk_none'     => array( 'info', __( 'Select at least one job and choose an action.', 'wpml-translation-check' ) ),
			'ran'           => array( 'success', __( 'The queue was processed.', 'wpml-translation-check' ) ),
			'retried'       => array( 'success', __( 'That job was sent back to the queue.', 'wpml-translation-check' ) ),
			'retried_all'   => array( 'success', __( 'Failed jobs were sent back to the queue.', 'wpml-translation-check' ) ),
			'cleared'       => array( 'success', __( 'Finished jobs were removed from the history.', 'wpml-translation-check' ) ),
			'nothing'       => array( 'info', __( 'There was nothing to retry. Jobs can only be retried while their source content is still stored.', 'wpml-translation-check' ) ),
			'cancelled'     => array( 'success', __( 'The job was deleted from the queue and will not be translated.', 'wpml-translation-check' ) ),
			'not_cancelled' => array( 'warning', __( 'That job could not be deleted. It may have finished or be saving right now.', 'wpml-translation-check' ) ),
		);

		if ( ! isset( $map[ $key ] ) ) {
			return null;
		}

		return array(
			'type' => $map[ $key ][0],
			'text' => $map[ $key ][1],
		);
	}
}
