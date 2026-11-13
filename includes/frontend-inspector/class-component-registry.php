<?php
/**
 * Text domain -> owning component (plugin / theme / WordPress core) and WPML admin routes.
 *
 * Built only from installed plugin/theme headers (Text Domain) so it works on any site.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component_Registry
 */
class Component_Registry {

	/**
	 * Domain => component cache.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private static $domains = null;

	/**
	 * Build the text-domain map from active plugins and the active theme (+ parent).
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function domains() {
		if ( null !== self::$domains ) {
			return self::$domains;
		}
		self::$domains = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$active = array_flip( $active );
		foreach ( (array) get_plugins() as $file => $data ) {
			$slug = dirname( (string) $file );
			if ( '.' === $slug || '' === $slug ) {
				$slug = (string) pathinfo( (string) $file, PATHINFO_FILENAME );
			}
			$domain = ( is_array( $data ) && ! empty( $data['TextDomain'] ) ) ? (string) $data['TextDomain'] : $slug;
			$entry  = array(
				'type'   => 'plugin',
				'name'   => ( is_array( $data ) && ! empty( $data['Name'] ) ) ? wp_strip_all_tags( (string) $data['Name'] ) : $slug,
				'slug'   => $slug,
				'file'   => (string) $file,
				'domain' => $domain,
				'active' => isset( $active[ $file ] ) ? '1' : '0',
			);
			// Active plugins win over inactive ones that share a domain.
			if ( ! isset( self::$domains[ $domain ] ) || '1' === $entry['active'] ) {
				self::$domains[ $domain ] = $entry;
			}
		}

		$themes = array( wp_get_theme() );
		if ( $themes[0]->parent() ) {
			$themes[] = $themes[0]->parent();
		}
		foreach ( $themes as $theme ) {
			if ( ! $theme instanceof \WP_Theme || ! $theme->exists() ) {
				continue;
			}
			$domain = (string) $theme->get( 'TextDomain' );
			if ( '' === $domain ) {
				$domain = (string) $theme->get_stylesheet();
			}
			self::$domains[ $domain ] = array(
				'type'   => 'theme',
				'name'   => wp_strip_all_tags( (string) $theme->get( 'Name' ) ),
				'slug'   => (string) $theme->get_stylesheet(),
				'file'   => '',
				'domain' => $domain,
				'active' => '1',
			);
		}

		return self::$domains;
	}

	/**
	 * Component that owns a text domain.
	 *
	 * Handles WPML JS domains ("{domain}-{script-handle}") and admin_texts_* contexts.
	 *
	 * @param string $domain Text domain / ST context.
	 * @return array<string, string> type, name, slug, domain (empty array when unknown).
	 */
	public static function for_domain( $domain ) {
		$domain = (string) $domain;
		if ( '' === $domain ) {
			return array();
		}
		if ( 'default' === $domain || 'WordPress' === $domain ) {
			return array(
				'type'   => 'core',
				'name'   => 'WordPress',
				'slug'   => 'wordpress',
				'domain' => 'default',
			);
		}
		$map = self::domains();
		if ( isset( $map[ $domain ] ) ) {
			return $map[ $domain ];
		}
		// WPML JED context "{domain}-{handle}": longest registered prefix wins.
		$best = '';
		foreach ( array_keys( $map ) as $known ) {
			$known = (string) $known;
			if ( strlen( $known ) > strlen( $best ) && 0 === strpos( $domain, $known . '-' ) ) {
				$best = $known;
			}
		}
		if ( '' !== $best ) {
			return $map[ $best ];
		}
		return array();
	}

	/**
	 * Component for a theme/plugin file path (template regions, script src).
	 *
	 * @param string $path Absolute path or path relative to wp-content.
	 * @return array<string, string>
	 */
	public static function for_path( $path ) {
		$domain = Origin_Collector::textdomain_for_path( (string) $path );
		return '' === $domain ? array() : self::for_domain( $domain );
	}

