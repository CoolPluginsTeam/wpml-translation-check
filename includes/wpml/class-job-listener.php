<?php
/**
 * Captures WPML translation jobs assigned to the bot.
 *
 * WPML extracts the translation package itself, which is what gives us ACF
 * fields, Elementor and Gutenberg content, SEO meta and taxonomies for free.
 * This class turns the resulting job into a queue row, keeping the field name
 * to tid mapping that write-back depends on.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Includes\Wpml;

use AUTOMLP_WPML\Includes\Queue\Queue_Table;
use WPML_AT_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job_Listener
 */
class Job_Listener {

	/**
	 * Field names WPML includes for bookkeeping rather than translation.
	 *
	 * @var array<int,string>
	 */
	private static $skip_suffixes = array( '-name', '-type', '-link', '-format' );

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'wpml_added_translation_jobs', array( __CLASS__, 'capture' ), 10, 1 );

		// Mirror state when a job is resolved outside our queue.
		add_action( 'wpml_translation_job_saved', array( __CLASS__, 'on_saved' ), 10, 2 );
		add_action( 'wpml_tm_job_cancelled', array( __CLASS__, 'on_cancelled' ), 10, 1 );
		add_action( 'wpml_tm_jobs_cancelled', array( __CLASS__, 'on_cancelled_many' ), 10, 1 );
	}

	/**
	 * Turn newly created WPML jobs into queue rows.
	 *
	 * @param array $jobs Job ids grouped by type, as passed by WPML.
	 * @return void
	 */
	public static function capture( $jobs ) {
		$bot_id = Bot_Translator::user_id();

		if ( ! $bot_id || empty( $jobs ) || ! is_array( $jobs ) ) {
			return;
		}

		foreach ( self::flatten( $jobs ) as $job_id ) {
			self::capture_one( (int) $job_id, $bot_id );
		}
	}

	/**
	 * WPML passes jobs grouped by element type. Flatten to a plain id list.
	 *
	 * @param array $jobs Nested job ids.
	 * @return array<int,int>
	 */
	private static function flatten( array $jobs ) {
		$out = array();

		array_walk_recursive(
			$jobs,
			static function ( $value ) use ( &$out ) {
				if ( is_numeric( $value ) ) {
					$out[] = (int) $value;
				}
			}
		);

		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Capture a single job if it belongs to the bot.
	 *
	 * @param int $wpml_job_id icl_translate_job.job_id.
	 * @param int $bot_id      Bot user id.
	 * @return void
	 */
	private static function capture_one( $wpml_job_id, $bot_id ) {
		$job = self::load_job( $wpml_job_id );

		if ( ! $job ) {
			return;
		}

		// Someone else's job: leave it for WPML or a human translator.
		if ( (int) $job->translator_id !== $bot_id ) {
			return;
		}

		$to_lang   = isset( $job->language_code ) ? (string) $job->language_code : '';
		$from_lang = isset( $job->source_language_code ) ? (string) $job->source_language_code : '';

		if ( '' === $to_lang ) {
			return;
		}

		// Free tier allows exactly one target language. Enforcing it here
		// covers jobs assigned from WPML's own Translation Management screen,
		// not just our UI.
		if ( ! self::language_allowed( $to_lang ) ) {
			self::cancel_wpml_job( $wpml_job_id );
			return;
		}

		$source_map = self::build_source_map( $job );

		if ( empty( $source_map ) ) {
			return;
		}

		// Keep translations in WPML's own editor rather than handing the job
		// to ATE, which would try to translate it a second time.
		self::force_native_editor( $wpml_job_id );

		$chars = 0;

		foreach ( $source_map as $entry ) {
			$chars += mb_strlen( (string) $entry['text'] );
		}

		Queue_Table::add(
			array(
				'wpml_job_id' => $wpml_job_id,
				'wpml_rid'    => isset( $job->rid ) ? (int) $job->rid : null,
				'source_id'   => self::source_id( $job ),
				'kind'        => self::job_kind( $job ),
				'from_lang'   => $from_lang,
				'to_lang'     => $to_lang,
				'field_count' => count( $source_map ),
				'char_count'  => $chars,
				'source_map'  => $source_map,
			)
		);
	}

	/**
	 * Load a job with its elements.
	 *
	 * @param int $wpml_job_id Job id.
	 * @return object|null
	 */
	private static function load_job( $wpml_job_id ) {
		$job = apply_filters( 'wpml_get_translation_job', null, $wpml_job_id, true );

		if ( ! is_object( $job ) || empty( $job->elements ) ) {
			return null;
		}

		return $job;
	}

	/**
	 * Build field name => { tid, text } from a job's elements.
	 *
	 * The tid is WPML's per-field translation id. Without it a translated
	 * value cannot be written back, so fields lacking one are dropped.
	 *
	 * @param object $job Job object.
	 * @return array<string,array{tid:int,text:string}>
	 */
	private static function build_source_map( $job ) {
		$map = array();

		foreach ( (array) $job->elements as $element ) {
			if ( empty( $element->field_type ) || empty( $element->tid ) ) {
				continue;
			}

			$field = (string) $element->field_type;

			if ( self::is_metadata_field( $field ) ) {
				continue;
			}

			// WPML marks fields it does not want translated.
			if ( isset( $element->field_translate ) && 1 !== (int) $element->field_translate ) {
				continue;
			}

			$text = self::decode_field( $element );

			if ( '' === trim( $text ) ) {
				continue;
			}

			$map[ $field ] = array(
				'tid'  => (int) $element->tid,
				'text' => $text,
			);
		}

		return $map;
	}

	/**
	 * Decode a field's stored value.
	 *
	 * @param object $element Job element.
	 * @return string
	 */
	private static function decode_field( $element ) {
		$data   = isset( $element->field_data ) ? (string) $element->field_data : '';
		$format = isset( $element->field_format ) ? (string) $element->field_format : '';

		if ( '' === $data ) {
			return '';
		}

		if ( 'base64' === $format ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$decoded = base64_decode( $data, true );

			return false === $decoded ? '' : $decoded;
		}

		return $data;
	}

	/**
	 * Is this a bookkeeping field rather than translatable content?
	 *
	 * @param string $field Field type.
	 * @return bool
	 */
	private static function is_metadata_field( $field ) {
		foreach ( self::$skip_suffixes as $suffix ) {
			if ( substr( $field, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Original element id for the job.
	 *
	 * @param object $job Job object.
	 * @return int
	 */
	private static function source_id( $job ) {
		if ( ! empty( $job->original_doc_id ) ) {
			return (int) $job->original_doc_id;
		}

		if ( ! empty( $job->original_id ) ) {
			return (int) $job->original_id;
		}

		return 0;
	}

	/**
	 * Classify the job so the writer knows how to save it.
	 *
	 * @param object $job Job object.
	 * @return string 'post', 'string' or 'package'.
	 */
	private static function job_kind( $job ) {
		$type = isset( $job->original_post_type ) ? (string) $job->original_post_type : '';

		if ( 'st-batch_strings' === $type ) {
			return 'string';
		}

		if ( 0 === strpos( $type, 'package' ) ) {
			return 'package';
		}

		// A batch of raw strings can also arrive with package-shaped fields.
		foreach ( (array) $job->elements as $element ) {
			if ( ! empty( $element->field_type ) && 0 === strpos( (string) $element->field_type, 'batch-string-' ) ) {
				return 'string';
			}
		}

		return 'post';
	}

	/**
	 * Is this target language permitted?
	 *
	 * @param string $to_lang Target language code.
	 * @return bool
	 */
	private static function language_allowed( $to_lang ) {
		if ( ! class_exists( 'WPML_AT_Helper' ) ) {
			return true;
		}

		$allowed = WPML_AT_Helper::get_wizard_allowed_language_code();

		// No wizard restriction recorded: allow everything.
		if ( null === $allowed ) {
			return true;
		}

		/**
		 * Filter the languages the queue will accept.
		 *
		 * Pro builds can return every active language here.
		 *
		 * @param array  $languages Allowed language codes.
		 * @param string $to_lang   Language being checked.
		 */
		$languages = apply_filters( 'automlp_allowed_target_languages', array( $allowed ), $to_lang );

		return in_array( strtolower( $to_lang ), array_map( 'strtolower', (array) $languages ), true );
	}

	/**
	 * Pin the job to WPML's own editor.
	 *
	 * @param int $wpml_job_id Job id.
	 * @return void
	 */
	private static function force_native_editor( $wpml_job_id ) {
		if ( ! function_exists( 'wpml_tm_load_job_factory' ) ) {
			return;
		}

		try {
			$factory = wpml_tm_load_job_factory();

			if ( method_exists( $factory, 'update_job_data' ) ) {
				$factory->update_job_data( $wpml_job_id, array( 'editor' => 'wpml' ) );
			}
		} catch ( \Throwable $e ) {
			// Non-fatal: the job still translates, WPML may just offer ATE.
			return;
		}
	}

	/**
	 * Cancel a WPML job we will not process.
	 *
	 * @param int $wpml_job_id Job id.
	 * @return void
	 */
	private static function cancel_wpml_job( $wpml_job_id ) {
		global $iclTranslationManagement;

		if ( ! $iclTranslationManagement || ! method_exists( $iclTranslationManagement, 'cancel_translation_request' ) ) {
			return;
		}

		$job = self::load_job( $wpml_job_id );

		if ( $job && ! empty( $job->rid ) ) {
			$iclTranslationManagement->cancel_translation_request( (int) $job->rid );
		}
	}

	/**
	 * A human completed the job in WPML's editor: close our row too.
	 *
	 * @param int   $wpml_job_id Job id.
	 * @param array $fields      Saved fields.
	 * @return void
	 */
	public static function on_saved( $wpml_job_id, $fields = array() ) {
		unset( $fields );

		if ( Writer::is_writing() ) {
			return;
		}

		Queue_Table::edit_by_wpml_job(
			(int) $wpml_job_id,
			array(
				'state'      => Queue_Table::STATE_DONE,
				'closed_at'  => current_time( 'mysql', true ),
				'source_map' => null,
			)
		);
	}

	/**
	 * WPML cancelled a job.
	 *
	 * @param int $wpml_job_id Job id.
	 * @return void
	 */
	public static function on_cancelled( $wpml_job_id ) {
		Queue_Table::edit_by_wpml_job(
			(int) $wpml_job_id,
			array(
				'state'      => Queue_Table::STATE_STOPPED,
				'closed_at'  => current_time( 'mysql', true ),
				'source_map' => null,
			)
		);
	}

	/**
	 * WPML cancelled several jobs at once.
	 *
	 * @param array $job_ids Job ids.
	 * @return void
	 */
	public static function on_cancelled_many( $job_ids ) {
		foreach ( (array) $job_ids as $job_id ) {
			self::on_cancelled( (int) $job_id );
		}
	}
}
