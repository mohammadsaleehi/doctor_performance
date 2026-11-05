<?php
/**
 * Diagnostics page for WP Performance Doctor admin.
 *
 * Displays diagnostic findings, health score, severity summary, run history,
 * and provides the Run Diagnostics action backed by the DiagnosticService.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Rules\RuleStatus;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Diagnostics page displaying findings, health score, and diagnostic runs.
 */
final class DiagnosticsPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Diagnostic service.
	 *
	 * @var \WPPerformanceDoctor\Diagnostics\DiagnosticService|null
	 */
	private ?\WPPerformanceDoctor\Diagnostics\DiagnosticService $diagnostic_service = null;

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
	 * @param \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $health_score_calculator Health score calculator.
	 * @return self
	 */
	public function set_health_score_calculator( \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $health_score_calculator ): self {
		$this->health_score_calculator = $health_score_calculator;
		return $this;
	}

	/**
	 * Render the diagnostics page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_diagnostics_data();

		\wp_enqueue_script( 'wp-performance-doctor-admin' );

		\wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdDiagnosticsPage',
			array(
				'restUrl'     => esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/run' ) ),
				'nonce'       => wp_create_nonce( 'wpd_collect' ),
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'     => array(
					'runDiagnostics' => __( 'Run Diagnostics', 'wp-performance-doctor' ),
					'running'        => __( 'Running diagnostics…', 'wp-performance-doctor' ),
					'complete'       => __( 'Diagnostics complete.', 'wp-performance-doctor' ),
					'error'          => __( 'Diagnostics failed.', 'wp-performance-doctor' ),
					'acknowledge'    => __( 'Acknowledge', 'wp-performance-doctor' ),
					'ignore'         => __( 'Ignore', 'wp-performance-doctor' ),
					'restore'        => __( 'Restore', 'wp-performance-doctor' ),
					'viewDetails'    => __( 'View details', 'wp-performance-doctor' ),
					'close'          => __( 'Close', 'wp-performance-doctor' ),
					'noFindings'     => __( 'No findings match your filters.', 'wp-performance-doctor' ),
					'runNeeded'      => __( 'No diagnostic data has been collected yet. Run diagnostics to analyze your site.', 'wp-performance-doctor' ),
				),
			)
		);

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/diagnostics.php';
	}

	/**
	 * Get diagnostics page data including summary, findings, and runs.
	 *
	 * @return array<string, mixed>
	 */
	private function get_diagnostics_data(): array {
		$data = array(
			'diagnostic_service_available' => null !== $this->diagnostic_service,
			'is_running'                   => false,
			'summary'                      => array(
				'open_findings'         => 0,
				'findings_detected'     => 0,
				'health_score'          => null,
				'rules_evaluated'       => 0,
				'last_run_at'           => null,
				'last_run_status'       => null,
				'severity_counts'       => array(
					'critical' => 0,
					'high'     => 0,
					'medium'   => 0,
					'low'      => 0,
					'info'     => 0,
				),
			),
			'findings'                     => array(),
			'findings_total'               => 0,
			'findings_page'                => 1,
			'findings_per_page'            => 20,
			'runs'                         => array(),
			'runs_total'                   => 0,
			'active_filters'               => array(),
		);

		if ( ! $this->diagnostic_service ) {
			return $data;
		}

		try {
			$data['is_running'] = $this->diagnostic_service->is_running();
		} catch ( \Throwable $e ) {
			$data['is_running'] = false;
		}

		// Get summary from storage via REST-compatible calls.
		$storage = null;
		// We can access storage through the diagnostic service indirectly,
		// but here we use the global storage pattern via the service's storage.
		// Since DiagnosticService doesn't expose storage, we use a fresh Storage
		// instance for read-only summary data.
		$storage = new \WPPerformanceDoctor\Monitoring\Storage();

		try {
			$latest_run = $storage->get_latest_diagnostic_run();
			if ( $latest_run ) {
				$data['summary']['last_run_at']     = $latest_run['completed_at'] ?? null;
				$data['summary']['last_run_status'] = $latest_run['status'] ?? null;
				$data['summary']['rules_evaluated'] = (int) ( $latest_run['rules_evaluated'] ?? 0 );
				$data['summary']['health_score']    = $latest_run['health_score'] ?? null;
				$data['summary']['findings_detected'] = (int) ( $latest_run['findings_count'] ?? 0 );

				// Build score breakdown from run severity counts.
				$data['summary']['score_breakdown'] = array();
				$total_deduction                    = 0;
				foreach ( array( 'critical', 'high', 'medium', 'low', 'info' ) as $sev ) {
					$count = (int) ( $latest_run[ $sev . '_count' ] ?? 0 );
					if ( $count > 0 ) {
						$data['summary']['score_breakdown'][ $sev ] = array(
							'count'     => $count,
							'deduction' => 0, // Deduct amounts computed by HealthScoreCalculator; shown as counts here.
						);
					}
				}
				$data['summary']['total_deduction'] = $total_deduction;
			}
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentional: suppress errors for read-only summary.
		} catch ( \Throwable $e ) {
			// phpcs:ignore Squiz.Commenting.EmptyCatchComment.Missing -- Intentional: read-only summary, suppress errors.
		}

		try {
			$severity_counts                    = $storage->get_finding_counts_by_severity();
			$data['summary']['severity_counts'] = array_merge(
				array(
					'critical' => 0,
					'high'     => 0,
					'medium'   => 0,
					'low'      => 0,
					'info'     => 0,
				),
				$severity_counts
			);
			$data['summary']['open_findings']   = (int) $storage->get_open_finding_count();
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentional: suppress errors for read-only counts.
		} catch ( \Throwable $e ) {
			// phpcs:ignore Squiz.Commenting.EmptyCatchComment.Missing -- Intentional: read-only counts, suppress errors.
		}

		// Get paginated findings with filters from query params.
		$page_raw     = filter_input( INPUT_GET, 'page', FILTER_VALIDATE_INT );
		$page         = ( false === $page_raw || null === $page_raw ) ? 1 : max( 1, (int) $page_raw );
		$per_page_raw = filter_input( INPUT_GET, 'per_page', FILTER_VALIDATE_INT );
		$per_page     = ( false === $per_page_raw || null === $per_page_raw ) ? 20 : max( 1, min( 100, (int) $per_page_raw ) );

		$filters  = array();
		$severity = filter_input( INPUT_GET, 'severity', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$category = filter_input( INPUT_GET, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status   = filter_input( INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$search   = filter_input( INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( false !== $severity && null !== $severity && '' !== $severity ) {
			$filters['severity'] = $severity;
		}
		if ( false !== $category && null !== $category && '' !== $category ) {
			$filters['category'] = $category;
		}
		if ( false !== $status && null !== $status && '' !== $status ) {
			$filters['status'] = $status;
		}
		if ( false !== $search && null !== $search && '' !== $search ) {
			$filters['search'] = $search;
		}

		$data['active_filters'] = $filters;

		try {
			$result                    = $storage->get_diagnostic_findings( $filters, $page, $per_page );
			$data['findings']          = $result['items'];
			$data['findings_total']    = (int) $result['total'];
			$data['findings_page']     = $page;
			$data['findings_per_page'] = $per_page;
		} catch ( \Throwable $e ) {
			$data['findings']       = array();
			$data['findings_total'] = 0;
		}

		// Get recent diagnostic runs.
		try {
			$runs_result        = $storage->get_diagnostic_runs( 1, 10 );
			$data['runs']       = $runs_result['items'];
			$data['runs_total'] = (int) $runs_result['total'];
		} catch ( \Throwable $e ) {
			$data['runs']       = array();
			$data['runs_total'] = 0;
		}

		return $data;
	}

	/**
	 * Get severity label for display.
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	public static function severity_label( string $severity ): string {
		return match ( $severity ) {
			Severity::CRITICAL => __( 'Critical', 'wp-performance-doctor' ),
			Severity::HIGH     => __( 'High', 'wp-performance-doctor' ),
			Severity::MEDIUM   => __( 'Medium', 'wp-performance-doctor' ),
			Severity::LOW      => __( 'Low', 'wp-performance-doctor' ),
			Severity::INFO     => __( 'Info', 'wp-performance-doctor' ),
			default            => $severity,
		};
	}

	/**
	 * Get status label for display.
	 *
	 * @param string $status Status level.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		return match ( $status ) {
			RuleStatus::OPEN        => __( 'Open', 'wp-performance-doctor' ),
			RuleStatus::ACKNOWLEDGED => __( 'Acknowledged', 'wp-performance-doctor' ),
			RuleStatus::IGNORED     => __( 'Ignored', 'wp-performance-doctor' ),
			RuleStatus::RESOLVED    => __( 'Resolved', 'wp-performance-doctor' ),
			default               => $status,
		};
	}

	/**
	 * Get status CSS class.
	 *
	 * @param string $status Status level.
	 * @return string
	 */
	public static function status_class( string $status ): string {
		return match ( $status ) {
			RuleStatus::OPEN        => 'wpd-finding-status--open',
			RuleStatus::ACKNOWLEDGED => 'wpd-finding-status--acknowledged',
			RuleStatus::IGNORED     => 'wpd-finding-status--ignored',
			RuleStatus::RESOLVED    => 'wpd-finding-status--resolved',
			default               => 'wpd-finding-status--unknown',
		};
	}

	/**
	 * Get severity CSS class.
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	public static function severity_class( string $severity ): string {
		return match ( $severity ) {
			Severity::CRITICAL => 'wpd-finding-severity--critical',
			Severity::HIGH     => 'wpd-finding-severity--high',
			Severity::MEDIUM   => 'wpd-finding-severity--medium',
			Severity::LOW      => 'wpd-finding-severity--low',
			Severity::INFO     => 'wpd-finding-severity--info',
			default            => 'wpd-finding-severity--info',
		};
	}
}