	/**
	 * Human label like "Plugin: WooCommerce" / "Theme: Astra" / "WordPress".
	 *
	 * @param array<string, string> $component Component.
	 * @return string
	 */
	public static function label( array $component ) {
		if ( empty( $component['name'] ) ) {
			return '';
		}
		$name = (string) $component['name'];
		$type = isset( $component['type'] ) ? (string) $component['type'] : '';
		if ( 'plugin' === $type ) {
			/* translators: %s: plugin name */
			return sprintf( __( 'Plugin: %s', 'wpml-translation-check' ), $name );
		}
		if ( 'theme' === $type ) {
			/* translators: %s: theme name */
			return sprintf( __( 'Theme: %s', 'wpml-translation-check' ), $name );
		}
		return $name;
	}

	/**
	 * Plain name for use inside a sentence ("Built into Astra").
	 *
	 * @param array<string, string> $component Component.
	 * @return string
	 */
	public static function name( array $component ) {
		return empty( $component['name'] ) ? '' : (string) $component['name'];
	}

	/**
	 * WPML "Theme and plugins localization" screen (legacy slug redirects on WPML 5).
	 *
	 * @return string
	 */
	public static function localization_url() {
		if ( self::has_admin_texts_page() ) {
			// WPML 5+: the theme/plugin scan lives on the Admin Texts page ("Not seeing an admin text you need?").
			return admin_url( 'admin.php?page=' . self::ADMIN_TEXTS_PAGE ) . '#wpml-st-localization';
		}
		$folder = defined( 'WPML_PLUGIN_FOLDER' ) ? WPML_PLUGIN_FOLDER : 'sitepress-multilingual-cms';
		return admin_url( 'admin.php?page=' . $folder . '/menu/theme-localization.php' );
	}

	/**
	 * WPML 5+ admin page slug for Admin Texts (also hosts the theme/plugin strings scan).
	 */
	const ADMIN_TEXTS_PAGE = 'wpml-admin-texts-translation';

	/**
	 * Whether this WPML version has the new Admin Texts page (legacy theme-localization.php
	 * redirects to Settings > Compatibility there, which no longer has the scan).
	 *
	 * @return bool
	 */
	public static function has_admin_texts_page() {
		return class_exists( '\WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController' );
	}

	/**
	 * WPML Admin Texts screen ("Translate texts in admin screens").
	 *
	 * @param string $option_name Optional option name, passed as a search hint.
	 * @return string
	 */
	public static function admin_texts_url( $option_name = '' ) {
		$folder = defined( 'WPML_ST_FOLDER' ) ? WPML_ST_FOLDER : 'wpml-string-translation';
		$url    = self::has_admin_texts_page()
			? admin_url( 'admin.php?page=' . self::ADMIN_TEXTS_PAGE )
			: admin_url( 'admin.php?page=' . $folder . '/menu/string-translation.php&trop=1' );
		if ( '' !== (string) $option_name ) {
			$url .= '&' . http_build_query( array( 'search' => (string) $option_name ), '', '&', PHP_QUERY_RFC3986 );
		}
		return $url;
	}

	/**
	 * WPML Menus Sync screen.
	 *
	 * @return string
	 */
	public static function menu_sync_url() {
		$folder = defined( 'WPML_PLUGIN_FOLDER' ) ? WPML_PLUGIN_FOLDER : 'sitepress-multilingual-cms';
		return admin_url( 'admin.php?page=' . $folder . '/menu/menu-sync/menus-sync.php' );
	}

	/**
	 * WPML Translation Management dashboard (string packages, forms, posts).
	 *
	 * @return string
	 */
	public static function tm_dashboard_url() {
		return admin_url( 'admin.php?page=tm/menu/main.php' );
	}

	/**
	 * Whether WPML String Translation is active.
	 *
	 * @return bool
	 */
	public static function has_st() {
		return defined( 'WPML_ST_VERSION' ) || function_exists( 'icl_register_string' );
	}
}
