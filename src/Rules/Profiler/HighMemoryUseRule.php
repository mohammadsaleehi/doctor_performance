<?php
/**
 * PHP request execution time diagnostic rule.
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
 * Rule: Abnormally high memory use in requests.
 */
final class HighMemoryUseRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'php_profiler.memory_usage_end',
				'title'            => __( 'High Request Memory Usage', 'wp-performance-doctor' ),
				'description'      => __( 'The request ended with high memory usage.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PROFILER,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'php_profiler.memory_usage_end' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_mb' => 128,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$end_mem = $this->get_metric( $context, 'php_profiler.memory_usage_end' );

		if ( ! $context->has_metric( 'php_profiler.memory_usage_end' ) ) {
			return $this->result_unavailable( __( 'Memory usage end metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $end_mem ) {
			return $this->result_unavailable( __( 'Memory usage end data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold_mb'] ?? 128 );
		$end_mb    = (int) ( (int) $end_mem / ( 1024 * 1024 ) );

		if ( $end_mb >= $threshold ) {
			return $this->result_warning(
				__( 'High Request Memory Usage', 'wp-performance-doctor' ),
				sprintf( __( 'The request used %d MB of memory at completion.', 'wp-performance-doctor' ), $end_mb ),
				array(
					'actual_value'   => $end_mb . ' MB',
					'expected_value' => '< ' . $threshold . ' MB',
					'unit'           => 'MB',
				),
				__( 'High memory usage at request completion may indicate memory leaks or inefficient code.', 'wp-performance-doctor' ),
				__( 'Investigate memory-intensive operations in the request.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Profile the request to identify memory-intensive code.', 'wp-performance-doctor' ),
						__( 'Review plugins and theme for memory leaks.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Request Memory Usage Is Normal', 'wp-performance-doctor' ),
			sprintf( __( 'The request used %d MB of memory at completion.', 'wp-performance-doctor' ), $end_mb ),
			array(
				'actual_value'   => $end_mb . ' MB',
				'expected_value' => '< ' . $threshold . ' MB',
			)
		);
	}
}
