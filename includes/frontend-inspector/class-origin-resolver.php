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
		'batch'  => false,
		'values' => array(),
		'admin'  => array(),
		'status' => array(),
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
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT string_id, status, value FROM {$trans} WHERE language = %s AND string_id IN ({$in})", array_merge( array( $current ), $ids ) ), ARRAY_A );
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
	 * @return array<string, mixed>
	 */
	public static function resolve( $text, array $hint ) {
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
		}

		// No collector hint: try exact-value matches only (never fuzzy guess).
		$by_value = self::resolve_by_exact_value( $text, $current, $default_lang );
		if ( $by_value ) {
			return $by_value;
		}

		return self::not_found(
			$text,
			__( 'No origin hint was available for this text.', 'wpml-translation-check' )
		);
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
			'status_label'      => __( 'Unknown', 'wpml-translation-check' ),
			'fix_url'           => '',
			'fix_label'         => '',
			'message'           => $hint_text,
			'text'              => $text_or_msg,
		);
	}

	/**
	 * Exact-value fallback when the light map hint was missing from the AJAX request.
	 *
	 * Only accepts exact string equality against theme_mods, Admin Texts option values,
	 * and icl_strings.value — never fuzzy matching.
	 *
	 * @param string $text    Normalized visible text.
	 * @param string $current Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_by_exact_value( $text, $current, $default_lang ) {
		if ( '' === $text ) {
			return null;
		}

		$mods = get_theme_mods();
		if ( is_array( $mods ) ) {
			foreach ( $mods as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				$hit = self::find_exact_leaf( $value, $text, '' );
				if ( null !== $hit ) {
					return self::resolve_option(
						$text,
						array(
							'k' => 'theme_mod',
							'n' => (string) $key,
							'p' => $hit,
						),
						$current,
						$default_lang,
						true
					);
				}
			}
		}

		$registry = get_option( '_icl_admin_option_names', array() );
		if ( is_array( $registry ) ) {
			foreach ( $registry as $option_name => $enabled ) {
				if ( ! is_string( $option_name ) || '' === $option_name ) {
					continue;
				}
				$val = get_option( $option_name, null );
				if ( null === $val ) {
					continue;
				}
				$hit = self::find_exact_leaf( $val, $text, '' );
				if ( null !== $hit ) {
					return self::resolve_option(
						$text,
						array(
							'k' => 'option',
							'n' => $option_name,
							'p' => $hit,
						),
						$current,
						$default_lang,
						false
					);
				}
			}
		}

		// Exact option string match (covers unregistered non-autoload options). Skipped in batch scans.
		global $wpdb;
		if ( self::$cache['batch'] ) {
			$st = self::lookup_icl_string_by_value( $text, '' );
			return $st ? self::resolve_gettext(
				$text,
				array(
					'k' => 'gettext',
					'd' => (string) $st['context'],
					'o' => (string) $st['value'],
				),
				$current,
				$default_lang
			) : null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix. -- read-only exact match for inspector.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value = %s LIMIT 1",
				$text
			),
			ARRAY_A
		);
		if ( is_array( $row ) && ! empty( $row['option_name'] ) ) {
			$name = (string) $row['option_name'];
			if ( 0 !== strpos( $name, '_transient' ) && 0 !== strpos( $name, '_site_transient' ) ) {
				return self::resolve_option(
					$text,
					array(
						'k' => 'option',
						'n' => $name,
						'p' => '',
					),
					$current,
					$default_lang,
					false
				);
			}
		}

		$st = self::lookup_icl_string_by_value( $text, '' );
		if ( $st ) {
			return self::resolve_gettext(
				$text,
				array(
					'k' => 'gettext',
					'd' => isset( $st['context'] ) ? (string) $st['context'] : '',
					'o' => isset( $st['value'] ) ? (string) $st['value'] : $text,
					'c' => '',
				),
				$current,
				$default_lang
			);
		}

		return null;
	}

	/**
	 * Find bracket path of an exact string leaf, or null.
	 *
	 * @param mixed  $data Value.
	 * @param string $text Needle.
	 * @param string $path Path so far.
	 * @return string|null Empty string for root string match; bracket path otherwise; null if none.
	 */
	private static function find_exact_leaf( $data, $text, $path ) {
		if ( is_string( $data ) ) {
			return ( Origin_Collector::normalize( $data ) === $text ) ? $path : null;
		}
		if ( ! is_array( $data ) ) {
			return null;
		}
		$count = 0;
		foreach ( $data as $key => $child ) {
			if ( $count++ > 80 ) {
				break;
			}
			$hit = self::find_exact_leaf( $child, $text, $path . '[' . $key . ']' );
			if ( null !== $hit ) {
				return $hit;
			}
		}
		return null;
	}

	/**
	 * Resolve a gettext string via public ST APIs / read-only icl_strings.
	 *
	 * @param string               $text    Visible text.
	 * @param array<string, mixed> $hint    Hint.
	 * @param string               $current Current lang.
	 * @param string               $default_lang Default lang.
	 * @return array<string, mixed>
	 */
	private static function resolve_gettext( $text, array $hint, $current, $default_lang ) {
		$domain   = isset( $hint['d'] ) ? (string) $hint['d'] : '';
		$original = isset( $hint['o'] ) ? (string) $hint['o'] : $text;
		$context  = isset( $hint['c'] ) ? (string) $hint['c'] : '';

		$row = self::lookup_icl_string( $domain, $original, $context );
		if ( ! $row ) {
			// Domain in WPML ST is often stored as context column.
			$row = self::lookup_icl_string_by_value( $original, $domain );
		}

		$translated   = null;
		$status_label = __( 'Not in String Translation', 'wpml-translation-check' );
		if ( $row ) {
			$st           = self::string_translation_status( (int) $row['id'], $current, $default_lang );
			$translated   = $st['translated'];
			$status_label = $st['label'];
		}
		if ( $current !== $default_lang && $text !== $original ) {
			// Visible text differs from the source string: a translation is being applied (.mo or ST).
			$translated   = true;
			$status_label = __( 'Translated via gettext', 'wpml-translation-check' );
		} elseif ( ! $row && $current !== $default_lang ) {
			$translated   = false;
			$status_label = __( 'Appears untranslated (matches original)', 'wpml-translation-check' );
		} elseif ( $current !== $default_lang && $text !== $original ) {
			$translated   = true;
			$status_label = __( 'Translated via gettext', 'wpml-translation-check' );
		}

		$fix_url = self::st_search_url( $original, $domain );

		return array(
			'found'             => true,
			'source_type'       => 'gettext',
			'source_type_label' => __( 'Gettext string', 'wpml-translation-check' ),
			'identifiers'       => array_filter(
				array(
					'domain'     => $domain,
					'original'   => $original,
					'context'    => $context,
					'string_id'  => $row ? (int) $row['id'] : null,
					'st_context' => $row ? (string) $row['context'] : null,
				)
			),
			'translated'        => $translated,
			'status_label'      => $status_label,
			'fix_url'           => $fix_url,
			'fix_label'         => __( 'Open in String Translation', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
		);
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
				__( 'Option name was not captured for this text.', 'wpml-translation-check' )
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
			$status_label = __( 'Registered in Admin Texts (string row not found)', 'wpml-translation-check' );
		} elseif ( '' === $current || $current === $default_lang ) {
			$status_label = __( 'Original language (not registered in Admin Texts)', 'wpml-translation-check' );
		} else {
			$translated   = false;
			$status_label = __( 'Not registered in Admin Texts', 'wpml-translation-check' );
		}

		if ( $registered && $st_row ) {
			$fix_url   = self::st_search_url( isset( $st_row['value'] ) ? (string) $st_row['value'] : $text, (string) $st_row['context'] );
			$fix_label = __( 'Open in String Translation', 'wpml-translation-check' );
		} else {
			$fix_url   = admin_url( 'admin.php?page=wpml-admin-texts-translation' );
			$fix_label = __( 'Open Admin Texts', 'wpml-translation-check' );
		}

		$type_label = $is_theme_mod
			? __( 'Theme mod', 'wpml-translation-check' )
			: __( 'Option', 'wpml-translation-check' );

		if ( $registered ) {
			$type_label = $is_theme_mod
				? __( 'Theme mod (Admin Texts)', 'wpml-translation-check' )
				: __( 'Admin Text option', 'wpml-translation-check' );
		}

		return array(
			'found'             => true,
			'source_type'       => $is_theme_mod ? 'theme_mod' : 'option',
			'source_type_label' => $type_label,
			'identifiers'       => array_filter(
				array(
					'name'       => $name,
					'path'       => $path,
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
	 * @return array<string, mixed>
	 */
	private static function resolve_post( $text, array $hint, $current, $default_lang, $kind ) {
		$post_id = isset( $hint['id'] ) ? absint( $hint['id'] ) : 0;
		if ( $post_id <= 0 ) {
			return self::not_found( __( 'Post ID was not captured for this text.', 'wpml-translation-check' ) );
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
		$status_label = __( 'Post language: ', 'wpml-translation-check' ) . $lang;

		if ( $lang === $default_lang ) {
			$translated   = null;
			$status_label = __( 'Original-language post', 'wpml-translation-check' );
		} elseif ( $source_id && $source_id !== $post_id ) {
			// Compare field to source post field when available.
			$source_post = get_post( $source_id );
			if ( $source_post instanceof \WP_Post ) {
				$src_val = ( 'post_title' === $kind ) ? $source_post->post_title : wp_strip_all_tags( $source_post->post_content );
				$cur_val = ( 'post_title' === $kind ) ? $post->post_title : wp_strip_all_tags( $post->post_content );
				$src_n   = Origin_Collector::normalize( $src_val );
				$cur_n   = Origin_Collector::normalize( $cur_val );
				$text_n  = Origin_Collector::normalize( $text );
				if ( 'post_content' === $kind && '' !== $text_n && false === strpos( $src_n, $text_n ) ) {
					// Text is not authored in the source post: it is rendered by a shortcode/block (e.g. gettext).
					return self::resolve_dynamic_post_text( $text, $post_id, $current, $default_lang );
				}
				if ( '' !== $src_n && $src_n === $cur_n ) {
					$translated   = false;
					$status_label = __( 'Matches original-language post (likely untranslated)', 'wpml-translation-check' );
				} else {
					$translated   = true;
					$status_label = __( 'Differs from original-language post', 'wpml-translation-check' );
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

		$label = ( 'post_title' === $kind )
			? __( 'Post title', 'wpml-translation-check' )
			: __( 'Post content', 'wpml-translation-check' );

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
			return self::resolve_gettext( $text, array( 'd' => (string) $orig['context'] ), $current, $default_lang );
		}
		$edit_url = get_edit_post_link( $post_id, 'raw' );
		return array(
			'found'             => true,
			'source_type'       => 'post_content',
			'source_type_label' => __( 'Post content', 'wpml-translation-check' ),
			'identifiers'       => array( 'post_id' => $post_id ),
			'translated'        => null,
			'status_label'      => __( 'Rendered dynamically (not authored in the original-language post)', 'wpml-translation-check' ),
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
			return self::not_found( __( 'Menu item ID was not captured.', 'wpml-translation-check' ) );
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
				/* translators: %s: language code */
				__( 'Menu item language: %s', 'wpml-translation-check' ),
				$lang
			)
			: __( 'Menu item', 'wpml-translation-check' );

		if ( '' !== $lang && '' !== $default_lang && $lang === $default_lang && $current !== $default_lang ) {
			$translated   = false;
			$status_label = __( 'Original-language menu item shown on translated page', 'wpml-translation-check' );
		}

		$fix_url = admin_url( 'nav-menus.php' );

		return array(
			'found'             => true,
			'source_type'       => 'nav_menu_item',
			'source_type_label' => __( 'Navigation menu item', 'wpml-translation-check' ),
			'identifiers'       => array_filter(
				array(
					'menu_item_id' => $item_id,
					'title'        => $item->post_title,
					'language'     => $lang ? $lang : null,
				)
			),
			'translated'        => $translated,
			'status_label'      => $status_label,
			'fix_url'           => $fix_url,
			'fix_label'         => __( 'Open Menus screen', 'wpml-translation-check' ),
			'message'           => '',
			'text'              => $text,
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
				$fix_url   = self::st_search_url( $text, 'Widgets' );
				$fix_label = __( 'Translate in String Translation', 'wpml-translation-check' );
			}
		} elseif ( $secondary ) {
			// Shown in the default-language value and not registered in ST: cannot be translated yet.
			$translated   = false;
			$status_label = __( 'Widget text not registered in String Translation', 'wpml-translation-check' );
		}

		return array(
			'found'             => true,
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
			'message'           => '',
			'text'              => $text,
		);
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
					/* translators: %s: language code */
					__( 'Elementor document language: %s', 'wpml-translation-check' ),
					$doc_lang
				);
			}

			if ( $secondary && '' !== $doc_lang ) {
				if ( $doc_lang === $default_lang ) {
					$translated   = false;
					$status_label = __( 'Default-language Elementor document shown on translated page', 'wpml-translation-check' );
				} else {
					$source_id = (int) apply_filters( 'wpml_object_id', $did, $post_type, false, $default_lang );
					if ( $source_id > 0 && $source_id !== $did ) {
						if ( self::elementor_document_has_text( $source_id, $text ) ) {
							$translated      = false;
							$status_label    = __( 'Same text as the default-language version', 'wpml-translation-check' );
							$same_as_default = true;
						} else {
							$translated   = true;
							$status_label = __( 'Differs from the default-language version', 'wpml-translation-check' );
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
					"SELECT id, context, name, value FROM {$table} WHERE context = %s AND value = %s AND gettext_context = %s LIMIT 1",
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
					"SELECT id, context, name, value FROM {$table} WHERE context = %s AND value = %s LIMIT 1",
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
					"SELECT id, context, name, value FROM {$table} WHERE value = %s AND context = %s LIMIT 1",
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
				"SELECT id, context, name, value FROM {$table} WHERE value = %s LIMIT 1",
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
	private static function string_translation_status( $string_id, $current, $default_lang ) {
		global $wpdb;
		$string_id = (int) $string_id;
		if ( $string_id <= 0 ) {
			return array(
				'translated' => null,
				'label'      => __( 'Unknown', 'wpml-translation-check' ),
			);
		}
		if ( '' === $current || $current === $default_lang ) {
			return array(
				'translated' => null,
				'label'      => __( 'Original language', 'wpml-translation-check' ),
			);
		}

		if ( isset( self::$cache['status'][ $current ] ) && array_key_exists( $string_id, self::$cache['status'][ $current ] ) ) {
			$row = self::$cache['status'][ $current ][ $string_id ];
		} else {
			$table = $wpdb->prefix . 'icl_string_translations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from `$wpdb->prefix., WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only ST lookup; table name from `$wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status, value FROM {$table} WHERE string_id = %d AND language = %s LIMIT 1",
					$string_id,
					$current
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $row ) ) {
			return array(
				'translated' => false,
				'label'      => __( 'Missing translation in current language', 'wpml-translation-check' ),
			);
		}

		// WPML ST: status 10 = complete, 1 = not translated, 2 = needs update, etc.
		$status = isset( $row['status'] ) ? (int) $row['status'] : 0;
		$value  = isset( $row['value'] ) ? (string) $row['value'] : '';
		if ( 10 === $status && '' !== $value ) {
			return array(
				'translated' => true,
				'label'      => __( 'Translated in current language', 'wpml-translation-check' ),
			);
		}
		if ( '' === $value || 1 === $status ) {
			return array(
				'translated' => false,
				'label'      => __( 'Not translated in current language', 'wpml-translation-check' ),
			);
		}
		return array(
			'translated' => false,
			'label'      => sprintf(
				/* translators: %d: WPML string translation status code */
				__( 'Translation status code: %d', 'wpml-translation-check' ),
				$status
			),
		);
	}

	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/**
	 * String Translation admin URL filtered by search text.
	 *
	 * @param string $search  Search string.
	 * @param string $context Optional ST context.
	 * @return string
	 */
	private static function st_search_url( $search, $context = '' ) {
		$args = array(
			'page'   => 'wpml-string-translation/menu/string-translation.php',
			'search' => $search,
		);
		if ( '' !== $context ) {
			$args['context'] = $context;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
