<?php
/**
 * Database table engine diagnostic rule.
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
 * Rule: Potentially problematic database table engines.
 *
 * MyISAM tables lack transactions and crash recovery compared to InnoDB.
 * This rule requires table engine metadata which may not be available.
 */
final class TableEngineRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.table_engines',
				'title'            => __( 'Non-InnoDB Table Engines', 'wp-performance-doctor' ),
				'description'      => __( 'Some tables use the MyISAM engine instead of InnoDB.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'low',
				'version'          => '1.0.0',
				'required_metrics' => array(),
				'cost'             => 'expensive',
				'enabled'          => false,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		return $this->result_info(
			__( 'Table Engine Check Not Available', 'wp-performance-doctor' ),
			__( 'Table engine inspection requires additional queries not currently collected.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'not collected',
				'expected_value' => 'InnoDB',
			)
		);
	}
}
