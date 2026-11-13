<?php
/**
 * Resolve text that JavaScript inserted into the page (Woo Blocks, React UIs, snippets).
 *
 * Sources, cheapest first: WPML ST rows (incl. JED contexts "{domain}-{handle}"),
 * JSON translation files of the page's script handles, the domain's POT file
 * (msgid + "#:" references), inline scripts on the page, and finally a bounded
 * literal search inside the page's own script files.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JS_Strings
 */
class JS_Strings {

	const MAX_HANDLES    = 80;
	const MAX_POT_BYTES  = 8388608;
	const MAX_JS_BYTES   = 3145728;
	const MAX_JS_FILES   = 40;
	const MAX_JSON_FILES = 120;

	/**
	 * Per-request caches.
	 *
	 * @var array<string, mixed>
	 */
	private static $cache = array(
		'pot'  => array(),
		'json' => array(),
		'js'   => array(),
	);

	/**
	 * Script handles printed on the inspected page that carry a text domain or live in a plugin/theme.
	 *
	 * Called while printing the origin map (inspect render only).
	 *
	 * @return array<int, array<string, string>> h = handle, d = text domain, s = src relative to wp-content.
	 */
	public static function page_scripts() {
		$scripts = wp_scripts();
		$out     = array();
		$handles = array_unique( array_merge( (array) $scripts->done, (array) $scripts->queue ) );
		$content = content_url();
		foreach ( $handles as $handle ) {
			if ( count( $out ) >= self::MAX_HANDLES ) {
				break;
			}
			if ( ! is_string( $handle ) || ! isset( $scripts->registered[ $handle ] ) ) {
				continue;
			}
			$dep    = $scripts->registered[ $handle ];
			$src    = is_string( $dep->src ) ? $dep->src : '';
			$domain = ! empty( $dep->textdomain ) ? (string) $dep->textdomain : '';
			$rel    = '';
			if ( '' !== $src ) {
				$path = wp_parse_url( $src, PHP_URL_PATH );
				$base = wp_parse_url( $content, PHP_URL_PATH );
				if ( is_string( $path ) && is_string( $base ) && 0 === strpos( $path, trailingslashit( $base ) ) ) {
					$rel = substr( $path, strlen( trailingslashit( $base ) ) );
				}
			}
			if ( '' === $domain && '' === $rel ) {
				continue;
			}
			if ( '' === $domain && 0 !== strpos( $rel, 'plugins/' ) && 0 !== strpos( $rel, 'themes/' ) ) {
				continue;
			}
			$out[] = array_filter(
				array(
					'h' => $handle,
					'd' => $domain,
					's' => $rel,
				)
			);
		}
		return $out;
	}

