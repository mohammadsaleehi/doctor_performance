<?php
/**
 * PHP memory limit diagnostic rule.
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
 * Rule: PHP memory limit below recommended threshold.
 *
 * Checks system.memory_limit. memory_limit = -1 (unlimited) is not treated
 * as zero or unavailable.
 */
final class MemoryLimitRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_memory_limit',
				'title'            => __( 'PHP Memory Limit Is Too Low', 'wp-performance-doctor' ),
				'description'      => __( 'The configured PHP memory limit may be insufficient for WordPress operations.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::HIGH,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.memory_limit' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'recommended_min_mb' => 256,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$limit = $this->get_metric( $context, 'system.memory_limit' );
		$raw   = $context->get_metric_record( 'system.memory_limit_raw' );

		if ( ! $context->has_metric( 'system.memory_limit' ) ) {
			return $this->result_unavailable( __( 'PHP memory limit metric is not available.', 'wp-performance-doctor' ) );
		}

		// null means -1 (unlimited) - not a problem.
		if ( null === $limit ) {
			return $this->result_pass(
				__( 'PHP Memory Limit Is Unlimited', 'wp-performance-doctor' ),
				__( 'PHP memory_limit is set to -1 (unlimited).', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => '>= 256 MB',
				)
			);
		}

		$limit_mb     = (int) ( $limit / ( 1024 * 1024 ) );
		$config       = $this->get_config();
		$min_mb       = (int) ( $config['recommended_min_mb'] ?? 256 );
		$limit_mb_val = (int) $config['recommended_min_mb'] ?? 256;

		if ( $limit_mb < $min_mb ) {
			return $this->result_fail(
				__( 'PHP Memory Limit Is Too Low', 'wp-performance-doctor' ),
				sprintf(
					__( 'PHP memory limit is %1$d MB, below the recommended %2$d MB.', 'wp-performance-doctor' ),
					$limit_mb,
					$min_mb
				),
				array(
					'actual_value'   => $limit_mb . ' MB',
					'expected_value' => $min_mb . ' MB',
					'unit'           => 'MB',
					'raw_value'      => $raw['value'] ?? null,
				),
				__( 'WordPress may fail to perform memory-intensive operations such as updates, imports, or bulk operations.', 'wp-performance-doctor' ),
				__( 'Increase the PHP memory_limit to at least 256M if your hosting environment permits it.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini or .user.ini and set memory_limit = 256M.', 'wp-performance-doctor' ),
						__( 'Or contact your hosting provider to increase the memory limit.', 'wp-performance-doctor' ),
						__( 'Avoid setting WP_MEMORY_LIMIT in wp-config.php as a substitute for server-level configuration.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'PHP Memory Limit Is Adequate', 'wp-performance-doctor' ),
			sprintf(
				__( 'PHP memory limit is %d MB, meeting the recommended minimum.', 'wp-performance-doctor' ),
				$limit_mb
			),
			array(
				'actual_value'   => $limit_mb . ' MB',
				'expected_value' => '>= ' . $min_mb . ' MB',
			)
		);
	}
}
