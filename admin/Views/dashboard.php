<?php
/**
 * Dashboard view for WP Performance Doctor admin.
 *
 * Overview page: health score, key metrics, findings summary,
 * recent activity, and priority recommendations.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Metric;

?>
<div class="wrap wp-performance-doctor wpd-dashboard">

	<?php
	$enabled                = ! empty( $data['enabled'] );
	$monitoring             = $data['monitoring_status'] ?? array();
	$snapshots                = $data['snapshots'] ?? array();
	$kpi_cards                = $data['kpi_cards'] ?? array();
	$health_score             = $data['health_score'] ?? null;
	$score_label              = $data['score_label'] ?? \__( 'Insufficient data', 'wp-performance-doctor' );
	$score_sufficient         = ! empty( $data['score_sufficient'] );
	$score_coverage           = $data['score_coverage'] ?? 0;
	$severity_counts          = $data['severity_counts'] ?? array(
		'critical' => 0,
		'high'     => 0,
		'medium'   => 0,
		'low'      => 0,
		'info'     => 0,
	);
	$open_findings            = $data['open_findings'] ?? 0;
	$recent_findings          = $data['recent_findings'] ?? array();
	$priority_recommendations = $data['priority_recommendations'] ?? array();
	$latest_diagnostic_run    = $data['latest_diagnostic_run'] ?? null;
	$system                   = $data['system'] ?? array();
	$wordpress                = $data['wordpress'] ?? array();
	$database                 = $data['database'] ?? array();
	$cache                    = $data['cache'] ?? array();
	$rest_url                 = $data['rest_url'] ?? '';
	$nonce                    = $data['nonce'] ?? '';
	$severity_class           = $score_sufficient
		? ( $health_score >= 75 ? 'low' : ( $health_score >= 60 ? 'medium' : ( $health_score >= 40 ? 'high' : 'critical' ) ) )
		: 'info';
	?>

	<!-- Dashboard header -->
	<header class="wpd-page-header">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php echo ''; // wpd_icon placeholder ?>
				<?php \esc_html_e( 'WP Performance Doctor', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php \esc_html_e( 'WordPress performance monitoring and diagnostics', 'wp-performance-doctor' ); ?>
			</p>
		</div>
		<div class="wpd-header-meta">
			<div class="wpd-meta-item">
				<span class="wpd-status-badge wpd-status--<?php echo $enabled ? 'active' : 'disabled'; ?>">
					<?php echo $enabled ? \esc_html( \__( 'Monitoring Active', 'wp-performance-doctor' ) ) : \esc_html( \__( 'Monitoring Disabled', 'wp-performance-doctor' ) ); ?>
				</span>
			</div>
			<?php if ( ! empty( $monitoring['last_collection'] ) ): ?>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Last Collection', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo \esc_html( $monitoring['last_collection'] ); ?></span>
			</div>
			<?php endif; ?>
		</div>
		<div class="wpd-header-actions">
			<?php if ( $enabled ): ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--small" id="wpd-collect">
				<span class="wpd-button-spinner"></span>
				<span class="wpd-button-label" id="wpd-collect-label"><?php \esc_html_e( 'Collect Now', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php endif; ?>
			<button type="button" class="wpd-button wpd-button--primary" id="wpd-run-diagnostics">
				<span class="wpd-button-label" id="wpd-run-diagnostics-label"><?php \esc_html_e( 'Run Diagnostics', 'wp-performance-doctor' ); ?></span>
			</button>
		</div>
		<div id="wpd-collect-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>
		<div id="wpd-diagnostics-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>
	</header>

	<?php if ( ! $enabled ) : ?>
		<div class="wpd-notice wpd-notice--info wpd-notice--inline is-dismissible">
			<p><?php \esc_html_e( 'Monitoring is disabled. Enable it in Settings to start collecting performance data.', 'wp-performance-doctor' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Health Score Section -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Health Score', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-health-score">
					<div class="wpd-health-score-circle">
						<svg class="wpd-health-score-svg" viewBox="0 0 140 140">
							<circle class="wpd-health-score-track" cx="70" cy="70" r="58"/>
							<circle class="wpd-health-score-progress wpd-health-score-progress--<?php echo \esc_attr( $severity_class ); ?>" cx="70" cy="70" r="58" stroke-dasharray="365" stroke-dashoffset="<?php echo \esc_html( null === $health_score ? 365 : 365 - ( $health_score / 100 * 365 ) ); ?>"/>
						</svg>
						<div class="wpd-health-score-text">
							<span class="wpd-health-score-number wpd-health-score-number--<?php echo $score_sufficient ? '' : 'insufficient'; ?>">
								<?php echo $score_sufficient ? \esc_html( $health_score ) : \esc_html( \__( 'N/A', 'wp-performance-doctor' ) ); ?>
							</span>
							<span class="wpd-health-score-caption"><?php echo \esc_html( $score_label ); ?></span>
						</div>
					</div>
					<div class="wpd-health-score-details">
						<h3 class="wpd-health-score-title"><?php \esc_html_e( 'Website Health Score', 'wp-performance-doctor' ); ?></h3>
						<div class="wpd-health-score-status">
							<?php echo \esc_html( $score_label ); ?>
						</div>
						<div class="wpd-health-score-meta">
							<div class="wpd-health-score-meta-item">
								<span class="wpd-meta-item__label"><?php \esc_html_e( 'Diagnostic Coverage', 'wp-performance-doctor' ); ?></span>
								<span class="wpd-meta-value"><?php echo \esc_html( $score_coverage ); ?> <?php \esc_html_e( 'rules', 'wp-performance-doctor' ); ?></span>
							</div>
							<?php if ( $score_sufficient ): ?>
							<div class="wpd-health-score-meta-item">
								<span class="wpd-meta-item__label"><?php \esc_html_e( 'Total Deduction', 'wp-performance-doctor' ); ?></span>
								<span class="wpd-meta-value"><?php echo \esc_html( 100 - (int) $health_score ); ?> <?php \esc_html_e( 'points', 'wp-performance-doctor' ); ?></span>
							</div>
							<?php endif; ?>
						</div>
						<?php if ( ! $score_sufficient ): ?>
						<p class="wpd-small-text"><?php \esc_html_e( 'Run diagnostics to generate a health score based on real findings.', 'wp-performance-doctor' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Finding Summary Cards -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Findings Summary', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-finding-summary-grid">
			<div class="wpd-finding-summary-card">
				<div class="wpd-finding-summary-header">
					<span class="wpd-finding-summary-label"><?php \esc_html_e( 'Critical', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-severity-badge wpd-severity--critical"><?php \esc_html_e( 'Critical', 'wp-performance-doctor' ); ?></span>
				</div>
				<span class="wpd-finding-summary-count wpd-finding-summary-count--critical"><?php echo \esc_html( $severity_counts['critical'] ?? 0 ); ?></span>
				<div class="wpd-finding-summary-footer">
					<a href="admin.php?page=wp-performance-doctor-diagnostics&amp;filter-severity=critical" class="wpd-button wpd-button--ghost wpd-button--small"><?php \esc_html_e( 'View', 'wp-performance-doctor' ); ?></a>
				</div>
			</div>
			<div class="wpd-finding-summary-card">
				<div class="wpd-finding-summary-header">
					<span class="wpd-finding-summary-label"><?php \esc_html_e( 'High', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-severity-badge wpd-severity--high"><?php \esc_html_e( 'High', 'wp-performance-doctor' ); ?></span>
				</div>
				<span class="wpd-finding-summary-count wpd-finding-summary-count--high"><?php echo \esc_html( $severity_counts['high'] ?? 0 ); ?></span>
				<div class="wpd-finding-summary-footer">
					<a href="admin.php?page=wp-performance-doctor-diagnostics&amp;filter-severity=high" class="wpd-button wpd-button--ghost wpd-button--small"><?php \esc_html_e( 'View', 'wp-performance-doctor' ); ?></a>
				</div>
			</div>
			<div class="wpd-finding-summary-card">
				<div class="wpd-finding-summary-header">
					<span class="wpd-finding-summary-label"><?php \esc_html_e( 'Medium', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-severity-badge wpd-severity--medium"><?php \esc_html_e( 'Medium', 'wp-performance-doctor' ); ?></span>
				</div>
				<span class="wpd-finding-summary-count wpd-finding-summary-count--medium"><?php echo \esc_html( $severity_counts['medium'] ?? 0 ); ?></span>
				<div class="wpd-finding-summary-footer">
					<a href="admin.php?page=wp-performance-doctor-diagnostics&amp;filter-severity=medium" class="wpd-button wpd-button--ghost wpd-button--small"><?php \esc_html_e( 'View', 'wp-performance-doctor' ); ?></a>
				</div>
			</div>
			<div class="wpd-finding-summary-card">
				<div class="wpd-finding-summary-header">
					<span class="wpd-finding-summary-label"><?php \esc_html_e( 'Low', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-severity-badge wpd-severity--low"><?php \esc_html_e( 'Low', 'wp-performance-doctor' ); ?></span>
				</div>
				<span class="wpd-finding-summary-count wpd-finding-summary-count--low"><?php echo \esc_html( $severity_counts['low'] ?? 0 ); ?></span>
				<div class="wpd-finding-summary-footer">
					<a href="admin.php?page=wp-performance-doctor-diagnostics&amp;filter-severity=low" class="wpd-button wpd-button--ghost wpd-button--small"><?php \esc_html_e( 'View', 'wp-performance-doctor' ); ?></a>
				</div>
			</div>
		</div>
	</section>

	<!-- System Summary KPI Cards -->
	<?php if ( ! empty( $kpi_cards ) ): ?>
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'System Summary', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-kpi-grid">
			<?php foreach ( $kpi_cards as $key => $card ): ?>
			<div class="wpd-kpi-card wpd-kpi-card--hover">
				<div class="wpd-kpi-card-body">
					<div class="wpd-kpi-card-header">
						<h3 class="wpd-kpi-card-title"><?php echo \esc_html( $card['title'] ); ?></h3>
						<span class="wpd-kpi-card-value wpd-kpi-card-value--neutral"><?php echo \esc_html( $card['value'] ); ?></span>
					</div>
					<p class="wpd-kpi-card-label"><?php echo \esc_html( $card['label'] ); ?></p>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<!-- Recent Activity -->
	<?php if ( $latest_diagnostic_run || ! empty( $recent_findings ) ): ?>
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Recent Activity', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<table class="wpd-table">
					<thead>
						<tr>
							<th><?php \esc_html_e( 'Type', 'wp-performance-doctor' ); ?></th>
							<th><?php \esc_html_e( 'Description', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Severity', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Detected', 'wp-performance-doctor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $latest_diagnostic_run ): ?>
						<tr>
							<td><span class="wpd-badge wpd-badge--outline"><?php \esc_html_e( 'Diagnostic Run', 'wp-performance-doctor' ); ?></span></td>
							<td><?php echo \esc_html( \sprintf( \__( 'Diagnostic run completed with %d findings.', 'wp-performance-doctor' ), (int) ( $latest_diagnostic_run['findings_count'] ?? 0 ) ) ); ?></td>
							<td class="wpd-table-cell-numeric"><?php echo \esc_html( (int) ( $latest_diagnostic_run['health_score'] ?? 0 ) ); ?></td>
							<td class="wpd-table-cell-numeric"><?php echo \esc_html( $latest_diagnostic_run['completed_at'] ?? '' ); ?></td>
						</tr>
						<?php endif; ?>
						<?php foreach ( $recent_findings as $finding ): ?>
						<tr>
							<td><span class="wpd-badge wpd-badge--outline"><?php echo \esc_html( \ucfirst( $finding['category'] ?? 'Finding' ) ); ?></span></td>
							<td><?php echo \esc_html( $finding['title'] ?? $finding['rule_id'] ?? '' ); ?></td>
							<td class="wpd-table-cell-numeric"><span class="wpd-severity-badge wpd-severity--<?php echo \esc_attr( $finding['severity'] ?? 'info' ); ?>"><?php echo \esc_html( \ucfirst( $finding['severity'] ?? 'info' ) ); ?></span></td>
							<td class="wpd-table-cell-numeric"><?php echo \esc_html( $finding['last_detected'] ?? '' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<!-- Priority Recommendations -->
	<?php if ( ! empty( $priority_recommendations ) ): ?>
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Priority Recommendations', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php foreach ( array_slice( $priority_recommendations, 0, 5 ) as $rec ): ?>
				<div class="wpd-card wpd-card--compact" style="margin-bottom: var(--wpd-space-4);">
					<div class="wpd-card-body">
						<div class="wpd-card-header">
							<h3 class="wpd-card-title"><?php echo \esc_html( $rec['title'] ?? '' ); ?></h3>
							<span class="wpd-severity-badge wpd-severity--<?php echo \esc_attr( $rec['severity'] ?? 'info' ); ?>"><?php echo \esc_html( \ucfirst( $rec['severity'] ?? 'info' ) ); ?></span>
						</div>
						<p class="wpd-body-text"><?php echo \esc_html( $rec['problem'] ?? '' ); ?></p>
						<div class="wpd-card-footer">
							<span class="wpd-meta-text"><?php echo \esc_html( $rec['difficulty'] ?? 'medium' ); ?> difficulty &middot; <?php echo \esc_html( $rec['risk'] ?? 'low' ); ?> risk</span>
							<a href="admin.php?page=wp-performance-doctor-diagnostics" class="wpd-button wpd-button--ghost wpd-button--small"><?php \esc_html_e( 'View Details', 'wp-performance-doctor' ); ?></a>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

</div>