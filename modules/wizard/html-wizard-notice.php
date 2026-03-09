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
				esc_html__( 'Welcome to %s', 'wpml-translation-check' ),
				esc_html__( 'AutoML – AI Translation for WPML', 'wpml-translation-check' )
			);
			?>
		</strong>
		<?php echo ' &#8211; '; ?>
		<?php esc_html_e( 'You\'re almost ready to translate your content with AI.', 'wpml-translation-check' ); ?>
	</p>
	<p class="buttons">
		<a href="<?php echo esc_url( $automl_wpml_wizard_url ); ?>" class="button button-primary">
			<?php esc_html_e( 'Run the Setup Wizard', 'wpml-translation-check' ); ?>
		</a>
		<a href="<?php echo esc_url( $automl_wpml_skip_url ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Skip setup', 'wpml-translation-check' ); ?>
		</a>
	</p>
</div>