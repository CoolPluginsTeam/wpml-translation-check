<?php
/**
 * Light-weight origin map collected during front-end inspect render.
 *
 * Records text origins without String Translation / Admin Texts DB lookups.
 * Heavy status resolution happens later via AJAX (Origin_Resolver).
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Origin_Collector
 */
class Origin_Collector {

	const MAX_ENTRIES  = 3500;
	const MAX_TEXT_LEN = 500;

	/**
	 * Map keyed by normalized visible text => list of origin hints.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $map = array();

	/**
	 * Pre-collected option/theme_mod value => origin hint.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $value_index = array();

	/**
	 * Whether collector hooks are active.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Current language for this request.
	 *
	 * @var string
	 */
	private static $current_lang = '';

	/**
	 * Default language.
	 *
	 * @var string
	 */
	private static $default_lang = '';

	/**
	 * Current Elementor element context.
	 *
	 * @var array{eid:string,did:int}|null
	 */
	private static $current_elementor = null;

	/**
	 * Full id (e.g. text-91) of the classic widget currently rendering.
	 *
	 * @var string
	 */
	private static $current_widget_id = '';

	/**
	 * Start collecting for an inspect-mode front-end request.
	 *
	 * @return void
	 */
	public static function start() {
		if ( self::$active ) {
			return;
		}

		self::$active       = true;
		self::$current_lang = (string) apply_filters( 'wpml_current_language', null );
		self::$default_lang = (string) apply_filters( 'wpml_default_language', null );

		self::build_value_index();

		// Track every option_* read during this inspect render (covers non-autoload options).
		add_action( 'all', array( __CLASS__, 'on_all_hooks' ), 1, 2 );

		add_filter( 'gettext', array( __CLASS__, 'on_gettext' ), 9999, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'on_gettext_with_context' ), 9999, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'on_ngettext' ), 9999, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'on_ngettext_with_context' ), 9999, 6 );

		add_filter( 'the_title', array( __CLASS__, 'on_the_title' ), 9999, 2 );
		add_filter( 'the_content', array( __CLASS__, 'on_the_content' ), 9999, 1 );
		add_filter( 'nav_menu_item_title', array( __CLASS__, 'on_nav_menu_item_title' ), 9999, 4 );
		add_action( 'dynamic_sidebar', array( __CLASS__, 'on_dynamic_sidebar' ), 1, 1 );
		add_filter( 'widget_title', array( __CLASS__, 'on_widget_title' ), 9999, 3 );
		add_filter( 'widget_text', array( __CLASS__, 'on_widget_text' ), 9999, 3 );

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			add_action( 'elementor/frontend/before_render', array( __CLASS__, 'on_elementor_before_render' ), 5 );
			add_filter( 'elementor/widget/render_content', array( __CLASS__, 'on_elementor_render_content' ), 9999, 2 );
		}
	}

	/**
	 * Compact payload for wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_payload() {
		$priority_kinds = array(
			'option'        => 1,
			'theme_mod'     => 1,
			'post_title'    => 1,
			'post_content'  => 1,
			'nav_menu_item' => 1,
			'widget'        => 1,
			'elementor'     => 1,
		);

		$priority = array();
		$rest     = array();

		foreach ( self::$map as $text => $origins ) {
			foreach ( $origins as $origin ) {
				$entry = array(
					't' => $text,
					'k' => isset( $origin['k'] ) ? (string) $origin['k'] : 'unknown',
					'u' => ! empty( $origin['u'] ) ? 1 : 0,
				);
				foreach ( array( 'd', 'o', 'c', 'n', 'p', 'id', 'eid', 'did', 'wid' ) as $key ) {
					if ( isset( $origin[ $key ] ) && '' !== $origin[ $key ] && null !== $origin[ $key ] ) {
						$entry[ $key ] = $origin[ $key ];
					}
				}
				if ( isset( $priority_kinds[ $entry['k'] ] ) ) {
					$priority[] = $entry;
				} else {
					$rest[] = $entry;
				}
			}
		}

		$entries = array_merge( $priority, $rest );
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, 0, self::MAX_ENTRIES );
		}

		return array(
			'entries'     => $entries,
			'currentLang' => self::$current_lang,
			'defaultLang' => self::$default_lang,
		);
	}

	/**
	 * Whether current language differs from default.
	 *
	 * @return bool
	 */
	private static function is_secondary_lang() {
		return '' !== self::$current_lang
			&& '' !== self::$default_lang
			&& self::$current_lang !== self::$default_lang;
	}

	/**
	 * Normalize text for map keys / DOM matching.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Whether text is too short / numeric to be useful.
	 *
	 * @param string $text Normalized text.
	 * @return bool
	 */
	private static function is_trivial( $text ) {
		if ( '' === $text || strlen( $text ) < 2 ) {
			return true;
		}
		if ( strlen( $text ) > self::MAX_TEXT_LEN ) {
			return true;
		}
		if ( preg_match( '/^[\d\s\.\,\%\$â‚¬Â£â‚¹\/\-\+:]+$/u', $text ) ) {
			return true;
		}
		return ! preg_match( '/\p{L}/u', $text );
	}

	/**
	 * Add an origin hint for a visible text string.
	 *
	 * @param string               $text   Visible text.
	 * @param array<string, mixed> $origin Origin hint.
	 * @return void
	 */
	private static function record( $text, array $origin ) {
		$text = self::normalize( $text );
		if ( self::is_trivial( $text ) ) {
			return;
		}

		$kind        = isset( $origin['k'] ) ? (string) $origin['k'] : '';
		$is_priority = in_array(
			$kind,
			array( 'option', 'theme_mod', 'post_title', 'post_content', 'nav_menu_item', 'widget', 'elementor' ),
			true
		);

		if ( ! isset( self::$map[ $text ] ) ) {
			if ( count( self::$map ) >= self::MAX_ENTRIES ) {
				if ( ! $is_priority ) {
					return;
				}
				// Evict a gettext-only key so option/theme_mod/post origins still fit.
				foreach ( self::$map as $existing_text => $origins ) {
					$only_gettext = true;
					foreach ( $origins as $o ) {
						if ( ! isset( $o['k'] ) || 'gettext' !== $o['k'] ) {
							$only_gettext = false;
							break;
						}
					}
					if ( $only_gettext ) {
						unset( self::$map[ $existing_text ] );
						break;
					}
				}
				if ( count( self::$map ) >= self::MAX_ENTRIES ) {
					return;
				}
			}
			self::$map[ $text ] = array();
		}

		// Prefer promoting a priority origin onto an existing gettext-only slot.
		if ( $is_priority && count( self::$map[ $text ] ) >= 5 ) {
			foreach ( self::$map[ $text ] as $idx => $existing ) {
				if ( isset( $existing['k'] ) && 'gettext' === $existing['k'] ) {
					unset( self::$map[ $text ][ $idx ] );
					self::$map[ $text ] = array_values( self::$map[ $text ] );
					break;
				}
			}
		}

		$sig = wp_json_encode( $origin );
		foreach ( self::$map[ $text ] as $existing ) {
			if ( wp_json_encode( $existing ) === $sig ) {
				return;
			}
		}
		if ( count( self::$map[ $text ] ) >= 5 ) {
			return;
		}
		self::$map[ $text ][] = $origin;
	}

	/**
	 * Index a string option / theme_mod value.
	 *
	 * @param string               $value  Option value.
	 * @param array<string, mixed> $origin Origin hint.
	 * @return void
	 */
	private static function index_value( $value, array $origin ) {
		if ( ! is_string( $value ) || is_serialized( $value ) ) {
			return;
		}
		$value = self::normalize( $value );
		if ( self::is_trivial( $value ) || ! self::is_meaningful_value( $value ) ) {
			return;
		}
		if ( ! isset( self::$value_index[ $value ] ) ) {
			self::$value_index[ $value ] = array();
		}
		if ( count( self::$value_index[ $value ] ) >= 5 ) {
			return;
		}
		self::$value_index[ $value ][] = $origin;
	}

	/**
	 * Walk a nested option array and index string leaves.
	 *
	 * @param mixed  $data  Option data.
	 * @param string $name  Option name.
	 * @param string $path  Bracket path.
	 * @param string $kind  option|theme_mod.
	 * @param int    $depth Recursion depth.
	 * @return void
	 */
	private static function walk_option_value( $data, $name, $path, $kind, $depth = 0 ) {
		if ( $depth > 4 ) {
			return;
		}
		if ( is_string( $data ) ) {
			$origin = array(
				'k' => $kind,
				'n' => $name,
			);
			if ( '' !== $path ) {
				$origin['p'] = $path;
			}
			$origin['u'] = self::is_secondary_lang() ? 1 : 0;
			self::index_value( $data, $origin );
			return;
		}
		if ( ! is_array( $data ) ) {
			return;
		}
		$count = 0;
		foreach ( $data as $key => $child ) {
			if ( $count++ > 80 ) {
				break;
			}
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			$segment = '[' . $key . ']';
			self::walk_option_value( $child, $name, $path . $segment, $kind, $depth + 1 );
		}
	}

	/**
	 * Build reverse index of option / theme_mod string values (no ST queries).
	 *
	 * @return void
	 */
	private static function build_value_index() {
		// Options are indexed only when read during this render (see on_all_hooks()).
		$mods = get_theme_mods();
		if ( is_array( $mods ) ) {
			foreach ( $mods as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				self::walk_option_value( $value, (string) $key, '', 'theme_mod' );
			}
		}

		// Warm-index: Admin Texts registry keys + automlp_inspect_tracked_options.
		// Non-autoload options not listed here are still captured via on_all_hooks().
		$tracked  = array();
		$registry = get_option( '_icl_admin_option_names', array() );
		if ( is_array( $registry ) ) {
			foreach ( $registry as $key => $enabled ) {
				if ( is_string( $key ) && '' !== $key ) {
					$tracked[] = $key;
				}
			}
		}

		/**
		 * Filters option names warm-indexed at inspect boot (before render).
		 *
		 * @param string[] $tracked Option names already queued (Admin Texts keys).
		 */
		$tracked = apply_filters( 'automlp_inspect_tracked_options', $tracked );
		if ( ! is_array( $tracked ) ) {
			$tracked = array();
		}

		$tracked = array_unique( array_filter( array_map( 'strval', $tracked ) ) );
		foreach ( $tracked as $option_name ) {
			if ( '' === $option_name ) {
				continue;
			}
			$val = get_option( $option_name, null );
			if ( null === $val ) {
				continue;
			}
			self::walk_option_value( $val, $option_name, '', 'option' );
		}
	}

	/**
	 * When a text matches a pre-indexed option/theme_mod value, attach those origins.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	private static function attach_value_matches( $text ) {
		$text = self::normalize( $text );
		if ( ! isset( self::$value_index[ $text ] ) ) {
			return;
		}
		foreach ( self::$value_index[ $text ] as $origin ) {
			self::record( $text, $origin );
		}
	}

	/**
	 * Scan HTML for indexed option values.
	 *
	 * @param string $html HTML.
	 * @return void
	 */
	private static function attach_value_matches_in_html( $html ) {
		$plain = self::normalize( $html );
		if ( '' === $plain ) {
			return;
		}
		foreach ( self::$value_index as $value => $origins ) {
			if ( false !== strpos( $plain, $value ) && preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $value, '/' ) . '(?![\p{L}\p{N}])/u', $plain ) ) {
				foreach ( $origins as $origin ) {
					self::record( $value, $origin );
				}
			}
		}
	}

	/**
	 * Whether an option value looks like human-visible text (not a slug, URL, id, code).
	 *
	 * @param string $value Normalized value.
	 * @return bool
	 */
	private static function is_meaningful_value( $value ) {
		if ( strlen( $value ) < 3 || ! preg_match( '/\p{L}{2,}/u', $value ) ) {
			return false;
		}
		if ( preg_match( '#^(https?:|//|/|www\.|\{|\[|a:\d)#i', $value ) ) {
			return false;
		}
		if ( preg_match( '/^[^\s@]+@[^\s@]+$/', $value ) || preg_match( '/^[A-Fa-f0-9]{8,}$/', $value ) ) {
			return false;
		}
		// Single lowercase token with no spaces = slug / key / identifier.
		if ( preg_match( '/^[a-z0-9_\-\.:]+$/', $value ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Match indexed option values against the rendered page body (visible text only).
	 *
	 * @param string $html Buffered page HTML so far.
	 * @return void
	 */
	public static function attach_from_buffer( $html ) {
		$html = (string) $html;
		$pos  = stripos( $html, '<body' );
		if ( false !== $pos ) {
			$html = substr( $html, $pos );
		}
		$html = preg_replace( '#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace( '#<[^>]+>#', ' ', (string) $html );
		self::attach_value_matches_in_html( (string) $html );
	}

	/**
	 * Record a gettext origin.
	 *
	 * @param string $translation Translated visible text.
	 * @param string $original    Original string.
	 * @param string $domain      Domain.
	 * @param string $context     Context.
	 * @return void
	 */
	private static function record_gettext( $translation, $original, $domain, $context ) {
		$visible = self::normalize( $translation );
		$orig    = self::normalize( $original );
		if ( self::is_trivial( $visible ) ) {
			return;
		}
		$likely_untranslated = self::is_secondary_lang() && $visible === $orig;
		self::record(
			$visible,
			array(
				'k' => 'gettext',
				'd' => (string) $domain,
				'o' => $orig,
				'c' => (string) $context,
				'u' => $likely_untranslated ? 1 : 0,
			)
		);
		self::attach_value_matches( $visible );
	}

	/**
	 * Gettext filter.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function on_gettext( $translation, $text, $domain ) {
		self::record_gettext( $translation, $text, $domain, '' );
		return $translation;
	}

	/**
	 * Gettext_with_context filter.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original.
	 * @param string $context     Context.
	 * @param string $domain      Domain.
	 * @return string
	 */
	public static function on_gettext_with_context( $translation, $text, $context, $domain ) {
		self::record_gettext( $translation, $text, $domain, (string) $context );
		return $translation;
	}

	/**
	 * Ngettext filter.
	 *
	 * @param string $translation Translated.
	 * @param string $single      Singular original.
	 * @param string $plural      Plural original.
	 * @param int    $number      Number.
	 * @param string $domain      Domain.
	 * @return string
	 */
	public static function on_ngettext( $translation, $single, $plural, $number, $domain ) {
		$original = ( 1 === (int) $number ) ? $single : $plural;
		self::record_gettext( $translation, $original, $domain, '' );
		return $translation;
	}

	/**
	 * Ngettext_with_context filter.
	 *
	 * @param string $translation Translated.
	 * @param string $single      Singular.
	 * @param string $plural      Plural.
	 * @param int    $number      Number.
	 * @param string $context     Context.
	 * @param string $domain      Domain.
	 * @return string
	 */
	public static function on_ngettext_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		$original = ( 1 === (int) $number ) ? $single : $plural;
		self::record_gettext( $translation, $original, $domain, (string) $context );
		return $translation;
	}

	/**
	 * The_title filter.
	 *
	 * @param string $title   Post title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function on_the_title( $title, $post_id = 0 ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			$post_id = (int) get_the_ID();
		}
		// Menu items are recorded via nav_menu_item_title with a proper fix link.
		if ( $post_id > 0 && 'nav_menu_item' === get_post_type( $post_id ) ) {
			return $title;
		}
		self::record(
			$title,
			array(
				'k'  => 'post_title',
				'id' => $post_id,
				'u'  => 0,
			)
		);
		self::attach_value_matches( $title );
		return $title;
	}

	/**
	 * The_content filter.
	 *
	 * @param string $content Content HTML.
	 * @return string
	 */
	public static function on_the_content( $content ) {
		$post_id = (int) get_the_ID();
		$plain   = self::normalize( $content );
		if ( ! self::is_trivial( $plain ) && strlen( $plain ) <= self::MAX_TEXT_LEN ) {
			self::record(
				$plain,
				array(
					'k'  => 'post_content',
					'id' => $post_id,
					'u'  => 0,
				)
			);
		}
		self::attach_value_matches_in_html( $content );
		return $content;
	}

	/**
	 * Nav menu item title.
	 *
	 * @param string   $title Menu item title.
	 * @param \WP_Post $item  Menu item.
	 * @param object   $args  Args.
	 * @param int      $depth Depth.
	 * @return string
	 */
	public static function on_nav_menu_item_title( $title, $item, $args = null, $depth = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$id = ( $item instanceof \WP_Post ) ? (int) $item->ID : 0;
		self::record(
			$title,
			array(
				'k'  => 'nav_menu_item',
				'id' => $id,
				'u'  => 0,
			)
		);
		self::attach_value_matches( $title );
		return $title;
	}

	/**
	 * Remember which sidebar widget is rendering (widget_title only receives the id base).
	 *
	 * @param mixed $widget Registered widget array from dynamic_sidebar().
	 * @return void
	 */
	public static function on_dynamic_sidebar( $widget ) {
		self::$current_widget_id = ( is_array( $widget ) && isset( $widget['id'] ) ) ? (string) $widget['id'] : '';
	}

	/**
	 * Widget title.
	 *
	 * @param string $title    Title.
	 * @param array  $instance Instance.
	 * @param mixed  $id_base  Widget id base.
	 * @return string
	 */
	public static function on_widget_title( $title, $instance = array(), $id_base = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$wid = is_string( $id_base ) ? $id_base : '';
		if ( '' !== $wid && 0 === strpos( self::$current_widget_id, $wid . '-' ) ) {
			$wid = self::$current_widget_id;
		}
		self::record(
			$title,
			array(
				'k'   => 'widget',
				'wid' => $wid,
				'u'   => 0,
			)
		);
		self::attach_value_matches( $title );
		return $title;
	}

	/**
	 * Widget text.
	 *
	 * @param string $text     Text.
	 * @param array  $instance Instance.
	 * @param mixed  $widget   Widget object.
	 * @return string
	 */
	public static function on_widget_text( $text, $instance = array(), $widget = null ) {
		// Page builders (e.g. Elementor text editor) reuse this filter without a WP_Widget; skip those.
		if ( ! $widget instanceof \WP_Widget ) {
			return $text;
		}
		$wid   = isset( $widget->id ) ? (string) $widget->id : '';
		$plain = self::normalize( $text );
		if ( ! self::is_trivial( $plain ) && strlen( $plain ) <= self::MAX_TEXT_LEN ) {
			self::record(
				$plain,
				array(
					'k'   => 'widget',
					'wid' => $wid,
					'u'   => 0,
				)
			);
		}
		self::attach_value_matches_in_html( $text );
		return $text;
	}

	/**
	 * Elementor before_render â€” stash element/document ids.
	 *
	 * @param mixed $element Elementor element.
	 * @return void
	 */
	public static function on_elementor_before_render( $element ) {
		self::$current_elementor = null;
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_id' ) ) {
			return;
		}
		$eid = (string) $element->get_id();
		$did = 0;
		if ( method_exists( $element, 'get_document' ) ) {
			$doc = $element->get_document();
			if ( is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ) {
				$did = (int) $doc->get_main_id();
			}
		}
		if ( ! $did && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
			$doc = \Elementor\Plugin::$instance->documents->get_current();
			if ( is_object( $doc ) && method_exists( $doc, 'get_main_id' ) ) {
				$did = (int) $doc->get_main_id();
			}
		}
		self::$current_elementor = array(
			'eid' => $eid,
			'did' => $did,
		);
	}

	/**
	 * Elementor widget HTML.
	 *
	 * @param string $content Widget HTML.
	 * @param mixed  $widget  Widget.
	 * @return string
	 */
	public static function on_elementor_render_content( $content, $widget = null ) {
		$eid = '';
		$did = 0;
		if ( is_array( self::$current_elementor ) ) {
			$eid = self::$current_elementor['eid'];
			$did = self::$current_elementor['did'];
		}
		if ( '' === $eid && is_object( $widget ) && method_exists( $widget, 'get_id' ) ) {
			$eid = (string) $widget->get_id();
		}
		$plain = self::normalize( $content );
		if ( ! self::is_trivial( $plain ) && strlen( $plain ) <= self::MAX_TEXT_LEN ) {
			self::record(
				$plain,
				array(
					'k'   => 'elementor',
					'eid' => $eid,
					'did' => $did,
					'u'   => 0,
				)
			);
		}
		self::attach_value_matches_in_html( $content );
		return $content;
	}
	/**
	 * Catch option_{$name} filters during inspect render to index non-autoload options.
	 *
	 * Bound to the 'all' hook only while inspect mode is active. Ignores everything
	 * except option_* value filters (not pre_option_* / default_option_*).
	 *
	 * @param string $tag   Hook name.
	 * @param mixed  $value First argument (option value for option_* filters).
	 * @return void
	 */
	public static function on_all_hooks( $tag = null, $value = null ) {
		// do_all_hook passes ( $hook_name, $value, ... ). Be defensive about arg shape.
		if ( ! is_string( $tag ) || '' === $tag ) {
			$tag = (string) current_filter();
		}
		if ( '' === $tag || 0 !== strpos( $tag, 'option_' ) ) {
			return;
		}
		// Exclude pre_option_* / default_option_* (they do not start with option_).
		$name = substr( $tag, 7 );
		if ( '' === $name ) {
			return;
		}
		if ( 0 === strpos( $name, '_' ) ) {
			return; // Internal / transient options.
		}
		$kind = ( 0 === strpos( $name, 'theme_mods_' ) ) ? 'theme_mod' : 'option';
		// Index for HTML attach, and record string leaves into the map immediately so
		// values read during shortcode/render appear in the localized payload even if
		// a later HTML attach misses an encoding variant of the same string.
		// Index only: a value is recorded when it is actually found in rendered output.
		self::walk_option_value( $value, $name, '', $kind );
	}

	/**
	 * Record string leaves from an option/theme_mod value directly into the map.
	 *
	 * @param mixed  $data  Value.
	 * @param string $name  Option name (or theme_mods_* ).
	 * @param string $path  Bracket path.
	 * @param string $kind  option|theme_mod.
	 * @param int    $depth Depth.
	 * @return void
	 */
	private static function record_option_leaves( $data, $name, $path, $kind, $depth = 0 ) {
		if ( $depth > 4 ) {
			return;
		}
		if ( is_string( $data ) ) {
			$opt_name = $name;
			$opt_path = $path;
			if ( 'theme_mod' === $kind && 0 === strpos( $name, 'theme_mods_' ) ) {
				if ( preg_match( '/^\[([^\]]+)\]/', $path, $m ) ) {
					$opt_name = $m[1];
					$opt_path = substr( $path, strlen( $m[0] ) );
				}
			}
			$origin = array(
				'k' => $kind,
				'n' => $opt_name,
				'u' => self::is_secondary_lang() ? 1 : 0,
			);
			if ( '' !== $opt_path ) {
				$origin['p'] = $opt_path;
			}
			self::record( $data, $origin );
			return;
		}
		if ( ! is_array( $data ) ) {
			return;
		}
		$count = 0;
		foreach ( $data as $key => $child ) {
			if ( $count++ > 80 ) {
				break;
			}
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			self::record_option_leaves( $child, $name, $path . '[' . $key . ']', $kind, $depth + 1 );
		}
	}
}
