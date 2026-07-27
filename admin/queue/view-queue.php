<?php
/**
 * Queue history view.
 *
 * @package WPML_Auto_Translate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$automlp_data = \AUTOMLP_WPML\Admin\Queue_Screen::view_data();

$automlp_counts = $automlp_data['counts'];
$automlp_health = $automlp_data['health'];
$automlp_rows   = $automlp_data['rows'];
$automlp_filter = $automlp_data['filter'];

$automlp_active = (int) $automlp_counts['waiting']
	+ (int) $automlp_counts['claimed']
	+ (int) $automlp_counts['sent']
	+ (int) $automlp_counts['writing'];

// Clean base (no state) for All / filter links. Pagination keeps the filter.
$automlp_base_url = admin_url( 'admin.php?page=automlp_ai_dashboard&tab=queue' );
$automlp_page_url = $automlp_filter
	? add_query_arg( 'state', $automlp_filter, $automlp_base_url )
	: $automlp_base_url;
?>

<div class="automlp-queue">

	<?php if ( $automlp_data['notice'] ) : ?>
		<div class="notice notice-<?php echo esc_attr( $automlp_data['notice']['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $automlp_data['notice']['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'ok' !== $automlp_health['state'] ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'The queue is not running normally.', 'wpml-translation-check' ); ?></strong>
				<?php if ( $automlp_health['wp_cron_off'] ) : ?>
					<?php esc_html_e( 'WP-Cron is disabled on this site. Set up a real cron job that calls wp-cron.php, or use Process now below.', 'wpml-translation-check' ); ?>
				<?php elseif ( ! $automlp_health['next_run'] ) : ?>
					<?php esc_html_e( 'No scheduled run was found. Deactivating and reactivating the plugin will restore it.', 'wpml-translation-check' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'The queue has not run recently, so translations may be delayed.', 'wpml-translation-check' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="automlp-queue__stats">
		<?php
		$automlp_cards = array(
			'active'  => array(
				'label' => __( 'In progress', 'wpml-translation-check' ),
				'value' => $automlp_active,
				'class' => 'is-running',
			),
			'done'    => array(
				'label' => __( 'Completed', 'wpml-translation-check' ),
				'value' => (int) $automlp_counts['done'],
				'class' => 'is-done',
			),
			'failed'  => array(
				'label' => __( 'Failed', 'wpml-translation-check' ),
				'value' => (int) $automlp_counts['failed'],
				'class' => 'is-failed',
			),
			'stopped' => array(
				'label' => __( 'Cancelled', 'wpml-translation-check' ),
				'value' => (int) $automlp_counts['stopped'],
				'class' => 'is-stopped',
			),
		);

		foreach ( $automlp_cards as $automlp_key => $automlp_card ) :
			?>
			<a class="automlp-queue__stat <?php echo esc_attr( $automlp_card['class'] ); ?>"
			   href="<?php echo esc_url( add_query_arg( 'state', $automlp_key, $automlp_base_url ) ); ?>">
				<span class="automlp-queue__stat-value"><?php echo esc_html( number_format_i18n( $automlp_card['value'] ) ); ?></span>
				<span class="automlp-queue__stat-label"><?php echo esc_html( $automlp_card['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="automlp-queue__toolbar">
		<div class="automlp-queue__filters">
			<a href="<?php echo esc_url( $automlp_base_url ); ?>"
			   class="<?php echo '' === $automlp_filter ? 'is-active' : ''; ?>">
				<?php esc_html_e( 'All', 'wpml-translation-check' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'state', 'active', $automlp_base_url ) ); ?>"
			   class="<?php echo 'active' === $automlp_filter ? 'is-active' : ''; ?>">
				<?php esc_html_e( 'In progress', 'wpml-translation-check' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'state', 'failed', $automlp_base_url ) ); ?>"
			   class="<?php echo 'failed' === $automlp_filter ? 'is-active' : ''; ?>">
				<?php esc_html_e( 'Failed', 'wpml-translation-check' ); ?>
			</a>
		</div>

		<div class="automlp-queue__actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'automlp_queue_action' ); ?>
				<input type="hidden" name="action" value="automlp_queue_action">
				<input type="hidden" name="queue_action" value="run">
				<button type="submit" class="button button-primary" <?php disabled( 0, $automlp_active ); ?>>
					<?php esc_html_e( 'Process now', 'wpml-translation-check' ); ?>
				</button>
			</form>

			<?php if ( (int) $automlp_counts['failed'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'automlp_queue_action' ); ?>
					<input type="hidden" name="action" value="automlp_queue_action">
					<input type="hidden" name="queue_action" value="retry_all">
					<button type="submit" class="button">
						<?php esc_html_e( 'Retry failed', 'wpml-translation-check' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			      onsubmit="return confirm('<?php echo esc_js( __( 'Remove all finished jobs from this list? Translations are not affected.', 'wpml-translation-check' ) ); ?>');">
				<?php wp_nonce_field( 'automlp_queue_action' ); ?>
				<input type="hidden" name="action" value="automlp_queue_action">
				<input type="hidden" name="queue_action" value="clear">
				<button type="submit" class="button">
					<?php esc_html_e( 'Clear history', 'wpml-translation-check' ); ?>
				</button>
			</form>
		</div>
	</div>

	<?php if ( empty( $automlp_rows ) ) : ?>

		<div class="automlp-queue__empty">
			<p><strong><?php esc_html_e( 'Nothing here yet.', 'wpml-translation-check' ); ?></strong></p>
			<p>
				<?php esc_html_e( 'Translations you start from the Posts or Pages list will appear here, along with anything still running.', 'wpml-translation-check' ); ?>
			</p>
		</div>

	<?php else : ?>

		<table class="wp-list-table widefat fixed striped automlp-queue__table">
			<thead>
				<tr>
					<th scope="col" class="col-content"><?php esc_html_e( 'Content', 'wpml-translation-check' ); ?></th>
					<th scope="col" class="col-language"><?php esc_html_e( 'Language', 'wpml-translation-check' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'wpml-translation-check' ); ?></th>
					<th scope="col" class="col-queued"><?php esc_html_e( 'Queued', 'wpml-translation-check' ); ?></th>
					<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'wpml-translation-check' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $automlp_rows as $automlp_row ) : ?>
					<tr>
						<td class="col-content">
							<?php if ( $automlp_row['source_link'] ) : ?>
								<a href="<?php echo esc_url( $automlp_row['source_link'] ); ?>">
									<?php echo esc_html( $automlp_row['source_title'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $automlp_row['source_title'] ); ?>
							<?php endif; ?>

							<?php if ( 'string' === $automlp_row['kind'] ) : ?>
								<span class="automlp-queue__kind"><?php esc_html_e( 'Strings', 'wpml-translation-check' ); ?></span>
							<?php endif; ?>

							<?php if ( ! empty( $automlp_row['last_error'] ) ) : ?>
								<div class="automlp-queue__error"><?php echo esc_html( $automlp_row['last_error'] ); ?></div>
							<?php endif; ?>
						</td>

						<td class="col-language">
							<?php echo esc_html( $automlp_row['language'] ); ?>
						</td>

						<td class="col-status">
							<span class="automlp-queue__pill <?php echo esc_attr( $automlp_row['state_class'] ); ?>">
								<?php echo esc_html( $automlp_row['state_label'] ); ?>
							</span>

							<?php if ( (int) $automlp_row['attempts'] > 1 ) : ?>
								<span class="automlp-queue__attempts">
									<?php
									printf(
										/* translators: %d: number of attempts */
										esc_html__( 'Attempt %d', 'wpml-translation-check' ),
										(int) $automlp_row['attempts']
									);
									?>
								</span>
							<?php endif; ?>
						</td>

						<td class="col-queued">
							<?php echo esc_html( $automlp_row['queued_ago'] ); ?>
						</td>

						<td class="col-actions">
							<?php if ( $automlp_row['result_link'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $automlp_row['result_link'] ); ?>">
									<?php esc_html_e( 'Review', 'wpml-translation-check' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $automlp_row['can_retry'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automlp-queue__inline-form">
									<?php wp_nonce_field( 'automlp_queue_action' ); ?>
									<input type="hidden" name="action" value="automlp_queue_action">
									<input type="hidden" name="queue_action" value="retry">
									<input type="hidden" name="job_id" value="<?php echo esc_attr( $automlp_row['job_id'] ); ?>">
									<button type="submit" class="button button-small">
										<?php esc_html_e( 'Retry', 'wpml-translation-check' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $automlp_data['pages'] > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%', $automlp_page_url ),
								'format'    => '',
								'current'   => $automlp_data['page'],
								'total'     => $automlp_data['pages'],
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<?php if ( $automlp_active > 0 ) : ?>
		<p class="automlp-queue__refresh-note">
			<?php esc_html_e( 'This page refreshes automatically while translations are running. You can safely close it — translation continues in the background.', 'wpml-translation-check' ); ?>
		</p>
	<?php endif; ?>

</div>
