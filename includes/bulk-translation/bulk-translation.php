<?php

namespace AUTOMLP_WPML\Includes\Bulk_Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;
use AUTOMLP_Ai_Cpt_Dashboard;

/**
 * Bulk_Translation
 *
 * @package AUTOMLP_WPML\Includes\Bulk_Translation
 */
class Bulk_Translation {
	/**
	 * Single instance of the class
	 *
	 * @var self
	 */
	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'current_screen', array( $this, 'bulk_translate_btn' ) );
	}

	public function bulk_translate_btn( $screen ) {
		if ( ! class_exists( WPML_AT_Helper::class ) || ! WPML_AT_Helper::tranlastable_post_type( $screen ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : '';

		if ( 'trash' === $post_status ) {
			return;
		}

		add_filter( "views_{$screen->id}", array( $this, 'automlp_wpml_bulk_translate_button' ) );

		add_action( 'admin_footer', array( $this, 'bulk_translate_container' ) );
	}

	public function automlp_wpml_bulk_translate_button( $views ) {
		echo "<button class='button automlp-wpml-bulk-translate-btn button-primary' style='display:none;'>AI Translate</button>";

		return $views;
	}

	public function bulk_translate_container() {
		echo "<div id='automlp-wpml-bulk-translate-wrapper'></div>";
	}
}

Bulk_Translation::get_instance();
