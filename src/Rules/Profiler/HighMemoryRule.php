<?php
/**
 * High PHP memory consumption diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\Profiler;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: High peak memory consumption.
 */
final class HighMemoryRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'php_profiler.high_memory',
				'title'            => __( 'High Peak Memory Usage', 'wp-performance-doctor' ),
				'description'      => __( 'The sampled request used a high amount of memory.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PROFILER,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'php_profiler.peak_memory_usage', 'system.memory_limit' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_pct' => 80,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$peak  = $this->get_metric( $context, 'php_profiler.peak_memory_usage' );
		$limit = $this->get_metric( $context, 'system.memory_limit' );

		if ( ! $context->has_metric( 'php_profiler.peak_memory_usage' ) ) {
			return $this->result_unavailable( __( 'Peak memory usage metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $peak ) {
			return $this->result_unavailable( __( 'Peak memory usage data is not available.', 'wp-performance-doctor' ) );
		}

		$peak_mb = (int) ( (int) $peak / ( 1024 * 1024 ) );

		if ( $context->has_metric( 'system.memory_limit' ) && null !== $limit ) {
			$limit_int = (int) $limit;
			$pct       = $limit_int > 0 ? (int) ( ( (int) $peak / $limit_int ) * 100 ) : 0;
			$config    = $this->get_config();
			$threshold = (int) ( $config['warning_threshold_pct'] ?? 80 );

			if ( $pct >= $threshold ) {
				return $this->result_warning(
					__( 'High Memory Usage Relative to Limit', 'wp-performance-doctor' ),
					sprintf(
						__( 'Peak memory usage is %1$d MB (%2$d%% of the %3$d MB PHP limit).', 'wp-performance-doctor' ),
						$peak_mb,
						$pct,
						(int) ( $limit_int / ( 1024 * 1024 ) )
					),
					array(
						'actual_value'   => $peak_mb . ' MB',
						'expected_value' => '< ' . $threshold . '% of limit',
						'unit'           => 'MB',
						'usage_percent'  => $pct,
					),
					__( 'The request is using a large portion of available PHP memory.', 'wp-performance-doctor' ),
					__( 'Identify memory-intensive operations and consider increasing the memory limit.', 'wp-performance-doctor' ),
					array(
						'steps'                  => array(
							__( 'Profile requests to identify memory-intensive code.', 'wp-performance-doctor' ),
							__( 'Consider increasing PHP memory_limit if server resources allow.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
				);
			}

			return $this->result_pass(
				__( 'Memory Usage Is Within Safe Range', 'wp-performance-doctor' ),
				sprintf(
					__( 'Peak memory usage is %1$d MB (%2$d%% of limit).', 'wp-performance-doctor' ),
					$peak_mb,
					$pct
				),
				array(
					'actual_value'   => $peak_mb . ' MB',
					'expected_value' => '< ' . (int) ( $config['warning_threshold_pct'] ?? 80 ) . '% of limit',
				)
			);
		}

		return $this->result_info(
			__( 'Peak Memory Usage: ' . $peak_mb . ' MB', 'wp-performance-doctor' ),
			sprintf( __( 'The request used %d MB of memory.', 'wp-performance-doctor' ), $peak_mb ),
			array(
				'actual_value'   => $peak_mb . ' MB',
				'expected_value' => 'context',
			)
		);
	}
}