	/**
	 * Sanitize the script list posted back by the browser.
	 *
	 * @param mixed $raw Decoded JSON.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_scripts( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( array_slice( $raw, 0, self::MAX_HANDLES ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['h'] ) || ! is_scalar( $row['h'] ) ) {
				continue;
			}
			$src = ( isset( $row['s'] ) && is_scalar( $row['s'] ) ) ? (string) $row['s'] : '';
			// Only plain relative paths under wp-content (no traversal, no scheme).
			if ( '' !== $src && ( false !== strpos( $src, '..' ) || ! preg_match( '#^(plugins|themes|mu-plugins)/[A-Za-z0-9_\-./@]+\.m?js$#', $src ) ) ) {
				$src = '';
			}
			// Handles and text domains are used to build file names: keep them to safe slug characters.
			$handle = str_replace( '..', '', (string) preg_replace( '/[^A-Za-z0-9_\-.]/', '', (string) $row['h'] ) );
			$domain = ( isset( $row['d'] ) && is_scalar( $row['d'] ) ) ? str_replace( '..', '', (string) preg_replace( '/[^A-Za-z0-9_\-.]/', '', (string) $row['d'] ) ) : '';
			if ( '' === $handle ) {
				continue;
			}
			$out[] = array(
				'h' => substr( $handle, 0, 120 ),
				'd' => substr( $domain, 0, 120 ),
				's' => $src,
			);
		}
		return $out;
	}

	/**
	 * Locale for a WPML language code.
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function locale_for( $lang ) {
		$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		if ( is_array( $langs ) && isset( $langs[ $lang ]['default_locale'] ) && '' !== $langs[ $lang ]['default_locale'] ) {
			return (string) $langs[ $lang ]['default_locale'];
		}
		return determine_locale();
	}

	/**
	 * Absolute path for a wp-content relative script path.
	 *
	 * @param string $rel Relative path.
	 * @return string
	 */
	private static function abs_path( $rel ) {
		return '' === $rel ? '' : wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . $rel );
	}

	/**
	 * Decoded Jed messages of one JSON translation file (cached).
	 *
	 * @param string $file Path.
	 * @return array<string, mixed>
	 */
	private static function jed_messages( $file ) {
		if ( isset( self::$cache['json'][ $file ] ) ) {
			return self::$cache['json'][ $file ];
		}
		$messages = array();
		if ( count( self::$cache['json'] ) < self::MAX_JSON_FILES && is_readable( $file ) && filesize( $file ) < 2097152 ) {
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local translation file.
			if ( is_array( $data ) && isset( $data['locale_data'] ) && is_array( $data['locale_data'] ) ) {
				foreach ( $data['locale_data'] as $set ) {
					if ( is_array( $set ) ) {
						$messages += $set;
					}
				}
				unset( $messages[''] );
			}
		}
		self::$cache['json'][ $file ] = $messages;
		return $messages;
	}

	/**
	 * Candidate JSON translation files for a handle (core naming + WPML JED files).
	 *
	 * @param array<string, string> $script Script row.
	 * @param string                $locale Locale.
	 * @return string[]
	 */
	private static function json_files( array $script, $locale ) {
		$files  = array();
		$domain = $script['d'];
		if ( '' === $domain ) {
			return $files;
		}
		$rel = $script['s'];
		if ( '' !== $rel && preg_match( '#^(plugins|themes)/[^/]+/(.+)$#', $rel, $m ) ) {
			$inner   = preg_replace( '/\.min\.js$/', '.js', $m[2] );
			$dir     = 'plugins' === $m[1] ? 'plugins' : 'themes';
			$files[] = WP_LANG_DIR . '/' . $dir . '/' . $domain . '-' . $locale . '-' . md5( (string) $inner ) . '.json';
			// Bundled languages folder inside the component.
			$slug    = strtok( substr( $rel, strlen( $m[1] ) + 1 ), '/' );
			$files[] = WP_CONTENT_DIR . '/' . $m[1] . '/' . $slug . '/languages/' . $domain . '-' . $locale . '-' . md5( (string) $inner ) . '.json';
		}
		$files[] = WP_LANG_DIR . '/wpml/' . $domain . '-' . $script['h'] . '-' . $locale . '.json';
		return $files;
	}

	/**
	 * POT file of a component (first match in the usual folders).
	 *
	 * @param array<string, string> $component Component row.
	 * @return string
	 */
	private static function pot_file( array $component ) {
		if ( empty( $component['slug'] ) || empty( $component['type'] ) || 'core' === $component['type'] ) {
			return '';
		}
		$base = 'theme' === $component['type']
			? trailingslashit( get_theme_root( $component['slug'] ) ) . $component['slug']
			: WP_PLUGIN_DIR . '/' . $component['slug'];
		$base = wp_normalize_path( $base );
		$name = isset( $component['domain'] ) ? $component['domain'] : $component['slug'];
		foreach ( array( 'languages', 'i18n/languages', 'lang', 'i18n', 'language', '' ) as $dir ) {
			$file = $base . ( '' === $dir ? '' : '/' . $dir ) . '/' . $name . '.pot';
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * Find a msgid in a POT file; returns its "#:" source references.
	 *
	 * @param string $file POT path.
	 * @param string $text Original text.
	 * @return string[]|null Null when absent, list of references (maybe empty) when present.
	 */
	private static function pot_lookup( $file, $text ) {
		if ( '' === $file ) {
			return null;
		}
		if ( ! isset( self::$cache['pot'][ $file ] ) ) {
			$size                        = (int) filesize( $file );
			self::$cache['pot'][ $file ] = ( $size > 0 && $size <= self::MAX_POT_BYTES ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local POT file.
		}
		$body = self::$cache['pot'][ $file ];
		if ( '' === $body ) {
			return null;
		}
		$needle = "\nmsgid \"" . addcslashes( $text, "\\\"\n\t" ) . "\"\n";
		$pos    = strpos( $body, $needle );
		if ( false === $pos ) {
			return null;
		}
		// Comments block above the msgid (bounded look-back).
		$start = max( 0, $pos - 4000 );
		$chunk = substr( $body, $start, $pos - $start );
		$split = strrpos( $chunk, "\n\n" );
		if ( false !== $split ) {
			$chunk = substr( $chunk, $split );
		}
		$refs = array();
		if ( preg_match_all( '/^#: (.+)$/m', $chunk, $m ) ) {
			foreach ( $m[1] as $line ) {
				foreach ( preg_split( '/\s+/', trim( $line ) ) as $ref ) {
					if ( '' !== $ref && count( $refs ) < 6 ) {
						$refs[] = $ref;
					}
				}
			}
		}
		return $refs;
	}

	/**
	 * Whether a script file contains the text as a string literal (bounded, cached).
	 *
	 * @param string $rel  wp-content relative path.
	 * @param string $text Text.
	 * @return bool
	 */
	private static function script_has_literal( $rel, $text ) {
		$file = self::abs_path( $rel );
		if ( '' === $file || ! is_readable( $file ) ) {
			return false;
		}
		if ( ! array_key_exists( $file, self::$cache['js'] ) ) {
			if ( count( self::$cache['js'] ) >= self::MAX_JS_FILES ) {
				return false;
			}
			$size                       = (int) filesize( $file );
			self::$cache['js'][ $file ] = ( $size > 0 && $size <= self::MAX_JS_BYTES ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local script file.
		}
		$body = self::$cache['js'][ $file ];
		if ( '' === $body ) {
			return false;
		}
		$json = substr( (string) wp_json_encode( $text ), 1, -1 );
		foreach ( array_unique( array( '"' . $text . '"', "'" . $text . "'", '`' . $text . '`', '"' . $json . '"', "'" . addcslashes( $text, "'\\" ) . "'" ) ) as $needle ) {
			if ( false !== strpos( $body, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Locate a JS-rendered text.
	 *
	 * @param string                            $text    Visible text (normalized).
	 * @param array<int, array<string, string>> $scripts Page scripts (sanitized).
	 * @param string[]                          $inline  Handles of inline scripts that contain the text.
	 * @param string                            $current Current language.
	 * @param string                            $default_lang Default language.
	 * @return array<string, mixed> Evidence: domain, handle, src, original, translation, translated (bool|null), refs, via.
	 */
	public static function locate( $text, array $scripts, array $inline, $current, $default_lang ) {
		$locale    = self::locale_for( $current );
		$secondary = '' !== $current && '' !== $default_lang && $current !== $default_lang;
		$by_handle = array();
		foreach ( $scripts as $script ) {
			$by_handle[ $script['h'] ] = $script;
		}

		// 1) JSON translation files of the page's script handles.
		foreach ( $scripts as $script ) {
			foreach ( self::json_files( $script, $locale ) as $file ) {
				$messages = self::jed_messages( $file );
				if ( ! $messages ) {
					continue;
				}
				foreach ( $messages as $original => $translations ) {
					$orig_text = (string) $original;
					$ctx_pos   = strpos( $orig_text, "\4" );
					if ( false !== $ctx_pos ) {
						$orig_text = substr( $orig_text, $ctx_pos + 1 );
					}
					$first = is_array( $translations ) && isset( $translations[0] ) ? (string) $translations[0] : '';
					if ( $orig_text === $text || ( '' !== $first && Origin_Collector::normalize( $first ) === $text ) ) {
						return array(
							'via'         => 'json',
							'domain'      => $script['d'],
							'handle'      => $script['h'],
							'src'         => $script['s'],
							'file'        => Container_Markers::relative_path( $file ),
							'original'    => $orig_text,
							'translation' => $first,
							'translated'  => $secondary ? ( $orig_text !== $text ) : null,
						);
					}
				}
			}
		}

		// 2) Inline script on the page holding the literal (snippets, localized data).
		foreach ( $inline as $handle ) {
			$script = isset( $by_handle[ $handle ] ) ? $by_handle[ $handle ] : array(
				'h' => $handle,
				'd' => '',
				's' => '',
			);
			return array(
				'via'        => 'inline',
				'domain'     => $script['d'],
				'handle'     => $handle,
				'src'        => $script['s'],
				'original'   => $text,
				'translated' => $secondary ? false : null,
			);
		}

		// 3) POT of each page domain: confirms the domain and gives "#:" references.
		$seen = array();
		foreach ( $scripts as $script ) {
			$domain = $script['d'];
			if ( '' === $domain || isset( $seen[ $domain ] ) ) {
				continue;
			}
			$seen[ $domain ] = true;
			$refs            = self::pot_lookup( self::pot_file( Component_Registry::for_domain( $domain ) ), $text );
			if ( null !== $refs ) {
				$handle = self::handle_for_refs( $scripts, $domain, $refs );
				if ( '' === $handle ) {
					// No reference matched a page script: find the bundle that holds the literal (bounded).
					foreach ( $scripts as $candidate ) {
						if ( $candidate['d'] === $domain && '' !== $candidate['s'] && self::script_has_literal( $candidate['s'], $text ) ) {
							$handle = $candidate['h'];
							break;
						}
					}
				}
				return array(
					'via'        => 'pot',
					'domain'     => $domain,
					'handle'     => $handle,
					'src'        => ( '' !== $handle && isset( $by_handle[ $handle ] ) ) ? $by_handle[ $handle ]['s'] : '',
					'refs'       => $refs,
					'original'   => $text,
					'translated' => $secondary ? false : null,
				);
			}
		}

		// 4) Bounded literal search in the page's own script files.
		foreach ( $scripts as $script ) {
			if ( '' !== $script['s'] && self::script_has_literal( $script['s'], $text ) ) {
				$domain = $script['d'];
				if ( '' === $domain ) {
					$domain = Origin_Collector::textdomain_for_path( $script['s'] );
				}
				return array(
					'via'        => 'script',
					'domain'     => $domain,
					'handle'     => $script['h'],
					'src'        => $script['s'],
					'original'   => $text,
					'translated' => $secondary ? false : null,
				);
			}
		}

		return array();
	}

	/**
	 * Pick the page handle whose src best matches POT "#:" references.
	 *
	 * @param array<int, array<string, string>> $scripts Page scripts.
	 * @param string                            $domain  Domain.
	 * @param string[]                          $refs    References.
	 * @return string
	 */
	private static function handle_for_refs( array $scripts, $domain, array $refs ) {
		$best   = '';
		$score  = 0;
		$count  = 0;
		$single = '';
		foreach ( $scripts as $script ) {
			if ( $script['d'] !== $domain ) {
				continue;
			}
			++$count;
			$single = $script['h'];
			$have   = (string) preg_replace( '/\.(min\.)?(js|jsx|ts|tsx)$/', '', basename( (string) $script['s'] ) );
			if ( '' === $have ) {
				continue;
			}
			foreach ( $refs as $ref ) {
				$file = (string) preg_replace( '/:\d+$/', '', $ref );
				$stem = (string) preg_replace( '/\.(min\.)?(js|jsx|ts|tsx)$/', '', basename( $file ) );
				if ( '' === $stem ) {
					continue;
				}
				$s = 0;
				if ( $stem === $have ) {
					$s = 3;
				} elseif ( $have === $stem . '-frontend' || $stem === $have . '-frontend' || substr( $have, -strlen( '-' . $stem ) ) === '-' . $stem ) {
					$s = 2;
				} elseif ( false !== strpos( $stem, '-' ) && false !== strpos( $have, $stem ) ) {
					$s = 1;
				}
				if ( $s > $score ) {
					$score = $s;
					$best  = $script['h'];
				}
			}
		}
		if ( '' !== $best ) {
			return $best;
		}
		// Only one script of this domain on the page: it must be the one.
		return 1 === $count ? $single : '';
	}
}
