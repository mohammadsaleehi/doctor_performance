<?php
/**
 * WordPress memory limit diagnostic rule.
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
 * Rule: WordPress memory limit potentially insufficient.
 *
 * Checks wordpress.wp_memory_limit against recommended minimum.
 * WP_MEMORY_LIMIT = -1 means unlimited.
 */
final class WpMemoryLimitRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.wp_memory_limit',
				'title'            => __( 'WordPress Memory Limit Is Too Low', 'wp-performance-doctor' ),
				'description'      => __( 'The WordPress memory limit (WP_MEMORY_LIMIT) may be insufficient.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.wp_memory_limit' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'recommended_min_mb' => 256,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$limit = $this->get_metric( $context, 'wordpress.wp_memory_limit' );

		if ( ! $context->has_metric( 'wordpress.wp_memory_limit' ) ) {
			return $this->result_unavailable( __( 'WordPress memory limit metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $limit ) {
			return $this->result_pass(
				__( 'WordPress Memory Limit Is Unlimited', 'wp-performance-doctor' ),
				__( 'WP_MEMORY_LIMIT is set to -1 (unlimited).', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => '>= 256 MB',
				)
			);
		}

		$config   = $this->get_config();
		$min_mb   = (int) ( $config['recommended_min_mb'] ?? 256 );
		$limit_mb = (int) ( $limit / ( 1024 * 1024 ) );

		if ( $limit_mb < $min_mb ) {
			return $this->result_fail(
				__( 'WordPress Memory Limit Is Too Low', 'wp-performance-doctor' ),
				sprintf(
					__( 'WP_MEMORY_LIMIT is %1$d MB, below the recommended %2$d MB.', 'wp-performance-doctor' ),
					$limit_mb,
					$min_mb
				),
				array(
					'actual_value'   => $limit_mb . ' MB',
					'expected_value' => '>= ' . $min_mb . ' MB',
					'unit'           => 'MB',
				),
				__( 'WordPress may fail to perform memory-intensive operations.', 'wp-performance-doctor' ),
				__( 'Increase WP_MEMORY_LIMIT in wp-config.php if your server memory_limit allows.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit wp-config.php and set WP_MEMORY_LIMIT to \'256M\'.', 'wp-performance-doctor' ),
						__( 'Ensure the server PHP memory_limit is at least as high.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'WordPress Memory Limit Is Adequate', 'wp-performance-doctor' ),
			sprintf( __( 'WP_MEMORY_LIMIT is %d MB.', 'wp-performance-doctor' ), $limit_mb ),
			array(
				'actual_value'   => $limit_mb . ' MB',
				'expected_value' => '>= ' . $min_mb . ' MB',
			)
		);
	}
}
