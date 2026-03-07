<?php
/**
 * Displays the wizard notice content (Run the Setup Wizard / Skip setup).
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$automl_wpml_wizard_url = add_query_arg(
	array( 'page' => 'automl_ai_wizard' ),
	admin_url( 'admin.php' )
);
$automl_wpml_skip_url = wp_nonce_url(
	add_query_arg( 'wpml_at_hide_wizard_notice', '1', admin_url( 'admin.php' ) ),
	'wpml_at_hide_wizard',
	'_wpml_at_notice_nonce'
);
?>
<div class="notice notice-info is-dismissible">
	<p>
		<strong>
			<?php
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'Welcome to %s', 'automl-ai-translation-for-wpml' ),
				esc_html__( 'AutoML – AI Translation for WPML', 'automl-ai-translation-for-wpml' )
			);
			?>
		</strong>
		<?php echo ' &#8211; '; ?>
		<?php esc_html_e( 'You\'re almost ready to translate your content with AI.', 'automl-ai-translation-for-wpml' ); ?>
	</p>
	<p class="buttons">
		<a href="<?php echo esc_url( $automl_wpml_wizard_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Run the Setup Wizard', 'automl-ai-translation-for-wpml' ); ?>
		</a>
		<a href="<?php echo esc_url( $automl_wpml_skip_url ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Skip setup', 'automl-ai-translation-for-wpml' ); ?>
		</a>
	</p>
</div>