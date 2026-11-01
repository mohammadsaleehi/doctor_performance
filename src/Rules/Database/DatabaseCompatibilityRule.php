<?php
/**
 * Database compatibility diagnostic rule.
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
 * Rule: Database server compatibility concerns.
 *
 * Checks database.db_version for known issues.
 */
final class DatabaseCompatibilityRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.db_compatibility',
				'title'            => __( 'Database Version Information', 'wp-performance-doctor' ),
				'description'      => __( 'Reports the database server version for context.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.db_version' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$version = $this->get_metric( $context, 'database.db_version' );

		if ( ! $context->has_metric( 'database.db_version' ) ) {
			return $this->result_unavailable( __( 'Database version metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $version || '' === $version ) {
			return $this->result_unavailable( __( 'Database version could not be determined.', 'wp-performance-doctor' ) );
		}

		return $this->result_info(
			__( 'Database Server Version', 'wp-performance-doctor' ),
			sprintf( __( 'Database server: %s', 'wp-performance-doctor' ), esc_html( (string) $version ) ),
			array(
				'actual_value'   => (string) $version,
				'expected_value' => 'MySQL 5.7+ or MariaDB 10.0+',
			)
		);
	}
}
