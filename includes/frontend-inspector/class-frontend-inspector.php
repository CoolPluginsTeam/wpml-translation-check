<?php
/**
 * Front-end "where does this text come from" inspector.
 *
 * Administrators (manage_options) get a tiny script on every front-end view. The
 * admin-bar item toggles inspect mode in place (no reload): the script fetches the
 * same URL with ?automlp-inspect=1, where the collector builds the light origin
 * map, then marks the page. Per-click and batch resolves run through admin-ajax.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend_Inspector
 */
class Frontend_Inspector {

	const QUERY_ARG    = 'automlp-inspect';
	const HANDLE       = 'automlp-frontend-inspector';
	const AJAX_ACTION  = 'automlp_inspect_resolve';
	const SCAN_ACTION  = 'automlp_inspect_scan';
	const NONCE_ACTION = 'automlp_inspect_resolve';
	const MAP_ID       = 'automlp-fi-map';
	const SCAN_LIMIT   = 200;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_item' ), 100 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_boot' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_resolve' ) );
		add_action( 'wp_ajax_' . self::SCAN_ACTION, array( __CLASS__, 'ajax_scan' ) );
	}

	/**
	 * Whether the current user may use the inspector on the front end.
	 *
	 * @return bool
	 */
	private static function can_inspect() {
		return ! is_admin() && current_user_can( 'manage_options' ) && ! self::is_builder_context();
	}

	/**
	 * Whether this request renders inside a page builder / editor preview.
	 *
	 * The inspector must stay out of editor canvases (no assets, toggle or markers).
	 *
	 * @return bool
	 */
	public static function is_builder_context() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Presence checks only, nothing is saved.
		$builder_args = array( 'elementor-preview', 'et_fb', 'fl_builder', 'ct_builder', 'oxygen_iframe', 'vc_editable', 'vc_action', 'breakdance', 'breakdance_iframe', 'brizy-edit-iframe', 'tve', 'wp_site_preview' );
		$in_builder   = false;
		foreach ( $builder_args as $arg ) {
			if ( isset( $_GET[ $arg ] ) ) {
				$in_builder = true;
				break;
			}
		}
		if ( ! $in_builder && isset( $_GET['bricks'] ) && 'run' === sanitize_key( wp_unslash( $_GET['bricks'] ) ) ) {
			$in_builder = true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $in_builder && is_customize_preview() ) {
			$in_builder = true;
		}
		if ( ! $in_builder && class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && is_object( \Elementor\Plugin::$instance->preview ) && method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			$in_builder = true;
		}
		if ( ! $in_builder && function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			$in_builder = true;
		}
		if ( ! $in_builder && class_exists( '\\FLBuilderModel' ) && \FLBuilderModel::is_builder_active() ) {
			$in_builder = true;
		}
		if ( ! $in_builder && function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			$in_builder = true;
		}
		/**
		 * Filters whether the current front-end request is a builder / editor context
		 * where the frontend text inspector must stay disabled.
		 *
		 * @param bool $in_builder Detected value.
		 */
		return (bool) apply_filters( 'automlp_inspect_is_builder_context', $in_builder );
	}

	/**
	 * Whether inspect mode was requested on this request.
	 *
	 * @return bool
	 */
	private static function is_requested() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view toggle, gated by manage_options.
		return isset( $_GET[ self::QUERY_ARG ] ) && '1' === sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
	}

	/**
	 * Admin bar item. The href is the no-JS fallback; the script toggles in place.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar.
	 * @return void
	 */
	public static function add_admin_bar_item( $admin_bar ) {
		if ( ! $admin_bar instanceof \WP_Admin_Bar || ! self::can_inspect() ) {
			return;
		}

		$open = self::is_requested();

		$admin_bar->add_node(
			array(
				'id'    => self::HANDLE,
				'title' => $open
					? esc_html__( 'Close Text Inspector', 'wpml-translation-check' )
					: esc_html__( 'Text Inspector', 'wpml-translation-check' ),
				'href'  => $open
					? remove_query_arg( self::QUERY_ARG )
					: add_query_arg( self::QUERY_ARG, '1' ),
				'meta'  => array(
					'title' => esc_attr__( 'Find where visible text on this page comes from', 'wpml-translation-check' ),
				),
			)
		);
	}

	/**
	 * Start the collector when the map is requested (direct visit or in-place fetch).
	 *
	 * @return void
	 */
	public static function maybe_boot() {
		if ( ! self::is_requested() || ! self::can_inspect() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );

		Origin_Collector::start();
		// Container layer: invisible markers while rendering, indexed + stripped on flush.
		Container_Markers::start();
		// Buffer the page so option values can be matched against rendered body text.
		ob_start( array( Container_Markers::class, 'finish' ) );

		// Late, so everything rendered in the footer is in the map.
		add_action( 'wp_footer', array( __CLASS__, 'print_map' ), 999 );
	}

	/**
	 * Enqueue the small toggle script + CSS for administrators on any front-end view.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::can_inspect() ) {
			return;
		}

		$css_rel = 'assets/css/automlp-frontend-inspector.css';
		$js_rel  = 'assets/js/automlp-frontend-inspector.js';
		$css     = AUTOMLP_AI_PLUGIN_DIR . $css_rel;
		$js      = AUTOMLP_AI_PLUGIN_DIR . $js_rel;

		wp_enqueue_style(
			self::HANDLE,
			AUTOMLP_AI_PLUGIN_URL . $css_rel,
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : AUTOMLP_AI_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			AUTOMLP_AI_PLUGIN_URL . $js_rel,
			array(),
			file_exists( $js ) ? (string) filemtime( $js ) : AUTOMLP_AI_VERSION,
			true
		);

		$config = array(
			'queryArg'     => self::QUERY_ARG,
			'mapId'        => self::MAP_ID,
			'autoOpen'     => self::is_requested(),
			'adminBarId'   => 'wp-admin-bar-' . self::HANDLE,
			// Relative admin-ajax keeps proxy / local hostnames same-origin.
			'ajaxUrl'      => admin_url( 'admin-ajax.php', 'relative' ),
			'action'       => self::AJAX_ACTION,
			'scanAction'   => self::SCAN_ACTION,
			'containersId' => Container_Markers::DATA_ID,
			'adminUrl'     => admin_url(),
			'scanLimit'    => self::SCAN_LIMIT,
			'scanBatch'    => 40,
			'userId'       => get_current_user_id(),
			'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
			'i18n'         => self::strings(),
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.automlpFrontendInspector = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Print the collected origin map as an inert JSON data block.
	 *
	 * @return void
	 */
	public static function print_map() {
		if ( ! self::can_inspect() || ! self::is_requested() ) {
			return;
		}
		if ( ob_get_level() > 0 ) {
			Origin_Collector::attach_from_buffer( (string) ob_get_contents() );
		}
		$json = wp_json_encode( Origin_Collector::get_payload(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return;
		}
		wp_print_inline_script_tag(
			$json,
			array(
				'type' => 'application/json',
				'id'   => self::MAP_ID,
			)
		);
	}

	/**
	 * Nonce + capability gate shared by both AJAX actions. Exits on failure.
	 *
	 * @return void
	 */
	private static function verify_ajax() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid security token.', 'wpml-translation-check' ) ),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Forbidden.', 'wpml-translation-check' ) ),
				403
			);
		}
	}

	/**
	 * Admin-ajax runs in the admin language; switch to the inspected page language.
	 *
	 * Only an active WPML language code is accepted. Nonce is verified by the caller.
	 *
	 * @return void
	 */
	private static function switch_to_page_language() {
		$lang = isset( $_POST['page_lang'] ) ? sanitize_key( wp_unslash( $_POST['page_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify_ajax().
		if ( '' === $lang ) {
			return;
		}
		$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		if ( is_array( $active ) && isset( $active[ $lang ] ) ) {
			do_action( 'wpml_switch_language', $lang );
		}
	}

	/**
	 * AJAX: resolve one clicked text.
	 *
	 * @return void
	 */
	public static function ajax_resolve() {
		self::verify_ajax();

		$text = isset( $_POST['text'] ) ? Origin_Collector::normalize( wp_unslash( $_POST['text'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax(); Normalized (tags stripped) by normalize().
		if ( '' === $text ) {
			wp_send_json_error(
				array( 'message' => __( 'Empty text.', 'wpml-translation-check' ) ),
				400
			);
		}

		$hint = self::read_hint_from_request();

		self::switch_to_page_language();

		wp_send_json_success( Origin_Resolver::resolve( $text, $hint ) );
	}

	/**
	 * AJAX: resolve many visible texts at once for the untranslated sidebar.
	 *
	 * Expects `items` = JSON array of { t: text, h: hint }. Capped at SCAN_LIMIT.
	 *
	 * @return void
	 */
	public static function ajax_scan() {
		self::verify_ajax();

		$raw   = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax(); JSON decoded; each field sanitized below.
		$items = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $items ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid scan request.', 'wpml-translation-check' ) ),
				400
			);
		}

		self::switch_to_page_language();

		$keep    = array( 'found', 'translated', 'same_as_default', 'source_type', 'source_type_label', 'status_label', 'identifiers', 'fix_url', 'fix_label', 'message' );
		$items   = array_slice( array_values( $items ), 0, self::SCAN_LIMIT );
		$entries = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['t'] ) && is_string( $item['t'] ) ) {
				$hint      = ( isset( $item['h'] ) && is_array( $item['h'] ) ) ? self::sanitize_hint( $item['h'] ) : array();
				$entries[] = array( Origin_Collector::normalize( $item['t'] ), $hint );
			}
		}
		// One query per source table for the whole batch.
		Origin_Resolver::prime( $entries );

		$results = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item['t'] ) || ! is_string( $item['t'] ) ) {
				$results[] = null;
				continue;
			}
			$text = Origin_Collector::normalize( $item['t'] );
			if ( '' === $text ) {
				$results[] = null;
				continue;
			}
			$hint      = ( isset( $item['h'] ) && is_array( $item['h'] ) ) ? self::sanitize_hint( $item['h'] ) : array();
			$resolved  = Origin_Resolver::resolve( $text, $hint );
			$results[] = array_intersect_key( $resolved, array_flip( $keep ) ) + array( 'i' => $index );
		}

		wp_send_json_success(
			array(
				'results' => $results,
				'total'   => count( $items ),
				'limit'   => self::SCAN_LIMIT,
			)
		);
	}

	/**
	 * Read origin hint from POST (JSON hint and/or flat hint_* fields).
	 *
	 * @return array<string, mixed>
	 */
	private static function read_hint_from_request() {
		$hint = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax() before this helper runs.

		$hint_raw = isset( $_POST['hint'] ) ? wp_unslash( $_POST['hint'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded/sanitized below.
		if ( is_string( $hint_raw ) && '' !== $hint_raw ) {
			$decoded = json_decode( $hint_raw, true );
			if ( ! is_array( $decoded ) ) {
				$decoded = json_decode( html_entity_decode( $hint_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
			}
			if ( is_array( $decoded ) ) {
				$hint = self::sanitize_hint( $decoded );
			}
		} elseif ( is_array( $hint_raw ) ) {
			$hint = self::sanitize_hint( $hint_raw );
		}

		// Flat fields survive when JSON hint is mangled by proxies or attribute encoding.
		$flat = array();
		if ( isset( $_POST['hint_k'] ) ) {
			$flat['k'] = sanitize_key( wp_unslash( $_POST['hint_k'] ) );
		}
		foreach ( array( 'd', 'c', 'n', 'p', 'eid', 'wid', 'tax' ) as $key ) {
			$post_key = 'hint_' . $key;
			if ( isset( $_POST[ $post_key ] ) ) {
				$flat[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			}
		}
		if ( isset( $_POST['hint_o'] ) ) {
			// Gettext originals may include intentional HTML / placeholders — keep them.
			$flat['o'] = self::sanitize_gettext_original( wp_unslash( $_POST['hint_o'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_gettext_original().
		}
		foreach ( array( 'id', 'did', 'u' ) as $key ) {
			$post_key = 'hint_' . $key;
			if ( isset( $_POST[ $post_key ] ) ) {
				$flat[ $key ] = absint( wp_unslash( $_POST[ $post_key ] ) );
			}
		}
		if ( ! empty( $flat['k'] ) ) {
			$hint = array_merge( $hint, $flat );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $hint;
	}

	/**
	 * Sanitize a light map hint from POST.
	 *
	 * @param array<string, mixed> $hint Raw hint.
	 * @return array<string, mixed>
	 */
	private static function sanitize_hint( array $hint ) {
		$out = array();
		if ( isset( $hint['k'] ) && is_scalar( $hint['k'] ) ) {
			$out['k'] = sanitize_key( (string) $hint['k'] );
		}
		foreach ( array( 'd', 'c', 'n', 'p', 'eid', 'wid', 'tax' ) as $key ) {
			if ( isset( $hint[ $key ] ) && is_scalar( $hint[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( (string) $hint[ $key ] );
			}
		}
		if ( isset( $hint['o'] ) && is_scalar( $hint['o'] ) ) {
			$out['o'] = self::sanitize_gettext_original( (string) $hint['o'] );
		}
		foreach ( array( 'id', 'did', 'u' ) as $key ) {
			if ( isset( $hint[ $key ] ) && is_scalar( $hint[ $key ] ) ) {
				$out[ $key ] = absint( $hint[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * Sanitize a gettext original that may contain HTML placeholders.
	 *
	 * Calling sanitize_text_field() would strip tags and break icl_strings lookups.
	 *
	 * @param string $value Raw original.
	 * @return string
	 */
	private static function sanitize_gettext_original( $value ) {
		$value = wp_check_invalid_utf8( (string) $value );
		$value = str_replace( "\0", '', $value );
		if ( strlen( $value ) > 2000 ) {
			$value = substr( $value, 0, 2000 );
		}
		return $value;
	}

	/**
	 * Panel copy.
	 *
	 * @return array<string, string>
	 */
	private static function strings() {
		return array(
			'title'          => __( 'Text Origin Inspector', 'wpml-translation-check' ),
			'close'          => __( 'Close', 'wpml-translation-check' ),
			'openLabel'      => __( 'Text Inspector', 'wpml-translation-check' ),
			'closeLabel'     => __( 'Close Text Inspector', 'wpml-translation-check' ),
			'hint'           => __( 'Click any text on the page, or an item below, to see where it comes from.', 'wpml-translation-check' ),
			'loading'        => __( 'Resolving…', 'wpml-translation-check' ),
			'loadingMap'     => __( 'Collecting text origins for this page…', 'wpml-translation-check' ),
			'mapError'       => __( 'Could not load the origin map for this page.', 'wpml-translation-check' ),
			'error'          => __( 'Could not resolve this text.', 'wpml-translation-check' ),
			'source'         => __( 'Source', 'wpml-translation-check' ),
			'status'         => __( 'Status', 'wpml-translation-check' ),
			'identifiers'    => __( 'Identifiers', 'wpml-translation-check' ),
			'fix'            => __( 'Fix', 'wpml-translation-check' ),
			'selected'       => __( 'Selected text', 'wpml-translation-check' ),
			'untranslated'   => __( 'Likely untranslated', 'wpml-translation-check' ),
			'openFix'        => __( 'Open fix link', 'wpml-translation-check' ),
			'alsoPossible'   => __( 'Also possible', 'wpml-translation-check' ),
			'noHint'         => __( 'No collector hint matched; resolver may still find a source.', 'wpml-translation-check' ),
			'scanTitle'      => __( 'Untranslated on this page', 'wpml-translation-check' ),
			'scanning'       => __( 'Checking visible strings…', 'wpml-translation-check' ),
			/* translators: 1: untranslated count, 2: scanned count */
			'scanSummary'    => __( '%1$d untranslated of %2$d checked', 'wpml-translation-check' ),
			/* translators: %d: maximum number of strings checked */
			'scanCapped'     => __( 'Only the first %d visible strings were checked.', 'wpml-translation-check' ),
			'scanEmpty'      => __( 'No untranslated strings found on this page.', 'wpml-translation-check' ),
			'scanError'      => __( 'Could not check strings on this page.', 'wpml-translation-check' ),
			'notFound'       => __( 'Source unknown', 'wpml-translation-check' ),
			'container'      => __( 'Rendered by', 'wpml-translation-check' ),
			'region'         => __( 'Theme template', 'wpml-translation-check' ),
			'edit'           => __( 'Edit', 'wpml-translation-check' ),
			'byJs'           => __( 'Added by JavaScript', 'wpml-translation-check' ),
			'byJsStatus'     => __( 'Not in the server HTML: inserted in the browser after load.', 'wpml-translation-check' ),
			'containerOnly'  => __( 'Not registered as a translatable string; edit it in the source above.', 'wpml-translation-check' ),
			'controlOptions' => __( 'Options in this control', 'wpml-translation-check' ),
			'resizeHandle'   => __( 'Drag to resize panel. Arrow keys adjust width.', 'wpml-translation-check' ),
		);
	}
}
