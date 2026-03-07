<?php

namespace AUTOML_WPML\Includes\Wpml\Builder\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_Gutenberg_Integration;
use WPML_Gutenberg_Config_Option;
use WPML_ST_String_Factory;
use WPML_Gutenberg_Strings_Registration;
use WPML_PB_Reuse_Translations;
use WPML_PB_String_Translation;
use WPML\PB\TranslateLinks as WPML_TranslateLinks;
use WPML\PB\Gutenberg\StringsInBlock\Collection as StringsInBlockCollection;
use WPML\PB\Gutenberg\StringsInBlock\HTML as StringsInBlockHTML;
use WPML\PB\Gutenberg\StringsInBlock\Attributes as StringsInBlockAttributes;
use AUTOML_WPML\Includes\Wpml\Builder\Content_Update_Base;
use AUTOML_WPML\Includes\Wpml\Builder\Gutenberg\Update_Block_Config;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;

use function WPML\Container\make;
/**
 * Gutenberg_Update
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Gutenberg_Update extends Content_Update_Base {
	/**
	 * @var WPML_Gutenberg_Integration
	 */
	private $gutenberg_builder_factory;

	/**
	 * @var Object Array of custom blocks config
	 */
	private $custom_blocks_config;

	/**
	 * @var Array of block default attributes value
	 */
	private $block_default_attributes_value;

	/**
	 * Editor Type
	 */
	protected $editor_type = 'Gutenberg';

	public function __construct( int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce ) {
		parent::__construct( $post_id, $translated_post_id, $translate_strings, $target_language, $nonce );
	}

	protected function is_content_update() {
		return ( defined( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) );
	}

	protected function cretae_builder_integration(): void {

		if ( ! $this->content_update_allowed ) {
			wp_send_json_error( 'You are not authorized to perform this action three.' );
			exit;
		}

		global $sitepress, $wpdb;

		$config_option    = new WPML_Gutenberg_Config_Option();
		$strings_in_block = $this->create_strings_in_block( $config_option );
		$string_factory   = new WPML_ST_String_Factory( $wpdb );

		$strings_registration = new WPML_Gutenberg_Strings_Registration(
			$strings_in_block,
			$string_factory,
			new WPML_PB_Reuse_Translations( $string_factory ),
			new WPML_PB_String_Translation( $wpdb ),
			make( 'WPML_Translate_Link_Targets' ),
			WPML_TranslateLinks::getTranslatorForString( $string_factory, $sitepress->get_active_languages() )
		);

		$this->gutenberg_builder_factory = new WPML_Gutenberg_Integration( $strings_in_block, $config_option, $strings_registration, $sitepress );
	}

	private function create_strings_in_block( WPML_Gutenberg_Config_Option $config_option ) {
		$string_parsers = array(
			new StringsInBlockHTML( $config_option ),
			new StringsInBlockAttributes( $config_option ),
		);

		return new StringsInBlockCollection( $string_parsers );
	}

	private function update_block_data( &$block, $custom_config ): void {
		if ( ! isset( $block['blockName'] ) ) {
			return;
		}

		$block_name = $block['blockName'];

		if ( isset( $custom_config[ $block_name ] ) ) {
			$this->set_block_default_attributes_value( $block, $custom_config );
		}

		if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as &$inner_block ) {
				$this->update_block_data( $inner_block, $custom_config );
			}
		}
	}

	private function set_block_default_attributes_value( &$block, $custom_config ): void {
		if ( isset( $custom_config[ $block['blockName'] ] ) ) {
			if ( ! isset( $this->block_default_attributes_value ) ) {
				$this->block_default_attributes_value = Update_Block_Config::get_instance()->fetch_block_default_attributes();
			}

			if ( isset( $this->block_default_attributes_value[ $block['blockName'] ]['attributes'] ) ) {
				$automl_block_default_attrs = $this->block_default_attributes_value[ $block['blockName'] ]['attributes'];

				foreach ( $automl_block_default_attrs as $attr_key => $attr_value ) {
					if ( ! isset( $block['attrs'][ $attr_key ] ) ) {
						$block['attrs'][ $attr_key ] = $attr_value;
					}
				}
			}
		}
	}

	function get_all_attr_matches( $html, $attr_name ) {

		$results = array();

		if ( empty( $html ) || empty( $attr_name ) ) {
			return $results;
		}

		// Regex:  attr="value"  OR  attr='value'
		$pattern = '/\b(' . preg_quote( $attr_name, '/' ) . ')\s*=\s*("|\')(.*?)\2/i';

		if ( preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$results[] = array( $match[1], $match[3] );
			}
		}

		return $results;
	}

	private function decode_aria_label_entities_only( &$block, $custom_blocks_config ) {
		$xpath = isset( $custom_blocks_config[ $block['blockName'] ]->xpath ) ? $custom_blocks_config[ $block['blockName'] ]->xpath : array();

		if ( ! isset( $block['innerContent'] ) && empty( $block['innerContent'] ) ) {
			return;
		}

		foreach ( $xpath as $xpath_item ) {
			$xpath_item = (array) $xpath_item;
			if ( isset( $xpath_item['value'] ) ) {
				$target_attr = explode( '/@', $xpath_item['value'] )[1];

				foreach ( $block['innerContent'] as &$inner_content ) {
					$attr_matches = $this->get_all_attr_matches( $inner_content, $target_attr );
					foreach ( $attr_matches as $attr_data ) {
						$attr_name  = $attr_data[0];
						$attr_value = $attr_data[1];

						if ( ! isset( $this->translate_strings[ md5( $block['blockName'] . $attr_value ) ] ) ) {
							$decoded_attr_value = html_entity_decode( $attr_value );
							$inner_content      = str_replace( $attr_name . '="' . $attr_value . '"', $attr_name . '="' . $decoded_attr_value . '"', $inner_content );
						}
					}
				}

				$block['innerHTML'] = implode( '', $block['innerContent'] );
			}
		}

		return $block;
	}

	protected function update_builder_translation(): void {
		if ( ! $this->gutenberg_builder_factory instanceof WPML_Gutenberg_Integration ) {
			wp_send_json_error( 'Gutenberg builder factory not found.' );
			exit;
		}

		if ( ! isset( $this->custom_blocks_config ) || empty( $this->custom_blocks_config ) ) {
			$this->custom_blocks_config = Update_Block_Config::get_instance()->get_custom_blocks_config();
		}

		$custom_blocks_config = (array) $this->custom_blocks_config;

		$source_post    = get_post( $this->post_id );
		$source_content = $source_post->post_content;
		$parse_blocks   = parse_blocks( $source_content );

		foreach ( $parse_blocks as &$block ) {
			$block_name = $block['blockName'];
			if ( isset( $custom_blocks_config[ $block_name ] ) ) {
				$this->update_block_data( $block, $custom_blocks_config );
			}

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as &$inner_block ) {
					$this->update_block_data( $inner_block, $custom_blocks_config );
				}
			}
		}

		$source_content = serialize_blocks( $parse_blocks );

		$updated_content = $this->gutenberg_builder_factory->replace_strings_in_blocks( $source_content, $this->translate_strings, $this->target_language );

		$updated_content = $this->decode_tags_attributes_in_blocks( parse_blocks( $updated_content ), $custom_blocks_config );

		$updated_content = serialize_blocks( $updated_content );

		wpml_update_escaped_post(
			array(
				'ID'           => $this->translated_post_id,
				'post_content' => $updated_content,
			),
			$this->target_language
		);
	}

	private function decode_tags_attributes_in_blocks( &$blocks, $custom_blocks_config ) {
		foreach ( $blocks as &$block ) {
			if ( isset( $block['blockName'] ) && isset( $custom_blocks_config[ $block['blockName'] ] ) && isset( $custom_blocks_config[ $block['blockName'] ]->xpath ) ) {
				$this->decode_aria_label_entities_only( $block, $custom_blocks_config );
			}

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				$this->decode_tags_attributes_in_blocks( $block['innerBlocks'], $custom_blocks_config );
			}
		}

		return $blocks;
	}
}
