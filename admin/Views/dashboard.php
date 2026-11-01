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
	$kpi_cards                = $data['kpi'] ?? array();
	$health_score             = $data['health_score'] ?? null;
	$score_label              = $data['score_label'] ?? __( 'Insufficient data', 'wp-performance-doctor' );
	$severity_counts          = $data['severity_counts'] ?? array();
	$open_findings            = $data['open_findings'] ?? 0;
	$recent_findings          = $data['recent_findings'] ?? array();
	$priority_recommendations = $data['priority_recommendations'] ?? array();
	$system                   = $data['system'] ?? array();
	$wordpress                = $data['wordpress'] ?? array();
	$database                 = $data['database'] ?? array();
	$cache                    = $data['cache'] ?? array();
	$rest_url                 = $data['rest_url'] ?? '';
	$nonce                    = $data['nonce'] ?? '';
	?>

	<!-- Dashboard header -->
	<header class="wpd-page-header">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php echo ''; // wpd_icon placeholder ?>
				<?php esc_html_e( 'WP Performance Doctor', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php esc_html_e( 'WordPress performance monitoring and diagnostics', 'wp-performance-doctor' ); ?>
			</p>
		</div>
	</header>

	<?php if ( ! $enabled ) : ?>
		<div class="wpd-notice wpd-notice--info wpd-notice--inline is-dismissible">
			<p><?php esc_html_e( 'Monitoring is disabled. Enable it in Settings to start collecting performance data.', 'wp-performance-doctor' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Health score -->
	<section class="wpd-section">
		<div class="wpd-header-title">
			<?php esc_html_e( 'Website Health Score', 'wp-performance-doctor' ); ?>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<h3 class="wpd-panel-title">Health Score</h3>
				<div class="wpd-panel-body">
					<div class="score-circle">
						<svg class="score-circle-svg" viewBox="0 0 140 140">
							<circle class="score-circle-track" cx="70" cy="70" r="58"/>
							<circle class="score-circle-progress" cx="70" cy="70" r="58" stroke-dasharray="365" stroke-dashoffset="<?php echo esc_html( null === $health_score ? 365 : 365 - ( $health_score / 100 * 365 ) ); ?>"/>
						</svg>
						<div class="score-text">
							<span class="score-number"><?php echo $health_score ?? 'N/A'; ?></span>
							<span class="score-label"><?php echo $score_label; ?></span>
						</div>
					</div>
				</div>
			</div>
		</section>
</div>
