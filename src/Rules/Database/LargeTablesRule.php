<?php
/**
 * Large database tables diagnostic rule.
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
 * Rule: Large database tables.
 *
 * Shows the five largest tables from database.table_sizes.
 */
final class LargeTablesRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.large_tables',
				'title'            => __( 'Large Database Tables', 'wp-performance-doctor' ),
				'description'      => __( 'Some database tables are unusually large and may affect performance.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.table_sizes' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_bytes' => 10485760, // 10 MB
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$table_sizes = $this->get_metric( $context, 'database.table_sizes' );

		if ( ! $context->has_metric( 'database.table_sizes' ) ) {
			return $this->result_unavailable( __( 'Table sizes metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $table_sizes ) {
			return $this->result_unavailable( __( 'Table sizes data is not available.', 'wp-performance-doctor' ) );
		}

		$tables = $this->decode_json( $table_sizes );
		if ( empty( $tables ) ) {
			return $this->result_unavailable( __( 'No table size data available.', 'wp-performance-doctor' ) );
		}

		arsort( $tables );
		$top5 = array_slice( $tables, 0, 5, true );

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold_bytes'] ?? 10485760 );
		$large     = array();

		foreach ( $top5 as $name => $size ) {
			if ( (int) $size >= $threshold ) {
				$large[ $name ] = (int) $size;
			}
		}

		if ( ! empty( $large ) ) {
			$list = array();
			foreach ( $large as $name => $size ) {
				$list[] = $name . ': ' . $this->format_bytes( $size );
			}

			return $this->result_warning(
				__( 'Large Database Tables Detected', 'wp-performance-doctor' ),
				sprintf(
					__( '%1$d table(s) exceed %2$s: %3$s', 'wp-performance-doctor' ),
					count( $large ),
					$this->format_bytes( $threshold ),
					implode( ', ', $list )
				),
				array(
					'actual_value'   => implode( ', ', array_map( fn( $s ) => $this->format_bytes( $s ), $large ) ),
					'expected_value' => '< ' . $this->format_bytes( $threshold ),
					'unit'           => 'bytes',
					'tables'         => $large,
				),
				__( 'Large tables can slow down queries and increase backup/restore times.', 'wp-performance-doctor' ),
				__( 'Review large tables for unnecessary data or consider archiving.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify the source of large tables (e.g., logs, post meta, comments).', 'wp-performance-doctor' ),
						__( 'Consider archiving or pruning old data.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Database Table Sizes Are Reasonable', 'wp-performance-doctor' ),
			__( 'No tables exceed the size threshold.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'within limits',
				'expected_value' => '< ' . $this->format_bytes( $threshold ),
			)
		);
	}
}
