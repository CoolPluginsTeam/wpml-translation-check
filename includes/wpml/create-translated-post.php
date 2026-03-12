<?php

namespace AUTOMLP_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AUTOMLP_WPML\Includes\Wpml\Get_Package_Content;
use AUTOMLP_WPML\Includes\Wpml\Builder\Elementor\Elementor_Update;
use AUTOMLP_WPML\Includes\Wpml\Builder\Gutenberg\Gutenberg_Update;
use AUTOMLP_WPML\Includes\Wpml\Builder\Content_Update_Base;
use WPML_AT_Helper;
use AUTOMLP_WPML\Helper\Sanitized_Content;

/**
 * Create_Translated_Post
 *
 * @package AUTOMLP_WPML\Includes\Wpml
 */
class Create_Translated_Post {

	/**
	 * @var int
	 */
	private $post_id;
	/**
	 * @var array
	 */
	private $translate_strings;
	/**
	 * @var string
	 */
	private $translated_title;
	/**
	 * @var string
	 */
	private $translated_excerpt;
	/**
	 * @var string
	 */
	private $source_language;
	/**
	 * @var string
	 */
	private $target_language;
	/**
	 * @var int
	 */
	private $translated_post_id;
	/**
	 * @var string
	 */
	private $editor_type;

	/**
	 * @var array
	 */
	private $post_translation_status;

	public function __construct( int $post_id, array $translate_strings, string $translated_title, string $translated_excerpt, string $source_language, string $target_language, string $editor_type ) {
		if ( ! $this->is_create_post() ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'You are not authorized to perform this action.' );
			exit;
		}

		if ( ! isset( $editor_type ) || empty( $editor_type ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'Invalid editor type' );
			exit;
		}

		if ( ! in_array( $editor_type, WPML_AT_Helper::supported_editors() ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'Unsupported editor type' );
			exit;
		}

