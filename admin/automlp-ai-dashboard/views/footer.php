<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automlp_ai_dashboard-info">
	<div class="automlp_ai_dashboard-info-links">
		<p>
			<?php esc_html_e( 'Made with ❤️ by', 'wpml-translation-check' ); ?>
			<span class="logo">
				<a href="<?php echo esc_url( 'https://coolplugins.net/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=author_page&utm_content=dashboard_footer' ); ?>" target="_blank">
					<img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/cool-plugins-logo-black.svg' ); ?>" alt="<?php esc_attr_e( 'Cool Plugins Logo', 'wpml-translation-check' ); ?>">
				</a>
			</span>
		</p>
		<a href="<?php echo esc_url( 'https://coolplugins.net/support/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=support&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Support', 'wpml-translation-check' ); ?>
		</a> |
		<a href="<?php echo esc_url( 'https://coolplugins.net/docs/?utm_source=wpml-auto-plugin&utm_medium=inside&utm_campaign=docs&utm_content=dashboard_footer' ); ?>" target="_blank">
			<?php esc_html_e( 'Docs', 'wpml-translation-check' ); ?>
		</a>

		<div class="automlp_ai_dashboard-social-icons">
			<?php
			$automlp_wpml_social_links = array(
				array( 'https://www.facebook.com/coolplugins/', 'facebook.svg', esc_html__( 'Facebook', 'wpml-translation-check' ) ),
				array( 'https://linkedin.com/company/coolplugins', 'linkedin.svg', esc_html__( 'LinkedIn', 'wpml-translation-check' ) ),
				array( 'https://x.com/cool_plugins', 'twitter.svg', esc_html__( 'Twitter / X', 'wpml-translation-check' ) ),
				array( 'https://www.youtube.com/@cool_plugins', 'youtube.svg', esc_html__( 'YouTube Channel', 'wpml-translation-check' ) ),
			);

			foreach ( $automlp_wpml_social_links as $link ) {
				echo '<a href="' . esc_url( $link[0] ) . '" target="_blank">
						<img src="' . esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/' . $link[1] ) . '" alt="' . esc_attr( $link[2] ) . '">
					  </a>';
			}
			?>
		</div>
	</div>
</div>