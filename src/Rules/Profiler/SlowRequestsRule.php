<?php
/**
 * Slow PHP requests diagnostic rule.
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
 * Rule: Slow sampled PHP requests.
 *
 * Checks php_profiler.request_duration against a threshold.
 */
final class SlowRequestsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'php_profiler.slow_requests',
				'title'            => __( 'Slow PHP Requests Detected', 'wp-performance-doctor' ),
				'description'      => __( 'Some sampled PHP requests exceeded the duration threshold.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PROFILER,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'php_profiler.request_duration', 'php_profiler.sampled' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_seconds' => 2.0,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$duration = $this->get_metric( $context, 'php_profiler.request_duration' );
		$sampled  = $this->get_metric( $context, 'php_profiler.sampled' );

		if ( ! $context->has_metric( 'php_profiler.request_duration' ) ) {
			return $this->result_unavailable( __( 'Request duration metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $duration ) {
			return $this->result_unavailable( __( 'Request duration data is not available (request may be excluded or unsampled).', 'wp-performance-doctor' ) );
		}

		$config       = $this->get_config();
		$threshold    = (float) ( $config['warning_threshold_seconds'] ?? 2.0 );
		$duration_sec = (float) $duration;

		if ( $duration_sec > $threshold ) {
			return $this->result_warning(
				__( 'Slow PHP Request Detected', 'wp-performance-doctor' ),
				sprintf(
					__( 'The sampled request took %.2f seconds to complete.', 'wp-performance-doctor' ),
					$duration_sec
				),
				array(
					'actual_value'   => number_format( $duration_sec, 2 ) . ' s',
					'expected_value' => '< ' . number_format( $threshold, 2 ) . ' s',
					'unit'           => 'seconds',
				),
				__( 'Slow requests may indicate performance bottlenecks in plugins, theme, or database queries.', 'wp-performance-doctor' ),
				__( 'Investigate slow requests using the profiler data.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Review the profiler samples to identify slow requests.', 'wp-performance-doctor' ),
						__( 'Use a profiling tool like Query Monitor to investigate further.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'PHP Request Duration Is Acceptable', 'wp-performance-doctor' ),
			sprintf( __( 'The sampled request took %.2f seconds.', 'wp-performance-doctor' ), $duration_sec ),
			array(
				'actual_value'   => number_format( $duration_sec, 2 ) . ' s',
				'expected_value' => '< ' . number_format( $threshold, 2 ) . ' s',
			)
		);
	}
}
