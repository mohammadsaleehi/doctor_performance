<?php
/**
 * WordPress memory limit consistency diagnostic rule.
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
 * Rule: WordPress max memory limit inconsistencies.
 *
 * WP_MAX_MEMORY_LIMIT should be >= WP_MEMORY_LIMIT for admin areas.
 */
final class WpMaxMemoryLimitRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.wp_max_memory_limit',
				'title'            => __( 'WordPress Max Memory Limit Inconsistency', 'wp-performance-doctor' ),
				'description'      => __( 'WP_MAX_MEMORY_LIMIT may be lower than WP_MEMORY_LIMIT.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.wp_memory_limit', 'wordpress.wp_max_memory_limit' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$wp_limit = $this->get_metric( $context, 'wordpress.wp_memory_limit' );
		$wp_max   = $this->get_metric( $context, 'wordpress.wp_max_memory_limit' );

		if ( ! $context->has_metric( 'wordpress.wp_memory_limit' ) || ! $context->has_metric( 'wordpress.wp_max_memory_limit' ) ) {
			return $this->result_unavailable( __( 'WordPress memory limit metrics are not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $wp_limit || null === $wp_max ) {
			return $this->result_pass(
				__( 'WordPress Memory Limits Are Unlimited', 'wp-performance-doctor' ),
				__( 'One or both WordPress memory limits are set to unlimited.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => 'unlimited',
				)
			);
		}

		if ( (int) $wp_max < (int) $wp_limit ) {
			return $this->result_warning(
				__( 'WP_MAX_MEMORY_LIMIT Is Lower Than WP_MEMORY_LIMIT', 'wp-performance-doctor' ),
				sprintf(
					__( 'WP_MAX_MEMORY_LIMIT is %1$d MB but WP_MEMORY_LIMIT is %2$d MB. Admin areas may lack memory.', 'wp-performance-doctor' ),
					(int) ( $wp_max / ( 1024 * 1024 ) ),
					(int) ( $wp_limit / ( 1024 * 1024 ) )
				),
				array(
					'actual_value'   => (int) ( $wp_max / ( 1024 * 1024 ) ) . ' MB',
					'expected_value' => '>= ' . (int) ( $wp_limit / ( 1024 * 1024 ) ) . ' MB',
					'unit'           => 'MB',
				),
				__( 'The admin area may not have enough memory for complex operations.', 'wp-performance-doctor' ),
				__( 'Set WP_MAX_MEMORY_LIMIT to be equal to or greater than WP_MEMORY_LIMIT.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit wp-config.php and ensure WP_MAX_MEMORY_LIMIT >= WP_MEMORY_LIMIT.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'WordPress Memory Limits Are Consistent', 'wp-performance-doctor' ),
			__( 'WP_MAX_MEMORY_LIMIT is sufficient for WP_MEMORY_LIMIT.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'consistent',
				'expected_value' => 'consistent',
			)
		);
	}
}
