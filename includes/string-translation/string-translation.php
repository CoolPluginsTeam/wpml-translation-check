<?php

namespace AUTOMLP_WPML\Includes\String_Translation;

if ( ! defined( 'ABSPATH' ) ) exit;

use AUTOMLP_WPML\Helper\Helper;

/**
 * String_Translation
 *
 * @package AUTOMLP_WPML\Includes\String_Translation
 */
class String_Translation {
	/**
         * Single instance of the class
         *
         * @var self
         */
        private static $instance;

        public static function get_instance()
        {
            if(!isset(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct()
        {
            add_action('current_screen', array($this, 'string_translation_bulk_button'));
        }

      	/**
	 * Add bulk translate button to WPML String Translation page.
	 *
	 * @param WP_Screen $screen Current screen object.
	 * @return void
	 */
	public function string_translation_bulk_button($screen)
	{
		if (! $screen) {
			return;
		}

		// Check if we're on the string translation page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if (empty($page) || strpos($page, 'wpml-string-translation/menu/string-translation.php') === false) {
			return;
		}

		// Add button and container to the page.
		add_action('admin_notices', array($this, 'render_string_bulk_translate_button'));
		add_action('admin_footer', array($this, 'render_bulk_translate_container'));
	}
    	/**
	 * Render bulk translate button on string translation page.
	 *
	 * @return void
	 */
	public function render_string_bulk_translate_button()
	{
    ?>
		<button class="button button-primary automlp-wpml-bulk-translate-btn" style="display: none;">
			<?php esc_html_e('AI Translate', 'wpml-translation-check'); ?>
		</button>
	<?php
	}

    	/**
	 * Render bulk translate container div.
	 *
	 * @return void
	 */
	public function render_bulk_translate_container()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for conditional logic.
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if (empty($page) || strpos($page, 'wpml-string-translation/menu/string-translation.php') === false) {
			return;
		}
	?>
		<div id="automlp-wpml-bulk-translate-wrapper"></div>
<?php
	}
}

String_Translation::get_instance();