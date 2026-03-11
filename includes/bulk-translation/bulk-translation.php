<?php

namespace AUTOML_WPML\Includes\Bulk_Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_AT_Helper;
use AUTOML_Ai_Cpt_Dashboard;

/**
 * Bulk_Translation
 *
 * @package AUTOML_WPML\Includes\Bulk_Translation
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
		add_filter('wpml_tm_show_page_builders_translation_editor_warning', array( $this, 'hide_page_builders_translation_editor_warning' ), 10, 2);
		add_filter('wpml_tm_editor_exclude_posts', array( $this, 'editor_exclude_posts' ), 10, 2);
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

		add_filter( "views_{$screen->id}", array( $this, 'automl_wpml_bulk_translate_button' ) );

		add_action( 'admin_footer', array( $this, 'bulk_translate_container' ) );
	}

	public function automl_wpml_bulk_translate_button( $views ) {
		echo "<button class='button automl-wpml-bulk-translate-btn button-primary' style='display:none;'>AI Translate</button>";

		return $views;
	}

	public function bulk_translate_container() {
		echo "<div id='automl-wpml-bulk-translate-wrapper'></div>";
	}

	public function hide_page_builders_translation_editor_warning( $show, $post_id ) {
		$status = get_post_meta( $post_id, '_automl_translation_editor_native', true );
		
		if($post_id == $status) {
			return false;
		}

		return $show;
	}

	public function editor_exclude_posts( $exclude_post_ids, $post_ids ) {
		
		if(is_array($post_ids) && !empty($post_ids)) {
			$current_post_id=$post_ids[0];
			
			if(isset($current_post_id)){
				global $post;

				if(!isset($post) || !isset($post->ID) || empty($post->ID)) {
					return $exclude_post_ids;
				}

				$post_id=$post->ID;
				
				$status = get_post_meta( $post_id, '_automl_translation_editor_native', true );
				if($post_id == $status) {
					$exclude_post_ids[$current_post_id] = __( 'This post was translated using AutoML AI Translation and cannot be translated by WPML. There is no need to open the WPML Advanced Translation Editor.', 'wpml-translation-check' );
				}
			};
		}

		return $exclude_post_ids;
	}
}

Bulk_Translation::get_instance();
