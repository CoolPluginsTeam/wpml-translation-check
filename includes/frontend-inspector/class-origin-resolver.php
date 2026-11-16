<?php
/**
 * Heavy per-click origin resolution for the front-end text inspector.
 *
 * Uses WPML public filters where possible; read-only icl_strings lookups when
 * needed for Admin Texts / String Translation status. Never guesses.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Origin_Resolver
 */
class Origin_Resolver {

	/**
	 * Batch caches filled by prime().
	 *
	 * @var array<string, mixed>
	 */
	private static $cache = array(
		'batch'      => false,
		'values'     => array(),
		'admin'      => array(),
		'status'     => array(),
		'values_all' => array(),
		'files'      => array(),
		'el_texts'   => array(),
	);

	/**
	 * Lowercase cache key (MySQL comparisons are case-insensitive).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function ckey( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
	}

	/**
	 * Preload String Translation rows for a batch: one IN() query per table.
	 *
	 * @param array<int, array{0:string,1:array<string, mixed>}> $entries Text + hint pairs.
	 * @return void
	 */
	public static function prime( array $entries ) {
		global $wpdb;
		self::$cache['batch'] = true;
		$current              = (string) apply_filters( 'wpml_current_language', null );
		$values               = array();
		$contexts             = array();
		$theme_ctx            = 'admin_texts_theme_mods_' . get_stylesheet();

		foreach ( $entries as $entry ) {
			$text = (string) $entry[0];
			$hint = (array) $entry[1];
			if ( '' !== $text ) {
				$values[ self::ckey( $text ) ] = $text;
			}
			if ( ! empty( $hint['o'] ) ) {
				$values[ self::ckey( (string) $hint['o'] ) ] = (string) $hint['o'];
			}
			$kind = isset( $hint['k'] ) ? (string) $hint['k'] : '';
			if ( ( 'option' === $kind || 'theme_mod' === $kind ) && ! empty( $hint['n'] ) ) {
				$contexts[ 'admin_texts_' . $hint['n'] ] = true;
				if ( 'theme_mod' === $kind ) {
					$contexts[ $theme_ctx ] = true;
				}
			}
		}

		$table = $wpdb->prefix . 'icl_strings';
		$ids   = array();
		foreach ( array_chunk( array_values( $values ), 200 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only; table from prefix, $in is a %s placeholder list.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, context, name, value, gettext_context FROM {$table} WHERE value IN ({$in})", $chunk ), ARRAY_A );
			foreach ( (array) $rows as $row ) {
				self::$cache['values'][ self::ckey( $row['value'] ) ][] = $row;
				$ids[] = (int) $row['id'];
			}
		}
		foreach ( array_keys( $values ) as $key ) {
			if ( ! isset( self::$cache['values'][ $key ] ) ) {
				self::$cache['values'][ $key ] = array();
			}
		}

		if ( $contexts ) {
			$names = array_keys( $contexts );
			$in    = implode( ',', array_fill( 0, count( $names ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only; table from prefix, $in is a %s placeholder list.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, context, name, value FROM {$table} WHERE context IN ({$in})", $names ), ARRAY_A );
			foreach ( $names as $name ) {
				self::$cache['admin'][ $name ] = array();
			}
			foreach ( (array) $rows as $row ) {
				self::$cache['admin'][ $row['context'] ][] = $row;
				$ids[]                                     = (int) $row['id'];
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( $ids && '' !== $current ) {
			$trans = $wpdb->prefix . 'icl_string_translations';
			$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only; table from prefix, $in is a %d placeholder list.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT string_id, status, value, translation_date FROM {$trans} WHERE language = %s AND string_id IN ({$in})", array_merge( array( $current ), $ids ) ), ARRAY_A );
			foreach ( $ids as $id ) {
				self::$cache['status'][ $current ][ $id ] = null;
			}
			foreach ( (array) $rows as $row ) {
				self::$cache['status'][ $current ][ (int) $row['string_id'] ] = $row;
			}
		}
	}

	/**
	 * Primed icl_strings rows for a value, or null when the value was not primed.
	 *
	 * @param string $value Value.
	 * @return array<int, array<string, string>>|null
	 */
	private static function primed_rows( $value ) {
		$key = self::ckey( $value );
		return isset( self::$cache['values'][ $key ] ) ? self::$cache['values'][ $key ] : null;
	}

	/**
	 * Resolve one clicked text using the light hint from the collector map.
	 *
	 * @param string               $text Visible text (normalized by caller).
	 * @param array<string, mixed> $hint Light map entry (k, d, o, n, p, id, ...).
	 * @param array<string, mixed> $ctx  Page context: qid (queried id), js (bool), scripts, inline, region.
	 * @return array<string, mixed>
	 */
	public static function resolve( $text, array $hint, array $ctx = array() ) {
		self::$cache['page'] = array(
			'qid' => isset( $ctx['qid'] ) ? absint( $ctx['qid'] ) : 0,
			'td'  => isset( $ctx['region']['td'] ) ? (string) $ctx['region']['td'] : '',
		);

		$res = self::resolve_raw( $text, $hint, $ctx );
		if ( is_array( $res ) && ( ( isset( $res['translated'] ) && false === $res['translated'] ) || ( isset( $res['found'] ) && false === $res['found'] ) ) ) {
			$res = self::check_shown_in_page_language( (string) $text, $res, $ctx );
		}
		// Show the language name (English, Hindi) instead of the code.
		if ( is_array( $res ) && isset( $res['identifiers']['language'] ) && is_string( $res['identifiers']['language'] ) ) {
			$res['identifiers']['language'] = self::language_name( $res['identifiers']['language'] );
		}
		return $res;
	}

	/**
	 * Text marked untranslated (or not found) may already be in the page language: a language name in the
	 * language switcher, a WordPress / theme / plugin language-file translation (dates, comment
	 * form, core labels) or text written in the page language's own script. Such text is not
	 * counted as untranslated.
	 *
	 * @param string               $text Visible text.
	 * @param array<string, mixed> $res  Resolver result with translated = false or found = false.
	 * @param array<string, mixed> $ctx  Request context (region).
	 * @return array<string, mixed>
	 */
	private static function check_shown_in_page_language( $text, array $res, array $ctx ) {
		$current = (string) apply_filters( 'wpml_current_language', null );
		$default = (string) apply_filters( 'wpml_default_language', null );
		$text_n  = Origin_Collector::normalize( $text );
		if ( '' === $current || '' === $default || '' === $text_n ) {
			return $res;
		}
		$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$langs = is_array( $langs ) ? $langs : array();
		if ( $current === $default ) {
			return self::check_original_in_language_files( $text_n, $res, $ctx, $langs, $current );
		}
		$lang = self::language_name( $current );

		// 1. A language name written in its own language (language switcher).
		foreach ( $langs as $info ) {
			if ( is_array( $info ) && isset( $info['native_name'] ) && Origin_Collector::normalize( (string) $info['native_name'] ) === $text_n ) {
				return self::result(
					array(
						'source_type'       => 'language_name',
						'source_type_label' => __( 'Language name', 'wpml-translation-check' ),
						'translated'        => null,
						'status_label'      => __( 'Language names are shown in their own language', 'wpml-translation-check' ),
						'identifiers'       => array( 'language' => $current ),
						'text'              => $text,
					)
				);
			}
		}

		// 2. Not found: a String Translation translation with exactly this text (e.g. a theme button label).
		if ( isset( $res['found'] ) && false === $res['found'] ) {
			$row = self::lookup_by_translation( $text_n, $current );
			if ( $row ) {
				return self::resolve_gettext(
					$text,
					array(
						'k' => 'gettext',
						'd' => (string) $row['context'],
						'o' => (string) $row['value'],
						'c' => (string) $row['gettext_context'],
					),
					$current,
					$default
				);
			}
		}

		// 3. A translation from a language file of the page locale.
		$locale = isset( $langs[ $current ]['default_locale'] ) ? (string) $langs[ $current ]['default_locale'] : '';
		$domain = '' !== $locale ? self::language_file_domain( $text_n, $locale, $ctx ) : '';
		if ( '' !== $domain ) {
			return self::language_file_result( $text, $domain, $current, true );
		}

		// 4. Written in the page language's script, which the default language does not use.
		$page_script = isset( $langs[ $current ]['native_name'] ) ? self::script_of( (string) $langs[ $current ]['native_name'] ) : '';
		$def_script  = isset( $langs[ $default ]['native_name'] ) ? self::script_of( (string) $langs[ $default ]['native_name'] ) : '';
		if ( isset( $res['translated'] ) && false === $res['translated'] && '' !== $page_script && $page_script !== $def_script && self::script_of( $text_n ) === $page_script ) {
			$res['translated']   = true;
			$res['status_label'] = sprintf( /* translators: %s: language name, e.g. Hindi */ __( 'Shown in %s', 'wpml-translation-check' ), $lang );
			unset( $res['message'], $res['fix_url'], $res['fix_label'] );
		}
		return $res;
	}

	/**
	 * Result for text found in a WordPress, theme or plugin language file.
	 *
	 * @param string $text       Visible text.
	 * @param string $domain     Text domain ('default' = WordPress core).
	 * @param string $lang       Page language code.
	 * @param bool   $translated True when the page shows the translation, false on the original language.
	 * @return array<string, mixed>
	 */
	private static function language_file_result( $text, $domain, $lang, $translated ) {
		$owner = 'default' === $domain ? array() : Component_Registry::for_domain( $domain );
		$label = 'default' === $domain
			? __( 'WordPress text', 'wpml-translation-check' )
			/* translators: %s: plugin or theme name */
			: sprintf( __( '%s text', 'wpml-translation-check' ), $owner ? Component_Registry::name( $owner ) : $domain );
		return self::result(
			array(
				'source_type'       => 'language_file',
				'source_type_label' => $label,
				'translated'        => $translated ? true : null,
				'status_label'      => $translated ? __( 'Translated by a language file', 'wpml-translation-check' ) : __( 'Original language', 'wpml-translation-check' ),
				'identifiers'       => array_filter(
					array(
						'component' => $owner ? Component_Registry::label( $owner ) : '',
						'language'  => $lang,
					)
				),
				'fix_url'           => Component_Registry::localization_url(),
				'fix_label'         => __( 'Theme and plugins localization', 'wpml-translation-check' ),
				'text'              => $text,
			)
		);
	}

	/**
	 * Default-language page: text not found elsewhere may be a WordPress, theme or plugin string
	 * (e.g. a button label). Its original is looked up in the language files of up to three
	 * secondary languages. Only used when nothing else matched.
	 *
	 * @param string                              $text_n Normalized text.
	 * @param array<string, mixed>                $res    Resolver result.
	 * @param array<string, mixed>                $ctx    Request context.
	 * @param array<string, array<string, mixed>> $langs  Active WPML languages.
	 * @param string                              $current Current language.
	 * @return array<string, mixed>
	 */
	private static function check_original_in_language_files( $text_n, array $res, array $ctx, array $langs, $current ) {
		if ( ! isset( $res['found'] ) || false !== $res['found'] ) {
			return $res;
		}
		$checked = 0;
		foreach ( $langs as $code => $info ) {
			if ( $code === $current || empty( $info['default_locale'] ) || $checked >= 3 ) {
				continue;
			}
			++$checked;
			$domain = self::language_file_domain( $text_n, (string) $info['default_locale'], $ctx, 0 );
			if ( '' !== $domain ) {
				return self::language_file_result( $text_n, $domain, $current, false );
			}
		}
		return $res;
	}

	/**
	 * Main writing system of a text ('' when unclear or not mostly one script).
	 *
	 * @param string $text Text.
	 * @return string Script name.
	 */
	private static function script_of( $text ) {
		$scripts = array( 'Latin', 'Devanagari', 'Arabic', 'Hebrew', 'Cyrillic', 'Greek', 'Thai', 'Hangul', 'Bengali', 'Tamil', 'Telugu', 'Gujarati', 'Gurmukhi', 'Kannada', 'Malayalam', 'Georgian', 'Armenian' );
		$total   = (int) preg_match_all( '/\p{L}/u', (string) $text );
		if ( $total < 2 ) {
			return '';
		}
		$cjk = (int) preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}]/u', (string) $text );
		if ( $cjk * 10 >= $total * 6 ) {
			return 'CJK';
		}
		foreach ( $scripts as $script ) {
			$n = (int) preg_match_all( '/\p{' . $script . '}/u', (string) $text );
			if ( $n * 10 >= $total * 6 ) {
				return $script;
			}
		}
		return '';
	}

	/**
	 * Text domain whose language file (for the given locale) has this text as a translation that
	 * differs from the original. Checks WordPress core, the active theme and the clicked area's
	 * plugin or theme. A text split by links matches one part of the translation; placeholders
	 * match any value. Bounded: a few domains, a cheap word check before any regex, cached per request.
	 *
	 * @param string               $text_n Normalized text.
	 * @param string               $locale Locale of the page language (hi_IN).
	 * @param array<string, mixed> $ctx    Request context.
	 * @param int                  $col    1 = match the translation, 0 = match the original text.
	 * @return string Text domain or ''.
	 */
	private static function language_file_domain( $text_n, $locale, array $ctx, $col = 1 ) {
		$domains = array( 'default' );
		foreach ( array( wp_get_theme( get_template() ), wp_get_theme() ) as $theme ) {
			$td = $theme->exists() ? (string) $theme->get( 'TextDomain' ) : '';
			if ( '' !== $td ) {
				$domains[] = $td;
			}
		}
		if ( isset( $ctx['region']['td'] ) && is_string( $ctx['region']['td'] ) && '' !== $ctx['region']['td'] ) {
			$domains[] = sanitize_key( $ctx['region']['td'] );
		}
		$domains = array_slice( array_unique( $domains ), 0, 5 );

		// Cheap filter: one of the two longest words must appear in the translation (the other
		// may be a placeholder value such as a user name).
		$words = array();
		foreach ( (array) preg_split( '/[\s\p{P}]+/u', $text_n ) as $w ) {
			if ( ! preg_match( '/^\d+$/', (string) $w ) && strlen( (string) $w ) >= 3 ) {
				$words[ (string) $w ] = strlen( (string) $w );
			}
		}
		if ( ! $words ) {
			return '';
		}
		arsort( $words );
		$words   = array_slice( array_keys( $words ), 0, 2 );
		$letters = (int) preg_match_all( '/\p{L}/u', $text_n );
		$found   = '';
		foreach ( $domains as $domain ) {
			foreach ( self::language_file_entries( $domain, $locale ) as $entry ) {
				$value = (string) $entry[ $col ];
				if ( false === strpos( $value, $words[0] ) && ( ! isset( $words[1] ) || false === strpos( $value, $words[1] ) ) ) {
					continue;
				}
				$parts = (array) preg_split( '/<[^>]*>/', $value );
				if ( count( $parts ) > 1 ) {
					$parts[] = implode( ' ', $parts );
				}
				foreach ( $parts as $part ) {
					$part    = (string) preg_replace( '/^[\s.,:;!?।|]+|[\s.,:;!?।|]+$/u', '', (string) $part );
					$literal = (int) preg_match_all( '/\p{L}/u', (string) preg_replace( '/%(?:\d+\$)?[sdFfcuxX]/', '', $part ) );
					if ( $literal < 3 || $literal * 10 < $letters * 3 ) {
						continue;
					}
					$re = self::visible_regex( $part );
					if ( '' !== $re && preg_match( '/^' . $re . '[\s.,:;!?।|]*$/u', $text_n ) ) {
						$found = $domain;
						break 3;
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Original/translation pairs of one text domain in one locale (translated entries only).
	 *
	 * Reads the locale's .mo files directly (WordPress languages folder, then the theme's own
	 * languages folder), so the result does not depend on which locale is loaded in this request.
	 *
	 * @param string $domain Text domain ('default' = WordPress core).
	 * @param string $locale Locale (hi_IN).
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function language_file_entries( $domain, $locale ) {
		$key = 'lf:' . $locale . ':' . $domain;
		if ( isset( self::$cache['visible'][ $key ] ) ) {
			return self::$cache['visible'][ $key ];
		}
		$locale = preg_replace( '/[^A-Za-z0-9_@-]/', '', (string) $locale );
		$domain = sanitize_key( (string) $domain );
		$files  = 'default' === $domain
			? array( WP_LANG_DIR . '/' . $locale . '.mo' )
			: array(
				WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo',
				WP_LANG_DIR . '/themes/' . $domain . '-' . $locale . '.mo',
			);
		// The theme's own languages folder only for the theme's text domain.
		foreach ( array( wp_get_theme(), wp_get_theme( get_template() ) ) as $theme ) {
			if ( 'default' !== $domain && $theme->exists() && (string) $theme->get( 'TextDomain' ) === $domain ) {
				$files[] = $theme->get_stylesheet_directory() . '/languages/' . $locale . '.mo';
			}
		}
		$out = array();
		foreach ( $files as $file ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}
			if ( ! class_exists( 'MO' ) ) {
				require_once ABSPATH . WPINC . '/pomo/mo.php';
			}
			$mo = new \MO();
			if ( ! $mo->import_from_file( $file ) ) {
				continue;
			}
			foreach ( $mo->entries as $entry ) {
				foreach ( (array) $entry->translations as $value ) {
					$value = (string) $value;
					if ( '' !== $value && $value !== (string) $entry->singular ) {
						$out[] = array( (string) $entry->singular, $value );
					}
				}
			}
			break;
		}
		self::$cache['visible'][ $key ] = $out;
		return $out;
	}

	/**
	 * Language name from WPML, shown in the admin's language (English, Hindi).
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function language_name( $code ) {
		static $names = array();

		$code = (string) $code;
		if ( '' === $code ) {
			return '';
		}
		if ( ! isset( $names[ $code ] ) ) {
			// Names in the admin's WPML admin language, else the site's default language.
			$display = (string) get_user_meta( get_current_user_id(), 'icl_admin_language', true );
			if ( '' === $display ) {
				$display = (string) apply_filters( 'wpml_default_language', null );
			}
			$name = apply_filters( 'wpml_translated_language_name', null, $code, '' !== $display ? $display : $code );
			if ( ! is_string( $name ) || '' === $name ) {
				$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
				if ( is_array( $active ) && isset( $active[ $code ] ) ) {
					$name = ! empty( $active[ $code ]['translated_name'] ) ? $active[ $code ]['translated_name'] : ( isset( $active[ $code ]['native_name'] ) ? $active[ $code ]['native_name'] : '' );
				}
			}
			$names[ $code ] = is_string( $name ) && '' !== $name ? $name : $code;
		}
		return $names[ $code ];
	}

	/**
	 * Resolve by hint kind (raw identifiers).
	 *
	 * @param string               $text Visible text.
	 * @param array<string, mixed> $hint Hint.
	 * @param array<string, mixed> $ctx  Page context.
	 * @return array<string, mixed>
	 */
	private static function resolve_raw( $text, array $hint, array $ctx = array() ) {
		$text = Origin_Collector::normalize( $text );
		$kind = isset( $hint['k'] ) ? sanitize_key( (string) $hint['k'] ) : '';

		$current      = (string) apply_filters( 'wpml_current_language', null );
		$default_lang = (string) apply_filters( 'wpml_default_language', null );

		switch ( $kind ) {
			case 'gettext':
				return self::resolve_gettext( $text, $hint, $current, $default_lang );
			case 'option':
				return self::resolve_option( $text, $hint, $current, $default_lang, false );
			case 'theme_mod':
				return self::resolve_option( $text, $hint, $current, $default_lang, true );
			case 'post_title':
			case 'post_content':
				return self::resolve_post( $text, $hint, $current, $default_lang, $kind );
			case 'elementor':
				return self::resolve_elementor( $text, $hint, $current, $default_lang );
			case 'nav_menu_item':
				return self::resolve_menu_item( $text, $hint, $current, $default_lang );
			case 'widget':
				return self::resolve_widget( $text, $hint, $current, $default_lang );
			case 'term':
				return self::resolve_term( $text, $hint, $current, $default_lang );
			case 'builder':
				$builder = isset( $hint['b'] ) ? sanitize_key( (string) $hint['b'] ) : '';
				$posts   = array( isset( $hint['id'] ) ? (int) $hint['id'] : 0, isset( $ctx['qid'] ) ? (int) $ctx['qid'] : 0 );
				$res     = Integration_Resolvers::resolve_builder( $text, $builder, $posts, $current, $default_lang );
				if ( $res ) {
					return $res;
				}
				break;
			case 'acf':
				$res = Integration_Resolvers::resolve_acf( $text, isset( $hint['fk'] ) ? (string) $hint['fk'] : '', isset( $hint['oid'] ) ? (string) $hint['oid'] : '', $current, $default_lang );
				if ( $res ) {
					return $res;
				}
				break;
			case 'form':
				$res = Integration_Resolvers::resolve_form( $text, isset( $hint['f'] ) ? sanitize_key( (string) $hint['f'] ) : '', isset( $hint['id'] ) ? (int) $hint['id'] : 0, $current, $default_lang );
				if ( $res ) {
					return $res;
				}
				break;
		}

		// No (usable) collector hint: exact-value matches only (never fuzzy guess).
		$by_value = self::resolve_by_exact_value( $text, $current, $default_lang, $ctx );
		if ( $by_value ) {
			return $by_value;
		}

		if ( ! empty( $ctx['js'] ) ) {
			return self::resolve_js( $text, $ctx, $current, $default_lang );
		}
		if ( ! empty( $ctx['region'] ) && is_array( $ctx['region'] ) ) {
			return self::resolve_hardcoded( $text, $ctx['region'], $current, $default_lang );
		}

		return self::not_found(
			$text,
			__( 'Try clicking a smaller part of the text. If it is still not found, edit it where it was created.', 'wpml-translation-check' )
		);
	}

	/**
	 * Fill a result row with defaults (shared shape for every resolver).
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function result( array $fields ) {
		return array_merge(
			array(
				'found'             => true,
				'source_type'       => 'unknown',
				'source_type_label' => '',
				'identifiers'       => array(),
				'translated'        => null,
				'status_label'      => '',
				'fix_url'           => '',
				'fix_label'         => '',
				'message'           => '',
				'text'              => '',
			),
			$fields
		);
	}

	/**
	 * Public ST row lookup by value, preferring a context.
	 *
	 * @param string $value   Value.
	 * @param string $context Preferred context.
	 * @return array<string, string>|null
	 */
	public static function lookup_st_row( $value, $context = '' ) {
		return self::lookup_icl_string_by_value( (string) $value, (string) $context );
	}

	/**
	 * Unknown source response.
	 *
	 * @param string      $text_or_msg Clicked text, or message when second arg is null.
	 * @param string|null $hint_text   User-facing message when text is the first argument.
	 * @return array<string, mixed>
	 */
	private static function not_found( $text_or_msg, $hint_text = null ) {
		// Back-compat: not_found( $message ) or not_found( $text, $message ).
		if ( null === $hint_text ) {
			$hint_text   = $text_or_msg;
			$text_or_msg = '';
		}
		return array(
			'found'             => false,
			'source_type'       => 'unknown',
			'source_type_label' => __( 'Source not found', 'wpml-translation-check' ),
			'identifiers'       => array(),
			'translated'        => null,
			'status_label'      => __( 'Could not be identified', 'wpml-translation-check' ),
			'fix_url'           => '',
			'fix_label'         => '',
			'message'           => $hint_text,
			'text'              => $text_or_msg,
		);
	}

	/**
	 * Exact-value fallback when the light map hint was missing or not resolvable.
	 *
	 * Exact string equality only (never fuzzy): options / theme_mods (recursive, with
	 * key path), String Translation rows, custom fields, builder data and forms.
	 *
	 * @param string               $text         Normalized visible text.
	 * @param string               $current      Current language.
	 * @param string               $default_lang Default language.
	 * @param array<string, mixed> $ctx          Page context.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_by_exact_value( $text, $current, $default_lang, array $ctx = array() ) {
		if ( '' === $text ) {
			return null;
		}
		$batch    = (bool) self::$cache['batch'];
		$qid      = isset( $ctx['qid'] ) ? (int) $ctx['qid'] : 0;
		$post_ids = $qid > 0 ? array( $qid ) : array();

		// 1) Options + theme_mods, including nested arrays / serialized values.
		$hits = Option_Index::find( $text, true );
		if ( $hits ) {
			$primary = $hits[0];
			$res     = self::resolve_option(
				$text,
				array(
					'k' => $primary['kind'],
					'n' => $primary['name'],
					'p' => $primary['path'],
				),
				$current,
				$default_lang,
				'theme_mod' === $primary['kind']
			);
			// Same text in several settings: only the one this page uses (the first hit) is shown.
			return $res;
		}

		// JS-rendered text: String Translation lookups are done by resolve_js() with script context.
		if ( ! empty( $ctx['js'] ) ) {
			return null;
		}

		// 2) String Translation rows (gettext, admin texts, widgets, packages).
		$st = self::lookup_icl_string_by_value( $text, '' );
		if ( $st ) {
			return self::resolve_st_row( $text, $st, $current, $default_lang );
		}

		// 3) Custom fields / ACF on the current document (any post on a single click).
		if ( $post_ids || ! $batch ) {
			$meta = Integration_Resolvers::resolve_meta_by_value( $text, $post_ids, $current, $default_lang );
			if ( $meta ) {
				return $meta;
			}
		}

		// 4) Divi / WPBakery / Bricks data of the current document.
		if ( $post_ids ) {
			$builder = Integration_Resolvers::resolve_builder( $text, '', $post_ids, $current, $default_lang );
			if ( $builder ) {
				return $builder;
			}
		}

		// 5) Forms (Contact Form 7, WPForms, Gravity Forms) — bounded, single click only.
		if ( ! $batch ) {
			$form = Integration_Resolvers::resolve_form( $text, '', 0, $current, $default_lang );
			if ( $form ) {
				return $form;
			}
		}

		return null;
	}

	/**
	 * Match a WPML builder package context that belongs to the current document.
	 *
	 * Only `gutenberg-{id}` and `elementor-{id}` are recognized; `{id}` must be a
	 * positive post ID equal to the current queried post, its original-language
	 * source, or its current-language copy. The returned post_id is mapped to the
	 * current-language copy so sibling body paragraphs of the same document resolve
	 * consistently to the post being viewed. Portable: uses the queried post ID and
	 * the public `wpml_object_id` filter, with no hardcoded IDs, hosts, or a generic
	 * "-{number}" rule.
	 *
	 * @param string $context String Translation context.
	 * @return array{builder:string, post_id:int}|null
	 */
	private static function match_builder_package( $context ) {
		if ( ! preg_match( '/^(gutenberg|elementor)-(\d+)$/', (string) $context, $m ) ) {
			return null;
		}
		$builder = $m[1];
		$ctx_id  = (int) $m[2];
		if ( $ctx_id <= 0 ) {
			return null;
		}

		$page = isset( self::$cache['page'] ) ? (array) self::$cache['page'] : array();
		$qid  = isset( $page['qid'] ) ? (int) $page['qid'] : 0;
		if ( $qid <= 0 ) {
			$qid = (int) get_queried_object_id();
		}
		if ( $qid <= 0 ) {
			return null;
		}

		$ids       = array( $qid => true );
		$post_type = get_post_type( $qid );
		$post_type = $post_type ? $post_type : 'any';
		$default   = (string) apply_filters( 'wpml_default_language', null );
		$current   = (string) apply_filters( 'wpml_current_language', null );
		// Original-language source of the current document.
		$source = (int) apply_filters( 'wpml_object_id', $qid, $post_type, true, $default );
		if ( $source > 0 ) {
			$ids[ $source ] = true;
		}
		// Current-language copy (a click inside the post body may carry either ID).
		$translation = 0;
		if ( '' !== $current ) {
			$translation = (int) apply_filters( 'wpml_object_id', $qid, $post_type, true, $current );
			if ( $translation > 0 ) {
				$ids[ $translation ] = true;
			}
		}

		if ( ! isset( $ids[ $ctx_id ] ) ) {
			return null;
		}

		// Resolve to the current-language copy actually being viewed so every body
		// paragraph of the same document points to one post (e.g. the Hindi post on a
		// Hindi page), instead of the English source for packages keyed by source ID.
		$target = $ctx_id;
		if ( '' !== $current ) {
			$current_copy = (int) apply_filters( 'wpml_object_id', $ctx_id, $post_type, true, $current );
			if ( $current_copy > 0 ) {
				$target = $current_copy;
			} elseif ( $translation > 0 ) {
				$target = $translation;
			}
		}

		return array(
			'builder' => $builder,
			'post_id' => $target,
		);
	}

	/**
	 * Route a matched builder package to the post-content or Elementor result.
	 *
	 * Gutenberg packages resolve as Post content with the Edit post / translation
	 * fix; Elementor packages resolve as the Elementor origin with Edit in
	 * Elementor. String Translation and Theme and plugins localization are never
	 * offered for these contexts.
	 *
	 * @param string                            $text         Visible text.
	 * @param array{builder:string,post_id:int} $package      Matched package.
	 * @param string                            $current      Current language.
	 * @param string                            $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_builder_package( $text, array $package, $current, $default_lang ) {
		if ( 'elementor' === $package['builder'] ) {
			return self::resolve_elementor( $text, array( 'did' => $package['post_id'] ), $current, $default_lang );
		}
		return self::resolve_post( $text, array( 'id' => $package['post_id'] ), $current, $default_lang, 'post_content', true );
	}

	/**
	 * Route an icl_strings row to the matching resolver by its context.
	 *
	 * @param string                $text         Visible text.
	 * @param array<string, string> $row          icl_strings row.
	 * @param string                $current      Current language.
	 * @param string                $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_st_row( $text, array $row, $current, $default_lang ) {
		$context = (string) $row['context'];
		$package = self::match_builder_package( $context );
		if ( $package ) {
			return self::resolve_builder_package( $text, $package, $current, $default_lang );
		}
		if ( 0 === strpos( $context, 'admin_texts_' ) ) {
			$option = substr( $context, 12 );
			$name   = (string) $row['name'];
			$path   = '';
			$prefix = '[' . $option . ']';
			if ( 0 === strpos( $name, $prefix ) ) {
				$rest = substr( $name, strlen( $prefix ) );
				if ( '' !== $rest ) {
					$keys = array();
					if ( preg_match_all( '/\[([^\]]*)\]/', $rest, $mm ) ) {
						$keys = $mm[1];
					}
					$tail = (string) preg_replace( '/\[[^\]]*\]/', '', $rest );
					if ( '' !== $tail ) {
						$keys[] = $tail;
					}
					$path = $keys ? '[' . implode( '][', $keys ) . ']' : '';
				}
			}
			$is_mod = 0 === strpos( $option, 'theme_mods_' );
			if ( $is_mod && preg_match( '/^\[([^\]]+)\](.*)$/', $path, $m ) ) {
				return self::resolve_option(
					$text,
					array(
						'n' => $m[1],
						'p' => $m[2],
					),
					$current,
					$default_lang,
					true
				);
			}
			return self::resolve_option(
				$text,
				array(
					'n' => $option,
					'p' => $path,
				),
				$current,
				$default_lang,
				false
			);
		}
		if ( 'Widgets' === $context ) {
			return self::resolve_widget( $text, array(), $current, $default_lang );
		}
		return self::resolve_gettext(
			$text,
			array(
				'k' => 'gettext',
				'd' => $context,
				'o' => (string) $row['value'],
				'c' => isset( $row['gettext_context'] ) ? (string) $row['gettext_context'] : '',
			),
			$current,
			$default_lang
		);
	}

	/**
	 * Resolve a gettext string via public ST APIs / read-only icl_strings.
	 *
	 * Adds the owning component, a register action when the string is not in ST,
	 * the one ST row this page uses (no list of other rows), and mismatch reasons
	 * when ST has a translation that the page does not show.
	 *
	 * @param string               $text    Visible text.
	 * @param array<string, mixed> $hint    Hint.
	 * @param string               $current Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_gettext( $text, array $hint, $current, $default_lang ) {
		$domain   = isset( $hint['d'] ) ? (string) $hint['d'] : '';
		$original = isset( $hint['o'] ) && '' !== (string) $hint['o'] ? (string) $hint['o'] : $text;
		$context  = isset( $hint['c'] ) ? (string) $hint['c'] : '';

		$row = self::lookup_icl_string( $domain, $original, $context );
		if ( ! $row ) {
			// Domain in WPML ST is often stored as context column.
			$row = self::lookup_icl_string_by_value( $original, $domain );
		}
		if ( ! $row ) {
			// Hint original may be HTML/placeholders; try the normalized visible text.
			$row = self::lookup_icl_string( $domain, $text, $context );
			if ( ! $row ) {
				$row = self::lookup_icl_string_by_value( $text, $domain );
			}
		}
		if ( ! $row && $current !== $default_lang && '' !== $current ) {
			// Visible text may be the translation: find the source string through its translation.
			$row = self::lookup_by_translation( $text, $current );
			if ( $row ) {
				$original = (string) $row['value'];
			}
		}

		$secondary    = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$translated   = null;
		$status_label = __( 'Not added to String Translation', 'wpml-translation-check' );
		$st           = null;
		if ( $row ) {
			$st           = self::string_translation_status( (int) $row['id'], $current, $default_lang );
			$translated   = $st['translated'];
			$status_label = $st['label'];
		}
		$orig_norm  = Origin_Collector::normalize( $original );
		$shows_orig = ( $text === $orig_norm || $text === $original );
		if ( $secondary && ! $shows_orig && ! ( $st && '' !== $st['value'] ) ) {
			// Visible text differs from the source string: a translation is being applied (.mo or ST).
			$translated   = true;
			$status_label = __( 'Translated by a language file', 'wpml-translation-check' );
		} elseif ( ! $row && $secondary ) {
			$translated   = false;
			$status_label = __( 'Not translated (same as the original)', 'wpml-translation-check' );
		}

		$string_id = $row ? (int) $row['id'] : 0;
		$st_ctx    = $row ? (string) $row['context'] : $domain;
		$owner     = Component_Registry::for_domain( '' !== $domain ? $domain : $st_ctx );
		if ( ! $owner && ! empty( $hint['cd'] ) ) {
			$owner = Component_Registry::for_domain( (string) $hint['cd'] );
		}

		$res = self::result(
			array(
				'source_type'       => 'gettext',
				'source_type_label' => $owner
					/* translators: %s: plugin or theme name, e.g. WooCommerce */
					? sprintf( __( '%s text', 'wpml-translation-check' ), Component_Registry::name( $owner ) )
					: __( 'Theme or plugin text', 'wpml-translation-check' ),
				'identifiers'       => array_filter(
					array(
						'domain'     => $domain,
						'component'  => Component_Registry::label( $owner ),
						'original'   => $original,
						'context'    => $context,
						'string_id'  => $string_id ? $string_id : null,
						'st_context' => $row ? (string) $row['context'] : null,
						'caller'     => ! empty( $hint['cd'] ) && (string) $hint['cd'] !== $domain ? (string) $hint['cd'] : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status_label,
				'fix_url'           => $row ? self::st_search_url( $original, $st_ctx, $string_id ) : Component_Registry::localization_url(),
				'fix_label'         => $row ? __( 'Open in String Translation', 'wpml-translation-check' ) : __( 'Scan in Theme and plugins localization', 'wpml-translation-check' ),
				'text'              => $text,
			)
		);

		if ( ! $row ) {
			$res['message'] = __( 'This text is not in String Translation yet. Add it here, or scan the theme or plugin in Theme and plugins localization.', 'wpml-translation-check' );
			if ( Component_Registry::has_st() && '' !== $domain ) {
				$res['actions'] = array( self::register_action( $domain, $original, $context ) );
			}
		} else {
			$res['links'] = array(
				array(
					'label' => __( 'Theme and plugins localization', 'wpml-translation-check' ),
					'url'   => Component_Registry::localization_url(),
				),
			);
			if ( $secondary && $st && '' !== $st['value'] ) {
				$res = self::apply_mismatch( $res, $text, $original, $row, $st, $domain, $context, $current );
			}
		}

		return self::attach_value_candidates( $res, $original, $string_id, $domain, $current, $default_lang );
	}

	/**
	 * Register-in-ST action descriptor (executed by Frontend_Inspector::ajax_register()).
	 *
	 * @param string $domain   Text domain / ST context.
	 * @param string $original Original string.
	 * @param string $context  Gettext context.
	 * @return array<string, mixed>
	 */
	public static function register_action( $domain, $original, $context = '' ) {
		return array(
			'id'      => 'register',
			'label'   => __( 'Add to String Translation', 'wpml-translation-check' ),
			'payload' => array_filter(
				array(
					'domain'   => (string) $domain,
					'original' => (string) $original,
					'context'  => (string) $context,
				),
				'strlen'
			),
		);
	}

	/**
	 * Register one string in WPML ST exactly like WPML auto-registers gettext.
	 *
	 * @param string $domain   Domain (ST context).
	 * @param string $original Original text.
	 * @param string $context  Gettext context.
	 * @return array<string, mixed> string_id + fix_url, or error.
	 */
	public static function register_string( $domain, $original, $context = '' ) {
		if ( ! function_exists( 'icl_register_string' ) ) {
			return array( 'error' => __( 'WPML String Translation is not active.', 'wpml-translation-check' ) );
		}
		$existing = self::lookup_icl_string( $domain, $original, $context );
		if ( ! $existing ) {
			$ctx_arg = '' !== $context
				? array(
					'domain'  => $domain,
					'context' => $context,
				)
				: $domain;
			// Same name WPML uses for gettext strings (md5 of the original).
			icl_register_string( $ctx_arg, md5( $original ), $original );
			if ( function_exists( 'wpml_st_flush_caches' ) ) {
				wpml_st_flush_caches();
			}
			$existing = self::lookup_icl_string( $domain, $original, $context );
		}
		if ( ! $existing ) {
			return array( 'error' => __( 'WPML could not add this text.', 'wpml-translation-check' ) );
		}
		return array(
			'string_id' => (int) $existing['id'],
			'fix_url'   => self::st_search_url( $original, $domain, (int) $existing['id'] ),
			'fix_label' => __( 'Translate it in String Translation', 'wpml-translation-check' ),
		);
	}

	/**
	 * Find a source ST string through a translation value in a language.
	 *
	 * @param string $value Translation text shown on the page.
	 * @param string $lang  Language.
	 * @return array<string, string>|null
	 */
	private static function lookup_by_translation( $value, $lang ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only lookup of a WPML ST translation by its value.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.id, s.context, s.name, s.value, s.gettext_context FROM {$wpdb->prefix}icl_string_translations t INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = t.string_id WHERE t.value = %s AND t.language = %s LIMIT 1",
				$value,
				$lang
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Every ST row holding the same value (bounded); uses the batch cache when primed.
	 *
	 * @param string $value Value.
	 * @return array<int, array<string, string>>
	 */
	private static function rows_for_value( $value ) {
		global $wpdb;
		$primed = self::primed_rows( $value );
		if ( null !== $primed ) {
			return $primed;
		}
		$key = 'all:' . self::ckey( $value );
		if ( isset( self::$cache['values_all'][ $key ] ) ) {
			return self::$cache['values_all'][ $key ];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only; bounded.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, context, name, value, gettext_context FROM {$wpdb->prefix}icl_strings WHERE value = %s LIMIT 12", $value ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();
		// The column collation is case-insensitive: keep exact-case rows when there are any.
		$exact                             = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $value ) {
					return (string) $row['value'] === (string) $value;
				}
			)
		);
		self::$cache['values_all'][ $key ] = $exact ? $exact : $rows;
		return self::$cache['values_all'][ $key ];
	}

	/**
	 * Gap 9 (same text in several domains): keep the row this page uses, or pick the best one.
	 *
	 * @param array<string, mixed> $res          Result.
	 * @param string               $value        Original.
	 * @param int                  $chosen_id    String chosen for the result (0 = none).
	 * @param string               $page_domain  Domain the page used (from gettext), or ''.
	 * @param string               $current      Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function attach_value_candidates( array $res, $value, $chosen_id, $page_domain, $current, $default_lang ) {
		// The row this page uses is already chosen: show only that one.
		if ( (int) $chosen_id > 0 ) {
			return $res;
		}
		$best = self::pick_best_row( self::rows_for_value( (string) $value ), (string) $page_domain );
		if ( ! $best ) {
			return $res;
		}
		$st                               = self::string_translation_status( (int) $best['id'], $current, $default_lang );
		$res['translated']                = $st['translated'];
		$res['status_label']              = $st['label'];
		$res['fix_url']                   = self::st_search_url( (string) $best['value'], (string) $best['context'], (int) $best['id'] );
		$res['fix_label']                 = __( 'Open in String Translation', 'wpml-translation-check' );
		$res['identifiers']['string_id']  = (int) $best['id'];
		$res['identifiers']['st_context'] = (string) $best['context'];
		return $res;
	}

	/**
	 * The one ST row this page most likely uses, from rows with the same text: the page's text
	 * domain, then the current post's package (e.g. elementor-123, also the original-language
	 * post), then the clicked area's plugin or theme, then the first row. Duplicates collapse.
	 *
	 * @param array<int, array<string, mixed>> $rows        ST rows (id, context, value).
	 * @param string                           $page_domain Domain the page used, or ''.
	 * @return array<string, mixed>|null
	 */
	private static function pick_best_row( array $rows, $page_domain = '' ) {
		if ( ! $rows ) {
			return null;
		}
		$page = isset( self::$cache['page'] ) ? (array) self::$cache['page'] : array();
		$qid  = isset( $page['qid'] ) ? (int) $page['qid'] : 0;
		$ids  = array();
		if ( $qid > 0 ) {
			$ids[] = $qid;
			$orig  = (int) apply_filters( 'wpml_object_id', $qid, get_post_type( $qid ) ? get_post_type( $qid ) : 'any', true, apply_filters( 'wpml_default_language', null ) );
			if ( $orig > 0 && $orig !== $qid ) {
				$ids[] = $orig;
			}
		}
		$td         = isset( $page['td'] ) ? (string) $page['td'] : '';
		$best       = null;
		$best_score = -1;
		foreach ( $rows as $row ) {
			$context = isset( $row['context'] ) ? (string) $row['context'] : '';
			$score   = 0;
			if ( '' !== $page_domain && $context === $page_domain ) {
				$score = 4;
			} elseif ( $ids && preg_match( '/-(\d+)$/', $context, $m ) && in_array( (int) $m[1], $ids, true ) ) {
				$score = 3;
			} elseif ( '' !== $td && $context === $td ) {
				$score = 2;
			}
			if ( $score > $best_score ) {
				$best       = $row;
				$best_score = $score;
			}
		}
		return $best;
	}

	/**
	 * Gap 8: ST has a translation for this language but the page shows something else.
	 *
	 * @param array<string, mixed>  $res      Result.
	 * @param string                $text     Visible text.
	 * @param string                $original Original.
	 * @param array<string, string> $row      icl_strings row.
	 * @param array<string, mixed>  $st       Status (translated, label, status, value, date).
	 * @param string                $domain   Domain the page used ('' when unknown).
	 * @param string                $context  Gettext context the page used.
	 * @param string                $current  Current language.
	 * @return array<string, mixed>
	 */
	private static function apply_mismatch( array $res, $text, $original, array $row, array $st, $domain, $context, $current ) {
		$translation = Origin_Collector::normalize( (string) $st['value'] );
		if ( '' === $translation || $translation === $text ) {
			return $res;
		}
		$reasons   = array();
		$st_domain = (string) $row['context'];
		$locale    = JS_Strings::locale_for( $current );

		if ( 10 !== (int) $st['status'] ) {
			$reasons[] = __( 'The translation is not marked as complete, so WPML does not show it yet.', 'wpml-translation-check' );
		}
		if ( '' !== $domain && 0 !== strcasecmp( $domain, $st_domain ) ) {
			$reasons[] = __( 'The translation was added to a different entry than the one this page uses. Translate the row marked ✓ below.', 'wpml-translation-check' );
		}
		if ( '' !== $context && isset( $row['gettext_context'] ) && $context !== (string) $row['gettext_context'] ) {
			$reasons[] = __( 'The translation was added to a different entry than the one this page uses.', 'wpml-translation-check' );
		}

		$orig_norm = Origin_Collector::normalize( $original );
		if ( $text !== $orig_norm ) {
			// The page shows a different translation: a .mo/.l10n.php file wins over ST.
			$mo = self::file_translation( '' !== $domain ? $domain : $st_domain, $locale, $original );
			if ( '' !== $mo && Origin_Collector::normalize( $mo ) === $text ) {
				$reasons[] = __( 'A language file overrides this translation. Re-save the translation in String Translation.', 'wpml-translation-check' );
			} else {
				$reasons[] = __( 'Another plugin or language file changes this text after WPML.', 'wpml-translation-check' );
			}
		} else {
			$wpml_file = self::wpml_file_state( $st_domain, $locale, (string) $st['date'] );
			if ( '' !== $wpml_file ) {
				$reasons[] = $wpml_file;
			}
			if ( 0 === strpos( $st_domain, 'admin_texts_' ) ) {
				$reasons[] = __( 'The theme or plugin reads this setting before WPML can translate it.', 'wpml-translation-check' );
			}
			if ( self::page_cache_active() ) {
				$reasons[] = __( 'A cache may be showing an old copy. Clear the cache for this page.', 'wpml-translation-check' );
			}
			if ( ! $reasons ) {
				$reasons[] = __( 'WPML did not apply the translation on this page.', 'wpml-translation-check' );
			}
		}

		$res['translated']                    = false;
		$res['status_label']                  = __( 'Translated, but not shown on the page', 'wpml-translation-check' );
		$res['reasons']                       = $reasons;
		$res['identifiers']['st_translation'] = (string) $st['value'];
		$res['links'][]                       = array(
			'label' => __( 'WPML Troubleshooting (clear cache)', 'wpml-translation-check' ),
			'url'   => admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' ),
		);
		return $res;
	}

	/**
	 * Translation of $original from the language-pack .mo / .l10n.php file of a domain.
	 *
	 * @param string $domain   Domain.
	 * @param string $locale   Locale.
	 * @param string $original Original.
	 * @return string
	 */
	private static function file_translation( $domain, $locale, $original ) {
		$domain = (string) $domain;
		if ( '' === $domain || '' === $locale ) {
			return '';
		}
		$base = 'default' === $domain ? $locale : $domain . '-' . $locale;
		foreach ( array( '', '/plugins', '/themes' ) as $dir ) {
			$stem = WP_LANG_DIR . $dir . '/' . $base;
			if ( ! isset( self::$cache['files'][ $stem ] ) ) {
				$entries = null;
				if ( is_readable( $stem . '.l10n.php' ) ) {
					$data    = include $stem . '.l10n.php';
					$entries = ( is_array( $data ) && isset( $data['messages'] ) && is_array( $data['messages'] ) ) ? $data['messages'] : array();
				} elseif ( is_readable( $stem . '.mo' ) && filesize( $stem . '.mo' ) < 8388608 ) {
					$mo      = new \MO();
					$entries = array();
					if ( $mo->import_from_file( $stem . '.mo' ) ) {
						foreach ( $mo->entries as $key => $entry ) {
							$entries[ $key ] = isset( $entry->translations[0] ) ? (string) $entry->translations[0] : '';
						}
					}
				}
				self::$cache['files'][ $stem ] = $entries;
			}
			$entries = self::$cache['files'][ $stem ];
			if ( is_array( $entries ) && isset( $entries[ $original ] ) ) {
				$value = $entries[ $original ];
				return is_array( $value ) ? (string) reset( $value ) : (string) strtok( (string) $value, "\0" );
			}
		}
		return '';
	}

	/**
	 * WPML generated translation file state for a domain (missing / older than the translation).
	 *
	 * @param string $domain ST context.
	 * @param string $locale Locale.
	 * @param string $date   Translation date (Y-m-d H:i:s, site time) or ''.
	 * @return string Reason or ''.
	 */
	private static function wpml_file_state( $domain, $locale, $date ) {
		if ( '' === $domain || '' === $locale || ! Component_Registry::has_st() ) {
			return '';
		}
		$stem = WP_LANG_DIR . '/wpml/' . $domain . '-' . $locale;
		$file = is_readable( $stem . '.l10n.php' ) ? $stem . '.l10n.php' : ( is_readable( $stem . '.mo' ) ? $stem . '.mo' : '' );
		if ( '' === $file ) {
			return __( 'WPML has not updated its language file yet. Re-save the translation in String Translation.', 'wpml-translation-check' );
		}
		$when = '' !== $date ? strtotime( get_gmt_from_date( $date ) . ' UTC' ) : 0;
		if ( $when && (int) filemtime( $file ) + 2 < $when ) {
			return __( 'WPML\'s language file is out of date. Re-save the translation in String Translation.', 'wpml-translation-check' );
		}
		return '';
	}

	/**
	 * Whether a page/object cache is likely serving this site.
	 *
	 * @return bool
	 */
	private static function page_cache_active() {
		return ( defined( 'WP_CACHE' ) && WP_CACHE ) || wp_using_ext_object_cache();
	}

	/**
	 * Resolve option / theme_mod, including Admin Texts registration.
	 *
	 * @param string               $text       Visible text.
	 * @param array<string, mixed> $hint       Hint.
	 * @param string               $current    Current lang.
	 * @param string               $default_lang    Default lang.
	 * @param bool                 $is_theme_mod Whether theme_mod.
	 * @return array<string, mixed>
	 */
	private static function resolve_option( $text, array $hint, $current, $default_lang, $is_theme_mod ) {
		$name = isset( $hint['n'] ) ? (string) $hint['n'] : '';
		$path = isset( $hint['p'] ) ? (string) $hint['p'] : '';

		if ( '' === $name ) {
			return self::not_found(
				__( 'The setting for this text could not be found.', 'wpml-translation-check' )
			);
		}

		// theme_mods stored under theme_mods_{stylesheet} when captured as option.
		if ( ! $is_theme_mod && 0 === strpos( $name, 'theme_mods_' ) && '' !== $path ) {
			$is_theme_mod = true;
			$mod_key      = trim( $path, '[]' );
			if ( preg_match( '/^\[([^\]]+)\]/', $path, $m ) ) {
				$mod_key = $m[1];
			}
			$name = $mod_key;
			$path = '';
		}

		// Core widget instances are stored as widget_{id_base}[N]: report them as widgets.
		if ( ! $is_theme_mod && 0 === strpos( $name, 'widget_' ) && preg_match( '/^\[(\d+)\]/', $path, $wm ) ) {
			return self::resolve_widget( $text, array( 'wid' => substr( $name, 7 ) . '-' . $wm[1] ), $current, $default_lang );
		}

		$registered          = false;
		$st_row              = null;
		$option_for_registry = $is_theme_mod ? ( 'theme_mods_' . get_stylesheet() ) : $name;

		if ( $is_theme_mod ) {
			// theme_mod keys are rarely in Admin Texts; check both theme_mods option and bare key.
			$registered = self::is_admin_text_registered( 'theme_mods_' . get_stylesheet(), '[' . $name . ']' )
				|| self::is_admin_text_registered( $name, '' );
			$st_row     = self::lookup_admin_text_string( 'theme_mods_' . get_stylesheet(), '[' . $name . ']' );
			if ( ! $st_row ) {
				$st_row = self::lookup_admin_text_string( $name, '' );
			}
		} else {
			$registered = self::is_admin_text_registered( $name, $path );
			$st_row     = self::lookup_admin_text_string( $name, $path );
		}

		$translated   = null;
		$status_label = '';

		if ( $registered && $st_row ) {
			$st           = self::string_translation_status( (int) $st_row['id'], $current, $default_lang );
			$translated   = $st['translated'];
			$status_label = $st['label'];
		} elseif ( $registered && ! $st_row ) {
			$status_label = __( 'In Admin Texts, but not found in String Translation', 'wpml-translation-check' );
		} elseif ( '' === $current || $current === $default_lang ) {
			$status_label = __( 'Original language (not in Admin Texts)', 'wpml-translation-check' );
		} else {
			$translated   = false;
			$status_label = __( 'Not in Admin Texts', 'wpml-translation-check' );
		}

		$display_name = $is_theme_mod ? 'theme_mods_' . get_stylesheet() : $name;
		$display_path = $is_theme_mod ? '[' . $name . ']' . $path : $path;
		$links        = array(
			array(
				'label' => __( 'WPML Admin Texts', 'wpml-translation-check' ),
				'url'   => Component_Registry::admin_texts_url( $display_name ),
			),
		);
		if ( $registered && $st_row ) {
			$fix_url   = self::st_search_url( isset( $st_row['value'] ) ? (string) $st_row['value'] : $text, (string) $st_row['context'], (int) $st_row['id'] );
			$fix_label = __( 'Open in String Translation', 'wpml-translation-check' );
		} else {
			$fix_url   = Component_Registry::admin_texts_url( $display_name );
			$fix_label = __( 'Open Admin Texts', 'wpml-translation-check' );
			$links     = array();
		}

		$type_label = $is_theme_mod
			? __( 'Theme setting', 'wpml-translation-check' )
			: __( 'Site setting', 'wpml-translation-check' );

		if ( $registered ) {
			$type_label = $is_theme_mod
				? __( 'Theme setting (Admin Texts)', 'wpml-translation-check' )
				: __( 'Site setting (Admin Texts)', 'wpml-translation-check' );
		}

		$message = '';
		if ( ! $registered ) {
			$message = sprintf(
				/* translators: 1: option name, 2: key path */
				__( 'This setting is not in Admin Texts. Open Admin Texts, select "%1$s", then translate it in String Translation.', 'wpml-translation-check' ),
				$display_name,
				'' !== $display_path ? $display_path : __( '(whole value)', 'wpml-translation-check' )
			);
		}

		$res = self::result(
			array(
				'source_type'       => $is_theme_mod ? 'theme_mod' : 'option',
				'source_type_label' => $type_label,
				'identifiers'       => array_filter(
					array(
						'name'       => $name,
						'path'       => $path,
						'option'     => $display_name !== $name ? $display_name : null,
						'key_path'   => $display_path,
						'registered' => $registered ? 'yes' : 'no',
						'string_id'  => $st_row ? (int) $st_row['id'] : null,
						'st_context' => $st_row ? (string) $st_row['context'] : null,
						'st_name'    => $st_row ? (string) $st_row['name'] : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status_label,
				'fix_url'           => $fix_url,
				'fix_label'         => $fix_label,
				'message'           => $message,
				'links'             => $links,
				'text'              => $text,
			)
		);

		// WooCommerce email settings: WC settings link + WCML route.
		$email = $is_theme_mod ? null : Integration_Resolvers::wc_email_setting( $name, $path );
		if ( $email ) {
			$res['source_type']       = 'wc_email';
			$res['source_type_label'] = $email['label'];
			$res['links'][]           = array(
				'label' => __( 'Edit email in WooCommerce settings', 'wpml-translation-check' ),
				'url'   => $email['url'],
			);
			$res['message']           = $registered
				? __( 'Translate this email text in String Translation, or switch the language on the WooCommerce email settings screen.', 'wpml-translation-check' )
				: $message;
		}

		// ACF options page fields are stored as options_{name} with a _options_{name} field reference.
		if ( ! $is_theme_mod && '' === $path && 0 === strpos( $name, 'options_' ) && Integration_Resolvers::acf_active() ) {
			$ref = get_option( '_' . $name );
			if ( is_string( $ref ) && 0 === strpos( $ref, 'field_' ) ) {
				$acf = Integration_Resolvers::resolve_acf( $text, $ref, 'option', $current, $default_lang );
				if ( $acf ) {
					return $acf;
				}
			}
		}

		// Gap 8: registered + translated in ST, but the page still shows another value.
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		if ( $secondary && $registered && $st_row ) {
			$st = self::string_translation_status( (int) $st_row['id'], $current, $default_lang );
			if ( '' !== $st['value'] ) {
				$res = self::apply_mismatch( $res, $text, isset( $st_row['value'] ) ? (string) $st_row['value'] : $text, $st_row, $st, (string) $st_row['context'], '', $current );
			} elseif ( isset( $st_row['value'] ) && Origin_Collector::normalize( (string) $st_row['value'] ) !== $text ) {
				// The page shows a translation String Translation does not have: a leftover WPML file.
				$res['translated']   = true;
				$res['status_label'] = __( 'Shown translated, but missing in String Translation', 'wpml-translation-check' );
				$res['reasons']      = array(
					sprintf(
						/* translators: 1: ST context, 2: locale */
						__( 'The page uses an old WPML language file. Re-save the translation in String Translation so both match.', 'wpml-translation-check' ),
						(string) $st_row['context'],
						JS_Strings::locale_for( $current )
					),
				);
			}
		}

		return $res;
	}

	/**
	 * Term name as stored for a language (avoids WPML rewriting get_term on the page lang).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $lang     Language code.
	 * @return string
	 */
	private static function term_name_in_language( $term_id, $taxonomy, $lang ) {
		$term_id  = (int) $term_id;
		$taxonomy = (string) $taxonomy;
		$lang     = (string) $lang;
		if ( $term_id <= 0 || '' === $taxonomy || '' === $lang ) {
			return '';
		}
		$prev = (string) apply_filters( 'wpml_current_language', null );
		do_action( 'wpml_switch_language', $lang );
		$term = get_term( $term_id, $taxonomy );
		if ( '' !== $prev ) {
			do_action( 'wpml_switch_language', $prev );
		}
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		return Origin_Collector::normalize( $term->name );
	}

	/**
	 * Resolve a taxonomy term name captured during inspect render.
	 *
	 * Fix link is always the term edit screen (translated term when present,
	 * otherwise the source/default-language term) — never the WPML translation editor.
	 *
	 * @param string               $text         Visible text.
	 * @param array<string, mixed> $hint         Hint with id + tax.
	 * @param string               $current      Current language.
	 * @param string               $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_term( $text, array $hint, $current, $default_lang ) {
		$term_id  = isset( $hint['id'] ) ? absint( $hint['id'] ) : 0;
		$taxonomy = isset( $hint['tax'] ) ? sanitize_key( (string) $hint['tax'] ) : '';

		if ( $term_id <= 0 ) {
			return self::not_found( __( 'The category or tag for this text could not be found.', 'wpml-translation-check' ) );
		}

		$term = $taxonomy ? get_term( $term_id, $taxonomy ) : get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return self::not_found( __( 'The category or tag could not be loaded.', 'wpml-translation-check' ) );
		}

		$taxonomy     = (string) $term->taxonomy;
		$element_type = 'tax_' . $taxonomy;
		$is_trans_tax = (bool) apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy );

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => (int) $term->term_id,
				'element_type' => $element_type,
			)
		);
		$lang    = ( is_object( $details ) && isset( $details->language_code ) )
			? (string) $details->language_code
			: $current;

		$source_id  = (int) apply_filters( 'wpml_object_id', (int) $term->term_id, $taxonomy, true, $default_lang );
		$current_id = (int) apply_filters( 'wpml_object_id', (int) $term->term_id, $taxonomy, false, $current );
		if ( $source_id <= 0 ) {
			$source_id = (int) $term->term_id;
		}

		$translated   = null;
		$status_label = sprintf( /* translators: %s: language name, e.g. English */ __( 'Language: %s', 'wpml-translation-check' ), self::language_name( $lang ) );
		$edit_id      = $current_id > 0 ? $current_id : $source_id;

		if ( ! $is_trans_tax ) {
			$translated   = null;
			$status_label = __( 'This taxonomy is not set as translatable in WPML', 'wpml-translation-check' );
			$edit_id      = (int) $term->term_id;
		} elseif ( $current === $default_lang || $lang === $default_lang ) {
			$translated   = null;
			$status_label = __( 'Original language', 'wpml-translation-check' );
			$edit_id      = (int) $term->term_id;
		} elseif ( $current_id <= 0 ) {
			$translated   = false;
			$status_label = __( 'Not translated in this language', 'wpml-translation-check' );
			$edit_id      = $source_id;
		} elseif ( $source_id > 0 && $current_id === $source_id ) {
			// Fallback: secondary page still uses the default-language term object.
			$translated   = false;
			$status_label = __( 'Not translated in this language', 'wpml-translation-check' );
			$edit_id      = $source_id;
		} else {
			// Compare names in their own languages (get_term is WPML-filtered otherwise).
			$src_name = self::term_name_in_language( $source_id, $taxonomy, $default_lang );
			$cur_name = self::term_name_in_language( $current_id, $taxonomy, $current );
			if ( '' === $cur_name ) {
				$cur_name = Origin_Collector::normalize( $text );
			}
			if ( '' !== $src_name && '' !== $cur_name && $src_name === $cur_name ) {
				$translated   = false;
				$status_label = __( 'Same as the original (probably not translated)', 'wpml-translation-check' );
			} elseif ( '' !== $src_name && '' !== $cur_name && $src_name !== $cur_name ) {
				$translated   = true;
				$status_label = __( 'Translated', 'wpml-translation-check' );
			} elseif ( $lang === $current && $current_id > 0 && $current_id !== $source_id ) {
				// Distinct current-lang term object with no contradictory name evidence.
				$translated   = true;
				$status_label = __( 'Translated', 'wpml-translation-check' );
			} else {
				// No positive evidence either way.
				$translated   = null;
				$status_label = sprintf( /* translators: %s: language name, e.g. English */ __( 'Language: %s', 'wpml-translation-check' ), self::language_name( $lang ) );
			}
			$edit_id = $current_id > 0 ? $current_id : $source_id;
		}

		$fix_url = get_edit_term_link( $edit_id, $taxonomy );
		// Some taxonomies (e.g. internal product types) yield an empty or non-admin URL.
		if (
			! is_string( $fix_url )
			|| '' === $fix_url
			|| ( false === strpos( $fix_url, 'tag_ID=' ) && false === strpos( $fix_url, 'taxonomy=' ) )
		) {
			$fix_url = admin_url( 'term.php?taxonomy=' . rawurlencode( $taxonomy ) . '&tag_ID=' . (int) $edit_id );
		}

		return array(
			'found'             => true,
			'source_type'       => 'term',
			'source_type_label' => __( 'Category or tag', 'wpml-translation-check' ),
			'identifiers'       => array_filter(
				array(
					'term_id'      => (int) $term->term_id,
					'taxonomy'     => $taxonomy,
					'language'     => $lang,
					'source_id'    => $source_id ? $source_id : null,
					'current_id'   => $current_id ? $current_id : null,
					'element_type' => $element_type,
					'edit_id'      => (int) $edit_id,
				)
			),
			'translated'        => $translated,
			'status_label'      => $status_label,
			'fix_url'           => $fix_url,
			'fix_label'         => __( 'Edit category or tag', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
		);
	}

	/**
	 * Resolve post title / content via public WPML filters.
	 *
	 * @param string               $text    Visible text.
	 * @param array<string, mixed> $hint    Hint.
	 * @param string               $current Current lang.
	 * @param string               $default_lang Default lang.
	 * @param string               $kind    post_title|post_content.
	 * @param bool                 $from_package Whether the text came from a WPML builder package.
	 * @return array<string, mixed>
	 */
	private static function resolve_post( $text, array $hint, $current, $default_lang, $kind, $from_package = false ) {
		$post_id = isset( $hint['id'] ) ? absint( $hint['id'] ) : 0;
		if ( $post_id <= 0 ) {
			return self::not_found( __( 'The post for this text could not be found.', 'wpml-translation-check' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return self::not_found( __( 'Post could not be loaded.', 'wpml-translation-check' ) );
		}

		$element_type = 'post_' . $post->post_type;
		$details      = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $post_id,
				'element_type' => $element_type,
			)
		);

		$lang = ( is_object( $details ) && isset( $details->language_code ) )
			? (string) $details->language_code
			: $current;

		$source_id    = (int) apply_filters( 'wpml_object_id', $post_id, $post->post_type, true, $default_lang );
		$translated   = null;
		$status_label = sprintf( /* translators: %s: language name, e.g. English */ __( 'Language: %s', 'wpml-translation-check' ), self::language_name( $lang ) );
		// Text printed by a shortcode/block/widget inside the content (e.g. WooCommerce My Account):
		// when it is not written in the post, a matching theme/plugin string wins.
		if ( 'post_content' === $kind && empty( $hint['x'] ) && ! $from_package ) {
			$gettext = self::gettext_for_rendered_text( $text, $post, $source_id, $current, $default_lang );
			if ( $gettext ) {
				return $gettext;
			}
		}

		// Archive cards print the excerpt: a manual excerpt is its own field.
		// Shown as an excerpt (hint x) + manual excerpt -> Post excerpt; auto excerpt -> Post content.
		$excerpt = false;
		if ( 'post_content' === $kind && ! empty( $hint['x'] ) ) {
			$excerpt = '' !== trim( (string) $post->post_excerpt );
		} elseif ( 'post_content' === $kind && '' !== (string) $post->post_excerpt ) {
			$text_n  = Origin_Collector::normalize( $text );
			$excerpt = '' !== $text_n
				&& false !== strpos( Origin_Collector::normalize( wp_strip_all_tags( $post->post_excerpt ) ), $text_n )
				&& false === strpos( Origin_Collector::normalize( wp_strip_all_tags( $post->post_content ) ), $text_n );
		}

		if ( $lang === $default_lang ) {
			$translated   = null;
			$status_label = __( 'Original language', 'wpml-translation-check' );
		} elseif ( $source_id && $source_id !== $post_id ) {
			// Compare field to source post field when available.
			$source_post = get_post( $source_id );
			if ( $source_post instanceof \WP_Post ) {
				$field   = ( 'post_title' === $kind ) ? 'post_title' : ( $excerpt ? 'post_excerpt' : 'post_content' );
				$src_val = wp_strip_all_tags( (string) $source_post->$field );
				$cur_val = wp_strip_all_tags( (string) $post->$field );
				$src_n   = Origin_Collector::normalize( $src_val );
				$cur_n   = Origin_Collector::normalize( $cur_val );
				$text_n  = Origin_Collector::normalize( $text );
				if ( ! $from_package && 'post_content' === $kind && ! $excerpt && '' !== $text_n && false === strpos( $src_n, $text_n ) && false === strpos( $cur_n, $text_n ) ) {
					// Text is not authored in the source post: it is rendered by a shortcode/block (e.g. gettext).
					return self::resolve_dynamic_post_text( $text, $post_id, $current, $default_lang );
				}
				if ( '' !== $src_n && $src_n === $cur_n ) {
					$translated   = false;
					$status_label = __( 'Same as the original (probably not translated)', 'wpml-translation-check' );
				} elseif ( self::is_untranslated_source_text( $text_n, $src_n, $lang, $default_lang ) ) {
					// Source-language leftover on a secondary-language page (left untranslated or
					// pasted back). translated=false so Untranslated on this page lists it; Status
					// stays Original language. Short proper nouns stay out via is_untranslated_source_text.
					$translated   = false;
					$status_label = __( 'Original language', 'wpml-translation-check' );
				} else {
					$translated   = true;
					$status_label = __( 'Translated', 'wpml-translation-check' );
				}
			}
		}

		$edit_url = get_edit_post_link( $post_id, 'raw' );
		$fix_url  = $edit_url ? $edit_url : admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		// Prefer WPML translation editor link when available via public filter.
		$tm_link = apply_filters( 'wpml_get_post_translation_url', null, $post_id );
		if ( is_string( $tm_link ) && '' !== $tm_link ) {
			$fix_url = $tm_link;
		}

		if ( 'post_title' === $kind ) {
			$label = __( 'Post title', 'wpml-translation-check' );
		} elseif ( $excerpt ) {
			$label = __( 'Post excerpt', 'wpml-translation-check' );
		} else {
			$label = __( 'Post content', 'wpml-translation-check' );
		}

		return array(
			'found'             => true,
			'source_type'       => $kind,
			'source_type_label' => $label,
			'identifiers'       => array_filter(
				array(
					'post_id'      => $post_id,
					'post_type'    => $post->post_type,
					'language'     => $lang,
					'source_id'    => $source_id ? $source_id : null,
					'element_type' => $element_type,
				)
			),
			'translated'        => $translated,
			'status_label'      => $status_label,
			'fix_url'           => $fix_url,
			'fix_label'         => __( 'Edit post / translation', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
		);
	}

	/**
	 * True when the clicked text is the source/original-language content shown on a
	 * secondary-language page (a source paragraph left untranslated, or pasted back into
	 * the translation) rather than a real translation. The text must appear verbatim in the
	 * source-language field. A cross-script language pair is confirmed when the text is in
	 * the source language's own script, which the page language does not use; for a
	 * same-script pair the script test cannot decide, so a substantial verbatim run is
	 * required to avoid flagging short shared tokens (names, numbers) that legitimately repeat.
	 *
	 * @param string $text_n  Normalized clicked text.
	 * @param string $src_n   Normalized source-language field.
	 * @param string $lang         Page (translation) language code.
	 * @param string $default_lang Default (source) language code.
	 * @return bool
	 */
	private static function is_untranslated_source_text( $text_n, $src_n, $lang, $default_lang ) {
		if ( '' === $text_n || '' === $src_n || false === strpos( $src_n, $text_n ) ) {
			return false;
		}
		$langs       = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		$langs       = is_array( $langs ) ? $langs : array();
		$page_script = isset( $langs[ $lang ]['native_name'] ) ? self::script_of( (string) $langs[ $lang ]['native_name'] ) : '';
		$def_script  = isset( $langs[ $default_lang ]['native_name'] ) ? self::script_of( (string) $langs[ $default_lang ]['native_name'] ) : '';
		$text_script = self::script_of( $text_n );
		// Cross-script pair: the clicked text is written in the source script, not the page script.
		if ( '' !== $def_script && $def_script !== $page_script && '' !== $text_script && $text_script === $def_script ) {
			return true;
		}
		// Same-script or undetermined pair: require a substantial verbatim run of letters.
		return (int) preg_match_all( '/\p{L}/u', $text_n ) >= 40;
	}

	/**
	 * Theme/plugin string for text a shortcode, block or widget printed inside the content.
	 *
	 * Only used when the text is not written in the post (or its original-language post).
	 *
	 * @param string   $text         Visible text.
	 * @param \WP_Post $post         Post.
	 * @param int      $source_id    Original-language post ID.
	 * @param string   $current      Current language.
	 * @param string   $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	private static function gettext_for_rendered_text( $text, $post, $source_id, $current, $default_lang ) {
		$text_n = Origin_Collector::normalize( $text );
		if ( '' === $text_n || strlen( $text_n ) < 5 || ! preg_match( '/\p{L}/u', $text_n ) ) {
			return null;
		}
		$bodies = array( (string) $post->post_content, (string) $post->post_excerpt );
		if ( $source_id && (int) $source_id !== (int) $post->ID ) {
			$source = get_post( $source_id );
			if ( $source instanceof \WP_Post ) {
				$bodies[] = (string) $source->post_content;
			}
		}
		foreach ( $bodies as $body ) {
			// Strip shortcodes / block comments so only text written by the author counts.
			$plain = Origin_Collector::normalize( strip_shortcodes( preg_replace( '/<!--.*?-->/s', ' ', $body ) ) );
			if ( '' !== $plain && false !== strpos( $plain, $text_n ) ) {
				return null;
			}
		}
		$matches = self::match_strings_by_visible( $text_n, $current, $default_lang );
		return $matches ? self::resolve_matched_strings( $text, $matches, $current, $default_lang ) : null;
	}

	/**
	 * Visible-text regex for a string: tags removed, placeholders (%s, %1$s, %d) as wildcards.
	 *
	 * @param string $value String value (may hold HTML and placeholders).
	 * @return string Regex without delimiters, '' when unusable.
	 */
	private static function visible_regex( $value ) {
		$marked = preg_replace( '/%(?:\d+\$)?[sdFfcuxX]/', "\x01", (string) $value );
		$marked = Origin_Collector::normalize( (string) $marked );
		if ( '' === $marked ) {
			return '';
		}
		$re = '';
		foreach ( explode( "\x01", $marked ) as $i => $part ) {
			$re .= ( $i > 0 ? '.*?' : '' ) . preg_quote( $part, '/' );
		}
		return (string) preg_replace( '/(?:\\ |\s)+/', '\\s*', $re );
	}

	/**
	 * WPML ST strings whose visible form matches the text (HTML stripped, placeholders as wildcards).
	 *
	 * Looks at originals, then at translations in the current language. Bounded: up to four needles, two LIKE queries each (LIMIT 80).
	 *
	 * @param string $text         Visible text.
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<int, array<string, string>> Matching icl_strings rows (most specific first).
	 */
	private static function match_strings_by_visible( $text, $current, $default_lang ) {
		global $wpdb;
		$text = Origin_Collector::normalize( $text );
		if ( '' === $text || strlen( $text ) > 2000 ) {
			return array();
		}
		$ckey = 'vis:' . $current . ':' . self::ckey( $text );
		if ( isset( self::$cache['visible'][ $ckey ] ) ) {
			return self::$cache['visible'][ $ckey ];
		}
		// Search by literal parts of the text: start, end, then the two longest words. A string may
		// start or end with a placeholder (e.g. "%s items"), so each needle is tried until one confirms
		// a match. At most four needles.
		$words   = array_values( array_filter( (array) preg_split( '/\s+/u', $text ), 'strlen' ) );
		$needles = array( $wpdb->esc_like( implode( ' ', array_slice( $words, 0, 4 ) ) ) . '%' );
		if ( count( $words ) > 1 ) {
			$needles[] = '%' . $wpdb->esc_like( implode( ' ', array_slice( $words, -2 ) ) );
		}
		$long = array();
		foreach ( $words as $word ) {
			$word = trim( $word, '.,:;!?()[]"\'' );
			if ( strlen( $word ) >= 3 && ! preg_match( '/^\d+$/', $word ) ) {
				$long[ $word ] = strlen( $word );
			}
		}
		arsort( $long );
		foreach ( array_slice( array_keys( $long ), 0, 2 ) as $word ) {
			$needles[] = '%' . $wpdb->esc_like( $word ) . '%';
		}
		$secondary = '' !== $current && $current !== $default_lang;
		$found     = array();
		foreach ( array_unique( $needles ) as $like ) {
			$rows  = self::visible_candidates( $like, $secondary ? $current : '' );
			$found = self::confirm_visible( $rows, $text );
			if ( $found ) {
				break;
			}
		}
		self::$cache['visible'][ $ckey ] = $found;
		return $found;
	}

	/**
	 * ST rows (originals, plus translations in one language) whose value is LIKE a needle.
	 *
	 * @param string $like Escaped LIKE pattern.
	 * @param string $lang Language for translations ('' = originals only).
	 * @return array<int, array<string, string>>
	 */
	private static function visible_candidates( $like, $lang ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only, bounded WPML ST lookup.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, context, name, value, gettext_context FROM {$wpdb->prefix}icl_strings WHERE value LIKE %s ORDER BY CHAR_LENGTH(value) LIMIT 80", $like ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();
		if ( '' !== $lang ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only, bounded WPML ST lookup.
			$tr   = $wpdb->get_results( $wpdb->prepare( "SELECT s.id, s.context, s.name, s.value, s.gettext_context, t.value AS tvalue FROM {$wpdb->prefix}icl_string_translations t INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = t.string_id WHERE t.language = %s AND t.value LIKE %s ORDER BY CHAR_LENGTH(t.value) LIMIT 80", $lang, $like ), ARRAY_A );
			$rows = array_merge( $rows, is_array( $tr ) ? $tr : array() );
		}
		return $rows;
	}

	/**
	 * Keep rows whose visible form (tags removed, placeholders as wildcards) equals the text.
	 *
	 * @param array<int, array<string, string>> $rows Candidate rows.
	 * @param string                            $text Normalized visible text.
	 * @return array<int, array<string, string>> Most literal text first.
	 */
	private static function confirm_visible( array $rows, $text ) {
		$found   = array();
		$letters = (int) preg_match_all( '/\p{L}/u', $text );
		foreach ( (array) $rows as $row ) {
			$shown   = isset( $row['tvalue'] ) ? (string) $row['tvalue'] : (string) $row['value'];
			$literal = (int) preg_match_all( '/\p{L}/u', (string) preg_replace( '/%(?:\d+\$)?[sdFfcuxX]/', '', Origin_Collector::normalize( $shown ) ) );
			// Mostly-placeholder strings ("%s", "%1$s: %2$s") would match anything.
			if ( $literal < 3 || $literal * 10 < $letters * 3 ) {
				continue;
			}
			$re = self::visible_regex( $shown );
			if ( '' !== $re && preg_match( '/^' . $re . '$/u', $text ) ) {
				$found[ (int) $row['id'] ] = array(
					'id'              => (string) $row['id'],
					'context'         => (string) $row['context'],
					'name'            => (string) $row['name'],
					'value'           => (string) $row['value'],
					'gettext_context' => (string) $row['gettext_context'],
					'literal'         => (string) $literal,
				);
			}
		}
		$found = array_values( $found );
		// Most literal text first: the string that explains more of what is shown.
		usort(
			$found,
			static function ( $a, $b ) {
				return (int) $b['literal'] - (int) $a['literal'];
			}
		);
		return $found;
	}

	/**
	 * Gettext result for the best matched ST row (only one row is shown).
	 *
	 * @param string                            $text         Visible text.
	 * @param array<int, array<string, string>> $matches      Rows from match_strings_by_visible().
	 * @param string                            $current      Current language.
	 * @param string                            $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_matched_strings( $text, array $matches, $current, $default_lang ) {
		$best    = self::pick_best_row( $matches );
		$package = $best ? self::match_builder_package( (string) $best['context'] ) : null;
		if ( $package ) {
			return self::resolve_builder_package( $text, $package, $current, $default_lang );
		}
		$res = self::resolve_gettext(
			$text,
			array(
				'd' => $best['context'],
				'o' => $best['value'],
				'c' => $best['gettext_context'],
			),
			$current,
			$default_lang
		);
		return $res;
	}

	/**
	 * Resolve text rendered inside post content but not authored in the source post.
	 *
	 * @param string $text         Visible text.
	 * @param int    $post_id      Post ID.
	 * @param string $current      Current lang.
	 * @param string $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_dynamic_post_text( $text, $post_id, $current, $default_lang ) {
		global $wpdb;
		$value = trim( (string) $text );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lookup of a WPML ST translation by its value.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.id, s.context, s.value FROM {$wpdb->prefix}icl_string_translations t INNER JOIN {$wpdb->prefix}icl_strings s ON s.id = t.string_id WHERE t.value = %s AND t.language = %s LIMIT 1",
				$value,
				$current
			),
			ARRAY_A
		);
		if ( is_array( $row ) ) {
			$package = self::match_builder_package( (string) $row['context'] );
			if ( $package ) {
				return self::resolve_builder_package( $text, $package, $current, $default_lang );
			}
			return self::resolve_gettext(
				$text,
				array(
					'o' => (string) $row['value'],
					'd' => (string) $row['context'],
				),
				$current,
				$default_lang
			);
		}
		$orig = self::lookup_icl_string_by_value( $value );
		if ( $orig ) {
			$package = self::match_builder_package( (string) $orig['context'] );
			if ( $package ) {
				return self::resolve_builder_package( $text, $package, $current, $default_lang );
			}
			return self::resolve_gettext( $text, array( 'd' => (string) $orig['context'] ), $current, $default_lang );
		}
		$matches = self::match_strings_by_visible( $value, $current, $default_lang );
		if ( $matches ) {
			return self::resolve_matched_strings( $text, $matches, $current, $default_lang );
		}
		$edit_url = get_edit_post_link( $post_id, 'raw' );
		return array(
			'found'             => true,
			'source_type'       => 'post_content',
			'source_type_label' => __( 'Post content', 'wpml-translation-check' ),
			'identifiers'       => array( 'post_id' => $post_id ),
			'translated'        => null,
			'status_label'      => __( 'Added automatically (not written in the original post)', 'wpml-translation-check' ),
			'fix_url'           => $edit_url ? $edit_url : admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			'fix_label'         => __( 'Edit post / translation', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
		);
	}

	/**
	 * Resolve nav menu item.
	 *
	 * @param string               $text    Visible text.
	 * @param array<string, mixed> $hint    Hint.
	 * @param string               $current Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_menu_item( $text, array $hint, $current, $default_lang ) {
		$item_id = isset( $hint['id'] ) ? absint( $hint['id'] ) : 0;
		if ( $item_id <= 0 ) {
			return self::not_found( __( 'The menu item could not be found.', 'wpml-translation-check' ) );
		}

		$item = get_post( $item_id );
		if ( ! $item instanceof \WP_Post ) {
			return self::not_found( __( 'Menu item could not be loaded.', 'wpml-translation-check' ) );
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => $item_id,
				'element_type' => 'post_nav_menu_item',
			)
		);
		$lang    = ( is_object( $details ) && isset( $details->language_code ) )
			? (string) $details->language_code
			: '';

		$translated   = null;
		$status_label = '' !== $lang
			? sprintf(
				/* translators: %s: language name, e.g. English */
				__( 'Language: %s', 'wpml-translation-check' ),
				self::language_name( $lang )
			)
			: __( 'Menu item', 'wpml-translation-check' );

		if ( '' !== $lang && '' !== $default_lang && $lang === $default_lang && $current !== $default_lang ) {
			$translated   = false;
			$status_label = __( 'Original-language menu item shown on translated page', 'wpml-translation-check' );
		}

		// The menu (nav_menu term) that holds this item, and its translation in the page language.
		$menu_id   = 0;
		$menu_name = '';
		$terms     = wp_get_object_terms( $item_id, 'nav_menu' );
		if ( is_array( $terms ) && $terms && $terms[0] instanceof \WP_Term ) {
			$menu_id   = (int) $terms[0]->term_id;
			$menu_name = (string) $terms[0]->name;
		}
		$fix_url   = $menu_id ? admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ) : admin_url( 'nav-menus.php' );
		$fix_label = $menu_id
			? sprintf( /* translators: %s: menu name */ __( 'Edit menu "%s"', 'wpml-translation-check' ), $menu_name )
			: __( 'Open Menus screen', 'wpml-translation-check' );
		$links     = array();
		$tr_menu   = 0;
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		if ( $menu_id && $secondary ) {
			$tr_menu = (int) apply_filters( 'wpml_object_id', $menu_id, 'nav_menu', false, $current );
			if ( $tr_menu > 0 && $tr_menu !== $menu_id ) {
				$fix_url   = admin_url( 'nav-menus.php?action=edit&menu=' . $tr_menu . '&lang=' . rawurlencode( $current ) );
				$fix_label = sprintf( /* translators: %s: language name, e.g. Hindi */ __( 'Edit the %s menu', 'wpml-translation-check' ), self::language_name( $current ) );
				$links[]   = array(
					'label' => __( 'Edit the original menu', 'wpml-translation-check' ),
					'url'   => admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ),
				);
			} elseif ( '' !== $lang && $lang === $current ) {
				// Item of the translated menu: compare with the original-language menu.
				$src_menu = (int) apply_filters( 'wpml_object_id', $menu_id, 'nav_menu', false, $default_lang );
				if ( $src_menu > 0 && $src_menu !== $menu_id ) {
					$links[]  = array(
						'label' => sprintf( /* translators: %s: language name, e.g. English */ __( 'Edit the original (%s) menu', 'wpml-translation-check' ), self::language_name( $default_lang ) ),
						'url'   => admin_url( 'nav-menus.php?action=edit&menu=' . $src_menu . '&lang=' . rawurlencode( $default_lang ) ),
					);
					$src_item = (int) apply_filters( 'wpml_object_id', $item_id, 'nav_menu_item', false, $default_lang );
					$same     = false;
					if ( $src_item > 0 && $src_item !== $item_id ) {
						$same = Origin_Collector::normalize( (string) get_the_title( $src_item ) ) === $text;
					} else {
						foreach ( array_slice( (array) wp_get_nav_menu_items( $src_menu ), 0, 200 ) as $src ) {
							if ( isset( $src->title ) && Origin_Collector::normalize( (string) $src->title ) === $text ) {
								$same = true;
								break;
							}
						}
					}
					$translated   = ! $same;
					$status_label = $same
						? sprintf( /* translators: %s: language name, e.g. English */ __( 'Label not translated: same as the %s menu item', 'wpml-translation-check' ), self::language_name( $default_lang ) )
						: __( 'Translated menu item', 'wpml-translation-check' );
				}
			} elseif ( '' !== $lang && $lang === $default_lang ) {
				$trid         = (int) apply_filters( 'wpml_element_trid', 0, $menu_id, 'tax_nav_menu' );
				$translated   = false;
				$status_label = sprintf( /* translators: %s: language name, e.g. Hindi */ __( 'This menu has no %s translation: the original menu is shown', 'wpml-translation-check' ), self::language_name( $current ) );
				if ( $trid > 0 ) {
					$links[] = array(
						'label' => sprintf( /* translators: %s: language name, e.g. Hindi */ __( 'Create the %s translation of this menu', 'wpml-translation-check' ), self::language_name( $current ) ),
						'url'   => admin_url( 'nav-menus.php?action=edit&menu=0&trid=' . $trid . '&lang=' . rawurlencode( $current ) ),
					);
				}
			}
		}
		if ( $menu_id && ! $secondary ) {
			// Original-language page: link every existing translation of this menu.
			$languages = (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			foreach ( array_slice( array_keys( $languages ), 0, 20 ) as $code ) {
				$code = (string) $code;
				if ( $code === $lang ) {
					continue;
				}
				$tid = (int) apply_filters( 'wpml_object_id', $menu_id, 'nav_menu', false, $code );
				if ( $tid > 0 && $tid !== $menu_id ) {
					$links[] = array(
						'label' => sprintf( /* translators: %s: language name, e.g. Hindi */ __( 'Edit the %s translation of this menu', 'wpml-translation-check' ), self::language_name( $code ) ),
						'url'   => admin_url( 'nav-menus.php?action=edit&menu=' . $tid . '&lang=' . rawurlencode( $code ) ),
					);
				}
			}
		}
		$links[] = array(
			'label' => __( 'WPML Menus Sync', 'wpml-translation-check' ),
			'url'   => Component_Registry::menu_sync_url(),
		);

		return self::result(
			array(
				'source_type'       => 'nav_menu_item',
				'source_type_label' => __( 'Menu item', 'wpml-translation-check' ),
				'identifiers'       => array_filter(
					array(
						'menu_item_id'    => $item_id,
						'title'           => $item->post_title,
						'language'        => $lang ? $lang : null,
						'menu_id'         => $menu_id ? $menu_id : null,
						'menu'            => '' !== $menu_name ? $menu_name : null,
						'translated_menu' => ( $tr_menu && $tr_menu !== $menu_id ) ? $tr_menu : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status_label,
				'fix_url'           => $fix_url,
				'fix_label'         => $fix_label,
				'links'             => $links,
				'text'              => $text,
			)
		);
	}

	/**
	 * Resolve classic widget text (title/body) via String Translation context "Widgets".
	 *
	 * @param string               $text         Visible text.
	 * @param array<string, mixed> $hint         Hint.
	 * @param string               $current      Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_widget( $text, array $hint, $current, $default_lang ) {
		$wid       = isset( $hint['wid'] ) ? (string) $hint['wid'] : '';
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;

		$translated   = null;
		$status_label = __( 'Original language', 'wpml-translation-check' );
		$fix_url      = admin_url( 'widgets.php' );
		$fix_label    = __( 'Open Widgets screen', 'wpml-translation-check' );
		$string_id    = 0;

		$row = self::lookup_icl_string_by_value( $text, 'Widgets' );
		if ( is_array( $row ) && 'Widgets' === (string) $row['context'] ) {
			$string_id = (int) $row['id'];
		}

		if ( $string_id > 0 ) {
			$st           = self::string_translation_status( $string_id, $current, $default_lang );
			$translated   = $st['translated'];
			$status_label = $st['label'];
			if ( $secondary ) {
				$fix_url   = self::st_search_url( $text, 'Widgets', $string_id );
				$fix_label = __( 'Translate in String Translation', 'wpml-translation-check' );
			}
		} elseif ( $secondary ) {
			// Shown in the default-language value and not registered in ST: cannot be translated yet.
			$translated   = false;
			$status_label = __( 'Widget text is not in String Translation', 'wpml-translation-check' );
		}

		$res = self::result(
			array(
				'source_type'       => 'widget',
				'source_type_label' => __( 'Widget', 'wpml-translation-check' ),
				'identifiers'       => array_filter(
					array(
						'widget_id' => $wid ? $wid : null,
						'string_id' => $string_id ? $string_id : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status_label,
				'fix_url'           => $fix_url,
				'fix_label'         => $fix_label,
				'text'              => $text,
			)
		);
		if ( $secondary && $string_id > 0 && is_array( $row ) ) {
			$st = self::string_translation_status( $string_id, $current, $default_lang );
			if ( '' !== $st['value'] ) {
				$res = self::apply_mismatch( $res, $text, (string) $row['value'], $row, $st, 'Widgets', '', $current );
			}
		}
		return $res;
	}

	/**
	 * Resolve Elementor widget text and compare it with the default-language document.
	 *
	 * @param string               $text         Visible text.
	 * @param array<string, mixed> $hint         Hint.
	 * @param string               $current      Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_elementor( $text, array $hint, $current, $default_lang ) {
		$eid = isset( $hint['eid'] ) ? (string) $hint['eid'] : '';
		$did = isset( $hint['did'] ) ? absint( $hint['did'] ) : 0;

		if ( $did <= 0 ) {
			$did = (int) get_queried_object_id();
		}

		$fix_url = '';
		if ( $did > 0 && defined( 'ELEMENTOR_VERSION' ) ) {
			$fix_url = admin_url( 'post.php?post=' . $did . '&action=elementor' );
			if ( $eid ) {
				$fix_url = add_query_arg( 'elementor_element_id', rawurlencode( $eid ), $fix_url );
			}
		} elseif ( $did > 0 ) {
			$edit    = get_edit_post_link( $did, 'raw' );
			$fix_url = $edit ? $edit : '';
		}

		$translated      = null;
		$same_as_default = false;
		$status_label    = __( 'Elementor widget text', 'wpml-translation-check' );
		$doc_lang        = '';
		$source_id       = 0;
		$secondary       = '' !== $current && '' !== $default_lang && $current !== $default_lang;

		if ( $did > 0 ) {
			$post_type = (string) get_post_type( $did );
			$details   = apply_filters(
				'wpml_element_language_details',
				null,
				array(
					'element_id'   => $did,
					'element_type' => 'post_' . $post_type,
				)
			);
			if ( is_object( $details ) && isset( $details->language_code ) ) {
				$doc_lang     = (string) $details->language_code;
				$status_label = sprintf(
					/* translators: %s: language name, e.g. English */
					__( 'Language: %s', 'wpml-translation-check' ),
					self::language_name( $doc_lang )
				);
			}

			if ( $secondary && '' !== $doc_lang ) {
				if ( $doc_lang === $default_lang ) {
					$translated   = false;
					$status_label = __( 'Original-language Elementor page shown on a translated page', 'wpml-translation-check' );
				} else {
					$source_id = (int) apply_filters( 'wpml_object_id', $did, $post_type, false, $default_lang );
					if ( $source_id > 0 && $source_id !== $did ) {
						if ( self::elementor_document_has_text( $source_id, $text ) ) {
							$translated      = false;
							$status_label    = __( 'Same as the original', 'wpml-translation-check' );
							$same_as_default = true;
						} else {
							$translated   = true;
							$status_label = __( 'Translated', 'wpml-translation-check' );
						}
					}
				}
			}
		}

		return array(
			'found'             => true,
			'source_type'       => 'elementor',
			'source_type_label' => __( 'Elementor widget', 'wpml-translation-check' ),
			'identifiers'       => array_filter(
				array(
					'element_id'          => $eid ? $eid : null,
					'document_id'         => $did ? $did : null,
					'document_language'   => $doc_lang ? $doc_lang : null,
					'default_lang_doc_id' => $source_id ? $source_id : null,
				)
			),
			'translated'        => $translated,
			'same_as_default'   => $same_as_default,
			'status_label'      => $status_label,
			'fix_url'           => $fix_url,
			'fix_label'         => __( 'Edit in Elementor', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
		);
	}

	/**
	 * Whether an Elementor document contains a setting whose plain text equals $text.
	 *
	 * @param int    $post_id Document ID.
	 * @param string $text    Normalized visible text.
	 * @return bool
	 */
	private static function elementor_document_has_text( $post_id, $text ) {
		// Decode + flatten each document once per request (scan batches reuse it).
		if ( ! isset( self::$cache['el_texts'][ $post_id ] ) ) {
			$texts = array();
			$raw   = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_string( $raw ) ) {
				$raw = json_decode( $raw, true );
			}
			if ( is_array( $raw ) ) {
				array_walk_recursive(
					$raw,
					static function ( $value ) use ( &$texts ) {
						if ( is_string( $value ) && '' !== $value ) {
							$texts[ Origin_Collector::normalize( wp_strip_all_tags( $value ) ) ] = true;
						}
					}
				);
			}
			self::$cache['el_texts'][ $post_id ] = $texts;
		}
		return isset( self::$cache['el_texts'][ $post_id ][ $text ] );
	}

	/**
	 * Whether an option (+ optional path) is registered in Admin Texts.
	 *
	 * @param string $option_name Option name.
	 * @param string $path        Bracket path e.g. [headline].
	 * @return bool
	 */
	private static function is_admin_text_registered( $option_name, $path = '' ) {
		$registry = get_option( '_icl_admin_option_names', array() );
		if ( ! is_array( $registry ) || ! isset( $registry[ $option_name ] ) ) {
			return false;
		}
		$entry = $registry[ $option_name ];
		if ( 1 === (int) $entry || true === $entry ) {
			return true;
		}
		if ( ! is_array( $entry ) || '' === $path ) {
			return is_array( $entry ); // Nested option registered at top level.
		}
		// Path like [headline] or [footer-sml-section-1-credit].
		$keys = array();
		if ( preg_match_all( '/\[([^\]]+)\]/', $path, $m ) ) {
			$keys = $m[1];
		}
		$cursor = $entry;
		foreach ( $keys as $key ) {
			if ( ! is_array( $cursor ) || ! isset( $cursor[ $key ] ) ) {
				return false;
			}
			$cursor = $cursor[ $key ];
		}
		return ( 1 === (int) $cursor || true === $cursor || is_array( $cursor ) );
	}

	/**
	 * Look up Admin Texts string row (read-only).
	 *
	 * @param string $option_name Option name.
	 * @param string $path        Bracket path.
	 * @return array{id:int,context:string,name:string,value:string}|null
	 */
	private static function lookup_admin_text_string( $option_name, $path = '' ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are prefix-safe strings, not user input.
		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return null;
		}
		$table   = $wpdb->prefix . 'icl_strings';
		$context = 'admin_texts_' . $option_name;
		$name    = '' !== $path ? '[' . $option_name . ']' . $path : $option_name;
		// WPML stores Admin Text names like [option_name]field_key.
		$name_alt = '' !== $path
			? '[' . $option_name . ']' . preg_replace( '/^\[|\]$/', '', str_replace( '][', '][', $path ) )
			: $option_name;
		// Normalize [option][key] => [option]key (WPML style for one level).
		if ( '' !== $path && preg_match( '/^\[([^\]]+)\]$/', $path, $m ) ) {
			$name_alt = '[' . $option_name . ']' . $m[1];
		}
		if ( isset( self::$cache['admin'][ $context ] ) ) {
			$needle = trim( $path, '[]' );
			$loose  = null;
			foreach ( self::$cache['admin'][ $context ] as $row ) {
				if ( $row['name'] === $name || $row['name'] === $name_alt ) {
					return $row;
				}
				if ( null === $loose && '' !== $needle && false !== stripos( $row['name'], $needle ) ) {
					$loose = $row;
				}
			}
			return $loose;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix., WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only ST lookup; table name from `$wpdb->prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, context, name, value FROM {$table} WHERE context = %s AND (name = %s OR name = %s) LIMIT 1",
				$context,
				$name,
				$name_alt
			),
			ARRAY_A
		);

		if ( ! $row && '' !== $path ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, context, name, value FROM {$table} WHERE context = %s AND name LIKE %s LIMIT 1",
					$context,
					'%' . $wpdb->esc_like( trim( $path, '[]' ) ) . '%'
				),
				ARRAY_A
			);
		}

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Look up a gettext string in icl_strings by domain/context and original.
	 *
	 * @param string $domain  Domain.
	 * @param string $value   Original value.
	 * @param string $gettext_context Gettext context.
	 * @return array{id:int,context:string,name:string,value:string}|null
	 */
	private static function lookup_icl_string( $domain, $value, $gettext_context = '' ) {
		global $wpdb;
		if ( '' === $domain || '' === $value ) {
			return null;
		}
		$primed = self::primed_rows( $value );
		if ( null !== $primed ) {
			foreach ( $primed as $row ) {
				if ( 0 === strcasecmp( $row['context'], $domain ) && ( '' === $gettext_context || $row['gettext_context'] === $gettext_context ) ) {
					return $row;
				}
			}
			return null;
		}
		$table = $wpdb->prefix . 'icl_strings';
		if ( '' !== $gettext_context ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix., WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only ST lookup; table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, context, name, value, gettext_context FROM {$table} WHERE context = %s AND value = %s AND gettext_context = %s LIMIT 1",
					$domain,
					$value,
					$gettext_context
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix., WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only ST lookup; table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, context, name, value, gettext_context FROM {$table} WHERE context = %s AND value = %s LIMIT 1",
					$domain,
					$value
				),
				ARRAY_A
			);
		}
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fallback ST lookup by value, optionally preferring a domain/context.
	 *
	 * @param string $value  Original value.
	 * @param string $domain Preferred context/domain.
	 * @return array{id:int,context:string,name:string,value:string}|null
	 */
	private static function lookup_icl_string_by_value( $value, $domain = '' ) {
		global $wpdb;
		if ( '' === $value ) {
			return null;
		}
		$primed = self::primed_rows( $value );
		if ( null !== $primed ) {
			foreach ( $primed as $row ) {
				if ( '' !== $domain && 0 === strcasecmp( $row['context'], $domain ) ) {
					return $row;
				}
			}
			return $primed ? $primed[0] : null;
		}
		$table = $wpdb->prefix . 'icl_strings';
		if ( '' !== $domain ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, context, name, value, gettext_context FROM {$table} WHERE value = %s AND context = %s LIMIT 1",
					$value,
					$domain
				),
				ARRAY_A
			);
			if ( is_array( $row ) ) {
				return $row;
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, context, name, value, gettext_context FROM {$table} WHERE value = %s LIMIT 1",
				$value
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Translation status for one ST string in the current language.
	 *
	 * @param int    $string_id String ID.
	 * @param string $current   Current language.
	 * @param string $default_lang   Default language.
	 * @return array{translated:bool|null,label:string}
	 */
	public static function string_translation_status( $string_id, $current, $default_lang ) {
		global $wpdb;
		$string_id = (int) $string_id;
		if ( $string_id <= 0 ) {
			return array(
				'translated' => null,
				'label'      => __( 'Could not be identified', 'wpml-translation-check' ),
				'status'     => 0,
				'value'      => '',
				'date'       => '',
			);
		}
		if ( '' === $current || $current === $default_lang ) {
			return array(
				'translated' => null,
				'label'      => __( 'Original language', 'wpml-translation-check' ),
				'status'     => 0,
				'value'      => '',
				'date'       => '',
			);
		}

		if ( isset( self::$cache['status'][ $current ] ) && array_key_exists( $string_id, self::$cache['status'][ $current ] ) ) {
			$row = self::$cache['status'][ $current ][ $string_id ];
		} else {
			$table = $wpdb->prefix . 'icl_string_translations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix., WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only ST lookup; table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status, value, translation_date FROM {$table} WHERE string_id = %d AND language = %s LIMIT 1",
					$string_id,
					$current
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $row ) ) {
			return array(
				'translated' => false,
				/* translators: %s: language name, e.g. Hindi */
				'label'      => sprintf( __( 'Not translated in %s', 'wpml-translation-check' ), self::language_name( $current ) ),
				'status'     => 0,
				'value'      => '',
				'date'       => '',
			);
		}

		// WPML ST: status 10 = complete, 1 = not translated, 2 = needs update, etc.
		$status = isset( $row['status'] ) ? (int) $row['status'] : 0;
		$value  = isset( $row['value'] ) ? (string) $row['value'] : '';
		$raw    = array(
			'status' => $status,
			'value'  => $value,
			'date'   => isset( $row['translation_date'] ) ? (string) $row['translation_date'] : '',
		);
		if ( 10 === $status && '' !== $value ) {
			return array(
				'translated' => true,
				/* translators: %s: language name, e.g. Hindi */
				'label'      => sprintf( __( 'Translated in %s', 'wpml-translation-check' ), self::language_name( $current ) ),
			) + $raw;
		}
		if ( '' === $value || 1 === $status ) {
			return array(
				'translated' => false,
				/* translators: %s: language name, e.g. Hindi */
				'label'      => sprintf( __( 'Not translated in %s', 'wpml-translation-check' ), self::language_name( $current ) ),
			) + $raw;
		}
		return array(
			'translated' => false,
			/* translators: %s: language name, e.g. Hindi */
			'label'      => sprintf( __( 'Translation in %s is not complete', 'wpml-translation-check' ), self::language_name( $current ) ),
		) + $raw;
	}

	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/**
	 * String Translation admin URL filtered to one string when possible.
	 *
	 * WPML ST supports search + context + em (exact match). When string_id is
	 * known we look up the exact icl_strings.value so the table shows one row.
	 *
	 * @param string $search    Search string (fallback).
	 * @param string $context   Optional ST context / domain.
	 * @param int    $string_id Optional icl_strings.id.
	 * @return string
	 */
	public static function st_search_url( $search, $context = '', $string_id = 0 ) {
		$string_id = (int) $string_id;
		if ( $string_id > 0 ) {
			$row = self::lookup_icl_string_by_id( $string_id );
			if ( $row ) {
				$search  = (string) $row['value'];
				$context = (string) $row['context'];
			}
		}

		$page = defined( 'WPML_ST_MENU_URL' )
			? WPML_ST_MENU_URL
			: 'wpml-string-translation/menu/string-translation.php';

		// WPML_ST_MENU_URL may already include "&tab=strings".
		$url = admin_url( 'admin.php?page=' . $page );

		$args = array(
			'search' => (string) $search,
			'em'     => 1,
		);
		if ( '' !== $context ) {
			$args['context'] = $context;
		}

		/*
		 * add_query_arg() leaves newly added values unencoded, and esc_url_raw()
		 * strips HTML from the search text. Encode explicitly so placeholders
		 * like %1$s and quoted attributes survive and WPML exact-match (em=1)
		 * receives the real icl_strings.value.
		 */
		$join = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
		return $url . $join . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Read-only icl_strings row by id.
	 *
	 * @param int $string_id String ID.
	 * @return array{id:int,context:string,name:string,value:string}|null
	 */
	private static function lookup_icl_string_by_id( $string_id ) {
		global $wpdb;
		$string_id = (int) $string_id;
		if ( $string_id <= 0 ) {
			return null;
		}
		foreach ( self::$cache['values'] as $rows ) {
			foreach ( (array) $rows as $row ) {
				if ( isset( $row['id'] ) && (int) $row['id'] === $string_id ) {
					return $row;
				}
			}
		}
		$table = $wpdb->prefix . 'icl_strings';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only lookup by primary key.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				"SELECT id, context, name, value FROM {$table} WHERE id = %d LIMIT 1",
				$string_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Gap 3/7: text inserted by JavaScript (Woo Blocks, React UIs, snippets).
	 *
	 * @param string               $text         Visible text.
	 * @param array<string, mixed> $ctx          Page context (scripts, inline).
	 * @param string               $current      Current language.
	 * @param string               $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_js( $text, array $ctx, $current, $default_lang ) {
		$scripts = isset( $ctx['scripts'] ) && is_array( $ctx['scripts'] ) ? $ctx['scripts'] : array();
		$inline  = isset( $ctx['inline'] ) && is_array( $ctx['inline'] ) ? $ctx['inline'] : array();
		$domains = array();
		foreach ( $scripts as $script ) {
			if ( '' !== $script['d'] ) {
				$domains[ $script['d'] ] = true;
			}
		}

		// 1) String Translation: JED contexts "{domain}-{handle}" first, then any page domain, then any row.
		$rows = self::rows_for_value( $text );
		if ( ! $rows && '' !== $current && $current !== $default_lang ) {
			$tr = self::lookup_by_translation( $text, $current );
			if ( $tr ) {
				$rows = array( $tr );
			}
		}
		$best  = null;
		$score = -1;
		foreach ( $rows as $row ) {
			$c = (string) $row['context'];
			$s = 0;
			foreach ( $scripts as $script ) {
				if ( '' !== $script['d'] && $c === $script['d'] . '-' . $script['h'] ) {
					$s = 3;
				}
			}
			if ( $s < 3 && isset( $domains[ $c ] ) ) {
				$s = 2;
			}
			if ( $s < 2 && 0 !== strpos( $c, 'admin_texts_' ) ) {
				$s = 1;
			}
			if ( $s > $score ) {
				$best  = $row;
				$score = $s;
			}
		}

		// A Gutenberg/Elementor package for the current document is post/page
		// content, not script text: route it to the correct origin and Fix.
		if ( $best ) {
			$package = self::match_builder_package( (string) $best['context'] );
			if ( $package ) {
				return self::resolve_builder_package( $text, $package, $current, $default_lang );
			}
		}

		$evidence = JS_Strings::locate( $text, $scripts, $inline, $current, $default_lang );
		$domain   = isset( $evidence['domain'] ) ? (string) $evidence['domain'] : '';
		if ( '' === $domain && $best ) {
			$domain = (string) $best['context'];
		}
		$handle = isset( $evidence['handle'] ) ? (string) $evidence['handle'] : '';
		$owner  = Component_Registry::for_domain( $domain );
		if ( ! $owner && ! empty( $evidence['src'] ) ) {
			$owner = Component_Registry::for_path( (string) $evidence['src'] );
		}
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;

		if ( ! $best && ! $evidence ) {
			$res                      = self::not_found(
				$text,
				$domains
					? __( 'Added by a script after the page loaded. It was not found in String Translation, so it may come from an outside service.', 'wpml-translation-check' )
					: __( 'Added by a script after the page loaded. It was not found in String Translation.', 'wpml-translation-check' )
			);
			$res['source_type']       = 'js';
			$res['source_type_label'] = __( 'Added by a script', 'wpml-translation-check' );
			$res['links']             = array(
				array(
					'label' => __( 'Theme and plugins localization', 'wpml-translation-check' ),
					'url'   => Component_Registry::localization_url(),
				),
			);
			return $res;
		}

		$label = __( 'Script text', 'wpml-translation-check' );
		$res   = self::result(
			array(
				'source_type'       => 'js',
				'source_type_label' => $owner ? sprintf( /* translators: %s: component */ __( 'Script text — %s', 'wpml-translation-check' ), Component_Registry::name( $owner ) ) : $label,
				'identifiers'       => array_filter(
					array(
						'domain'      => $domain,
						'component'   => Component_Registry::label( $owner ),
						'script'      => $handle,
						'script_file' => isset( $evidence['src'] ) ? (string) $evidence['src'] : '',
						'json_file'   => isset( $evidence['file'] ) ? (string) $evidence['file'] : '',
						'found_via'   => isset( $evidence['via'] ) ? (string) $evidence['via'] : 'string-translation',
						'pot_refs'    => ! empty( $evidence['refs'] ) ? implode( ', ', (array) $evidence['refs'] ) : '',
						'original'    => isset( $evidence['original'] ) && $evidence['original'] !== $text ? (string) $evidence['original'] : '',
					)
				),
				'translated'        => isset( $evidence['translated'] ) ? $evidence['translated'] : null,
				'status_label'      => '',
				'text'              => $text,
				'links'             => array(
					array(
						'label' => __( 'Theme and plugins localization', 'wpml-translation-check' ),
						'url'   => Component_Registry::localization_url(),
					),
				),
			)
		);

		if ( $best ) {
			$st                               = self::string_translation_status( (int) $best['id'], $current, $default_lang );
			$res['translated']                = $st['translated'];
			$res['status_label']              = $st['label'];
			$res['fix_url']                   = self::st_search_url( (string) $best['value'], (string) $best['context'], (int) $best['id'] );
			$res['fix_label']                 = __( 'Open in String Translation', 'wpml-translation-check' );
			$res['identifiers']['string_id']  = (int) $best['id'];
			$res['identifiers']['st_context'] = (string) $best['context'];
			if ( $secondary && '' !== $st['value'] ) {
				$res = self::apply_mismatch( $res, $text, (string) $best['value'], $best, $st, (string) $best['context'], '', $current );
			}
			return self::attach_value_candidates( $res, (string) $best['value'], (int) $best['id'], '', $current, $default_lang );
		}

		$original = isset( $evidence['original'] ) ? (string) $evidence['original'] : $text;
		if ( 'json' === ( isset( $evidence['via'] ) ? $evidence['via'] : '' ) ) {
			$json_tr = isset( $evidence['translation'] ) ? Origin_Collector::normalize( (string) $evidence['translation'] ) : '';
			if ( ! empty( $evidence['translated'] ) ) {
				$res['status_label'] = __( 'Translated by a language file', 'wpml-translation-check' );
			} elseif ( $secondary && '' !== $json_tr && $json_tr !== $text ) {
				$res['status_label']                  = __( 'A translation exists, but the page shows the original', 'wpml-translation-check' );
				$res['identifiers']['st_translation'] = $json_tr;
				$res['reasons']                       = array(
					__( 'The script did not load its translations on this page. A cache or optimization plugin may be serving an old copy.', 'wpml-translation-check' ),
				);
			} else {
				$res['status_label'] = __( 'The language file has no translation for this text', 'wpml-translation-check' );
			}
		} else {
			$res['status_label'] = $secondary
				? __( 'Not translated in this language', 'wpml-translation-check' )
				: __( 'Not in String Translation', 'wpml-translation-check' );
		}
		// WPML translates script strings under context "{domain}-{handle}" and writes JED files for them.
		$st_context       = ( '' !== $domain && '' !== $handle && ! empty( $evidence['domain'] ) ) ? $domain . '-' . $handle : $domain;
		$res['fix_url']   = Component_Registry::localization_url();
		$res['fix_label'] = __( 'Scan in Theme and plugins localization', 'wpml-translation-check' );
		$via              = isset( $evidence['via'] ) ? (string) $evidence['via'] : '';
		if ( 'inline' === $via ) {
			// Literal in an inline <script> (wp_add_inline_script / wp_localize_script / snippet): no gettext call to register.
			$res['status_label'] = __( 'Written directly in a script, so WPML cannot translate it', 'wpml-translation-check' );
			$res['translated']   = $secondary ? false : null;
			$res['message']      = __( 'Ask your developer to make this text translatable, then translate it in String Translation.', 'wpml-translation-check' );
			return $res;
		}
		if ( '' !== $st_context && Component_Registry::has_st() ) {
			$res['actions'] = array( self::register_action( $st_context, $original ) );
			$res['message'] = ! empty( $evidence['translated'] )
				? sprintf(
					/* translators: 1: JSON file, 2: ST context */
					__( 'Translated by %1$s. To manage it in WPML, add it to String Translation.', 'wpml-translation-check' ),
					isset( $evidence['file'] ) ? (string) $evidence['file'] : __( 'a language file', 'wpml-translation-check' ),
					$st_context
				)
				: __( 'Add it to String Translation to translate it.', 'wpml-translation-check' );
		}
		return $res;
	}

	/**
	 * Gap 6: text printed directly by a theme/plugin template (not gettext, not stored data).
	 *
	 * The removed file locator is NOT used: only the template region + text domain are reported.
	 *
	 * @param string               $text         Visible text.
	 * @param array<string, mixed> $region       Region: td, file, label.
	 * @param string               $current      Current language.
	 * @param string               $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function resolve_hardcoded( $text, array $region, $current, $default_lang ) {
		$td    = isset( $region['td'] ) ? (string) $region['td'] : '';
		$file  = isset( $region['file'] ) ? (string) $region['file'] : '';
		$owner = '' !== $td ? Component_Registry::for_domain( $td ) : array();
		if ( ! $owner && '' !== $file ) {
			$owner = Component_Registry::for_path( $file );
		}
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$name      = $owner ? Component_Registry::name( $owner ) : __( 'the theme', 'wpml-translation-check' );
		return self::result(
			array(
				'source_type'       => 'hardcoded',
				'source_type_label' => sprintf( /* translators: %s: component */ __( 'Built into %s', 'wpml-translation-check' ), $name ),
				'identifiers'       => array_filter(
					array(
						'component'       => Component_Registry::label( $owner ),
						'domain'          => '' !== $td ? $td : ( isset( $owner['domain'] ) ? $owner['domain'] : '' ),
						'template'        => $file,
						'wrapped_gettext' => 'no',
					)
				),
				'translated'        => $secondary ? false : null,
				'status_label'      => __( 'Not translatable with WPML as written', 'wpml-translation-check' ),
				'fix_url'           => Component_Registry::localization_url(),
				'fix_label'         => __( 'Scan theme in Theme and plugins localization', 'wpml-translation-check' ),
				'message'           => __( 'This text is written directly in the theme or plugin files. Ask your developer to make it translatable, then scan it in Theme and plugins localization.', 'wpml-translation-check' ),
				'text'              => $text,
			)
		);
	}
}
