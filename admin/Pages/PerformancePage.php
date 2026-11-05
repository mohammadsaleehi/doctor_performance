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

		$data['metrics'] = $metrics;
		$data['has_data'] = ! empty( $metrics );

		// Normalize string 'null' values stored by older snapshots back to
		// real nulls so unavailable numeric metrics are not mistaken for
		// truthy values (e.g. 'null' > 0 evaluates to true in PHP).
		foreach ( $metrics as &$metric ) {
			$metric['metric_value'] = self::normalize_null_string( $metric['metric_value'] ?? null );
		}
		unset( $metric );

		// Re-key metrics by logical identifier so the analyzer and views can
		// look them up. Metrics collected by the PerformanceCollector are
		// stored under the 'performance' category with identifiers like
		// `performance.php.memory_limit`. Strip that prefix so the analyzer
		// (which expects `php.memory_limit`, `database.savequeries_enabled`,
		// etc.) can find the corresponding server-collected values. Dedicated
		// collector metrics (e.g. `system.memory_limit`) take precedence over
		// prefixed duplicates.
		$metrics_by_id = array();
		foreach ( $metrics as $metric ) {
			$identifier = $metric['metric_identifier'] ?? '';
			if ( '' === $identifier ) {
				continue;
			}
			$existing = $metrics_by_id[ $identifier ] ?? null;
			// For frontend browser metrics, prefer a row that identifies the
			// measured page (non-empty page_url) over one that does not, so
			// duplicated rows for the same identifier do not lose context.
			$has_page_url   = ! empty( $metric['page_url'] ?? '' );
			$existing_page  = ! empty( ( $existing['page_url'] ?? '' ) );
			if ( ! $existing || ( $has_page_url && ! $existing_page ) ) {
				$metrics_by_id[ $identifier ] = $metric;
			}
			if ( \str_starts_with( $identifier, 'performance.' ) ) {
				$stripped = \substr( $identifier, \strlen( 'performance.' ) );
				$existing_stripped = $metrics_by_id[ $stripped ] ?? null;
				$has_stripped_page = ! empty( $metric['page_url'] ?? '' );
				$existing_stripped_page = ! empty( ( $existing_stripped['page_url'] ?? '' ) );
				if ( ! $existing_stripped || ( $has_stripped_page && ! $existing_stripped_page ) ) {
					$metrics_by_id[ $stripped ] = $metric;
				}
			}
		}
		$data['metrics_by_id'] = $metrics_by_id;

		// Group metrics for the Collected Metrics section by logical category
		// (the segment before the first dot in the identifier).
		$data['grouped_metrics'] = $this->group_metrics_by_logical_category( $metrics );

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
			$data['bottlenecks']                       = $bottlenecks;
			$data['bottleneck_summary']                = $this->summarize_bottlenecks( $bottlenecks );
			$data['overview']['confirmed_bottlenecks'] = $bottlenecks['confirmed'] ?? 0;
		}

		// Overview values.
		$data['overview']['available_measurements'] = \count( $metrics );
		$data['overview']['latest_profile']         = $latest_session['started_at'] ?? null;
		if ( $latest_session ) {
			$data['latest_profile_timestamp'] = $latest_session['started_at'] ?? null;
		}
		$data['overview']['browser_measured'] = $this->has_frontend_metrics( $metrics_by_id );

		// Pre-computed section data.
		$data['frontend_metrics'] = $this->prepare_frontend_metrics( $metrics_by_id, $latest_session );
		$data['resource_metrics'] = $this->prepare_resource_metrics( $metrics_by_id );
		$data['database_metrics'] = $this->prepare_database_metrics( $metrics_by_id );

		// Historical comparison (current vs previous compatible snapshot).
		$latest_snapshot_id = $this->storage->get_latest_snapshot_id();
		if ( $latest_snapshot_id ) {
			$comparison         = new HistoricalComparison();
			$data['comparison'] = $comparison->compare_with_previous( $this->storage, $latest_snapshot_id );
			$data['comparison'] = $this->prepare_comparison( $data['comparison'], $metrics_by_id );
		}

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
		foreach ( $evidence as $key => $value ) {
			$formatted_evidence[ $key ] = array(
				'label'  => MetricFormatter::label( $key ),
				'value'  => $value,
				'metric' => $metrics_by_id[ $key ] ?? null,
			);
		}

		return array(
			'id'               => $bottleneck['id'] ?? '',
			'title'            => $bottleneck['title'] ?? '',
			'type'             => $type,
			// Severity / confidence are also kept for backward-compatible
			// consumers and for display badges.
			'severity'         => $severity,
			'confidence'       => $confidence,
			'severity_label'   => \WPPerformanceDoctor\Rules\Severity::label( $severity ),
			'confidence_label' => \ucfirst( $confidence ),
			'description'      => $bottleneck['description'] ?? '',
			'impact'           => $bottleneck['impact'] ?? '',
			'evidence'         => $formatted_evidence,
			'remediation'      => $bottleneck['remediation'] ?? array(),
			'limitations'      => $bottleneck['measurement_limitations'] ?? '',
			'category'         => $bottleneck['category'] ?? '',
		);
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
	 * @return array List of frontend metric display data.
	 */
	private function prepare_frontend_metrics( array $metrics_by_id, ?array $latest_session ): array {
		$target_url = $latest_session['target_url'] ?? '';
		$context    = $latest_session['request_context'] ?? 'admin';
		if ( empty( $target_url ) ) {
			$target_url = \home_url();
		}

		$metrics = array();
		foreach ( self::FRONTEND_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';
			$rating    = null;
			if ( $available && \is_numeric( $value ) ) {
				$rating = MetricFormatter::vital_rating( $identifier, (float) $value );
			}

			// Identify the measured page and context from the metric itself,
			// falling back to the profiling session when the metric row does
			// not carry them. Empty strings are treated as missing so the
			// fallback is applied.
			$page_url   = ! empty( $metric['page_url'] ?? '' ) ? $metric['page_url'] : $target_url;
			$context_r  = ! empty( $metric['measurement_context'] ?? '' ) ? $metric['measurement_context'] : $context;

			$metrics[] = array(
				'identifier'    => $identifier,
				'label'         => $label,
				'unit'          => $unit,
				'available'     => $available,
				'value'         => $value,
				'display'       => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'rating'        => $rating,
				'rating_label'  => MetricFormatter::vital_label( $rating ),
				'rating_class'  => MetricFormatter::vital_class( $rating ),
				'collected_at'  => $metric['collected_at'] ?? '',
				'page_url'      => $page_url,
				'context'       => $context_r,
				'context_label' => MetricFormatter::request_context_label( $context_r ),
			);
		}

		return $metrics;
	}

	/**
	 * Prepare resource analysis metrics for display.
	 *
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return array List of resource metric display data.
	 */
	private function prepare_resource_metrics( array $metrics_by_id ): array {
		$metrics = array();
		foreach ( self::RESOURCE_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';

			$metrics[] = array(
				'identifier'   => $identifier,
				'label'        => $label,
				'unit'         => $unit,
				'available'    => $available,
				'value'        => $value,
				'display'      => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'collected_at' => $metric['collected_at'] ?? '',
			);
		}

		return $metrics;
	}

	/**
	 * Prepare database profiling metrics for display.
	 *
	 * @param array $metrics_by_id Metrics keyed by identifier.
	 * @return array Database metric display data plus savequeries state.
	 */
	private function prepare_database_metrics( array $metrics_by_id ): array {
		$metrics               = array();
		$savequeries_enabled   = null;
		$savequeries_available = false;

		foreach ( self::DATABASE_METRIC_ORDER as $identifier => $label ) {
			$metric    = $metrics_by_id[ $identifier ] ?? null;
			$available = isset( $metric ) && 'available' === ( $metric['availability_status'] ?? '' );
			$value     = $available ? ( $metric['metric_value'] ?? null ) : null;
			$unit      = $metric['unit'] ?? '';

			if ( 'database.savequeries_enabled' === $identifier ) {
				$savequeries_available = $available;
				$savequeries_enabled   = $available && $this->is_truthy( $value );
			}

			$metrics[] = array(
				'identifier'   => $identifier,
				'label'        => $label,
				'unit'         => $unit,
				'available'    => $available,
				'value'        => $value,
				'display'      => $available ? MetricFormatter::format( $metric ) : __( 'Not measured', 'wp-performance-doctor' ),
				'collected_at' => $metric['collected_at'] ?? '',
			);
		}

		return array(
			'metrics'               => $metrics,
			'savequeries_enabled'   => $savequeries_enabled,
			'savequeries_available' => $savequeries_available,
		);
	}

	/**
	 * Prepare historical comparison results for display.
	 *
	 * Filters out non-comparable metrics and internal identifiers so the view
	 * only shows meaningful, valid comparisons. Zero values are treated as
	 * real measurements, not as unavailable data.
	 *
	 * @param array $comparison Raw comparison result.
	 * @param array $metrics_by_id Current metrics keyed by identifier.
	 * @return array Prepared comparison data.
	 */
	private function prepare_comparison( array $comparison, array $metrics_by_id ): array {
		$previous_snapshot = $comparison['previous_snapshot'] ?? null;
		$raw_comparisons   = $comparison['comparisons'] ?? array();

		$comparisons = array();
		foreach ( $raw_comparisons as $comp ) {
			// Only show valid, comparable metrics.
			if ( empty( $comp['available'] ) ) {
				continue;
			}
			$metric_id = $comp['metric_identifier'] ?? '';
			if ( '' === $metric_id ) {
				continue;
			}
			// Skip internal / non-trendable identifiers.
			if ( \in_array( $metric_id, self::COMPARISON_EXCLUDED_IDENTIFIERS, true ) ) {
				continue;
			}
			// Strip the performance. prefix for consistent labeling.
			$label_id = $metric_id;
			if ( \str_starts_with( $metric_id, 'performance.' ) ) {
				$label_id = \substr( $metric_id, \strlen( 'performance.' ) );
			}

			$previous = $comp['previous'] ?? null;
			$current  = $comp['current'] ?? null;
			$unit     = $comp['unit'] ?? '';

			$difference = $comp['difference'] ?? null;
			$percent    = $comp['percent_change'] ?? null;
			$improved   = $comp['improved'] ?? null;

			if ( null !== $difference && null === $percent && 0.0 === $previous ) {
				// Percent is undefined when the previous value is zero.
				$percent = null;
			}

			$interpretation       = null;
			$interpretation_label = __( 'Neutral', 'wp-performance-doctor' );
			if ( null === $improved ) {
				$interpretation       = 'neutral';
				$interpretation_label = __( 'Neutral', 'wp-performance-doctor' );
			} elseif ( $improved ) {
				$interpretation       = 'improved';
				$interpretation_label = __( 'Improved', 'wp-performance-doctor' );
			} else {
				$interpretation       = 'worsened';
				$interpretation_label = __( 'Worsened', 'wp-performance-doctor' );
			}

			// Format values using the metric's real availability. Both sides
			// are available when `available` is true, but format defensively.
			$previous_metric = array_merge(
				array(
					'metric_identifier'   => $metric_id,
					'metric_value'        => $previous,
					'unit'                => $unit,
					'availability_status' => 'available',
				),
				$metrics_by_id[ $metric_id ] ?? array()
			);
			$current_metric  = array_merge(
				array(
					'metric_identifier'   => $metric_id,
					'metric_value'        => $current,
					'unit'                => $unit,
					'availability_status' => 'available',
				),
				$metrics_by_id[ $label_id ] ?? $metrics_by_id[ $metric_id ] ?? array()
			);

			$comparisons[] = array(
				'metric_identifier'    => $metric_id,
				'label'                => MetricFormatter::label( $label_id ),
				'unit'                 => $unit,
				'previous'             => $previous,
				'current'              => $current,
				'previous_display'     => MetricFormatter::format( $previous_metric ),
				'current_display'      => MetricFormatter::format( $current_metric ),
				'difference'           => $difference,
				'percent_change'       => $percent,
				'difference_display'   => null !== $difference ? MetricFormatter::format(
					array_merge(
						$previous_metric,
						array(
							'metric_value'        => $difference,
							'availability_status' => 'available',
						)
					)
				) : __( 'N/A', 'wp-performance-doctor' ),
				'percent_display'      => null !== $percent ? sprintf( '%+.1f%%', $percent ) : __( 'N/A', 'wp-performance-doctor' ),
				'interpretation'       => $interpretation,
				'interpretation_label' => $interpretation_label,
				'context'              => $comp['context'] ?? null,
				'page_url'             => $comp['page_url'] ?? null,
				'current_timestamp'    => $comp['current_timestamp'] ?? null,
				'previous_timestamp'   => $comp['previous_timestamp'] ?? null,
			);
		}

		// Sort: interpreted changes first, then neutral, then by label.
		\usort(
			$comparisons,
			function ( $a, $b ) {
				$order = array(
					'improved' => 0,
					'worsened' => 1,
					'neutral'  => 2,
				);
				$oa    = $order[ $a['interpretation'] ] ?? 99;
				$ob    = $order[ $b['interpretation'] ] ?? 99;
				if ( $oa !== $ob ) {
					return $oa <=> $ob;
				}
				return \strcmp( $a['label'], $b['label'] );
			}
		);

		return array(
			'previous_snapshot' => $previous_snapshot,
			'comparisons'       => $comparisons,
			'has_comparison'    => ! empty( $comparisons ),
		);
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
