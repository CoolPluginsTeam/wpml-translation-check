<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automl_ai_dashboard-info">
	<div class="automl_ai_dashboard-info-links">
		<p>
			<?php esc_html_e( 'Made with ❤️ by', 'automl-ai-translation-for-wpml' ); ?>
			<span class="logo">
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=author_page&utm_content=dashboard_footer' ); ?>" target="_blank">
					<img src="<?php echo esc_url( AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/images/cool-plugins-logo-black.svg' ); ?>" alt="<?php esc_attr_e( 'Cool Plugins Logo', 'automl-ai-translation-for-wpml' ); ?>">
				</a>
			</span>
		</p>
		<a href="<?php echo esc_url( 'https://coolplugins.net/support/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Support', 'automl-ai-translation-for-wpml' ); ?>
		</a> |
		<a href="<?php echo esc_url( 'https://coolplugins.net/docs/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Docs', 'automl-ai-translation-for-wpml' ); ?>
		</a>

		<div class="automl_ai_dashboard-social-icons">
			<?php
			$automl_wpml_social_links = array(
				array( 'https://www.facebook.com/coolplugins/', 'facebook.svg', esc_html__( 'Facebook', 'automl-ai-translation-for-wpml' ) ),
				array( 'https://linkedin.com/company/coolplugins', 'linkedin.svg', esc_html__( 'LinkedIn', 'automl-ai-translation-for-wpml' ) ),
				array( 'https://x.com/cool_plugins', 'twitter.svg', esc_html__( 'Twitter / X', 'automl-ai-translation-for-wpml' ) ),
				array( 'https://www.youtube.com/@cool_plugins', 'youtube.svg', esc_html__( 'YouTube Channel', 'automl-ai-translation-for-wpml' ) ),
			);

			foreach ( $automl_wpml_social_links as $link ) {
				echo '<a href="' . esc_url( $link[0] ) . '" target="_blank">
						<img src="' . esc_url( AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/images/' . $link[1] ) . '" alt="' . esc_attr( $link[2] ) . '">
					  </a>';
			}
			?>
		</div>
	</div>
</div>