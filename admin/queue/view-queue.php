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

$automlp_base_url = admin_url( 'admin.php?page=automlp_ai_dashboard&tab=queue' );

// The filters currently applied. Every link on the screen keeps them, so sorting or paging
// never throws the selection away.
$automlp_current = array(
	'state'       => $automlp_filter,
	'target_lang' => $automlp_data['lang'],
	'type'        => $automlp_data['type'],
	's'           => $automlp_data['search'],
	'orderby'     => 'job_id' === $automlp_data['orderby'] ? '' : $automlp_data['orderby'],
	'order'       => 'desc' === $automlp_data['order'] ? '' : $automlp_data['order'],
);

/**
 * URL of this screen with some of the current filters changed.
 *
 * @param array $automlp_change Query args to set; an empty value removes the arg.
 * @return string
 */
$automlp_url = static function ( array $automlp_change = array() ) use ( $automlp_base_url, $automlp_current ) {
	$args = array_filter(
		array_merge( $automlp_current, $automlp_change ),
		static function ( $value ) {
			return '' !== $value && null !== $value;
		}
	);

	return add_query_arg( $args, $automlp_base_url );
};

// Only failed and in-flight jobs can be retried or deleted; finished ones are history.
$automlp_selectable = count(
	array_filter(
		$automlp_rows,
		static function ( $row ) {
			return $row['can_retry'] || ! empty( $row['can_cancel'] );
		}
	)
);

$automlp_is_filtered = '' !== $automlp_filter || '' !== $automlp_data['lang'] || '' !== $automlp_data['type'] || '' !== $automlp_data['search'];

$automlp_views = array(
	''        => array( __( 'All', 'wpml-translation-check' ), array_sum( $automlp_counts ) ),
	'active'  => array( __( 'In progress', 'wpml-translation-check' ), $automlp_active ),
	'done'    => array( __( 'Completed', 'wpml-translation-check' ), (int) $automlp_counts['done'] ),
	'failed'  => array( __( 'Failed', 'wpml-translation-check' ), (int) $automlp_counts['failed'] ),
	'stopped' => array( __( 'Cancelled', 'wpml-translation-check' ), (int) $automlp_counts['stopped'] ),
);

/**
 * A sortable column heading.
 *
 * @param string $automlp_key   Sort key understood by Queue_Table::browse().
 * @param string $automlp_label Visible heading.
 * @param string $automlp_class Column class.
 * @return void
 */
$automlp_sortable = static function ( $automlp_key, $automlp_label, $automlp_class ) use ( $automlp_data, $automlp_url ) {
	$is_current = $automlp_data['orderby'] === $automlp_key;
	$next       = $is_current && 'asc' === $automlp_data['order'] ? 'desc' : 'asc';
	$aria       = 'none';

	if ( $is_current ) {
		$aria = 'asc' === $automlp_data['order'] ? 'ascending' : 'descending';
	}
	?>
	<?php
	$automlp_sort_url = $automlp_url(
		array(
			'orderby' => $automlp_key,
			'order'   => $next,
			'paged'   => '',
		)
	);
	?>
	<th scope="col" class="<?php echo esc_attr( $automlp_class . ' is-sortable' . ( $is_current ? ' is-sorted is-' . $automlp_data['order'] : '' ) ); ?>" aria-sort="<?php echo esc_attr( $aria ); ?>">
		<a href="<?php echo esc_url( $automlp_sort_url ); ?>">
			<span><?php echo esc_html( $automlp_label ); ?></span>
			<span class="automlp-queue__sort" aria-hidden="true"></span>
		</a>
	</th>
	<?php
};
?>

