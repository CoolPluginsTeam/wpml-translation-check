<?php

namespace AUTOML_WPML\Includes\Wpml\Builder\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( Update_Block_Config::class ) ) {
	return;
}

/**
 * Update_Block_Config
 *
 * @package AUTOML_WPML\Includes\Wpml\Builder\Gutenberg
 */
class Update_Block_Config {

	/**
	 * @var Object Array of custom blocks config
	 */
	private $custom_blocks_config = null;

	/**
	 * @var Object Array of block default support child block
	 */
	private $block_default_support_child_block = null;

	/**
	 * @var Object Array of update block config
	 */
	private $update_block_config = null;

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @var array Array of block default attributes value
	 */
	private $block_default_attributes_value = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_filter( 'option_wpml-gutenberg-config', array( $this, 'update_gutenberg_config' ), 10, 2 );
	}

	public function update_gutenberg_config( $config, $option_name ) {

		if ( $this->is_content_update() && ! isset( $this->update_block_config ) ) {

			if ( ! isset( $this->custom_blocks_config ) || empty( $this->custom_blocks_config ) ) {
				$this->custom_blocks_config = $this->get_block_parse_rules();
			}

			if ( ! isset( $this->block_default_support_child_block ) || empty( $this->block_default_support_child_block ) ) {
				$this->block_default_support_child_block = $this->get_block_default_support_child_block();
			}

			if ( ! empty( $this->custom_blocks_config ) ) {
				$this->set_custom_block_config( $config, $this->custom_blocks_config );

				$this->update_block_config = $config;
			}
		}

		if ( isset( $this->update_block_config ) && ! empty( $this->update_block_config ) ) {
			return $this->update_block_config;
		}

		return $config;
	}

	private function get_block_parse_rules() {
		$response = wp_remote_get(
			esc_url_raw( AUTOML_AI_PLUGIN_URL . 'includes/wpml/builder/gutenberg/blocks-config/blocks-config.json' ),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			global $wp_filesystem;

			// Initialize the WordPress filesystem
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();

			$local_path = AUTOML_AI_PLUGIN_DIR . 'includes/wpml/builder/gutenberg/blocks-config/blocks-config.json';
			if ( $wp_filesystem->exists( $local_path ) && $wp_filesystem->is_readable( $local_path ) ) {
				$block_rules = $wp_filesystem->get_contents( $local_path );
			} else {
				$block_rules = array();
			}
		} else {
			$block_rules = wp_remote_retrieve_body( $response );
		}

		if ( empty( $block_rules ) ) {
			return array();
		}

		$block_translation_rules = json_decode( $block_rules );

		return $block_translation_rules;
	}

	private function get_block_default_support_child_block(): array {
		$response = wp_remote_get(
			esc_url_raw( AUTOML_AI_PLUGIN_URL . 'includes/wpml/builder/gutenberg/blocks-config/default-support-child-block.json' ),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			global $wp_filesystem;

			// Initialize the WordPress filesystem
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();

			$local_path = AUTOML_AI_PLUGIN_DIR . 'includes/wpml/builder/gutenberg/blocks-config/default-support-child-block.json';
			if ( $wp_filesystem->exists( $local_path ) && $wp_filesystem->is_readable( $local_path ) ) {
				$block_default_support_child_block = $wp_filesystem->get_contents( $local_path );
			} else {
				$block_default_support_child_block = array();
			}
		} else {
			$block_default_support_child_block = wp_remote_retrieve_body( $response );
		}

		if ( empty( $block_default_support_child_block ) ) {
			return array();
		}

		$block_default_support_child_block = json_decode( $block_default_support_child_block, true );

		return $block_default_support_child_block;
	}

	private function get_block_default_attributes_value(): array {
		$response = wp_remote_get(
			esc_url_raw( AUTOML_AI_PLUGIN_URL . 'includes/wpml/builder/gutenberg/blocks-config/attr-default-value.json' ),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			global $wp_filesystem;

			// Initialize the WordPress filesystem
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();

			$local_path = AUTOML_AI_PLUGIN_DIR . 'includes/wpml/builder/gutenberg/blocks-config/attr-default-value.json';
			if ( $wp_filesystem->exists( $local_path ) && $wp_filesystem->is_readable( $local_path ) ) {
				$block_default_attributes_value = $wp_filesystem->get_contents( $local_path );
			} else {
				$block_default_attributes_value = array();
			}
		} else {
			$block_default_attributes_value = wp_remote_retrieve_body( $response );
		}

		if ( empty( $block_default_attributes_value ) ) {
			return array();
		}

		$block_default_attributes_value = json_decode( $block_default_attributes_value, true );

		return $block_default_attributes_value;
	}

	private function set_custom_block_config( array &$config, $custom_block_config ): void {
		if ( empty( $custom_block_config ) ) {
			return;
		}

		$custom_block_config = (array) $custom_block_config;

		foreach ( $custom_block_config as $block_name => $block_config ) {

			// Skip if block not present in base config
			if ( ! isset( $config[ $block_name ] ) ) {
				if ( isset( $this->block_default_support_child_block[ $block_name ] ) ) {
					if ( true === $this->block_default_support_child_block[ $block_name ] ) {
						$this->set_block_custom_config( $config, $block_name, (array) $block_config );
					} elseif ( $this->block_default_support_child_block[ $block_name ]['parent'] && isset( $config[ $this->block_default_support_child_block[ $block_name ]['parent'] ] ) ) {
						$this->set_block_custom_config( $config, $block_name, (array) $block_config );
					}
				}
				continue;
			}

			$this->set_block_custom_config(
				$config,
				$block_name,
				(array) $block_config
			);
		}
	}

	private function set_block_custom_config( array &$config, string $block_name, array $block_config ): void {

		$attributes = isset( $block_config['attributes'] ) ? (array) $block_config['attributes'] : array();
		$xpath      = isset( $block_config['xpath'] ) ? json_decode( json_encode( $block_config['xpath'] ), true ) : array();

		if ( $xpath && ! empty( $xpath ) ) {
			if ( ! isset( $config[ $block_name ]['xpath'] ) ) {
				$config[ $block_name ]['xpath'] = array();
			}

			foreach ( $xpath as $xpath_item ) {
				$config[ $block_name ]['xpath'][] = $xpath_item;
			}
		}

		if ( $attributes && ! empty( $attributes ) ) {
			// Ensure base keys exist
			if ( ! isset( $config[ $block_name ]['key'] ) ) {
				$config[ $block_name ]['key'] = array();
			}

			foreach ( $attributes as $attr_key => $attr_value ) {
				// Skip if attribute already exists
				if ( isset( $config[ $block_name ]['key'][ $attr_key ] ) ) {
					continue;
				}

				/**
				 * CASE 1 — SIMPLE ATTRIBUTE (true)
				 */
				if ( $attr_value === true ) {
					$config[ $block_name ]['key'][ $attr_key ] = array();
					continue;
				}

				/**
				 * CASE 2 — OBJECT ATTRIBUTE
				 */
				if ( is_object( $attr_value ) ) {
					$this->parse_object_children( $config[ $block_name ]['key'][ $attr_key ], $attr_value );
					continue;
				}

				/**
				 * CASE 3 — ARRAY ATTRIBUTE
				 */
				if ( is_array( $attr_value ) ) {
					if ( ! isset( $config[ $block_name ]['key'][ $attr_key ] ) ) {
						$config[ $block_name ]['key'][ $attr_key ] = array( '*' => array( 'children' => array() ) );
					}

					if ( ! isset( $config[ $block_name ]['key'][ $attr_key ]['*'] ) ) {
						$config[ $block_name ]['key'][ $attr_key ]['*'] = array( 'children' => array() );
					}

					$this->parse_array_children( $config[ $block_name ]['key'][ $attr_key ]['*']['children'], $attr_value[0] );
				}
			}
		}
	}

	private function parse_object_children( &$reference, $object ): void {
		foreach ( (array) $object as $child_key => $child_value ) {

			// simple true value
			if ( $child_value === true ) {
				$reference[ $child_key ] = array();
				continue;
			}

			// nested object
			if ( is_object( $child_value ) ) {
				$this->parse_object_children( $reference[ $child_key ], $child_value );
				continue;
			}

			// nested array
			if ( is_array( $child_value ) ) {
				if ( ! isset( $reference[ $child_key ]['*'] ) ) {
					$reference[ $child_key ]['*'] = array( 'children' => array() );
				}
					$this->parse_array_children( $reference[ $child_key ]['*']['children'], $child_value[0] );
			}
		}
	}

	private function parse_array_children( &$reference, $child_values ): void {

		if ( is_object( $child_values ) ) {
			$this->parse_object_children( $reference, $child_values );
			return;
		}

		if ( is_array( $child_values ) ) {
			if ( ! isset( $reference['*'] ) ) {
				$reference['*'] = array( 'children' => array() );
			}
			$this->parse_array_children( $reference['*']['children'], $child_values[0] );
			return;
		}
	}

	public function is_content_update() {
		return ( defined( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) ) || ( defined( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) && true === constant( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) );
	}

	public function get_update_block_config() {
		if ( isset( $this->update_block_config ) ) {
			return $this->update_block_config;
		}

		return null;
	}

	public function get_custom_blocks_config() {

		if ( ! isset( $this->custom_blocks_config ) || empty( $this->custom_blocks_config ) ) {
			$this->custom_blocks_config = $this->get_block_parse_rules();
		}

		return (array) $this->custom_blocks_config;
	}

	public function fetch_block_default_attributes(): array {
		if ( ! isset( $this->block_default_attributes_value ) || empty( $this->block_default_attributes_value ) ) {
			$this->block_default_attributes_value = $this->get_block_default_attributes_value();
		}

		return (array) $this->block_default_attributes_value;
	}

	private function set_block_translatables_attributes( &$block, $custom_config, $translation_package, &$attr_translations, $translated_strings_texts ): void {
		if ( ! isset( $block['blockName'] ) ) {
			return;
		}

		$block_name = $block['blockName'];

		if ( isset( $custom_config[ $block_name ] ) ) {
			$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
			$this->update_block_attributes_in_package( $block, $attrs, $custom_config, $translation_package, $attr_translations, $translated_strings_texts );
		}

		if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as &$inner_block ) {
				$this->set_block_translatables_attributes( $inner_block, $custom_config, $translation_package, $attr_translations, $translated_strings_texts );
			}
		}
	}

	private function update_block_attributes_in_package( &$block, $block_attrs, $custom_config, $translation_package, &$attr_translations, $translated_strings_texts ): void {

		if ( isset( $custom_config[ $block['blockName'] ] ) ) {
			if ( ! isset( $this->block_default_attributes_value ) || empty( $this->block_default_attributes_value ) ) {
				$this->block_default_attributes_value = $this->fetch_block_default_attributes();
			}

			if ( isset( $custom_config[ $block['blockName'] ]->attributes ) ) {
				$automl_block_default_attrs = $custom_config[ $block['blockName'] ]->attributes;

				foreach ( $automl_block_default_attrs as $attr_key => $attr_value ) {
					$attr_value = isset( $this->block_default_attributes_value[ $block['blockName'] ]['attributes'][ $attr_key ] ) ? $this->block_default_attributes_value[ $block['blockName'] ]['attributes'][ $attr_key ] : ( isset( $block_attrs[ $attr_key ] ) ? $block_attrs[ $attr_key ] : null );

					if ( is_null( $attr_value ) ) {
						continue;
					}

					if ( isset( $attr_value ) && ! empty( $attr_value ) ) {
						if ( is_string( $attr_value ) ) {
							if ( isset( $block_attrs[ $attr_key ] ) ) {
								$attr_value = $block_attrs[ $attr_key ];

								if ( ! is_string( $attr_value ) ) {
									continue;
								}
							}

							$string_exists = false;

							foreach ( $translated_strings_texts as $translated_string_text ) {
								$trimmed_value = preg_replace( '/\s+/', '', $attr_value );
								if ( strpos( $translated_string_text, $trimmed_value ) !== false || strpos( $translated_string_text, $attr_value ) !== false ) {
									$string_exists = true;
									break;
								}
							}

							if ( ! $string_exists ) {
								continue;
							}

							$string_id = md5( $block['blockName'] . $attr_value );

							if ( ! isset( $translation_package[ $string_id ] ) ) {
								$attr_translations[ $string_id ] = array();
								$this->update_package_strings( $attr_translations[ $string_id ], wp_kses_post( $attr_value ), $string_id, null, wp_kses_post( $attr_value ), 'content', 'base64', 1 );
							}
						} elseif ( is_array( $attr_value ) ) {
							$current_attr   = isset( $block_attrs[ $attr_key ] ) ? $block_attrs[ $attr_key ] : array();
							$current_config = isset( $custom_config[ $block['blockName'] ]->attributes->$attr_key ) ? (array) $custom_config[ $block['blockName'] ]->attributes->$attr_key : array();

							if ( ! empty( $current_config ) ) {
								foreach ( $attr_value as $attr_key => $attr_attr_value ) {
									if ( is_string( $attr_attr_value ) ) {

										$string_exists = false;

										foreach ( $translated_strings_texts as $translated_string_text ) {
											$trimmed_value = preg_replace( '/\s+/', '', $attr_attr_value );
											if ( strpos( $translated_string_text, $trimmed_value ) !== false || strpos( $translated_string_text, $attr_attr_value ) !== false ) {
												$string_exists = true;
												break;
											}
										}

										if ( ! $string_exists ) {
											continue;
										}

										$string_id = md5( $block['blockName'] . $attr_attr_value );
										if ( ! isset( $translation_package[ $string_id ] ) ) {
											$attr_translations[ $string_id ] = array();
											$this->update_package_strings( $attr_translations[ $string_id ], wp_kses_post( $attr_attr_value ), $string_id, null, wp_kses_post( $attr_attr_value ), 'content', 'base64', 1 );
										}
									} elseif ( is_array( $attr_attr_value ) ) {
										$this->update_array_attributes_in_package( $block, $current_attr, $attr_attr_value, $current_config, $translation_package, $attr_translations, $translated_strings_texts );
									}
								}
							}
						}
					}
				}
			}
		}
	}

	private function update_array_attributes_in_package( &$block, $block_attrs, $attr_values, $current_config, $translation_package, &$attr_translations, $translated_strings_texts ): void {
		foreach ( $attr_values as $attr_key => $attr_value ) {
			if ( ! isset( $current_config[ $attr_key ] ) && ! isset( $current_config[0]->$attr_key ) ) {
				continue;
			}

			if ( isset( $attr_value ) && ! empty( $attr_value ) ) {
				if ( is_string( $attr_value ) ) {

					$string_exists = false;

					foreach ( $translated_strings_texts as $translated_string_text ) {
						$trimmed_value = preg_replace( '/\s+/', '', $attr_value );
						if ( strpos( $translated_string_text, $trimmed_value ) !== false || strpos( $translated_string_text, $attr_value ) !== false ) {
							$string_exists = true;
							break;
						}
					}

					if ( ! $string_exists ) {
						continue;
					}

					$string_id = md5( $block['blockName'] . $attr_value );

					if ( isset( $block_attrs[ $attr_key ] ) ) {
						$attr_value = $block_attrs[ $attr_key ];

						if ( ! is_string( $attr_value ) ) {
							continue;
						}
					}

					if ( ! isset( $translation_package[ $string_id ] ) ) {
						$attr_translations[ $string_id ] = array();
						$this->update_package_strings( $attr_translations[ $string_id ], wp_kses_post( $attr_value ), $string_id, null, wp_kses_post( $attr_value ), 'content', 'base64', 1 );
					}
				} elseif ( is_array( $attr_value ) ) {

					$current_attr   = isset( $block_attrs[ $attr_key ] ) ? $block_attrs[ $attr_key ] : array();
					$current_config = array();
					$current_config = (array) $this->get_current_config( $current_config, $attr_key );
					if ( empty( $current_config ) ) {
						$current_config = (array) $this->get_current_config( $current_config[0], $attr_key );
					}
					if ( ! empty( $current_config ) ) {
						$this->update_array_attributes_in_package( $block, $current_attr, $attr_value, $current_config, $translation_package, $attr_translations );
					}
				}
			}
		}
	}

	private function get_current_config( $current_config, $attr_key ) {

		$current_confing = array();
		if ( is_array( $current_config ) && isset( $current_config[ $attr_key ] ) ) {
			$current_confing = $current_config[ $attr_key ];
		} elseif ( is_object( $current_config ) && isset( $current_config->$attr_key ) ) {
			$current_confing = $current_config->$attr_key;
		}

		return $current_confing;
	}

	private function update_package_strings( array &$append_data, $html, $field_key, $field_name, $text, $type, $format, $translate ): void {
		if ( defined( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) && true === constant( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) ) {
			$this->set_content_data( $append_data, 'html', $html );
			$this->set_content_data( $append_data, 'text', $text );
		} else {
			$this->set_content_data( $append_data, 'html', $html );
			$this->set_content_data( $append_data, 'field_key', $field_key );
			$this->set_content_data( $append_data, 'field_name', $field_name );
			$this->set_content_data( $append_data, 'text', $text );
			$this->set_content_data( $append_data, 'type', $type );
			$this->set_content_data( $append_data, 'format', $format );
			$this->set_content_data( $append_data, 'translate', $translate );
		}
	}

	private function set_content_data( &$append_data, $key, $value ) {
		if ( isset( $value ) && ! empty( $value ) ) {
			$append_data[ $key ] = $value;
		}
	}

	final public function get_custom_attributes_translations( int $post_id, array $translation_package, array $translated_strings_texts ): array {
		$attr_translations = array();

		$custom_blocks_config = self::get_instance()->get_custom_blocks_config();

		$source_post    = get_post( $post_id );
		$source_content = $source_post->post_content;
		$parse_blocks   = parse_blocks( $source_content );

		foreach ( $parse_blocks as &$block ) {
			$block_name = $block['blockName'];
			if ( isset( $custom_blocks_config[ $block_name ] ) ) {
				$this->set_block_translatables_attributes( $block, $custom_blocks_config, $translation_package, $attr_translations, $translated_strings_texts );
			}

			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as &$inner_block ) {
					$this->set_block_translatables_attributes( $inner_block, $custom_blocks_config, $translation_package, $attr_translations, $translated_strings_texts );
				}
			}
		}

		return $attr_translations;
	}
}
