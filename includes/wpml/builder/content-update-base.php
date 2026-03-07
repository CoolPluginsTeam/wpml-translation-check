<?php

namespace AUTOML_WPML\Includes\Wpml\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WPML_Page_Builders_Integration;
use WPML_Gutenberg_Blocks_Integration;

/**
 * Content_Update_Base
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Content_Update_Base {
	/**
	 * @var int
	 */
	protected $post_id;
	/**
	 * @var array
	 */
	protected $translate_strings;
	/**
	 * @var int
	 */
	protected $translated_post_id;
	/**
	 * @var string
	 */
	protected $target_language;

	/**
	 * @var string
	 */
	protected $editor_type = null;

	/**
	 * @var bool
	 */
	protected $content_update_allowed = false;

	public function __construct( int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce ) {

		if ( ! isset( $this->editor_type ) || empty( $this->editor_type ) ) {
			wp_send_json_error( 'Editor type not set.' );
			exit;
		}

		$nonce_name = 'automl_wpml_' . sanitize_text_field( strtolower( $this->editor_type ) ) . '_content_update_nonce';

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $nonce_name ) ) {
			wp_send_json_error( 'You are not authorized to perform this action. Invalid nonce.' );
			exit;
		}

		if ( ! $this->is_content_update() ) {
			wp_send_json_error( 'You are not authorized to perform this action. Invalid content update.' );
			exit;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'You are not authorized to perform this action. Invalid user.' );
			exit;
		}

		$this->content_update_allowed = true;

		$this->post_id            = $post_id;
		$this->translated_post_id = $translated_post_id;
		$this->translate_strings  = $translate_strings;
		$this->target_language    = $target_language;

		$this->cretae_builder_integration();
	}

	final public function update_content(): void {
		$this->update_builder_translation();
	}
}
