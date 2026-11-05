<?php
/**
 * Diagnostics view for WP Performance Doctor admin.
 *
 * Displays diagnostic findings, health score, severity summary, run history,
 * and provides Run Diagnostics action with finding lifecycle management.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Pages\DiagnosticsPage;
use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Rules\RuleStatus;
use WPPerformanceDoctor\Rules\Severity;

$is_running           = ! empty( $data['is_running'] );
$diagnostic_available = ! empty( $data['diagnostic_service_available'] );
$summary              = $data['summary'] ?? array();
$findings             = $data['findings'] ?? array();
$findings_total       = (int) ( $data['findings_total'] ?? 0 );
$findings_page        = (int) ( $data['findings_page'] ?? 1 );
$findings_per_page    = (int) ( $data['findings_per_page'] ?? 20 );
$runs                 = $data['runs'] ?? array();
$active_filters       = $data['active_filters'] ?? array();

$open_findings   = (int) ( $summary['open_findings'] ?? 0 );
$findings_detected = (int) ( $summary['findings_detected'] ?? 0 );
$health_score    = $summary['health_score'] ?? null;
$rules_evaluated = (int) ( $summary['rules_evaluated'] ?? 0 );
$last_run_at     = $summary['last_run_at'] ?? null;
$last_run_status = $summary['last_run_status'] ?? null;
$severity_counts = $summary['severity_counts'] ?? array(
	'critical' => 0,
	'high'     => 0,
	'medium'   => 0,
	'low'      => 0,
	'info'     => 0,
);

$rest_url      = esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/run' ) );
$findings_rest = esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/findings' ) );
$collect_nonce = wp_create_nonce( 'wpd_collect' );
$wp_rest_nonce = wp_create_nonce( 'wp_rest' );

$total_pages = $findings_per_page > 0 ? max( 1, ceil( $findings_total / $findings_per_page ) ) : 1;

$health_score_class = 'wpd-health-score--neutral';
if ( null !== $health_score ) {
	if ( $health_score >= 80 ) {
		$health_score_class = 'wpd-health-score--good';
	} elseif ( $health_score >= 50 ) {
		$health_score_class = 'wpd-health-score--fair';
	} else {
		$health_score_class = 'wpd-health-score--poor';
	}
}

$severity_badge_classes = array(
	'critical' => 'wpd-badge--critical',
	'high'     => 'wpd-badge--high',
	'medium'   => 'wpd-badge--medium',
	'low'      => 'wpd-badge--low',
	'info'     => 'wpd-badge--info',
);

?>
<div class="wrap wp-performance-doctor wpd-diagnostics">

	<?php
	$diagnostics_notice = filter_input( INPUT_GET, 'wpd_diag_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( false === $diagnostics_notice || null === $diagnostics_notice ) {
		$diagnostics_notice = '';
	}
	$diagnostics_error = filter_input( INPUT_GET, 'wpd_diag_error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( false === $diagnostics_error || null === $diagnostics_error ) {
		$diagnostics_error = '';
	}
	?>

	<!-- Page header -->
	<header class="wpd-page-header wpd-page-header--stacked">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php esc_html_e( 'Diagnostics', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php
				esc_html_e(
					'Run diagnostic rules against your latest monitoring snapshot to identify performance, security, and configuration issues. Findings are tracked with lifecycle states so you can acknowledge, ignore, or resolve them.',
					'wp-performance-doctor'
				);
				?>
			</p>
		</div>
		<div class="wpd-header-meta">
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Open Findings', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large"><?php echo esc_html( $open_findings ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Rules Evaluated', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value wpd-meta-value--large"><?php echo esc_html( $rules_evaluated ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Findings Detected', 'wp-performance-doctor' ); ?><br><span class="wpd-text--small wpd-text--muted"><?php esc_html_e( 'this run', 'wp-performance-doctor' ); ?></span></span>
				<span class="wpd-meta-value wpd-meta-value--large"><?php echo esc_html( $findings_detected ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Last Run', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo esc_html( $last_run_at ? date_i18n( __( 'M j, Y g:i a', 'wp-performance-doctor' ), strtotime( $last_run_at ) ) : __( 'Never', 'wp-performance-doctor' ) ); ?></span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value"><?php echo esc_html( $last_run_status ? ucfirst( $last_run_status ) : __( 'Never', 'wp-performance-doctor' ) ); ?></span>
			</div>
		</div>
		<div class="wpd-header-actions">
			<?php if ( $diagnostic_available && ! $is_running ) : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-diagnostics">
				<span class="wpd-button-spinner"></span>
				<span class="wpd-button-label" id="wpd-run-diagnostics-label"><?php esc_html_e( 'Run Diagnostics', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php elseif ( $is_running ) : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium wpd-button--disabled" disabled>
				<span class="wpd-button-spinner wpd-button-spinner--spin"></span>
				<span class="wpd-button-label"><?php esc_html_e( 'Running diagnostics…', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php else : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium wpd-button--disabled" disabled title="<?php esc_attr_e( 'Diagnostic service is not available.', 'wp-performance-doctor' ); ?>">
				<span class="wpd-button-label"><?php esc_html_e( 'Run Diagnostics', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php endif; ?>
		</div>
		<div id="wpd-diagnostics-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>
	</header>

	<?php if ( $diagnostics_notice ) : ?>
	<div class="wpd-notice wpd-notice--success wpd-notice--inline is-dismissible">
		<p><?php echo esc_html( $diagnostics_notice ); ?></p>
	</div>
	<?php endif; ?>
	<?php if ( $diagnostics_error ) : ?>
	<div class="wpd-notice wpd-notice--error wpd-notice--inline is-dismissible">
		<p><?php echo esc_html( $diagnostics_error ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( ! $diagnostic_available ) : ?>
	<div class="wpd-notice wpd-notice--error wpd-notice--inline is-dismissible">
		<p><?php esc_html_e( 'Diagnostic service is not available on this installation.', 'wp-performance-doctor' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- Health Score Card -->
	<section class="wpd-section wpd-section--compact">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Website Health Score', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body wpd-health-score-card">
				<div class="wpd-health-score-display">
					<div class="wpd-health-score-circle <?php echo esc_attr( $health_score_class ); ?>">
						<span class="wpd-health-score-value">
							<?php echo null !== $health_score ? esc_html( $health_score ) : esc_html__( 'N/A', 'wp-performance-doctor' ); ?>
						</span>
						<span class="wpd-health-score-label"><?php esc_html_e( '/ 100', 'wp-performance-doctor' ); ?></span>
					</div>
					<div class="wpd-health-score-meta">
						<p class="wpd-health-score-subtitle"><?php esc_html_e( 'Based on the latest diagnostic run.', 'wp-performance-doctor' ); ?></p>
						<?php if ( null !== $health_score ) : ?>
						<p class="wpd-health-score-description">
							<?php
							if ( $health_score >= 80 ) {
								esc_html_e( 'Good. Few issues detected.', 'wp-performance-doctor' );
							} elseif ( $health_score >= 50 ) {
								esc_html_e( 'Fair. Some issues need attention.', 'wp-performance-doctor' );
							} else {
								esc_html_e( 'Poor. Multiple issues need attention.', 'wp-performance-doctor' );
							}
							?>
						</p>
						<?php else : ?>
						<p class="wpd-health-score-description wpd-text--muted"><?php esc_html_e( 'Run diagnostics to calculate your health score.', 'wp-performance-doctor' ); ?></p>
						<?php endif; ?>
						<div class="wpd-health-score-details">
							<span class="wpd-health-score-detail"><?php echo esc_html( $rules_evaluated ); ?> <?php esc_html_e( 'rules evaluated', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-health-score-divider">|</span>
							<span class="wpd-health-score-detail"><?php echo esc_html( $open_findings ); ?> <?php esc_html_e( 'open findings', 'wp-performance-doctor' ); ?></span>
						</div>
						<?php
						$score_breakdown = $summary['score_breakdown'] ?? array();
						if ( ! empty( $score_breakdown ) ) :
							?>
						<div class="wpd-health-score-breakdown" style="margin-top: var(--wpd-space-4);">
							<h4 class="wpd-detail-section-title" style="font-size: var(--wpd-font-size-sm); margin-bottom: var(--wpd-space-2);"><?php esc_html_e( 'Score Breakdown', 'wp-performance-doctor' ); ?></h4>
							<div class="wpd-detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: var(--wpd-space-3);">
								<?php foreach ( $score_breakdown as $sev => $info ) : ?>
								<div class="wpd-detail-field">
									<span class="wpd-detail-field-label"><?php echo esc_html( DiagnosticsPage::severity_label( $sev ) ); ?></span>
									<span class="wpd-detail-field-value"><?php echo esc_html( $info['count'] ); ?> <?php esc_html_e( 'findings', 'wp-performance-doctor' ); ?></span>
									<span class="wpd-detail-field-label wpd-text--muted" style="font-size: var(--wpd-font-size-xs);"><?php echo esc_html( $info['deduction'] ); ?> <?php esc_html_e( 'points', 'wp-performance-doctor' ); ?></span>
								</div>
								<?php endforeach; ?>
								<div class="wpd-detail-field wpd-detail-field--total">
									<span class="wpd-detail-field-label"><?php esc_html_e( 'Total Deduction', 'wp-performance-doctor' ); ?></span>
									<span class="wpd-detail-field-value"><?php echo esc_html( $summary['total_deduction'] ?? 0 ); ?> <?php esc_html_e( 'points', 'wp-performance-doctor' ); ?></span>
								</div>
							</div>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Severity Summary -->
	<section class="wpd-section wpd-section--compact">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Findings by Severity', 'wp-performance-doctor' ); ?></h2>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-severity-grid">
					<?php
					$severity_order  = array( 'critical', 'high', 'medium', 'low', 'info' );
					$severity_labels = array(
						'critical' => __( 'Critical', 'wp-performance-doctor' ),
						'high'     => __( 'High', 'wp-performance-doctor' ),
						'medium'   => __( 'Medium', 'wp-performance-doctor' ),
						'low'      => __( 'Low', 'wp-performance-doctor' ),
						'info'     => __( 'Info', 'wp-performance-doctor' ),
					);
					foreach ( $severity_order as $sev ) :
						$count       = (int) ( $severity_counts[ $sev ] ?? 0 );
						$badge_class = $severity_badge_classes[ $sev ] ?? 'wpd-badge--info';
						$url         = esc_url(
							add_query_arg(
								array(
									'severity' => $sev,
									'status'   => 'open',
								),
								remove_query_arg( array( 'page' ) )
							)
						);
						?>
						<div class="wpd-severity-card <?php echo esc_attr( $badge_class ); ?>">
							<a href="<?php echo esc_url( $url ); ?>" class="wpd-severity-card-link">
								<span class="wpd-severity-count"><?php echo esc_html( $count ); ?></span>
								<span class="wpd-severity-label"><?php echo esc_html( $severity_labels[ $sev ] ); ?></span>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- Findings List -->
	<section class="wpd-section">
		<div class="wpd-section-header wpd-section-header--with-toolbar">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Diagnostic Findings', 'wp-performance-doctor' ); ?></h2>
			<div class="wpd-findings-toolbar">
				<div class="wpd-findings-filter wpd-findings-filter--search">
					<label class="wpd-screen-reader-text"><?php esc_html_e( 'Search findings', 'wp-performance-doctor' ); ?></label>
					<input type="text" id="wpd-filter-search" name="search" class="wpd-form-input" placeholder="<?php esc_attr_e( 'Search findings…', 'wp-performance-doctor' ); ?>" value="<?php echo esc_attr( $active_filters['search'] ?? '' ); ?>">
				</div>
				<div class="wpd-findings-filter wpd-findings-filter--select">
					<label class="wpd-screen-reader-text"><?php esc_html_e( 'Filter by severity', 'wp-performance-doctor' ); ?></label>
					<select id="wpd-filter-severity" name="severity" class="wpd-form-select">
						<option value=""><?php esc_html_e( 'All Severities', 'wp-performance-doctor' ); ?></option>
						<?php
						foreach ( array( 'critical', 'high', 'medium', 'low', 'info' ) as $sev ) :
							$selected = ( isset( $active_filters['severity'] ) && $active_filters['severity'] === $sev ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $sev ) . '"' . esc_attr( $selected ) . '>' . esc_html( DiagnosticsPage::severity_label( $sev ) ) . '</option>';
						endforeach;
						?>
					</select>
				</div>
				<div class="wpd-findings-filter wpd-findings-filter--select">
					<label class="wpd-screen-reader-text"><?php esc_html_e( 'Filter by status', 'wp-performance-doctor' ); ?></label>
					<select id="wpd-filter-status" name="status" class="wpd-form-select">
						<option value=""><?php esc_html_e( 'All Statuses', 'wp-performance-doctor' ); ?></option>
						<?php
						$statuses = array(
							'open'         => __( 'Open', 'wp-performance-doctor' ),
							'acknowledged' => __( 'Acknowledged', 'wp-performance-doctor' ),
							'ignored'      => __( 'Ignored', 'wp-performance-doctor' ),
							'resolved'     => __( 'Resolved', 'wp-performance-doctor' ),
						);
						foreach ( $statuses as $key => $label ) :
							$selected = ( isset( $active_filters['status'] ) && $active_filters['status'] === $key ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $key ) . '"' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
						endforeach;
						?>
					</select>
				</div>
				<div class="wpd-findings-filter wpd-findings-filter--actions">
					<?php
					$has_filters = ! empty( $active_filters );
					$clear_url   = esc_url( remove_query_arg( array( 'severity', 'category', 'status', 'search', 'page' ) ) );
					?>
					<?php if ( $has_filters ) : ?>
					<a href="<?php echo esc_url( $clear_url ); ?>" class="wpd-button wpd-button--small wpd-button--outline"><?php esc_html_e( 'Clear Filters', 'wp-performance-doctor' ); ?></a>
					<?php endif; ?>
					<span class="wpd-findings-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d is the number of diagnostic findings. */
								_n( '%d finding', '%d findings', $findings_total, 'wp-performance-doctor' ),
								$findings_total
							)
						);
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php if ( 0 === $findings_total ) : ?>
				<div class="wpd-empty-state wpd-empty-state--centered">
					<p class="wpd-empty-state-title">
						<?php
						if ( $is_running ) {
							esc_html_e( 'Diagnostics are running. Refresh when complete.', 'wp-performance-doctor' );
						} elseif ( 0 === $rules_evaluated ) {
							esc_html_e( 'No diagnostic data has been collected yet.', 'wp-performance-doctor' );
						} else {
							esc_html_e( 'No findings match your filters.', 'wp-performance-doctor' );
						}
						?>
					</p>
					<?php if ( 0 === $rules_evaluated && $diagnostic_available && ! $is_running ) : ?>
					<p><?php esc_html_e( 'Click "Run Diagnostics" to analyze your site and generate findings.', 'wp-performance-doctor' ); ?></p>
					<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-diagnostics-empty">
						<span class="wpd-button-label"><?php esc_html_e( 'Run Diagnostics', 'wp-performance-doctor' ); ?></span>
					</button>
					<?php endif; ?>
				</div>
				<?php else : ?>
				<div class="wpd-findings-table-wrap">
					<table class="wpd-table wpd-table--findings" id="wpd-findings-table">
						<thead>
							<tr>
								<th class="wpd-table-cell--finding"><?php esc_html_e( 'Finding', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--severity"><?php esc_html_e( 'Severity', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--category"><?php esc_html_e( 'Category', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--status"><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--confidence"><?php esc_html_e( 'Confidence', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--first-detected"><?php esc_html_e( 'First Detected', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell--actions"><?php esc_html_e( 'Actions', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $findings as $finding ) : ?>
							<tr class="wpd-finding-row" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>" data-fingerprint="<?php echo esc_attr( $finding['fingerprint'] ?? '' ); ?>">
								<td class="wpd-table-cell--finding">
									<div class="wpd-finding-summary">
										<a href="#" class="wpd-finding-link" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>"><?php echo esc_html( $finding['title'] ?? $finding['rule_id'] ?? '' ); ?></a>
										<span class="wpd-finding-description wpd-text--small wpd-text--muted"><?php echo esc_html( $finding['description'] ?? '' ); ?></span>
									</div>
								</td>
								<td class="wpd-table-cell--severity">
									<span class="wpd-badge <?php echo esc_attr( $severity_badge_classes[ $finding['severity'] ?? 'info' ] ?? 'wpd-badge--info' ); ?>">
										<?php echo esc_html( DiagnosticsPage::severity_label( $finding['severity'] ?? 'info' ) ); ?>
									</span>
								</td>
								<td class="wpd-table-cell--category wpd-text--small wpd-text--muted">
									<?php echo esc_html( $finding['category'] ?? '' ); ?>
								</td>
								<td class="wpd-table-cell--status">
									<span class="wpd-badge wpd-badge--outline <?php echo esc_attr( DiagnosticsPage::status_class( $finding['status'] ?? 'open' ) ); ?>">
										<?php echo esc_html( DiagnosticsPage::status_label( $finding['status'] ?? 'open' ) ); ?>
									</span>
								</td>
								<td class="wpd-table-cell--confidence wpd-text--small wpd-text--muted">
									<?php echo esc_html( ucfirst( $finding['confidence'] ?? 'low' ) ); ?>
								</td>
								<td class="wpd-table-cell--first-detected wpd-text--small wpd-text--muted">
									<?php echo esc_html( $finding['first_detected'] ? date_i18n( __( 'M j, Y', 'wp-performance-doctor' ), strtotime( $finding['first_detected'] ) ) : __( 'N/A', 'wp-performance-doctor' ) ); ?>
								</td>
								<td class="wpd-table-cell--actions">
									<div class="wpd-finding-actions">
										<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-button--details" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>"><?php esc_html_e( 'Details', 'wp-performance-doctor' ); ?></button>
										<?php if ( in_array( $finding['status'] ?? 'open', array( 'open', 'resolved' ), true ) ) : ?>
										<button type="button" class="wpd-button wpd-button--small wpd-button--ack" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>" data-nonce="<?php echo esc_attr( $wp_rest_nonce ); ?>"><?php esc_html_e( 'Acknowledge', 'wp-performance-doctor' ); ?></button>
										<?php endif; ?>
										<?php if ( 'open' === ( $finding['status'] ?? 'open' ) ) : ?>
										<button type="button" class="wpd-button wpd-button--small wpd-button--ignore" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>" data-nonce="<?php echo esc_attr( $wp_rest_nonce ); ?>"><?php esc_html_e( 'Ignore', 'wp-performance-doctor' ); ?></button>
										<?php endif; ?>
										<?php if ( in_array( $finding['status'] ?? 'open', array( 'acknowledged', 'ignored' ), true ) ) : ?>
										<button type="button" class="wpd-button wpd-button--small wpd-button--restore" data-id="<?php echo esc_attr( $finding['id'] ?? '' ); ?>" data-nonce="<?php echo esc_attr( $wp_rest_nonce ); ?>"><?php esc_html_e( 'Restore', 'wp-performance-doctor' ); ?></button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
				<nav class="wpd-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Findings pages', 'wp-performance-doctor' ); ?>">
						<?php
						$base_url  = remove_query_arg( 'page' );
						$prev_page = ( $findings_page > 1 ) ? $findings_page - 1 : false;
						$next_page = ( $findings_page < $total_pages ) ? $findings_page + 1 : false;
						?>
						<?php if ( $prev_page ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'page', $prev_page, $base_url ) ); ?>" class="wpd-pagination-link wpd-pagination-link--prev"><?php esc_html_e( 'Previous', 'wp-performance-doctor' ); ?></a>
					<?php endif; ?>
					<span class="wpd-pagination-current">
						<?php
						echo esc_html(
							sprintf(
							/* translators: 1 is the current page number, 2 is the total number of pages. */
								__( 'Page %1$d of %2$d', 'wp-performance-doctor' ),
								$findings_page,
								$total_pages
							)
						);
						?>
					</span>
						<?php if ( $next_page ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'page', $next_page, $base_url ) ); ?>" class="wpd-pagination-link wpd-pagination-link--next"><?php esc_html_e( 'Next', 'wp-performance-doctor' ); ?></a>
					<?php endif; ?>
				</nav>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- Diagnostic Run History -->
	<?php if ( ! empty( $runs ) ) : ?>
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php esc_html_e( 'Diagnostic Run History', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php esc_html_e( 'Previous diagnostic evaluations for historical comparison.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<table class="wpd-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Run ID', 'wp-performance-doctor' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php esc_html_e( 'Rules Evaluated', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php esc_html_e( 'Findings Detected', 'wp-performance-doctor' ); ?><br><span class="wpd-text--small wpd-text--muted"><?php esc_html_e( 'this run', 'wp-performance-doctor' ); ?></span></th>
							<th class="wpd-table-cell-numeric"><?php esc_html_e( 'Health Score', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php esc_html_e( 'Completed At', 'wp-performance-doctor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $runs as $run ) : ?>
						<tr>
							<td><?php echo esc_html( $run['id'] ?? '' ); ?></td>
							<td><?php echo esc_html( ucfirst( $run['trigger_type'] ?? '' ) ); ?></td>
							<td><span class="wpd-badge wpd-badge--<?php echo esc_attr( $run['status'] ?? 'completed' ); ?>"><?php echo esc_html( ucfirst( $run['status'] ?? '' ) ); ?></span></td>
							<td class="wpd-table-cell-numeric"><?php echo esc_html( $run['rules_evaluated'] ?? 0 ); ?></td>
							<td class="wpd-table-cell-numeric"><?php echo esc_html( $run['findings_count'] ?? 0 ); ?></td>
							<td class="wpd-table-cell-numeric"><?php echo null !== $run['health_score'] ? esc_html( $run['health_score'] ) : esc_html__( 'N/A', 'wp-performance-doctor' ); ?></td>
							<td class="wpd-table-cell-numeric"><?php echo esc_html( $run['completed_at'] ? date_i18n( __( 'M j, Y g:i a', 'wp-performance-doctor' ), strtotime( $run['completed_at'] ) ) : __( 'N/A', 'wp-performance-doctor' ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<!-- Finding Detail Modal -->
	<div class="wpd-finding-modal-backdrop" id="wpd-finding-modal-backdrop"></div>
	<div class="wpd-finding-modal" id="wpd-finding-modal" role="dialog" aria-modal="true" aria-labelledby="wpd-detail-modal-title">
		<div class="wpd-finding-modal-content">
			<div class="wpd-finding-modal-header">
				<h2 class="wpd-finding-modal-title" id="wpd-detail-modal-title"></h2>
				<button type="button" class="wpd-button wpd-button--small wpd-button--outline" id="wpd-modal-close"><?php esc_html_e( 'Close', 'wp-performance-doctor' ); ?></button>
			</div>
			<div class="wpd-finding-modal-body" id="wpd-detail-modal-content">
				<!-- Populated via AJAX -->
			</div>
			<div class="wpd-finding-modal-section" id="wpd-finding-history-section" style="display:none;">
				<h3 class="wpd-detail-section-title"><?php esc_html_e( 'Finding History', 'wp-performance-doctor' ); ?></h3>
				<div id="wpd-finding-history"></div>
			</div>
		</div>
	</div>

</div>

<script id="wpd-diagnostics-config" type="application/json">
<?php
echo esc_html(
	wp_json_encode(
		array(
			'restUrl'         => $rest_url,
			'findingsRestUrl' => $findings_rest,
			'nonce'           => $collect_nonce,
			'wpRestNonce'     => $wp_rest_nonce,
			'isRunning'       => $is_running,
		)
	)
);
?>
</script>