<div class="automlp-queue" data-active="<?php echo esc_attr( $automlp_active ); ?>">

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

	<div class="automlp-queue__card">

		<div class="automlp-queue__head">
			<p class="automlp-queue__intro">
				<?php esc_html_e( 'Track every translation AutoMLP has run: its status, who translated it and when. Use this page to monitor progress, retry failures or remove jobs you no longer need.', 'wpml-translation-check' ); ?>
			</p>

			<div class="automlp-queue__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'automlp_queue_action' ); ?>
					<input type="hidden" name="action" value="automlp_queue_action">
					<input type="hidden" name="queue_action" value="run">
					<button type="submit" class="automlp-queue__btn is-primary" <?php disabled( 0, $automlp_active ); ?>>
						<?php esc_html_e( 'Process now', 'wpml-translation-check' ); ?>
					</button>
				</form>

				<?php if ( (int) $automlp_counts['failed'] > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'automlp_queue_action' ); ?>
						<input type="hidden" name="action" value="automlp_queue_action">
						<input type="hidden" name="queue_action" value="retry_all">
						<button type="submit" class="automlp-queue__btn">
							<?php esc_html_e( 'Retry failed', 'wpml-translation-check' ); ?>
						</button>
					</form>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="clear">
					<?php wp_nonce_field( 'automlp_queue_action' ); ?>
					<input type="hidden" name="action" value="automlp_queue_action">
					<input type="hidden" name="queue_action" value="clear">
					<button type="submit" class="automlp-queue__btn">
						<?php esc_html_e( 'Clear history', 'wpml-translation-check' ); ?>
					</button>
				</form>
			</div>
		</div>

		<ul class="automlp-queue__views" aria-label="<?php esc_attr_e( 'Filter by status', 'wpml-translation-check' ); ?>">
			<?php foreach ( $automlp_views as $automlp_view_key => $automlp_view ) : ?>
				<li>
					<a href="<?php echo esc_url( $automlp_url( array( 'state' => $automlp_view_key ) ) ); ?>"
						class="<?php echo $automlp_filter === $automlp_view_key ? 'is-active' : ''; ?>"
						<?php echo $automlp_filter === $automlp_view_key ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $automlp_view[0] ); ?>
						<span class="automlp-queue__count"><?php echo esc_html( number_format_i18n( $automlp_view[1] ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="automlp-queue__filters">
			<input type="hidden" name="page" value="automlp_ai_dashboard">
			<input type="hidden" name="tab" value="queue">
			<?php if ( '' !== $automlp_filter ) : ?>
				<input type="hidden" name="state" value="<?php echo esc_attr( $automlp_filter ); ?>">
			<?php endif; ?>
			<?php if ( '' !== $automlp_current['orderby'] ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $automlp_current['orderby'] ); ?>">
				<input type="hidden" name="order" value="<?php echo esc_attr( $automlp_data['order'] ); ?>">
			<?php endif; ?>

			<label class="screen-reader-text" for="automlp-queue-search"><?php esc_html_e( 'Search jobs', 'wpml-translation-check' ); ?></label>
			<input type="search" id="automlp-queue-search" name="s" class="automlp-queue__search"
				value="<?php echo esc_attr( $automlp_data['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'Search by title, job ID or error', 'wpml-translation-check' ); ?>">

			<?php if ( ! empty( $automlp_data['types'] ) ) : ?>
				<label class="screen-reader-text" for="automlp-queue-type"><?php esc_html_e( 'Filter by type', 'wpml-translation-check' ); ?></label>
				<select id="automlp-queue-type" name="type" class="automlp-queue__select">
					<option value=""><?php esc_html_e( 'All types', 'wpml-translation-check' ); ?></option>
					<?php foreach ( $automlp_data['types'] as $automlp_type_key => $automlp_type_label ) : ?>
						<option value="<?php echo esc_attr( $automlp_type_key ); ?>" <?php selected( $automlp_data['type'], $automlp_type_key ); ?>>
							<?php echo esc_html( $automlp_type_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( ! empty( $automlp_data['languages'] ) ) : ?>
				<label class="screen-reader-text" for="automlp-queue-lang"><?php esc_html_e( 'Filter by language', 'wpml-translation-check' ); ?></label>
				<select id="automlp-queue-lang" name="target_lang" class="automlp-queue__select">
					<option value=""><?php esc_html_e( 'All languages', 'wpml-translation-check' ); ?></option>
					<?php foreach ( $automlp_data['languages'] as $automlp_lang_code => $automlp_lang_label ) : ?>
						<option value="<?php echo esc_attr( $automlp_lang_code ); ?>" <?php selected( $automlp_data['lang'], $automlp_lang_code ); ?>>
							<?php echo esc_html( $automlp_lang_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<button type="submit" class="automlp-queue__btn"><?php esc_html_e( 'Filter', 'wpml-translation-check' ); ?></button>

			<?php if ( $automlp_is_filtered ) : ?>
				<a class="automlp-queue__reset" href="<?php echo esc_url( $automlp_base_url ); ?>"><?php esc_html_e( 'Reset filters', 'wpml-translation-check' ); ?></a>
			<?php endif; ?>
		</form>

		<?php if ( empty( $automlp_rows ) ) : ?>

			<div class="automlp-queue__empty">
				<?php if ( $automlp_is_filtered ) : ?>
					<p><strong><?php esc_html_e( 'No jobs match these filters.', 'wpml-translation-check' ); ?></strong></p>
					<p><a href="<?php echo esc_url( $automlp_base_url ); ?>"><?php esc_html_e( 'Show all jobs', 'wpml-translation-check' ); ?></a></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Nothing here yet.', 'wpml-translation-check' ); ?></strong></p>
					<p>
						<?php esc_html_e( 'Translations you start from the Posts or Pages list will appear here, along with anything still running.', 'wpml-translation-check' ); ?>
					</p>
				<?php endif; ?>
			</div>

		<?php else : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automlp-queue__bulk-form">
				<?php wp_nonce_field( 'automlp_queue_action' ); ?>
				<input type="hidden" name="action" value="automlp_queue_action">
				<input type="hidden" name="queue_action" value="bulk">

				<div class="automlp-queue__bulkbar">
					<label class="screen-reader-text" for="automlp-bulk-top"><?php esc_html_e( 'Bulk actions', 'wpml-translation-check' ); ?></label>
					<select name="bulk_action" id="automlp-bulk-top" class="automlp-queue__select" data-bulk-select <?php disabled( 0, $automlp_selectable ); ?>>
						<option value=""><?php esc_html_e( 'Bulk actions', 'wpml-translation-check' ); ?></option>
						<option value="retry"><?php esc_html_e( 'Retry', 'wpml-translation-check' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'wpml-translation-check' ); ?></option>
					</select>
					<button type="submit" class="automlp-queue__btn" data-bulk-apply disabled><?php esc_html_e( 'Apply', 'wpml-translation-check' ); ?></button>
					<span class="automlp-queue__total">
						<?php
						printf(
							/* translators: %s: number of jobs */
							esc_html( _n( '%s job', '%s jobs', (int) $automlp_data['total'], 'wpml-translation-check' ) ),
							esc_html( number_format_i18n( (int) $automlp_data['total'] ) )
						);
						?>
					</span>
				</div>

				<div class="automlp-queue__scroll">
					<table class="automlp-queue__table">
						<thead>
							<tr>
								<?php // A disabled checkbox shows no tooltip, so the explanation sits on the cell. ?>
								<td class="col-check"<?php echo 0 === $automlp_selectable ? ' title="' . esc_attr__( 'Nothing to select: only failed or running jobs can be retried or deleted.', 'wpml-translation-check' ) . '"' : ''; ?>>
									<label class="screen-reader-text" for="automlp-select-all"><?php esc_html_e( 'Select all jobs', 'wpml-translation-check' ); ?></label>
									<input type="checkbox" id="automlp-select-all" data-select-all <?php disabled( 0, $automlp_selectable ); ?>>
								</td>
								<?php $automlp_sortable( 'job_id', __( 'Job ID', 'wpml-translation-check' ), 'col-id' ); ?>
								<?php $automlp_sortable( 'title', __( 'Content', 'wpml-translation-check' ), 'col-content' ); ?>
								<?php $automlp_sortable( 'language', __( 'Languages', 'wpml-translation-check' ), 'col-language' ); ?>
								<?php $automlp_sortable( 'queued', __( 'Queued', 'wpml-translation-check' ), 'col-queued' ); ?>
								<?php $automlp_sortable( 'state', __( 'Status', 'wpml-translation-check' ), 'col-status' ); ?>
								<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'wpml-translation-check' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $automlp_rows as $automlp_row ) : ?>
								<tr>
									<td class="col-check">
										<?php if ( $automlp_row['can_retry'] || ! empty( $automlp_row['can_cancel'] ) ) : ?>
											<label class="screen-reader-text" for="automlp-job-<?php echo esc_attr( $automlp_row['job_id'] ); ?>">
												<?php
												printf(
													/* translators: %s: content title */
													esc_html__( 'Select %s', 'wpml-translation-check' ),
													esc_html( $automlp_row['source_title'] )
												);
												?>
											</label>
											<input type="checkbox" name="job_ids[]" id="automlp-job-<?php echo esc_attr( $automlp_row['job_id'] ); ?>" value="<?php echo esc_attr( $automlp_row['job_id'] ); ?>" data-row-check>
										<?php endif; ?>
									</td>

									<td class="col-id"><?php echo esc_html( $automlp_row['job_id'] ); ?></td>

									<td class="col-content">
										<?php if ( $automlp_row['source_link'] ) : ?>
											<a href="<?php echo esc_url( $automlp_row['source_link'] ); ?>">
												<?php echo esc_html( $automlp_row['source_title'] ); ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $automlp_row['source_title'] ); ?>
										<?php endif; ?>

										<span class="automlp-queue__sub">
											<?php
											echo esc_html(
												implode(
													' · ',
													array_filter(
														array(
															$automlp_row['type_label'],
															trim( $automlp_row['provider'] . ' ' . $automlp_row['model'] ),
														),
														static function ( $part ) {
															return '' !== $part && '—' !== $part;
														}
													)
												)
											);
											?>
										</span>

										<?php if ( ! empty( $automlp_row['last_error'] ) ) : ?>
											<div class="automlp-queue__error"><?php echo esc_html( $automlp_row['last_error'] ); ?></div>
										<?php endif; ?>
									</td>

									<td class="col-language"><?php echo esc_html( $automlp_row['languages'] ); ?></td>

									<td class="col-queued">
										<span title="<?php echo esc_attr( $automlp_row['queued_full'] ); ?>"><?php echo esc_html( $automlp_row['queued_ago'] ); ?></span>
									</td>

									<td class="col-status">
										<span class="automlp-queue__pill <?php echo esc_attr( $automlp_row['state_class'] ); ?>">
											<?php echo esc_html( $automlp_row['state_label'] ); ?>
										</span>

										<?php if ( (int) $automlp_row['attempts'] > 1 ) : ?>
											<span class="automlp-queue__sub">
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

									<td class="col-actions">
										<?php if ( $automlp_row['result_link'] ) : ?>
											<a class="automlp-queue__btn is-small" href="<?php echo esc_url( $automlp_row['result_link'] ); ?>">
												<?php esc_html_e( 'Review', 'wpml-translation-check' ); ?>
											</a>
										<?php endif; ?>

										<?php if ( $automlp_row['can_retry'] ) : ?>
											<button type="submit" class="automlp-queue__btn is-small" form="automlp-row-<?php echo esc_attr( $automlp_row['job_id'] ); ?>-retry">
												<?php esc_html_e( 'Retry', 'wpml-translation-check' ); ?>
											</button>
										<?php endif; ?>

										<?php if ( ! empty( $automlp_row['can_cancel'] ) ) : ?>
											<button type="submit" class="automlp-queue__btn is-small is-danger" form="automlp-row-<?php echo esc_attr( $automlp_row['job_id'] ); ?>-cancel"
												aria-label="<?php echo esc_attr( sprintf( /* translators: %s: content title */ __( 'Delete queue job for %s', 'wpml-translation-check' ), $automlp_row['source_title'] ) ); ?>">
												<?php esc_html_e( 'Delete', 'wpml-translation-check' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="automlp-queue__bulkbar is-bottom">
					<span class="automlp-queue__total">
						<?php
						$automlp_from = ( ( $automlp_data['page'] - 1 ) * $automlp_data['per_page'] ) + 1;
						$automlp_to   = min( $automlp_data['total'], $automlp_from + count( $automlp_rows ) - 1 );
						printf(
							/* translators: 1: first job shown, 2: last job shown, 3: total jobs */
							esc_html__( 'Showing %1$s–%2$s of %3$s', 'wpml-translation-check' ),
							esc_html( number_format_i18n( $automlp_from ) ),
							esc_html( number_format_i18n( $automlp_to ) ),
							esc_html( number_format_i18n( (int) $automlp_data['total'] ) )
						);
						?>
					</span>

					<?php if ( $automlp_data['pages'] > 1 ) : ?>
						<nav class="automlp-queue__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'wpml-translation-check' ); ?>">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%', $automlp_url() ),
										'format'    => '',
										'current'   => $automlp_data['page'],
										'total'     => $automlp_data['pages'],
										'prev_text' => '&lsaquo;',
										'next_text' => '&rsaquo;',
										'type'      => 'plain',
									)
								)
							);
							?>
						</nav>
					<?php endif; ?>
				</div>
			</form>

			<?php // Row actions live outside the bulk form (forms cannot nest) and are wired up with the button form attribute. ?>
			<?php foreach ( $automlp_rows as $automlp_row ) : ?>
				<?php if ( $automlp_row['can_retry'] ) : ?>
					<form method="post" id="automlp-row-<?php echo esc_attr( $automlp_row['job_id'] ); ?>-retry" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automlp-queue__row-form">
						<?php wp_nonce_field( 'automlp_queue_action', '_wpnonce', false ); ?>
						<input type="hidden" name="action" value="automlp_queue_action">
						<input type="hidden" name="queue_action" value="retry">
						<input type="hidden" name="job_id" value="<?php echo esc_attr( $automlp_row['job_id'] ); ?>">
					</form>
				<?php endif; ?>
				<?php if ( ! empty( $automlp_row['can_cancel'] ) ) : ?>
					<form method="post" id="automlp-row-<?php echo esc_attr( $automlp_row['job_id'] ); ?>-cancel" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automlp-queue__row-form" data-confirm="delete-one">
						<?php wp_nonce_field( 'automlp_queue_action', '_wpnonce', false ); ?>
						<input type="hidden" name="action" value="automlp_queue_action">
						<input type="hidden" name="queue_action" value="cancel">
						<input type="hidden" name="job_id" value="<?php echo esc_attr( $automlp_row['job_id'] ); ?>">
					</form>
				<?php endif; ?>
			<?php endforeach; ?>

		<?php endif; ?>

	</div>

	<?php if ( $automlp_active > 0 ) : ?>
		<p class="automlp-queue__refresh-note">
			<?php esc_html_e( 'This page refreshes automatically while translations are running. You can safely close it — translation continues in the background.', 'wpml-translation-check' ); ?>
		</p>
	<?php endif; ?>

</div>
