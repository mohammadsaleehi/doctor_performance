<?php
/**
 * PHP memory usage vs limit diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\PHP;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: High PHP memory usage relative to configured limit.
 *
 * Compares system.peak_memory_usage against system.memory_limit.
 * memory_limit = -1 (unlimited) is not treated as zero.
 */
final class MemoryUsageRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_memory_usage',
				'title'            => __( 'High PHP Memory Usage', 'wp-performance-doctor' ),
				'description'      => __( 'PHP memory usage is approaching the configured memory limit.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.memory_limit', 'system.peak_memory_usage' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_pct' => 80,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$limit = $this->get_metric( $context, 'system.memory_limit' );
		$peak  = $this->get_metric( $context, 'system.peak_memory_usage' );

		if ( ! $context->has_metric( 'system.memory_limit' ) || ! $context->has_metric( 'system.peak_memory_usage' ) ) {
			return $this->result_unavailable( __( 'Memory limit or peak usage metric is not available.', 'wp-performance-doctor' ) );
		}

		// null limit means -1 (unlimited) - skip.
		if ( null === $limit || null === $peak ) {
			return $this->result_pass(
				__( 'Memory Usage Within Limits', 'wp-performance-doctor' ),
				__( 'PHP memory limit is unlimited or peak usage data is unavailable.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => null,
					'expected_value' => null,
				)
			);
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold_pct'] ?? 80 );
		$limit_mb  = (int) ( $limit / ( 1024 * 1024 ) );
		$peak_mb   = (int) ( $peak / ( 1024 * 1024 ) );
		$usage_pct = $limit > 0 ? (int) ( ( $peak / $limit ) * 100 ) : 0;

		if ( $usage_pct >= $threshold ) {
			return $this->result_fail(
				__( 'High PHP Memory Usage Relative to Limit', 'wp-performance-doctor' ),
				sprintf(
					__( 'Peak memory usage is %1$d MB (%2$d%% of the %3$d MB limit).', 'wp-performance-doctor' ),
					$peak_mb,
					$usage_pct,
					$limit_mb
				),
				array(
					'actual_value'   => $peak_mb . ' MB',
					'expected_value' => '< ' . $threshold . '% of limit',
					'unit'           => 'MB',
					'usage_percent'  => $usage_pct,
				),
				__( 'The site is using a large portion of its available PHP memory, which may cause out-of-memory errors during traffic spikes.', 'wp-performance-doctor' ),
				__( 'Increase the PHP memory limit or identify and reduce memory-intensive operations.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Increase PHP memory_limit if server resources allow.', 'wp-performance-doctor' ),
						__( 'Profile slow requests to identify memory-intensive code.', 'wp-performance-doctor' ),
						__( 'Consider object caching to reduce repeated memory allocation.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Memory Usage Within Safe Range', 'wp-performance-doctor' ),
			sprintf(
				__( 'Peak memory usage is %1$d MB (%2$d%% of the %3$d MB limit).', 'wp-performance-doctor' ),
				$peak_mb,
				$usage_pct,
				$limit_mb
			),
			array(
				'actual_value'   => $peak_mb . ' MB',
				'expected_value' => '< ' . $threshold . '% of limit',
			)
		);
	}
}
