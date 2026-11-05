<?php
/**
 * Dashboard page for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * Dashboard page for WP Performance Doctor admin.
 *
 * Displays system metrics, WordPress info, database stats, cache status,
 * PHP profiler data, and frontend asset information.
 */
final class DashboardPage {

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
	 * Rule registry.
	 *
	 * @var \WPPerformanceDoctor\Diagnostics\RuleRegistry|null
	 */
	private ?\WPPerformanceDoctor\Diagnostics\RuleRegistry $rule_registry = null;

	/**
	 * Health score calculator.
	 *
	 * @var \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator|null
	 */
	private ?\WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $health_score_calculator = null;

	/**
	 * Recommendation provider.
	 *
	 * @var \WPPerformanceDoctor\Diagnostics\RecommendationProvider|null
	 */
	private ?\WPPerformanceDoctor\Diagnostics\RecommendationProvider $recommendation_provider = null;

	/**
	 * Diagnostic service.
	 *
	 * @var \WPPerformanceDoctor\Diagnostics\DiagnosticService|null
	 */
	private ?\WPPerformanceDoctor\Diagnostics\DiagnosticService $diagnostic_service = null;

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
	 * Set rule registry.
	 *
	 * @param \WPPerformanceDoctor\Diagnostics\RuleRegistry $rule_registry Rule registry.
	 * @return self
	 */
	public function set_rule_registry( \WPPerformanceDoctor\Diagnostics\RuleRegistry $rule_registry ): self {
		$this->rule_registry = $rule_registry;
		return $this;
	}

	/**
	 * Set health score calculator.
	 *
	 * @param \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $calculator Health score calculator.
	 * @return self
	 */
	public function set_health_score_calculator( \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $calculator ): self {
		$this->health_score_calculator = $calculator;
		return $this;
	}

	/**
	 * Set recommendation provider.
	 *
	 * @param \WPPerformanceDoctor\Diagnostics\RecommendationProvider $provider Recommendation provider.
	 * @return self
	 */
	public function set_recommendation_provider( \WPPerformanceDoctor\Diagnostics\RecommendationProvider $provider ): self {
		$this->recommendation_provider = $provider;
		return $this;
	}

	/**
	 * Set diagnostic service.
	 *
	 * @param \WPPerformanceDoctor\Diagnostics\DiagnosticService $diagnostic_service Diagnostic service.
	 * @return self
	 */
	public function set_diagnostic_service( \WPPerformanceDoctor\Diagnostics\DiagnosticService $diagnostic_service ): self {
		$this->diagnostic_service = $diagnostic_service;
		return $this;
	}

	/**
	 * Get dashboard data for rendering.
	 *
	 * @return array{
	 *     enabled: bool,
	 *     interval: int,
	 *     system: array,
	 *     wordpress: array,
	 *     database: array,
	 *     cache: array,
	 *     php: array,
	 *     frontend: array,
	 *     has_data: bool,
	 *     health_score: int|null,
	 *     score_label: string,
	 *     score_sufficient: bool,
	 *     score_coverage: int,
	 *     severity_counts: array<string, int>,
	 *     open_findings: int,
	 *     recent_findings: array,
	 *     priority_recommendations: array,
	 *     latest_diagnostic_run: array|null,
	 *     kpi_cards: array
	 * }
	 */
	public function get_dashboard_data(): array {
		$data = [
			'enabled'                => false,
			'interval'               => 0,
			'system'                 => [],
			'wordpress'              => [],
			'database'               => [],
			'cache'                  => [],
			'php'                    => [],
			'frontend'               => [],
			'has_data'               => false,
			'health_score'           => null,
			'score_label'            => \__( 'Insufficient data', 'wp-performance-doctor' ),
			'score_sufficient'       => false,
			'score_coverage'         => 0,
			'severity_counts'        => array(
				'critical' => 0,
				'high'     => 0,
				'medium'   => 0,
				'low'      => 0,
				'info'     => 0,
			),
			'open_findings'          => 0,
			'recent_findings'        => array(),
			'priority_recommendations' => array(),
			'latest_diagnostic_run'  => null,
			'kpi_cards'              => array(),
			'rest_url'               => '',
			'nonce'                  => '',
		];

		if ( ! $this->service ) {
			return $data;
		}

		$data['enabled']  = $this->service->is_enabled();
		$data['interval'] = $this->service->get_interval();

		if ( ! $this->storage ) {
			return $data;
		}

		$data['system']    = $this->storage->get_latest_metrics_by_category( 'system' );
		$data['wordpress'] = $this->storage->get_latest_metrics_by_category( 'wordpress' );
		$data['database']  = $this->storage->get_latest_metrics_by_category( 'database' );
		$data['cache']     = $this->storage->get_latest_metrics_by_category( 'cache' );
		$data['php']       = $this->storage->get_latest_metrics_by_category( 'php' );
		$data['frontend']  = $this->storage->get_latest_metrics_by_category( 'frontend' );

		$data['has_data'] = ! empty( $data['system'] ) ||
			! empty( $data['wordpress'] ) ||
			! empty( $data['database'] ) ||
			! empty( $data['cache'] );

		$wpdb_available = isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'];

		// Health score.
		if ( $wpdb_available && $this->health_score_calculator && $this->diagnostic_service ) {
			$score_data = $this->get_health_score_data();
			$data['health_score']        = $score_data['score'];
			$data['score_label']         = $score_data['label'];
			$data['score_sufficient']    = $score_data['sufficient'];
			$data['score_coverage']      = $score_data['coverage'];
		}

		// Finding summary.
		if ( $wpdb_available ) {
			$severity_counts = $this->storage->get_finding_counts_by_severity();
			$data['severity_counts'] = $severity_counts;
			$data['open_findings']   = array_sum( $severity_counts );

			// Recent findings.
			$findings_result = $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 5 );
			$data['recent_findings'] = $findings_result['items'];
		}

