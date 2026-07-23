<?php
/**
 * Registers a virtual translator that WPML can assign jobs to.
 *
 * WPML only creates translation jobs for users it recognises as local
 * translators. Rather than writing posts directly, we register a dedicated
 * account so WPML builds the job, extracts the package and later saves the
 * result through its own pipeline.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bot_Translator
 */
class Bot_Translator {

	const USER_LOGIN   = 'automlp_ai_bot';
	const USER_ID_KEY  = 'automlp_bot_user_id';
	const SIGNATURE    = 'automlp_bot_pairs_signature';
	const CAPABILITY   = 'translate';

	/**
	 * Attach hooks that keep the translator in sync with WPML.
	 *
	 * @return void
	 */
	public static function boot() {
		// Language set changes invalidate the stored pair map.
		add_action( 'icl_after_add_language', array( __CLASS__, 'refresh' ) );
		add_action( 'icl_after_delete_language', array( __CLASS__, 'refresh' ) );
		add_action( 'wpml_after_language_settings_saved', array( __CLASS__, 'refresh' ) );

		// Keep the bot out of the users list and out of author dropdowns.
		add_action( 'pre_get_users', array( __CLASS__, 'hide_from_user_list' ) );
	}

	/**
	 * Ensure the account exists and is registered with WPML.
	 *
	 * Safe to call repeatedly: the signature check makes it a no-op once
	 * nothing has changed.
	 *
	 * @return int Bot user id, or 0 on failure.
	 */
	public static function ensure() {
		$user_id = self::user_id();

		if ( ! $user_id ) {
			$user_id = self::create_user();
		}

		if ( ! $user_id ) {
			return 0;
		}

		self::register_with_wpml( $user_id );

		return $user_id;
	}

	/**
	 * Stored bot user id, verified to still exist.
	 *
	 * @return int
	 */
	public static function user_id() {
		$stored = (int) get_option( self::USER_ID_KEY, 0 );

		if ( $stored && get_userdata( $stored ) ) {
			return $stored;
		}

		// Fall back to a login lookup: the option may have been lost while
		// the account survived.
		$user = get_user_by( 'login', self::USER_LOGIN );

		if ( $user ) {
			update_option( self::USER_ID_KEY, $user->ID, false );
			return (int) $user->ID;
		}

		return 0;
	}

	/**
	 * Is this user id the bot?
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	public static function is_bot( $user_id ) {
		$bot = self::user_id();
		return $bot && (int) $user_id === $bot;
	}

	/**
	 * Create the account.
	 *
	 * @return int New user id, or 0 on failure.
	 */
	private static function create_user() {
		$user_id = wp_insert_user(
			array(
				'user_login'   => self::USER_LOGIN,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => self::mailbox(),
				'display_name' => __( 'AutoMLP AI Translator', 'wpml-translation-check' ),
				'nickname'     => __( 'AutoMLP AI Translator', 'wpml-translation-check' ),
				'role'         => 'translator',
				'description'  => __( 'Automated translator used by AutoMLP. Do not delete while translations are queued.', 'wpml-translation-check' ),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		update_option( self::USER_ID_KEY, (int) $user_id, false );

		return (int) $user_id;
	}

	/**
	 * A non-deliverable address on the site's own domain.
	 *
	 * @return string
	 */
	private static function mailbox() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? $host : 'example.com';

		return 'automlp-ai-bot@' . sanitize_text_field( $host );
	}

	/**
	 * Tell WPML the bot can translate between every active language pair.
	 *
	 * WPML discovers translators by capability and reads their pairs from
	 * user meta, so both have to be set before its translator cache is
	 * cleared.
	 *
	 * @param int $user_id Bot user id.
	 * @return void
	 */
	private static function register_with_wpml( $user_id ) {
		$pairs = self::build_pairs();

		if ( empty( $pairs ) ) {
			return;
		}

		$signature = md5( (string) $user_id . '|' . wp_json_encode( $pairs ) );

		// Nothing changed since the last run.
		if ( get_option( self::SIGNATURE ) === $signature ) {
			return;
		}

		$user = new \WP_User( $user_id );

		if ( ! $user->exists() ) {
			return;
		}

		if ( ! $user->has_cap( self::CAPABILITY ) ) {
			$user->add_cap( self::CAPABILITY );
		}

		global $wpdb;
		update_user_meta( $user_id, $wpdb->prefix . 'language_pairs', $pairs );

		// Clear only WPML's translator cache. Deleting broader caches would
		// disturb other translators on the site.
		delete_option( 'wpml-cache-translators-' . self::CAPABILITY );

		update_option( self::SIGNATURE, $signature, false );
	}

	/**
	 * Every active language paired with every other.
	 *
	 * @return array<string,array<string,int>>
	 */
	private static function build_pairs() {
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );

		if ( ! is_array( $languages ) || count( $languages ) < 2 ) {
			return array();
		}

		$codes = array_keys( $languages );
		$pairs = array();

		foreach ( $codes as $from ) {
			foreach ( $codes as $to ) {
				if ( $from === $to ) {
					continue;
				}

				$pairs[ $from ][ $to ] = 1;
			}
		}

		return $pairs;
	}

	/**
	 * Rebuild registration after a language change.
	 *
	 * @return void
	 */
	public static function refresh() {
		delete_option( self::SIGNATURE );
		self::ensure();
	}

	/**
	 * Keep the bot out of the Users screen.
	 *
	 * @param \WP_User_Query $query User query.
	 * @return void
	 */
	public static function hide_from_user_list( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}

		$bot = self::user_id();

		if ( ! $bot ) {
			return;
		}

		$excluded = (array) $query->get( 'exclude' );
		$excluded[] = $bot;

		$query->set( 'exclude', array_unique( $excluded ) );
	}

	/**
	 * Remove the account and its registration. Called from uninstall.
	 *
	 * @return void
	 */
	public static function remove() {
		$user_id = self::user_id();

		if ( $user_id ) {
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			wp_delete_user( $user_id );
		}

		delete_option( self::USER_ID_KEY );
		delete_option( self::SIGNATURE );
		delete_option( 'wpml-cache-translators-' . self::CAPABILITY );
	}
}
