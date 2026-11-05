<?php
/**
 * Monitoring view for WP Performance Doctor admin.
 *
 * Displays collected monitoring metrics organized by category with
 * snapshot selection, KPI overview cards, grouped detailed sections,
 * historical trend data, and a functional Collect Now action.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Metric;
?>
<div class="wrap wp-performance-doctor wpd-monitoring">

	<?php
	$enabled              = ! empty( $data['enabled'] );
	$interval             = (int) ( $data['interval'] ?? 0 );
	$last_collection      = $data['last_collection'] ?? null;
	$last_status          = $data['last_status'] ?? null;
	$next_collection      = $data['next_collection'] ?? null;
	$collection_duration  = $data['collection_duration'] ?? null;
	$snapshots            = $data['snapshots'] ?? array();
	$selected_snapshot    = $data['selected_snapshot'] ?? null;
	$selected_snapshot_id = $data['selected_snapshot_id'] ?? null;
	$is_viewing_older     = ! empty( $data['is_viewing_older'] );
	$system               = $data['system'] ?? array();
	$wordpress            = $data['wordpress'] ?? array();
	$database             = $data['database'] ?? array();
	$cache                = $data['cache'] ?? array();
	$php                  = $data['php'] ?? array();
	$frontend             = $data['frontend'] ?? array();
	$has_data             = ! empty( $data['has_data'] );
	$kpi                  = $data['kpi'] ?? array();
	$profiler_stats       = $data['profiler_stats'] ?? array();
	$history              = $data['history'] ?? array();
	$rest_url             = $data['rest_url'] ?? '';
	$collect_nonce        = $data['nonce'] ?? wp_create_nonce( 'wpd_collect' );
	$wp_rest_nonce        = wp_create_nonce( 'wp_rest' );

	$status_class     = $enabled ? 'wpd-status--available' : 'wpd-status--unavailable';
	$status_text      = $enabled ? __( 'Enabled', 'wp-performance-doctor' ) : __( 'Disabled', 'wp-performance-doctor' );
	$snapshot_id      = $selected_snapshot['id'] ?? $selected_snapshot_id ?? null;
	$snapshot_ts      = $selected_snapshot['timestamp'] ?? null;
	$snapshot_trigger = $selected_snapshot['trigger_type'] ?? null;
	$snapshot_status  = $selected_snapshot['collection_status'] ?? null;
	$snapshot_dur     = (float) ( $selected_snapshot['collection_duration'] ?? $collection_duration ?? 0 );

	$last_collection_formatted    = MetricFormatter::format_date( $last_collection );
	$next_collection_formatted    = MetricFormatter::format_date( $next_collection );
	$duration_formatted           = null !== $collection_duration ? MetricFormatter::format_seconds( $collection_duration ) : __( 'N/A', 'wp-performance-doctor' );
	$snapshot_timestamp_formatted = $snapshot_ts ? MetricFormatter::format_date( $snapshot_ts ) : __( 'N/A', 'wp-performance-doctor' );
	$snapshot_duration_formatted  = $snapshot_dur ? MetricFormatter::format_seconds( $snapshot_dur ) : __( 'N/A', 'wp-performance-doctor' );

	$interval_formatted = $interval > 0 ? MetricFormatter::format_seconds( $interval ) : __( 'Not set', 'wp-performance-doctor' );

	$system_count       = count( $system );
	$wordpress_count    = count( $wordpress );
	$database_count     = count( $database );
	$cache_count        = count( $cache );
	$total_metric_count = $system_count + $wordpress_count + $database_count + $cache_count;

	// Collect Now notices (post-collection reload).
	$collect_success = isset( $_GET['wpd_collect_success'] ) ? sanitize_text_field( wp_unslash( $_GET['wpd_collect_success'] ) ) : '';
	$collect_error   = isset( $_GET['wpd_collect_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wpd_collect_error'] ) ) : '';
	?>

	<!-- Page header -->
	<header class="wpd-page-header wpd-page-header--stacked">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php esc_html_e( 'Monitoring', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php
				esc_html_e(
					'Periodic performance monitoring of your WordPress environment, infrastructure, database, caching, and request performance. Data is collected on a schedule, not in real time.',
					'wp-performance-doctor'
				);
				?>
			</p>
		</div>
		<div class="wpd-header-meta">
			<div class="wpd-meta-item">
				<span class="wpd-status-badge <?php echo \esc_attr( $status_class ); ?>">
					<?php echo \esc_html( $status_text ); ?>
				</span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Interval', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo \esc_html( $interval_formatted ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Last Collection', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo \esc_html( $last_collection_formatted ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Next Collection', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo \esc_html( $next_collection_formatted ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo \esc_html( $duration_formatted ); ?></span>
			</div>
		</div>
		<div class="wpd-header-actions">
			<?php if ( $enabled ) : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--small" id="wpd-monitor-collect">
				<span class="wpd-button-spinner"></span>
				<span class="wpd-button-label" id="wpd-monitor-collect-label"><?php \esc_html_e( 'Collect Now', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php endif; ?>
		</div>
		<div id="wpd-monitor-collect-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>
	</header>

	<?php if ( $collect_success ) : ?>
	<div class="wpd-notice wpd-notice--success wpd-notice--inline">
		<p><?php echo \esc_html( $collect_success ); ?></p>
	</div>
	<?php endif; ?>
	<?php if ( $collect_error ) : ?>
	<div class="wpd-notice wpd-notice--error wpd-notice--inline">
		<p><?php echo \esc_html( $collect_error ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( ! $enabled ) : ?>
		<div class="wpd-notice wpd-notice--info wpd-notice--inline is-dismissible">
			<p><?php \esc_html_e( 'Monitoring is disabled. Enable it in Settings to start collecting performance data.', 'wp-performance-doctor' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $has_data ) : ?>
		<div class="wpd-empty-state">
			<p><?php \esc_html_e( 'No monitoring data has been collected yet.', 'wp-performance-doctor' ); ?></p>
			<?php if ( $enabled ) : ?>
				<p><?php \esc_html_e( 'Click Collect Now to gather an initial snapshot, or wait for the next scheduled collection.', 'wp-performance-doctor' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<!-- Snapshot selector -->
		<section class="wpd-section wpd-section--compact">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Snapshot Selection', 'wp-performance-doctor' ); ?></h2>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<div class="wpd-snapshot-selector">
						<label for="wpd-snapshot-select" class="wpd-snapshot-selector__label">
							<?php \esc_html_e( 'View snapshot:', 'wp-performance-doctor' ); ?>
						</label>
						<select id="wpd-snapshot-select" name="snapshot" class="wpd-form-select">
							<?php foreach ( $snapshots as $snap ) : ?>
							<option value="<?php echo \esc_attr( $snap['id'] ?? '' ); ?>"
								<?php
								if ( (int) ( $snap['id'] ?? 0 ) === (int) $snapshot_id ) {
									echo ' selected'; }
								?>
							>
								<?php echo \esc_html( '#' . ( $snap['id'] ?? '' ) ); ?> &mdash;
								<?php echo MetricFormatter::format_date( $snap['timestamp'] ?? '' ); ?>
								&nbsp;(&#8226; <?php echo \esc_html( \ucfirst( $snap['trigger_type'] ?? '' ) ); ?>)
								<?php if ( 'completed' !== ( $snap['collection_status'] ?? '' ) ) : ?>
								&nbsp;<span class="wpd-badge wpd-badge--small wpd-badge--<?php echo \esc_attr( $snap['collection_status'] ?? 'unknown' ); ?>"><?php echo \esc_html( \ucfirst( $snap['collection_status'] ?? '' ) ); ?></span>
								<?php endif; ?>
							</option>
							<?php endforeach; ?>
						</select>
						<span class="wpd-snapshot-selector__meta">
							<?php
							echo \esc_html(
								sprintf(
									/* translators: 1 is the snapshot ID, 2 is the snapshot timestamp, 3 is the duration, 4 is the trigger type. */
									__( 'Snapshot %1$d &mdash; %2$s &mdash; %3$s &middot; %4$s', 'wp-performance-doctor' ),
									$snapshot_id,
									$snapshot_timestamp_formatted,
									$snapshot_duration_formatted,
									\esc_html( \ucfirst( $snapshot_trigger ?? '' ) )
								)
							);
							?>
						</span>
						<?php if ( $is_viewing_older ) : ?>
						<span class="wpd-badge wpd-badge--small wpd-badge--outline wpd-snapshot-selector__older">
							<?php \esc_html_e( 'Viewing older snapshot', 'wp-performance-doctor' ); ?>
						</span>
						<a href="?page=wp-performance-doctor-monitoring" class="wpd-badge wpd-badge--small wpd-badge--filled">
							<?php \esc_html_e( 'Return to latest', 'wp-performance-doctor' ); ?>
						</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>

		<!-- KPI Overview Cards -->
		<section class="wpd-section wpd-section--kpi">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Overview', 'wp-performance-doctor' ); ?></h2>
			</div>
			<div class="wpd-kpi-grid">
				<!-- System Card -->
				<div class="wpd-kpi-card wpd-kpi-card--system">
					<div class="wpd-kpi-card-body">
						<div class="wpd-kpi-card-header">
							<h3 class="wpd-kpi-card-title"><?php \esc_html_e( 'System', 'wp-performance-doctor' ); ?></h3>
							<span class="wpd-kpi-card-count"><?php echo \esc_html( $system_count ); ?></span>
						</div>
						<?php $sys_primary = $kpi['system']['primary'] ?? null; ?>
						<div class="wpd-kpi-metric">
							<span class="wpd-kpi-metric-label"><?php \esc_html_e( 'Memory Usage', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-kpi-metric-value">
								<?php
								if ( $sys_primary && wpd_is_available_metric( $sys_primary ) ) {
									echo MetricFormatter::format( $sys_primary );
								} else {
									echo \esc_html__( 'Not available', 'wp-performance-doctor' );
								}
								?>
							</span>
						</div>
						<div class="wpd-kpi-meta">
							<?php
							$php_version = $kpi['system']['secondary'][0] ?? null;
							if ( $php_version && wpd_is_available_metric( $php_version ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $php_version ) ) . '</span>';
							}
							$opcache = $kpi['system']['secondary'][1] ?? null;
							if ( $opcache && wpd_is_available_metric( $opcache ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $opcache ) ) . '</span>';
							}
							?>
						</div>
						<a href="#wpd-section-system" class="wpd-kpi-card-view-all"><?php \esc_html_e( 'View all system metrics', 'wp-performance-doctor' ); ?></a>
					</div>
				</div>

				<!-- WordPress Card -->
				<div class="wpd-kpi-card wpd-kpi-card--wordpress">
					<div class="wpd-kpi-card-body">
						<div class="wpd-kpi-card-header">
							<h3 class="wpd-kpi-card-title"><?php \esc_html_e( 'WordPress', 'wp-performance-doctor' ); ?></h3>
							<span class="wpd-kpi-card-count"><?php echo \esc_html( $wordpress_count ); ?></span>
						</div>
						<div class="wpd-kpi-metric">
							<span class="wpd-kpi-metric-label"><?php \esc_html_e( 'Version', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-kpi-metric-value">
								<?php
								$wordpress_version = $kpi['wordpress']['primary'] ?? null;
								if ( $wordpress_version && wpd_is_available_metric( $wordpress_version ) ) {
									echo MetricFormatter::format( $wordpress_version );
								} else {
									echo \esc_html__( 'Not available', 'wp-performance-doctor' );
								}
								?>
							</span>
						</div>
						<div class="wpd-kpi-meta">
							<?php
							$theme_metric = $kpi['wordpress']['secondary'][0] ?? null;
							if ( $theme_metric && wpd_is_available_metric( $theme_metric ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $theme_metric ) ) . '</span>';
							}
							$plugins_metric = $kpi['wordpress']['secondary'][1] ?? null;
							if ( $plugins_metric && wpd_is_available_metric( $plugins_metric ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $plugins_metric ) ) . '</span>';
							}
							?>
						</div>
						<a href="#wpd-section-wordpress" class="wpd-kpi-card-view-all"><?php \esc_html_e( 'View all WordPress metrics', 'wp-performance-doctor' ); ?></a>
					</div>
				</div>

				<!-- Database Card -->
				<div class="wpd-kpi-card wpd-kpi-card--database">
					<div class="wpd-kpi-card-body">
						<div class="wpd-kpi-card-header">
							<h3 class="wpd-kpi-card-title"><?php \esc_html_e( 'Database', 'wp-performance-doctor' ); ?></h3>
							<span class="wpd-kpi-card-count"><?php echo \esc_html( $database_count ); ?></span>
						</div>
						<div class="wpd-kpi-metric">
							<span class="wpd-kpi-metric-label"><?php \esc_html_e( 'Database Size', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-kpi-metric-value">
								<?php
								$db_size = $kpi['database']['primary'] ?? null;
								if ( $db_size && wpd_is_available_metric( $db_size ) ) {
									echo MetricFormatter::format( $db_size );
								} else {
									echo \esc_html__( 'Not available', 'wp-performance-doctor' );
								}
								?>
							</span>
						</div>
						<div class="wpd-kpi-meta">
							<?php
							$engine = $kpi['database']['secondary'][0] ?? null;
							if ( $engine && wpd_is_available_metric( $engine ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $engine ) ) . '</span>';
							}
							$tables = $kpi['database']['secondary'][1] ?? null;
							if ( $tables && wpd_is_available_metric( $tables ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $tables ) ) . '</span>';
							}
							?>
						</div>
						<a href="#wpd-section-database" class="wpd-kpi-card-view-all"><?php \esc_html_e( 'View all database metrics', 'wp-performance-doctor' ); ?></a>
					</div>
				</div>

				<!-- Cache Card -->
				<div class="wpd-kpi-card wpd-kpi-card--cache">
					<div class="wpd-kpi-card-body">
						<div class="wpd-kpi-card-header">
							<h3 class="wpd-kpi-card-title"><?php \esc_html_e( 'Cache', 'wp-performance-doctor' ); ?></h3>
							<span class="wpd-kpi-card-count"><?php echo \esc_html( $cache_count ); ?></span>
						</div>
						<div class="wpd-kpi-metric">
							<span class="wpd-kpi-metric-label"><?php \esc_html_e( 'Persistent Object Cache', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-kpi-metric-value">
								<?php
								$persistent = $kpi['cache']['primary'] ?? null;
								if ( $persistent && wpd_is_available_metric( $persistent ) ) {
									echo MetricFormatter::format( $persistent );
								} else {
									echo \esc_html__( 'Not available', 'wp-performance-doctor' );
								}
								?>
							</span>
						</div>
						<div class="wpd-kpi-meta">
							<?php
							$opcache = $kpi['cache']['secondary'][0] ?? null;
							if ( $opcache && wpd_is_available_metric( $opcache ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $opcache ) ) . '</span>';
							}
							$redis = $kpi['cache']['secondary'][1] ?? null;
							if ( $redis && wpd_is_available_metric( $redis ) ) {
								echo '<span class="wpd-kpi-meta-item">' . \esc_html( MetricFormatter::format( $redis ) ) . '</span>';
							}
							?>
						</div>
						<a href="#wpd-section-cache" class="wpd-kpi-card-view-all"><?php \esc_html_e( 'View all cache metrics', 'wp-performance-doctor' ); ?></a>
					</div>
				</div>
			</div>
		</section>

		<!-- Section Navigation -->
		<nav class="wpd-section-nav">
			<a href="#wpd-section-system" class="wpd-section-nav-link"><?php \esc_html_e( 'System &amp; PHP', 'wp-performance-doctor' ); ?></a>
			<a href="#wpd-section-wordpress" class="wpd-section-nav-link"><?php \esc_html_e( 'WordPress', 'wp-performance-doctor' ); ?></a>
			<a href="#wpd-section-database" class="wpd-section-nav-link"><?php \esc_html_e( 'Database', 'wp-performance-doctor' ); ?></a>
			<a href="#wpd-section-cache" class="wpd-section-nav-link"><?php \esc_html_e( 'Cache', 'wp-performance-doctor' ); ?></a>
			<a href="#wpd-section-profiler" class="wpd-section-nav-link"><?php \esc_html_e( 'PHP Request Profiling', 'wp-performance-doctor' ); ?></a>
			<a href="#wpd-section-frontend" class="wpd-section-nav-link"><?php \esc_html_e( 'Frontend Assets', 'wp-performance-doctor' ); ?></a>
		</nav>

		<!-- System & PHP Details -->
		<section class="wpd-section" id="wpd-section-system">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'System &amp; PHP', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'PHP runtime, memory, OPcache, and server configuration.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_system_groups( $system ); ?>
				</div>
			</div>
		</section>

		<!-- WordPress Environment -->
		<section class="wpd-section" id="wpd-section-wordpress">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'WordPress Environment', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Core version, theme, plugins, memory, and environment configuration.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_wordpress_groups( $wordpress ); ?>
				</div>
			</div>
		</section>

		<!-- Database -->
		<section class="wpd-section" id="wpd-section-database">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Database', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Database engine, size, table count, and options.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_database_groups( $database ); ?>
				</div>
			</div>
		</section>

		<!-- Cache -->
		<section class="wpd-section" id="wpd-section-cache">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Cache', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Object cache, OPcache, Redis, Memcached, and page caching status.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_cache_groups( $cache ); ?>
				</div>
			</div>
		</section>

		<!-- PHP Profiler -->
		<section class="wpd-section" id="wpd-section-profiler">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'PHP Request Profiling', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Request-level profiling data for this snapshot collection context.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_profiler_section( $php, $profiler_stats ); ?>
				</div>
			</div>
		</section>

		<!-- Frontend Assets -->
		<section class="wpd-section" id="wpd-section-frontend">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Frontend Assets', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Enqueued scripts, stylesheets, and external assets.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_frontend_section( $frontend ); ?>
				</div>
			</div>
		</section>

		<!-- Snapshot History -->
		<?php if ( ! empty( $snapshots ) ) : ?>
		<section class="wpd-section">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Snapshot History', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Previous monitoring collections for historical comparison.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<table class="wpd-table">
						<thead>
							<tr>
								<th><?php \esc_html_e( 'Snapshot ID', 'wp-performance-doctor' ); ?></th>
								<th><?php \esc_html_e( 'Timestamp', 'wp-performance-doctor' ); ?></th>
								<th><?php \esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></th>
								<th><?php \esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $snapshots as $snapshot ) : ?>
							<tr>
								<td><?php echo \esc_html( $snapshot['id'] ?? '' ); ?></td>
								<td><?php echo MetricFormatter::format_date( $snapshot['timestamp'] ?? '' ); ?></td>
								<td><?php echo \esc_html( \ucfirst( $snapshot['trigger_type'] ?? '' ) ); ?></td>
								<td><span class="wpd-badge wpd-badge--<?php echo \esc_attr( $snapshot['collection_status'] ?? 'completed' ); ?>"><?php echo \esc_html( \ucfirst( $snapshot['collection_status'] ?? '' ) ); ?></span></td>
								<td class="wpd-table-cell-numeric"><?php echo \esc_html( MetricFormatter::format_seconds( $snapshot['collection_duration'] ?? null ) ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
		<?php endif; ?>

		<!-- Historical Trends -->
		<?php if ( ! empty( $history['snapshots'] ) ) : ?>
		<section class="wpd-section">
			<div class="wpd-section-header">
				<h2 class="wpd-section-title"><?php \esc_html_e( 'Historical Trends', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-section-subtitle"><?php \esc_html_e( 'Recorded metric values across recent snapshots.', 'wp-performance-doctor' ); ?></p>
			</div>
			<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php wpd_render_history_charts( $history ); ?>
				</div>
			</div>
		</section>
		<?php endif; ?>

	<?php endif; ?>

</div>

<?php
/**
 * Render grouped system metrics.
 *
 * @param array $system System metric rows.
 * @return void
 */
function wpd_render_system_groups( array $system ): void {
	$groups = array(
		array(
			'title' => __( 'PHP Runtime', 'wp-performance-doctor' ),
			'ids'   => array( 'system.php_version', 'system.php_sapi', 'system.php_os', 'system.os_family', 'system.timezone' ),
		),
		array(
			'title' => __( 'Memory', 'wp-performance-doctor' ),
			'ids'   => array( 'system.memory_usage', 'system.peak_memory_usage', 'system.memory_limit' ),
		),
		array(
			'title' => __( 'Execution Settings', 'wp-performance-doctor' ),
			'ids'   => array( 'system.max_execution_time', 'system.max_input_time', 'system.max_input_vars', 'system.upload_max_filesize', 'system.post_max_size' ),
		),
		array(
			'title' => __( 'OPcache', 'wp-performance-doctor' ),
			'ids'   => array( 'system.opcache_enabled', 'system.opcache_available', 'system.opcache_memory_consumption', 'system.opcache_used_memory' ),
		),
		array(
			'title' => __( 'Server Environment', 'wp-performance-doctor' ),
			'ids'   => array( 'system.server_software', 'system.php_extensions', 'system.load_average' ),
		),
		array(
			'title' => __( 'Disk Capacity', 'wp-performance-doctor' ),
			'ids'   => array( 'system.disk_total_space', 'system.disk_free_space' ),
		),
	);

	foreach ( $groups as $group ) {
		$shown = array();
		foreach ( $group['ids'] as $id ) {
			foreach ( $system as $metric ) {
				if ( ( $metric['metric_identifier'] ?? '' ) === $id ) {
					$shown[] = $metric;
					break;
				}
			}
		}
		if ( empty( $shown ) ) {
			continue;
		}
		?>
		<div class="wpd-detail-section">
			<h3 class="wpd-detail-section-title"><?php echo \esc_html( $group['title'] ); ?></h3>
			<div class="wpd-metric-grid">
				<?php foreach ( $shown as $metric ) : ?>
				<div class="wpd-metric-card">
					<div class="wpd-metric-card-body">
						<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
						<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
						<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
							<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
						</span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

/**
 * Render grouped WordPress metrics.
 *
 * @param array $wordpress WordPress metric rows.
 * @return void
 */
function wpd_render_wordpress_groups( array $wordpress ): void {
	$groups = array(
		array(
			'title' => __( 'Core Information', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.wordpress_version', 'wordpress.wordpress_installation_type', 'wordpress.wordpress_environment_type', 'wordpress.multisite', 'wordpress.blog_count' ),
		),
		array(
			'title' => __( 'Theme', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.active_theme', 'wordpress.active_theme_version', 'wordpress.active_theme_parent' ),
		),
		array(
			'title' => __( 'Plugins', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.plugin_count', 'wordpress.installed_plugin_count', 'wordpress.active_plugins' ),
		),
		array(
			'title' => __( 'Memory Configuration', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.wp_memory_limit', 'wordpress.wp_max_memory_limit' ),
		),
		array(
			'title' => __( 'Cron', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.cron_events_count' ),
		),
		array(
			'title' => __( 'Environment Configuration', 'wp-performance-doctor' ),
			'ids'   => array( 'wordpress.permalink_structure', 'wordpress.timezone', 'wordpress.timezone_offset', 'wordpress.debug_mode', 'wordpress.debug_display', 'wordpress.wpdb_prefix' ),
		),
	);

	foreach ( $groups as $group ) {
		$shown = array();
		foreach ( $group['ids'] as $id ) {
			foreach ( $wordpress as $metric ) {
				if ( ( $metric['metric_identifier'] ?? '' ) === $id ) {
					$shown[] = $metric;
					break;
				}
			}
		}
		if ( empty( $shown ) ) {
			continue;
		}
		?>
		<div class="wpd-detail-section">
			<h3 class="wpd-detail-section-title"><?php echo \esc_html( $group['title'] ); ?></h3>
			<div class="wpd-metric-grid">
				<?php foreach ( $shown as $metric ) : ?>
				<div class="wpd-metric-card">
					<div class="wpd-metric-card-body">
						<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
						<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
						<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
							<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
						</span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

/**
 * Render grouped database metrics.
 *
 * @param array $database Database metric rows.
 * @return void
 */
function wpd_render_database_groups( array $database ): void {
	$groups = array(
		array(
			'title' => __( 'Database Engine', 'wp-performance-doctor' ),
			'ids'   => array( 'database.db_engine_type', 'database.db_version', 'database.db_server', 'database.db_name', 'database.db_charset', 'database.db_collate', 'database.db_prefix' ),
		),
		array(
			'title' => __( 'Size and Tables', 'wp-performance-doctor' ),
			'ids'   => array( 'database.database_size', 'database.table_count', 'database.table_sizes' ),
		),
		array(
			'title' => __( 'Options', 'wp-performance-doctor' ),
			'ids'   => array( 'database.autoloaded_options_size', 'database.autoloaded_options_count', 'database.total_options_count', 'database.transient_count', 'database.revisions_count' ),
		),
	);

	foreach ( $groups as $group ) {
		$shown = array();
		foreach ( $group['ids'] as $id ) {
			foreach ( $database as $metric ) {
				if ( ( $metric['metric_identifier'] ?? '' ) === $id ) {
					$shown[] = $metric;
					break;
				}
			}
		}
		if ( empty( $shown ) ) {
			continue;
		}
		?>
		<div class="wpd-detail-section">
			<h3 class="wpd-detail-section-title"><?php echo \esc_html( $group['title'] ); ?></h3>
			<?php foreach ( $shown as $metric ) : ?>
				<?php if ( 'database.table_sizes' === ( $metric['metric_identifier'] ?? '' ) ) : ?>
			<div class="wpd-expandable" id="wpd-expandable-table-sizes" aria-expanded="false">
				<div class="wpd-expandable-header" role="button" tabindex="0">
					<h3 class="wpd-expandable-title"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></h3>
					<span class="wpd-expandable-toggle">&#9654;</span>
					<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
						<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
					</span>
				</div>
				<div class="wpd-expandable-content">
					<?php echo MetricFormatter::format( $metric ); // Already HTML list. ?>
				</div>
			</div>
			<?php else : ?>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
					<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
					<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
						<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
					</span>
				</div>
			</div>
			<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/**
 * Render grouped cache metrics.
 *
 * @param array $cache Cache metric rows.
 * @return void
 */
function wpd_render_cache_groups( array $cache ): void {
	$groups = array(
		array(
			'title' => __( 'Object Cache', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.object_cache_available', 'cache.object_cache_active', 'cache.object_cache_type' ),
		),
		array(
			'title' => __( 'Persistent Object Cache', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.persistent_object_cache' ),
		),
		array(
			'title' => __( 'Redis', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.redis_available', 'cache.redis_active' ),
		),
		array(
			'title' => __( 'Memcached', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.memcached_available', 'cache.memcached_active' ),
		),
		array(
			'title' => __( 'OPcache', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.opcache_available', 'cache.opcache_enabled' ),
		),
		array(
			'title' => __( 'Page Cache', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.page_cache_detected', 'cache.cache_plugins', 'cache.cache_configured' ),
		),
		array(
			'title' => __( 'Transients', 'wp-performance-doctor' ),
			'ids'   => array( 'cache.transient_storage' ),
		),
	);

	foreach ( $groups as $group ) {
		$shown = array();
		foreach ( $group['ids'] as $id ) {
			foreach ( $cache as $metric ) {
				if ( ( $metric['metric_identifier'] ?? '' ) === $id ) {
					$shown[] = $metric;
					break;
				}
			}
		}
		if ( empty( $shown ) ) {
			continue;
		}
		?>
		<div class="wpd-detail-section">
			<h3 class="wpd-detail-section-title"><?php echo \esc_html( $group['title'] ); ?></h3>
			<div class="wpd-metric-grid">
				<?php foreach ( $shown as $metric ) : ?>
				<div class="wpd-metric-card">
					<div class="wpd-metric-card-body">
						<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
						<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
						<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
							<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
						</span>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

/**
 * Render PHP profiler section.
 *
 * @param array $php Profiler metric rows.
 * @param array $stats Computed profiler stats.
 * @return void
 */
function wpd_render_profiler_section( array $php, array $stats ): void {
	if ( empty( $stats['has_samples'] ) ) :
		?>
		<div class="wpd-empty-state" style="padding: var(--wpd-space-8) var(--wpd-space-6);">
			<p><?php \esc_html_e( 'No valid request samples captured in this snapshot context.', 'wp-performance-doctor' ); ?></p>
			<p class="wpd-small-text"><?php \esc_html_e( 'Cron, REST, admin, and AJAX requests are excluded from profiling. Only frontend request samples are shown.', 'wp-performance-doctor' ); ?></p>
		</div>
		<?php
		return;
	endif;

	?>
	<div class="wpd-detail-section">
		<h3 class="wpd-detail-section-title"><?php \esc_html_e( 'Request Summary', 'wp-performance-doctor' ); ?></h3>
		<div class="wpd-metric-grid">
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Request Count', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo \esc_html( $stats['request_count'] ); ?></span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Average Duration', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo \esc_html( MetricFormatter::format_seconds( $stats['avg_duration'] ?? null ) ); ?></span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Median Duration', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo \esc_html( MetricFormatter::format_seconds( $stats['median_duration'] ?? null ) ); ?></span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'P95 Duration', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo \esc_html( MetricFormatter::format_seconds( $stats['p95_duration'] ?? null ) ); ?></span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Peak Memory', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo $stats['peak_memory'] ? MetricFormatter::format_bytes( $stats['peak_memory'] ) : \esc_html__( 'Not available', 'wp-performance-doctor' ); ?></span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Sample Rate', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value"><?php echo isset( $stats['sample_rate'] ) ? \esc_html( sprintf( '%.0f%%', $stats['sample_rate'] * 100 ) ) : \esc_html__( 'Not available', 'wp-performance-doctor' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="wpd-detail-section">
		<h3 class="wpd-detail-section-title"><?php \esc_html_e( 'Request Type Distribution', 'wp-performance-doctor' ); ?></h3>
		<div class="wpd-badge-list">
			<?php foreach ( $stats['request_types'] as $type => $count ) : ?>
			<span class="wpd-badge wpd-badge--small"><?php echo \esc_html( \ucfirst( $type ) ); ?>: <?php echo \esc_html( $count ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="wpd-detail-section">
		<h3 class="wpd-detail-section-title"><?php \esc_html_e( 'Raw Profiler Metrics', 'wp-performance-doctor' ); ?></h3>
		<div class="wpd-metric-grid">
			<?php foreach ( $php as $metric ) : ?>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
					<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
					<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
						<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
					</span>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render frontend assets section.
 *
 * @param array $frontend Frontend metric rows.
 * @return void
 */
function wpd_render_frontend_section( array $frontend ): void {
	$find = function ( string $id ) use ( $frontend ): ?array {
		foreach ( $frontend as $m ) {
			if ( ( $m['metric_identifier'] ?? '' ) === $id ) {
				return $m;
			}
		}
		return null;
	};

	$styles   = $find( 'frontend.styles_count' );
	$scripts  = $find( 'frontend.scripts_count' );
	$external = $find( 'frontend.external_asset_count' );
	$deferred = $find( 'frontend.deferred_scripts' );
	$async    = $find( 'frontend.async_scripts' );
	$total    = $find( 'frontend.total_assets' );
	$context  = $find( 'frontend.request_type' );

	?>
	<div class="wpd-detail-section">
		<h3 class="wpd-detail-section-title"><?php \esc_html_e( 'Asset Summary', 'wp-performance-doctor' ); ?></h3>
		<div class="wpd-metric-grid">
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'CSS Files', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $styles && wpd_is_available_metric( $styles ) ) {
							echo MetricFormatter::format( $styles );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'JavaScript Files', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $scripts && wpd_is_available_metric( $scripts ) ) {
							echo MetricFormatter::format( $scripts );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'External Assets', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $external && wpd_is_available_metric( $external ) ) {
							echo MetricFormatter::format( $external );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Deferred Scripts', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $deferred && wpd_is_available_metric( $deferred ) ) {
							echo MetricFormatter::format( $deferred );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Async Scripts', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $async && wpd_is_available_metric( $async ) ) {
							echo MetricFormatter::format( $async );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php \esc_html_e( 'Total Assets', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-metric-value">
						<?php
						if ( $total && wpd_is_available_metric( $total ) ) {
							echo MetricFormatter::format( $total );
						} else {
							echo \esc_html__( 'Not measured in this snapshot', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
			</div>
		</div>
		<?php if ( $context && wpd_is_available_metric( $context ) ) : ?>
		<p class="wpd-small-text wpd-small-text--muted">
			<?php
			echo \esc_html(
				sprintf(
					/* translators: %s is the request context in which frontend assets were measured. */
					__( 'Measured during a %s request.', 'wp-performance-doctor' ),
					\esc_html( MetricFormatter::format( $context ) )
				)
			);
			?>
		</p>
		<?php endif; ?>
	</div>

	<div class="wpd-detail-section">
		<h3 class="wpd-detail-section-title"><?php \esc_html_e( 'Raw Frontend Metrics', 'wp-performance-doctor' ); ?></h3>
		<div class="wpd-metric-grid">
			<?php foreach ( $frontend as $metric ) : ?>
			<div class="wpd-metric-card">
				<div class="wpd-metric-card-body">
					<span class="wpd-metric-label"><?php echo MetricFormatter::label( $metric['metric_identifier'] ?? '' ); ?></span>
					<span class="wpd-metric-value"><?php echo MetricFormatter::format( $metric ); ?></span>
					<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $metric['availability_status'] ?? 'available' ); ?>">
						<?php echo \esc_html( MetricFormatter::status_text( $metric['availability_status'] ?? Metric::STATUS_AVAILABLE ) ); ?>
					</span>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Render historical trend charts.
 *
 * @param array $history Computed history data.
 * @return void
 */
function wpd_render_history_charts( array $history ): void {
	$snapshots = $history['snapshots'] ?? array();
	$memory    = $history['memory'] ?? array();
	$database  = $history['database'] ?? array();
	$peak      = $history['peak_memory'] ?? array();

	?>
	<div class="wpd-history-grid">
		<div class="wpd-history-card">
			<h3 class="wpd-history-card-title"><?php \esc_html_e( 'PHP Memory Usage', 'wp-performance-doctor' ); ?></h3>
			<div class="wpd-chart" id="wpd-chart-memory" data-metric="system.memory_usage"></div>
			<div class="wpd-chart-note">
				<?php
				$count = count( $memory );
				echo \esc_html(
					sprintf(
						/* translators: %d is the number of recorded metric values. */
						_n( '%d recorded value', '%d recorded values', $count, 'wp-performance-doctor' ),
						$count
					)
				);
				?>
			</div>
		</div>
		<div class="wpd-history-card">
			<h3 class="wpd-history-card-title"><?php \esc_html_e( 'Database Size', 'wp-performance-doctor' ); ?></h3>
			<div class="wpd-chart" id="wpd-chart-database" data-metric="database.database_size"></div>
			<div class="wpd-chart-note">
				<?php
				$count = count( $database );
				echo \esc_html(
					sprintf(
						/* translators: %d is the number of recorded metric values. */
						_n( '%d recorded value', '%d recorded values', $count, 'wp-performance-doctor' ),
						$count
					)
				);
				?>
			</div>
		</div>
		<div class="wpd-history-card">
			<h3 class="wpd-history-card-title"><?php \esc_html_e( 'Peak Memory Usage', 'wp-performance-doctor' ); ?></h3>
			<div class="wpd-chart" id="wpd-chart-peak" data-metric="system.peak_memory_usage"></div>
			<div class="wpd-chart-note">
				<?php
				$count = count( $peak );
				echo \esc_html(
					sprintf(
						/* translators: %d is the number of recorded metric values. */
						_n( '%d recorded value', '%d recorded values', $count, 'wp-performance-doctor' ),
						$count
					)
				);
				?>
			</div>
		</div>
	</div>

	<script type="application/json" id="wpd-history-data">
	<?php
	echo \esc_html(
		wp_json_encode(
			array(
				'snapshots' => $snapshots,
				'memory'    => $memory,
				'database'  => $database,
				'peak'      => $peak,
			)
		)
	);
	?>
	</script>
	<?php
}

/**
 * Check whether a metric row represents an available value.
 *
 * @param array $metric Metric row.
 * @return bool
 */
function wpd_is_available_metric( array $metric ): bool {
	return ( $metric['availability_status'] ?? '' ) === Metric::STATUS_AVAILABLE;
}
?>