<?php

namespace AUTOMLP_WPML\Includes\Wpml\Builder\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_Elementor_Translatable_Nodes;
use WPML_Elementor_DB_Factory;
use WPML_Elementor_Data_Settings;
use WPML_String_Registration_Factory;
use WPML_Elementor_Register_Strings;
use WPML_Elementor_Update_Translation;
use WPML_Page_Builders_Integration;
use AUTOMLP_WPML\Includes\Wpml\Builder\Content_Update_Base;
/**
 * Elementor_Update
 *
 * @package AUTOMLP_WPML\Includes\Wpml
 */
class Elementor_Update extends Content_Update_Base {
	/**
	 * @var WPML_Page_Builders_Integration
	 */
	private $elementor_builder_factory;

	/**
	 * Editor Type
	 */
	protected $editor_type = 'Elementor';

	public function __construct( int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce ) {
		parent::__construct( $post_id, $translated_post_id, $translate_strings, $target_language, $nonce );
	}

	protected function is_content_update() {
		return ( defined( 'DOING_AUTOMLP_WPML_ELEMENTOR_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOMLP_WPML_ELEMENTOR_CONTENT_UPDATE' ) );
	}

	protected function cretae_builder_integration(): void {

		if ( ! $this->content_update_allowed ) {
			wp_send_json_error( 'You are not authorized to perform this action two.' );
			exit;
		}

		$nodes                = new WPML_Elementor_Translatable_Nodes();
		$elementor_db_factory = new WPML_Elementor_DB_Factory();
		$data_settings        = new WPML_Elementor_Data_Settings( $elementor_db_factory->create() );

		$string_registration_factory = new WPML_String_Registration_Factory( $data_settings->get_pb_name() );
		$string_registration         = $string_registration_factory->create();

		$register_strings   = new WPML_Elementor_Register_Strings( $nodes, $data_settings, $string_registration );
		$update_translation = new WPML_Elementor_Update_Translation( $nodes, $data_settings );

		$this->elementor_builder_factory = new WPML_Page_Builders_Integration( $register_strings, $update_translation, $data_settings );
	}

	protected function update_builder_translation(): void {

		if ( ! $this->elementor_builder_factory instanceof WPML_Page_Builders_Integration ) {
			wp_send_json_error( 'Elementor builder factory not found.' );
			exit;
		}

		$source_post = get_post( $this->post_id );

		$automlp_wpml_elementor_data = get_post_meta( $this->post_id, '_elementor_data', true );

		if ( empty( $automlp_wpml_elementor_data ) ) {
			return;
		}


		$this->elementor_builder_factory->update_translated_post( $this->editor_type, $this->translated_post_id, $source_post, $this->translate_strings, $this->target_language );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		delete_metadata( 'post', $this->translated_post_id, '_elementor_element_cache', '' );
	}
}
