<?php
/**
 * PHP execution time diagnostic rule.
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
 * Rule: PHP max_execution_time too restrictive.
 *
 * A value of 0 means unlimited and is not treated as a problem.
 */
final class ExecutionTimeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_max_execution_time',
				'title'            => __( 'PHP Max Execution Time Is Too Restrictive', 'wp-performance-doctor' ),
				'description'      => __( 'The PHP max_execution_time may be too low for reliable WordPress operation.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.max_execution_time' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'recommended_min_seconds' => 60,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$time = $this->get_metric( $context, 'system.max_execution_time' );

		if ( ! $context->has_metric( 'system.max_execution_time' ) ) {
			return $this->result_unavailable( __( 'PHP max_execution_time metric is not available.', 'wp-performance-doctor' ) );
		}

		// 0 means unlimited - not a problem.
		if ( 0 === $time ) {
			return $this->result_pass(
				__( 'PHP Execution Time Is Unlimited', 'wp-performance-doctor' ),
				__( 'max_execution_time is set to 0 (unlimited).', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => '>= 60 seconds',
				)
			);
		}

		$config      = $this->get_config();
		$min_seconds = (int) ( $config['recommended_min_seconds'] ?? 60 );

		if ( (int) $time < $min_seconds ) {
			return $this->result_fail(
				__( 'PHP Max Execution Time Is Too Restrictive', 'wp-performance-doctor' ),
				sprintf(
					__( 'max_execution_time is %1$d seconds, below the recommended %2$d seconds.', 'wp-performance-doctor' ),
					(int) $time,
					$min_seconds
				),
				array(
					'actual_value'   => (int) $time . ' seconds',
					'expected_value' => '>= ' . $min_seconds . ' seconds',
					'unit'           => 'seconds',
				),
				__( 'Long-running operations such as imports, updates, or complex queries may time out.', 'wp-performance-doctor' ),
				__( 'Increase max_execution_time to at least 60 seconds if your hosting environment permits it.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini or .user.ini and set max_execution_time = 60.', 'wp-performance-doctor' ),
						__( 'Or contact your hosting provider to increase the execution time limit.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'PHP Execution Time Is Adequate', 'wp-performance-doctor' ),
			sprintf(
				__( 'max_execution_time is %d seconds, meeting the recommended minimum.', 'wp-performance-doctor' ),
				(int) $time
			),
			array(
				'actual_value'   => (int) $time . ' seconds',
				'expected_value' => '>= ' . $min_seconds . ' seconds',
			)
		);
	}
}
