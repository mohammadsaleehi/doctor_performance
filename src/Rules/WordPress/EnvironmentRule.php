<?php
/**
 * WordPress environment diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\WordPress;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: WordPress environment configuration.
 *
 * Reports the detected environment type as context without flagging it
 * as a problem (development vs production is a configuration choice).
 */
final class EnvironmentRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.environment_type',
				'title'            => __( 'WordPress Environment Type', 'wp-performance-doctor' ),
				'description'      => __( 'The detected WordPress environment type.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.wordpress_environment_type' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$env = $this->get_metric( $context, 'wordpress.wordpress_environment_type' );

		if ( ! $context->has_metric( 'wordpress.wordpress_environment_type' ) ) {
			return $this->result_unavailable( __( 'Environment type metric is not available.', 'wp-performance-doctor' ) );
		}

		$env_str = $env ? (string) $env : 'unknown';

		return $this->result_info(
			__( 'WordPress Environment: ' . ucfirst( $env_str ), 'wp-performance-doctor' ),
			sprintf(
				__( 'The site is running in the %s environment.', 'wp-performance-doctor' ),
				$env_str
			),
			array(
				'actual_value'   => $env_str,
				'expected_value' => 'production or development',
			)
		);
	}
}
