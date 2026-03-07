<?php

namespace AUTOML_WPML\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( Sanitized_Content::class ) ) {
	return;
}

	/**
	 * Sanitized_Content
	 *
	 * @package AUTOML_WPML\Helper
	 */
class Sanitized_Content {

	/**
	 * The HTML to sanitize.
	 *
	 * @var string
	 */
	private $source_html;

	/**
	 * The target HTML.
	 *
	 * @var string
	 */
	private $translated_html;

	/**
	 * The sanitized HTML.
	 *
	 * @var string
	 */
	private $sanitized_html;

	/**
	 * The allowed styles.
	 *
	 * @var array
	 */
	private $allowed_styles;

	/**
	 * Constructor.
	 *
	 * @param string $html The source HTML to sanitize.
	 */
	public function __construct( string $html ) {
		$this->source_html = $html;
	}

	/**
	 * Checks if the string has HTML.
	 *
	 * @param string $string The string to check.
	 * @return bool True if the string has HTML, false otherwise.
	 */
	private function string_has_html( string $string ): bool {
		return wp_strip_all_tags( $string ) !== $string;
	}

	/**
	 * Extracts the allowed HTML from the string.
	 *
	 * @param string $html The source HTML to extract the allowed HTML from.
	 * @return array The allowed HTML.
	 */
	private function extract_allowed_html_from_string( string $html ): array {

		$allowed_html = array();

		if ( empty( $html ) ) {
			return $allowed_html;
		}

		preg_match_all(
			'/<([a-z0-9\-]+)(\s[^>]*?)?>/i',
			$html,
			$tag_matches,
			PREG_SET_ORDER
		);

		foreach ( $tag_matches as $tag_match ) {

			$tag = strtolower( $tag_match[1] );

			if ( ! isset( $allowed_html[ $tag ] ) ) {
				$allowed_html[ $tag ] = array();
			}

			if ( empty( $tag_match[2] ) ) {
				continue;
			}

			$attr_string = trim( $tag_match[2] );

			preg_match_all(
				'/([a-z0-9\-:]+)(\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/i',
				$attr_string,
				$attr_matches,
				PREG_SET_ORDER
			);

			foreach ( $attr_matches as $attr_match ) {

				$attr = strtolower( $attr_match[1] );

				if ( $attr === $tag ) {
					continue;
				}

				// Block unsafe attributes.
				if ( in_array(
					$attr,
					array( 'onclick', 'onload', 'onerror', 'onmouseover' ),
					true
				) ) {
					continue;
				}

				$allowed_html[ $tag ][ $attr ] = true;
			}
		}

		// Safety fallback.
		if ( empty( $allowed_html ) ) {
			$allowed_html = wp_kses_allowed_html( 'post' );
		}

		return $allowed_html;
	}

	/**
	 * Gets the boolean attributes without value.
	 *
	 * @param string $html The source HTML to get the boolean attributes without value from.
	 * @return array The boolean attributes without value.
	 */
	private function get_boolean_attrs_without_value( string $html ): array {

		$boolean_attrs = array(
			'required',
			'disabled',
			'checked',
			'selected',
			'readonly',
			'multiple',
			'autofocus',
			'novalidate',
			'formnovalidate',
			'hidden',
		);

		$found_attrs = array();

		foreach ( $boolean_attrs as $attr ) {

			if ( preg_match( '/\s' . $attr . '(\s|>|\/)/i', $html ) &&
			! preg_match( '/\s' . $attr . '\s*=/i', $html ) ) {
				$found_attrs[] = $attr;
			}
		}

		return $found_attrs;
	}

	/**
	 * Normalizes the boolean attributes conditionally.
	 *
	 * @param string $translated_html The translated HTML to normalize the boolean attributes conditionally.
	 * @param array  $boolean_attrs_to_fix The boolean attributes to fix.
	 * @return string The normalized HTML.
	 */
	private function normalize_boolean_attributes_conditionally(
		string $translated_html,
		array $boolean_attrs_to_fix
	): string {

		if ( empty( $boolean_attrs_to_fix ) ) {
			return $translated_html;
		}

		foreach ( $boolean_attrs_to_fix as $attr ) {
			$translated_html = preg_replace(
				'/\s' . $attr . '=""/i',
				' ' . $attr,
				$translated_html
			);
		}

		return $translated_html;
	}

	/**
	 * Gets the sanitized content.
	 *
	 * @param string $translated_html The translated HTML to sanitize.
	 * @return string The sanitized content.
	 */
	public function get_sanitized_content(string $translated_html): string {
		$this->translated_html = $translated_html;

		if ( ! $this->string_has_html( $this->translated_html ) ) {
			return wp_kses_post( $this->translated_html );
		}

		$this->sanitize_html();
		return $this->sanitized_html;
	}

	private function extract_allowed_styles_from_string( string $html ): array {

		$allowed_styles = array();
	
		if ( empty( $html ) ) {
			return $allowed_styles;
		}
	
		// 1. Get all style="..." attributes
		preg_match_all( '/style\s*=\s*["\']([^"\']*)["\']/i', $html, $matches );
	
		if ( empty( $matches[1] ) ) {
			return $allowed_styles;
		}
	
		foreach ( $matches[1] as $style_string ) {
	
			// 2. Split CSS declarations
			$declarations = explode( ';', $style_string );
	
			foreach ( $declarations as $declaration ) {
	
				if ( strpos( $declaration, ':' ) === false ) {
					continue;
				}
	
				// 3. Extract property name before colon
				list( $property ) = explode( ':', $declaration, 2 );
	
				$property = strtolower( trim( $property ) );
	
				if ( ! empty( $property ) ) {
					$allowed_styles[] = sanitize_text_field(wp_unslash($property));
				}
			}
		}
	
		// 4. Remove duplicates & reindex
		$allowed_styles = array_values( array_unique( $allowed_styles ) );
	
		return $allowed_styles;
	}	

	/**
	 * Sanitizes the HTML.
	 *
	 * @return void
	 */
	private function sanitize_html(): void {

		if(!isset($this->allowed_styles) || empty($this->allowed_styles)){
			$this->allowed_styles = $this->extract_allowed_styles_from_string( $this->source_html );
		}

		// Add the filter to allow the flex styles
		add_filter( 'safe_style_css', array( $this, 'automl_wpml_allow_flex_styles' ), 10, 1 );

		// Extract allowed tags + attributes from source HTML.
		$allowed_html_tags = $this->extract_allowed_html_from_string( $this->source_html );

		// Detect boolean attributes used in source without value.
		$boolean_attrs_to_fix = $this->get_boolean_attrs_without_value( $this->source_html );

		$this->sanitized_html = wp_kses( $this->translated_html, $allowed_html_tags );

		$this->sanitized_html = $this->normalize_boolean_attributes_conditionally( $this->sanitized_html, $boolean_attrs_to_fix );

		remove_filter( 'safe_style_css', array( $this, 'automl_wpml_allow_flex_styles' ), 10 );
	}

	public function automl_wpml_allow_flex_styles( array $styles ): array {
		if(isset($this->allowed_styles) && !empty($this->allowed_styles)){
			$styles = array_merge($styles, $this->allowed_styles);
		}

		return $styles;
	}
}
