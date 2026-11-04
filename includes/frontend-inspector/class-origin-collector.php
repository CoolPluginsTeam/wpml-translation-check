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
	 * Gettext recording phase: e = early (init/registration), r = render (template output).
	 *
	 * @var string
	 */
	private static $phase = 'e';

	/**
	 * Monotonic gettext sequence (later calls override earlier ones for the same text).
	 *
	 * @var int
	 */
	private static $gettext_seq = 0;

	/**
	 * Gettext patterns for strings with printf placeholders / HTML that render differently.
	 *
	 * Each entry: re (JS-safe regex body), plus the same hint fields as map entries (k/d/o/c/u).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $patterns = array();

	/**
	 * Guard against re-entrancy while recording a term (get_term lookups).
	 *
	 * @var bool
	 */
	private static $recording_term = false;

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

		// maybe_boot() already runs on template_redirect, so this request is rendering.
		// Also arm the hook in case start() is ever called earlier.
		self::$phase = did_action( 'template_redirect' ) ? 'r' : 'e';
		add_action( 'template_redirect', array( __CLASS__, 'enter_render_phase' ), 0 );

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

		// Taxonomy term names (any taxonomy): cheap core filters only in inspect mode.
		add_filter( 'get_term', array( __CLASS__, 'on_get_term' ), 9999, 2 );
		add_filter( 'get_terms', array( __CLASS__, 'on_get_terms' ), 9999, 4 );
		add_filter( 'get_the_terms', array( __CLASS__, 'on_get_the_terms' ), 9999, 3 );
		add_filter( 'wp_get_object_terms', array( __CLASS__, 'on_wp_get_object_terms' ), 9999, 4 );
		add_filter( 'list_cats', array( __CLASS__, 'on_list_cats' ), 9999, 2 );
		add_filter( 'single_cat_title', array( __CLASS__, 'on_single_term_title' ), 9999, 1 );
		add_filter( 'single_tag_title', array( __CLASS__, 'on_single_term_title' ), 9999, 1 );
		add_filter( 'single_term_title', array( __CLASS__, 'on_single_term_title' ), 9999, 1 );
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
			'term'          => 1,
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
				foreach ( array( 'd', 'o', 'c', 'n', 'p', 'id', 'eid', 'did', 'wid', 'ph', 'cd', 'cs', 'sq', 'tax' ) as $key ) {
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

		$patterns = self::$patterns;
		if ( count( $patterns ) > 200 ) {
			$patterns = array_slice( $patterns, 0, 200 );
		}

		return array(
			'entries'     => $entries,
			'patterns'    => $patterns,
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
			array( 'option', 'theme_mod', 'post_title', 'post_content', 'nav_menu_item', 'widget', 'elementor', 'term' ),
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
		foreach ( self::$map[ $text ] as $idx => $existing ) {
			if ( wp_json_encode( $existing ) === $sig ) {
				return;
			}
			// Same term recorded twice with different u — keep the untranslated flag.
			if (
				'term' === $kind
				&& isset( $existing['k'], $existing['id'], $origin['id'] )
				&& 'term' === $existing['k']
				&& (int) $existing['id'] === (int) $origin['id']
				&& (string) ( isset( $existing['tax'] ) ? $existing['tax'] : '' ) === (string) ( isset( $origin['tax'] ) ? $origin['tax'] : '' )
			) {
				if ( ! empty( $origin['u'] ) ) {
					self::$map[ $text ][ $idx ]['u'] = 1;
				}
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
	 * Whether a gettext string contains printf-style placeholders.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function has_printf_placeholder( $text ) {
		return (bool) preg_match( '/%(?:\d+\$)?[sdFcCOPuxX]/', (string) $text );
	}

	/**
	 * Whether a gettext string contains HTML tags.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function has_html_markup( $text ) {
		return (bool) preg_match( '/<[^>]+>/', (string) $text );
	}

	/**
	 * Build a JS-safe regex body that matches the rendered (sprintf + strip-tags) form.
	 *
	 * Placeholders become .+?; whitespace is flexible. Returns empty when no
	 * placeholder survives tag-stripping (exact map key is enough then).
	 *
	 * @param string $translation Gettext return value (may include HTML / %s).
	 * @return string
	 */
	private static function build_match_pattern( $translation ) {
		$raw = (string) $translation;
		if ( strlen( $raw ) > self::MAX_TEXT_LEN * 2 ) {
			return '';
		}
		// Sentinel keeps placeholders that live inside HTML attributes through strip_tags.
		$marked = preg_replace( '/%(?:\d+\$)?[sdFcCOPuxX]/', "\x01", $raw );
		$marked = wp_strip_all_tags( (string) $marked );
		$marked = html_entity_decode( $marked, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$marked = preg_replace( '/\s+/u', ' ', $marked );
		$marked = trim( (string) $marked );
		if ( '' === $marked || false === strpos( $marked, "\x01" ) ) {
			return '';
		}
		if ( strlen( $marked ) > self::MAX_TEXT_LEN + 32 ) {
			return '';
		}
		$parts = explode( "\x01", $marked );
		$re    = '';
		foreach ( $parts as $i => $part ) {
			if ( $i > 0 ) {
				$re .= '.+?';
			}
			$re .= preg_quote( $part, '/' );
		}
		$re = preg_replace( '/\s+/', '\\s+', $re );
		return (string) $re;
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
		// Keep the raw gettext original (HTML + placeholders) for icl_strings lookup.
		$raw_orig = (string) $original;
		if ( strlen( $raw_orig ) > 2000 ) {
			$raw_orig = substr( $raw_orig, 0, 2000 );
		}
		$orig_norm = self::normalize( $raw_orig );
		if ( self::is_trivial( $visible ) && self::is_trivial( $orig_norm ) ) {
			return;
		}
		$likely_untranslated = self::is_secondary_lang() && '' !== $visible && $visible === $orig_norm;
		$hint                = array(
			'k'  => 'gettext',
			'd'  => (string) $domain,
			'o'  => $raw_orig,
			'c'  => (string) $context,
			'u'  => $likely_untranslated ? 1 : 0,
			'ph' => self::$phase,
			'sq' => ++self::$gettext_seq,
		);
		// Caller text domain + whether the frame is a theme or plugin (generic path signals).
		$caller = self::detect_caller();
		if ( ! empty( $caller['d'] ) ) {
			$hint['cd'] = $caller['d'];
		}
		if ( ! empty( $caller['cs'] ) ) {
			$hint['cs'] = $caller['cs'];
		}

		if ( ! self::is_trivial( $visible ) ) {
			self::record( $visible, $hint );
			self::attach_value_matches( $visible );
		}

		// Pattern for sprintf/HTML gettext: rendered block text may differ from the map key.
		if ( self::has_printf_placeholder( $translation ) || self::has_printf_placeholder( $raw_orig ) || self::has_html_markup( $translation ) || self::has_html_markup( $raw_orig ) ) {
			$re = self::build_match_pattern( $translation );
			if ( '' === $re ) {
				// Placeholders only lived in attributes; stripped visible text already matches the block.
				return;
			}
			if ( count( self::$patterns ) >= 200 ) {
				return;
			}
			foreach ( self::$patterns as $existing ) {
				if ( isset( $existing['re'] ) && $existing['re'] === $re && isset( $existing['d'] ) && $existing['d'] === $hint['d'] ) {
					return;
				}
			}
			self::$patterns[] = array_merge(
				array( 're' => $re ),
				$hint
			);
		}
	}


	/**
	 * Mark that template rendering has begun (vs init/registration).
	 *
	 * @return void
	 */
	public static function enter_render_phase() {
		self::$phase = 'r';
	}

	/**
	 * Resolve a PHP file path to its theme/plugin text domain (dynamic, no name lists).
	 *
	 * Accepts absolute paths or paths relative to wp-content. Uses the theme/plugin
	 * header Text Domain when present, otherwise the directory slug (WP convention).
	 *
	 * @param string $path File path.
	 * @return string Empty when outside themes/plugins.
	 */
	public static function textdomain_for_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}
		// Drop optional :line suffix from shortcode locations.
		if ( preg_match( '/:\d+$/', $path ) ) {
			$path = (string) preg_replace( '/:\d+$/', '', $path );
		}
		$path = wp_normalize_path( $path );

		static $cache = array();
		if ( isset( $cache[ $path ] ) ) {
			return $cache[ $path ];
		}

		$content = wp_normalize_path( WP_CONTENT_DIR );
		$abs     = $path;
		if ( 0 !== strpos( $abs, $content ) ) {
			// Relative to wp-content (e.g. themes/foo/bar.php).
			if ( 0 === strpos( $abs, 'themes/' ) || 0 === strpos( $abs, 'plugins/' ) || 0 === strpos( $abs, 'mu-plugins/' ) ) {
				$abs = wp_normalize_path( trailingslashit( $content ) . ltrim( $abs, '/' ) );
			}
		}

		$td      = '';
		$plugins = wp_normalize_path( WP_PLUGIN_DIR );
		$themes  = wp_normalize_path( get_theme_root() );
		$mu      = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';

		if ( 0 === strpos( $abs, trailingslashit( $plugins ) ) ) {
			$rel  = substr( $abs, strlen( trailingslashit( $plugins ) ) );
			$slug = strtok( $rel, '/' );
			$td   = self::domain_for_plugin_slug( (string) $slug );
		} elseif ( '' !== $mu && 0 === strpos( $abs, trailingslashit( $mu ) ) ) {
			$rel  = substr( $abs, strlen( trailingslashit( $mu ) ) );
			$slug = strtok( $rel, '/' );
			$td   = self::domain_for_mu_slug( (string) $slug, $mu );
		} elseif ( 0 === strpos( $abs, trailingslashit( $themes ) ) ) {
			$rel   = substr( $abs, strlen( trailingslashit( $themes ) ) );
			$slug  = strtok( $rel, '/' );
			$theme = wp_get_theme( (string) $slug );
			if ( $theme instanceof \WP_Theme && $theme->exists() ) {
				$td = (string) $theme->get( 'TextDomain' );
			}
			if ( '' === $td ) {
				$td = (string) $slug;
			}
		}

		$cache[ $path ] = $td;
		return $td;
	}

	/**
	 * Text domain for a plugin directory slug (from plugin header, else slug).
	 *
	 * @param string $slug Plugin directory (or single-file stem).
	 * @return string
	 */
	private static function domain_for_plugin_slug( $slug ) {
		if ( '' === $slug ) {
			return '';
		}
		$map = self::plugin_domain_map();
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		return $slug;
	}

	/**
	 * Build slug -> TextDomain map from installed plugins (cached per request).
	 *
	 * @return array<string, string>
	 */
	private static function plugin_domain_map() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			return $map;
		}
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( (string) $file );
			if ( '.' === $slug || '' === $slug ) {
				$slug = (string) pathinfo( (string) $file, PATHINFO_FILENAME );
			}
			$header       = ( is_array( $data ) && ! empty( $data['TextDomain'] ) ) ? (string) $data['TextDomain'] : '';
			$map[ $slug ] = '' !== $header ? $header : $slug;
		}
		return $map;
	}

	/**
	 * Text domain for a must-use plugin file or directory.
	 *
	 * @param string $slug First path segment under mu-plugins.
	 * @param string $mu   Absolute mu-plugins directory.
	 * @return string
	 */
	private static function domain_for_mu_slug( $slug, $mu ) {
		if ( '' === $slug ) {
			return '';
		}
		$base = trailingslashit( $mu ) . $slug;
		$file = is_dir( $base ) ? $base : $base;
		if ( is_dir( $base ) ) {
			$files = glob( trailingslashit( $base ) . '*.php' );
			$file  = ( is_array( $files ) && $files ) ? $files[0] : '';
		} elseif ( ! preg_match( '/\.php$/i', $slug ) ) {
			$file = $base . '.php';
		}
		if ( is_string( $file ) && is_readable( $file ) && function_exists( 'get_plugin_data' ) ) {
			$data = get_plugin_data( $file, false, false );
			if ( is_array( $data ) && ! empty( $data['TextDomain'] ) ) {
				return (string) $data['TextDomain'];
			}
		} elseif ( is_string( $file ) && is_readable( $file ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( function_exists( 'get_plugin_data' ) ) {
				$data = get_plugin_data( $file, false, false );
				if ( is_array( $data ) && ! empty( $data['TextDomain'] ) ) {
					return (string) $data['TextDomain'];
				}
			}
		}
		return (string) pathinfo( $slug, PATHINFO_FILENAME );
	}

	/**
	 * Walk a short backtrace to the first theme/plugin frame.
	 *
	 * @return array{d:string,cs:string} d = text domain, cs = t (theme) | p (plugin) | ''.
	 */
	private static function detect_caller() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Inspect-mode only; bounded depth.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 16 );
		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) || ! is_string( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( false !== strpos( $file, '/wp-includes/' ) || false !== strpos( $file, '/wp-admin/' ) ) {
				continue;
			}
			// Skip this inspector plugin's own frames.
			if ( false !== strpos( $file, '/wpml-translation-check/' ) ) {
				continue;
			}
			$info = self::caller_info_for_path( $file );
			if ( '' !== $info['d'] ) {
				return $info;
			}
		}
		return array(
			'd'  => '',
			'cs' => '',
		);
	}

	/**
	 * Text domain + theme/plugin classification for a file path.
	 *
	 * @param string $path File path.
	 * @return array{d:string,cs:string}
	 */
	private static function caller_info_for_path( $path ) {
		$td = self::textdomain_for_path( $path );
		if ( '' === $td ) {
			return array(
				'd'  => '',
				'cs' => '',
			);
		}
		$abs     = wp_normalize_path( $path );
		$content = wp_normalize_path( WP_CONTENT_DIR );
		if ( 0 !== strpos( $abs, $content ) && ( 0 === strpos( $abs, 'themes/' ) || 0 === strpos( $abs, 'plugins/' ) || 0 === strpos( $abs, 'mu-plugins/' ) ) ) {
			$abs = wp_normalize_path( trailingslashit( $content ) . ltrim( $abs, '/' ) );
		}
		$cs      = '';
		$themes  = wp_normalize_path( get_theme_root() );
		$plugins = wp_normalize_path( WP_PLUGIN_DIR );
		$mu      = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
		if ( 0 === strpos( $abs, trailingslashit( $themes ) ) || 0 === strpos( $abs, 'themes/' ) ) {
			$cs = 't';
		} elseif ( 0 === strpos( $abs, trailingslashit( $plugins ) ) || 0 === strpos( $abs, 'plugins/' ) || ( '' !== $mu && 0 === strpos( $abs, trailingslashit( $mu ) ) ) || 0 === strpos( $abs, 'mu-plugins/' ) ) {
			$cs = 'p';
		}
		return array(
			'd'  => $td,
			'cs' => $cs,
		);
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
	 * Record a taxonomy term name for the origin map.
	 *
	 * @param \WP_Term $term Term object.
	 * @return void
	 */
	private static function record_term( $term ) {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$name = isset( $term->name ) ? (string) $term->name : '';
		if ( '' === self::normalize( $name ) ) {
			return;
		}

		$taxonomy = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
		$term_id  = isset( $term->term_id ) ? (int) $term->term_id : 0;
		if ( $term_id <= 0 || '' === $taxonomy ) {
			return;
		}

		self::record(
			$name,
			array(
				'k'   => 'term',
				'id'  => $term_id,
				'tax' => $taxonomy,
				'u'   => self::term_likely_untranslated( $term ) ? 1 : 0,
			)
		);
	}

	/**
	 * Whether a term name is likely untranslated on a secondary language page.
	 *
	 * @param \WP_Term $term Term.
	 * @return bool
	 */
	/**
	 * Read a term name as stored for a given language (WPML filters get_term otherwise).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $lang     Language code.
	 * @return string Normalized name or empty.
	 */
	private static function term_name_in_language( $term_id, $taxonomy, $lang ) {
		$term_id  = (int) $term_id;
		$taxonomy = (string) $taxonomy;
		$lang     = (string) $lang;
		if ( $term_id <= 0 || '' === $taxonomy || '' === $lang ) {
			return '';
		}

		$was_rec              = self::$recording_term;
		self::$recording_term = true;
		$prev_lang            = (string) apply_filters( 'wpml_current_language', null );
		do_action( 'wpml_switch_language', $lang );
		$term = get_term( $term_id, $taxonomy );
		if ( '' !== $prev_lang ) {
			do_action( 'wpml_switch_language', $prev_lang );
		}
		self::$recording_term = $was_rec;

		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		return self::normalize( $term->name );
	}

	/**
	 * Whether a term is positively untranslated on a secondary-language page.
	 *
	 * Unknown / inconclusive is treated as translated (not flagged).
	 *
	 * @param \WP_Term $term Term instance from the current page.
	 * @return bool
	 */
	private static function term_likely_untranslated( $term ) {
		if ( ! self::is_secondary_lang() ) {
			return false;
		}
		$taxonomy = (string) $term->taxonomy;
		$is_trans = (bool) apply_filters( 'wpml_is_translated_taxonomy', false, $taxonomy );
		if ( ! $is_trans ) {
			return false;
		}

		$term_id    = (int) $term->term_id;
		$source_id  = (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, self::$default_lang );
		$current_id = (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, false, self::$current_lang );

		// Positive evidence only: missing translation object, or fallback to source object.
		if ( $current_id <= 0 ) {
			return true;
		}
		if ( $source_id > 0 && $current_id === $source_id ) {
			return true;
		}
		// Displaying the default-language term itself on a secondary page.
		if ( $source_id > 0 && $source_id === $term_id && $current_id === $term_id ) {
			return true;
		}
		if ( $source_id <= 0 ) {
			return false;
		}

		$src = self::term_name_in_language( $source_id, $taxonomy, self::$default_lang );
		$cur = self::normalize( $term->name );
		if ( '' === $src || '' === $cur ) {
			return false;
		}
		return ( $src === $cur );
	}

	/**
	 * Get_term filter - any taxonomy.
	 *
	 * @param \WP_Term|array|\WP_Error|null $term     Term.
	 * @param string                        $taxonomy Taxonomy.
	 * @return \WP_Term|array|\WP_Error|null
	 */
	public static function on_get_term( $term, $taxonomy = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$recording_term || ! $term instanceof \WP_Term ) {
			return $term;
		}
		self::$recording_term = true;
		self::record_term( $term );
		self::$recording_term = false;
		return $term;
	}

	/**
	 * Get_terms filter - record each WP_Term in the result set.
	 *
	 * @param array|\WP_Error $terms      Terms.
	 * @param array|null      $taxonomies Taxonomies.
	 * @param array           $args       Query args.
	 * @param \WP_Term_Query  $term_query Query.
	 * @return array|\WP_Error
	 */
	public static function on_get_terms( $terms, $taxonomies = null, $args = null, $term_query = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$recording_term || is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $terms;
		}
		self::$recording_term = true;
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				self::record_term( $term );
			}
		}
		self::$recording_term = false;
		return $terms;
	}

	/**
	 * Get_the_terms filter.
	 *
	 * @param array|\WP_Error|false $terms    Terms.
	 * @param int                   $post_id  Post ID.
	 * @param string                $taxonomy Taxonomy.
	 * @return array|\WP_Error|false
	 */
	public static function on_get_the_terms( $terms, $post_id = 0, $taxonomy = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$recording_term || ! is_array( $terms ) ) {
			return $terms;
		}
		self::$recording_term = true;
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				self::record_term( $term );
			}
		}
		self::$recording_term = false;
		return $terms;
	}

	/**
	 * Wp_get_object_terms filter.
	 *
	 * @param array|\WP_Error $terms      Terms.
	 * @param int|array       $object_ids Object IDs.
	 * @param string|array    $taxonomies Taxonomies.
	 * @param array           $args       Args.
	 * @return array|\WP_Error
	 */
	public static function on_wp_get_object_terms( $terms, $object_ids = null, $taxonomies = null, $args = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$recording_term || is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $terms;
		}
		self::$recording_term = true;
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				self::record_term( $term );
			} elseif ( is_string( $term ) && '' !== self::normalize( $term ) ) {
				// fields=names: no id available - skip (cannot build a fix link).
				continue;
			}
		}
		self::$recording_term = false;
		return $terms;
	}

	/**
	 * List_cats filter (category / taxonomy list walkers).
	 *
	 * @param string        $name Category name.
	 * @param \WP_Term|null $category Term or null for option labels.
	 * @return string
	 */
	public static function on_list_cats( $name, $category = null ) {
		if ( $category instanceof \WP_Term ) {
			self::record_term( $category );
		}
		return $name;
	}

	/**
	 * Single_cat_title / single_tag_title / single_term_title.
	 *
	 * @param string $title Term title.
	 * @return string
	 */
	public static function on_single_term_title( $title ) {
		$obj = get_queried_object();
		if ( $obj instanceof \WP_Term ) {
			// Prefer queried term id/taxonomy; use filtered title as visible text.
			$clone       = clone $obj;
			$clone->name = (string) $title;
			self::record_term( $clone );
		}
		return $title;
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
