<?php
/**
 * CPFM Review - the admin-notice surface.
 *
 * Two templates, chosen per plugin by config:
 *
 *   two_step  Ask "do you like it?" first; a Yes swaps IN PLACE to the review
 *             CTA. Both outcomes stay reachable at every step - this is a
 *             two-click UI, never a filter. See plan R4.
 *   direct    One line, review button and dismiss immediately visible.
 *
 * Three exits, three different meanings, deliberately:
 *   Submit review / dismiss link -> permanent. Never asked again.
 *   "Ask me later"               -> SERVER-side snooze (default 30 days), so it
 *                                   survives a new browser or another device.
 *   the x                        -> BROWSER-side 24h only. Nothing is saved, so
 *                                   a stray click can never permanently hide it.
 *
 * @package CoolPlugins\CPFM
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CPFM_Review_Notice' ) ) {

	/**
	 * Renders the review ask as a wp-admin notice.
	 */
	final class CPFM_Review_Notice {

		/**
		 * Only one review notice per page load, however many plugins qualify.
		 *
		 * @var bool
		 */
		private static $rendered = false;

		/**
		 * Hook the surface. Safe to call more than once.
		 *
		 * @return void
		 */
		public static function cpfm_init() {

			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;

			add_action( 'admin_notices', array( __CLASS__, 'cpfm_maybe_render' ) );
		}

		/**
		 * Render the first eligible plugin's ask on an allowed screen.
		 *
		 * Static so a host whose settings page suppresses foreign notices can
		 * re-attach this one deliberately.
		 *
		 * @param bool $from_hook True when called by the `admin_notices` hook
		 *                       (the default, since WP passes no args). A host
		 *                       that places the notice inside its own layout
		 *                       calls cpfm_maybe_render( false ) and lists that screen
		 *                       in notice.defer_screens.
		 * @return void
		 */
		public static function cpfm_maybe_render( $from_hook = true ) {
			/*
			 * Treat anything that is not EXACTLY false as "came from the hook".
			 *
			 * do_action( 'admin_notices' ) passes '' to its callbacks — WP_Hook
			 * always supplies one argument — so a plain truthy test would see ''
			 * and decide this was a direct call, silently defeating
			 * defer_screens. Only a host passing literal false counts as direct.
			 */
			$from_hook = ( false !== $from_hook );

			if ( self::$rendered || ! class_exists( 'CPFM_Review' ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || empty( $screen->id ) ) {
				return;
			}

			foreach ( CPFM_Review::cpfm_registered() as $id ) {
				$config = CPFM_Review::cpfm_config( $id );
				if ( empty( $config['notice']['enabled'] ) ) {
					continue;
				}

				$screens = isset( $config['notice']['screens'] ) ? (array) $config['notice']['screens'] : array();
				if ( ! in_array( $screen->id, $screens, true ) ) {
					continue;
				}

				// Deferred screens: the host places this notice itself, somewhere
				// inside its own layout. Rendering from `admin_notices` would drop
				// it at the very top of #wpbody-content, ABOVE the host's own
				// header chrome. Skipping the hook lets the host call
				// maybe_render( false ) at the right point instead — which is a
				// gentler answer than nuking every other plugin's admin_notices
				// on the screen just to control our own placement.
				$deferred = isset( $config['notice']['defer_screens'] ) ? (array) $config['notice']['defer_screens'] : array();
				if ( $from_hook && in_array( $screen->id, $deferred, true ) ) {
					continue;
				}

				if ( ! CPFM_Review::cpfm_is_due( $id ) ) {
					continue;
				}

				self::cpfm_render( $config, $screen );
				self::$rendered = true;

				// An ask on the plugin's OWN screen was exempt from the shared
				// throttle, so it must not start a new window either — otherwise
				// visiting your own settings page would silence every sibling for
				// two weeks.
				CPFM_Review::cpfm_record_impression( $id, ! CPFM_Review::cpfm_is_own_screen( $id ) );
				return;
			}
		}

		/**
		 * Emit the notice markup.
		 *
		 * @param array<string, mixed> $config Plugin config.
		 * @param WP_Screen            $screen Current screen.
		 * @return void
		 */
		private static function cpfm_render( $config, $screen ) {

			$id       = $config['id'];
			$template = isset( $config['notice']['template'] ) ? $config['notice']['template'] : 'two_step';
			$name     = isset( $config['plugin_name'] ) ? $config['plugin_name'] : '';
			$nonce    = CPFM_Review_Assets::cpfm_nonce( $id );

			// `inline` stops wp-admin's common.js relocating the notice to just
			// after the first h1.
			//
			// This is wanted ONLY on custom full-bleed pages, where that h1 lives
			// inside a hero and the notice would land mid-panel. On a standard WP
			// screen (plugins.php, edit.php) the relocation is exactly right, and
			// suppressing it leaves the notice stranded ABOVE the page title. So
			// it is opt-in PER SCREEN via notice.inline_screens; position =>
			// 'inline' still forces it everywhere for the rare host that wants that.
			$inline_screens = isset( $config['notice']['inline_screens'] ) ? (array) $config['notice']['inline_screens'] : array();
			$force_inline   = ( isset( $config['notice']['position'] ) && 'inline' === $config['notice']['position'] );
			$inline         = ( $force_inline || in_array( $screen->id, $inline_screens, true ) ) ? ' inline' : '';

			$dismiss = CPFM_Review_Assets::cpfm_text( $config, 'dismiss_link', 'No thanks' );
			$later   = CPFM_Review_Assets::cpfm_text( $config, 'later_link', 'Ask me later' );
			$submit  = CPFM_Review_Assets::cpfm_text( $config, 'submit_button', 'Submit review' );
			?>
			<div class="notice notice-info cpfm-rv<?php echo esc_attr( $inline ); ?>"
				data-cpfm-rv
				data-cpfm-rv-id="<?php echo esc_attr( $id ); ?>"
				data-cpfm-rv-nonce="<?php echo esc_attr( $nonce ); ?>">

				<button type="button" class="cpfm-rv__x" data-cpfm-rv-close
					aria-label="<?php echo esc_attr( CPFM_Review_Assets::cpfm_text( $config, 'close_label', 'Close' ) ); ?>">&times;</button>

				<?php if ( 'direct' === $template ) : ?>

					<div class="cpfm-rv__step">
						<p class="cpfm-rv__line">
							<span class="dashicons dashicons-star-filled cpfm-rv__icon" aria-hidden="true"></span>
							<span class="cpfm-rv__text">
								<?php
								printf(
									esc_html( CPFM_Review_Assets::cpfm_text( $config, 'direct_line', 'Enjoying %s? A short review really helps.' ) ),
									'<strong>' . esc_html( $name ) . '</strong>'
								);
								?>
							</span>
							<a class="button button-primary button-small cpfm-rv__cta"
								href="<?php echo esc_url( $config['review_url'] ); ?>"
								target="_blank" rel="noopener noreferrer" data-cpfm-rv-answer>
								<?php echo esc_html( $submit ); ?>
							</a>
						</p>
						<p class="cpfm-rv__links">
							<a href="#" data-cpfm-rv-answer><?php echo esc_html( $dismiss ); ?></a>
							<span class="cpfm-rv__sep" aria-hidden="true">&middot;</span>
							<a href="#" data-cpfm-rv-later><?php echo esc_html( $later ); ?></a>
						</p>
					</div>

				<?php else : ?>

					<?php // STEP 1 - the question. Dismiss is available right here. ?>
					<div class="cpfm-rv__step" data-cpfm-rv-step="1">
						<p class="cpfm-rv__line">
							<span class="dashicons dashicons-star-filled cpfm-rv__icon" aria-hidden="true"></span>
							<span class="cpfm-rv__text">
								<?php
								printf(
									esc_html( CPFM_Review_Assets::cpfm_text( $config, 'like_question', 'Do you like the %s plugin?' ) ),
									'<strong>' . esc_html( $name ) . '</strong>'
								);
								?>
							</span>
							<button type="button" class="button button-primary button-small" data-cpfm-rv-yes>
								<?php echo esc_html( CPFM_Review_Assets::cpfm_text( $config, 'yes_button', 'Yes, I like it' ) ); ?>
							</button>
						</p>
						<p class="cpfm-rv__links">
							<a href="#" data-cpfm-rv-answer><?php echo esc_html( $dismiss ); ?></a>
							<span class="cpfm-rv__sep" aria-hidden="true">&middot;</span>
							<a href="#" data-cpfm-rv-later><?php echo esc_html( $later ); ?></a>
						</p>
					</div>

					<?php // STEP 2 - swapped in place, no reload. ?>
					<div class="cpfm-rv__step" data-cpfm-rv-step="2" hidden>
						<p class="cpfm-rv__line">
							<span class="dashicons dashicons-heart cpfm-rv__icon cpfm-rv__icon--heart" aria-hidden="true"></span>
							<span class="cpfm-rv__text">
								<?php echo esc_html( CPFM_Review_Assets::cpfm_text( $config, 'thanks_line', 'Great to hear! A quick review on WordPress.org would really help us.' ) ); ?>
							</span>
							<a class="button button-primary button-small cpfm-rv__cta"
								href="<?php echo esc_url( $config['review_url'] ); ?>"
								target="_blank" rel="noopener noreferrer" data-cpfm-rv-answer>
								<?php echo esc_html( $submit ); ?>
							</a>
							<a href="#" class="cpfm-rv__inline-dismiss" data-cpfm-rv-answer>
								<?php echo esc_html( CPFM_Review_Assets::cpfm_text( $config, 'no_link', 'I do not like it, dismiss' ) ); ?>
							</a>
						</p>
					</div>

				<?php endif; ?>
			</div>
			<?php
			
			CPFM_Review_Assets::cpfm_enqueue( 'cpfm-review-notice', 'cpfm-review-notice.css' );
		}

		/**
		 * Reset the per-request guards. Test seam only.
		 *
		 * @return void
		 */
		public static function _reset() {
			
			self::$rendered = false;
		}
	}
}
