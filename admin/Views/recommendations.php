<?php
/**
 * Recommendations view for WP Performance Doctor admin.
 *
 * Displays prioritized recommendations generated from diagnostic findings,
 * with summary counts, priority groups, recommendation cards, and detail panels.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Rules\Severity;

$recommendations       = $data['recommendations'] ?? array();
$recommendations_total = (int) ( $data['recommendations_total'] ?? 0 );
$summary               = $data['summary'] ?? array(
	'total'                => 0,
	'high_priority'        => 0,
	'medium_priority'      => 0,
	'low_priority'         => 0,
	'hosting_access'       => 0,
	'developer_assistance' => 0,
	'wordpress_only'       => 0,
);

$total_high   = (int) ( $summary['high_priority'] ?? 0 );
$total_medium = (int) ( $summary['medium_priority'] ?? 0 );
$total_low    = (int) ( $summary['low_priority'] ?? 0 );

$severity_badge_classes = array(
	'critical' => 'wpd-badge--critical',
	'high'     => 'wpd-badge--high',
	'medium'   => 'wpd-badge--medium',
	'low'      => 'wpd-badge--low',
	'info'     => 'wpd-badge--info',
);

// Group recommendations by priority.
$priority_groups = array(
	'fix_first'   => array(
		'title' => __( 'Fix First', 'wp-performance-doctor' ),
		'desc'  => __( 'Issues with the greatest potential impact and sufficient confidence. Address these before other recommendations.', 'wp-performance-doctor' ),
		'class' => 'wpd-priority-group--critical',
		'items' => array(),
	),
	'recommended' => array(
		'title' => __( 'Recommended Improvements', 'wp-performance-doctor' ),
		'desc'  => __( 'Useful improvements that deserve attention but are not urgent.', 'wp-performance-doctor' ),
		'class' => 'wpd-priority-group--high',
		'items' => array(),
	),
	'optional'    => array(
		'title' => __( 'Optional Improvements', 'wp-performance-doctor' ),
		'desc'  => __( 'Low-risk or lower-impact optimization opportunities.', 'wp-performance-doctor' ),
		'class' => 'wpd-priority-group--medium',
		'items' => array(),
	),
);

foreach ( $recommendations as $rec ) {
	$sev = $rec['severity'] ?? 'info';
	if ( in_array( $sev, array( 'critical', 'high' ), true ) ) {
		$priority_groups['fix_first']['items'][] = $rec;
	} elseif ( 'medium' === $sev ) {
		$priority_groups['recommended']['items'][] = $rec;
	} else {
		$priority_groups['optional']['items'][] = $rec;
	}
}

$strings = $data['recommendations_strings'] ?? array();

?>
<div class="wrap wp-performance-doctor wpd-recommendations">

	<?php
	$recommendations_notice = filter_input( INPUT_GET, 'wpd_rec_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( false === $recommendations_notice || null === $recommendations_notice ) {
		$recommendations_notice = '';
	}
	?>

	<!-- Page header -->
	<header class="wpd-page-header wpd-page-header--stacked">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php esc_html_e( 'Recommendations', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php
				esc_html_e(
					'Prioritized remediation guidance based on your diagnostic findings. Each recommendation links to its source finding with evidence, impact, and step-by-step instructions.',
					'wp-performance-doctor'
				);
				?>
			</p>
		</div>
		<div class="wpd-header-meta">
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Total Recommendations', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large"><?php echo esc_html( $recommendations_total ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Fix First', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large wpd-meta-value--critical"><?php echo esc_html( $total_high ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Recommended', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large wpd-meta-value--medium"><?php echo esc_html( $total_medium ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Optional', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large wpd-meta-value--low"><?php echo esc_html( $total_low ); ?></span>
			</div>
		</div>
		<div class="wpd-header-actions">
			<a href="?page=wp-performance-doctor-diagnostics" class="wpd-button wpd-button--primary wpd-button--medium"><?php esc_html_e( 'View Diagnostics', 'wp-performance-doctor' ); ?></a>
		</div>
		<?php if ( $recommendations_notice ) : ?>
		<div class="wpd-notice wpd-notice--success wpd-notice--inline is-dismissible" style="margin-top: var(--wpd-space-4);">
			<p><?php echo esc_html( $recommendations_notice ); ?></p>
		</div>
		<?php endif; ?>
	</header>

	<?php if ( 0 === $recommendations_total ) : ?>
	<div class="wpd-empty-state wpd-empty-state--centered">
		<p class="wpd-empty-state-title"><?php esc_html_e( 'No recommendations yet.', 'wp-performance-doctor' ); ?></p>
		<p><?php esc_html_e( 'Run diagnostics to analyze your site and generate prioritized recommendations.', 'wp-performance-doctor' ); ?></p>
		<a href="?page=wp-performance-doctor-diagnostics" class="wpd-button wpd-button--primary"><?php esc_html_e( 'Run Diagnostics', 'wp-performance-doctor' ); ?></a>
	</div>
	<?php else : ?>

	<!-- Recommendation Summary -->
	<section class="wpd-section wpd-section--compact">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Recommendation Summary', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-recommendation-summary-grid">
					<div class="wpd-summary-card wpd-summary-card--total">
						<span class="wpd-summary-value"><?php echo esc_html( $recommendations_total ); ?></span>
						<span class="wpd-summary-label"><?php esc_html_e( 'Total Recommendations', 'wp-performance-doctor' ); ?></span>
					</div>
					<div class="wpd-summary-card wpd-summary-card--hosting">
						<span class="wpd-summary-value"><?php echo esc_html( $summary['hosting_access'] ); ?></span>
						<span class="wpd-summary-label"><?php esc_html_e( 'Require Hosting Access', 'wp-performance-doctor' ); ?></span>
					</div>
					<div class="wpd-summary-card wpd-summary-card--developer">
						<span class="wpd-summary-value"><?php echo esc_html( $summary['developer_assistance'] ); ?></span>
						<span class="wpd-summary-label"><?php esc_html_e( 'Require Developer Assistance', 'wp-performance-doctor' ); ?></span>
					</div>
					<div class="wpd-summary-card wpd-summary-card--wordpress">
						<span class="wpd-summary-value"><?php echo esc_html( $summary['wordpress_only'] ); ?></span>
						<span class="wpd-summary-label"><?php esc_html_e( 'Can Fix in WordPress', 'wp-performance-doctor' ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Priority Groups -->
		<?php foreach ( $priority_groups as $group_key => $group ) : ?>
			<?php
			if ( empty( $group['items'] ) ) :
				continue;
endif;
			?>
	<section class="wpd-section wpd-section--compact">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title">
				<?php echo esc_html( $group['title'] ); ?>
				<span class="wpd-section-count"><?php echo esc_html( count( $group['items'] ) ); ?></span>
			</h2>
			<p class="wpd-section-subtitle"><?php echo esc_html( $group['desc'] ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-recommendation-grid">
					<?php foreach ( $group['items'] as $rec ) : ?>
					<div class="wpd-recommendation-card wpd-recommendation-card--<?php echo esc_attr( $rec['severity'] ?? 'info' ); ?>" data-rec-id="<?php echo esc_attr( $rec['id'] ?? '' ); ?>">
						<div class="wpd-recommendation-card-header">
							<h3 class="wpd-recommendation-title"><?php echo esc_html( $rec['title'] ?? $rec['rule_id'] ?? '' ); ?></h3>
							<span class="wpd-badge <?php echo esc_attr( $severity_badge_classes[ $rec['severity'] ?? 'info' ] ?? 'wpd-badge--info' ); ?>">
								<?php echo esc_html( ucfirst( $rec['severity'] ?? 'info' ) ); ?>
							</span>
						</div>
						<div class="wpd-recommendation-card-body">
							<p class="wpd-recommendation-problem"><?php echo esc_html( $rec['problem'] ?? '' ); ?></p>
							<div class="wpd-recommendation-meta">
								<span class="wpd-recommendation-meta-item">
									<span class="wpd-meta-label"><?php esc_html_e( 'Confidence', 'wp-performance-doctor' ); ?>:</span>
									<span class="wpd-meta-value"><?php echo esc_html( ucfirst( $rec['confidence'] ?? 'low' ) ); ?></span>
								</span>
								<span class="wpd-recommendation-meta-item">
									<span class="wpd-meta-label"><?php esc_html_e( 'Difficulty', 'wp-performance-doctor' ); ?>:</span>
									<span class="wpd-meta-value"><?php echo esc_html( ucfirst( $rec['difficulty'] ?? 'medium' ) ); ?></span>
								</span>
								<span class="wpd-recommendation-meta-item">
									<span class="wpd-meta-label"><?php esc_html_e( 'Risk', 'wp-performance-doctor' ); ?>:</span>
									<span class="wpd-meta-value"><?php echo esc_html( ucfirst( $rec['risk'] ?? 'low' ) ); ?></span>
								</span>
								<span class="wpd-recommendation-meta-item">
									<span class="wpd-meta-label"><?php esc_html_e( 'Category', 'wp-performance-doctor' ); ?>:</span>
									<span class="wpd-meta-value"><?php echo esc_html( $rec['category'] ?? '' ); ?></span>
								</span>
							</div>
						</div>
						<div class="wpd-recommendation-card-actions">
							<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-rec-detail-btn" data-rec-id="<?php echo esc_attr( $rec['id'] ?? '' ); ?>"><?php esc_html_e( 'View Details', 'wp-performance-doctor' ); ?></button>
							<a href="?page=wp-performance-doctor-diagnostics#finding-<?php echo esc_attr( $rec['related_finding_id'] ?? '' ); ?>" class="wpd-button wpd-button--small wpd-button--outline"><?php esc_html_e( 'View Finding', 'wp-performance-doctor' ); ?></a>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>
	<?php endforeach; ?>

	<?php endif; ?>

	<!-- Recommendation Detail Panel -->
	<div class="wpd-rec-detail-panel" id="wpd-rec-detail-panel" role="dialog" aria-modal="true" aria-labelledby="wpd-rec-detail-title" style="display: none;">
		<div class="wpd-rec-detail-backdrop" id="wpd-rec-detail-backdrop"></div>
		<div class="wpd-rec-detail-content">
			<div class="wpd-rec-detail-header">
				<h2 class="wpd-rec-detail-title" id="wpd-rec-detail-title"></h2>
				<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-rec-detail-close" id="wpd-rec-detail-close"><?php esc_html_e( 'Close', 'wp-performance-doctor' ); ?></button>
			</div>
			<div class="wpd-rec-detail-body" id="wpd-rec-detail-body">
				<!-- Populated via AJAX -->
			</div>
		</div>
	</div>

</div>

<script id="wpd-recommendations-config" type="application/json">
<?php
echo esc_html(
	wp_json_encode(
		array(
			'recommendationsRestUrl' => esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/recommendations' ) ),
			'nonce'                  => wp_create_nonce( 'wpd_collect' ),
			'wpRestNonce'            => wp_create_nonce( 'wp_rest' ),
			'strings'                => array(
				'viewFinding'  => __( 'View finding', 'wp-performance-doctor' ),
				'viewEvidence' => __( 'View evidence', 'wp-performance-doctor' ),
				'viewDetails'  => __( 'View details', 'wp-performance-doctor' ),
				'close'        => __( 'Close', 'wp-performance-doctor' ),
				'loading'      => __( 'Loading…', 'wp-performance-doctor' ),
				'noEvidence'   => __( 'No evidence available.', 'wp-performance-doctor' ),
				'noSteps'      => __( 'No remediation steps available.', 'wp-performance-doctor' ),
			),
		)
	)
);
?>
</script>
