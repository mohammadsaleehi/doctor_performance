<?php
/**
 * History view for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;

?>
<div class="wrap wp-performance-doctor wpd-history">

	<?php
	$snapshots = $data['snapshots'] ?? array();
	$runs      = $data['runs'] ?? array();
	$has_data  = ! empty( $snapshots ) || ! empty( $runs );
	?>

	<!-- Page header -->
	<header class="wpd-page-header">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php echo ''; // wpd_icon placeholder ?>
				<?php esc_html_e( 'History', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php esc_html_e( 'View historical monitoring snapshots and diagnostic runs.', 'wp-performance-doctor' ); ?>
			</p>
		</div>
	</header>

	<?php if ( ! $has_data ) : ?>
		<div class="wpd-empty-state">
			<h1><?php esc_html_e( 'No history yet', 'wp-performance-doctor' ); ?></h1>
			<p><?php esc_html_e( 'No monitoring snapshots or diagnostic runs have been recorded yet.', 'wp-performance-doctor' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Monitoring snapshots -->
	<?php if ( ! empty( $snapshots ) ) : ?>
		<section class="wpd-section">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Monitoring Snapshots', 'wp-performance-doctor' ); ?></h2>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<table class="wpd-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Time', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Metrics', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $snapshots as $snapshot ) : ?>
								<tr>
									<td><?php echo esc_html( $snapshot['timestamp'] ?? '' ); ?></td>
									<td><span class="wpd-status-badge wpd-status-badge--small <?php echo esc_attr( $snapshot['collection_status'] ?? '' ); ?>"><?php echo esc_html( $snapshot['collection_status'] ?? '' ); ?></span></td>
									<td><?php echo esc_html( $snapshot['collection_duration'] ?? '0s' ); ?></td>
									<td><?php echo esc_html( $snapshot['trigger_type'] ?? 'manual' ); ?></td>
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
			<h2 class="wpd-section-title"><?php esc_html_e( 'Diagnostic Runs', 'wp-performance-doctor' ); ?></h2>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<table class="wpd-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Time', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Findings', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $runs as $run ) : ?>
								<tr>
									<td><?php echo esc_html( $run['timestamp'] ?? '' ); ?></td>
									<td><span class="wpd-status-badge wpd-status-badge--small <?php echo esc_attr( $run['status'] ?? '' ); ?>"><?php echo esc_html( $run['status'] ?? '' ); ?></span></td>
									<td class="wpd-table-cell-numeric"><?php echo esc_html( $run['findings_count'] ?? 0 ); ?></td>
									<td><?php echo esc_html( $run['collection_duration'] ?? '0s' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	<?php endif; ?>

</div>
