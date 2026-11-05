<?php
/**
 * Performance view for WP Performance Doctor admin.
 *
 * Displays performance profiling data, bottleneck analysis, historical
 * comparisons, and on-demand profiling controls.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Rules\Severity;

$enabled          = ! empty( $data['enabled'] );
$has_data         = ! empty( $data['has_data'] );
$metrics          = $data['metrics'] ?? array();
$metrics_by_id    = $data['metrics_by_id'] ?? array();
$grouped_metrics  = $data['grouped_metrics'] ?? array();
$category_labels  = $data['category_labels'] ?? array();
$bottlenecks      = $data['bottlenecks'] ?? array();
$bottleneck_summary = $data['bottleneck_summary'] ?? array(
	'confirmed' => 0,
	'potential' => 0,
	'info'      => 0,
);
$sessions         = $data['sessions'] ?? array();
$sessions_total   = (int) ( $data['sessions_total'] ?? 0 );
$latest_session   = $data['latest_session'] ?? null;
$comparison       = $data['comparison'] ?? null;
$overview         = $data['overview'] ?? array();
$frontend_metrics = $data['frontend_metrics'] ?? array();
$resource_metrics = $data['resource_metrics'] ?? array();
$database_metrics = $data['database_metrics'] ?? array();
$rest_url         = $data['rest_url'] ?? '';
$browser_rest_url = $data['browser_rest_url'] ?? '';
$collect_nonce    = $data['nonce'] ?? \wp_create_nonce( 'wpd_collect' );
$browser_nonce    = $data['browser_nonce'] ?? \wp_create_nonce( 'wpd_collect' );
$wp_rest_nonce    = \wp_create_nonce( 'wp_rest' );

$session_id   = $latest_session['id'] ?? '';
$snapshot_id  = $latest_session['snapshot_id'] ?? '';
$target_url   = $latest_session['target_url'] ?? \home_url();
$session_mode = $latest_session['mode'] ?? '';
$session_ctx  = $latest_session['request_context'] ?? 'admin';

$confirmed_count = (int) ( $bottleneck_summary['confirmed'] ?? 0 );
$potential_count = (int) ( $bottleneck_summary['potential'] ?? 0 );
$info_count      = (int) ( $bottleneck_summary['info'] ?? 0 );

$confirmed_bottlenecks = array_filter( $bottlenecks, function ( $b ) { return 'confirmed' === ( $b['type'] ?? '' ); } );
$potential_bottlenecks = array_filter( $bottlenecks, function ( $b ) { return 'potential' === ( $b['type'] ?? '' ); } );
$info_bottlenecks      = array_filter( $bottlenecks, function ( $b ) { return 'info' === ( $b['type'] ?? '' ); } );

$type_badge_classes = array(
	'confirmed' => 'wpd-finding-type--confirmed',
	'potential' => 'wpd-finding-type--potential',
	'info'      => 'wpd-finding-type--info',
);

$severity_badge_classes = array(
	'critical' => 'wpd-severity--critical',
	'high'     => 'wpd-severity--high',
	'medium'   => 'wpd-severity--medium',
	'low'      => 'wpd-severity--low',
	'info'     => 'wpd-severity--info',
);

?>
<div class="wrap wp-performance-doctor wpd-performance">

	<?php
	// Inline JSON config consumed by admin.js.
	echo '<script id="wpd-performance-config" type="application/json">';
	echo \esc_html(
		\wp_json_encode(
			array(
				'restUrl'     => $rest_url,
				'restBrowserUrl' => $browser_rest_url,
				'nonce'       => $collect_nonce,
				'browserNonce' => $browser_nonce,
				'wpRestNonce' => $wp_rest_nonce,
				'sessionId'   => $session_id,
				'snapshotId'  => $snapshot_id,
				'targetUrl'   => $target_url,
				'strings'     => array(
					'runProfile'    => __( 'Run Profile', 'wp-performance-doctor' ),
					'profiling'     => __( 'Profiling…', 'wp-performance-doctor' ),
					'complete'      => __( 'Profile complete.', 'wp-performance-doctor' ),
					'error'         => __( 'Profiling failed.', 'wp-performance-doctor' ),
					'noData'        => __( 'No performance data available. Run a profile to collect measurements.', 'wp-performance-doctor' ),
					'collectBrowser' => __( 'Collect Browser Metrics', 'wp-performance-doctor' ),
					'collecting'    => __( 'Collecting browser metrics…', 'wp-performance-doctor' ),
					'browserStored' => __( 'Browser metrics stored.', 'wp-performance-doctor' ),
					'noSession'     => __( 'No active profiling session. Run a profile first.', 'wp-performance-doctor' ),
				),
			)
		)
	);
	echo '</script>';
	?>

	<div id="wpd-performance-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>

	<!-- ======================================================================
	     A. Page Header
	     ====================================================================== -->
	<header class="wpd-page-header wpd-page-header--stacked">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php \esc_html_e( 'Performance Profiling', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php
				\esc_html_e(
					'Measure your WordPress performance, identify bottlenecks, and understand what needs attention.',
					'wp-performance-doctor'
				);
				?>
			</p>
		</div>
		<div class="wpd-header-meta">
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Monitoring', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value">
					<span class="wpd-status-badge wpd-status--<?php echo \esc_attr( $enabled ? 'available' : 'unavailable' ); ?>">
						<?php echo \esc_html( $enabled ? __( 'Enabled', 'wp-performance-doctor' ) : __( 'Disabled', 'wp-performance-doctor' ) ); ?>
					</span>
				</span>
			</div>
			<div class="wpd-meta-item">
				<span class="wpd-meta-item__label"><?php \esc_html_e( 'Latest Profile', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-meta-value">
					<?php
					$latest_ts = $latest_session['started_at'] ?? null;
					if ( $latest_ts ) {
						echo \esc_html( MetricFormatter::format_date( $latest_ts ) );
					} else {
						echo \esc_html__( 'Not yet', 'wp-performance-doctor' );
					}
					?>
				</span>
			</div>
		</div>
		<div class="wpd-header-actions">
			<?php if ( $enabled ) : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-profile">
				<span class="wpd-button-spinner"></span>
				<span class="wpd-button-label" id="wpd-run-profile-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php else : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium wpd-button--disabled" disabled title="<?php \esc_attr_e( 'Performance profiling is disabled. Enable it in Settings to start profiling.', 'wp-performance-doctor' ); ?>">
				<span class="wpd-button-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! $enabled ) : ?>
	<div class="wpd-notice wpd-notice--info wpd-notice--inline is-dismissible">
		<p><?php \esc_html_e( 'Performance profiling is disabled. Enable it in Settings to start collecting performance data.', 'wp-performance-doctor' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- ======================================================================
	     B. Performance Overview
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Performance Overview', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'A summary of the latest profiling session. Values reflect the most recent on-demand profile.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-kpi-grid">
			<?php
			$overview_cards = array(
				array(
					'title' => __( 'Confirmed Bottlenecks', 'wp-performance-doctor' ),
					'value' => $confirmed_count,
					'explanation' => __( 'Issues confirmed from collected measurements that are likely affecting performance.', 'wp-performance-doctor' ),
					'class'   => $confirmed_count > 0 ? 'wpd-kpi-card--attention' : '',
				),
				array(
					'title' => __( 'Available Measurements', 'wp-performance-doctor' ),
					'value' => (int) ( $overview['available_measurements'] ?? \count( $metrics ) ),
					'explanation' => __( 'Individual metrics collected during the latest profiling session.', 'wp-performance-doctor' ),
					'class'   => '',
				),
				array(
					'title' => __( 'Latest Profile', 'wp-performance-doctor' ),
					'value' => $latest_session ? \esc_html( MetricFormatter::format_date( $latest_session['started_at'] ?? null ) ) : __( 'Not yet', 'wp-performance-doctor' ),
					'value_is_html' => true,
					'explanation' => __( 'When the most recent profiling session was completed.', 'wp-performance-doctor' ),
					'class'   => '',
				),
			);
			foreach ( $overview_cards as $card ) :
			?>
			<div class="wpd-kpi-card <?php echo \esc_attr( $card['class'] ); ?>">
				<div class="wpd-kpi-card-body">
					<div class="wpd-kpi-card-header">
						<span class="wpd-kpi-card-title"><?php echo \esc_html( $card['title'] ); ?></span>
					</div>
					<span class="wpd-kpi-card-value"><?php
						if ( ! empty( $card['value_is_html'] ) ) {
							echo $card['value'];
						} else {
							echo \esc_html( $card['value'] );
						}
					?></span>
					<p class="wpd-kpi-card-label"><?php echo \esc_html( $card['explanation'] ); ?></p>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- ======================================================================
	     C. Run Profile
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Start a server-side performance profile of the current admin request, or collect browser performance metrics from a page of your choice.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-profile-form">
					<div class="wpd-profile-card">
						<h3 class="wpd-profile-card__title"><?php \esc_html_e( 'Server Profile', 'wp-performance-doctor' ); ?></h3>
						<p class="wpd-profile-card__desc"><?php \esc_html_e( 'Profiles the current admin request: PHP runtime, database queries, cache, and system metrics. Results are stored as a profiling session.', 'wp-performance-doctor' ); ?></p>
						<div class="wpd-profile-card__fields">
							<div class="wpd-form-field">
								<label class="wpd-form-label"><?php \esc_html_e( 'Profiling target', 'wp-performance-doctor' ); ?></label>
								<span class="wpd-form-value wpd-text--mono wpd-text--small"><?php echo \esc_html( \admin_url() ); ?></span>
								<p class="wpd-form-hint"><?php \esc_html_e( 'The profile runs on the current admin page request. It does not measure a public frontend URL.', 'wp-performance-doctor' ); ?></p>
							</div>
							<div class="wpd-form-field">
								<label class="wpd-form-label"><?php \esc_html_e( 'Measurement context', 'wp-performance-doctor' ); ?></label>
								<span class="wpd-form-value"><?php echo \esc_html( \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::request_context_label( $session_ctx ) ); ?></span>
								<p class="wpd-form-hint"><?php \esc_html_e( 'Server-side profiling always measures the current admin request.', 'wp-performance-doctor' ); ?></p>
							</div>
							<div class="wpd-form-field">
								<label class="wpd-form-label"><?php \esc_html_e( 'Profiling mode', 'wp-performance-doctor' ); ?></label>
								<span class="wpd-form-value"><?php echo \esc_html( \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::session_mode_label( $session_mode ) ); ?></span>
								<p class="wpd-form-hint"><?php \esc_html_e( 'On-demand profiles are started manually by an administrator.', 'wp-performance-doctor' ); ?></p>
							</div>
						</div>
						<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-profile">
							<span class="wpd-button-spinner"></span>
							<span class="wpd-button-label" id="wpd-run-profile-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
						</button>
					</div>

					<div class="wpd-profile-card wpd-profile-card--browser wpd-mt-6">
						<h3 class="wpd-profile-card__title"><?php \esc_html_e( 'Browser Metrics', 'wp-performance-doctor' ); ?></h3>
						<p class="wpd-profile-card__desc"><?php \esc_html_e( 'Collects Core Web Vitals and resource timings from the page currently open in your browser using native performance APIs.', 'wp-performance-doctor' ); ?></p>
						<div class="wpd-profile-card__fields">
							<div class="wpd-form-field">
								<label class="wpd-form-label"><?php \esc_html_e( 'Target URL', 'wp-performance-doctor' ); ?></label>
								<input type="url" id="wpd-perf-target-url" class="wpd-form-input" value="<?php echo \esc_attr( $target_url ); ?>" placeholder="<?php \esc_attr_e( 'https://example.com/', 'wp-performance-doctor' ); ?>">
								<p class="wpd-form-hint"><?php \esc_html_e( 'The exact URL that will be measured in your browser.', 'wp-performance-doctor' ); ?></p>
							</div>
							<div class="wpd-form-field">
								<label class="wpd-form-label"><?php \esc_html_e( 'Measurement context', 'wp-performance-doctor' ); ?></label>
								<select id="wpd-perf-context" class="wpd-form-input">
									<option value="admin"><?php \esc_html_e( 'Current admin page', 'wp-performance-doctor' ); ?></option>
									<option value="frontend"><?php \esc_html_e( 'Public frontend page', 'wp-performance-doctor' ); ?></option>
								</select>
								<p class="wpd-form-hint"><?php \esc_html_e( 'Browser metrics are collected from the page currently open in this tab. To profile a public frontend URL, open that URL in your browser first.', 'wp-performance-doctor' ); ?></p>
							</div>
						</div>
						<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-frontend-profile">
							<span class="wpd-button-spinner"></span>
							<span class="wpd-button-label" id="wpd-run-frontend-profile-label"><?php \esc_html_e( 'Collect Browser Metrics', 'wp-performance-doctor' ); ?></span>
						</button>
						<input type="hidden" id="wpd-perf-session-id" value="<?php echo \esc_attr( $session_id ); ?>">
						<input type="hidden" id="wpd-perf-snapshot-id" value="<?php echo \esc_attr( $snapshot_id ); ?>">
					</div>
					<div id="wpd-frontend-profile-notice" class="wpd-notice wpd-notice--inline wpd-hidden"></div>
				</div>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     D. Bottlenecks & Recommendations
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Bottlenecks &amp; Recommendations', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Findings from the latest profile, separated by severity. Informational observations are not treated as bottlenecks.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php if ( empty( $bottlenecks ) ) : ?>
				<div class="wpd-empty-state">
					<p class="wpd-empty-state-title"><?php \esc_html_e( 'No performance bottlenecks detected.', 'wp-performance-doctor' ); ?></p>
					<p><?php \esc_html_e( 'The latest profile did not identify any confirmed bottlenecks. Run a profile to gather fresh measurements.', 'wp-performance-doctor' ); ?></p>
				</div>
				<?php else : ?>
				<div class="wpd-finding-type-summary wpd-mb-6">
					<?php if ( $confirmed_count > 0 ) : ?>
					<div class="wpd-finding-type-chip wpd-finding-type-chip--confirmed">
						<span class="wpd-finding-type-chip__count"><?php echo \esc_html( $confirmed_count ); ?></span>
						<span class="wpd-finding-type-chip__label"><?php \esc_html_e( 'Confirmed bottlenecks', 'wp-performance-doctor' ); ?></span>
					</div>
					<?php endif; ?>
					<?php if ( $potential_count > 0 ) : ?>
					<div class="wpd-finding-type-chip wpd-finding-type-chip--potential">
						<span class="wpd-finding-type-chip__count"><?php echo \esc_html( $potential_count ); ?></span>
						<span class="wpd-finding-type-chip__label"><?php \esc_html_e( 'Potential issues', 'wp-performance-doctor' ); ?></span>
					</div>
					<?php endif; ?>
					<?php if ( $info_count > 0 ) : ?>
					<div class="wpd-finding-type-chip wpd-finding-type-chip--info">
						<span class="wpd-finding-type-chip__count"><?php echo \esc_html( $info_count ); ?></span>
						<span class="wpd-finding-type-chip__label"><?php \esc_html_e( 'Informational observations', 'wp-performance-doctor' ); ?></span>
					</div>
					<?php endif; ?>
				</div>

				<div class="wpd-bottleneck-grid">
					<?php foreach ( $bottlenecks as $bottleneck ) : ?>
					<?php
					$b_type      = $bottleneck['type'] ?? 'info';
					$b_severity  = $bottleneck['severity'] ?? 'info';
					$b_conf      = $bottleneck['confidence'] ?? 'low';
					$b_class     = $type_badge_classes[ $b_type ] ?? 'wpd-finding-type--info';
					$sev_class   = $severity_badge_classes[ $b_severity ] ?? 'wpd-severity--info';
					$evidence    = $bottleneck['evidence'] ?? array();
					$remediation = $bottleneck['remediation'] ?? array();
					$steps       = $remediation['steps'] ?? array();
					?>
					<article class="wpd-bottleneck-card wpd-bottleneck--<?php echo \esc_attr( $b_type ); ?>">
						<div class="wpd-bottleneck-header">
							<div class="wpd-bottleneck-header__main">
								<h3 class="wpd-bottleneck-title"><?php echo \esc_html( $bottleneck['title'] ?? '' ); ?></h3>
								<span class="wpd-finding-type-badge <?php echo \esc_attr( $b_class ); ?>">
									<?php
									$type_labels = array(
										'confirmed' => __( 'Confirmed bottleneck', 'wp-performance-doctor' ),
										'potential' => __( 'Potential issue', 'wp-performance-doctor' ),
										'info'      => __( 'Informational', 'wp-performance-doctor' ),
									);
									echo \esc_html( $type_labels[ $b_type ] ?? __( 'Informational', 'wp-performance-doctor' ) );
									?>
								</span>
							</div>
							<div class="wpd-bottleneck-header__meta">
								<span class="wpd-severity-badge <?php echo \esc_attr( $sev_class ); ?>">
									<?php echo \esc_html( \WPPerformanceDoctor\Rules\Severity::label( $b_severity ) ); ?>
								</span>
								<span class="wpd-badge wpd-badge--small wpd-badge--outline">
									<?php echo \esc_html( \ucfirst( $b_conf ) ); ?> Confidence
								</span>
							</div>
						</div>
						<div class="wpd-bottleneck-body">
							<p class="wpd-bottleneck-description"><?php echo \esc_html( $bottleneck['description'] ?? '' ); ?></p>
							<?php if ( ! empty( $evidence ) ) : ?>
							<div class="wpb-bottleneck-evidence">
								<h4 class="wpd-bottleneck-evidence-title"><?php \esc_html_e( 'Measured values', 'wp-performance-doctor' ); ?></h4>
								<table class="wpd-table wpd-table--compact wpd-bottleneck-evidence">
									<tbody>
									<?php foreach ( $evidence as $key => $ev ) : ?>
										<?php
										$m = $metrics_by_id[ $key ] ?? null;
										$disp = MetricFormatter::display_data( array_merge(
											array(
												'metric_identifier'   => $key,
												'metric_value'        => $ev,
												'unit'                  => $m['unit'] ?? '',
												'availability_status'   => 'available',
											),
											$m ?? array()
										) );
										?>
									<tr>
										<th><?php echo \esc_html( $disp['label'] ); ?></th>
										<td class="wpd-table-cell-numeric"><?php echo \esc_html( $disp['display'] ); ?></td>
									</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php endif; ?>
							<?php
							$impact   = $bottleneck['impact'] ?? '';
							$limitations = $bottleneck['measurement_limitations'] ?? '';
							if ( $impact || $limitations ) :
							?>
							<div class="wpd-bottleneck-details">
								<?php if ( $impact ) : ?>
								<p class="wpd-bottleneck-impact"><span class="wpd-bottleneck-details__label"><?php \esc_html_e( 'Why it matters:', 'wp-performance-doctor' ); ?></span> <?php echo \esc_html( $impact ); ?></p>
								<?php endif; ?>
								<?php if ( $limitations ) : ?>
								<p class="wpd-bottleneck-limitations wpd-text--small wpd-text--muted"><span class="wpd-bottleneck-details__label"><?php \esc_html_e( 'Limitations:', 'wp-performance-doctor' ); ?></span> <?php echo \esc_html( $limitations ); ?></p>
								<?php endif; ?>
							</div>
							<?php endif; ?>
						</div>
						<div class="wpd-bottleneck-actions">
							<?php if ( ! empty( $steps ) ) : ?>
							<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-bottleneck-steps" data-steps="<?php echo \esc_attr( \wp_json_encode( $steps ) ); ?>">
								<?php \esc_html_e( 'View Steps', 'wp-performance-doctor' ); ?>
							</button>
							<?php endif; ?>
							<?php
							$risk       = $remediation['risk'] ?? 'low';
							$difficulty = $remediation['difficulty'] ?? 'medium';
							?>
							<span class="wpd-bottleneck-meta">
								<span class="wpd-badge wpd-badge--outline wpd-badge--small"><?php echo \esc_html( \ucfirst( $risk ) ); ?> Risk</span>
								<span class="wpd-badge wpd-badge--outline wpd-badge--small"><?php echo \esc_html( \ucfirst( $difficulty ) ); ?></span>
							</span>
						</div>
					</article>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     E. Frontend Performance
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Frontend Performance', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Browser-collected Core Web Vitals and navigation timings. Values reflect the page and context shown below.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
				<div class="wpd-panel-body">
					<?php
					$first_frontend = $frontend_metrics[0] ?? null;
					$context_url    = $first_frontend['page_url'] ?? $target_url;
					$context_label  = $first_frontend['context_label'] ?? \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::request_context_label( $session_ctx );
					?>
					<div class="wpd-frontend-context">
						<span class="wpd-frontend-context__label"><?php \esc_html_e( 'Measured page:', 'wp-performance-doctor' ); ?></span>
						<span class="wpd-frontend-context__value wpd-text--mono wpd-text--small"><?php echo \esc_html( $context_url ); ?></span>
						<span class="wpd-frontend-context__sep">·</span>
						<span class="wpd-frontend-context__label"><?php \esc_html_e( 'Context:', 'wp-performance-doctor' ); ?></span>
						<span class="wpd-frontend-context__value"><?php echo \esc_html( $context_label ); ?></span>
					</div>
					<?php $any_frontend = false; foreach ( $frontend_metrics as $fm ) { if ( $fm['available'] ) { $any_frontend = true; break; } } ?>
				<?php if ( ! $any_frontend ) : ?>
				<div class="wpd-empty-state">
					<p class="wpd-empty-state-title"><?php \esc_html_e( 'No frontend performance measurements yet.', 'wp-performance-doctor' ); ?></p>
					<p><?php \esc_html_e( 'Collect browser metrics to see TTFB, LCP, CLS, INP and page load timings.', 'wp-performance-doctor' ); ?></p>
					<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-frontend-profile">
						<span class="wpd-button-spinner"></span>
						<span class="wpd-button-label" id="wpd-run-frontend-profile-label"><?php \esc_html_e( 'Collect Browser Metrics', 'wp-performance-doctor' ); ?></span>
					</button>
					<input type="hidden" id="wpd-perf-session-id" value="<?php echo \esc_attr( $session_id ); ?>">
					<input type="hidden" id="wpd-perf-snapshot-id" value="<?php echo \esc_attr( $snapshot_id ); ?>">
				</div>
				<?php else : ?>
				<div class="wpd-vital-grid">
					<?php foreach ( $frontend_metrics as $fm ) : ?>
					<?php
					$vital_tooltips = array(
						'frontend.navigation_timing_ttfb'       => __( 'Time from the browser request to the first byte of the server response.', 'wp-performance-doctor' ),
						'frontend.largest_contentful_paint'     => __( 'When the largest visible content element finishes rendering.', 'wp-performance-doctor' ),
						'frontend.cumulative_layout_shift'      => __( 'Unexpected layout shifts that occur while the page loads.', 'wp-performance-doctor' ),
						'frontend.interaction_to_next_paint'    => __( 'The slowest interaction latency on the page.', 'wp-performance-doctor' ),
						'frontend.navigation_timing_dom_content_loaded' => __( 'When the initial HTML document is fully loaded and parsed.', 'wp-performance-doctor' ),
						'frontend.navigation_timing_page_load'  => __( 'When the page and all its resources have finished loading.', 'wp-performance-doctor' ),
					);
					$tooltip = $vital_tooltips[ $fm['identifier'] ] ?? '';
					?>
					<div class="wpd-vital-card wpd-vital--<?php echo \esc_attr( $fm['rating'] ?? 'not-measured' ); ?>">
						<div class="wpd-vital-card__label">
							<?php echo \esc_html( $fm['label'] ); ?>
							<?php if ( $tooltip ) : ?>
							<span class="wpd-vital-card__tooltip" title="<?php echo \esc_attr( $tooltip ); ?>">
								<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
							</span>
							<?php endif; ?>
						</div>
						<div class="wpd-vital-card__value">
							<?php echo \esc_html( $fm['available'] ? $fm['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
							<?php if ( $fm['available'] && ! empty( $fm['unit'] ) ) : ?>
							<span class="wpd-vital-card__unit"><?php echo \esc_html( $fm['unit'] ); ?></span>
							<?php endif; ?>
						</div>
						<span class="wpd-vital-card__status wpd-vital-status--<?php echo \esc_attr( $fm['rating'] ?? 'not-measured' ); ?>">
							<?php echo \esc_html( $fm['rating_label'] ); ?>
						</span>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     F. Resource Analysis
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Resource Analysis', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Frontend resources detected during profiling, grouped by type.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<div class="wpd-resource-grid">
					<?php foreach ( $resource_metrics as $rm ) : ?>
					<div class="wpd-metric-card">
						<div class="wpd-metric-card-body">
							<span class="wpd-metric-label"><?php echo \esc_html( $rm['label'] ); ?></span>
							<span class="wpd-metric-value"><?php echo \esc_html( $rm['available'] ? $rm['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
							<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $rm['available'] ? 'available' : 'unavailable' ); ?>">
								<?php echo \esc_html( $rm['available'] ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
							</span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     G. Database Profiling
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Database Profiling', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Query profiling relies on WordPress SAVEQUERIES. Enable it temporarily to capture query counts and timings.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php
				$db_metrics   = $database_metrics['metrics'] ?? array();
				$sq_enabled   = $database_metrics['savequeries_enabled'] ?? null;
				$sq_available = $database_metrics['savequeries_available'] ?? false;
				$db_metric    = function ( $id ) use ( $db_metrics ) {
					foreach ( $db_metrics as $m ) {
						if ( ( $m['identifier'] ?? '' ) === $id ) {
							return $m;
						}
					}
					return null;
				};
				?>
				<div class="wpd-database-grid">
					<div class="wpd-metric-card">
						<div class="wpd-metric-card-body">
							<span class="wpd-metric-label"><?php \esc_html_e( 'SAVEQUERIES', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-metric-value wpd-text--mono">
								<?php
								if ( ! $sq_available ) {
									echo \esc_html__( 'Not detected', 'wp-performance-doctor' );
								} elseif ( $sq_enabled ) {
									echo \esc_html__( 'Enabled', 'wp-performance-doctor' );
								} else {
									echo \esc_html__( 'Disabled', 'wp-performance-doctor' );
								}
								?>
							</span>
							<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $sq_available ? ( $sq_enabled ? 'available' : 'available' ) : 'unavailable' ); ?>">
								<?php
								if ( ! $sq_available ) {
									echo \esc_html__( 'Not detected', 'wp-performance-doctor' );
								} elseif ( $sq_enabled ) {
									echo \esc_html__( 'Enabled', 'wp-performance-doctor' );
								} else {
									echo \esc_html__( 'Disabled', 'wp-performance-doctor' );
								}
								?>
							</span>
						</div>
					</div>
					<div class="wpd-metric-card">
						<div class="wpd-metric-card-body">
							<span class="wpd-metric-label"><?php \esc_html_e( 'Query Count', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-metric-value wpd-text--mono"><?php echo \esc_html( $db_metric( 'database.query_count' )['available'] ? $db_metric( 'database.query_count' )['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
							<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $db_metric( 'database.query_count' )['available'] ? 'available' : 'unavailable' ); ?>">
								<?php echo \esc_html( $db_metric( 'database.query_count' )['available'] ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
							</span>
						</div>
					</div>
					<div class="wpd-metric-card">
						<div class="wpd-metric-card-body">
							<span class="wpd-metric-label"><?php \esc_html_e( 'Total Query Time', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-metric-value wpd-text--mono"><?php echo \esc_html( $db_metric( 'database.query_time_total' )['available'] ? $db_metric( 'database.query_time_total' )['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
							<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $db_metric( 'database.query_time_total' )['available'] ? 'available' : 'unavailable' ); ?>">
								<?php echo \esc_html( $db_metric( 'database.query_time_total' )['available'] ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
							</span>
						</div>
					</div>
					<div class="wpd-metric-card">
						<div class="wpd-metric-card-body">
							<span class="wpd-metric-label"><?php \esc_html_e( 'Slow Query Count', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-metric-value wpd-text--mono"><?php echo \esc_html( $db_metric( 'database.slow_query_count' )['available'] ? $db_metric( 'database.slow_query_count' )['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
							<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $db_metric( 'database.slow_query_count' )['available'] ? 'available' : 'unavailable' ); ?>">
								<?php echo \esc_html( $db_metric( 'database.slow_query_count' )['available'] ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
							</span>
						</div>
					</div>
				</div>

				<?php
				$qt = $db_metric( 'database.query_type_distribution' );
				if ( $qt['available'] && is_array( $qt['value'] ) && ! empty( $qt['value'] ) ) :
				?>
				<div class="wpd-database-types wpd-mt-6">
					<h4 class="wpd-database-types-title"><?php \esc_html_e( 'Query Type Distribution', 'wp-performance-doctor' ); ?></h4>
					<table class="wpd-table wpd-table--compact">
						<thead>
							<tr>
								<th><?php \esc_html_e( 'Type', 'wp-performance-doctor' ); ?></th>
								<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Count', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $qt['value'] as $query_type => $count ) : ?>
							<tr>
								<td><?php echo \esc_html( \ucfirst( $query_type ) ); ?></td>
								<td class="wpd-table-cell-numeric"><?php echo \esc_html( (int) $count ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<?php if ( ! $sq_enabled ) : ?>
				<div class="wpd-expandable wpd-mt-6" id="wpd-savequeries-setup">
					<div class="wpd-expandable-header" role="button" tabindex="0" aria-expanded="false" aria-controls="wpd-savequeries-setup-content">
						<h3 class="wpd-expandable-title"><?php \esc_html_e( 'How to enable SAVEQUERIES (temporary debugging)', 'wp-performance-doctor' ); ?></h3>
						<span class="wpd-expandable-toggle">+</span>
					</div>
					<div class="wpd-expandable-content" id="wpd-savequeries-setup-content" hidden>
						<p><?php \esc_html_e( 'SAVEQUERIES is disabled, so query counts, total query time, and slow queries are not available. This is expected and safe: the plugin does not modify wp-config.php or enable SAVEQUERIES automatically.', 'wp-performance-doctor' ); ?></p>
						<ol class="wpd-list wpd-list--decimal">
							<li><?php \esc_html_e( 'Open your site\'s <code>wp-config.php</code> file.', 'wp-performance-doctor' ); ?></li>
							<li><?php \esc_html_e( 'Add or update the line: <code>define( \'SAVEQUERIES\', true );</code> before the <code>/* That\'s all, stop editing! */</code> comment.', 'wp-performance-doctor' ); ?></li>
							<li><?php \esc_html_e( 'Reload the page and run a new profile to see query count, total query time, slow query count, and query type distribution.', 'wp-performance-doctor' ); ?></li>
							<li><?php \esc_html_e( 'For production sites, only enable SAVEQUERIES temporarily for debugging, as it adds memory and processing overhead.', 'wp-performance-doctor' ); ?></li>
						</ol>
						<p><?php \esc_html_e( 'The plugin stores only sanitized query fingerprints (normalized SQL with literals replaced by <code>?</code>, truncated to 128 characters) and never stores raw SQL containing personal data or credentials.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     H. Collected Metrics
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Collected Metrics', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'All measurements from the latest profiling snapshot, grouped by category. Expand a category to view raw metric identifiers and values.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php if ( empty( $grouped_metrics ) ) : ?>
				<div class="wpd-empty-state">
					<p class="wpd-empty-state-title"><?php \esc_html_e( 'No metrics collected yet.', 'wp-performance-doctor' ); ?></p>
					<p><?php \esc_html_e( 'Run a profile to collect measurements.', 'wp-performance-doctor' ); ?></p>
				</div>
				<?php else : ?>
				<?php foreach ( $grouped_metrics as $category => $category_metrics ) : ?>
				<div class="wpd-metric-category wpd-mt-6">
					<div class="wpd-expandable" id="wpd-metrics-category-<?php echo \esc_attr( $category ); ?>">
						<div class="wpd-expandable-header" role="button" tabindex="0" aria-expanded="false" aria-controls="wpd-metrics-category-content-<?php echo \esc_attr( $category ); ?>">
							<h3 class="wpd-expandable-title">
								<?php echo \esc_html( $category_labels[ $category ] ?? \ucfirst( $category ) ); ?>
								<span class="wpd-expandable-category-count"><?php echo \esc_html( \count( $category_metrics ) ); ?></span>
							</h3>
							<span class="wpd-expandable-toggle">+</span>
						</div>
						<div class="wpd-expandable-content" id="wpd-metrics-category-content-<?php echo \esc_attr( $category ); ?>" hidden>
							<div class="wpd-metric-grid">
								<?php foreach ( $category_metrics as $metric ) : ?>
								<?php
								$logical_id = $metric['logical_identifier'] ?? ( $metric['metric_identifier'] ?? '' );
								$disp = MetricFormatter::display_data( $metric );
								?>
								<div class="wpd-metric-card">
									<div class="wpd-metric-card-body">
										<span class="wpd-metric-label"><?php echo \esc_html( $disp['label'] ); ?></span>
										<span class="wpd-metric-value"><?php echo \esc_html( $disp['display'] ); ?></span>
										<span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $disp['available'] ? 'available' : 'unavailable' ); ?>">
											<?php echo \esc_html( $disp['status_label'] ); ?>
										</span>
									</div>
								</div>
								<?php endforeach; ?>
							</div>
							<div class="wpd-metric-technical wpd-mt-4">
								<h4 class="wpd-metric-technical-title"><?php \esc_html_e( 'Technical details', 'wp-performance-doctor' ); ?></h4>
								<table class="wpd-table wpd-table--compact wpd-metric-technical-table">
									<thead>
										<tr>
											<th><?php \esc_html_e( 'Metric Identifier', 'wp-performance-doctor' ); ?></th>
											<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Value', 'wp-performance-doctor' ); ?></th>
											<th><?php \esc_html_e( 'Unit', 'wp-performance-doctor' ); ?></th>
											<th><?php \esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $category_metrics as $metric ) : ?>
									<?php
									$raw_id = $metric['metric_identifier'] ?? '';
									$logical_id = $metric['logical_identifier'] ?? $raw_id;
									$disp = MetricFormatter::display_data( $metric );
									?>
									<tr>
										<td class="wpd-text--mono wpd-text--small"><?php echo \esc_html( $logical_id ); ?></td>
										<td class="wpd-table-cell-numeric wpd-text--mono wpd-text--small"><?php echo \esc_html( $disp['display'] ); ?></td>
										<td class="wpd-text--small"><?php echo \esc_html( $disp['unit'] ?: __( '—', 'wp-performance-doctor' ) ); ?></td>
										<td><span class="wpd-metric-status wpd-metric-status--<?php echo \esc_attr( $disp['available'] ? 'available' : 'unavailable' ); ?> wpd-metric-status--small"><?php echo \esc_html( $disp['status_label'] ); ?></span></td>
									</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     I. Historical Comparison
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Historical Comparison', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Compares compatible measurements between the current and previous profiling snapshots.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php
				$has_comp = ! empty( $comparison['has_comparison'] );
				$prev_snap = $comparison['previous_snapshot'] ?? null;
				$comps = $comparison['comparisons'] ?? array();
				?>
				<?php if ( ! $has_comp ) : ?>
				<div class="wpd-empty-state">
					<p class="wpd-empty-state-title"><?php \esc_html_e( 'No comparable snapshots available.', 'wp-performance-doctor' ); ?></p>
					<p><?php \esc_html_e( 'A historical comparison requires at least two compatible profiling snapshots with matching measurement contexts.', 'wp-performance-doctor' ); ?></p>
				</div>
				<?php else : ?>
				<div class="wpd-comparison-header wpd-mb-4">
					<span class="wpd-comparison-header__label"><?php \esc_html_e( 'Current snapshot:', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-comparison-header__value"><?php echo \esc_html( $latest_session['started_at'] ?? __( 'Unknown', 'wp-performance-doctor' ) ); ?></span>
					<span class="wpd-comparison-header__label"><?php \esc_html_e( 'Previous snapshot:', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-comparison-header__value wpd-text--mono wpd-text--small">
						<?php
						if ( $prev_snap && isset( $prev_snap['timestamp'] ) ) {
							echo \esc_html( MetricFormatter::format_date( $prev_snap['timestamp'] ) );
						} else {
							echo \esc_html__( 'Unknown', 'wp-performance-doctor' );
						}
						?>
					</span>
				</div>
				<table class="wpd-table wpd-table--comparison">
					<thead>
						<tr>
							<th><?php \esc_html_e( 'Metric', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Previous', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Current', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Change', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( '% Change', 'wp-performance-doctor' ); ?></th>
							<th><?php \esc_html_e( 'Interpretation', 'wp-performance-doctor' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $comps as $comp ) : ?>
					<tr class="wpd-comparison-row wpd-comparison--<?php echo \esc_attr( $comp['interpretation'] ); ?>">
						<td><?php echo \esc_html( $comp['label'] ); ?></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( $comp['previous_display'] ); ?></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( $comp['current_display'] ); ?></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( $comp['difference_display'] ); ?></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( $comp['percent_display'] ); ?></td>
						<td>
							<span class="wpd-badge wpd-badge--small wpd-badge--<?php echo \esc_attr( $comp['interpretation'] === 'improved' ? 'success' : ( $comp['interpretation'] === 'worsened' ? 'warning' : 'outline' ) ); ?>">
								<?php echo \esc_html( $comp['interpretation_label'] ); ?>
							</span>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- ======================================================================
	     J. Profiling Session History
	     ====================================================================== -->
	<section class="wpd-section">
		<div class="wpd-section-header">
			<h2 class="wpd-section-title"><?php \esc_html_e( 'Profiling Session History', 'wp-performance-doctor' ); ?></h2>
			<p class="wpd-section-subtitle"><?php \esc_html_e( 'Recorded profiling sessions. Select a session to view its details.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-panel">
			<div class="wpd-panel-body">
				<?php if ( empty( $sessions ) ) : ?>
				<div class="wpd-empty-state">
					<p class="wpd-empty-state-title"><?php \esc_html_e( 'No profiling sessions recorded yet.', 'wp-performance-doctor' ); ?></p>
					<p><?php \esc_html_e( 'Run a profile to record the first session.', 'wp-performance-doctor' ); ?></p>
				</div>
				<?php else : ?>
				<table class="wpd-table">
					<thead>
						<tr>
							<th><?php \esc_html_e( 'Session ID', 'wp-performance-doctor' ); ?></th>
							<th><?php \esc_html_e( 'Date / Time', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-text--mono wpd-text--small"><?php \esc_html_e( 'Target URL', 'wp-performance-doctor' ); ?></th>
							<th><?php \esc_html_e( 'Context', 'wp-performance-doctor' ); ?></th>
							<th><?php \esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
							<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Measurements', 'wp-performance-doctor' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $sessions as $session ) : ?>
					<?php
					$s_status   = $session['status'] ?? 'completed';
					$s_mode     = $session['mode'] ?? '';
					$s_ctx      = $session['request_context'] ?? 'admin';
					$s_status_class = 'wpd-status--' . \esc_attr( $s_status );
					$s_status_label = \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::session_status_label( $s_status );
					$s_ctx_label = \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::request_context_label( $s_ctx );
					$s_mode_label = \WPPerformanceDoctor\Admin\Formatter\MetricFormatter::session_mode_label( $s_mode );
					$s_id = (int) ( $session['id'] ?? 0 );
					?>
					<tr class="wpd-session-row" data-session-id="<?php echo \esc_attr( $s_id ); ?>" data-session-url="<?php echo \esc_attr( $session['target_url'] ?? '' ); ?>" data-session-context="<?php echo \esc_attr( $s_ctx ); ?>" data-session-mode="<?php echo \esc_attr( $s_mode ); ?>">
						<td class="wpd-text--mono wpd-text--small">#<?php echo \esc_html( $s_id ); ?></td>
						<td><?php echo \esc_html( $session['started_at'] ? MetricFormatter::format_date( $session['started_at'] ) : __( 'N/A', 'wp-performance-doctor' ) ); ?></td>
						<td class="wpd-text--mono wpd-text--small"><?php echo \esc_html( $session['target_url'] ?? __( '—', 'wp-performance-doctor' ) ); ?></td>
						<td><?php echo \esc_html( $s_ctx_label ); ?></td>
						<td><span class="wpd-status-badge <?php echo \esc_attr( $s_status_class ); ?>"><?php echo \esc_html( $s_status_label ); ?></span></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( MetricFormatter::format_seconds( $session['duration'] ?? null ) ); ?></td>
						<td class="wpd-table-cell-numeric"><?php echo \esc_html( $session['metrics_count'] ?? 0 ); ?></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- Bottleneck Steps Modal -->
	<div class="wpd-modal-backdrop" id="wpd-bottleneck-steps-backdrop" hidden></div>
	<div class="wpd-modal" id="wpd-bottleneck-steps-modal" role="dialog" aria-modal="true" aria-labelledby="wpd-bottleneck-steps-title">
		<div class="wpd-modal-header">
			<h2 class="wpd-modal-title" id="wpd-bottleneck-steps-title"><?php \esc_html_e( 'Remediation Steps', 'wp-performance-doctor' ); ?></h2>
			<button type="button" class="wpd-modal-close" id="wpd-bottleneck-steps-close"><?php \esc_html_e( 'Close', 'wp-performance-doctor' ); ?></button>
		</div>
		<div class="wpd-modal-body">
			<div class="wpd-bottleneck-steps-body" id="wpd-bottleneck-steps-body">
				<!-- Populated via JavaScript -->
			</div>
		</div>
	</div>

</div>
