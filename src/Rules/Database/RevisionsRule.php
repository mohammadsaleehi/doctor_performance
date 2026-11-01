<?php
/**
 * Post revisions diagnostic rule.
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
 * Rule: Excessive revision count.
 *
 * Checks database.revisions_count with contextual severity.
 */
final class RevisionsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.excessive_revisions',
				'title'            => __( 'Excessive Post Revisions', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of post revisions may bloat the database.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.revisions_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold'  => 100,
					'critical_threshold' => 500,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'database.revisions_count' );

		if ( ! $context->has_metric( 'database.revisions_count' ) ) {
			return $this->result_unavailable( __( 'Revisions count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Revisions count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$warn      = (int) ( $config['warning_threshold'] ?? 100 );
		$crit      = (int) ( $config['critical_threshold'] ?? 500 );
		$count_int = (int) $count;

		if ( $count_int >= $crit ) {
			return $this->result_fail(
				__( 'Critically High Revision Count', 'wp-performance-doctor' ),
				sprintf( __( '%d post revisions exist.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $warn,
					'unit'           => 'count',
				),
				__( 'A very high number of revisions can significantly bloat the posts table.', 'wp-performance-doctor' ),
				__( 'Limit post revisions in wp-config.php or clean up old revisions.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Define WP_POST_REVISIONS in wp-config.php to limit stored revisions.', 'wp-performance-doctor' ),
						__( 'Use a revision cleanup tool to remove old revisions.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		if ( $count_int >= $warn ) {
			return $this->result_warning(
				__( 'High Revision Count', 'wp-performance-doctor' ),
				sprintf( __( '%d post revisions exist.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $warn,
					'unit'           => 'count',
				),
				__( 'Many revisions increase database size and may affect query performance.', 'wp-performance-doctor' ),
				__( 'Consider limiting or cleaning up old post revisions.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Define WP_POST_REVISIONS in wp-config.php to limit stored revisions.', 'wp-performance-doctor' ),
						__( 'Use a revision cleanup tool to remove old revisions.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'Revision Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d post revisions.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $warn,
			)
		);
	}
}
