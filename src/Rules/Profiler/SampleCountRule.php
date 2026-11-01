<?php
/**
 * Profiler sample count diagnostic rule.
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
 * Rule: Insufficient profiler samples for trend analysis.
 *
 * Reports when there is not enough data for meaningful performance trends.
 */
final class SampleCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'php_profiler.sample_count',
				'title'            => __( 'Insufficient Profiler Samples', 'wp-performance-doctor' ),
				'description'      => __( 'Not enough profiler samples have been collected for trend analysis.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PROFILER,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'php_profiler.sampled', 'php_profiler.sample_rate' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$sampled = $this->get_metric( $context, 'php_profiler.sampled' );
		$rate    = $this->get_metric( $context, 'php_profiler.sample_rate' );

		if ( ! $context->has_metric( 'php_profiler.sampled' ) ) {
			return $this->result_unavailable( __( 'Profiler sampling metric is not available.', 'wp-performance-doctor' ) );
		}

		$sampled_truthy = $this->is_truthy( $sampled );
		$rate_val       = null !== $rate ? (float) $rate : 0.0;

		if ( ! $sampled_truthy && $rate_val > 0 ) {
			return $this->result_info(
				__( 'Request Not Sampled', 'wp-performance-doctor' ),
				__( 'The current request was not sampled for profiling.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'not sampled',
					'expected_value' => 'sampled',
				)
			);
		}

		if ( $rate_val <= 0 ) {
			return $this->result_info(
				__( 'Profiler Sampling Disabled', 'wp-performance-doctor' ),
				__( 'PHP profiler sampling is disabled. Enable it to collect request performance data.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'disabled',
					'expected_value' => 'enabled',
				)
			);
		}

		return $this->result_pass(
			__( 'Profiler Is Active', 'wp-performance-doctor' ),
			sprintf( __( 'Profiler sampling is enabled at %.0f%%.', 'wp-performance-doctor' ), $rate_val * 100 ),
			array(
				'actual_value'   => $rate_val * 100 . '%',
				'expected_value' => 'enabled',
			)
		);
	}

	/**
	 * Coerce a value to boolean truthiness.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	protected function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value > 0.0;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}
}
