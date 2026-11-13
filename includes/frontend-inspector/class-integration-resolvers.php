<?php
/**
 * Resolvers for page builders (Divi, WPBakery, Bricks), ACF / custom fields,
 * forms (Contact Form 7, WPForms, Gravity Forms) and WooCommerce emails.
 *
 * Each integration is skipped when its plugin/theme is absent. Data lookups are
 * read-only and bounded.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration_Resolvers
 */
class Integration_Resolvers {

	/**
	 * Builder ids => labels.
	 *
	 * @return array<string, string>
	 */
	public static function builders() {
		return array(
			'divi'     => 'Divi',
			'wpbakery' => 'WPBakery',
			'bricks'   => 'Bricks',
		);
	}

	/**
	 * Whether a builder is active on this site.
	 *
	 * @param string $builder divi|wpbakery|bricks.
	 * @return bool
	 */
	public static function builder_active( $builder ) {
		switch ( $builder ) {
			case 'divi':
				return defined( 'ET_BUILDER_VERSION' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' ) || function_exists( 'et_setup_theme' ) || 'Divi' === get_template();
			case 'wpbakery':
				return defined( 'WPB_VC_VERSION' ) || function_exists( 'vc_map' );
			case 'bricks':
				return defined( 'BRICKS_VERSION' ) || 'bricks' === get_template();
		}
		return false;
	}

	/**
	 * Builder for a shortcode tag or block name, or ''.
	 *
	 * @param string $tag Shortcode tag / block name.
	 * @return string
	 */
	public static function builder_for_tag( $tag ) {
		$tag = (string) $tag;
		if ( 0 === strpos( $tag, 'et_pb_' ) || 0 === strpos( $tag, 'divi/' ) ) {
			return 'divi';
		}
		if ( 0 === strpos( $tag, 'vc_' ) ) {
			return 'wpbakery';
		}
		return '';
	}

	/**
	 * Builder-aware edit link for a post.
	 *
	 * @param string $builder Builder.
	 * @param int    $post_id Post ID.
	 * @param string $eid     Element id (Bricks) when known.
	 * @return string
	 */
	public static function builder_edit_url( $builder, $post_id, $eid = '' ) {
		$post_id = (int) $post_id;
		$classic = (string) get_edit_post_link( $post_id, 'raw' );
		if ( '' === $classic ) {
			$classic = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		}
		if ( ! self::builder_active( $builder ) ) {
			return $classic;
		}
		$permalink = (string) get_permalink( $post_id );
		switch ( $builder ) {
			case 'divi':
				return '' !== $permalink ? add_query_arg( 'et_fb', '1', $permalink ) : $classic;
			case 'wpbakery':
				return add_query_arg(
					array(
						'vc_action' => 'vc_inline',
						'post_id'   => $post_id,
						'post_type' => (string) get_post_type( $post_id ),
					),
					admin_url( 'post.php' )
				);
			case 'bricks':
				$url = '' !== $permalink ? add_query_arg( 'bricks', 'run', $permalink ) : $classic;
				return '' !== $eid ? $url . '#brxe-' . rawurlencode( $eid ) : $url;
		}
		return $classic;
	}

	/**
	 * Document-order position of the module (nth [tag] in the post) whose text contains the value.
	 *
	 * @param string $content  Post content.
	 * @param string $tag      Shortcode tag.
	 * @param string $text     Clicked text.
	 * @param int    $fallback Index from the nested search.
	 * @return int
	 */
	private static function tag_ordinal( $content, $tag, $text, $fallback ) {
		if ( ! preg_match( '/^[a-z0-9_]+$/', $tag ) ) {
			return $fallback;
		}
		if ( ! preg_match_all( '/\[' . $tag . '(?=[\s\]])[^\]]*\](.*?)\[\/' . $tag . '\]/s', $content, $m ) ) {
			return $fallback;
		}
		foreach ( $m[1] as $i => $inner ) {
			$plain = (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) preg_replace( '/\[[^\]]*\]/', ' ', (string) $inner ) ) );
			if ( false !== strpos( $plain, $text ) ) {
				return $i + 1;
			}
		}
		return $fallback;
	}

	/**
	 * Find text inside Divi / WPBakery shortcodes or Divi 5 blocks of a post.
	 *
	 * @param string $content Post content.
	 * @param string $text    Normalized text.
	 * @param string $builder divi|wpbakery.
	 * @param int    $depth   Nesting depth (bounded).
	 * @return array{tag:string,index:int}|null
	 */
	private static function find_in_shortcodes( $content, $text, $builder, $depth = 0 ) {
		$pattern = 'divi' === $builder
			? '/\[(et_pb_[a-z0-9_]+)([^\]]*)\](.*?)\[\/\1\]|<!--\s+wp:(divi\/[a-z0-9\-]+)\s+(\{.*?\})\s+\/?-->/s'
			: '/\[(vc_[a-z0-9_]+)([^\]]*)\](.*?)\[\/\1\]/s';
		if ( ! preg_match_all( $pattern, $content, $m, PREG_SET_ORDER ) ) {
			return null;
		}
		$index = 0;
		foreach ( $m as $match ) {
			++$index;
			$tag = ! empty( $match[1] ) ? $match[1] : ( isset( $match[4] ) ? $match[4] : '' );
			// Report the innermost module (et_pb_text, vc_column_text), not the wrapping section/row.
			if ( $depth < 8 && ! empty( $match[3] ) && preg_match( 'divi' === $builder ? '/\[et_pb_/' : '/\[vc_/', $match[3] ) ) {
				$inner = self::find_in_shortcodes( $match[3], $text, $builder, $depth + 1 );
				if ( $inner ) {
					return $inner;
				}
			}
			$parts = array();
			if ( ! empty( $match[3] ) ) {
				$parts[] = (string) preg_replace( '/\[[^\]]*\]/', ' ', $match[3] );
			}
			$attrs = ! empty( $match[2] ) ? $match[2] : ( isset( $match[5] ) ? $match[5] : '' );
			if ( '' !== $attrs && preg_match_all( '/(?:="|":")((?:[^"\\\\]|\\\\.)*)"/', $attrs, $am ) ) {
				foreach ( $am[1] as $value ) {
					$parts[] = stripcslashes( (string) $value );
				}
			}
			foreach ( $parts as $part ) {
				$part = str_replace( array( '%22', '%91', '%93' ), array( '"', '[', ']' ), $part );
				foreach ( Option_Index::keys_for_leaf( $part ) as $key => $partial ) {
					if ( $key === $text ) {
						return array(
							'tag'   => $tag,
							'index' => $index,
						);
					}
				}
			}
		}
		return null;
	}

	/**
	 * Find text in Bricks page data (content, header, footer).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $text    Normalized text.
	 * @return array{id:string,name:string,area:string}|null
	 */
	private static function find_in_bricks( $post_id, $text ) {
		foreach ( array( 'content', 'header', 'footer' ) as $area ) {
			$elements = get_post_meta( (int) $post_id, '_bricks_page_' . $area . '_2', true );
			if ( ! is_array( $elements ) ) {
				continue;
			}
			foreach ( array_slice( $elements, 0, 600 ) as $element ) {
				if ( ! is_array( $element ) || empty( $element['settings'] ) ) {
					continue;
				}
				$found  = false;
				$budget = 400;
				Option_Index::walk(
					$element['settings'],
					'',
					static function ( $leaf ) use ( $text, &$found ) {
						if ( ! $found && array_key_exists( $text, Option_Index::keys_for_leaf( $leaf ) ) ) {
							$found = true;
						}
					},
					0,
					$budget
				);
				if ( $found ) {
					return array(
						'id'   => isset( $element['id'] ) ? (string) $element['id'] : '',
						'name' => isset( $element['name'] ) ? (string) $element['name'] : '',
						'area' => $area,
					);
				}
			}
		}
		return null;
	}

	/**
	 * Resolve builder text for a post (hint b + id, or a by-value probe of the given posts).
	 *
	 * @param string $text         Normalized text.
	 * @param string $builder      Builder id or '' for any.
	 * @param int[]  $post_ids     Posts to search (current document first).
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_builder( $text, $builder, array $post_ids, $current, $default_lang ) {
		$builders = '' !== $builder ? array( $builder ) : array_keys( self::builders() );
		foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			foreach ( $builders as $b ) {
				$hit = null;
				if ( 'bricks' === $b ) {
					$bricks = self::find_in_bricks( $post_id, $text );
					if ( $bricks ) {
						$hit = array(
							'element' => $bricks['name'] . ( '' !== $bricks['id'] ? ' #' . $bricks['id'] : '' ),
							'eid'     => $bricks['id'],
							'area'    => $bricks['area'],
						);
					}
				} else {
					$sc = self::find_in_shortcodes( (string) $post->post_content, $text, $b );
					if ( $sc ) {
						$sc['index'] = self::tag_ordinal( (string) $post->post_content, $sc['tag'], $text, $sc['index'] );
					}
					if ( $sc ) {
						$hit = array(
							'element' => $sc['tag'] . ' #' . $sc['index'],
							'eid'     => '',
							'area'    => 'content',
						);
					}
				}
				if ( ! $hit ) {
					continue;
				}
				return self::builder_result( $text, $b, $post, $hit, $current, $default_lang );
			}
		}
		return null;
	}

	/**
	 * Build the builder result row.
	 *
	 * @param string               $text         Text.
	 * @param string               $builder      Builder.
	 * @param \WP_Post             $post         Post.
	 * @param array<string, mixed> $hit          Element info.
	 * @param string               $current      Current language.
	 * @param string               $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function builder_result( $text, $builder, \WP_Post $post, array $hit, $current, $default_lang ) {
		$labels     = self::builders();
		$label      = isset( $labels[ $builder ] ) ? $labels[ $builder ] : $builder;
		$active     = self::builder_active( $builder );
		$details    = apply_filters(
			'wpml_element_language_details',
			null,
			array(
				'element_id'   => (int) $post->ID,
				'element_type' => 'post_' . $post->post_type,
			)
		);
		$lang       = ( is_object( $details ) && isset( $details->language_code ) ) ? (string) $details->language_code : '';
		$secondary  = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$translated = null;
		$status     = sprintf( /* translators: %s: builder name */ __( '%s module text', 'wpml-translation-check' ), $label );
		if ( $secondary && '' !== $lang ) {
			if ( $lang === $default_lang ) {
				$translated = false;
				$status     = __( 'Original-language page shown on a translated page', 'wpml-translation-check' );
			} else {
				$source_id = (int) apply_filters( 'wpml_object_id', (int) $post->ID, $post->post_type, false, $default_lang );
				if ( $source_id > 0 && $source_id !== (int) $post->ID ) {
					$same       = (bool) self::resolve_builder( $text, $builder, array( $source_id ), $default_lang, $default_lang );
					$translated = ! $same;
					$status     = $same
						? __( 'Same as the original', 'wpml-translation-check' )
						: __( 'Translated', 'wpml-translation-check' );
				}
			}
		}
		$links   = array();
		$tm_link = apply_filters( 'wpml_get_post_translation_url', null, (int) $post->ID );
		if ( is_string( $tm_link ) && '' !== $tm_link ) {
			$links[] = array(
				'label' => __( 'Translate this page (WPML)', 'wpml-translation-check' ),
				'url'   => $tm_link,
			);
		}
		$links[] = array(
			'label' => __( 'WPML Translation Management', 'wpml-translation-check' ),
			'url'   => Component_Registry::tm_dashboard_url(),
		);
		return Origin_Resolver::result(
			array(
				'source_type'       => 'builder_' . $builder,
				'source_type_label' => sprintf( /* translators: %s: builder name */ __( '%s module', 'wpml-translation-check' ), $label ),
				'identifiers'       => array_filter(
					array(
						'builder'        => $label,
						'post_id'        => (int) $post->ID,
						'element'        => $hit['element'],
						'area'           => 'content' !== $hit['area'] ? $hit['area'] : null,
						'language'       => $lang ? $lang : null,
						'builder_active' => $active ? 'yes' : 'no',
					)
				),
				'translated'        => $translated,
				'status_label'      => $status,
				'fix_url'           => self::builder_edit_url( $builder, (int) $post->ID, (string) $hit['eid'] ),
				'fix_label'         => $active
					? sprintf( /* translators: %s: builder name */ __( 'Edit in %s', 'wpml-translation-check' ), $label )
					: __( 'Edit post', 'wpml-translation-check' ),
				'message'           => $active ? '' : sprintf( /* translators: %s: builder name */ __( '%s is not active on this site. The saved content is shown.', 'wpml-translation-check' ), $label ),
				'links'             => $links,
				'text'              => $text,
			)
		);
	}

	/* ---------- ACF / custom fields ---------- */

	/**
	 * Whether ACF is available.
	 *
	 * @return bool
	 */
	public static function acf_active() {
		return function_exists( 'acf_get_field' );
	}

	/**
	 * ACFML translation preference label for a field.
	 *
	 * @param array<string, mixed> $field ACF field.
	 * @return string
	 */
	private static function acf_preference_label( array $field ) {
		if ( ! isset( $field['wpml_cf_preferences'] ) ) {
			return '';
		}
		$map  = array(
			0 => __( 'Don\'t translate', 'wpml-translation-check' ),
			1 => __( 'Copy', 'wpml-translation-check' ),
			2 => __( 'Translate', 'wpml-translation-check' ),
			3 => __( 'Copy once', 'wpml-translation-check' ),
		);
		$pref = (int) $field['wpml_cf_preferences'];
		return isset( $map[ $pref ] ) ? $map[ $pref ] : (string) $pref;
	}

	/**
	 * Resolve an ACF field value.
	 *
	 * @param string $text         Text.
	 * @param string $field_key    Field key (field_xxx) or name.
	 * @param string $object_id    ACF post_id (123, "option", "term_5", "user_2").
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_acf( $text, $field_key, $object_id, $current, $default_lang ) {
		if ( ! self::acf_active() || '' === (string) $field_key ) {
			return null;
		}
		$field = acf_get_field( $field_key );
		if ( ! is_array( $field ) ) {
			return null;
		}
		$group_id = 0;
		$parent   = isset( $field['parent'] ) ? $field['parent'] : 0;
		for ( $i = 0; $i < 5 && $parent; $i++ ) {
			$parent_post = is_numeric( $parent ) ? get_post( (int) $parent ) : null;
			if ( ! $parent_post && function_exists( 'acf_get_field_group' ) ) {
				$grp = acf_get_field_group( $parent );
				if ( is_array( $grp ) && ! empty( $grp['ID'] ) ) {
					$group_id = (int) $grp['ID'];
				}
				break;
			}
			if ( $parent_post instanceof \WP_Post && 'acf-field-group' === $parent_post->post_type ) {
				$group_id = (int) $parent_post->ID;
				break;
			}
			$parent = $parent_post instanceof \WP_Post ? $parent_post->post_parent : 0;
		}
		$secondary  = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$pref       = self::acf_preference_label( $field );
		$translated = null;
		$status     = '' !== $pref
			? sprintf( /* translators: %s: ACFML translation preference */ __( 'ACF field (set to: %s)', 'wpml-translation-check' ), $pref )
			: __( 'ACF field value', 'wpml-translation-check' );
		$links      = array();
		$fix_url    = '';
		$fix_label  = '';
		$object_id  = (string) $object_id;

		if ( is_numeric( $object_id ) && (int) $object_id > 0 ) {
			$post_id   = (int) $object_id;
			$post_type = (string) get_post_type( $post_id );
			$source_id = (int) apply_filters( 'wpml_object_id', $post_id, $post_type, false, $default_lang );
			if ( $secondary && $source_id > 0 ) {
				$src_val = get_post_meta( $source_id, (string) $field['name'], true );
				$same    = is_string( $src_val ) && Origin_Collector::normalize( $src_val ) === $text;
				if ( $source_id === $post_id || $same ) {
					$translated = false;
					$status     = isset( $field['wpml_cf_preferences'] ) && 1 === (int) $field['wpml_cf_preferences']
						? __( 'Copied from the original language', 'wpml-translation-check' )
						: __( 'Same as the original', 'wpml-translation-check' );
				} else {
					$translated = true;
					$status     = __( 'Translated', 'wpml-translation-check' );
				}
			}
			$tm_link   = apply_filters( 'wpml_get_post_translation_url', null, $post_id );
			$fix_url   = ( is_string( $tm_link ) && '' !== $tm_link ) ? $tm_link : (string) get_edit_post_link( $post_id, 'raw' );
			$fix_label = __( 'Edit post / translation', 'wpml-translation-check' );
		} elseif ( 0 === strpos( $object_id, 'term_' ) ) {
			$fix_url   = admin_url( 'term.php?tag_ID=' . absint( substr( $object_id, 5 ) ) );
			$fix_label = __( 'Edit category or tag', 'wpml-translation-check' );
		} else {
			$fix_url   = admin_url( 'admin.php?page=acf-options' );
			$fix_label = __( 'Open ACF options page', 'wpml-translation-check' );
			$links[]   = array(
				'label' => __( 'Translate the ACF options page', 'wpml-translation-check' ),
				'url'   => admin_url( 'edit.php?post_type=acf-field-group&page=acfml-options' ),
			);
		}
		if ( $group_id ) {
			$links[] = array(
				'label' => __( 'Edit field group', 'wpml-translation-check' ),
				'url'   => admin_url( 'post.php?post=' . $group_id . '&action=edit' ),
			);
		}
		$links[] = array(
			'label' => __( 'WPML custom field settings', 'wpml-translation-check' ),
			'url'   => admin_url( 'admin.php?page=tm/menu/settings#ml-content-setup-sec-cf' ),
		);
		return Origin_Resolver::result(
			array(
				'source_type'       => 'acf',
				'source_type_label' => sprintf( /* translators: %s: ACF field label */ __( 'ACF field: %s', 'wpml-translation-check' ), isset( $field['label'] ) ? wp_strip_all_tags( (string) $field['label'] ) : $field_key ),
				'identifiers'       => array_filter(
					array(
						'field_name'     => isset( $field['name'] ) ? (string) $field['name'] : null,
						'field_key'      => isset( $field['key'] ) ? (string) $field['key'] : null,
						'object'         => $object_id,
						'field_group_id' => $group_id ? $group_id : null,
						'acfml'          => '' !== $pref ? $pref : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status,
				'fix_url'           => $fix_url,
				'fix_label'         => $fix_label,
				'links'             => $links,
				'text'              => $text,
			)
		);
	}

	/**
	 * By-value lookup in postmeta (ACF fields and plain custom fields) for given posts.
	 *
	 * @param string $text         Text.
	 * @param int[]  $post_ids     Posts to search (current document first); empty = any post (single click only).
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_meta_by_value( $text, array $post_ids, $current, $default_lang ) {
		global $wpdb;
		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
		if ( $post_ids ) {
			$in = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only; $in is a %d list.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value = %s AND post_id IN ({$in}) LIMIT 10", array_merge( array( $text ), $post_ids ) ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only exact match.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value = %s LIMIT 10", $text ), ARRAY_A );
		}
		foreach ( (array) $rows as $row ) {
			$key = (string) $row['meta_key'];
			if ( '' === $key || '_' === $key[0] ) {
				continue;
			}
			$ref = get_post_meta( (int) $row['post_id'], '_' . $key, true );
			if ( is_string( $ref ) && 0 === strpos( $ref, 'field_' ) ) {
				$acf = self::resolve_acf( $text, $ref, (string) (int) $row['post_id'], $current, $default_lang );
				if ( $acf ) {
					return $acf;
				}
			}
			$post_id = (int) $row['post_id'];
			$tm_link = apply_filters( 'wpml_get_post_translation_url', null, $post_id );
			return Origin_Resolver::result(
				array(
					'source_type'       => 'custom_field',
					'source_type_label' => sprintf( /* translators: %s: meta key */ __( 'Custom field: %s', 'wpml-translation-check' ), $key ),
					'identifiers'       => array(
						'post_id'  => $post_id,
						'meta_key' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Display only.
					),
					'translated'        => null,
					'status_label'      => __( 'Custom field value', 'wpml-translation-check' ),
					'fix_url'           => ( is_string( $tm_link ) && '' !== $tm_link ) ? $tm_link : (string) get_edit_post_link( $post_id, 'raw' ),
					'fix_label'         => __( 'Edit post / translation', 'wpml-translation-check' ),
					'links'             => array(
						array(
							'label' => __( 'WPML custom field settings', 'wpml-translation-check' ),
							'url'   => admin_url( 'admin.php?page=tm/menu/settings#ml-content-setup-sec-cf' ),
						),
					),
					'text'              => $text,
				)
			);
		}
		return null;
	}

	/* ---------- Forms ---------- */

	/**
	 * Installed form plugins.
	 *
	 * @return array<string, string>
	 */
	public static function form_plugins() {
		$out = array();
		if ( class_exists( '\WPCF7_ContactForm' ) || defined( 'WPCF7_VERSION' ) ) {
			$out['cf7'] = 'Contact Form 7';
		}
		if ( function_exists( 'wpforms' ) || defined( 'WPFORMS_VERSION' ) ) {
			$out['wpforms'] = 'WPForms';
		}
		if ( class_exists( '\GFAPI' ) ) {
			$out['gf'] = 'Gravity Forms';
		}
		return $out;
	}

	/**
	 * Form plugin for a shortcode tag / block name.
	 *
	 * @param string $tag Tag.
	 * @return string
	 */
	public static function form_for_tag( $tag ) {
		$map = array(
			'contact-form-7'                       => 'cf7',
			'contact-form'                         => 'cf7',
			'contact-form-7/contact-form-selector' => 'cf7',
			'wpforms'                              => 'wpforms',
			'wpforms/form-selector'                => 'wpforms',
			'gravityform'                          => 'gf',
			'gravityforms'                         => 'gf',
			'gravityforms/form'                    => 'gf',
		);
		return isset( $map[ $tag ] ) ? $map[ $tag ] : '';
	}

	/**
	 * Searchable strings of a form.
	 *
	 * @param string $type cf7|wpforms|gf.
	 * @param int    $id   Form ID.
	 * @return string[]
	 */
	private static function form_texts( $type, $id ) {
		$raw = array();
		if ( 'cf7' === $type ) {
			$raw[] = (string) get_post_meta( $id, '_form', true );
			$raw[] = get_post_meta( $id, '_messages', true );
			$raw[] = get_the_title( $id );
		} elseif ( 'wpforms' === $type ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				$raw[] = json_decode( (string) $post->post_content, true );
			}
		} elseif ( 'gf' === $type && class_exists( '\GFAPI' ) ) {
			$raw[] = \GFAPI::get_form( $id );
		}
		$texts = array();
		foreach ( $raw as $value ) {
			$budget = 2000;
			Option_Index::walk(
				$value,
				'',
				static function ( $leaf ) use ( &$texts ) {
					// CF7 form-tags: [text* your-name placeholder "Your name"] -> quoted values.
					if ( preg_match_all( '/"([^"]{2,200})"/', $leaf, $q ) ) {
						foreach ( $q[1] as $quoted ) {
							$texts[ Origin_Collector::normalize( $quoted ) ] = true;
						}
					}
					$clean = (string) preg_replace( '/\[[^\]]*\]/', "\n", $leaf );
					foreach ( Option_Index::keys_for_leaf( $clean ) as $key => $partial ) {
						$texts[ $key ] = true;
					}
				},
				0,
				$budget
			);
		}
		return array_keys( $texts );
	}

	/**
	 * Candidate form IDs of a type (bounded).
	 *
	 * @param string $type Type.
	 * @return int[]
	 */
	private static function form_ids( $type ) {
		if ( 'gf' === $type ) {
			if ( ! class_exists( '\GFAPI' ) ) {
				return array();
			}
			$ids = array();
			foreach ( (array) \GFAPI::get_forms() as $form ) {
				if ( isset( $form['id'] ) ) {
					$ids[] = (int) $form['id'];
				}
			}
			return array_slice( $ids, 0, 100 );
		}
		$post_type = 'cf7' === $type ? 'wpcf7_contact_form' : 'wpforms';
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => 'any',
					'numberposts'      => 100,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			)
		);
	}

	/**
	 * Resolve text that belongs to a form.
	 *
	 * @param string $text         Text.
	 * @param string $type         cf7|wpforms|gf or '' for any installed.
	 * @param int    $form_id      Form ID or 0.
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_form( $text, $type, $form_id, $current, $default_lang ) {
		$installed = self::form_plugins();
		$types     = '' !== $type ? array( $type ) : array_keys( $installed );
		foreach ( $types as $t ) {
			if ( ! isset( $installed[ $t ] ) ) {
				continue;
			}
			$ids = $form_id > 0 ? array( (int) $form_id ) : self::form_ids( $t );
			foreach ( $ids as $id ) {
				if ( in_array( $text, self::form_texts( $t, $id ), true ) ) {
					return self::form_result( $text, $t, $installed[ $t ], $id, $current, $default_lang );
				}
			}
		}
		return null;
	}

	/**
	 * Form result row with edit link and WPML route.
	 *
	 * @param string $text         Text.
	 * @param string $type         Type.
	 * @param string $label        Plugin label.
	 * @param int    $id           Form ID.
	 * @param string $current      Current language.
	 * @param string $default_lang Default language.
	 * @return array<string, mixed>
	 */
	private static function form_result( $text, $type, $label, $id, $current, $default_lang ) {
		$secondary  = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$links      = array();
		$translated = null;
		$status     = sprintf( /* translators: %s: form plugin */ __( '%s form text', 'wpml-translation-check' ), $label );
		$string_id  = 0;

		if ( 'cf7' === $type ) {
			$edit       = admin_url( 'admin.php?page=wpcf7&post=' . $id . '&action=edit' );
			$translates = (bool) apply_filters( 'wpml_is_translated_post_type', false, 'wpcf7_contact_form' );
			if ( $translates ) {
				$current_id = (int) apply_filters( 'wpml_object_id', $id, 'wpcf7_contact_form', false, $current );
				$tm_link    = apply_filters( 'wpml_get_post_translation_url', null, $id );
				if ( $secondary ) {
					$translated = $current_id > 0 && $current_id !== $id && ! in_array( $text, self::form_texts( 'cf7', $current_id ), true );
					$status     = $translated ? __( 'Form translated', 'wpml-translation-check' ) : __( 'Form not translated in this language', 'wpml-translation-check' );
					if ( $current_id > 0 && $current_id !== $id ) {
						$edit = admin_url( 'admin.php?page=wpcf7&post=' . $current_id . '&action=edit' );
					}
				}
				if ( is_string( $tm_link ) && '' !== $tm_link ) {
					$links[] = array(
						'label' => __( 'Translate form (WPML)', 'wpml-translation-check' ),
						'url'   => $tm_link,
					);
				}
			} else {
				if ( $secondary ) {
					$translated = false;
				}
				$status  = __( 'Contact forms are not set as translatable in WPML', 'wpml-translation-check' );
				$links[] = array(
					'label' => __( 'WPML translation settings', 'wpml-translation-check' ),
					'url'   => admin_url( 'admin.php?page=tm/menu/settings#ml-content-setup-sec-7' ),
				);
			}
		} else {
			$edit = 'wpforms' === $type
				? admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . $id )
				: admin_url( 'admin.php?page=gf_edit_forms&id=' . $id );
			// WPML form add-ons register form strings as a string package: context "{kind}-{id}".
			$kind = 'wpforms' === $type ? 'wpforms' : 'gravity_form';
			$row  = Origin_Resolver::lookup_st_row( $text, $kind . '-' . $id );
			if ( $row && 0 === strcasecmp( (string) $row['context'], $kind . '-' . $id ) ) {
				$string_id  = (int) $row['id'];
				$st         = Origin_Resolver::string_translation_status( $string_id, $current, $default_lang );
				$translated = $st['translated'];
				$status     = $st['label'];
				$links[]    = array(
					'label' => __( 'Translate in String Translation', 'wpml-translation-check' ),
					'url'   => Origin_Resolver::st_search_url( (string) $row['value'], (string) $row['context'], $string_id ),
				);
			} else {
				if ( $secondary ) {
					$translated = false;
				}
				$status = __( 'Form text is not registered in WPML yet', 'wpml-translation-check' );
			}
			$links[] = array(
				'label' => __( 'Translate the form in WPML', 'wpml-translation-check' ),
				'url'   => Component_Registry::tm_dashboard_url(),
			);
		}

		return Origin_Resolver::result(
			array(
				'source_type'       => 'form_' . $type,
				'source_type_label' => sprintf( /* translators: 1: form plugin, 2: form id */ __( '%1$s form #%2$d', 'wpml-translation-check' ), $label, $id ),
				'identifiers'       => array_filter(
					array(
						'form_id'   => $id,
						'plugin'    => $label,
						'string_id' => $string_id ? $string_id : null,
					)
				),
				'translated'        => $translated,
				'status_label'      => $status,
				'fix_url'           => $edit,
				'fix_label'         => __( 'Edit form', 'wpml-translation-check' ),
				'links'             => $links,
				'text'              => $text,
			)
		);
	}

	/* ---------- WooCommerce emails ---------- */

	/**
	 * Describe a WooCommerce email setting option (woocommerce_{email_id}_settings / email footer).
	 *
	 * @param string $option_name Option name.
	 * @param string $path        Key path.
	 * @return array<string, string>|null label, url.
	 */
	public static function wc_email_setting( $option_name, $path ) {
		if ( ! function_exists( 'WC' ) || ! class_exists( '\WC_Emails' ) ) {
			return null;
		}
		$option_name = (string) $option_name;
		if ( in_array( $option_name, array( 'woocommerce_email_footer_text', 'woocommerce_email_header_image', 'woocommerce_email_from_name' ), true ) ) {
			return array(
				'label' => __( 'WooCommerce email template setting', 'wpml-translation-check' ),
				'url'   => admin_url( 'admin.php?page=wc-settings&tab=email' ),
				'field' => $option_name,
			);
		}
		if ( ! preg_match( '/^woocommerce_(.+)_settings$/', $option_name, $m ) ) {
			return null;
		}
		$emails = \WC_Emails::instance()->get_emails();
		foreach ( (array) $emails as $class => $email ) {
			if ( is_object( $email ) && isset( $email->id ) && $email->id === $m[1] ) {
				$field = trim( (string) preg_replace( '/\]\[/', '.', (string) $path ), '[]' );
				return array(
					'label' => sprintf( /* translators: %s: email title */ __( 'WooCommerce email: %s', 'wpml-translation-check' ), wp_strip_all_tags( (string) $email->get_title() ) ),
					'url'   => admin_url( 'admin.php?page=wc-settings&tab=email&section=' . strtolower( (string) $class ) ),
					'field' => $field,
				);
			}
		}
		return null;
	}
}
