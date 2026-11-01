<?php
/**
 * Database table count diagnostic rule.
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
 * Rule: Excessive database table count.
 *
 * A very high table count may indicate multiple installations or
 * orphaned tables from removed plugins.
 */
final class TableCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.table_count',
				'title'            => __( 'High Database Table Count', 'wp-performance-doctor' ),
				'description'      => __( 'The database contains a large number of tables.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.table_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 200,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'database.table_count' );

		if ( ! $context->has_metric( 'database.table_count' ) ) {
			return $this->result_unavailable( __( 'Table count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Table count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 200 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'High Database Table Count', 'wp-performance-doctor' ),
				sprintf( __( '%d tables exist in the database.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'A very high table count may indicate orphaned tables from removed plugins.', 'wp-performance-doctor' ),
				__( 'Review and remove tables from plugins that are no longer installed.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Compare table prefixes against installed plugins.', 'wp-performance-doctor' ),
						__( 'Remove orphaned tables carefully with a backup.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'hard',
				),
			);
		}

		return $this->result_pass(
			__( 'Table Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d tables in the database.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
