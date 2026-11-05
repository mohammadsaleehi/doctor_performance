<?php
/**
 * Recommendations page for WP Performance Doctor admin.
 *
 * Displays prioritized recommendations generated from diagnostic findings,
 * with summary counts, priority groups, and links to related findings.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RecommendationProvider;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * Recommendations page displaying prioritized remediation guidance.
 */
final class RecommendationsPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Monitoring storage.
	 *
	 * @var \WPPerformanceDoctor\Monitoring\Storage|null
	 */
	private ?\WPPerformanceDoctor\Monitoring\Storage $storage = null;

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
	 * Set recommendation provider.
	 *
	 * @param \WPPerformanceDoctor\Diagnostics\RecommendationProvider $recommendation_provider Recommendation provider.
	 * @return self
	 */
	public function set_recommendation_provider( \WPPerformanceDoctor\Diagnostics\RecommendationProvider $recommendation_provider ): self {
		$this->recommendation_provider = $recommendation_provider;
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
	 * Set storage.
	 *
	 * @param \WPPerformanceDoctor\Monitoring\Storage $storage Monitoring storage.
	 * @return self
	 */
	public function set_storage( \WPPerformanceDoctor\Monitoring\Storage $storage ): self {
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
	 * @param \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $health_score_calculator Health score calculator.
	 * @return self
	 */
	public function set_health_score_calculator( \WPPerformanceDoctor\Diagnostics\HealthScoreCalculator $health_score_calculator ): self {
		$this->health_score_calculator = $health_score_calculator;
		return $this;
	}

	/**
	 * Render the recommendations page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_recommendations_data();

		\wp_enqueue_script( 'wp-performance-doctor-admin' );

		\wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdRecommendationsPage',
			array(
				'restUrl'         => esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/recommendations' ) ),
				'nonce'           => wp_create_nonce( 'wpd_collect' ),
				'wpRestNonce'     => wp_create_nonce( 'wp_rest' ),
				'recommendations' => $data['recommendations'],
				'summary'         => $data['summary'],
				'strings'         => array(
					'viewFinding'       => __( 'View finding', 'wp-performance-doctor' ),
					'viewEvidence'      => __( 'View evidence', 'wp-performance-doctor' ),
					'viewDetails'       => __( 'View details', 'wp-performance-doctor' ),
					'close'             => __( 'Close', 'wp-performance-doctor' ),
					'noRecommendations' => __( 'No recommendations. Run diagnostics to generate recommendations based on your site data.', 'wp-performance-doctor' ),
					'loading'           => __( 'Loading…', 'wp-performance-doctor' ),
					'noEvidence'        => __( 'No evidence available.', 'wp-performance-doctor' ),
					'noSteps'           => __( 'No remediation steps available.', 'wp-performance-doctor' ),
				),
			)
		);

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/recommendations.php';
	}

	/**
	 * Get recommendations page data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_recommendations_data(): array {
		$data = array(
			'recommendations'       => array(),
			'recommendations_total' => 0,
			'summary'               => array(
				'total'                => 0,
				'high_priority'        => 0,
				'medium_priority'      => 0,
				'low_priority'         => 0,
				'hosting_access'       => 0,
				'developer_assistance' => 0,
				'wordpress_only'       => 0,
			),
			'active_filters'        => array(),
		);

		if ( ! $this->recommendation_provider || ! $this->storage ) {
			return $data;
		}

		// Get open findings and generate recommendations.
		try {
			$findings_result               = $this->storage->get_diagnostic_findings(
				array( 'status' => 'open' ),
				1,
				1000
			);
			$findings                      = $findings_result['items'];
			$data['recommendations_total'] = (int) $findings_result['total'];

			$recommendations         = $this->recommendation_provider->from_findings( $findings );
			$data['recommendations'] = $recommendations;

			// Compute summary.
			$summary = array(
				'total'                => count( $recommendations ),
				'high_priority'        => 0,
				'medium_priority'      => 0,
				'low_priority'         => 0,
				'hosting_access'       => 0,
				'developer_assistance' => 0,
				'wordpress_only'       => 0,
			);

			foreach ( $recommendations as $rec ) {
				$sev = $rec['severity'] ?? 'info';
				if ( in_array( $sev, array( 'critical', 'high' ), true ) ) {
					++$summary['high_priority'];
				} elseif ( 'medium' === $sev ) {
					++$summary['medium_priority'];
				} else {
					++$summary['low_priority'];
				}

				$remediation = $rec['remediation'] ?? array();
				if ( ! empty( $remediation['requires_hosting_access'] ) || ! empty( $remediation['server_access_required'] ) ) {
					++$summary['hosting_access'];
				}
				if ( 'hard' === ( $rec['difficulty'] ?? 'medium' ) || ! empty( $remediation['requires_developer'] ) ) {
					++$summary['developer_assistance'];
				}
				if ( in_array( $rec['category'] ?? '', array( 'wordpress', 'plugin_theme' ), true ) && empty( $remediation['requires_hosting_access'] ) ) {
					++$summary['wordpress_only'];
				}
			}

			$data['summary'] = $summary;
		} catch ( \Throwable $e ) {
			$data['recommendations']       = array();
			$data['recommendations_total'] = 0;
		}

		return $data;
	}
}
