<?php
/**
 * CPFM Review - the embeddable inline surface.
 *
 * For placing the ask INSIDE a plugin's own content: under a live preview, in a
 * settings card, in a block or Elementor widget's render callback, on a CPT
 * screen. Anywhere the host can call a function or write a shortcode.
 *
 * Its dismissal semantics are deliberately different from the notice:
 *
 *   - The close button is BROWSER-side only (default 24h, configurable). It
 *     never writes server state, so an inline embed can neither become a
 *     permanent nag nor be permanently lost to a stray click.
 *   - It still respects a REAL answer: once the user has reviewed or dismissed
 *     through any surface, this renders nothing at all.
 *   - It ignores the cross-plugin quiet period. That throttle exists to stop
 *     six addons queueing notices at a user; an embed the host deliberately
 *     placed inside its own UI is not competing for that space.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Review_Inline' ) ) {

	/**
	 * Renders the review ask inline, anywhere the host wants it.
	 */
	final class CPFM_Review_Inline {

		/**
		 * Register the optional shortcode. Safe to call more than once.
		 *
		 * @return void
		 */
		public static function cpfm_init() {

			
			add_shortcode( 'cpfm_review', array( __CLASS__, 'cpfm_shortcode' ) );
			
		}

		/**
		 * Shortcode handler: [cpfm_review id="tecc" style="card" hours="24"].
		 *
		 * @param array<string, mixed> $atts Shortcode attributes.
		 * @return string
		 */
		public static function cpfm_shortcode( $atts ) {
			
			$atts = shortcode_atts(
				array(
					'id'    => '',
					'style' => 'card',
					'hours' => 24,
				),
				is_array( $atts ) ? $atts : array(),
				'cpfm_review'
			);

			return self::cpfm_get( $atts['id'], array(
				'style' => $atts['style'],
				'hours' => (int) $atts['hours'],
			) );
		}

		/**
		 * Echo the embed.
		 *
		 * @param string               $id   Plugin id.
		 * @param array<string, mixed> $args style|hours.
		 * @return void
		 */
		public static function cpfm_render( $id, $args = array() ) {

			echo self::cpfm_get( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cpfm_get() escapes every interpolated value.
		}

		/**
		 * Build the embed markup, or '' when it must not show.
		 *
		 * @param string               $id   Plugin id.
		 * @param array<string, mixed> $args style|hours.
		 * @return string
		 */
		public static function cpfm_get( $id, $args = array() ) {

			if ( ! class_exists( 'CPFM_Review' ) ) {
				return '';
			}

			$id     = sanitize_key( $id );
			$config = CPFM_Review::cpfm_config( $id );

			if ( ! $config ) {
				return '';
			}

			// Master switch, checked before persistent bypasses anything else
			// below: a host that registered inline.enabled => false gets a
			// hard "never render", not just "quiet once answered".
			if ( empty( $config['inline']['enabled'] ) ) {
				return '';
			}

			if ( ! current_user_can( $config['capability'] ) ) {
				return '';
			}

			$args = array_merge(
				array(
					'style'      => 'card',
					'hours'      => 24,
					/*
					 * persistent => true makes this slot a standing invitation
					 * rather than an ask: it ignores the saved review state and
					 * the server snooze, and is only ever hidden by its own
					 * browser-side close for `hours`. Use it for a card the host
					 * deliberately placed inside its OWN settings UI, where the
					 * door should stay open without ever nagging.
					 *
					 * Default false: every other placement must go quiet once the
					 * user has answered.
					 */
					'persistent' => false,
				),
				is_array( $args ) ? $args : array()
			);

			if ( empty( $args['persistent'] ) ) {
				// A real answer silences every surface, including this one.
				$state = CPFM_Review::cpfm_state( $id );
				if ( 'done' === $state['status'] ) {
					return '';
				}

				// A server-side snooze ("Ask me later" elsewhere) is respected too.
				if ( $state['snooze_until'] > time() ) {
					return '';
				}
			}

			$style = in_array( $args['style'], array( 'card', 'bar', 'minimal' ), true ) ? $args['style'] : 'card';
			$hours = max( 1, min( 24 * 30, (int) $args['hours'] ) );

			$nonce = CPFM_Review_Assets::cpfm_nonce( $id );

			$title  = CPFM_Review_Assets::cpfm_text( $config, 'inline_title', 'Enjoying %s?' );
			$text   = CPFM_Review_Assets::cpfm_text( $config, 'inline_text', 'A short review on WordPress.org helps other people find it.' );
			$submit = CPFM_Review_Assets::cpfm_text( $config, 'submit_button', 'Submit review' );
			$later  = CPFM_Review_Assets::cpfm_text( $config, 'later_link', 'Ask me later' );
			$close  = CPFM_Review_Assets::cpfm_text( $config, 'close_label', 'Close' );

			$stars = CPFM_Review_Assets::cpfm_stars();

			// Rendered `hidden` and unhidden by the shared script when no live
			// browser snooze applies - so a snoozed embed never flashes on load.
			$html = '<div class="cpfm-rv-inline cpfm-rv-inline--' . esc_attr( $style ) . '"'
				. ' data-cpfm-rv'
				. ' data-cpfm-rv-id="' . esc_attr( $id ) . '"'
				. ' data-cpfm-rv-nonce="' . esc_attr( $nonce ) . '"'
				. ' data-cpfm-rv-hours="' . esc_attr( (string) $hours ) . '"'
				. ' hidden>'
				. '<button type="button" class="cpfm-rv-inline__x" data-cpfm-rv-close'
				. ' aria-label="' . esc_attr( $close ) . '">&times;</button>'
				. '<span class="cpfm-rv-inline__stars" aria-hidden="true">' . $stars . '</span>';

			if ( 'minimal' !== $style ) {
				$html .= '<p class="cpfm-rv-inline__title">'
					. esc_html( sprintf( $title, $config['plugin_name'] ) )
					. '</p>';
			}

			$html .= '<p class="cpfm-rv-inline__text">' . esc_html( $text ) . '</p>'
				. '<p class="cpfm-rv-inline__actions">'
				. '<a class="button button-primary button-small cpfm-rv__cta"'
				. ' href="' . esc_url( $config['review_url'] ) . '"'
				. ' target="_blank" rel="noopener noreferrer" data-cpfm-rv-answer>'
				. esc_html( $submit ) . '</a>';

			// "Ask me later" writes a SERVER snooze, which a persistent card then
			// ignores - offering it there would be incoherent. A persistent card
			// has exactly one way out: its own browser-side close.
			if ( empty( $args['persistent'] ) ) {
				$html .= '<a href="#" class="cpfm-rv-inline__later" data-cpfm-rv-later>' . esc_html( $later ) . '</a>';
			}

			$html .= '</p></div>';

			
			CPFM_Review_Assets::cpfm_enqueue( 'cpfm-review-inline', 'cpfm-review-inline.css' );

			return $html;
		}

		/**
		 * Reset per-request state. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {}
	}
}
