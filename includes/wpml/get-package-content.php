<?php

namespace AUTOML_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_Element_Translation_Package;
use WPML_Package_Helper;
use DOMDocument;
use DOMXPath;
use WPML_Package;
use Exception;
use WPML_AT_Helper;
use AUTOML_WPML\Includes\Wpml\Builder\Gutenberg\Update_Block_Config;

/**
 * Get_Package_Content
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Get_Package_Content {

	private $post_id;
	private $source_lang;
	private $package;
	private $package_factory;
	private $translation_package = array();
	private $editor_type;
	private $translated_strings_texts = array();

	public function __construct( $post_id, $source_lang = null ) {
		if ( ! $this->is_bulk_translation() && ! $this->is_create_post() ) {
			return array();
		}

		$this->post_id     = $post_id;
		$this->source_lang = $source_lang;
		Update_Block_Config::get_instance();

		$automl_wpml_file_exists = $this->include_required_files();

		if ( ! $automl_wpml_file_exists ) {
			throw new Exception( 'WPML Element Translation Package file not found Please check "WPML String Translation" plugin is installed and activated' );
		}

		$this->create_package();
	}

	private function is_bulk_translation() {
		return ( defined( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) && true === constant( 'DOING_AUTOML_WPML_BULK_POST_TRANSLATION' ) );
	}

	private function is_create_post() {
		return ( defined( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST' ) && true === constant( 'DOING_AUTOML_WPML_CREATE_TRANSLATED_POST' ) );
	}

	private function include_required_files() {

		$automl_wpml_file_exists = true;

		if ( ! class_exists( WPML_Element_Translation_Package::class ) ) {
			$base = WP_PLUGIN_DIR . '/sitepress-multilingual-cms/classes';
			$file = $base . '/translation-jobs/class-wpml-element-translation-package.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			} else {
				$automl_wpml_file_exists = false;
			}
		}

		if ( ! class_exists( WPML_Package_Helper::class ) ) {
			$file = WP_PLUGIN_DIR . '/wpml-string-translation/inc/package-translation/inc/wpml-package-translation-helper.class.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			} else {
				$automl_wpml_file_exists = false;
			}
		}

		return $automl_wpml_file_exists;
	}

	private function create_package() {

		if ( wpml_is_st_loaded() ) {
			$automl_wpml_package_helper = new WPML_Package_Helper();
			$this->package              = $automl_wpml_package_helper->get_post_string_packages( false, $this->post_id );

			if ( empty( $this->package ) ) {
				$builder = new WPML_Element_Translation_Package( null );

				$builder->create_translation_package(
					$this->post_id,
					$this->source_lang,
					true // is original
				);

				$this->package = $automl_wpml_package_helper->get_post_string_packages( false, $this->post_id );
			}

			if ( ! empty( $this->package ) ) {
				$this->translation_package = $this->set_translatable_strings();
			}
		}

		// Set post title, excerpt for translation
		$this->translation_package = $this->set_post_default_strings();
	}

	private function set_post_default_strings(): array {
		$source_post = get_post( $this->post_id );

		if ( ! $source_post ) {
			return $this->translation_package;
		}

		if ( isset( $source_post->post_title ) && ! empty( $source_post->post_title ) ) {
			$this->translation_package['title'] = $source_post->post_title;
		}

		if ( isset( $source_post->post_excerpt ) && ! empty( $source_post->post_excerpt ) ) {
			$this->translation_package['excerpt'] = $source_post->post_excerpt;
		}

		return $this->translation_package;
	}

	private function set_translatable_strings(): array {

		if ( empty( $this->package ) ) {
			return array();
		}

		$translation_package = array();

		foreach ( $this->package as $package ) {
			if ( ! isset( $package->kind ) || ! in_array( $package->kind, WPML_AT_Helper::supported_editors(), true ) ) {
				continue;
			}

			if ( ! isset( $this->editor_type ) || empty( $this->editor_type ) ) {
				$this->editor_type = $package->kind;
			}

			$strings = $package->get_package_strings();

			if ( $strings && is_array( $strings ) && ! empty( $strings ) && ! isset( $translation_package['contents'] ) ) {
				$translation_package['contents'] = array();
			}

			foreach ( $strings as $string ) {
				if ( 'LINK' !== $string->type ) {
					$string_value = $string->value;

					$translation_package['contents'][ $string->name ] = array(
						'translate' => 1,
						// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						'data'      => base64_encode( $string_value ),
						// phpcs:enable
						'wrap_tag'  => isset( $string->wrap_tag ) ? $string->wrap_tag : '',
						'format'    => 'base64',
					);
				}
			}
		}
		$automl_wpml_content_translation = $this->extract_translatable_strings( $translation_package );

		$automl_wpml_content_translation = array( 'contents' => $automl_wpml_content_translation );

		return $automl_wpml_content_translation;
	}

	public function get_translatable_strings() {
		if ( $this->editor_type === 'Gutenberg' ) {
			$custom_block_attributes_translations = $this->update_translation_package_strings();

			if ( isset( $custom_block_attributes_translations ) && is_array( $custom_block_attributes_translations ) ) {
				$this->translation_package['contents'] = array_merge( $this->translation_package['contents'], $custom_block_attributes_translations );
			}
		}

		return $this->translation_package;
	}

	private function update_translation_package_strings() {
		if ( $this->editor_type === 'Gutenberg' && isset( $this->translation_package['contents'] ) && is_array( $this->translation_package['contents'] ) ) {
			if ( ! is_array( $this->translated_strings_texts ) ) {
				$this->translated_strings_texts = array();
			}

			return Update_Block_Config::get_instance()->get_custom_attributes_translations( $this->post_id, $this->translation_package['contents'], $this->translated_strings_texts );
		}
	}

	private function extract_translatable_strings( $package ) {
		$strings = array();

		if ( ! isset( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
			return $strings;
		}

		foreach ( $package['contents'] as $key => $content ) {

			// Only process fields with translate => 1
			if ( ! is_array( $content ) || ! isset( $content['translate'] ) || $content['translate'] != 1 ) {
				continue;
			}

			// Skip metadata fields (name, type, etc.)
			if ( strpos( $key, '-name' ) !== false || strpos( $key, '-type' ) !== false ) {
				continue;
			}

			// Get the field data
			$data = isset( $content['data'] ) ? $content['data'] : '';

			// Decode if base64
			if ( isset( $content['format'] ) && $content['format'] === 'base64' ) {
				$data = base64_decode( $data );
			}

			// Skip empty data
			if ( empty( $data ) || trim( $data ) === '' ) {
				continue;
			}

			// Try to get field name from corresponding -name field if it exists
			$field_name = $key;
			$name_key   = $key . '-name';
			if ( isset( $package['contents'][ $name_key ] ) && isset( $package['contents'][ $name_key ]['data'] ) ) {
				$name_data = $package['contents'][ $name_key ]['data'];
				if ( isset( $package['contents'][ $name_key ]['format'] ) && $package['contents'][ $name_key ]['format'] === 'base64' ) {
					$name_data = base64_decode( $name_data );
				}
				if ( ! empty( $name_data ) ) {
					$field_name = $name_data;
					// Format custom field names to be more readable
					if ( strpos( $key, 'field-' ) === 0 ) {
						// Remove leading underscore and format
						$field_name = ltrim( $field_name, '_' );
						$field_name = str_replace( '_', ' ', $field_name );
						// Remove numeric suffix like -0, -1, etc.
						$field_name = preg_replace( '/-\d+$/', '', $field_name );
						$field_name = ucwords( $field_name );
					}
				}
			}

			// If no name field found or name is still the key, format the key as a readable name
			if ( $field_name === $key ) {
				// Handle field- prefix
				if ( strpos( $key, 'field-' ) === 0 ) {
					$field_name = str_replace( 'field-', '', $key );
					$field_name = ltrim( $field_name, '_' );
					$field_name = preg_replace( '/-\d+$/', '', $field_name );
					$field_name = str_replace( '_', ' ', $field_name );
					$field_name = ucwords( $field_name );
				} else {
					$field_name = str_replace( '_', ' ', $key );
					$field_name = ucwords( $field_name );
				}
			}

			// Special handling for body field - parse HTML and break into individual elements
			if ( $key === 'body' ) {
				$parsed_elements = $this->parse_html_content( $data );

				if ( ! empty( $parsed_elements ) ) {
					// Add each element as a separate row
					foreach ( $parsed_elements as $index => $element ) {
						$text = trim( $element['text'] );
						if ( ! empty( $text ) ) {
							$element_field_name = $field_name . ' - ' . ucfirst( $element['type'] ) . ' ' . ( $index + 1 );
							if ( $element['type'] === 'heading' ) {
								$element_field_name = $field_name . ' - ' . strtoupper( $element['tag'] ) . ' ' . ( $index + 1 );
							}

							$strings[ $key . '_element_' . $index ] = array();

							$this->set_content_strings(
								$strings[ $key . '_element_' . $index ],
								$key . '_element_' . $index,
								$element_field_name,
								$text,
								$element['html'],
								$element['type'],
								isset( $content['format'] ) ? $content['format'] : 'text',
								isset( $content['translate'] ) ? $content['translate'] : 0
							);
						}
					}
				} else {
					// Fallback: if parsing fails, use the whole content
					$text = wp_strip_all_tags( $data );
					$text = trim( $text );
					if ( ! empty( $text ) ) {
						continue;
					}

					$strings[ $field_name ] = array();

					$this->set_content_strings(
						$strings[ $field_name ],
						$field_name,
						'body',
						$text,
						$data,
						'content',
						isset( $content['format'] ) ? $content['format'] : 'text',
						isset( $content['translate'] ) ? $content['translate'] : 0
					);
				}
			} else {
				// For non-body fields, use the original logic
				// Get plain text version for display
				$text = wp_strip_all_tags( $data );
				$text = trim( $text );

				// Skip if no text content
				if ( empty( $text ) ) {
					continue;
				}

				$strings[ $key ] = array();

				$this->set_content_strings(
					$strings[ $key ],
					$key,
					null,
					$text,
					$data,
					'content',
					isset( $content['format'] ) ? $content['format'] : 'text',
					isset( $content['translate'] ) ? $content['translate'] : 0
				);
			}
		}

		return $strings;
	}

	private function set_content_strings( &$append_data, $field_key, $field_name, $text, $html, $type, $format, $translate = 0 ) {

		if ( strpos( $text, '!#readmoreText!#' ) !== false || strpos( $html, 'class="wp-block-themeisle-blocks-countdown"' ) !== false ) {
			return;
		}

		if ( ! is_array( $this->translated_strings_texts ) ) {
			$this->translated_strings_texts = array();
		}

		if ( ! in_array( $text, $this->translated_strings_texts ) ) {
			$this->translated_strings_texts[] = $text;
		}

		if ( $this->is_bulk_translation() ) {
			if ( $translate == 1 ) {
				$this->set_content_data( $append_data, 'html', $html );
				$this->set_content_data( $append_data, 'text', $text );
			}
			return;
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

	/**
	 * Parse HTML content and extract individual text elements
	 *
	 * @param string $html The HTML content
	 * @return array Array of text elements with their HTML
	 */
	private function parse_html_content( $html ) {
		$elements = array();

		if ( empty( $html ) ) {
			return $elements;
		}

		// Use DOMDocument to parse HTML
		$dom = new DOMDocument();
		// Suppress warnings for malformed HTML
		libxml_use_internal_errors( true );

		// Wrap in a container div to handle multiple root elements
		// Prepend XML encoding declaration for proper UTF-8 handling (replaces deprecated mb_convert_encoding)
		$wrapped_html = '<?xml encoding="UTF-8">' . '<div>' . $html . '</div>';
		@$dom->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// Get the container div
		$container = $dom->getElementsByTagName( 'div' )->item( 0 );
		if ( ! $container ) {
			return $elements;
		}

		// Define semantic block-level elements that should be extracted
		$block_elements = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'li', 'blockquote', 'pre' );

		// Get all block-level elements that contain text
		$xpath       = new DOMXPath( $dom );
		$block_nodes = $xpath->query( './/*[not(self::script) and not(self::style) and not(self::noscript) and not(self::img) and not(self::svg) and normalize-space(text())]', $container );

		$processed_nodes = array();

		foreach ( $block_nodes as $node ) {
			$tag_name = $node->nodeName;

			// Skip if already processed as part of a parent
			if ( in_array( $node, $processed_nodes, true ) ) {
				continue;
			}

			// Get the full text content of this element
			$text = trim( $node->textContent );

			// Skip if empty or only whitespace
			if ( empty( $text ) ) {
				continue;
			}

			// Check if this is a block-level element or if it's a semantic container
			$is_block_element = in_array( $tag_name, $block_elements );

			// Check if this element has block-level children
			$has_block_children = false;
			foreach ( $node->childNodes as $child ) {
				if ( $child->nodeType === XML_ELEMENT_NODE && in_array( $child->nodeName, $block_elements ) ) {
					$has_block_children = true;
					break;
				}
			}

			// If it's a block element and doesn't have block children, extract it
			// OR if it's an inline element (like strong, em, span) but is a direct child of a block element
			if ( $is_block_element && ! $has_block_children ) {
				// Mark all child nodes as processed
				foreach ( $node->getElementsByTagName( '*' ) as $child ) {
					$processed_nodes[] = $child;
				}

				// Get the HTML for this element
				$element_html = $dom->saveHTML( $node );

				// Determine element type for better naming
				$element_type = 'content';
				if ( in_array( $tag_name, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) {
					$element_type = 'heading';
				} elseif ( $tag_name === 'p' ) {
					$element_type = 'paragraph';
				} elseif ( $tag_name === 'div' ) {
					$element_type = 'div';
				} elseif ( $tag_name === 'span' ) {
					$element_type = 'span';
				}

				$elements[] = array(
					'text' => $text,
					'html' => $element_html,
					'type' => $element_type,
					'tag'  => $tag_name,
				);
			}
		}

		return $elements;
	}
}
