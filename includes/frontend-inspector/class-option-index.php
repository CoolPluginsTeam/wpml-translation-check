<?php
/**
 * Bounded recursive search of wp_options / theme_mods for a visible text.
 *
 * Finds values inside nested arrays and serialized options and reports the key
 * path ("[footer][copyright]"). Autoloaded options are searched in memory; non
 * autoloaded options use one bounded LIKE query. Batches share one index.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\FrontendInspector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option_Index
 */
class Option_Index {

	const MAX_LEAVES   = 12000;
	const MAX_DEPTH    = 6;
	const MAX_CHILDREN = 200;
	const MAX_HITS     = 8;
	const MAX_LIKE     = 40;

	/**
	 * Normalized text => list of hits (memory index of autoloaded options + theme_mods).
	 *
	 * @var array<string, array<int, array<string, mixed>>>|null
	 */
	private static $index = null;

	/**
	 * Leaves indexed so far.
	 *
	 * @var int
	 */
	private static $leaves = 0;

	/**
	 * Results of non-autoload lookups keyed by normalized text.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static $cold = array();

	/**
	 * Option names never searched (noise, internal state, credentials).
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	public static function is_skipped( $name ) {
		$name = (string) $name;
		if ( '' === $name || '_' === $name[0] ) {
			return true;
		}
		$prefixes = array( 'theme_mods_', 'icl_', 'wpml_', 'wpml-', 'cron', 'rewrite_rules', 'active_plugins', 'recently_', 'uninstall_plugins', 'auto_updater', 'auth_', 'secure_auth_', 'logged_in_', 'nonce_', 'can_compress', 'db_', 'user_roles', 'automlp_', 'elementor_pro_license', 'action_scheduler', 'jetpack_' );
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
		if ( false !== strpos( $name, 'user_roles' ) || false !== strpos( $name, 'license' ) || false !== strpos( $name, 'password' ) || false !== strpos( $name, 'secret' ) || false !== strpos( $name, '_key' ) || false !== strpos( $name, 'token' ) ) {
			return true;
		}
		/**
		 * Filters whether an option is excluded from the inspector option search.
		 *
		 * @param bool   $skip Default false.
		 * @param string $name Option name.
		 */
		return (bool) apply_filters( 'automlp_fi_option_index_skip', false, $name );
	}

	/**
	 * Visible-text keys for one stored leaf: the whole value plus its HTML text segments.
	 *
	 * @param string $value Stored value.
	 * @return array<string, bool> key => partial flag.
	 */
	public static function keys_for_leaf( $value ) {
		$out  = array();
		$full = Origin_Collector::normalize( $value );
		if ( '' !== $full && strlen( $full ) <= 2000 ) {
			$out[ $full ] = false;
		}
		if ( false !== strpos( $value, '<' ) || false !== strpos( $value, "\n" ) ) {
			$parts = preg_split( '/<[^>]*>|\R/u', $value );
			$count = 0;
			foreach ( (array) $parts as $part ) {
				$seg = Origin_Collector::normalize( (string) $part );
				if ( strlen( $seg ) >= 3 && ! isset( $out[ $seg ] ) && preg_match( '/\p{L}{2,}/u', $seg ) ) {
					$out[ $seg ] = true;
					if ( ++$count >= 20 ) {
						break;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Unserialize stored data without ever instantiating objects.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Decoded value (objects become incomplete classes and are ignored by walk()).
	 */
	public static function decode( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
			return $value;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- allowed_classes=false blocks object injection; malformed data returns false.
		$decoded = @unserialize( trim( $value ), array( 'allowed_classes' => false ) );
		return false === $decoded ? $value : $decoded;
	}

	/**
	 * Walk one value and call $callback( $leaf_string, $path ) for every string leaf (bounded).
	 *
	 * @param mixed    $data   Value.
	 * @param string   $path   Path so far.
	 * @param callable $callback Callback.
	 * @param int      $depth  Depth.
	 * @param int      $budget Remaining leaves (by reference).
	 * @return void
	 */
	public static function walk( $data, $path, $callback, $depth, &$budget ) {
		if ( $budget <= 0 || $depth > self::MAX_DEPTH ) {
			return;
		}
		if ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				$data = self::decode( $data );
				if ( ! is_array( $data ) ) {
					return;
				}
			} else {
				--$budget;
				if ( strlen( $data ) >= 2 && strlen( $data ) <= 20000 ) {
					call_user_func( $callback, $data, $path );
				}
				return;
			}
		}
		if ( is_object( $data ) && ! $data instanceof \__PHP_Incomplete_Class ) {
			$data = get_object_vars( $data );
		}
		if ( ! is_array( $data ) ) {
			return;
		}
		$count = 0;
		foreach ( $data as $key => $child ) {
			if ( ++$count > self::MAX_CHILDREN || $budget <= 0 ) {
				break;
			}
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			self::walk( $child, $path . '[' . $key . ']', $callback, $depth + 1, $budget );
		}
	}

	/**
	 * Add one option/theme_mod value to the memory index.
	 *
	 * @param mixed  $value    Value.
	 * @param string $name     Option name or theme_mod key.
	 * @param string $kind     option|theme_mod.
	 * @param bool   $autoload Whether autoloaded.
	 * @return void
	 */
	private static function index_value( $value, $name, $kind, $autoload ) {
		$budget = self::MAX_LEAVES - self::$leaves;
		$before = $budget;
		self::walk(
			$value,
			'',
			static function ( $leaf, $path ) use ( $name, $kind, $autoload ) {
				foreach ( self::keys_for_leaf( $leaf ) as $key => $partial ) {
					if ( ! isset( self::$index[ $key ] ) ) {
						self::$index[ $key ] = array();
					}
					if ( count( self::$index[ $key ] ) < self::MAX_HITS ) {
						self::$index[ $key ][] = array(
							'kind'     => $kind,
							'name'     => $name,
							'path'     => $path,
							'autoload' => $autoload,
							'partial'  => $partial,
						);
					}
				}
			},
			0,
			$budget
		);
		self::$leaves += max( 0, $before - $budget );
	}

	/**
	 * Build the memory index once per request (theme_mods + autoloaded options).
	 *
	 * @return void
	 */
	private static function build() {
		if ( null !== self::$index ) {
			return;
		}
		self::$index  = array();
		self::$leaves = 0;

		$mods = get_theme_mods();
		if ( is_array( $mods ) ) {
			foreach ( $mods as $key => $value ) {
				if ( is_string( $key ) && '' !== $key ) {
					self::index_value( $value, $key, 'theme_mod', true );
				}
			}
		}

		$all = wp_load_alloptions();
		foreach ( (array) $all as $name => $raw ) {
			if ( self::$leaves >= self::MAX_LEAVES ) {
				break;
			}
			if ( self::is_skipped( (string) $name ) ) {
				continue;
			}
			self::index_value( self::decode( $raw ), (string) $name, 'option', true );
		}

		// Admin Texts registry options may be non-autoloaded; they matter most.
		$registry = get_option( '_icl_admin_option_names', array() );
		if ( is_array( $registry ) ) {
			foreach ( array_keys( $registry ) as $name ) {
				if ( is_string( $name ) && '' !== $name && ! isset( $all[ $name ] ) && 0 !== strpos( $name, 'theme_mods_' ) ) {
					$val = get_option( $name, null );
					if ( null !== $val ) {
						self::index_value( $val, $name, 'option', false );
					}
				}
			}
		}
	}

	/**
	 * A LIKE needle for a visible text: the longest plain run without markup-sensitive chars.
	 *
	 * @param string $text Normalized text.
	 * @return string
	 */
	private static function like_needle( $text ) {
		$parts = preg_split( '/[<>&"\'\\\\]+/u', (string) $text );
		$best  = '';
		foreach ( (array) $parts as $part ) {
			$part = trim( (string) $part );
			if ( strlen( $part ) > strlen( $best ) ) {
				$best = $part;
			}
		}
		return strlen( $best ) >= 4 ? $best : '';
	}

	/**
	 * Search non-autoloaded options for several texts with one bounded query.
	 *
	 * @param string[] $texts Normalized texts.
	 * @return void
	 */
	public static function prime_cold( array $texts ) {
		global $wpdb;
		$needles = array();
		foreach ( $texts as $text ) {
			$text = (string) $text;
			if ( isset( self::$cold[ $text ] ) ) {
				continue;
			}
			self::$cold[ $text ] = array();
			$needle              = self::like_needle( $text );
			if ( '' !== $needle && count( $needles ) < self::MAX_LIKE ) {
				$needles[ $text ] = $needle;
			}
		}
		if ( ! $needles ) {
			return;
		}
		$where = array();
		$args  = array();
		foreach ( $needles as $needle ) {
			$where[] = 'option_value LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$sql = "SELECT option_name, option_value FROM {$wpdb->options} WHERE autoload IN ('no','off','auto-off') AND option_name NOT LIKE '\\_%' AND (" . implode( ' OR ', $where ) . ') LIMIT 200';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only; placeholders built above, values passed to prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$name = (string) $row['option_name'];
			if ( self::is_skipped( $name ) ) {
				continue;
			}
			$budget = 3000;
			$value  = self::decode( $row['option_value'] );
			self::walk(
				$value,
				'',
				static function ( $leaf, $path ) use ( $name, $needles ) {
					foreach ( self::keys_for_leaf( $leaf ) as $key => $partial ) {
						if ( isset( $needles[ $key ] ) && count( self::$cold[ $key ] ) < self::MAX_HITS ) {
							self::$cold[ $key ][] = array(
								'kind'     => 'option',
								'name'     => $name,
								'path'     => $path,
								'autoload' => false,
								'partial'  => $partial,
							);
						}
					}
				},
				0,
				$budget
			);
		}
	}

	/**
	 * Every option / theme_mod location whose value (or HTML text segment) equals $text.
	 *
	 * @param string $text Normalized visible text.
	 * @param bool   $cold Also search non-autoloaded options.
	 * @return array<int, array<string, mixed>> Whole-value hits first.
	 */
	public static function find( $text, $cold = true ) {
		$text = Origin_Collector::normalize( $text );
		if ( '' === $text ) {
			return array();
		}
		self::build();
		$hits = isset( self::$index[ $text ] ) ? self::$index[ $text ] : array();
		if ( $cold ) {
			if ( ! isset( self::$cold[ $text ] ) ) {
				self::prime_cold( array( $text ) );
			}
			$hits = array_merge( $hits, self::$cold[ $text ] );
		}
		usort(
			$hits,
			static function ( $a, $b ) {
				return (int) $a['partial'] - (int) $b['partial'];
			}
		);
		return array_slice( $hits, 0, self::MAX_HITS );
	}

	/**
	 * Read a value at a bracket path inside an option / theme_mod.
	 *
	 * @param string $name Option name or theme_mod key.
	 * @param string $path Bracket path.
	 * @param string $kind option|theme_mod.
	 * @return mixed|null
	 */
	public static function value_at( $name, $path, $kind = 'option' ) {
		$value = 'theme_mod' === $kind ? get_theme_mod( $name, null ) : get_option( $name, null );
		if ( '' === (string) $path ) {
			return $value;
		}
		if ( ! preg_match_all( '/\[([^\]]*)\]/', (string) $path, $m ) ) {
			return null;
		}
		foreach ( $m[1] as $key ) {
			if ( is_string( $value ) && is_serialized( $value ) ) {
				$value = self::decode( $value );
			}
			if ( is_object( $value ) ) {
				$value = get_object_vars( $value );
			}
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}
}
