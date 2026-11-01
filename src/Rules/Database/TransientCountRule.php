<?php
/**
 * Excessive transients diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\Database;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Excessive transient records.
 *
 * Checks database.transient_count. Note: cannot distinguish expired from
 * non-expired without additional queries.
 */
final class TransientCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.excessive_transients',
				'title'            => __( 'Excessive Transient Records', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of transient records exist in the options table.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.transient_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 100,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'database.transient_count' );

		if ( ! $context->has_metric( 'database.transient_count' ) ) {
			return $this->result_unavailable( __( 'Transient count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Transient count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 100 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'Excessive Transient Records', 'wp-performance-doctor' ),
				sprintf( __( '%d transient records exist in the options table.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'Many transients can bloat the options table and slow down autoloaded option queries.', 'wp-performance-doctor' ),
				__( 'Review and clean up expired transients.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Use a transient cleanup plugin or run a database query to remove expired transients.', 'wp-performance-doctor' ),
						__( 'Do not delete transients manually without a backup.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Transient Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d transient records.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
