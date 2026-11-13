<?php
/**
 * Builds the translation prompt.
 *
 * Kept separate from the gateway so prompt wording can be tuned, filtered or
 * unit-tested without touching transport code.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompt_Builder
 */
class Prompt_Builder {

	/**
	 * Compose the prompt for one batch.
	 *
	 * @param array  $strings   Numerically indexed source strings.
	 * @param string $from_lang Source language code.
	 * @param string $to_lang   Target language code.
	 * @return string
	 */
	public static function build( array $strings, $from_lang, $to_lang ) {
		$target_name = self::language_name( $to_lang );
		$source_name = self::language_name( $from_lang );

		$rules = array(
			sprintf(
				/* translators: 1: source language name, 2: target language name */
				__( 'Translate each value from %1$s into %2$s. Produce a natural, meaning-based translation rather than a literal one.', 'wpml-translation-check' ),
				$source_name,
				$target_name
			),
			__( 'Leave anything inside square brackets exactly as it is. Those are shortcodes or dynamic placeholders.', 'wpml-translation-check' ),
			__( 'Keep every HTML tag and attribute intact, including class, id and data-* attributes. Do not add, remove or reorder markup.', 'wpml-translation-check' ),
			__( 'Preserve leading and trailing whitespace, and keep line breaks where they appear in the source.', 'wpml-translation-check' ),
			__( 'Leave URLs, email addresses, file paths and code identifiers untranslated.', 'wpml-translation-check' ),
			__( 'Return a JSON object whose keys are the same numeric keys as the input and whose values are the translated strings.', 'wpml-translation-check' ),
			__( 'Return raw JSON only. No markdown fences, no commentary, no surrounding prose.', 'wpml-translation-check' ),
			__( 'Do not escape double quotes with extra backslashes. The output must parse as valid JSON on the first attempt.', 'wpml-translation-check' ),
		);

		$context = self::site_context();

		if ( '' !== $context ) {
			$rules[] = sprintf(
				/* translators: %s: short description of the website */
				__( 'Use this site context to guide tone and terminology: %s', 'wpml-translation-check' ),
				$context
			);
		}

		$numbered = '';

		foreach ( $rules as $i => $rule ) {
			$numbered .= sprintf( "%d. %s\n", $i + 1, $rule );
		}

		$payload = wp_json_encode( $strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$prompt = __( 'You are a translation engine. Follow every rule below exactly.', 'wpml-translation-check' )
			. "\n\n" . $numbered
			. "\n" . __( 'Input JSON:', 'wpml-translation-check' ) . "\n" . $payload;

		/**
		 * Filter the finished translation prompt.
		 *
		 * @param string $prompt    Prompt text.
		 * @param array  $strings   Source strings for this batch.
		 * @param string $from_lang Source language code.
		 * @param string $to_lang   Target language code.
		 */
		return apply_filters( 'automlp_translation_prompt', $prompt, $strings, $from_lang, $to_lang );
	}

	/**
	 * Site topic, purpose and audience, as configured in WPML.
	 *
	 * @return string
	 */
	private static function site_context() {
		if ( ! class_exists( '\WPML\TM\API\ATE\WebsiteContext' ) ) {
			return '';
		}

		$context = \WPML\TM\API\ATE\WebsiteContext::getWebsiteContext();

		if ( ! is_array( $context ) ) {
			return '';
		}

		$parts = array();
		$map   = array(
			'site_topic'    => __( 'Topic', 'wpml-translation-check' ),
			'site_purpose'  => __( 'Purpose', 'wpml-translation-check' ),
			'site_audience' => __( 'Audience', 'wpml-translation-check' ),
		);

		foreach ( $map as $key => $label ) {
			if ( ! empty( $context[ $key ] ) ) {
				$parts[] = $label . ': ' . sanitize_text_field( $context[ $key ] );
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Human-readable language name from a WPML code.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	private static function language_name( $code ) {
		$name = apply_filters( 'wpml_translated_language_name', null, $code, 'en' );

		return $name ? $name : $code;
	}
}
