<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automlp_ai_dashboard-ai-translations">
	<div class="automlp_ai_dashboard-ai-translations-container">
		<div class="header">
			<h1><?php esc_html_e( 'AI Translations', 'wpml-translation-check' ); ?></h1>
			<div class="automlp_ai_dashboard-status">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=automlp_ai_dashboard&tab=settings' ) ); ?>"
					class="automlp_ai_dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'Configure AI Provider', 'wpml-translation-check' ); ?>
				</a>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e(
				'This addon uses the WordPress AI SDK (wp-ai-client) to connect to multiple AI providers. Choose a model per provider and then use WPML Translation Management to auto-translate content.',
				'wpml-translation-check'
			); ?>
		</p>

		<div class="automlp_ai_dashboard-translations">
			<?php
			$automlp_wpml_ai_translations = array(
				array(
					'logo'       => 'openai-translate-logo.png',
					'alt'        => 'OpenAI',
					'title'      => esc_html__( 'OpenAI Models', 'wpml-translation-check' ),
					'description'=> esc_html__( 'Use OpenAI models (like GPT) via the AI SDK registry for context-aware translations.', 'wpml-translation-check' ),
					'icon'       => 'open-ai-translate.png',
					'url'        => 'https://youtu.be/ynJJGH2qpXE?si=6NaSMcta-igDe0QP',
				),
				array(
					'logo'       => 'geminiai-logo.png',
					'alt'        => 'Google / Gemini',
					'title'      => esc_html__( 'Google Gemini Models', 'wpml-translation-check' ),
					'description'=> esc_html__( 'Use Google Gemini models registered in the AI SDK for fast and accurate translations.', 'wpml-translation-check' ),
					'icon'       => 'gemini-translate.png',
					'url'        => 'https://youtu.be/03Hcs6PnWU4',
				),
			);

			foreach ( $automlp_wpml_ai_translations as $automlp_wpml_ai_translation ) :
				?>
				<div class="automlp_ai_dashboard-translation-card">
					<div class="logo">
						<img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'assets/images/' . $automlp_wpml_ai_translation['logo'] ); ?>"
							alt="<?php echo esc_attr( $automlp_wpml_ai_translation['alt'] ); ?>">
					</div>
					<h3><?php echo esc_html( $automlp_wpml_ai_translation['title'] ); ?></h3>
					<p><?php echo esc_html( $automlp_wpml_ai_translation['description'] ); ?></p>
					<div class="play-btn-container">
						<a href="<?php echo esc_url( $automlp_wpml_ai_translation['url'] ); ?>" target="_blank">
							<img src="<?php echo esc_url( AUTOMLP_AI_PLUGIN_URL . 'admin/automlp-ai-dashboard/images/' . $automlp_wpml_ai_translation['icon'] ); ?>"
								alt="<?php echo esc_attr( $automlp_wpml_ai_translation['alt'] ); ?>">
						</a>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>
	</div>
</div>