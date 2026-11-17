<?php
/**
 * CPFM Environment — shared non-sensitive site snapshot for feedback payloads.
 *
 * Used by deactivation surveys, usage-data opt-in, and the 30-day usage cron
 * so every surface sends the same server_info / extra_details shape.
 *
 * Hosts may extend the payload via the `cpfm_environment` filter.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Environment' ) ) {

	/**
	 * Builds the standard Cool Plugins feedback environment snapshot.
	 */
	final class CPFM_Environment {

		/**
		 * PHP 7 treats a method named like the class as a constructor.
		 * `cpfm_environment()` is static, so that would fatal. An explicit
		 * constructor keeps it a normal static method.
		 */
		private function __construct() {}

		/**
		 * Non-sensitive environment snapshot (disclosed in the consent copy).
		 *
		 * @return array{server_info:array<string,string>,extra_details:array<string,mixed>}
		 */
		public static function cpfm_environment() {
			global $wpdb;

			$server_info = array(
				'server_software'        => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				'mysql_version'          => $wpdb ? sanitize_text_field( $wpdb->get_var( 'SELECT VERSION()' ) ) : 'N/A',
				'php_version'            => sanitize_text_field( phpversion() ?: 'N/A' ),
				'wp_version'             => sanitize_text_field( get_bloginfo( 'version' ) ?: 'N/A' ),
				'wp_debug'               => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled',
				'wp_memory_limit'        => sanitize_text_field( ini_get( 'memory_limit' ) ?: 'N/A' ),
				'wp_max_upload_size'     => sanitize_text_field( ini_get( 'upload_max_filesize' ) ?: 'N/A' ),
				'wp_permalink_structure' => sanitize_text_field( get_option( 'permalink_structure' ) ?: 'Default' ),
				'wp_multisite'           => is_multisite() ? 'Enabled' : 'Disabled',
				'wp_language'            => sanitize_text_field( get_option( 'WPLANG' ) ?: get_locale() ),
				'wp_prefix'              => isset( $wpdb->prefix ) ? sanitize_key( $wpdb->prefix ) : 'N/A',
			);

			$theme      = wp_get_theme();
			$theme_data = array(
				'name'      => sanitize_text_field( $theme->get( 'Name' ) ),
				'version'   => sanitize_text_field( $theme->get( 'Version' ) ),
				'theme_uri' => esc_url( $theme->get( 'ThemeURI' ) ),
			);

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_data    = array();
			$active_plugins = get_option( 'active_plugins', array() );

			foreach ( $active_plugins as $plugin_path ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . ltrim( $plugin_path, '/' );

				if ( ! file_exists( $plugin_file ) ) {
					continue;
				}

				$plugin_info = get_plugin_data( $plugin_file, false, false );
				$plugin_url  = ! empty( $plugin_info['PluginURI'] ) ? esc_url( $plugin_info['PluginURI'] ) : ( ! empty( $plugin_info['AuthorURI'] ) ? esc_url( $plugin_info['AuthorURI'] ) : 'N/A' );

				$plugin_data[] = array(
					'name'       => sanitize_text_field( $plugin_info['Name'] ),
					'version'    => sanitize_text_field( $plugin_info['Version'] ),
					'plugin_uri' => ! empty( $plugin_url ) ? $plugin_url : 'N/A',
				);
			}

			$env = array(
				'server_info'   => $server_info,
				'extra_details' => array(
					// Key must stay `wp_theme` — the Cool Plugins feedback
					// dashboard reads that exact name (legacy v1.5.x contract).
					'wp_theme'       => $theme_data,
					'active_plugins' => $plugin_data,
				),
			);

			/**
			 * Filter the shared CPFM environment snapshot.
			 *
			 * @param array{server_info:array,extra_details:array} $env Environment snapshot.
			 */
			return apply_filters( 'cpfm_environment', $env );
		}
	}
}
