<?php
/**
 * Performance view for WP Performance Doctor admin.
 *
 * Displays a compact performance analytics dashboard: summary KPIs, profiling
 * controls, diagnostic findings, frontend vitals, resources, database
 * profiling, collected metrics, comparison, and session history.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Rules\Severity;

$enabled            = ! empty( $data['enabled'] );
$has_data           = ! empty( $data['has_data'] );
$metrics            = $data['metrics'] ?? array();
$metrics_by_id      = $data['metrics_by_id'] ?? array();
$grouped_metrics    = $data['grouped_metrics'] ?? array();
$category_labels    = $data['category_labels'] ?? array();
$bottlenecks        = $data['bottlenecks'] ?? array();
$bottleneck_groups  = $data['bottleneck_groups'] ?? array();
$severity_counts    = $data['severity_counts'] ?? array();
$bottleneck_summary = $data['bottleneck_summary'] ?? array(
	'confirmed' => 0,
	'potential' => 0,
	'info'      => 0,
);
$sessions           = $data['sessions'] ?? array();
$sessions_total     = (int) ( $data['sessions_total'] ?? 0 );
$latest_session     = $data['latest_session'] ?? null;
$comparison         = $data['comparison'] ?? null;
$overview           = $data['overview'] ?? array();
$frontend_metrics   = $data['frontend_metrics'] ?? array();
$frontend_status    = $data['frontend_status'] ?? null;
$frontend_context   = $data['frontend_context'] ?? array();
$resource_metrics   = $data['resource_metrics'] ?? array();
$resource_summary   = $data['resource_summary'] ?? array();
$resource_dist      = $data['resource_distribution'] ?? array();
$database_metrics   = $data['database_metrics'] ?? array();
$rest_url           = $data['rest_url'] ?? '';
$browser_rest_url   = $data['browser_rest_url'] ?? '';
$collect_nonce      = $data['nonce'] ?? \wp_create_nonce( 'wpd_collect' );
$browser_nonce      = $data['browser_nonce'] ?? \wp_create_nonce( 'wpd_collect' );
$wp_rest_nonce      = \wp_create_nonce( 'wp_rest' );

$session_id   = $latest_session['id'] ?? '';
$snapshot_id  = $latest_session['snapshot_id'] ?? '';
$target_url   = $latest_session['target_url'] ?? \admin_url();
$session_mode = $latest_session['mode'] ?? '';
$session_ctx  = $latest_session['request_context'] ?? 'admin';
$latest_ts    = $latest_session['started_at'] ?? null;
$server_target = \in_array( $session_ctx, array( 'rest', 'ajax', 'cron' ), true ) ? $rest_url : \admin_url();
$server_target_label = \in_array( $session_ctx, array( 'rest', 'ajax', 'cron' ), true )
	? __( 'Request that started the profile', 'wp-performance-doctor' )
	: __( 'Admin request', 'wp-performance-doctor' );

$confirmed_count = (int) ( $bottleneck_summary['confirmed'] ?? 0 );
$potential_count = (int) ( $bottleneck_summary['potential'] ?? 0 );
$info_count      = (int) ( $bottleneck_summary['info'] ?? 0 );
$category_count  = \count( $grouped_metrics );
$available_count = (int) ( $overview['available_measurements'] ?? \count( $metrics ) );
$collected_count = (int) ( $overview['collected_measurements'] ?? \count( $metrics ) );
$latest_duration = $overview['latest_profile_duration'] ?? null;

$severity_labels = array(
	'critical' => __( 'Critical', 'wp-performance-doctor' ),
	'high'     => __( 'High', 'wp-performance-doctor' ),
	'medium'   => __( 'Medium', 'wp-performance-doctor' ),
	'low'      => __( 'Low', 'wp-performance-doctor' ),
	'info'     => __( 'Informational', 'wp-performance-doctor' ),
);

$severity_help = array(
	'critical' => __( 'Immediate attention recommended. These findings have the largest measured impact.', 'wp-performance-doctor' ),
	'high'     => __( 'Confirmed issues that are likely to affect real users.', 'wp-performance-doctor' ),
	'medium'   => __( 'Potential issues supported by measured evidence.', 'wp-performance-doctor' ),
	'low'      => __( 'Minor observations with limited measured impact.', 'wp-performance-doctor' ),
	'info'     => __( 'Context and configuration observations. These are not confirmed bottlenecks.', 'wp-performance-doctor' ),
);

$category_icons = array(
	'php'          => 'cpu',
	'wordpress'    => 'globe',
	'database'     => 'database',
	'cache'        => 'zap',
	'system'       => 'server',
	'frontend'     => 'monitor',
	'performance'  => 'activity',
	'php_profiler' => 'activity',
);

$icon = static function ( string $name, string $class_name = 'wpd-perf-icon' ): string {
	$paths = array(
		'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
		'server'   => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/>',
		'monitor'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/>',
		'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
		'zap'      => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
		'cpu'      => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="2" x2="9" y2="5"/><line x1="15" y1="2" x2="15" y2="5"/><line x1="9" y1="19" x2="9" y2="22"/><line x1="15" y1="19" x2="15" y2="22"/><line x1="2" y1="9" x2="5" y2="9"/><line x1="2" y1="15" x2="5" y2="15"/><line x1="19" y1="9" x2="22" y2="9"/><line x1="19" y1="15" x2="22" y2="15"/>',
		'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
		'check'    => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
		'layers'   => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 12 12 17 22 12"/><polyline points="2 17 12 22 22 17"/>',
		'chart'    => '<line x1="4" y1="20" x2="4" y2="10"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="14"/><line x1="22" y1="20" x2="22" y2="8"/>',
		'list'     => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>',
		'info'     => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
		'arrow'    => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
		'chevron'  => '<polyline points="6 9 12 15 18 9"/>',
	);

	return '<svg class="' . \esc_attr( $class_name ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['activity'] ) . '</svg>';
};

$format_target = static function ( ?string $url ): string {
	$url = (string) $url;
	if ( '' === $url ) {
		return __( '—', 'wp-performance-doctor' );
	}
	$parts = \wp_parse_url( $url );
	if ( ! \is_array( $parts ) ) {
		return $url;
	}
	$host = $parts['host'] ?? '';
	$path = $parts['path'] ?? '/';
	$text = $host . $path;
	if ( '' === $text ) {
		return $url;
	}
	return $text;
};

$format_seconds_display = static function ( $value ): string {
	if ( null === $value || '' === $value || ! \is_numeric( $value ) ) {
		return __( '—', 'wp-performance-doctor' );
	}
	return MetricFormatter::format_seconds( (float) $value );
};

$current_snapshot      = $comparison['current_snapshot'] ?? null;
$previous_snapshot     = $comparison['previous_snapshot'] ?? null;
$comparisons           = $comparison['comparisons'] ?? array();
$comparison_summary    = $comparison['summary'] ?? array(
	'improved'       => 0,
	'worsened'       => 0,
	'unchanged'      => 0,
	'not_comparable' => 0,
	'total'          => 0,
);
$comparison_categories = $comparison['categories'] ?? array();
$has_comparison        = ! empty( $comparison['has_comparison'] );

$frontend_measured = false;
foreach ( $frontend_metrics as $fm_row ) {
	if ( ! empty( $fm_row['available'] ) ) {
		$frontend_measured = true;
		break;
	}
}

$db_state  = $database_metrics['state'] ?? 'unavailable';
$db_types  = $database_metrics['query_types'] ?? array();
$db_active = 'enabled' === $db_state;

$total_resources   = $resource_summary['total'] ?? null;
$transfer_size     = $resource_summary['transfer'] ?? null;
$slowest_load      = $resource_summary['slowest'] ?? null;
$external_count    = $resource_summary['external'] ?? null;
$resource_measured = ! empty( $resource_summary['measured'] );
?>

<div class="wrap wp-performance-doctor wpd-performance">

	<?php
	// Inline JSON config consumed by admin.js.
	echo '<script id="wpd-performance-config" type="application/json">';
	echo \esc_html(
		\wp_json_encode(
			array(
				'restUrl'        => $rest_url,
				'restBrowserUrl' => $browser_rest_url,
				'nonce'          => $collect_nonce,
				'browserNonce'   => $browser_nonce,
				'wpRestNonce'    => $wp_rest_nonce,
				'sessionId'      => $session_id,
				'snapshotId'     => $snapshot_id,
				'targetUrl'      => $target_url,
				'strings'        => array(
					'runProfile'     => __( 'Run Profile', 'wp-performance-doctor' ),
					'profiling'      => __( 'Profiling…', 'wp-performance-doctor' ),
					'complete'       => __( 'Profile complete.', 'wp-performance-doctor' ),
					'error'          => __( 'Profiling failed.', 'wp-performance-doctor' ),
					'noData'         => __( 'No performance data available. Run a profile to collect measurements.', 'wp-performance-doctor' ),
					'collectBrowser' => __( 'Collect Browser Metrics', 'wp-performance-doctor' ),
					'collecting'     => __( 'Collecting browser metrics…', 'wp-performance-doctor' ),
					'browserStored'  => __( 'Browser metrics stored.', 'wp-performance-doctor' ),
					'noSession'      => __( 'No active profiling session. Run a profile first.', 'wp-performance-doctor' ),
					/* translators: 1: visible row count, 2: total row count. */
					'showing'        => __( 'Showing %1$d of %2$d', 'wp-performance-doctor' ),
					'details'        => __( 'Details', 'wp-performance-doctor' ),
					'hide'           => __( 'Hide', 'wp-performance-doctor' ),
				),
			)
		)
	);
	echo '</script>';
	?>

	<div id="wpd-performance-notice" class="wpd-notice wpd-notice--inline wpd-hidden" role="status" aria-live="polite"></div>

	<!-- ======================================================================
		A. Compact page header
		====================================================================== -->
	<header class="wpd-perf-header">
		<div class="wpd-perf-header__main">
			<div class="wpd-perf-eyebrow"><?php echo $icon( 'activity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php \esc_html_e( 'WordPress performance analytics', 'wp-performance-doctor' ); ?></span></div>
			<h1 class="wpd-perf-title"><?php \esc_html_e( 'Performance Profiling', 'wp-performance-doctor' ); ?></h1>
			<p class="wpd-perf-subtitle"><?php \esc_html_e( 'Measure server and browser performance, diagnose bottlenecks, and track changes between profiles.', 'wp-performance-doctor' ); ?></p>
		</div>
		<div class="wpd-perf-header__aside">
			<div class="wpd-perf-header__meta">
				<span class="wpd-perf-status wpd-perf-status--<?php echo \esc_attr( $enabled ? 'good' : 'neutral' ); ?>">
					<span class="wpd-perf-status__dot" aria-hidden="true"></span>
					<?php echo \esc_html( $enabled ? __( 'Monitoring enabled', 'wp-performance-doctor' ) : __( 'Monitoring disabled', 'wp-performance-doctor' ) ); ?>
				</span>
				<span class="wpd-perf-header__time">
					<?php \esc_html_e( 'Last profile', 'wp-performance-doctor' ); ?>
					<strong><?php echo \esc_html( $latest_ts ? MetricFormatter::format_date( $latest_ts ) : __( 'Not yet', 'wp-performance-doctor' ) ); ?></strong>
				</span>
			</div>
			<?php if ( $enabled ) : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium wpd-perf-run" id="wpd-run-profile">
				<span class="wpd-button-spinner"></span>
				<span class="wpd-button-label" id="wpd-run-profile-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php else : ?>
			<button type="button" class="wpd-button wpd-button--primary wpd-button--medium wpd-perf-run wpd-button--disabled" disabled title="<?php \esc_attr_e( 'Performance profiling is disabled. Enable it in Settings to start profiling.', 'wp-performance-doctor' ); ?>">
				<span class="wpd-button-label" id="wpd-run-profile-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
			</button>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( ! $enabled ) : ?>
	<div class="wpd-notice wpd-notice--info wpd-notice--inline">
		<p><?php \esc_html_e( 'Performance profiling is disabled. Enable it in Settings to start collecting performance data.', 'wp-performance-doctor' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- ======================================================================
		B. Performance summary KPIs
		====================================================================== -->
	<section class="wpd-perf-kpis<?php echo $frontend_status ? '' : ' wpd-perf-kpis--three'; ?>" aria-label="<?php \esc_attr_e( 'Performance summary', 'wp-performance-doctor' ); ?>">
		<div class="wpd-perf-kpi wpd-perf-kpi--<?php echo \esc_attr( $confirmed_count > 0 ? 'danger' : 'good' ); ?>">
			<div class="wpd-perf-kpi__top">
				<span class="wpd-perf-kpi__label"><?php \esc_html_e( 'Confirmed Bottlenecks', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-kpi__icon"><?php echo $icon( $confirmed_count > 0 ? 'alert' : 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<div class="wpd-perf-kpi__value"><?php echo \esc_html( (string) $confirmed_count ); ?></div>
			<div class="wpd-perf-kpi__meta">
				<?php
				printf(
					/* translators: 1: potential issue count, 2: informational count. */
					\esc_html__( '%1$d potential · %2$d informational', 'wp-performance-doctor' ),
					\esc_html( (string) $potential_count ),
					\esc_html( (string) $info_count )
				);
				?>
			</div>
		</div>

		<div class="wpd-perf-kpi wpd-perf-kpi--neutral">
			<div class="wpd-perf-kpi__top">
				<span class="wpd-perf-kpi__label"><?php \esc_html_e( 'Available Measurements', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-kpi__icon"><?php echo $icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<div class="wpd-perf-kpi__value"><?php echo \esc_html( (string) $available_count ); ?></div>
			<div class="wpd-perf-kpi__meta">
				<?php
				printf(
					/* translators: 1: total collected metric rows, 2: number of metric categories. */
					\esc_html__( 'of %1$d collected · %2$d categories', 'wp-performance-doctor' ),
					\esc_html( (string) $collected_count ),
					\esc_html( (string) $category_count )
				);
				?>
			</div>
		</div>

		<div class="wpd-perf-kpi wpd-perf-kpi--info">
			<div class="wpd-perf-kpi__top">
				<span class="wpd-perf-kpi__label"><?php \esc_html_e( 'Latest Profile Duration', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-kpi__icon"><?php echo $icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<div class="wpd-perf-kpi__value"><?php echo \esc_html( $format_seconds_display( $latest_duration ) ); ?></div>
			<div class="wpd-perf-kpi__meta"><?php echo \esc_html( $latest_ts ? MetricFormatter::format_date( $latest_ts ) : __( 'No profile recorded yet', 'wp-performance-doctor' ) ); ?></div>
		</div>

		<?php if ( $frontend_status ) : ?>
		<div class="wpd-perf-kpi wpd-perf-kpi--<?php echo \esc_attr( 'poor' === $frontend_status['rating'] ? 'danger' : ( 'needs_improvement' === $frontend_status['rating'] ? 'warning' : 'good' ) ); ?>">
			<div class="wpd-perf-kpi__top">
				<span class="wpd-perf-kpi__label"><?php \esc_html_e( 'Frontend Performance', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-kpi__icon"><?php echo $icon( 'monitor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<div class="wpd-perf-kpi__value wpd-perf-kpi__value--text"><?php echo \esc_html( $frontend_status['label'] ); ?></div>
			<div class="wpd-perf-kpi__meta">
				<?php
				if ( $frontend_status['measured'] < $frontend_status['total'] ) {
					echo \esc_html__( 'Partial data · ', 'wp-performance-doctor' );
				}
				printf(
					/* translators: 1: measured Core Web Vitals count, 2: total Core Web Vitals count. */
					\esc_html__( '%1$d of %2$d Core Web Vitals measured', 'wp-performance-doctor' ),
					\esc_html( (string) $frontend_status['measured'] ),
					\esc_html( (string) $frontend_status['total'] )
				);
				?>
			</div>
		</div>
		<?php endif; ?>
	</section>

	<!-- ======================================================================
		C. Profiling controls
		====================================================================== -->
	<section class="wpd-perf-controls" aria-label="<?php \esc_attr_e( 'Profiling controls', 'wp-performance-doctor' ); ?>">
		<article class="wpd-perf-card wpd-perf-profile">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--blue"><?php echo $icon( 'server' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title"><?php \esc_html_e( 'Server Profile', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'PHP runtime, database, cache, and system measurements for the profiling request.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php if ( $latest_session ) : ?>
				<span class="wpd-status-badge wpd-status--<?php echo \esc_attr( $latest_session['status'] ?? 'completed' ); ?>"><?php echo \esc_html( MetricFormatter::session_status_label( $latest_session['status'] ?? 'completed' ) ); ?></span>
				<?php else : ?>
				<span class="wpd-status-badge wpd-status--disabled"><?php \esc_html_e( 'No profile yet', 'wp-performance-doctor' ); ?></span>
				<?php endif; ?>
			</header>

			<div class="wpd-perf-profile__body">
				<dl class="wpd-perf-facts">
					<div class="wpd-perf-fact">
						<dt><?php \esc_html_e( 'What it measures', 'wp-performance-doctor' ); ?></dt>
						<dd><?php \esc_html_e( 'A single server-side request across PHP, WordPress, database, cache, and system collectors.', 'wp-performance-doctor' ); ?></dd>
					</div>
					<div class="wpd-perf-fact">
						<dt><?php \esc_html_e( 'Profiling target', 'wp-performance-doctor' ); ?></dt>
						<dd>
							<code title="<?php echo \esc_attr( $server_target ); ?>"><?php echo \esc_html( $server_target ); ?></code>
							<span class="wpd-perf-fact__hint"><?php echo \esc_html( $server_target_label ); ?></span>
						</dd>
					</div>
					<div class="wpd-perf-fact">
						<dt><?php \esc_html_e( 'Measurement context', 'wp-performance-doctor' ); ?></dt>
						<dd><?php echo \esc_html( MetricFormatter::request_context_label( $session_ctx ) ); ?><span class="wpd-perf-fact__hint"><?php \esc_html_e( 'Request that triggered the profile.', 'wp-performance-doctor' ); ?></span></dd>
					</div>
					<div class="wpd-perf-fact">
						<dt><?php \esc_html_e( 'Profiling mode', 'wp-performance-doctor' ); ?></dt>
						<dd><?php echo \esc_html( MetricFormatter::session_mode_label( $session_mode ) ); ?></dd>
					</div>
				</dl>
				<p class="wpd-perf-note"><?php \esc_html_e( 'A server profile measures the request that starts it (REST API when triggered here). It does not fetch a public frontend URL.', 'wp-performance-doctor' ); ?></p>
			</div>

			<footer class="wpd-perf-card__foot">
				<span class="wpd-perf-card__foot-meta">
					<?php if ( $latest_session && ! empty( $latest_session['duration'] ) ) : ?>
						<?php
						printf(
							/* translators: %s: profile duration. */
							\esc_html__( 'Last run %s', 'wp-performance-doctor' ),
							\esc_html( MetricFormatter::format_seconds( (float) $latest_session['duration'] ) )
						);
						?>
					<?php else : ?>
						<?php \esc_html_e( 'No completed profile recorded', 'wp-performance-doctor' ); ?>
					<?php endif; ?>
				</span>
				<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-server-profile" <?php echo $enabled ? '' : 'disabled title="' . \esc_attr__( 'Performance profiling is disabled in Settings.', 'wp-performance-doctor' ) . '"'; ?>>
					<span class="wpd-button-spinner"></span>
					<span class="wpd-button-label" id="wpd-run-server-profile-label"><?php \esc_html_e( 'Run Profile', 'wp-performance-doctor' ); ?></span>
				</button>
			</footer>
		</article>

		<article class="wpd-perf-card wpd-perf-browser">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--violet"><?php echo $icon( 'monitor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title"><?php \esc_html_e( 'Browser Metrics', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'Core Web Vitals and resource timings collected with native browser performance APIs.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php if ( $frontend_status ) : ?>
				<span class="wpd-perf-pill wpd-perf-pill--<?php echo \esc_attr( $frontend_status['slug'] ); ?>"><?php echo \esc_html( $frontend_status['label'] ); ?></span>
				<?php else : ?>
				<span class="wpd-perf-pill wpd-perf-pill--neutral"><?php \esc_html_e( 'Not measured', 'wp-performance-doctor' ); ?></span>
				<?php endif; ?>
			</header>

			<div class="wpd-perf-profile__body">
				<div class="wpd-perf-field">
					<label class="wpd-form-label" for="wpd-perf-target-url"><?php \esc_html_e( 'Target URL', 'wp-performance-doctor' ); ?></label>
					<input type="url" id="wpd-perf-target-url" class="wpd-form-input" value="<?php echo \esc_attr( $target_url ); ?>" placeholder="<?php \esc_attr_e( 'https://example.com/', 'wp-performance-doctor' ); ?>">
					<p class="wpd-form-hint"><?php \esc_html_e( 'The page URL associated with the browser measurements.', 'wp-performance-doctor' ); ?></p>
				</div>
				<div class="wpd-perf-field">
					<label class="wpd-form-label" for="wpd-perf-context"><?php \esc_html_e( 'Measurement context', 'wp-performance-doctor' ); ?></label>
					<select id="wpd-perf-context" class="wpd-form-input">
						<option value="admin"><?php \esc_html_e( 'Current admin page', 'wp-performance-doctor' ); ?></option>
						<option value="frontend"><?php \esc_html_e( 'Public frontend page', 'wp-performance-doctor' ); ?></option>
					</select>
					<p class="wpd-form-hint"><?php \esc_html_e( 'Metrics are collected from the page currently open in this tab. In admin context its URL is recorded automatically.', 'wp-performance-doctor' ); ?></p>
				</div>

				<div class="wpd-perf-coverage">
					<span class="wpd-perf-coverage__label"><?php \esc_html_e( 'Core Web Vitals coverage', 'wp-performance-doctor' ); ?></span>
					<div class="wpd-perf-coverage__items">
						<?php foreach ( $frontend_metrics as $fm ) : ?>
							<?php
							if ( empty( $fm['is_core_vital'] ) ) {
								continue;
							}
							?>
						<span class="wpd-perf-coverage__item wpd-perf-coverage__item--<?php echo \esc_attr( $fm['available'] ? 'measured' : 'missing' ); ?>">
							<strong><?php echo \esc_html( $fm['abbreviation'] ); ?></strong>
							<?php echo \esc_html( $fm['available'] ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Missing', 'wp-performance-doctor' ) ); ?>
						</span>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $frontend_context['collected_at'] ) ) : ?>
					<span class="wpd-perf-coverage__time"><?php echo \esc_html( sprintf( /* translators: %s: date/time. */ __( 'Last browser measurement: %s', 'wp-performance-doctor' ), MetricFormatter::format_date( $frontend_context['collected_at'] ) ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<footer class="wpd-perf-card__foot">
				<span class="wpd-perf-card__foot-meta">
					<?php echo \esc_html( $frontend_status ? __( 'Browser measurements available', 'wp-performance-doctor' ) : __( 'No browser measurements yet', 'wp-performance-doctor' ) ); ?>
				</span>
				<button type="button" class="wpd-button wpd-button--primary wpd-button--medium" id="wpd-run-frontend-profile">
					<span class="wpd-button-spinner"></span>
					<span class="wpd-button-label" id="wpd-run-frontend-profile-label"><?php \esc_html_e( 'Collect Browser Metrics', 'wp-performance-doctor' ); ?></span>
				</button>
			</footer>
			<input type="hidden" id="wpd-perf-session-id" value="<?php echo \esc_attr( $session_id ); ?>">
			<input type="hidden" id="wpd-perf-snapshot-id" value="<?php echo \esc_attr( $snapshot_id ); ?>">
			<div id="wpd-frontend-profile-notice" class="wpd-notice wpd-notice--inline wpd-hidden" role="status" aria-live="polite"></div>
		</article>
	</section>

	<!-- ======================================================================
		D. Bottlenecks & recommendations
		====================================================================== -->
	<section class="wpd-perf-section" aria-labelledby="wpd-perf-diagnostics-title">
		<div class="wpd-perf-section__head">
			<div>
				<h2 class="wpd-perf-section__title" id="wpd-perf-diagnostics-title"><?php \esc_html_e( 'Bottlenecks & Recommendations', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-perf-section__subtitle"><?php \esc_html_e( 'Findings from the latest profile, grouped by severity. Informational observations are not confirmed bottlenecks.', 'wp-performance-doctor' ); ?></p>
			</div>
			<?php if ( ! empty( $bottlenecks ) ) : ?>
			<div class="wpd-perf-summary-chips" aria-label="<?php \esc_attr_e( 'Finding summary', 'wp-performance-doctor' ); ?>">
				<span class="wpd-perf-chip wpd-perf-chip--danger"><strong><?php echo \esc_html( (string) $confirmed_count ); ?></strong> <?php \esc_html_e( 'Confirmed', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-chip wpd-perf-chip--warning"><strong><?php echo \esc_html( (string) $potential_count ); ?></strong> <?php \esc_html_e( 'Potential', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-chip wpd-perf-chip--neutral"><strong><?php echo \esc_html( (string) $info_count ); ?></strong> <?php \esc_html_e( 'Informational', 'wp-performance-doctor' ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<div class="wpd-perf-card wpd-perf-diagnostics">
			<?php if ( empty( $bottlenecks ) ) : ?>
				<div class="wpd-perf-empty wpd-perf-empty--good">
					<span class="wpd-perf-empty__icon"><?php echo $icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php echo \esc_html( $has_data ? __( 'No performance bottlenecks detected', 'wp-performance-doctor' ) : __( 'No profile measurements yet', 'wp-performance-doctor' ) ); ?></h3>
						<p>
							<?php
							echo \esc_html(
								$has_data
									? __( 'The latest profile did not identify any confirmed bottlenecks. Run another profile after changes to verify the result.', 'wp-performance-doctor' )
									: __( 'Run a server profile to collect measurements and evaluate bottlenecks.', 'wp-performance-doctor' )
							);
							?>
						</p>
					</div>
				</div>
			<?php else : ?>
				<?php if ( 0 === $confirmed_count ) : ?>
				<div class="wpd-perf-empty wpd-perf-empty--good wpd-perf-empty--inline">
					<span class="wpd-perf-empty__icon"><?php echo $icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php \esc_html_e( 'No confirmed bottlenecks', 'wp-performance-doctor' ); ?></h3>
						<p><?php \esc_html_e( 'No critical or high-severity issues were confirmed by the latest measurements. Review the lower-severity observations below.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php endif; ?>

				<?php foreach ( $bottleneck_groups as $severity => $findings ) : ?>
				<div class="wpd-perf-severity-group">
					<div class="wpd-perf-severity-group__head">
						<span class="wpd-perf-severity-dot wpd-perf-severity-dot--<?php echo \esc_attr( $severity ); ?>"></span>
						<h3><?php echo \esc_html( $severity_labels[ $severity ] ?? Severity::label( $severity ) ); ?></h3>
						<span class="wpd-perf-severity-group__count"><?php echo \esc_html( (string) \count( $findings ) ); ?></span>
						<span class="wpd-perf-severity-group__help"><?php echo \esc_html( $severity_help[ $severity ] ?? '' ); ?></span>
					</div>

					<?php foreach ( $findings as $bottleneck ) : ?>
						<?php
						$b_severity  = $bottleneck['severity'] ?? 'info';
						$b_conf      = $bottleneck['confidence'] ?? 'low';
						$evidence    = $bottleneck['evidence'] ?? array();
						$remediation = $bottleneck['remediation'] ?? array();
						$steps       = $remediation['steps'] ?? array();
						$risk        = $remediation['risk'] ?? 'low';
						$difficulty  = $remediation['difficulty'] ?? 'medium';
						$summary_ev  = $bottleneck['evidence_summary'] ?? array();
						?>
					<details class="wpd-finding wpd-finding--<?php echo \esc_attr( $b_severity ); ?>">
						<summary class="wpd-finding__summary">
							<span class="wpd-finding__severity"><?php echo \esc_html( $bottleneck['severity_label'] ?? $b_severity ); ?></span>
							<span class="wpd-finding__main">
								<span class="wpd-finding__title"><?php echo \esc_html( $bottleneck['title'] ?? '' ); ?></span>
								<span class="wpd-finding__desc"><?php echo \esc_html( $bottleneck['description'] ?? '' ); ?></span>
								<?php if ( ! empty( $summary_ev ) ) : ?>
								<span class="wpd-finding__values"><?php echo \esc_html( \implode( ' · ', $summary_ev ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="wpd-finding__meta">
								<span class="wpd-finding__confidence"><?php echo \esc_html( $bottleneck['confidence_label'] ?? \ucfirst( $b_conf ) ); ?> <?php \esc_html_e( 'confidence', 'wp-performance-doctor' ); ?></span>
								<span class="wpd-finding__toggle"><?php \esc_html_e( 'View details', 'wp-performance-doctor' ); ?><?php echo $icon( 'chevron', 'wpd-finding__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							</span>
						</summary>
						<div class="wpd-finding__body">
							<?php if ( ! empty( $evidence ) ) : ?>
							<div class="wpd-finding__evidence">
								<span class="wpd-finding__body-label"><?php \esc_html_e( 'Measured values', 'wp-performance-doctor' ); ?></span>
								<dl>
									<?php foreach ( $evidence as $ev ) : ?>
										<?php
										if ( empty( $ev['display'] ) ) {
											continue;
										}
										?>
									<div>
										<dt><?php echo \esc_html( $ev['label'] ?? '' ); ?></dt>
										<dd><?php echo \esc_html( $ev['display'] ); ?></dd>
									</div>
									<?php endforeach; ?>
								</dl>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $bottleneck['impact'] ) ) : ?>
							<div class="wpd-finding__text">
								<span class="wpd-finding__body-label"><?php \esc_html_e( 'Why it matters', 'wp-performance-doctor' ); ?></span>
								<p><?php echo \esc_html( $bottleneck['impact'] ); ?></p>
							</div>
							<?php endif; ?>

							<?php if ( ! empty( $bottleneck['limitations'] ) ) : ?>
							<p class="wpd-finding__limitation"><strong><?php \esc_html_e( 'Limitation:', 'wp-performance-doctor' ); ?></strong> <?php echo \esc_html( $bottleneck['limitations'] ); ?></p>
							<?php endif; ?>

							<div class="wpd-finding__actions">
								<?php if ( ! empty( $steps ) ) : ?>
								<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-bottleneck-steps" data-steps="<?php echo \esc_attr( \wp_json_encode( $steps ) ); ?>">
									<?php \esc_html_e( 'View Steps', 'wp-performance-doctor' ); ?>
								</button>
								<?php endif; ?>
								<span class="wpd-finding__risk">
									<span class="wpd-badge wpd-badge--outline wpd-badge--small"><?php echo \esc_html( \ucfirst( $risk ) ); ?> <?php \esc_html_e( 'risk', 'wp-performance-doctor' ); ?></span>
									<span class="wpd-badge wpd-badge--outline wpd-badge--small"><?php echo \esc_html( \ucfirst( $difficulty ) ); ?></span>
								</span>
							</div>
						</div>
					</details>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</section>

	<!-- ======================================================================
		E + F. Frontend performance and resource analysis
		====================================================================== -->
	<div class="wpd-perf-columns wpd-perf-columns--2-1">
		<section class="wpd-perf-card" aria-labelledby="wpd-perf-frontend-title">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--violet"><?php echo $icon( 'monitor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title" id="wpd-perf-frontend-title"><?php \esc_html_e( 'Frontend Performance', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'Core Web Vitals and navigation timings collected in the browser.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php if ( $frontend_status ) : ?>
				<span class="wpd-perf-pill wpd-perf-pill--<?php echo \esc_attr( $frontend_status['slug'] ); ?>"><?php echo \esc_html( $frontend_status['label'] ); ?></span>
				<?php endif; ?>
			</header>

			<div class="wpd-perf-card__body">
				<div class="wpd-perf-context">
					<span><?php \esc_html_e( 'Measured page', 'wp-performance-doctor' ); ?> <code><?php echo \esc_html( $frontend_context['page_url'] ?? $target_url ); ?></code></span>
					<span><?php \esc_html_e( 'Context', 'wp-performance-doctor' ); ?> <strong><?php echo \esc_html( $frontend_context['context_label'] ?? MetricFormatter::request_context_label( $session_ctx ) ); ?></strong></span>
				</div>

				<?php if ( ! $frontend_measured ) : ?>
				<div class="wpd-perf-empty wpd-perf-empty--compact">
					<span class="wpd-perf-empty__icon"><?php echo $icon( 'monitor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php \esc_html_e( 'No frontend measurements yet', 'wp-performance-doctor' ); ?></h3>
						<p><?php \esc_html_e( 'Collect browser metrics to see TTFB, LCP, CLS, INP, and navigation timings.', 'wp-performance-doctor' ); ?></p>
						<button type="button" class="wpd-button wpd-button--primary wpd-button--small" id="wpd-run-frontend-profile-empty">
							<span class="wpd-button-spinner"></span>
							<span class="wpd-button-label" id="wpd-run-frontend-profile-label-empty"><?php \esc_html_e( 'Collect Browser Metrics', 'wp-performance-doctor' ); ?></span>
						</button>
						<input type="hidden" id="wpd-perf-session-id-empty" value="<?php echo \esc_attr( $session_id ); ?>">
						<input type="hidden" id="wpd-perf-snapshot-id-empty" value="<?php echo \esc_attr( $snapshot_id ); ?>">
					</div>
				</div>
				<?php else : ?>
				<div class="wpd-perf-vitals">
					<?php foreach ( $frontend_metrics as $fm ) : ?>
						<?php
						if ( empty( $fm['is_core_vital'] ) ) {
							continue;
						}
						?>
					<div class="wpd-perf-vital wpd-perf-vital--<?php echo \esc_attr( $fm['rating_slug'] ); ?>">
						<div class="wpd-perf-vital__top">
							<span class="wpd-perf-vital__abbr" title="<?php echo \esc_attr( $fm['label'] ); ?>"><?php echo \esc_html( $fm['abbreviation'] ); ?></span>
							<span class="wpd-perf-pill wpd-perf-pill--<?php echo \esc_attr( $fm['rating_slug'] ); ?>"><?php echo \esc_html( $fm['rating_label'] ); ?></span>
						</div>
						<div class="wpd-perf-vital__value">
							<?php if ( $fm['available'] ) : ?>
								<?php echo \esc_html( $fm['value_display'] ); ?>
								<?php if ( '' !== $fm['unit_display'] ) : ?>
									<span class="wpd-perf-vital__unit"><?php echo \esc_html( $fm['unit_display'] ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpd-perf-vital__none"><?php \esc_html_e( 'Not measured', 'wp-performance-doctor' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wpd-perf-vital__name"><?php echo \esc_html( $fm['label'] ); ?></div>
						<?php if ( $fm['is_core_vital'] ) : ?>
						<div class="wpd-perf-vital__bar" aria-hidden="true">
							<span style="width: <?php echo \esc_attr( (string) ( $fm['progress'] ?? 0 ) ); ?>%"></span>
						</div>
						<div class="wpd-perf-vital__threshold"><?php echo \esc_html( $fm['threshold_label'] ); ?></div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="wpd-perf-navtimings">
					<?php foreach ( $frontend_metrics as $fm ) : ?>
						<?php
						if ( ! empty( $fm['is_core_vital'] ) ) {
							continue;
						}
						?>
					<div class="wpd-perf-navtiming">
						<span class="wpd-perf-navtiming__abbr"><?php echo \esc_html( $fm['abbreviation'] ); ?></span>
						<span class="wpd-perf-navtiming__name"><?php echo \esc_html( $fm['label'] ); ?></span>
						<span class="wpd-perf-navtiming__value">
							<?php if ( $fm['available'] ) : ?>
								<?php echo \esc_html( $fm['value_display'] ); ?>
								<?php if ( '' !== $fm['unit_display'] ) : ?>
									<span class="wpd-perf-navtiming__unit"><?php echo \esc_html( $fm['unit_display'] ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpd-perf-muted"><?php \esc_html_e( 'Not measured', 'wp-performance-doctor' ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="wpd-perf-card" aria-labelledby="wpd-perf-resource-title">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--teal"><?php echo $icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title" id="wpd-perf-resource-title"><?php \esc_html_e( 'Resource Analysis', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'Frontend resources observed by the browser.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
			</header>

			<div class="wpd-perf-card__body">
				<?php if ( ! $resource_measured ) : ?>
				<div class="wpd-perf-empty wpd-perf-empty--compact">
					<span class="wpd-perf-empty__icon"><?php echo $icon( 'layers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php \esc_html_e( 'Resource timings not collected', 'wp-performance-doctor' ); ?></h3>
						<p><?php \esc_html_e( 'Collect browser metrics to analyze JavaScript, CSS, images, fonts, transfer size, and the slowest resource.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php else : ?>
				<div class="wpd-perf-resource-total">
					<span class="wpd-perf-resource-total__label"><?php \esc_html_e( 'Total resources', 'wp-performance-doctor' ); ?></span>
					<span class="wpd-perf-resource-total__value">
						<?php echo \esc_html( $total_resources && $total_resources['available'] ? $total_resources['value_display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?>
					</span>
				</div>

				<div class="wpd-perf-resource-list">
					<?php foreach ( $resource_dist as $row ) : ?>
					<div class="wpd-perf-resource-row">
						<span class="wpd-perf-resource-row__label"><?php echo \esc_html( $row['label'] ); ?></span>
						<span class="wpd-perf-resource-row__value"><?php echo \esc_html( null !== $row['value'] ? (string) $row['value'] : __( '—', 'wp-performance-doctor' ) ); ?></span>
						<span class="wpd-perf-resource-row__bar" aria-hidden="true">
							<span class="wpd-perf-resource-row__fill wpd-perf-resource-row__fill--<?php echo \esc_attr( $row['slug'] ); ?>" style="width: <?php echo \esc_attr( (string) ( $row['share'] ?? 0 ) ); ?>%"></span>
						</span>
						<span class="wpd-perf-resource-row__share"><?php echo \esc_html( null !== $row['share'] ? $row['share'] . '%' : '—' ); ?></span>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="wpd-perf-resource-foot">
					<div>
						<span class="wpd-perf-resource-foot__label"><?php \esc_html_e( 'External resources', 'wp-performance-doctor' ); ?></span>
						<span class="wpd-perf-resource-foot__value"><?php echo \esc_html( $external_count && $external_count['available'] ? $external_count['value_display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
					</div>
					<div>
						<span class="wpd-perf-resource-foot__label"><?php \esc_html_e( 'Transfer size', 'wp-performance-doctor' ); ?></span>
						<span class="wpd-perf-resource-foot__value"><?php echo \esc_html( $transfer_size && $transfer_size['available'] ? $transfer_size['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
					</div>
					<div>
						<span class="wpd-perf-resource-foot__label"><?php \esc_html_e( 'Slowest resource', 'wp-performance-doctor' ); ?></span>
						<span class="wpd-perf-resource-foot__value"><?php echo \esc_html( $slowest_load && $slowest_load['available'] ? $slowest_load['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<!-- ======================================================================
		G + H. Database profiling and collected metrics
		====================================================================== -->
	<div class="wpd-perf-columns wpd-perf-columns--1-2">
		<section class="wpd-perf-card" aria-labelledby="wpd-perf-db-title">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--amber"><?php echo $icon( 'database' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title" id="wpd-perf-db-title"><?php \esc_html_e( 'Database Profiling', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'Query metrics depend on the SAVEQUERIES constant.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<span class="wpd-perf-pill wpd-perf-pill--<?php echo \esc_attr( $db_active ? 'good' : 'neutral' ); ?>"><?php echo \esc_html( $database_metrics['state_label'] ?? __( 'Not detected', 'wp-performance-doctor' ) ); ?></span>
			</header>

			<div class="wpd-perf-card__body">
				<?php if ( ! $db_active ) : ?>
				<div class="wpd-perf-inactive">
					<span class="wpd-perf-inactive__icon"><?php echo $icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php \esc_html_e( 'Detailed query profiling is inactive', 'wp-performance-doctor' ); ?></h3>
						<p><?php \esc_html_e( 'SAVEQUERIES is disabled or was not recorded in this profile. Query count, total query time, and slow query metrics were not collected; they are not zero.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>

				<details class="wpd-inline-details">
					<summary><?php \esc_html_e( 'How to enable SAVEQUERIES temporarily', 'wp-performance-doctor' ); ?><?php echo $icon( 'chevron', 'wpd-inline-details__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
					<div class="wpd-inline-details__body">
						<ol class="wpd-list wpd-list--decimal">
							<li><?php \esc_html_e( 'Open the site’s wp-config.php file.', 'wp-performance-doctor' ); ?></li>
							<li><?php \esc_html_e( 'Add or update this line before the “That’s all, stop editing!” comment:', 'wp-performance-doctor' ); ?> <code>define( 'SAVEQUERIES', true );</code></li>
							<li><?php \esc_html_e( 'Reload this page and run a new profile to capture query metrics.', 'wp-performance-doctor' ); ?></li>
							<li><?php \esc_html_e( 'Use SAVEQUERIES only temporarily; it adds memory and processing overhead.', 'wp-performance-doctor' ); ?></li>
						</ol>
						<p class="wpd-perf-note"><?php \esc_html_e( 'The plugin only stores sanitized query fingerprints, never raw SQL containing personal data or credentials.', 'wp-performance-doctor' ); ?></p>
					</div>
				</details>
				<?php endif; ?>

				<?php
				$db_context = array(
					array(
						'label'  => __( 'Database size', 'wp-performance-doctor' ),
						'metric' => $metrics_by_id['database.database_size'] ?? null,
					),
					array(
						'label'  => __( 'Tables', 'wp-performance-doctor' ),
						'metric' => $metrics_by_id['database.table_count'] ?? null,
					),
					array(
						'label'  => __( 'Autoloaded options', 'wp-performance-doctor' ),
						'metric' => $metrics_by_id['database.autoloaded_options_size'] ?? null,
					),
				);
				$db_context = array_filter(
					$db_context,
					static function ( $row ) {
						return is_array( $row['metric'] ) && 'available' === ( $row['metric']['availability_status'] ?? '' );
					}
				);
				?>
				<?php if ( ! empty( $db_context ) ) : ?>
				<div class="wpd-perf-db-context">
					<span class="wpd-perf-db-context__label"><?php \esc_html_e( 'Database context', 'wp-performance-doctor' ); ?></span>
					<div class="wpd-perf-db-context__items">
						<?php foreach ( $db_context as $row ) : ?>
						<span class="wpd-perf-db-context__item"><span><?php echo \esc_html( $row['label'] ); ?></span><strong><?php echo \esc_html( MetricFormatter::format( $row['metric'] ) ); ?></strong></span>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( $db_active ) : ?>
					<?php if ( ! $database_metrics['has_query_metrics'] ) : ?>
					<div class="wpd-perf-empty wpd-perf-empty--compact">
						<span class="wpd-perf-empty__icon"><?php echo $icon( 'database' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<h3><?php \esc_html_e( 'SAVEQUERIES is enabled but no query metrics were stored', 'wp-performance-doctor' ); ?></h3>
							<p><?php \esc_html_e( 'Run a new profile to collect query counts and timings.', 'wp-performance-doctor' ); ?></p>
						</div>
					</div>
					<?php else : ?>
					<div class="wpd-perf-db-stats">
						<div class="wpd-perf-db-stat">
							<span class="wpd-perf-db-stat__label"><?php \esc_html_e( 'Query count', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-perf-db-stat__value"><?php echo \esc_html( $database_metrics['query_count']['available'] ? $database_metrics['query_count']['value_display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
						</div>
						<div class="wpd-perf-db-stat">
							<span class="wpd-perf-db-stat__label"><?php \esc_html_e( 'Total query time', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-perf-db-stat__value"><?php echo \esc_html( $database_metrics['query_time']['available'] ? $database_metrics['query_time']['display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
						</div>
						<div class="wpd-perf-db-stat <?php echo ( $database_metrics['slow_count']['available'] && (float) $database_metrics['slow_count']['value'] > 0 ) ? 'wpd-perf-db-stat--warning' : ''; ?>">
							<span class="wpd-perf-db-stat__label"><?php \esc_html_e( 'Slow queries', 'wp-performance-doctor' ); ?></span>
							<span class="wpd-perf-db-stat__value"><?php echo \esc_html( $database_metrics['slow_count']['available'] ? $database_metrics['slow_count']['value_display'] : __( 'Not measured', 'wp-performance-doctor' ) ); ?></span>
							<?php if ( $database_metrics['slow_count']['available'] && (float) $database_metrics['slow_count']['value'] > 0 ) : ?>
							<span class="wpd-perf-db-stat__hint"><?php \esc_html_e( 'Above the 0.5 s threshold', 'wp-performance-doctor' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>

					<?php if ( ! empty( $db_types ) ) : ?>
					<div class="wpd-perf-db-types">
						<span class="wpd-finding__body-label"><?php \esc_html_e( 'Query type distribution', 'wp-performance-doctor' ); ?></span>
						<?php foreach ( $db_types as $query_type ) : ?>
						<div class="wpd-perf-resource-row">
							<span class="wpd-perf-resource-row__label"><?php echo \esc_html( $query_type['type'] ); ?></span>
							<span class="wpd-perf-resource-row__value"><?php echo \esc_html( (string) $query_type['value'] ); ?></span>
							<span class="wpd-perf-resource-row__bar" aria-hidden="true"><span class="wpd-perf-resource-row__fill wpd-perf-resource-row__fill--js" style="width: <?php echo \esc_attr( (string) $query_type['share'] ); ?>%"></span></span>
							<span class="wpd-perf-resource-row__share"><?php echo \esc_html( $query_type['share'] . '%' ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</section>

		<section class="wpd-perf-card" aria-labelledby="wpd-perf-metrics-title">
			<header class="wpd-perf-card__head">
				<div class="wpd-perf-card__title-wrap">
					<span class="wpd-perf-card__icon wpd-perf-card__icon--neutral"><?php echo $icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="wpd-perf-card__title" id="wpd-perf-metrics-title"><?php \esc_html_e( 'Collected Metrics', 'wp-performance-doctor' ); ?></h2>
						<p class="wpd-perf-card__subtitle"><?php \esc_html_e( 'Every measurement from this profile, grouped by category.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<span class="wpd-perf-pill wpd-perf-pill--neutral"><?php echo \esc_html( (string) $collected_count ); ?> <?php \esc_html_e( 'metrics', 'wp-performance-doctor' ); ?></span>
			</header>

			<div class="wpd-perf-card__body wpd-perf-card__body--flush">
				<?php if ( empty( $grouped_metrics ) ) : ?>
				<div class="wpd-perf-empty wpd-perf-empty--compact">
					<span class="wpd-perf-empty__icon"><?php echo $icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h3><?php \esc_html_e( 'No metrics collected yet', 'wp-performance-doctor' ); ?></h3>
						<p><?php \esc_html_e( 'Run a profile to collect measurements.', 'wp-performance-doctor' ); ?></p>
					</div>
				</div>
				<?php else : ?>
					<?php foreach ( $grouped_metrics as $category => $category_metrics ) : ?>
					<details class="wpd-metric-cat">
						<summary class="wpd-metric-cat__summary">
							<span class="wpd-metric-cat__icon">
								<?php echo $icon( $category_icons[ $category ] ?? 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
							<span class="wpd-metric-cat__name"><?php echo \esc_html( $category_labels[ $category ] ?? \ucfirst( $category ) ); ?></span>
							<span class="wpd-metric-cat__count"><?php echo \esc_html( (string) \count( $category_metrics ) ); ?></span>
							<?php echo $icon( 'chevron', 'wpd-metric-cat__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</summary>
						<div class="wpd-metric-cat__body">
							<div class="wpd-metric-list">
								<?php foreach ( $category_metrics as $metric ) : ?>
									<?php
									$disp = MetricFormatter::display_data( $metric );
									?>
								<div class="wpd-metric-row">
									<span class="wpd-metric-row__label"><?php echo \esc_html( $disp['label'] ); ?></span>
									<span class="wpd-metric-row__value"><?php echo \esc_html( $disp['display'] ); ?></span>
									<span class="wpd-metric-row__status wpd-perf-muted"><?php echo \esc_html( $disp['status_label'] ); ?></span>
								</div>
								<?php endforeach; ?>
							</div>
							<details class="wpd-inline-details wpd-inline-details--technical">
								<summary><?php \esc_html_e( 'Technical identifiers', 'wp-performance-doctor' ); ?><?php echo $icon( 'chevron', 'wpd-inline-details__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></summary>
								<div class="wpd-inline-details__body">
									<table class="wpd-table wpd-table--compact">
										<thead>
											<tr>
												<th><?php \esc_html_e( 'Identifier', 'wp-performance-doctor' ); ?></th>
												<th class="wpd-table-cell-numeric"><?php \esc_html_e( 'Value', 'wp-performance-doctor' ); ?></th>
												<th><?php \esc_html_e( 'Unit', 'wp-performance-doctor' ); ?></th>
												<th><?php \esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
											</tr>
										</thead>
										<tbody>
										<?php foreach ( $category_metrics as $metric ) : ?>
											<?php
											$disp   = MetricFormatter::display_data( $metric );
											$raw_id = $metric['metric_identifier'] ?? '';
											?>
											<tr>
												<td class="wpd-text--mono wpd-text--small" data-label="<?php \esc_attr_e( 'Identifier', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $metric['logical_identifier'] ?? $raw_id ); ?></td>
												<td class="wpd-table-cell-numeric wpd-text--mono wpd-text--small" data-label="<?php \esc_attr_e( 'Value', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $disp['display'] ); ?></td>
												<td class="wpd-text--small" data-label="<?php \esc_attr_e( 'Unit', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $disp['unit'] ? $disp['unit'] : '—' ); ?></td>
												<td class="wpd-text--small" data-label="<?php \esc_attr_e( 'Status', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $disp['status_label'] ); ?></td>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</details>
						</div>
					</details>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>
	</div>

	<!-- ======================================================================
		I. Historical comparison
		====================================================================== -->
	<section class="wpd-perf-section" aria-labelledby="wpd-perf-compare-title">
		<div class="wpd-perf-section__head">
			<div>
				<h2 class="wpd-perf-section__title" id="wpd-perf-compare-title"><?php \esc_html_e( 'Historical Comparison', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-perf-section__subtitle"><?php \esc_html_e( 'Compares compatible measurements between this profile and the most recent older snapshot.', 'wp-performance-doctor' ); ?></p>
			</div>
			<?php if ( $has_comparison ) : ?>
			<div class="wpd-perf-summary-chips" aria-label="<?php \esc_attr_e( 'Comparison summary', 'wp-performance-doctor' ); ?>">
				<span class="wpd-perf-chip wpd-perf-chip--good"><strong><?php echo \esc_html( (string) $comparison_summary['improved'] ); ?></strong> <?php \esc_html_e( 'Improved', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-chip wpd-perf-chip--danger"><strong><?php echo \esc_html( (string) $comparison_summary['worsened'] ); ?></strong> <?php \esc_html_e( 'Worsened', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-chip wpd-perf-chip--neutral"><strong><?php echo \esc_html( (string) $comparison_summary['unchanged'] ); ?></strong> <?php \esc_html_e( 'Unchanged', 'wp-performance-doctor' ); ?></span>
				<span class="wpd-perf-chip wpd-perf-chip--neutral"><strong><?php echo \esc_html( (string) $comparison_summary['not_comparable'] ); ?></strong> <?php \esc_html_e( 'Not comparable', 'wp-performance-doctor' ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<div class="wpd-perf-card">
			<?php if ( ! $has_comparison ) : ?>
			<div class="wpd-perf-empty wpd-perf-empty--compact">
				<span class="wpd-perf-empty__icon"><?php echo $icon( 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h3><?php \esc_html_e( 'No comparable snapshot available', 'wp-performance-doctor' ); ?></h3>
					<p><?php \esc_html_e( 'A comparison requires at least two snapshots with matching measurement contexts and compatible units.', 'wp-performance-doctor' ); ?></p>
				</div>
			</div>
			<?php else : ?>
			<div class="wpd-perf-card__body">
				<div class="wpd-perf-snapshots">
					<div class="wpd-perf-snapshot wpd-perf-snapshot--current">
						<span class="wpd-perf-snapshot__label"><?php \esc_html_e( 'Current snapshot', 'wp-performance-doctor' ); ?></span>
						<strong><?php echo \esc_html( MetricFormatter::format_date( $latest_ts ) ); ?></strong>
						<span class="wpd-perf-snapshot__meta">
							<?php
							printf(
								/* translators: 1: session id, 2: snapshot id. */
								\esc_html__( 'Session #%1$s · Snapshot #%2$s', 'wp-performance-doctor' ),
								\esc_html( (string) $session_id ),
								\esc_html( (string) $snapshot_id )
							);
							?>
						</span>
					</div>
					<span class="wpd-perf-snapshots__arrow" aria-hidden="true"><?php echo $icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="wpd-perf-snapshot">
						<span class="wpd-perf-snapshot__label"><?php \esc_html_e( 'Previous snapshot', 'wp-performance-doctor' ); ?></span>
						<strong><?php echo \esc_html( ! empty( $previous_snapshot['timestamp'] ) ? MetricFormatter::format_date( $previous_snapshot['timestamp'] ) : __( 'Unknown', 'wp-performance-doctor' ) ); ?></strong>
						<span class="wpd-perf-snapshot__meta">
							<?php
							printf(
								/* translators: %s: snapshot id. */
								\esc_html__( 'Snapshot #%s', 'wp-performance-doctor' ),
								\esc_html( (string) ( $previous_snapshot['id'] ?? '—' ) )
							);
							?>
						</span>
					</div>
				</div>

				<div class="wpd-perf-toolbar" data-wpd-comparison>
					<label class="wpd-perf-search" for="wpd-comp-search">
						<?php echo $icon( 'search', 'wpd-perf-search__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="screen-reader-text"><?php \esc_html_e( 'Search metrics', 'wp-performance-doctor' ); ?></span>
						<input type="search" id="wpd-comp-search" placeholder="<?php \esc_attr_e( 'Search metrics…', 'wp-performance-doctor' ); ?>" autocomplete="off">
					</label>
					<label class="wpd-perf-select">
						<span class="screen-reader-text"><?php \esc_html_e( 'Filter by category', 'wp-performance-doctor' ); ?></span>
						<select id="wpd-comp-category">
							<option value=""><?php \esc_html_e( 'All categories', 'wp-performance-doctor' ); ?></option>
							<?php foreach ( $comparison_categories as $slug => $label ) : ?>
							<option value="<?php echo \esc_attr( $slug ); ?>"><?php echo \esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="wpd-perf-checkbox">
						<input type="checkbox" id="wpd-comp-changes-only">
						<span><?php \esc_html_e( 'Changes only', 'wp-performance-doctor' ); ?></span>
					</label>
					<span class="wpd-perf-toolbar__count" id="wpd-comp-count" aria-live="polite">
						<?php
						printf(
							/* translators: 1: visible count, 2: total count. */
							\esc_html__( 'Showing %1$d of %2$d', 'wp-performance-doctor' ),
							\esc_html( (string) $comparison_summary['total'] ),
							\esc_html( (string) $comparison_summary['total'] )
						);
						?>
					</span>
				</div>

				<div class="wpd-perf-table-scroll wpd-perf-table-scroll--limited">
					<table class="wpd-table wpd-perf-compare-table" id="wpd-comp-table">
						<thead>
							<tr>
								<th scope="col"><?php \esc_html_e( 'Metric', 'wp-performance-doctor' ); ?></th>
								<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( 'Previous', 'wp-performance-doctor' ); ?></th>
								<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( 'Current', 'wp-performance-doctor' ); ?></th>
								<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( 'Change', 'wp-performance-doctor' ); ?></th>
								<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( '% change', 'wp-performance-doctor' ); ?></th>
								<th scope="col"><?php \esc_html_e( 'Interpretation', 'wp-performance-doctor' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpd-comp-body">
						<?php foreach ( $comparisons as $comp ) : ?>
							<tr class="wpd-compare-row wpd-compare-row--<?php echo \esc_attr( $comp['interpretation'] ); ?>" data-category="<?php echo \esc_attr( $comp['category'] ); ?>" data-search="<?php echo \esc_attr( $comp['search'] ); ?>" data-change="<?php echo $comp['is_change'] ? '1' : '0'; ?>">
								<td data-label="<?php \esc_attr_e( 'Metric', 'wp-performance-doctor' ); ?>">
									<span class="wpd-compare-metric"><?php echo \esc_html( $comp['label'] ); ?></span>
									<span class="wpd-compare-category"><?php echo \esc_html( $comp['category_label'] ); ?></span>
								</td>
								<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( 'Previous', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $comp['previous_display'] ); ?></td>
								<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( 'Current', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $comp['current_display'] ); ?></td>
								<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( 'Change', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $comp['difference_display'] ); ?></td>
								<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( '% change', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $comp['percent_display'] ); ?></td>
								<td data-label="<?php \esc_attr_e( 'Interpretation', 'wp-performance-doctor' ); ?>">
									<span class="wpd-perf-pill wpd-perf-pill--<?php echo \esc_attr( 'needs_improvement' === $comp['interpretation'] ? 'needs-improvement' : str_replace( '_', '-', $comp['interpretation'] ) ); ?>"<?php echo $comp['interpretation_note'] ? ' title="' . \esc_attr( $comp['interpretation_note'] ) . '"' : ''; ?>><?php echo \esc_html( $comp['interpretation_label'] ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="wpd-perf-empty wpd-perf-empty--compact" id="wpd-comp-empty" hidden>
						<div>
							<h3><?php \esc_html_e( 'No matching measurements', 'wp-performance-doctor' ); ?></h3>
							<p><?php \esc_html_e( 'No rows match the current search and filters. Try a different category or disable “Changes only”.', 'wp-performance-doctor' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- ======================================================================
		J. Profiling session history
		====================================================================== -->
	<section class="wpd-perf-section" aria-labelledby="wpd-perf-sessions-title">
		<div class="wpd-perf-section__head">
			<div>
				<h2 class="wpd-perf-section__title" id="wpd-perf-sessions-title"><?php \esc_html_e( 'Profiling Session History', 'wp-performance-doctor' ); ?></h2>
				<p class="wpd-perf-section__subtitle"><?php \esc_html_e( 'Recorded profiling sessions. Select Details to inspect a session’s metadata.', 'wp-performance-doctor' ); ?></p>
			</div>
			<?php if ( $sessions_total > 0 ) : ?>
			<span class="wpd-perf-pill wpd-perf-pill--neutral"><?php echo \esc_html( (string) $sessions_total ); ?> <?php \esc_html_e( 'sessions', 'wp-performance-doctor' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wpd-perf-card">
			<?php if ( empty( $sessions ) ) : ?>
			<div class="wpd-perf-empty wpd-perf-empty--compact">
				<span class="wpd-perf-empty__icon"><?php echo $icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h3><?php \esc_html_e( 'No profiling sessions recorded yet', 'wp-performance-doctor' ); ?></h3>
					<p><?php \esc_html_e( 'Run a profile to record the first session.', 'wp-performance-doctor' ); ?></p>
				</div>
			</div>
			<?php else : ?>
			<div class="wpd-perf-table-scroll">
				<table class="wpd-table wpd-perf-sessions-table">
					<thead>
						<tr>
							<th scope="col"><?php \esc_html_e( 'Session', 'wp-performance-doctor' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Date and time', 'wp-performance-doctor' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Target', 'wp-performance-doctor' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Context', 'wp-performance-doctor' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Status', 'wp-performance-doctor' ); ?></th>
							<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( 'Duration', 'wp-performance-doctor' ); ?></th>
							<th scope="col" class="wpd-table-cell-numeric"><?php \esc_html_e( 'Measurements', 'wp-performance-doctor' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php \esc_html_e( 'Action', 'wp-performance-doctor' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $sessions as $s_index => $session ) : ?>
						<?php
						$s_status       = $session['status'] ?? 'completed';
						$s_ctx          = $session['request_context'] ?? 'admin';
						$s_id           = (int) ( $session['id'] ?? 0 );
						$s_is_latest    = 0 === $s_index;
						$s_target       = $session['target_url'] ?? '';
						$s_measurements = (int) ( $session['stored_metrics_count'] ?? $session['metrics_count'] ?? 0 );
						$s_target_text  = $s_target
							? $format_target( $s_target )
							: ( \in_array( $s_ctx, array( 'admin', 'rest', 'ajax' ), true ) ? __( 'Current request', 'wp-performance-doctor' ) : __( 'Not recorded', 'wp-performance-doctor' ) );
						$s_details_id   = 'wpd-session-details-' . $s_id;
						?>
						<tr class="wpd-session-row<?php echo $s_is_latest ? ' wpd-session-row--latest' : ''; ?>" data-session-id="<?php echo \esc_attr( $s_id ); ?>">
							<td data-label="<?php \esc_attr_e( 'Session', 'wp-performance-doctor' ); ?>">
								<span class="wpd-session-id">#<?php echo \esc_html( (string) $s_id ); ?></span>
								<?php if ( $s_is_latest ) : ?>
								<span class="wpd-session-latest"><?php \esc_html_e( 'Latest', 'wp-performance-doctor' ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php \esc_attr_e( 'Date and time', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( ! empty( $session['started_at'] ) ? MetricFormatter::format_date( $session['started_at'] ) : __( '—', 'wp-performance-doctor' ) ); ?></td>
							<td data-label="<?php \esc_attr_e( 'Target', 'wp-performance-doctor' ); ?>">
								<span class="wpd-session-target" title="<?php echo \esc_attr( $s_target ? $s_target : __( 'No target URL recorded', 'wp-performance-doctor' ) ); ?>"><?php echo \esc_html( $s_target_text ); ?></span>
							</td>
							<td data-label="<?php \esc_attr_e( 'Context', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( MetricFormatter::request_context_label( $s_ctx ) ); ?></td>
							<td data-label="<?php \esc_attr_e( 'Status', 'wp-performance-doctor' ); ?>"><span class="wpd-status-badge wpd-status--<?php echo \esc_attr( $s_status ); ?>"><?php echo \esc_html( MetricFormatter::session_status_label( $s_status ) ); ?></span></td>
							<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( 'Duration', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( $format_seconds_display( $session['duration'] ?? null ) ); ?></td>
							<td class="wpd-table-cell-numeric" data-label="<?php \esc_attr_e( 'Measurements', 'wp-performance-doctor' ); ?>"><?php echo \esc_html( (string) $s_measurements ); ?></td>
							<td data-label="<?php \esc_attr_e( 'Action', 'wp-performance-doctor' ); ?>">
								<button type="button" class="wpd-button wpd-button--small wpd-button--outline wpd-session-toggle" data-target="<?php echo \esc_attr( $s_details_id ); ?>" aria-expanded="false" aria-controls="<?php echo \esc_attr( $s_details_id ); ?>">
									<?php \esc_html_e( 'Details', 'wp-performance-doctor' ); ?>
								</button>
							</td>
						</tr>
						<tr class="wpd-session-details-row" id="<?php echo \esc_attr( $s_details_id ); ?>" hidden>
							<td colspan="8">
								<div class="wpd-session-details">
									<div class="wpd-session-details__grid">
										<div><span><?php \esc_html_e( 'Session', 'wp-performance-doctor' ); ?></span><strong>#<?php echo \esc_html( (string) $s_id ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Snapshot', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( ! empty( $session['snapshot_id'] ) ? '#' . $session['snapshot_id'] : '—' ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Mode', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( MetricFormatter::session_mode_label( $session['mode'] ?? '' ) ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Trigger', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( \ucfirst( $session['trigger_type'] ?? 'manual' ) ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Started', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( ! empty( $session['started_at'] ) ? MetricFormatter::format_date( $session['started_at'] ) : '—' ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Completed', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( ! empty( $session['completed_at'] ) ? MetricFormatter::format_date( $session['completed_at'] ) : '—' ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Target', 'wp-performance-doctor' ); ?></span><strong class="<?php echo $s_target ? 'wpd-text--mono wpd-text--small' : ''; ?>"><?php echo \esc_html( $s_target ? $s_target : $s_target_text ); ?></strong></div>
										<div><span><?php \esc_html_e( 'Overhead', 'wp-performance-doctor' ); ?></span><strong><?php echo \esc_html( ! empty( $session['overhead_warning'] ) ? $session['overhead_warning'] : __( 'None recorded', 'wp-performance-doctor' ) ); ?></strong></div>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
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
