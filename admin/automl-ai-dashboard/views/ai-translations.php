<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="automl_ai_dashboard-ai-translations">
	<div class="automl_ai_dashboard-ai-translations-container">
		<div class="header">
			<h1><?php esc_html_e( 'AI Translations', 'automl-ai-translation-for-wpml' ); ?></h1>
			<div class="automl_ai_dashboard-status">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=automl_ai_dashboard&tab=settings' ) ); ?>"
					class="automl_ai_dashboard-btn"
					target="_blank">
					<?php esc_html_e( 'Configure AI Provider', 'automl-ai-translation-for-wpml' ); ?>
				</a>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e(
				'This addon uses the WordPress AI SDK (wp-ai-client) to connect to multiple AI providers. Choose a model per provider and then use WPML Translation Management to auto-translate content.',
				'automl-ai-translation-for-wpml'
			); ?>
		</p>

		<div class="automl_ai_dashboard-translations">
			<?php
			$automl_wpml_ai_translations = array(
				array(
					'logo'       => 'openai-translate-logo.png',
					'alt'        => 'OpenAI',
					'title'      => esc_html__( 'OpenAI Models', 'automl-ai-translation-for-wpml' ),
					'description'=> esc_html__( 'Use OpenAI models (like GPT) via the AI SDK registry for context-aware translations.', 'automl-ai-translation-for-wpml' ),
					'icon'       => 'open-ai-translate.png',
					'url'        => 'https://youtu.be/ynJJGH2qpXE?si=6NaSMcta-igDe0QP',
				),
				array(
					'logo'       => 'geminiai-logo.png',
					'alt'        => 'Google / Gemini',
					'title'      => esc_html__( 'Google Gemini Models', 'automl-ai-translation-for-wpml' ),
					'description'=> esc_html__( 'Use Google Gemini models registered in the AI SDK for fast and accurate translations.', 'automl-ai-translation-for-wpml' ),
					'icon'       => 'gemini-translate.png',
					'url'        => 'https://youtu.be/03Hcs6PnWU4',
				),
			);

			foreach ( $automl_wpml_ai_translations as $automl_wpml_ai_translation ) :
				?>
				<div class="automl_ai_dashboard-translation-card">
					<div class="logo">
						<img src="<?php echo esc_url( AUTOML_AI_PLUGIN_URL . 'assets/images/' . $automl_wpml_ai_translation['logo'] ); ?>"
							alt="<?php echo esc_attr( $automl_wpml_ai_translation['alt'] ); ?>">
					</div>
					<h3><?php echo esc_html( $automl_wpml_ai_translation['title'] ); ?></h3>
					<p><?php echo esc_html( $automl_wpml_ai_translation['description'] ); ?></p>
					<div class="play-btn-container">
						<a href="<?php echo esc_url( $automl_wpml_ai_translation['url'] ); ?>" target="_blank">
							<img src="<?php echo esc_url( AUTOML_AI_PLUGIN_URL . 'admin/automl-ai-dashboard/images/' . $automl_wpml_ai_translation['icon'] ); ?>"
								alt="<?php echo esc_attr( $automl_wpml_ai_translation['alt'] ); ?>">
						</a>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>
	</div>
</div>