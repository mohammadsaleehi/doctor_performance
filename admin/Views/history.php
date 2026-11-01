<?php
/**
 * History view for WP Performance Doctor admin.
 *
 * Displays historical monitoring snapshots and diagnostic runs.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
?>
<div class="wrap wp-performance-doctor wpd-history">

	<!-- Page header -->
	<header class="wpd-page-header">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php  // phpcs:ignore WordPress.Security.EscapeOutput -- Safe SVG from wpd_icon() ?>
				<?php esc_html_e( 'History', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php esc_html_e( 'Review historical monitoring snapshots and diagnostic runs.', 'wp-performance-doctor' ); ?>
			</p>
		</div>
	</header>

	<?php
	$snapshots = $data['snapshots'] ?? array();
	$runs      = $data['runs'] ?? array();
	$has_data  = ! empty( $data['has_data'] );

	if ( ! $has_data ) :
		?>
		<div class="wpd-empty-state">
			<?php  // phpcs:ignore WordPress.Security.EscapeOutput -- Safe SVG from wpd_icon() ?>
			<h2 class="wpd-empty-state-title"><?php esc_html_e( 'No history yet', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-empty-state-description">
				<?php esc_html_e( 'Monitoring snapshots and diagnostic runs will appear here once data has been collected.', 'wp-performance-doctor' ); ?>
			</p>
		</div>
	<?php else : ?>
		<!-- Monitoring snapshots -->
		<?php if ( ! empty( $snapshots ) ) : ?>
			<section class="wpd-section">
				<div class="wpd-section-header">
					<h2 class="wpd-section-title">
						<?php esc_html_e( 'Monitoring Snapshots', 'wp-performance-doctor' ); ?>
					</h2>
				</div>

				<div class="wpd-card">
					<div class="wpd-card-body wpd-card--compact">
						<table class="wpd-table wpd-table--compact">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Time', 'wp-performance-doctor' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></th>
									<th scope="col" class="wpd-table-cell-numeric"><?php esc_html_e( 'Metrics', 'wp-performance-doctor' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $snapshots as $snapshot ) : ?>
									<tr>
										<td><?php echo esc_html( MetricFormatter::format_date( $snapshot['timestamp'] ?? '' ) ); ?></td>
										<td>
<td class="wpd-status-badge wpd-status-badge--small">status</td>
<?php echo esc_html( MetricFormatter::format_seconds( $snapshot['collection_duration'] ?? 0 ) ); ?>
											</span>
										</td>
<td><?php echo esc_html( MetricFormatter::format_seconds( $snapshot['collection_duration'] ?? 0 ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $snapshot['trigger_type'] ?? 'manual' ) ); ?></td>
<td class="wpd-table-cell-numeric"><?php echo esc_html( $snapshot['metric_count'] ?? 0 ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- Diagnostic runs -->
		<?php if ( ! empty( $runs ) ) : ?>
			<section class="wpd-section">
				<div class="wpd-section-header">
					<h2 class="wpd-section-title">
						<?php esc_html_e( 'Diagnostic Runs', 'wp-performance-doctor' ); ?>
					</h2>
				</div>

				<div class="wpd-card">
					<div class="wpd-card-body wpd-card--compact">
						<table class="wpd-table wpd-table--compact">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Time', 'wp-performance-doctor' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
									<th scope="col" class="wpd-table-cell-numeric"><?php esc_html_e( 'Health Score', 'wp-performance-doctor' ); ?></th>
									<th scope="col" class="wpd-table-cell-numeric"><?php esc_html_e( 'Findings', 'wp-performance-doctor' ); ?></th>
									<th scope="col" class="wpd-table-cell-numeric"><?php esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $runs as $run ) : ?>
									<tr>
											<td><?php echo esc_html( $run['completed_at'] ?? $run['started_at'] ?? '' ); ?></td>
										<td>
											<span class="wpd-status-badge wpd-status-badge--small <?php echo esc_attr( 'completed' === ( $run['status'] ?? '' ) ? 'wpd-status--completed' : 'wpd-status--failed'; ?>">
												<?php echo esc_html( ucfirst( $run['status'] ?? 'unknown' ); ?>
											</span>
										</td>
										<td class="wpd-table-cell-numeric">
											<?php
											$score = $run['health_score'] ?? null;
											if ( null === $score ) :
												?>
												<span class="wpd-small-text"><?php esc_html_e( 'N/A', 'wp-performance-doctor' ); ?></span>
											<?php else : ?>
												<span class="wpd-numeric-value"><?php echo esc_html( $score; ?></span>
											<?php endif; ?>
										</td>
										<td class="wpd-table-cell-numeric"><?php echo esc_html( $run['findings_count'] ?? 0; ?></td>
										<td><?php echo esc_html( (float) ( $run['execution_duration'] ?? 0 ) . 's'; ?></td>
										<td><?php echo esc_html( ucfirst( $run['trigger_type'] ?? 'manual' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

</div>
<?php

<?php

<?php