		// Priority recommendations.
		if ( $wpdb_available && $this->recommendation_provider ) {
			$all_findings = $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 10 );
			$data['priority_recommendations'] = $this->recommendation_provider->from_findings( $all_findings['items'] );
		}

		// Latest diagnostic run.
		if ( $wpdb_available ) {
			$latest_run = $this->storage->get_latest_diagnostic_run();
			if ( $latest_run ) {
				$data['latest_diagnostic_run'] = $latest_run;
			}
		}

		// KPI cards.
		$data['kpi_cards'] = $this->get_kpi_cards();

		// REST URL and nonce for collection.
		$data['rest_url'] = \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) );
		$data['nonce']    = \wp_create_nonce( 'wpd_collect' );

		return $data;
	}

	/**
	 * Get health score data from calculator.
	 *
	 * @return array{
	 *     score: int|null,
	 *     label: string,
	 *     sufficient: bool,
	 *     coverage: int
	 * }
	 */
	private function get_health_score_data(): array {
		$open_findings = $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 1000 );
		$findings      = $open_findings['items'];

		$latest_run = $this->storage->get_latest_diagnostic_run();
		$rules_evaluated = $latest_run['rules_evaluated'] ?? 0;

		$score_details = $this->health_score_calculator->calculate( $findings, (int) $rules_evaluated );

		return array(
			'score'      => $score_details['score'],
			'label'      => HealthScoreCalculator::score_label( $score_details['score'] ),
			'sufficient' => $score_details['sufficient'],
			'coverage'   => $score_details['coverage'],
		);
	}

	/**
	 * Build KPI cards from monitoring metrics.
	 *
	 * @return array<string, array{
	 *     title: string,
	 *     value: string|int,
	 *     label: string,
	 *     status: string
	 * }>
	 */
	private function get_kpi_cards(): array {
		$cards = array();

		$system    = $this->storage->get_latest_metrics_by_category( 'system' );
		$wordpress = $this->storage->get_latest_metrics_by_category( 'wordpress' );
		$database  = $this->storage->get_latest_metrics_by_category( 'database' );
		$cache     = $this->storage->get_latest_metrics_by_category( 'cache' );

		$cards['system'] = $this->build_kpi_card(
			\__( 'System', 'wp-performance-doctor' ),
			$this->extract_metric_value( $system, 'system.php_version', 'N/A' ),
			\__( 'PHP version', 'wp-performance-doctor' ),
			'neutral'
		);

		$cards['wordpress'] = $this->build_kpi_card(
			\__( 'WordPress', 'wp-performance-doctor' ),
			$this->extract_metric_value( $wordpress, 'wordpress.wordpress_version', 'N/A' ),
			\__( 'WP version', 'wp-performance-doctor' ),
			'neutral'
		);

		$cards['database'] = $this->build_kpi_card(
			\__( 'Database', 'wp-performance-doctor' ),
			$this->extract_metric_value( $database, 'database.database_size', 'N/A' ),
			\__( 'Database size', 'wp-performance-doctor' ),
			'neutral'
		);

		$cards['cache'] = $this->build_kpi_card(
			\__( 'Cache', 'wp-performance-doctor' ),
			$this->extract_metric_value( $cache, 'cache.opcache_enabled', 'N/A' ),
			\__( 'OPcache status', 'wp-performance-doctor' ),
			'neutral'
		);

		return $cards;
	}

	/**
	 * Build a single KPI card entry.
	 *
	 * @param string $title Card title.
	 * @param string|int $value Card value.
	 * @param string $label Card label.
	 * @param string $status Status color key.
	 * @return array<string, string|int>
	 */
	private function build_kpi_card( string $title, $value, string $label, string $status ): array {
		return array(
			'title'  => $title,
			'value'  => $value,
			'label'  => $label,
			'status' => $status,
		);
	}

	/**
	 * Extract a metric value by identifier from a metrics array.
	 *
	 * @param array $metrics Metrics array.
	 * @param string $identifier Metric identifier.
	 * @param string $default Default value if not found.
	 * @return string|int
	 */
	private function extract_metric_value( array $metrics, string $identifier, string $default ) {
		foreach ( $metrics as $metric ) {
			if ( ( $metric['metric_identifier'] ?? '' ) === $identifier ) {
				return $metric['metric_value'] ?? $default;
			}
		}
		return $default;
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_dashboard_data();
		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/dashboard.php';
	}
}
