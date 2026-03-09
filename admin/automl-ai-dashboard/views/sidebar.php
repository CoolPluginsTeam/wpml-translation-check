<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers
 */

if ( ! function_exists( 'automl_ai_format_time_taken' ) ) :
	/**
	 * Format total time taken in a readable way.
	 *
	 * @param int $time_taken Seconds.
	 * @return string
	 */
	function automl_ai_format_time_taken( $time_taken ) {
		if ( 0 === intval( $time_taken ) ) {
			return esc_html__( '0', 'wpml-translation-check' );
		}

		$time_taken = intval( $time_taken );

		if ( $time_taken < 60 ) {
			// translators: %d: seconds.
			return sprintf( esc_html__( '%d sec', 'wpml-translation-check' ), $time_taken );
		}

		if ( $time_taken < 3600 ) {
			$min = floor( $time_taken / 60 );
			$sec = $time_taken % 60;
			// translators: 1: minutes, 2: seconds.
			return sprintf( esc_html__( '%1$d min %2$d sec', 'wpml-translation-check' ), $min, $sec );
		}

		$hours = floor( $time_taken / 3600 );
		$min   = floor( ( $time_taken % 3600 ) / 60 );
		// translators: 1: hours, 2: minutes.
		return sprintf( esc_html__( '%1$d hours %2$d min', 'wpml-translation-check' ), $hours, $min );
	}
endif;

if ( ! function_exists( 'automl_ai_is_plugin_installed' ) ) :
	/**
	 * Check if a specific plugin is installed.
	 *
	 * @param string $plugin_slug Plugin slug key.
	 * @return bool
	 */
	function automl_ai_is_plugin_installed( $plugin_slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( 'automatic-translator-addon-for-loco-translate' === $plugin_slug ) {
			return isset( $plugins['automatic-translator-addon-for-loco-translate/automatic-translator-addon-for-loco-translate.php'] )
				|| isset( $plugins['loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php'] );
		}

		return false;
	}
endif;

if ( ! function_exists( 'automl_ai_get_plugin_display_name' ) ) :
	/**
	 * Get display name for addon plugin (free / pro).
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return string
	 */
	function automl_ai_get_plugin_display_name( $plugin_slug ) {
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();

		$plugin_paths = array(
			'automatic-translator-addon-for-loco-translate' => array(
				'free'      => 'automatic-translator-addon-for-loco-translate/automatic-translator-addon-for-loco-translate.php',
				'pro'       => 'loco-automatic-translate-addon-pro/loco-automatic-translate-addon-pro.php',
				'free_name' => esc_html__( 'LocoAI – Auto Translate For Loco Translate', 'wpml-translation-check' ),
				'pro_name'  => esc_html__( 'LocoAI – Auto Translate for Loco Translate (Pro)', 'wpml-translation-check' ),
			),
		);

		if ( ! isset( $plugin_paths[ $plugin_slug ] ) ) {
			return $plugin_slug;
		}

		$free_installed = isset( $plugins[ $plugin_paths[ $plugin_slug ]['free'] ] );
		$pro_installed  = isset( $plugins[ $plugin_paths[ $plugin_slug ]['pro'] ] );

		if ( $pro_installed ) {
			return $plugin_paths[ $plugin_slug ]['pro_name'];
		} elseif ( $free_installed ) {
			return $plugin_paths[ $plugin_slug ]['free_name'];
		}

		return $plugin_paths[ $plugin_slug ]['free_name'];
	}
endif;

if ( ! function_exists( 'automl_ai_format_number' ) ) :
	/**
	 * Format big numbers as K/M/B.
	 *
	 * @param int $number Number.
	 * @return string
	 */
	function automl_ai_format_number( $number ) {
		$number = intval( $number );

		if ( $number >= 1000000000 ) {
			return round( $number / 1000000000, 1 ) . esc_html__( 'B', 'wpml-translation-check' );
		} elseif ( $number >= 1000000 ) {
			return round( $number / 1000000, 1 ) . esc_html__( 'M', 'wpml-translation-check' );
		} elseif ( $number >= 1000 ) {
			return round( $number / 1000, 1 ) . esc_html__( 'K', 'wpml-translation-check' );
		}

		return (string) $number;
	}
endif;
?>

<!-- Right Sidebar -->
<div class="automl_ai_dashboard-sidebar">
	<div class="automl_ai_dashboard-status">
		<h3><?php esc_html_e( 'Auto Translation Status', 'wpml-translation-check' ); ?></h3>
		<div class="automl_ai_dashboard-sts-top">
			<?php
			// You can later store stats in an option similar to this.
			$automl_wpml_all_translation_data = get_option( 'automl_ai_dashboard_data', array() );

			if ( ! is_array( $automl_wpml_all_translation_data ) || ! isset( $automl_wpml_all_translation_data['automl_ai'] ) ) {
				$automl_wpml_all_translation_data['automl_ai'] = array();
			}

			$automl_valid_providers = array('openai'=>'OpenAI Characters', 'google_ai'=>'Google Characters');
			$totals = array_reduce(
				$automl_wpml_all_translation_data['automl_ai'],
				function ( $carry, $translation ) {
					$carry['string_count']    += intval( $translation['string_count'] ?? 0 );
					$carry['character_count'] += intval( $translation['character_count'] ?? 0 );
					$carry['time_taken']      += intval( $translation['time_taken'] ?? 0 );

					if(!isset($carry[$translation['service_provider']])){
						$carry[$translation['service_provider']] = 0;
					}

					$carry[$translation['service_provider']] += intval( $translation['character_count'] ?? 0 );

					if ( ! empty( $translation['post_id'] ) ) {
						$carry['translation_count']++;
					}
					return $carry;
				},
				array(
					'string_count'      => 0,
					'character_count'   => 0,
					'time_taken'        => 0,
					'translation_count' => 0,
				)
			);
			$automl_wpml_time_taken_str = automl_ai_format_time_taken( $totals['time_taken'] );
			?>
			<span><?php echo esc_html( automl_ai_format_number( $totals['character_count'] ) ); ?></span>
			<span><?php esc_html_e( 'Total Characters Translated!', 'wpml-translation-check' ); ?></span>
		</div>
		<ul class="automl_ai_dashboard-sts-btm">
			<li>
				<span><?php esc_html_e( 'Total Strings', 'wpml-translation-check' ); ?></span>
				<span><?php echo esc_html( automl_ai_format_number( $totals['string_count'] ) ); ?></span>
			</li>
			<?php foreach($automl_valid_providers as $provider_key => $provider_name): // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
				<?php if(isset($totals[$provider_key])): ?>
				<li>
						<span><?php echo esc_html( ucfirst( $provider_name ) ); ?></span>
						<span><?php echo esc_html( automl_ai_format_number( $totals[$provider_key] ) ); ?></span>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			<li>
				<span><?php esc_html_e( 'Total Translation Jobs', 'wpml-translation-check' ); ?></span>
				<span><?php echo esc_html( $totals['translation_count'] ); ?></span>
			</li>
			<li>
				<span><?php esc_html_e( 'Time Taken', 'wpml-translation-check' ); ?></span>
				<span><?php echo esc_html( $automl_wpml_time_taken_str ); ?></span>
			</li>
		</ul>
	</div>
</div>