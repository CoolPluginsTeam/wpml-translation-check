<?php
/**
 * Keeps the old pipeline out of the way while the new one runs.
 *
 * The browser-driven flow and the queue must never both write a translation
 * for the same post. Rather than deleting the old code on day one, this class
 * disables its entry points whenever the background flow is enabled, so the
 * legacy path can be removed in a later release once the queue has proven
 * itself in production.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy_Bridge
 */
class Legacy_Bridge {

	/**
	 * Legacy REST routes that must not run alongside the queue.
	 *
	 * @var array<int,string>
	 */
	private static $retired_routes = array(
		'translate-text',
		'create-translate-post',
		'create-translate-taxonomy',
		'bulk-translate-entries',
		'bulk-translate-taxonomy-entries',
		'pending-posts-ids',
	);

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( ! Background_Flow::enabled() ) {
			return;
		}

		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'block_legacy_routes' ), 10, 3 );
		add_filter( 'rest_endpoints', array( __CLASS__, 'unregister_legacy_routes' ) );

		// The old flow needed these to stop WPML complaining about posts it
		// had not created. The queue writes through WPML, so they now hide
		// legitimate warnings.
		add_action( 'init', array( __CLASS__, 'drop_editor_workarounds' ), 20 );
	}

	/**
	 * Refuse legacy REST calls with a clear message.
	 *
	 * Covers stale JS still open in a browser tab after an upgrade.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function block_legacy_routes( $result, $server, $request ) {
		unset( $server );

		$route = $request->get_route();

		if ( false === strpos( $route, 'automlp-bulk-translate' ) ) {
			return $result;
		}

		foreach ( self::$retired_routes as $retired ) {
			if ( false !== strpos( $route, $retired ) ) {
				return new \WP_Error(
					'automlp_route_retired',
					__( 'This translation method has been replaced. Please reload the page.', 'wpml-translation-check' ),
					array( 'status' => 410 )
				);
			}
		}

		return $result;
	}

	/**
	 * Remove legacy routes from the REST index.
	 *
	 * @param array $endpoints Registered endpoints.
	 * @return array
	 */
	public static function unregister_legacy_routes( $endpoints ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( false === strpos( $route, 'automlp-bulk-translate' ) ) {
				continue;
			}

			foreach ( self::$retired_routes as $retired ) {
				if ( false !== strpos( $route, $retired ) ) {
					unset( $endpoints[ $route ] );
					break;
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Remove the WPML editor workarounds the old flow depended on.
	 *
	 * @return void
	 */
	public static function drop_editor_workarounds() {
		if ( ! class_exists( '\AUTOMLP_WPML\Includes\Bulk_Translation\Bulk_Translation' ) ) {
			return;
		}

		$instance = \AUTOMLP_WPML\Includes\Bulk_Translation\Bulk_Translation::get_instance();

		remove_filter(
			'wpml_tm_show_page_builders_translation_editor_warning',
			array( $instance, 'hide_page_builders_translation_editor_warning' ),
			10
		);

		remove_filter( 'wpml_tm_editor_exclude_posts', array( $instance, 'editor_exclude_posts' ), 10 );
	}

	/**
	 * Delete the meta key the old flow wrote.
	 *
	 * Run once from an upgrade routine, not on every request.
	 *
	 * @return int Rows removed.
	 */
	public static function purge_legacy_meta() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE meta_key = %s',
				$wpdb->postmeta,
				'_automlp_translation_editor_native'
			)
		);

		return (int) $wpdb->rows_affected;
	}
}
