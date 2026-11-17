<?php
/**
 * CPFM Review - the plugins.php row surface.
 *
 * Renders INSIDE the plugin's own meta line ("Version 1.2 | By X | View
 * details") rather than as a separate table row, so it lines up under that
 * block instead of starting at the far left of the table.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Review_Row' ) ) {

	/**
	 * Appends the review ask to a plugin's meta line on plugins.php.
	 */
	final class CPFM_Review_Row {

		/**
		 * Ids that actually rendered this request (assets follow in the footer).
		 *
		 * @var string[]
		 */
		private static $rendered = array();

		/**
		 * Hook the surface. Safe to call more than once.
		 *
		 * @return void
		 */
		public static function cpfm_init() {
			
			static $done = false;

			if ( $done ) {

				return;
			}
			
			$done = true;

			add_action( 'load-plugins.php', array( __CLASS__, 'cpfm_hook_row' ) );
		}

		/**
		 * Attach the filters, but only once we know we are on plugins.php.
		 *
		 * @return void
		 */
		public static function cpfm_hook_row() {
			
			add_filter( 'plugin_row_meta', array( __CLASS__, 'cpfm_row_meta' ), 20, 2 );
		}

		/**
		 * Append the ask to the matching plugin's meta line.
		 *
		 * @param string[] $links Meta links.
		 * @param string   $file  Plugin file being rendered.
		 * @return string[]
		 */
		public static function cpfm_row_meta( $links, $file ) {

			if ( ! class_exists( 'CPFM_Review' ) ) {
				return $links;
			}

			foreach ( CPFM_Review::cpfm_registered() as $id ) {

				$config = CPFM_Review::cpfm_config( $id );

				if ( empty( $config['row']['enabled'] ) || empty( $config['plugin_file'] ) ) {
					continue;
				}
				if ( plugin_basename( $config['plugin_file'] ) !== $file ) {
					continue;
				}
				if ( in_array( $id, self::$rendered, true ) ) {
					continue;
				}
				if ( ! CPFM_Review::cpfm_is_due( $id ) ) {
					continue;
				}
				if ( self::cpfm_slot_taken( $config['plugin_file'] ) ) {
					continue;
				}

				self::$rendered[] = $id;
				CPFM_Review::cpfm_record_impression( $id );
				CPFM_Review_Assets::cpfm_enqueue( 'cpfm-review-row', 'cpfm-review-row.css' );

				$block = self::cpfm_block( $config );

				// Glue onto the LAST existing item rather than adding a new one:
				// WordPress implodes these with ' | ', so a new item would leave a
				// dangling separator in front of it. As a block element it then
				// falls onto its own line, aligned under the meta block.
				if ( empty( $links ) ) {
					$links[] = $block;
				} else {
					$last    = array_pop( $links );
					$links[] = $last . $block;
				}

				return $links;
			}

			return $links;
		}

		/**
		 * Build the row markup.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @return string
		 */
		private static function cpfm_block( $config ) {

			$id    = $config['id'];
			$nonce = CPFM_Review_Assets::cpfm_nonce( $id );

			$stars = CPFM_Review_Assets::cpfm_stars();

			$question = CPFM_Review_Assets::cpfm_text( $config, 'row_question', 'Do you like this plugin?' );
			$submit   = CPFM_Review_Assets::cpfm_text( $config, 'submit_button', 'Submit review' );
			$no       = CPFM_Review_Assets::cpfm_text( $config, 'no_link', 'I do not like it, dismiss' );

			return '<span class="cpfm-rv-row" data-cpfm-rv'
				. ' data-cpfm-rv-id="' . esc_attr( $id ) . '"'
				. ' data-cpfm-rv-nonce="' . esc_attr( $nonce ) . '">'
				. '<span class="cpfm-rv-row__line">'
				. '<span class="cpfm-rv-row__stars" aria-hidden="true">' . $stars . '</span>'
				. '<span class="cpfm-rv-row__text">' . esc_html( $question ) . '</span>'
				. '<a class="button button-primary button-small cpfm-rv-row__go"'
				. ' href="' . esc_url( $config['review_url'] ) . '"'
				. ' target="_blank" rel="noopener noreferrer" data-cpfm-rv-answer>'
				. esc_html( $submit ) . '</a>'
				. '<a class="cpfm-rv-row__no" href="#" data-cpfm-rv-answer>'
				. esc_html( $no ) . '</a>'
				. '</span></span>';
		}

		/**
		 * True when something more important already owns the space under this row.
		 *
		 * Two stacked rows look broken, and whatever the other thing is - an
		 * available update, a licence prompt, a compatibility warning - it matters
		 * more than a review ask. So we stand down rather than compete.
		 *
		 * @param string $plugin_file Absolute plugin file path.
		 * @return bool
		 */
		private static function cpfm_slot_taken( $plugin_file ) {

			$basename = plugin_basename( $plugin_file );

			// An available update owns this slot: core hooks wp_plugin_update_row
			// only in that case, and that row matters far more than ours.
			$updates = get_site_transient( 'update_plugins' );
			if ( $updates && isset( $updates->response[ $basename ] ) ) {
				return true;
			}

			// Anyone ELSE injecting a row here.
			global $wp_filter;
			$tag = 'after_plugin_row_' . $basename;

			if ( isset( $wp_filter[ $tag ] ) && isset( $wp_filter[ $tag ]->callbacks ) ) {
				foreach ( $wp_filter[ $tag ]->callbacks as $callbacks ) {
					foreach ( $callbacks as $cb ) {
						$fn = isset( $cb['function'] ) ? $cb['function'] : null;
						$is_us = is_array( $fn ) && isset( $fn[0] )
							&& ( __CLASS__ === $fn[0] || $fn[0] instanceof self );
						if ( ! $is_us ) {
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Reset per-request state. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {

			self::$rendered = array();
		}
	}
}
