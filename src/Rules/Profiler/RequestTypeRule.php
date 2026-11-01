<?php
/**
 * Request type diagnostic rule.
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
 * Rule: Request type context.
 */
final class RequestTypeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'php_profiler.request_type',
				'title'            => __( 'Request Type Information', 'wp-performance-doctor' ),
				'description'      => __( 'Reports the type of the sampled request.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PROFILER,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'php_profiler.request_type' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$type = $this->get_metric( $context, 'php_profiler.request_type' );

		if ( ! $context->has_metric( 'php_profiler.request_type' ) ) {
			return $this->result_unavailable( __( 'Request type metric is not available.', 'wp-performance-doctor' ) );
		}

		$type_str = $type ? (string) $type : 'unknown';

		return $this->result_info(
			sprintf( __( 'Request Type: %s', 'wp-performance-doctor' ), ucfirst( $type_str ) ),
			sprintf( __( 'The sampled request was a %s request.', 'wp-performance-doctor' ), $type_str ),
			array(
				'actual_value'   => $type_str,
				'expected_value' => 'context',
			)
		);
	}
}