		if ( ! isset( $post_id ) || empty( $post_id ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'Invalid post ID' );
			exit;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'You are not authorized to perform this action.' );
			exit;
		}

		if ( ! isset( $source_language ) || empty( $source_language ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'Invalid source language' );
			exit;
		}

		if ( ! isset( $target_language ) || empty( $target_language ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'Invalid target language' );
			exit;
		}

		if ( ( ! isset( $translate_strings ) || empty( $translate_strings ) ) && ( ! isset( $translated_title ) || empty( $translated_title ) ) && ( ! isset( $translated_excerpt ) || empty( $translated_excerpt ) ) ) {
			$this->post_translation_status = false;
			wp_send_json_error( 'No translate strings found' );
			exit;
		}

		if ( isset( $translated_title ) && ! empty( $translated_title ) ) {
			$this->translated_title = sanitize_text_field( $translated_title );
		}

		if ( isset( $translated_excerpt ) && ! empty( $translated_excerpt ) ) {
			$this->translated_excerpt = wp_kses_post( $translated_excerpt );
		}

		$this->editor_type             = $editor_type;
		$this->post_translation_status = true;
		$this->post_id                 = $post_id;
		$this->source_language         = $source_language;
		$this->target_language         = $target_language;

		$this->defined_post_translation_constant();
		$this->filter_translate_strings( $translate_strings );
	}

	public function create_post() {
		if ( ! $this->is_create_post() ) {
			wp_send_json_error( 'You are not authorized to perform this action.' );
			exit;
		}

		if ( ! $this->post_translation_status ) {
			wp_send_json_error( 'Post translation status is false' );
			exit;
		}

		$this->create_translated_post();
		$this->update_translate_strings();

		$this->update_post_translation_status();

		return $this->translated_post_id;
	}

	private function update_post_translation_status(): void {
		if(function_exists('wpml_get_post_status_helper')){
			$post_status_helper = wpml_get_post_status_helper();
			$post_status_helper->set_status( $this->translated_post_id, 10 );
			update_post_meta( $this->translated_post_id, '_automlp_translation_editor_native', $this->translated_post_id );
		};
	}

	private function is_create_post() {
		return ( defined( 'DOING_AUTOMLP_WPML_CREATE_TRANSLATED_POST' ) && true === constant( 'DOING_AUTOMLP_WPML_CREATE_TRANSLATED_POST' ) );
	}

	private function defined_post_translation_constant(): void {
		if ( $this->editor_type === 'Elementor' ) {
			! defined( 'DOING_AUTOMLP_WPML_ELEMENTOR_CONTENT_UPDATE' ) && define( 'DOING_AUTOMLP_WPML_ELEMENTOR_CONTENT_UPDATE', true );
		}

		if ( $this->editor_type === 'Gutenberg' ) {
			! defined( 'DOING_AUTOMLP_WPML_GUTENBERG_CONTENT_UPDATE' ) && define( 'DOING_AUTOMLP_WPML_GUTENBERG_CONTENT_UPDATE', true );
		}
	}

	private function create_translated_post(): void {
		$post_type = get_post_type( $this->post_id );
		$post_data = get_post( $this->post_id, ARRAY_A );

		unset( $post_data['ID'] ); // remove ID to duplicate
		$post_data['post_status'] = 'draft';

		if ( isset( $this->translated_title ) && ! empty( $this->translated_title ) ) {
			$post_data['post_title'] = $this->translated_title;
		}

		if ( isset( $this->translated_excerpt ) && ! empty( $this->translated_excerpt ) ) {
			$post_data['post_excerpt'] = $this->translated_excerpt;
		}

		// Insert translated post
		$translated_post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $translated_post_id ) ) {
			wp_send_json_error( array( 'msg' => 'Failed to create translated post.' ) );
			exit;
		}

		// Duplicate meta
		$meta = get_post_meta( $this->post_id );
		foreach ( $meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, array( '_edit_lock', '_edit_last' ) ) ) {
				continue;
			}

			foreach ( $meta_values as $meta_value ) {
				update_post_meta( $translated_post_id, $meta_key, maybe_unserialize( $meta_value ) );
			}
		}

		// WPML language linking
		do_action(
			'wpml_set_element_language_details', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			array(
				'element_id'           => $translated_post_id,
				'element_type'         => 'post_' . $post_type,
				'trid'                 => apply_filters( 'wpml_element_trid', null, $this->post_id, 'post_' . $post_type ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'language_code'        => $this->target_language,
				'source_language_code' => apply_filters( 'wpml_post_language_details', null, $this->post_id )['language_code'], // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound	
			)
		);

		$this->translated_post_id = $translated_post_id;
	}

	private function update_translate_strings(): void {
		$automlp_wpml_content_update = null;

		if ( ! is_array( $this->translate_strings ) || empty( $this->translate_strings ) ) {
			return;
		}

		if ( $this->editor_type === 'Elementor' ) {
			$nonce                      = wp_create_nonce( 'automlp_wpml_elementor_content_update_nonce' );
			$automlp_wpml_content_update = new Elementor_Update( $this->post_id, $this->translated_post_id, $this->translate_strings, $this->target_language, $nonce );
		}

		if ( $this->editor_type === 'Gutenberg' ) {
			$nonce                      = wp_create_nonce( 'automlp_wpml_gutenberg_content_update_nonce' );
			$automlp_wpml_content_update = new Gutenberg_Update( $this->post_id, $this->translated_post_id, $this->translate_strings, $this->target_language, $nonce );
		}

		if ( isset( $automlp_wpml_content_update ) && $automlp_wpml_content_update instanceof Content_Update_Base ) {
			$automlp_wpml_content_update->update_content();
		}
	}

	private function string_has_html( string $string ): bool {
		return $string !== wp_strip_all_tags( $string );
	}

	private function filter_translate_strings( array $translate_strings ): void {
		$this->translate_strings = array();

		$get_package_content  = new Get_Package_Content( $this->post_id, $this->source_language );
		$translatable_strings = $get_package_content->get_translatable_strings();

		if ( isset( $translatable_strings['contents'] ) && is_array( $translatable_strings['contents'] ) && ! empty( $translatable_strings['contents'] ) ) {
			foreach ( $translatable_strings['contents'] as $package_key => $package_content ) {
				if ( isset( $translate_strings[ $package_key ] ) && ! empty( $translate_strings[ $package_key ] ) ) {
					if ( isset( $package_content['translate'] ) && $package_content['translate'] === 1 ) {
						if ( isset( $package_content['html'] ) && isset( $package_content['text'] ) ) {

							if ( $package_content['text'] === $package_content['html'] || ! $this->string_has_html( $package_content['html'] ) ) {
								$this->translate_strings[ $package_key ] = array(
									$this->target_language => array(
										'value'  => sanitize_text_field( $translate_strings[ $package_key ]['html'] ),
										'status' => 10,
									),
								);
							} elseif ( ! empty( $package_content['html'] ) ) {

								$automlp_wpml_sanitized_content = new Sanitized_Content( $package_content['html'] );
								$final_value                   = $automlp_wpml_sanitized_content->get_sanitized_content( $translate_strings[ $package_key ]['html'] );

								$this->translate_strings[ $package_key ] = array(
									$this->target_language => array(
										'value'  => $final_value,
										'status' => 10,
									),
								);
							}
						}
					}
				}
			}
		}
	}
}
