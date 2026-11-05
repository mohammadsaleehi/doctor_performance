<?php
/**
 * Performance page for WP Performance Doctor admin.
 *
 * Displays performance profiling data, bottleneck analysis, historical
 * comparisons, and provides on-demand profiling controls.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;
use WPPerformanceDoctor\Performance\HistoricalComparison;
use WPPerformanceDoctor\Performance\PerformanceAnalyzer;

/**
 * Performance page displaying profiling data and bottleneck analysis.
 */
final class PerformancePage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Monitoring service.
	 *
	 * @var Service|null
	 */
	private ?Service $service = null;

	/**
	 * Monitoring storage.
	 *
	 * @var Storage|null
	 */
	private ?Storage $storage = null;

	/**
	 * Logical metric categories shown in the Collected Metrics section, in
	 * display order. These are derived from metric identifier prefixes
	 * (e.g. `php.memory_limit` → PHP) rather than storage categories.
	 *
	 * @var string[]
	 */
	private const DISPLAY_CATEGORY_ORDER = array(
		'php',
		'wordpress',
		'database',
		'cache',
		'system',
		'frontend',
	);

	/**
	 * Display labels for logical metric categories.
	 *
	 * @var array<string, string>
	 */
	private const CATEGORY_LABELS = array(
		'php'         => 'PHP',
		'wordpress'   => 'WordPress',
		'database'    => 'Database',
		'cache'       => 'Cache',
		'system'      => 'System',
		'frontend'    => 'Frontend',
		'performance' => 'Performance',
	);

	/**
	 * Frontend performance metric identifiers shown in the Frontend
	 * Performance section, in display order.
	 *
	 * @var string[]
	 */
	private const FRONTEND_METRIC_ORDER = array(
		'frontend.navigation_timing_ttfb'               => 'Time to First Byte (TTFB)',
		'frontend.largest_contentful_paint'             => 'Largest Contentful Paint (LCP)',
		'frontend.cumulative_layout_shift'              => 'Cumulative Layout Shift (CLS)',
		'frontend.interaction_to_next_paint'            => 'Interaction to Next Paint (INP)',
		'frontend.navigation_timing_dom_content_loaded' => 'DOM Content Loaded',
		'frontend.navigation_timing_page_load'          => 'Page Load Duration',
	);

	/**
	 * Short abbreviations for the Core Web Vitals cards.
	 *
	 * @var array<string, string>
	 */
	private const VITAL_ABBREVIATIONS = array(
		'frontend.navigation_timing_ttfb'               => 'TTFB',
		'frontend.largest_contentful_paint'             => 'LCP',
		'frontend.cumulative_layout_shift'              => 'CLS',
		'frontend.interaction_to_next_paint'            => 'INP',
		'frontend.navigation_timing_dom_content_loaded' => 'DCL',
		'frontend.navigation_timing_page_load'          => 'Load',
	);

	/**
	 * Core Web Vitals identifiers in display order.
	 *
	 * @var string[]
	 */
	private const CORE_VITALS = array(
		'frontend.navigation_timing_ttfb',
		'frontend.largest_contentful_paint',
		'frontend.cumulative_layout_shift',
		'frontend.interaction_to_next_paint',
	);

	/**
	 * Database profiling metric identifiers.
	 *
	 * @var string[]
	 */
	private const DATABASE_METRIC_ORDER = array(
		'database.savequeries_enabled'     => 'SAVEQUERIES Enabled',
		'database.query_count'             => 'Query Count',
		'database.query_time_total'        => 'Total Query Time',
		'database.slow_query_count'        => 'Slow Query Count',
		'database.query_type_distribution' => 'Query Type Distribution',
	);

	/**
	 * Resource analysis metric identifiers.
	 *
	 * @var string[]
	 */
	private const RESOURCE_METRIC_ORDER = array(
		'frontend.resource_count'            => 'Total Resources',
		'frontend.css_resource_count'        => 'CSS Resources',
		'frontend.js_resource_count'         => 'JavaScript Resources',
		'frontend.image_resource_count'      => 'Image Resources',
		'frontend.font_resource_count'       => 'Font Resources',
		'frontend.external_resource_count'   => 'External Resources',
		'frontend.resource_transfer_size'    => 'Resource Transfer Size',
		'frontend.resource_loading_duration' => 'Slowest Resource Load',
	);

	/**
	 * Browser-collected metric identifiers. These are passed to the analyzer
	 * as full metric rows (with `availability_status`, `metric_value`,
	 * `measurement_context`, `page_url`) because the browser metric analysis
	 * methods read row fields, whereas every other analyzer method expects
	 * raw metric values.
	 *
	 * @var string[]
	 */
	private const BROWSER_METRIC_IDENTIFIERS = array(
		'frontend.navigation_timing_ttfb',
		'frontend.largest_contentful_paint',
		'frontend.cumulative_layout_shift',
		'frontend.interaction_to_next_paint',
		'frontend.navigation_timing_dom_content_loaded',
		'frontend.navigation_timing_page_load',
		'frontend.resource_loading_duration',
		'frontend.resource_count',
	);
	/**
	 * Metric identifiers excluded from historical comparison because they are
	 * internal, non-numeric, timestamps, or not meaningful to trend.
	 *
	 * @var string[]
	 */
	private const COMPARISON_EXCLUDED_IDENTIFIERS = array(
		'performance.php.request_time_float',
		'performance.profiling_mode',
		'performance.request_context',
		'performance.collected_at',
		'php.request_time_float',
		'frontend.browser_support',
		'frontend.measurement_method',
		'network.http_requests_count',
		'network.http_request_time',
		'network.slow_external_count',
	);

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set monitoring service.
	 *
	 * @param Service $service Monitoring service.
	 * @return self
	 */
	public function set_service( Service $service ): self {
		$this->service = $service;
		return $this;
	}

	/**
	 * Set monitoring storage.
	 *
	 * @param Storage $storage Monitoring storage.
	 * @return self
	 */
	public function set_storage( Storage $storage ): self {
		$this->storage = $storage;
		return $this;
	}

	/**
	 * Render the performance page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_performance_data();

		\wp_enqueue_script( 'wp-performance-doctor-admin' );

		\wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdPerformance',
			array(
				'restUrl'        => \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/performance/profile' ) ),
				'restBrowserUrl' => \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/performance/browser-metrics' ) ),
				'nonce'          => \wp_create_nonce( 'wpd_collect' ),
				'browserNonce'   => \wp_create_nonce( 'wpd_collect' ),
				'wpRestNonce'    => \wp_create_nonce( 'wp_rest' ),
				'strings'        => array(
					'runProfile'     => __( 'Run Profile', 'wp-performance-doctor' ),
					'profiling'      => __( 'Profiling…', 'wp-performance-doctor' ),
					'complete'       => __( 'Profile complete.', 'wp-performance-doctor' ),
					'error'          => __( 'Profiling failed.', 'wp-performance-doctor' ),
					'noData'         => __( 'No performance data available. Run a profile to collect measurements.', 'wp-performance-doctor' ),
					'compare'        => __( 'Compare', 'wp-performance-doctor' ),
					'collectBrowser' => __( 'Collect Browser Metrics', 'wp-performance-doctor' ),
					'collecting'     => __( 'Collecting browser metrics…', 'wp-performance-doctor' ),
					'browserStored'  => __( 'Browser metrics stored.', 'wp-performance-doctor' ),
					'noSession'      => __( 'No active profiling session. Run a profile first.', 'wp-performance-doctor' ),
				),
			)
		);

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/performance.php';
	}

	/**
	 * Get performance page data including metrics, bottlenecks, and history.
	 *
	 * @return array<string, mixed>
	 */
	private function get_performance_data(): array {
		$data = array(
			'enabled'                  => false,
			'interval'                 => 0,
			'has_data'                 => false,
			'monitoring_status'        => __( 'Disabled', 'wp-performance-doctor' ),
			'latest_profile_timestamp' => null,
			'metrics'                  => array(),
			'metrics_by_id'            => array(),
			'grouped_metrics'          => array(),
			'category_labels'          => self::CATEGORY_LABELS,
			'bottlenecks'              => array(),
			'bottleneck_summary'       => array(
				'confirmed' => 0,
				'potential' => 0,
				'info'      => 0,
			),
			'sessions'                 => array(),
			'sessions_total'           => 0,
			'latest_session'           => null,
			'comparison'               => null,
			'overview'                 => array(
				'confirmed_bottlenecks'  => 0,
				'available_measurements' => 0,
				'latest_profile'         => null,
				'browser_measured'       => false,
			),
			'frontend_metrics'         => array(),
			'resource_metrics'         => array(),
			'database_metrics'         => array(),
			'active_filters'           => array(),
			'rest_url'                 => '',
			'nonce'                    => '',
			'browser_rest_url'         => '',
			'browser_nonce'            => '',
		);

		if ( ! $this->service ) {
			return $data;
		}

		$data['enabled']           = $this->service->is_enabled();
		$data['interval']          = $this->service->get_interval();
		$data['monitoring_status'] = $data['enabled']
			? __( 'Enabled', 'wp-performance-doctor' )
			: __( 'Disabled', 'wp-performance-doctor' );

		if ( ! $this->storage ) {
			return $data;
		}

		// Get profiling sessions first so browser metrics (stored against the
		// latest profiling session's snapshot) are displayed rather than
		// metrics from an unrelated passive monitoring snapshot.
		$session_result         = $this->storage->get_profiling_sessions( 1, 10 );
		$data['sessions']       = $session_result['items'];
		$data['sessions_total'] = $session_result['total'];

		$latest_session = null;
		if ( ! empty( $data['sessions'] ) ) {
			$latest_session         = $data['sessions'][0];
			$data['latest_session'] = $latest_session;
		}

		// Enrich sessions with the number of metrics actually stored against
		// their snapshot. The session's own metrics_count is written when the
		// server profile finishes and therefore excludes browser metrics that
		// are submitted to the same snapshot afterwards. Displaying the stored
		// snapshot count avoids contradictory measurement totals.
		$session_snapshot_ids = array();
		foreach ( $data['sessions'] as $session_row ) {
			$sid = isset( $session_row['snapshot_id'] ) ? (int) $session_row['snapshot_id'] : 0;
			if ( $sid > 0 ) {
				$session_snapshot_ids[] = $sid;
			}
		}
		$snapshot_metric_counts = $this->storage->get_snapshot_metric_counts( $session_snapshot_ids );
		foreach ( $data['sessions'] as &$session_row ) {
			$sid                                 = isset( $session_row['snapshot_id'] ) ? (int) $session_row['snapshot_id'] : 0;
			$stored                              = $sid > 0 && isset( $snapshot_metric_counts[ $sid ] )
				? (int) $snapshot_metric_counts[ $sid ]
				: (int) ( $session_row['metrics_count'] ?? 0 );
			$session_row['stored_metrics_count'] = $stored;
		}
		unset( $session_row );
		if ( $latest_session ) {
			$data['latest_session'] = $data['sessions'][0];
			$latest_session         = $data['latest_session'];
		}

		$latest_session_snapshot = $latest_session['snapshot_id'] ?? null;

		// Get metrics for the latest profiling snapshot across every storage
		// category that can hold performance data. Scoping to the session
		// snapshot keeps the metrics tied to the profile the user is viewing.
		$storage_categories = array(
			'system',
			'database',
			'cache',
			'frontend',
			'performance',
			'php_profiler',
			'wordpress',
			'WordPress',
		);

		$metrics = array();
		foreach ( $storage_categories as $category ) {
			$category_metrics = $this->storage->get_latest_metrics_by_category( $category, $latest_session_snapshot );
			foreach ( $category_metrics as $metric ) {
				$metrics[] = $metric;
			}
		}

		$data['metrics']  = $metrics;
		$data['has_data'] = ! empty( $metrics );

		// Normalize string 'null' values stored by older snapshots back to
		// real nulls so unavailable numeric metrics are not mistaken for
		// truthy values (e.g. 'null' > 0 evaluates to true in PHP).
		foreach ( $metrics as &$metric ) {
			$metric['metric_value'] = self::normalize_null_string( $metric['metric_value'] ?? null );
		}
		unset( $metric );

		// Build a canonical set of metrics: one row per logical identifier.
		// The PerformanceCollector stores measurements under `performance.`
		// prefixed identifiers (e.g. `performance.database.query_count`) while
		// dedicated collectors store the same measurements without the prefix
		// (e.g. `database.query_count`). Without dedup, the same logical
		// measurement would appear twice across the interface. The canonical
		// set keeps a single representative per logical identifier, preferring
		// the dedicated collector's row over the prefixed duplicate, and for
		// frontend browser metrics preferring the row that identifies the
		// measured page.
		$canonical_metrics = array();
		foreach ( $metrics as $metric ) {
			$identifier = $metric['metric_identifier'] ?? '';
			if ( '' === $identifier ) {
				continue;
			}
			$is_prefixed = \str_starts_with( $identifier, 'performance.' );
			$logical_id  = $is_prefixed ? \substr( $identifier, \strlen( 'performance.' ) ) : $identifier;
			$existing    = $canonical_metrics[ $logical_id ] ?? null;
			if ( ! $existing ) {
				$canonical_metrics[ $logical_id ] = $metric;
				continue;
			}
			$existing_prefixed = \str_starts_with( $existing['metric_identifier'] ?? '', 'performance.' );
			$metric_usable     = self::is_metric_usable( $metric );
			$existing_usable   = self::is_metric_usable( $existing );

			// A dedicated collector normally wins over the performance
			// collector's prefixed copy. However, some dedicated collectors
			// only report a metric as unavailable (for example query metrics
			// when SAVEQUERIES is disabled), while the on-demand performance
			// collector may hold a real measurement. In that case keep the
			// usable value instead of discarding it.
			if ( $is_prefixed && ! $existing_prefixed ) {
				if ( $metric_usable && ! $existing_usable ) {
					$canonical_metrics[ $logical_id ] = $metric;
				}
				continue;
			}
			if ( ! $is_prefixed && $existing_prefixed ) {
				if ( $metric_usable || ! $existing_usable ) {
					$canonical_metrics[ $logical_id ] = $metric;
				}
				continue;
			}
			// Same prefix status: prefer the row that identifies the measured
			// page; if both (or neither) identify a page, prefer an available
			// measured value over an unavailable/null one so the UI never
			// displays "Not measured" when a real measurement exists.
			$has_page_url  = ! empty( $metric['page_url'] ?? '' );
			$existing_page = ! empty( ( $existing['page_url'] ?? '' ) );
			if ( $has_page_url && ! $existing_page ) {
				$canonical_metrics[ $logical_id ] = $metric;
			} elseif ( $has_page_url === $existing_page && $metric_usable && ! $existing_usable ) {
				$canonical_metrics[ $logical_id ] = $metric;
			} elseif ( $has_page_url === $existing_page && $metric_usable === $existing_usable ) {
				// Prefer the most recently collected measurement when duplicate
				// rows exist for the same logical identifier (for example after
				// browser metrics are collected more than once).
				$metric_time   = $metric['collected_at'] ?? '';
				$existing_time = $existing['collected_at'] ?? '';
				if ( $metric_time > $existing_time ) {
					$canonical_metrics[ $logical_id ] = $metric;
				}
			}
		}

		$data['metrics']       = array_values( $canonical_metrics );
		$data['has_data']      = ! empty( $data['metrics'] );
		$data['metrics_by_id'] = $canonical_metrics;
		$metrics_by_id         = $canonical_metrics;

		// Group metrics for the Collected Metrics section by logical category
		// (the segment before the first dot in the identifier). Uses the
		// canonical set so no measurement appears under two different
		// identifiers.
		$data['grouped_metrics'] = $this->group_metrics_by_logical_category( $data['metrics'] );

		// Run bottleneck analysis on the latest metrics keyed by identifier.
		// The analyzer expects raw values for server metrics but full metric
		// rows for browser-collected metrics, so build a hybrid array.
		if ( ! empty( $metrics_by_id ) ) {
			$analyzer_metrics = array();
			foreach ( $metrics_by_id as $identifier => $metric_row ) {
				if ( \in_array( $identifier, self::BROWSER_METRIC_IDENTIFIERS, true ) ) {
					$analyzer_metrics[ $identifier ] = $metric_row;
				} else {
					$analyzer_metrics[ $identifier ] = $metric_row['metric_value'] ?? null;
				}
			}
			$analyzer        = new PerformanceAnalyzer();
			$raw_bottlenecks = $analyzer->analyze( $analyzer_metrics );
			$bottlenecks     = array();
			foreach ( $raw_bottlenecks as $bottleneck ) {
				$bottlenecks[] = $this->prepare_bottleneck( $bottleneck, $metrics_by_id );
			}
			// Sort: confirmed first, then potential, then informational.
			\usort(
				$bottlenecks,
				function ( $a, $b ) {
					$order = array(
						'confirmed' => 0,
						'potential' => 1,
						'info'      => 2,
					);
					$oa    = $order[ $a['type'] ] ?? 99;
					$ob    = $order[ $b['type'] ] ?? 99;
					return $oa <=> $ob;
				}
			);
			$data['bottlenecks']        = $bottlenecks;
			$data['bottleneck_summary'] = $this->summarize_bottlenecks( $bottlenecks );

			// Findings are grouped by their actual severity for the diagnostic
			// section. Empty severity groups are intentionally omitted.
			$severity_order = array( 'critical', 'high', 'medium', 'low', 'info' );
			$groups         = array();
			$sev_counts     = array_fill_keys( $severity_order, 0 );
			foreach ( $bottlenecks as $bottleneck ) {
				$sev = $bottleneck['severity'] ?? 'info';
				if ( ! isset( $groups[ $sev ] ) ) {
					$groups[ $sev ] = array();
				}
				$groups[ $sev ][] = $bottleneck;
				if ( isset( $sev_counts[ $sev ] ) ) {
					++$sev_counts[ $sev ];
				}
			}
			$ordered_groups = array();
			foreach ( $severity_order as $sev ) {
				if ( ! empty( $groups[ $sev ] ) ) {
					$ordered_groups[ $sev ] = $groups[ $sev ];
				}
			}
			$data['bottleneck_groups']                 = $ordered_groups;
			$data['severity_counts']                   = $sev_counts;
			$data['overview']['confirmed_bottlenecks'] = $data['bottleneck_summary']['confirmed'];
		}

		// Pre-computed section data.
		$frontend                      = $this->prepare_frontend_metrics( $metrics_by_id, $latest_session );
		$data['frontend_metrics']      = $frontend['metrics'];
		$data['frontend_status']       = $frontend['status'];
		$data['frontend_context']      = $frontend['context'];
		$resource                      = $this->prepare_resource_metrics( $metrics_by_id );
		$data['resource_metrics']      = $resource['metrics'];
		$data['resource_summary']      = $resource['summary'];
		$data['resource_distribution'] = $resource['distribution'];
		$data['database_metrics']      = $this->prepare_database_metrics( $metrics_by_id );

		// Overview values. "Available" counts only usable measurements; the
		// collected total is kept separately so unavailable rows are never
		// presented as available data.
		$available_measurements = 0;
		foreach ( $data['metrics'] as $metric_row ) {
			if ( self::is_metric_usable( $metric_row ) ) {
				++$available_measurements;
			}
		}
		$data['overview']['available_measurements']  = $available_measurements;
		$data['overview']['collected_measurements']  = \count( $data['metrics'] );
		$data['overview']['latest_profile']          = $latest_session['started_at'] ?? null;
		$data['overview']['latest_profile_duration'] = isset( $latest_session['duration'] ) ? (float) $latest_session['duration'] : null;
		$data['overview']['latest_profile_snapshot'] = $latest_session['snapshot_id'] ?? null;
		$data['overview']['browser_measured']        = $this->has_frontend_metrics( $metrics_by_id );
		if ( $latest_session ) {
			$data['latest_profile_timestamp'] = $latest_session['started_at'] ?? null;
		}

		// Historical comparison (current profiling snapshot vs the most recent
		// older snapshot with at least one comparable measurement).
		$current_snapshot_id = $latest_session_snapshot ? (int) $latest_session_snapshot : $this->storage->get_latest_snapshot_id();
		$data['comparison']  = $current_snapshot_id
			? $this->build_comparison( $current_snapshot_id, $metrics_by_id )
			: null;

		// REST URL and nonce.
		$data['rest_url']         = \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/performance/profile' ) );
		$data['browser_rest_url'] = \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/performance/browser-metrics' ) );
		$data['nonce']            = \wp_create_nonce( 'wpd_collect' );
		$data['browser_nonce']    = \wp_create_nonce( 'wpd_collect' );

		return $data;
	}

	/**
	 * Group metrics by logical category for the Collected Metrics section.
	 *
	 * The logical category is derived from the metric identifier prefix
	 * (e.g. `php.memory_limit` → PHP) so that PerformanceCollector metrics
	 * stored under the `performance` storage category appear under their
	 * meaningful heading instead of a generic "Performance" bucket.
	 *
	 * @param array $metrics Stored metric rows.
	 * @return array<string, array<int, array>> Categories keyed by logical category.
	 */
	private function group_metrics_by_logical_category( array $metrics ): array {
		$groups = array();
		foreach ( $metrics as $metric ) {
			$identifier = $metric['metric_identifier'] ?? '';
			if ( '' === $identifier ) {
				continue;
			}
			// Use the stripped logical identifier when a performance.-prefixed
			// duplicate exists, so the metric groups under its real category.
			$logical_id = $identifier;
			if ( \str_starts_with( $identifier, 'performance.' ) ) {
				$logical_id = \substr( $identifier, \strlen( 'performance.' ) );
			}
			$pos      = \strpos( $logical_id, '.' );
			$category = false !== $pos ? \substr( $logical_id, 0, $pos ) : 'performance';
			$category = \strtolower( $category );
			if ( ! isset( $groups[ $category ] ) ) {
				$groups[ $category ] = array();
			}
			// Attach the logical identifier for display labeling.
			$metric['logical_identifier'] = $logical_id;
			$groups[ $category ][]        = $metric;
		}

		$sorted = array();
		foreach ( self::DISPLAY_CATEGORY_ORDER as $category ) {
			if ( isset( $groups[ $category ] ) ) {
				$sorted[ $category ] = $groups[ $category ];
				unset( $groups[ $category ] );
			}
		}
		foreach ( $groups as $category => $category_metrics ) {
			$sorted[ $category ] = $category_metrics;
		}

		return $sorted;
	}

	/**
	 * Normalize the string 'null' (stored by older snapshots) back to a real
	 * null so unavailable metrics are not mistaken for truthy values.
	 *
	 * @param mixed $value Stored metric value.
	 * @return mixed
	 */
	private static function normalize_null_string( $value ) {
		if ( \is_string( $value ) && 'null' === strtolower( $value ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Whether a metric row represents a usable (available, non-null) value.
	 *
	 * @param array $metric Stored metric row.
	 * @return bool
	 */
	private static function is_metric_usable( array $metric ): bool {
		if ( ! isset( $metric['availability_status'] ) || 'available' !== $metric['availability_status'] ) {
			return false;
		}
		$value = self::normalize_null_string( $metric['metric_value'] ?? null );
		return null !== $value && '' !== $value;
	}

	/**
	 * Derive the logical category label for a metric identifier.
	 *
	 * @param string $identifier Metric identifier.
	 * @return string
	 */
	public static function logical_category_label( string $identifier ): string {
		$logical_id = $identifier;
		if ( \str_starts_with( $identifier, 'performance.' ) ) {
			$logical_id = \substr( $identifier, \strlen( 'performance.' ) );
		}
		$pos      = \strpos( $logical_id, '.' );
		$category = false !== $pos ? \substr( $logical_id, 0, $pos ) : 'performance';
		$category = \strtolower( $category );
		return self::CATEGORY_LABELS[ $category ] ?? \ucfirst( $category );
	}

	/**
	 * Prepare a bottleneck finding for display.
	 *
	 * Adds a human-readable finding type (confirmed / potential / info),
	 * normalized severity/confidence labels, and evidence values formatted
	 * for display.
	 *
	 * @param array $bottleneck Raw analyzer finding.
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return array Display-ready finding data.
	 */
	private function prepare_bottleneck( array $bottleneck, array $metrics_by_id ): array {
		$severity   = $bottleneck['severity'] ?? 'info';
		$confidence = $bottleneck['confidence'] ?? 'low';

		$type = 'info';
		if ( \in_array( $severity, array( 'critical', 'high' ), true ) ) {
			$type = 'confirmed';
		} elseif ( 'medium' === $severity ) {
			$type = 'potential';
		}

		$evidence           = $bottleneck['evidence'] ?? array();
		$formatted_evidence = array();
		$summary_evidence   = array();
		foreach ( $evidence as $key => $value ) {
			$metric_row = $metrics_by_id[ $key ] ?? null;
			$display    = $this->format_evidence_value( $key, $value, $metric_row );

			$formatted_evidence[] = array(
				'id'      => $key,
				'label'   => MetricFormatter::label( $key ),
				'value'   => $value,
				'metric'  => $metric_row,
				'display' => $display,
			);

			// Only scalar, human-readable evidence is used for the compact
			// summary row. Complex values remain available in the expanded
			// finding details.
			if ( $display && '' !== $display && \count( $summary_evidence ) < 2 ) {
				$summary_evidence[] = MetricFormatter::label( $key ) . ': ' . $display;
			}
		}

		$severity_label = \WPPerformanceDoctor\Rules\Severity::label( $severity );
		if ( 'info' === $severity ) {
			$severity_label = __( 'Informational', 'wp-performance-doctor' );
		}

		return array(
			'id'               => $bottleneck['id'] ?? '',
			'title'            => $bottleneck['title'] ?? '',
			'type'             => $type,
			// Severity / confidence are also kept for backward-compatible
			// consumers and for display badges.
			'severity'         => $severity,
			'confidence'       => $confidence,
			'severity_label'   => $severity_label,
			'confidence_label' => \ucfirst( $confidence ),
			'description'      => $bottleneck['description'] ?? '',
			'impact'           => $bottleneck['impact'] ?? '',
			'evidence'         => $formatted_evidence,
			'evidence_summary' => $summary_evidence,
			'remediation'      => $bottleneck['remediation'] ?? array(),
			'limitations'      => $bottleneck['measurement_limitations'] ?? '',
			'category'         => $bottleneck['category'] ?? '',
		);
	}

	/**
	 * Format a single analyzer evidence value for display.
	 *
	 * @param string     $identifier Metric identifier.
	 * @param mixed      $value      Evidence value.
	 * @param array|null $metric_row Stored metric row when available.
	 * @return string Human-readable value or an empty string when the value
	 *                cannot be represented meaningfully.
	 */
	private function format_evidence_value( string $identifier, $value, ?array $metric_row ): string {
		if ( \is_array( $value ) ) {
			// Browser-collected findings store the metric row as evidence.
			if ( isset( $value['value'] ) && \is_numeric( $value['value'] ) ) {
				return MetricFormatter::format(
					array(
						'metric_identifier'   => $identifier,
						'metric_value'        => $value['value'],
						'unit'                => $value['unit'] ?? '',
						'availability_status' => 'available',
					)
				);
			}

			// Slow query evidence is an array of query records. Summarize the
			// slowest recorded query instead of dumping the raw structure.
			if ( 'database.slow_queries' === $identifier ) {
				$slowest = null;
				foreach ( $value as $query ) {
					if ( ! \is_array( $query ) ) {
						continue;
					}
					if ( null === $slowest || (float) ( $query['duration'] ?? 0 ) > (float) ( $slowest['duration'] ?? 0 ) ) {
						$slowest = $query;
					}
				}
				if ( $slowest && isset( $slowest['duration'] ) ) {
					return sprintf(
						/* translators: %s: query duration in seconds. */
						__( 'Slowest %s', 'wp-performance-doctor' ),
						MetricFormatter::format_seconds( (float) $slowest['duration'] )
					);
				}
			}
			return '';
		}

		$row = array(
			'metric_identifier'   => $identifier,
			'metric_value'        => $value,
			'unit'                => $metric_row['unit'] ?? '',
			'availability_status' => 'available',
		);
		if ( $metric_row ) {
			$row = array_merge( $metric_row, $row );
		}

		return MetricFormatter::format( $row );
	}

	/**
	 * Summarize bottlenecks by type.
	 *
	 * @param array $bottlenecks Prepared bottleneck findings.
	 * @return array{confirmed: int, potential: int, info: int}
	 */
	private function summarize_bottlenecks( array $bottlenecks ): array {
		$summary = array(
			'confirmed' => 0,
			'potential' => 0,
			'info'      => 0,
		);
		foreach ( $bottlenecks as $bottleneck ) {
			$type = $bottleneck['type'] ?? 'info';
			if ( array_key_exists( $type, $summary ) ) {
				++$summary[ $type ];
			}
		}
		return $summary;
	}

	/**
	 * Whether any frontend (browser-collected) metrics are present.
	 *
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return bool
	 */
	private function has_frontend_metrics( array $metrics_by_id ): bool {
		foreach ( self::FRONTEND_METRIC_ORDER as $identifier => $_label ) {
			if ( isset( $metrics_by_id[ $identifier ] ) && 'available' === ( $metrics_by_id[ $identifier ]['availability_status'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Prepare frontend performance metrics for display.
	 *
	 * @param array      $metrics_by_id Metrics keyed by identifier.
	 * @param array|null $latest_session Latest profiling session.
	 * @return array{metrics: array, status: array|null, context: array}
	 */
	private function prepare_frontend_metrics( array $metrics_by_id, ?array $latest_session ): array {
		$target_url = $latest_session['target_url'] ?? '';
		$context    = $latest_session['request_context'] ?? 'admin';
		if ( empty( $target_url ) ) {
			$target_url = \admin_url();
		}

		$metrics       = array();
		$ratings       = array();
		$context_meta  = array(
			'page_url'      => $target_url,
			'context'       => $context,
			'context_label' => MetricFormatter::request_context_label( $context ),
			'collected_at'  => '',
		);
		$context_found = false;

		foreach ( self::FRONTEND_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';
			$is_cwv    = \in_array( $identifier, self::CORE_VITALS, true );

			$rating = null;
			if ( $available && $is_cwv && \is_numeric( $value ) ) {
				$rating = MetricFormatter::vital_rating( $identifier, (float) $value );
			}

			// Identify the measured page and context from the metric itself,
			// falling back to the profiling session when the metric row does
			// not carry them. Empty strings are treated as missing so the
			// fallback is applied.
			$page_url  = ! empty( $metric['page_url'] ?? '' ) ? $metric['page_url'] : $target_url;
			$context_r = ! empty( $metric['measurement_context'] ?? '' ) ? $metric['measurement_context'] : $context;

			if ( $available && ! $context_found ) {
				$context_meta  = array(
					'page_url'      => $page_url,
					'context'       => $context_r,
					'context_label' => MetricFormatter::request_context_label( $context_r ),
					'collected_at'  => $metric['collected_at'] ?? '',
				);
				$context_found = true;
			}

			$parts = $available && \is_numeric( $value )
				? self::split_value_unit( (float) $value, $unit )
				: array( '', '' );

			$threshold = $is_cwv ? $this->vital_threshold( $identifier ) : null;
			$progress  = null;
			if ( null !== $threshold && $available && \is_numeric( $value ) ) {
				$progress = (int) \min( 100, \max( 0, ( (float) $value / $threshold['needs_improvement'] ) * 100 ) );
			}

			$slug = 'not-measured';
			if ( $rating ) {
				$slug = 'needs_improvement' === $rating ? 'needs-improvement' : $rating;
			}

			if ( $is_cwv ) {
				$status_label = MetricFormatter::vital_label( $rating );
			} else {
				$status_label = $available ? __( 'Measured', 'wp-performance-doctor' ) : __( 'Not measured', 'wp-performance-doctor' );
			}

			if ( $rating ) {
				$ratings[] = $rating;
			}

			$metrics[] = array(
				'identifier'      => $identifier,
				'label'           => $label,
				'abbreviation'    => self::VITAL_ABBREVIATIONS[ $identifier ] ?? $label,
				'is_core_vital'   => $is_cwv,
				'unit'            => $unit,
				'unit_display'    => $parts[1],
				'available'       => $available,
				'value'           => $value,
				'value_display'   => $parts[0],
				'display'         => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'rating'          => $rating,
				'rating_label'    => $status_label,
				'rating_slug'     => $slug,
				'rating_class'    => MetricFormatter::vital_class( $rating ),
				'progress'        => $progress,
				'threshold_label' => $threshold['label'] ?? '',
				'description'     => $this->vital_description( $identifier ),
				'collected_at'    => $metric['collected_at'] ?? '',
				'page_url'        => $page_url,
				'context'         => $context_r,
				'context_label'   => MetricFormatter::request_context_label( $context_r ),
			);
		}

		$status = null;
		if ( ! empty( $ratings ) ) {
			$worst = 'good';
			foreach ( $ratings as $rating ) {
				if ( 'poor' === $rating ) {
					$worst = 'poor';
					break;
				}
				if ( 'needs_improvement' === $rating ) {
					$worst = 'needs_improvement';
				}
			}
			$status = array(
				'rating'   => $worst,
				'slug'     => 'needs_improvement' === $worst ? 'needs-improvement' : $worst,
				'label'    => MetricFormatter::vital_label( $worst ),
				'measured' => \count( $ratings ),
				'total'    => \count( self::CORE_VITALS ),
			);
		}

		return array(
			'metrics' => $metrics,
			'status'  => $status,
			'context' => $context_meta,
		);
	}

	/**
	 * Get the threshold metadata for a Core Web Vital.
	 *
	 * @param string $identifier Metric identifier.
	 * @return array{good: float, needs_improvement: float, label: string}|null
	 */
	private function vital_threshold( string $identifier ): ?array {
		return match ( $identifier ) {
			'frontend.navigation_timing_ttfb'    => array(
				'good'              => 800,
				'needs_improvement' => 1800,
				'label'             => __( 'Good at or below 800 ms', 'wp-performance-doctor' ),
			),
			'frontend.largest_contentful_paint'  => array(
				'good'              => 2500,
				'needs_improvement' => 4000,
				'label'             => __( 'Good at or below 2.5 s', 'wp-performance-doctor' ),
			),
			'frontend.cumulative_layout_shift'   => array(
				'good'              => 0.1,
				'needs_improvement' => 0.25,
				'label'             => __( 'Good at or below 0.1', 'wp-performance-doctor' ),
			),
			'frontend.interaction_to_next_paint' => array(
				'good'              => 200,
				'needs_improvement' => 500,
				'label'             => __( 'Good at or below 200 ms', 'wp-performance-doctor' ),
			),
			default => null,
		};
	}

	/**
	 * Short explanation for a frontend metric.
	 *
	 * @param string $identifier Metric identifier.
	 * @return string
	 */
	private function vital_description( string $identifier ): string {
		return match ( $identifier ) {
			'frontend.navigation_timing_ttfb'    => __( 'Time from the browser request to the first byte of the server response.', 'wp-performance-doctor' ),
			'frontend.largest_contentful_paint'  => __( 'When the largest visible content element finishes rendering.', 'wp-performance-doctor' ),
			'frontend.cumulative_layout_shift'   => __( 'Unexpected layout shifts that occur while the page loads.', 'wp-performance-doctor' ),
			'frontend.interaction_to_next_paint' => __( 'The slowest interaction latency on the page.', 'wp-performance-doctor' ),
			'frontend.navigation_timing_dom_content_loaded' => __( 'When the initial HTML document is fully loaded and parsed.', 'wp-performance-doctor' ),
			'frontend.navigation_timing_page_load'          => __( 'When the page and all its resources have finished loading.', 'wp-performance-doctor' ),
			default => '',
		};
	}

	/**
	 * Split a formatted numeric value into a display number and unit.
	 *
	 * Keeps the unit separate so cards never render duplicated units such as
	 * "4,621.7 ms ms".
	 *
	 * @param float  $value Numeric value.
	 * @param string $unit  Stored unit.
	 * @return array{0: string, 1: string} Value and unit.
	 */
	private static function split_value_unit( float $value, string $unit ): array {
		switch ( $unit ) {
			case 'ms':
				return array( self::trim_number( $value, 1 ), 'ms' );
			case 'score':
				return array( self::trim_number( $value, 3 ), 'score' );
			case 'seconds':
				return array( self::trim_number( $value, 2 ), 's' );
			case 'bytes':
				$formatted = MetricFormatter::format_bytes( $value );
				$parts     = \explode( ' ', $formatted, 2 );
				return array( $parts[0], $parts[1] ?? '' );
			case 'count':
				return array( \number_format_i18n( $value ), '' );
			case 'string':
			case '':
				return array( self::trim_number( $value, 2 ), '' );
			default:
				return array( self::trim_number( $value, 2 ), $unit );
		}
	}

	/**
	 * Format a number with a fixed precision without trailing zeroes.
	 *
	 * @param float $value     Numeric value.
	 * @param int   $precision Decimal precision.
	 * @return string
	 */
	private static function trim_number( float $value, int $precision ): string {
		return \rtrim( \rtrim( \number_format( $value, $precision, '.', ',' ), '0' ), '.' );
	}

	/**
	 * Prepare resource analysis metrics for display.
	 *
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return array{metrics: array, summary: array, distribution: array}
	 */
	private function prepare_resource_metrics( array $metrics_by_id ): array {
		$metrics = array();
		$lookup  = array();
		foreach ( self::RESOURCE_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';
			$parts     = $available && \is_numeric( $value )
				? self::split_value_unit( (float) $value, $unit )
				: array( '', '' );

			$entry = array(
				'identifier'    => $identifier,
				'label'         => $label,
				'unit'          => $unit,
				'unit_display'  => $parts[1],
				'available'     => $available,
				'value'         => $value,
				'value_display' => $parts[0],
				'display'       => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'collected_at'  => $metric['collected_at'] ?? '',
			);

			$metrics[]             = $entry;
			$lookup[ $identifier ] = $entry;
		}

		$total = $lookup['frontend.resource_count'] ?? null;
		$typed = array(
			'css'    => $lookup['frontend.css_resource_count'] ?? null,
			'js'     => $lookup['frontend.js_resource_count'] ?? null,
			'images' => $lookup['frontend.image_resource_count'] ?? null,
			'fonts'  => $lookup['frontend.font_resource_count'] ?? null,
		);

		$typed_total = 0;
		foreach ( $typed as $entry ) {
			if ( $entry && $entry['available'] && \is_numeric( $entry['value'] ) ) {
				$typed_total += (int) $entry['value'];
			}
		}

		$denominator = ( $total && $total['available'] && \is_numeric( $total['value'] ) && (int) $total['value'] > 0 )
			? (int) $total['value']
			: $typed_total;

		$distribution = array();
		$dist_map     = array(
			'css'    => __( 'CSS', 'wp-performance-doctor' ),
			'js'     => __( 'JavaScript', 'wp-performance-doctor' ),
			'images' => __( 'Images', 'wp-performance-doctor' ),
			'fonts'  => __( 'Fonts', 'wp-performance-doctor' ),
		);
		foreach ( $dist_map as $slug => $label ) {
			$entry          = $typed[ $slug ];
			$value          = ( $entry && $entry['available'] && \is_numeric( $entry['value'] ) ) ? (int) $entry['value'] : null;
			$distribution[] = array(
				'slug'      => $slug,
				'label'     => $label,
				'value'     => $value,
				'available' => null !== $value,
				'share'     => ( null !== $value && $denominator > 0 ) ? \round( ( $value / $denominator ) * 100, 1 ) : null,
			);
		}

		if ( $denominator > $typed_total ) {
			$other          = $denominator - $typed_total;
			$distribution[] = array(
				'slug'      => 'other',
				'label'     => __( 'Other', 'wp-performance-doctor' ),
				'value'     => $other,
				'available' => true,
				'share'     => \round( ( $other / $denominator ) * 100, 1 ),
			);
		}

		$summary = array(
			'total'       => $total,
			'transfer'    => $lookup['frontend.resource_transfer_size'] ?? null,
			'slowest'     => $lookup['frontend.resource_loading_duration'] ?? null,
			'external'    => $lookup['frontend.external_resource_count'] ?? null,
			'counts'      => array(
				'js'     => $lookup['frontend.js_resource_count'] ?? null,
				'css'    => $lookup['frontend.css_resource_count'] ?? null,
				'images' => $lookup['frontend.image_resource_count'] ?? null,
				'fonts'  => $lookup['frontend.font_resource_count'] ?? null,
			),
			'typed_total' => $typed_total,
			'measured'    => ( $total && $total['available'] ) || $typed_total > 0,
		);

		return array(
			'metrics'      => $metrics,
			'summary'      => $summary,
			'distribution' => $distribution,
		);
	}

	/**
	 * Prepare database profiling metrics for display.
	 *
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return array Database metric display data plus savequeries state.
	 */
	private function prepare_database_metrics( array $metrics_by_id ): array {
		$metrics               = array();
		$lookup                = array();
		$savequeries_enabled   = null;
		$savequeries_available = false;

		foreach ( self::DATABASE_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';
			$parts     = $available && \is_numeric( $value )
				? self::split_value_unit( (float) $value, $unit )
				: array( '', '' );

			if ( 'database.savequeries_enabled' === $identifier ) {
				$savequeries_available = $available;
				$savequeries_enabled   = $available && $this->is_truthy( $value );
			}

			$entry = array(
				'identifier'    => $identifier,
				'label'         => $label,
				'unit'          => $unit,
				'unit_display'  => $parts[1],
				'available'     => $available,
				'value'         => $value,
				'value_display' => $parts[0],
				'display'       => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'collected_at'  => $metric['collected_at'] ?? '',
			);

			$metrics[]             = $entry;
			$lookup[ $identifier ] = $entry;
		}

		$state = 'unavailable';
		if ( $savequeries_available ) {
			$state = $savequeries_enabled ? 'enabled' : 'disabled';
		}

		$query_types         = array();
		$distribution        = $lookup['database.query_type_distribution'] ?? null;
		$distribution_values = ( $distribution && $distribution['available'] ) ? $distribution['value'] : null;
		if ( \is_string( $distribution_values ) ) {
			$decoded = \json_decode( $distribution_values, true );
			if ( \is_array( $decoded ) ) {
				$distribution_values = $decoded;
			}
		}
		if ( \is_array( $distribution_values ) ) {
			$type_total = 0;
			foreach ( $distribution_values as $count ) {
				$type_total += (int) $count;
			}
			foreach ( $distribution_values as $query_type => $count ) {
				$query_types[] = array(
					'type'  => \ucfirst( (string) $query_type ),
					'value' => (int) $count,
					'share' => $type_total > 0 ? \round( ( (int) $count / $type_total ) * 100, 1 ) : 0,
				);
			}
		}

		$query_count = $lookup['database.query_count'] ?? null;
		$query_time  = $lookup['database.query_time_total'] ?? null;
		$slow_count  = $lookup['database.slow_query_count'] ?? null;

		return array(
			'metrics'               => $metrics,
			'metric_map'            => $lookup,
			'savequeries_enabled'   => $savequeries_enabled,
			'savequeries_available' => $savequeries_available,
			'state'                 => $state,
			'state_label'           => match ( $state ) {
				'enabled'  => __( 'Active', 'wp-performance-doctor' ),
				'disabled' => __( 'Inactive', 'wp-performance-doctor' ),
				default    => __( 'Not detected', 'wp-performance-doctor' ),
			},
			'query_count'           => $query_count,
			'query_time'            => $query_time,
			'slow_count'            => $slow_count,
			'query_types'           => $query_types,
			'has_query_metrics'     => ( $query_count && $query_count['available'] )
				|| ( $query_time && $query_time['available'] )
				|| ( $slow_count && $slow_count['available'] ),
		);
	}

	/**
	 * Build a historical comparison for a specific profiling snapshot.
	 *
	 * Chooses the most recent older snapshot that has at least one comparable
	 * measurement, rather than blindly comparing against the second-newest
	 * snapshot (which may belong to a different request context or page).
	 *
	 * @param int   $current_snapshot_id Snapshot ID to compare.
	 * @param array $metrics_by_id      Canonical current metrics.
	 * @return array|null Prepared comparison data.
	 */
	private function build_comparison( int $current_snapshot_id, array $metrics_by_id ): ?array {
		$current = $this->storage->get_snapshot( $current_snapshot_id );
		if ( null === $current ) {
			return null;
		}

		$current_map = array();
		foreach ( $current['metrics'] as $metric ) {
			$identifier = $metric['metric_identifier'] ?? '';
			if ( '' !== $identifier ) {
				$current_map[ $identifier ] = $metric;
			}
		}

		$current_timestamp = $current['snapshot']['timestamp'] ?? '';
		$snapshots         = $this->storage->get_snapshots( 1, 15 );

		foreach ( $snapshots['items'] as $snapshot_row ) {
			$candidate_id = (int) ( $snapshot_row['id'] ?? 0 );
			if ( $candidate_id <= 0 || $candidate_id === $current_snapshot_id ) {
				continue;
			}

			// Only compare against older snapshots so "previous" can never be
			// newer than "current".
			$candidate_timestamp = $snapshot_row['timestamp'] ?? '';
			if ( '' !== $current_timestamp && '' !== $candidate_timestamp && $candidate_timestamp >= $current_timestamp ) {
				continue;
			}

			$candidate = $this->storage->get_snapshot( $candidate_id );
			if ( null === $candidate ) {
				continue;
			}

			$previous_map = array();
			foreach ( $candidate['metrics'] as $metric ) {
				$identifier = $metric['metric_identifier'] ?? '';
				if ( '' !== $identifier ) {
					$previous_map[ $identifier ] = $metric;
				}
			}

			$raw = ( new HistoricalComparison() )->compare( $previous_map, $current_map );

			$prepared = $this->prepare_comparison(
				array(
					'previous_snapshot' => $candidate['snapshot'],
					'comparisons'       => $raw,
				),
				$metrics_by_id
			);

			if ( ! empty( $prepared['comparisons'] ) ) {
				$prepared['current_snapshot'] = $current['snapshot'];
				return $prepared;
			}
		}

		return array(
			'current_snapshot'  => $current['snapshot'],
			'previous_snapshot' => null,
			'comparisons'       => array(),
			'has_comparison'    => false,
			'summary'           => array(
				'improved'       => 0,
				'worsened'       => 0,
				'unchanged'      => 0,
				'not_comparable' => 0,
				'total'          => 0,
			),
			'categories'        => array(),
		);
	}

	/**
	 * Prepare historical comparison results for display.
	 *
	 * Filters out non-comparable metrics and internal identifiers so the view
	 * only shows meaningful, valid comparisons. Zero values are treated as
	 * real measurements, not as unavailable data. Configuration and neutral
	 * values are labelled "Not comparable" instead of being assumed to be an
	 * improvement or regression.
	 *
	 * @param array $comparison Raw comparison result.
	 * @param array $metrics_by_id Current metrics keyed by identifier.
	 * @return array Prepared comparison data.
	 */
	private function prepare_comparison( array $comparison, array $metrics_by_id ): array {
		$previous_snapshot = $comparison['previous_snapshot'] ?? null;
		$raw_comparisons   = $comparison['comparisons'] ?? array();

		$comparisons = array();
		$seen_ids    = array();
		$summary     = array(
			'improved'       => 0,
			'worsened'       => 0,
			'unchanged'      => 0,
			'not_comparable' => 0,
		);
		$categories  = array();

		foreach ( $raw_comparisons as $comp ) {
			// Only show valid, comparable numeric measurements.
			if ( empty( $comp['available'] ) ) {
				continue;
			}
			$metric_id = $comp['metric_identifier'] ?? '';
			if ( '' === $metric_id ) {
				continue;
			}

			// Normalize the performance. prefix (possibly doubled by older
			// snapshots) before applying exclusions and deduplication.
			$logical_id = $metric_id;
			while ( \str_starts_with( $logical_id, 'performance.' ) ) {
				$logical_id = \substr( $logical_id, \strlen( 'performance.' ) );
			}

			if ( \in_array( $metric_id, self::COMPARISON_EXCLUDED_IDENTIFIERS, true )
				|| \in_array( $logical_id, self::COMPARISON_EXCLUDED_IDENTIFIERS, true )
				|| \str_ends_with( $logical_id, '_collected_at' )
				|| \str_ends_with( $logical_id, '.collected_at' )
			) {
				continue;
			}
			if ( isset( $seen_ids[ $logical_id ] ) ) {
				continue; // Already represented by a canonical identifier.
			}
			$seen_ids[ $logical_id ] = true;

			$previous = $comp['previous'] ?? null;
			$current  = $comp['current'] ?? null;
			$unit     = $comp['unit'] ?? '';

			if ( ! \is_numeric( $previous ) || ! \is_numeric( $current ) ) {
				continue;
			}

			$difference = $comp['difference'] ?? ( (float) $current - (float) $previous );
			$percent    = $comp['percent_change'] ?? null;
			if ( null !== $difference && null === $percent && 0.0 === (float) $previous ) {
				// Percent is undefined when the previous value is zero.
				$percent = null;
			}

			$direction        = $this->comparison_direction( $logical_id );
			$difference_value = (float) $difference;

			$previous_metric  = array_merge(
				$metrics_by_id[ $logical_id ] ?? $metrics_by_id[ $metric_id ] ?? array(),
				array(
					'metric_identifier'   => $logical_id,
					'metric_value'        => $previous,
					'unit'                => $unit,
					'availability_status' => 'available',
				)
			);
			$current_metric   = array_merge(
				$metrics_by_id[ $logical_id ] ?? $metrics_by_id[ $metric_id ] ?? array(),
				array(
					'metric_identifier'   => $logical_id,
					'metric_value'        => $current,
					'unit'                => $unit,
					'availability_status' => 'available',
				)
			);
			$previous_display = MetricFormatter::format( $previous_metric );
			$current_display  = MetricFormatter::format( $current_metric );

			// Treat values that render identically as unchanged so tiny,
			// display-level noise does not appear as a regression.
			if ( 0.0 === $difference_value || $previous_display === $current_display ) {
				$interpretation       = 'unchanged';
				$interpretation_label = __( 'Unchanged', 'wp-performance-doctor' );
				$interpretation_note  = '';
			} elseif ( null === $direction ) {
				$interpretation       = 'not_comparable';
				$interpretation_label = __( 'Not comparable', 'wp-performance-doctor' );
				$interpretation_note  = __( 'No directional interpretation is defined for this metric.', 'wp-performance-doctor' );
			} else {
				$improved             = 'lower' === $direction ? $difference_value < 0 : $difference_value > 0;
				$interpretation       = $improved ? 'improved' : 'worsened';
				$interpretation_label = $improved ? __( 'Improved', 'wp-performance-doctor' ) : __( 'Worsened', 'wp-performance-doctor' );
				$interpretation_note  = '';
			}

			if ( isset( $summary[ $interpretation ] ) ) {
				++$summary[ $interpretation ];
			}

			$category_slug                = \str_contains( $logical_id, '.' ) ? \strtolower( \strstr( $logical_id, '.', true ) ) : 'performance';
			$category_label               = self::CATEGORY_LABELS[ $category_slug ] ?? \ucfirst( $category_slug );
			$categories[ $category_slug ] = $category_label;

			$difference_display = 'unchanged' === $interpretation
				? __( '—', 'wp-performance-doctor' )
				: self::signed_value_display( $difference_value, $unit );
			$percent_display    = ( 'unchanged' === $interpretation || null === $percent )
				? __( '—', 'wp-performance-doctor' )
				: \sprintf( '%+.1f%%', $percent );

			$comparisons[] = array(
				'metric_identifier'    => $metric_id,
				'logical_identifier'   => $logical_id,
				'label'                => MetricFormatter::label( $logical_id ),
				'category'             => $category_slug,
				'category_label'       => $category_label,
				'unit'                 => $unit,
				'previous'             => (float) $previous,
				'current'              => (float) $current,
				'previous_display'     => $previous_display,
				'current_display'      => $current_display,
				'difference'           => $difference_value,
				'percent_change'       => $percent,
				'difference_display'   => $difference_display,
				'percent_display'      => $percent_display,
				'interpretation'       => $interpretation,
				'interpretation_label' => $interpretation_label,
				'interpretation_note'  => $interpretation_note,
				'direction'            => $direction,
				'is_change'            => \in_array( $interpretation, array( 'improved', 'worsened' ), true ),
				'search'               => \strtolower( MetricFormatter::label( $logical_id ) . ' ' . $logical_id . ' ' . $category_label ),
				'context'              => $comp['context'] ?? null,
				'page_url'             => $comp['page_url'] ?? null,
				'current_timestamp'    => $comp['current_timestamp'] ?? null,
				'previous_timestamp'   => $comp['previous_timestamp'] ?? null,
			);
		}

		// Sort: regressions first, then improvements, then unchanged and
		// non-comparable values.
		\usort(
			$comparisons,
			function ( $a, $b ) {
				$order = array(
					'worsened'       => 0,
					'improved'       => 1,
					'unchanged'      => 2,
					'not_comparable' => 3,
				);
				$oa    = $order[ $a['interpretation'] ] ?? 99;
				$ob    = $order[ $b['interpretation'] ] ?? 99;
				if ( $oa !== $ob ) {
					return $oa <=> $ob;
				}
				return \strcmp( $a['label'], $b['label'] );
			}
		);

		$summary['total'] = \count( $comparisons );
		\ksort( $categories );

		return array(
			'previous_snapshot' => $previous_snapshot,
			'comparisons'       => $comparisons,
			'has_comparison'    => ! empty( $comparisons ),
			'summary'           => $summary,
			'categories'        => $categories,
		);
	}

	/**
	 * Direction in which a metric should be interpreted.
	 *
	 * Only explicitly directional metrics receive an improved/worsened
	 * interpretation. Configuration, inventory, and other neutral values are
	 * intentionally left without a direction.
	 *
	 * @param string $identifier Logical metric identifier.
	 * @return string|null 'lower', 'higher', or null.
	 */
	private function comparison_direction( string $identifier ): ?string {
		$higher_is_better = array( 'php.opcache_hit_rate' );
		if ( \in_array( $identifier, $higher_is_better, true ) ) {
			return 'higher';
		}

		$lower_is_better = array(
			'php.memory_usage',
			'php.peak_memory_usage',
			'php.memory_limit',
			'system.load_average',
			'system.memory_usage',
			'system.peak_memory_usage',
			'php_profiler.request_duration',
			'php_profiler.memory_usage_end',
			'php_profiler.peak_memory_usage',
			'database.query_count',
			'database.query_time_total',
			'database.slow_query_count',
			'database.autoloaded_options_count',
			'database.autoloaded_options_size',
			'frontend.total_assets',
			'frontend.styles_count',
			'frontend.scripts_count',
			'frontend.external_asset_count',
			'frontend.largest_contentful_paint',
			'frontend.cumulative_layout_shift',
			'frontend.interaction_to_next_paint',
			'frontend.navigation_timing_ttfb',
		);
		if ( \in_array( $identifier, $lower_is_better, true ) ) {
			return 'lower';
		}

		$lower_prefixes = array(
			'frontend.navigation_timing_',
			'frontend.resource_',
			'frontend.css_resource_count',
			'frontend.js_resource_count',
			'frontend.image_resource_count',
			'frontend.font_resource_count',
			'frontend.external_resource_count',
		);
		foreach ( $lower_prefixes as $prefix ) {
			if ( \str_starts_with( $identifier, $prefix ) ) {
				return 'lower';
			}
		}

		return null;
	}

	/**
	 * Format a signed difference with its unit.
	 *
	 * @param float  $value Difference value.
	 * @param string $unit  Stored unit.
	 * @return string
	 */
	private static function signed_value_display( float $value, string $unit ): string {
		$metric  = array(
			'metric_identifier'   => 'comparison.difference',
			'metric_value'        => $value,
			'unit'                => $unit,
			'availability_status' => 'available',
		);
		$display = MetricFormatter::format( $metric );
		if ( $value > 0 && ! \str_starts_with( $display, '+' ) ) {
			$display = '+' . $display;
		}
		return $display;
	}

	/**
	 * Coerce a stored value to boolean truthiness.
	 *
	 * @param mixed $value Value to coerce.
	 * @return bool
	 */
	private function is_truthy( $value ): bool {
		if ( \is_bool( $value ) ) {
			return $value;
		}
		if ( \is_numeric( $value ) ) {
			return (float) $value > 0.0;
		}
		if ( \is_string( $value ) ) {
			return \in_array( \strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}
}
