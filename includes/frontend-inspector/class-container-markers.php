<?php
/**
 * Container layer of the front-end inspector.
 *
 * While inspect mode renders (manage_options + ?automlp-inspect=1 only) the main
 * output producers are wrapped in tiny numbered HTML comments. When the page
 * buffer is flushed the comments are parsed into a "text -> innermost container"
 * index, printed as an inert JSON block, and every marker is removed again, so the
 * delivered HTML is unchanged apart from that one data block.
 *
 * Normal page loads never register any of these hooks.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Container_Markers
 */
class Container_Markers {

	const DATA_ID        = 'automlp-fi-containers';
	const MAX_CONTAINERS = 4000;
	const MAX_TEXTS      = 6000;
	const MAX_TEXT_LEN   = 400;
	const MAX_REPEATS    = 20;
	const MARKER_REGEX   = '/<!--afi:[ser]:\d+-->/';

	/**
	 * Container meta keyed by marker id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $meta = array();

	/**
	 * Last marker id.
	 *
	 * @var int
	 */
	private static $seq = 0;

	/**
	 * Register the wrapping hooks for this request only.
	 *
	 * @return void
	 */
	public static function start() {
		if ( is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$late = PHP_INT_MAX;

		add_filter( 'the_content', array( __CLASS__, 'wrap_content' ), $late );
		add_filter( 'the_excerpt', array( __CLASS__, 'wrap_excerpt' ), $late );
		add_filter( 'render_block', array( __CLASS__, 'wrap_block' ), $late, 2 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'wrap_shortcode' ), $late, 2 );
		add_filter( 'wp_nav_menu', array( __CLASS__, 'wrap_menu' ), $late, 2 );
		add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'wrap_menu_item' ), $late, 2 );
		add_action( 'dynamic_sidebar_before', array( __CLASS__, 'sidebar_before' ), 1 );
		add_action( 'dynamic_sidebar_after', array( __CLASS__, 'sidebar_after' ), $late );
		add_filter( 'dynamic_sidebar_params', array( __CLASS__, 'widget_params' ), $late );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'wrap_elementor' ), $late, 2 );
		// Render Elementor live while inspecting so markers are never written into its element cache.
		add_filter( 'pre_option_elementor_element_cache_ttl', array( __CLASS__, 'disable_element_cache' ) );

		// Template regions: each one runs until the next region starts.
		add_action( 'get_header', array( __CLASS__, 'region_header' ), $late );
		add_action( 'get_footer', array( __CLASS__, 'region_footer' ), $late );
		add_action( 'get_sidebar', array( __CLASS__, 'region_sidebar' ), $late );
		add_action( 'get_template_part', array( __CLASS__, 'region_part' ), $late, 3 );
		add_action( 'loop_start', array( __CLASS__, 'region_template' ), 1 );
	}

	/**
	 * Allocate a marker id for container meta.
	 *
	 * @param array<string, mixed> $meta Meta.
	 * @return int 0 when the cap is reached.
	 */
	private static function open( array $meta ) {
		if ( self::$seq >= self::MAX_CONTAINERS ) {
			return 0;
		}
		self::$meta[ ++self::$seq ] = $meta;
		return self::$seq;
	}

	/**
	 * Wrap HTML in start/end markers.
	 *
	 * @param mixed                $html HTML.
	 * @param array<string, mixed> $meta Container meta.
	 * @return mixed
	 */
	private static function wrap( $html, array $meta ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $html;
		}
		$id = self::open( $meta );
		return $id ? '<!--afi:s:' . $id . '-->' . $html . '<!--afi:e:' . $id . '-->' : $html;
	}

	/**
	 * Post content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function wrap_content( $content ) {
		$post_id = (int) get_the_ID();
		return self::wrap(
			$content,
			array(
				'type' => 'post_content',
				'id'   => $post_id,
			)
		);
	}

	/**
	 * Post excerpt (archive and blog listings).
	 *
	 * @param string $excerpt Excerpt HTML.
	 * @return string
	 */
	public static function wrap_excerpt( $excerpt ) {
		return self::wrap(
			$excerpt,
			array(
				'type'    => 'post_content',
				'id'      => (int) get_the_ID(),
				'excerpt' => 1,
			)
		);
	}

	/**
	 * Any block (core, theme, plugin, synced pattern, template part).
	 *
	 * @param string               $html  Block HTML.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string
	 */
	public static function wrap_block( $html, $block ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
			return $html;
		}
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$meta  = array(
			'type'  => 'block',
			'block' => (string) $block['blockName'],
			'id'    => (int) get_the_ID(),
		);
		if ( isset( $attrs['ref'] ) ) {
			$meta['ref'] = absint( $attrs['ref'] );
		}
		if ( isset( $attrs['slug'] ) && is_string( $attrs['slug'] ) ) {
			$meta['slug'] = sanitize_key( $attrs['slug'] );
		}
		return self::wrap( $html, $meta );
	}

	/**
	 * Shortcode output.
	 *
	 * @param string $output Output.
	 * @param string $tag    Shortcode tag.
	 * @return string
	 */
	public static function wrap_shortcode( $output, $tag ) {
		return self::wrap(
			$output,
			array(
				'type' => 'shortcode',
				'tag'  => (string) $tag,
			)
		);
	}

	/**
	 * Whole nav menu.
	 *
	 * @param string $nav_menu HTML.
	 * @param object $args     Menu args (menu is a WP_Term by now).
	 * @return string
	 */
	public static function wrap_menu( $nav_menu, $args ) {
		$term = ( is_object( $args ) && isset( $args->menu ) && $args->menu instanceof \WP_Term ) ? $args->menu : null;
		return self::wrap(
			$nav_menu,
			array(
				'type'     => 'nav_menu',
				'id'       => $term ? (int) $term->term_id : 0,
				'name'     => $term ? $term->name : '',
				'location' => ( is_object( $args ) && ! empty( $args->theme_location ) ) ? (string) $args->theme_location : '',
			)
		);
	}

	/**
	 * One nav menu item.
	 *
	 * @param string   $item_output Item HTML.
	 * @param \WP_Post $item        Menu item.
	 * @return string
	 */
	public static function wrap_menu_item( $item_output, $item ) {
		return self::wrap(
			$item_output,
			array(
				'type' => 'nav_menu_item',
				'id'   => is_object( $item ) && isset( $item->ID ) ? (int) $item->ID : 0,
			)
		);
	}

	/**
	 * Sidebar start.
	 *
	 * @param string $index Sidebar id.
	 * @return void
	 */
	public static function sidebar_before( $index ) {
		$id = self::open(
			array(
				'type' => 'sidebar',
				'sid'  => (string) $index,
			)
		);
		if ( $id ) {
			echo '<!--afi:s:' . (int) $id . '-->';
		}
	}

	/**
	 * Sidebar end (closes the innermost open sidebar).
	 *
	 * @param string $index Sidebar id.
	 * @return void
	 */
	public static function sidebar_after( $index ) {
		for ( $i = self::$seq; $i > 0; $i-- ) {
			if ( 'sidebar' === self::$meta[ $i ]['type'] && (string) $index === self::$meta[ $i ]['sid'] && empty( self::$meta[ $i ]['closed'] ) ) {
				self::$meta[ $i ]['closed'] = 1;
				echo '<!--afi:e:' . (int) $i . '-->';
				return;
			}
		}
	}

	/**
	 * Wrap every widget through its before/after markup.
	 *
	 * @param array<int, array<string, mixed>> $params Widget params.
	 * @return array<int, array<string, mixed>>
	 */
	public static function widget_params( $params ) {
		if ( ! isset( $params[0]['widget_id'] ) ) {
			return $params;
		}
		$id = self::open(
			array(
				'type' => 'widget',
				'wid'  => (string) $params[0]['widget_id'],
				'sid'  => isset( $params[0]['id'] ) ? (string) $params[0]['id'] : '',
			)
		);
		if ( $id ) {
			$params[0]['before_widget'] = '<!--afi:s:' . $id . '-->' . ( isset( $params[0]['before_widget'] ) ? $params[0]['before_widget'] : '' );
			$params[0]['after_widget']  = ( isset( $params[0]['after_widget'] ) ? $params[0]['after_widget'] : '' ) . '<!--afi:e:' . $id . '-->';
		}
		return $params;
	}

	/**
	 * Elementor widget HTML (other builders are covered by render_block/the_content/shortcodes).
	 *
	 * @param string $content Widget HTML.
	 * @param object $widget  Widget.
	 * @return string
	 */
	public static function wrap_elementor( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_id' ) ) {
			return $content;
		}
		$did = 0;
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$doc = \Elementor\Plugin::$instance->documents->get_current();
			if ( is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ) {
				$did = (int) $doc->get_main_id();
			}
		}
		$meta = array(
			'type'   => 'elementor',
			'eid'    => (string) $widget->get_id(),
			'did'    => $did,
			'widget' => method_exists( $widget, 'get_name' ) ? (string) $widget->get_name() : '',
		);
		try {
			$ref = new \ReflectionClass( $widget );
			$td  = Origin_Collector::textdomain_for_path( (string) $ref->getFileName() );
			if ( '' !== $td ) {
				$meta['td'] = $td;
			}
		} catch ( \ReflectionException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Leave td unset when reflection fails.
		}
		return self::wrap( $content, $meta );
	}

	/**
	 * Short-circuit Elementor's element cache option while inspecting.
	 *
	 * @return string
	 */
	public static function disable_element_cache() {
		return 'disable';
	}

	/**
	 * Start a template region.
	 *
	 * @param string             $label     Region label.
	 * @param array<int, string> $templates Candidate template files.
	 * @return void
	 */
	private static function region( $label, array $templates ) {
		$file = $templates ? locate_template( $templates ) : '';
		$rel  = self::relative_path( (string) $file );
		$meta = array(
			'type'  => 'template',
			'label' => $label,
			'file'  => $rel,
		);
		$td   = Origin_Collector::textdomain_for_path( '' !== $file ? (string) $file : $rel );
		if ( '' !== $td ) {
			$meta['td'] = $td;
		}
		$id = self::open( $meta );
		if ( $id ) {
			echo '<!--afi:r:' . (int) $id . '-->';
		}
	}

	/**
	 * Candidate list for header/footer/sidebar.
	 *
	 * @param string $base Base name.
	 * @param mixed  $name Optional specialised name.
	 * @return array<int, string>
	 */
	private static function candidates( $base, $name ) {
		$list = array();
		if ( is_string( $name ) && '' !== $name ) {
			$list[] = $base . '-' . $name . '.php';
		}
		$list[] = $base . '.php';
		return $list;
	}

	/**
	 * Region: header.
	 *
	 * @param mixed $name Header name.
	 * @return void
	 */
	public static function region_header( $name = null ) {
		self::region( 'header', self::candidates( 'header', $name ) );
	}

	/**
	 * Region: footer.
	 *
	 * @param mixed $name Footer name.
	 * @return void
	 */
	public static function region_footer( $name = null ) {
		self::region( 'footer', self::candidates( 'footer', $name ) );
	}

	/**
	 * Region: sidebar template.
	 *
	 * @param mixed $name Sidebar name.
	 * @return void
	 */
	public static function region_sidebar( $name = null ) {
		self::region( 'sidebar', self::candidates( 'sidebar', $name ) );
	}

	/**
	 * Region: template part.
	 *
	 * @param string             $slug      Slug.
	 * @param mixed              $name      Name.
	 * @param array<int, string> $templates Candidates.
	 * @return void
	 */
	public static function region_part( $slug, $name = null, $templates = array() ) {
		self::region( 'template-part: ' . $slug . ( is_string( $name ) && '' !== $name ? '-' . $name : '' ), is_array( $templates ) ? $templates : array() );
	}

	/**
	 * Region: main template (once, when the main loop starts).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function region_template( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() ) {
			return;
		}
		remove_action( 'loop_start', array( __CLASS__, 'region_template' ), 1 );
		global $template;
		$abs  = is_string( $template ) ? $template : '';
		$rel  = self::relative_path( $abs );
		$meta = array(
			'type'  => 'template',
			'label' => 'template',
			'file'  => $rel,
		);
		$td   = Origin_Collector::textdomain_for_path( '' !== $abs ? $abs : $rel );
		if ( '' !== $td ) {
			$meta['td'] = $td;
		}
		$id = self::open( $meta );
		if ( $id ) {
			echo '<!--afi:r:' . (int) $id . '-->';
		}
	}

	/**
	 * Path relative to wp-content for display.
	 *
	 * @param string $file Absolute file.
	 * @return string
	 */
	public static function relative_path( $file ) {
		if ( '' === $file ) {
			return '';
		}
		$file = wp_normalize_path( $file );
		$base = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		return 0 === strpos( $file, $base ) ? substr( $file, strlen( $base ) ) : basename( $file );
	}

	/**
	 * Output-buffer callback: index markers, print the index, strip markers.
	 *
	 * @param string $html Buffered page.
	 * @return string
	 */
	public static function finish( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '<!--afi:' ) ) {
			return $html;
		}
		$index = Frontend_Inspector::admin_strings(
			static function () use ( $html ) {
				return self::build_index( $html );
			}
		);
		$html  = (string) preg_replace( self::MARKER_REGEX, '', $html );
		$json  = wp_json_encode( $index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return $html;
		}
		$tag = '<script type="application/json" id="' . esc_attr( self::DATA_ID ) . '">' . $json . '</script>';
		$pos = strripos( $html, '</body>' );
		return false === $pos ? $html . $tag : substr( $html, 0, $pos ) . $tag . substr( $html, $pos );
	}

	/**
	 * Walk the body once and record text -> innermost container / template region.
	 *
	 * @param string $html Page HTML with markers.
	 * @return array<string, mixed>
	 */
	private static function build_index( $html ) {
		$pos = stripos( $html, '<body' );
		if ( false === $pos ) {
			$pos = 0;
		}
		// Regions opened before <body> (header) still apply to the body.
		$region = 0;
		if ( $pos > 0 && preg_match_all( '/<!--afi:r:(\d+)-->/', substr( $html, 0, $pos ), $m ) ) {
			$region = (int) end( $m[1] );
		}

		$tokens = preg_split(
			'#(<!--afi:[ser]:\d+-->|<(script|style|noscript|template|textarea)\b[^>]*>.*?</\2\s*>|<!--.*?-->|<[^>]+>)#is',
			substr( $html, $pos ),
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);
		$stack  = array();
		$parent = array();
		$texts  = array();
		$occ    = array();
		$used   = array();
		$skip   = false;

		foreach ( (array) $tokens as $token ) {
			if ( $skip ) {
				// Second capture group (tag name) of a script/style match.
				$skip = false;
				if ( preg_match( '/^(script|style|noscript|template|textarea)$/i', $token ) ) {
					continue;
				}
			}
			if ( '<' === $token[0] ) {
				if ( preg_match( '/^<!--afi:([ser]):(\d+)-->$/', $token, $mm ) ) {
					$id = (int) $mm[2];
					if ( 's' === $mm[1] ) {
						$parent[ $id ] = $stack ? end( $stack ) : 0;
						$stack[]       = $id;
					} elseif ( 'e' === $mm[1] ) {
						$at = array_search( $id, $stack, true );
						if ( false !== $at ) {
							$stack = array_slice( $stack, 0, $at );
						}
					} else {
						$region = $id;
					}
				} elseif ( preg_match( '#^<(script|style|noscript|template|textarea)\b#i', $token ) ) {
					$skip = true;
				}
				continue;
			}
			if ( count( $texts ) >= self::MAX_TEXTS ) {
				continue;
			}
			$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $token, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			if ( '' === $text || strlen( $text ) < 2 || strlen( $text ) > self::MAX_TEXT_LEN ) {
				continue;
			}
			$cid = $stack ? (int) end( $stack ) : 0;
			if ( isset( $texts[ $text ] ) ) {
				// Same text again (e.g. a title repeated in a paragraph): keep every place in page order.
				if ( count( $occ[ $text ] ) < self::MAX_REPEATS ) {
					$occ[ $text ][] = array( $cid, $region );
				}
			} else {
				$texts[ $text ] = array( $cid, $region );
				$occ[ $text ]   = array( $texts[ $text ] );
			}
			for ( $c = $cid; $c > 0 && ! isset( $used[ $c ] ); $c = isset( $parent[ $c ] ) ? $parent[ $c ] : 0 ) {
				$used[ $c ] = true;
			}
			if ( $region ) {
				$used[ $region ] = true;
			}
		}

		// Only texts whose repeats sit in different places are sent to the browser.
		$dups = array();
		foreach ( $occ as $text => $places ) {
			if ( count( $places ) > 1 && count( array_unique( array_map( 'wp_json_encode', $places ) ) ) > 1 ) {
				$dups[ $text ] = $places;
			}
		}

		$containers = array();
		foreach ( array_keys( $used ) as $id ) {
			if ( isset( self::$meta[ $id ] ) ) {
				$containers[ $id ] = self::describe( self::$meta[ $id ] ) + array( 'parent' => isset( $parent[ $id ] ) ? (int) $parent[ $id ] : 0 );
			}
		}

		return array(
			'texts'      => $texts,
			'dups'       => (object) $dups,
			'containers' => (object) $containers,
		);
	}

	/**
	 * Readable block title from the block name: "core/media-text" -> "Media text".
	 *
	 * @param string $name Block name.
	 * @return string
	 */
	private static function block_title( $name ) {
		// Built from the block name, not the registered title: that title is translated into the
		// page language and would mix languages in the panel (e.g. a Hindi word + "block").
		$slug = false !== strpos( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;
		return ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Plain shortcode label: "My account shortcode (WooCommerce)".
	 *
	 * @param string $tag  Shortcode tag, e.g. woocommerce_my_account.
	 * @param string $file Callback file (relative path:line) or ''.
	 * @return string
	 */
	private static function shortcode_label( $tag, $file ) {
		$owner = '' !== $file ? Component_Registry::for_path( (string) preg_replace( '/:\d+$/', '', $file ) ) : array();
		$words = preg_split( '/[_\-\s]+/', strtolower( $tag ), -1, PREG_SPLIT_NO_EMPTY );
		if ( $owner && count( (array) $words ) > 1 ) {
			$keys = array_filter(
				array(
					isset( $owner['slug'] ) ? strtolower( (string) $owner['slug'] ) : '',
					isset( $owner['domain'] ) ? strtolower( (string) $owner['domain'] ) : '',
					strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', Component_Registry::name( $owner ) ) ),
				)
			);
			foreach ( $keys as $key ) {
				if ( 0 === strpos( $key, $words[0] ) ) {
					array_shift( $words );
					break;
				}
			}
		}
		$name = ucfirst( implode( ' ', (array) $words ) );
		if ( $owner ) {
			/* translators: 1: shortcode name in words, e.g. My account; 2: plugin or theme name */
			return sprintf( __( '%1$s shortcode (%2$s)', 'wpml-translation-check' ), $name, Component_Registry::name( $owner ) );
		}
		/* translators: %s: shortcode name in words, e.g. My account */
		return sprintf( __( '%s shortcode', 'wpml-translation-check' ), $name );
	}

	/**
	 * Display data + edit link for one container (only for containers that hold text).
	 *
	 * @param array<string, mixed> $meta Raw meta.
	 * @return array<string, mixed>
	 */
	private static function describe( array $meta ) {
		$type = (string) $meta['type'];
		$out  = array( 'type' => $type );
		$edit = '';
		$id   = isset( $meta['id'] ) ? (int) $meta['id'] : 0;

		switch ( $type ) {
			case 'post_content':
				$out['label'] = empty( $meta['excerpt'] ) ? __( 'Post content', 'wpml-translation-check' ) : __( 'Post excerpt', 'wpml-translation-check' );
				$out['id']    = $id;
				$edit         = $id ? (string) get_edit_post_link( $id, 'raw' ) : '';
				if ( ! empty( $meta['excerpt'] ) ) {
					$out['excerpt'] = 1;
				}
				break;
			case 'block':
				$out['label'] = sprintf( /* translators: %s: block name, e.g. Paragraph */ __( '%s block', 'wpml-translation-check' ), self::block_title( (string) $meta['block'] ) );
				$out['block'] = $meta['block'];
				$out['id']    = $id;
				if ( ! empty( $meta['ref'] ) ) {
					$out['ref'] = (int) $meta['ref'];
					$edit       = (string) get_edit_post_link( (int) $meta['ref'], 'raw' );
				} elseif ( ! empty( $meta['slug'] ) && 'core/template-part' === $meta['block'] ) {
					$out['slug'] = $meta['slug'];
					$edit        = add_query_arg(
						array(
							'postType' => 'wp_template_part',
							'postId'   => get_stylesheet() . '//' . $meta['slug'],
						),
						admin_url( 'site-editor.php' )
					);
				} elseif ( $id ) {
					$edit = (string) get_edit_post_link( $id, 'raw' );
				}
				break;
			case 'shortcode':
				$out['tag']   = $meta['tag'];
				$out['file']  = self::callback_location( $meta['tag'] );
				$out['label'] = self::shortcode_label( (string) $meta['tag'], (string) $out['file'] );
				break;
			case 'nav_menu':
				$out['label']    = sprintf( /* translators: %s: menu name */ __( 'Menu: %s', 'wpml-translation-check' ), $meta['name'] );
				$out['id']       = $id;
				$out['location'] = $meta['location'];
				$edit            = $id ? admin_url( 'nav-menus.php?action=edit&menu=' . $id ) : admin_url( 'nav-menus.php' );
				break;
			case 'nav_menu_item':
				$out['label'] = __( 'Menu item', 'wpml-translation-check' );
				$out['id']    = $id;
				break;
			case 'sidebar':
				$out['label'] = sprintf( /* translators: %s: sidebar id */ __( 'Widget area: %s', 'wpml-translation-check' ), $meta['sid'] );
				$out['sid']   = $meta['sid'];
				$edit         = admin_url( 'widgets.php' );
				break;
			case 'widget':
				$out['label'] = sprintf( /* translators: %s: widget id */ __( 'Widget: %s', 'wpml-translation-check' ), $meta['wid'] );
				$out['wid']   = $meta['wid'];
				$out['sid']   = $meta['sid'];
				$edit         = admin_url( 'widgets.php' );
				break;
			case 'elementor':
				$out['label']  = sprintf( /* translators: %s: Elementor widget name */ __( 'Elementor widget: %s', 'wpml-translation-check' ), $meta['widget'] );
				$out['eid']    = $meta['eid'];
				$out['did']    = (int) $meta['did'];
				$out['widget'] = $meta['widget'];
				$edit          = $meta['did'] ? admin_url( 'post.php?post=' . (int) $meta['did'] . '&action=elementor' ) : '';
				break;
			case 'template':
				$owner        = ! empty( $meta['file'] ) ? Component_Registry::label( Component_Registry::for_path( (string) $meta['file'] ) ) : '';
				$out['label'] = '' !== $owner ? $owner : __( 'Your theme', 'wpml-translation-check' );
				$out['file']  = $meta['file'];
				break;
		}
		if ( '' !== $edit ) {
			$out['edit'] = $edit;
		}
		// Dynamic container text domain (caller theme/plugin) for gettext disambiguation.
		if ( ! empty( $meta['td'] ) && is_string( $meta['td'] ) ) {
			$out['td'] = $meta['td'];
		} elseif ( ! empty( $meta['file'] ) && is_string( $meta['file'] ) ) {
			$td = Origin_Collector::textdomain_for_path( $meta['file'] );
			if ( '' !== $td ) {
				$out['td'] = $td;
			}
		}
		return $out;
	}

	/**
	 * File:line of a shortcode callback (Reflection, no DB).
	 *
	 * @param string $tag Shortcode tag.
	 * @return string
	 */
	private static function callback_location( $tag ) {
		global $shortcode_tags;
		if ( ! isset( $shortcode_tags[ $tag ] ) ) {
			return '';
		}
		$cb = $shortcode_tags[ $tag ];
		try {
			if ( is_array( $cb ) && 2 === count( $cb ) ) {
				$ref = new \ReflectionMethod( $cb[0], $cb[1] );
			} elseif ( is_string( $cb ) && false !== strpos( $cb, '::' ) ) {
				$ref = new \ReflectionMethod( $cb );
			} elseif ( is_string( $cb ) || $cb instanceof \Closure ) {
				$ref = new \ReflectionFunction( $cb );
			} else {
				return '';
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}
		$file = $ref->getFileName();
		return $file ? self::relative_path( $file ) . ':' . $ref->getStartLine() : '';
	}
}
