<?php
/**
 * Autoloaded options size diagnostic rule.
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
 * Rule: Excessively large autoloaded options payload.
 *
 * Checks database.autoloaded_options_size. WordPress autoload behavior
 * varies by version; this rule relies on the collector's measurement.
 */
final class AutoloadedOptionsSizeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.autoloaded_options_size',
				'title'            => __( 'Large Autoloaded Options Payload', 'wp-performance-doctor' ),
				'description'      => __( 'Autoloaded options consume a large amount of memory on every page load.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.autoloaded_options_size', 'database.autoloaded_options_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_bytes'  => 1048576, // 1 MB
					'critical_threshold_bytes' => 5242880, // 5 MB
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$size  = $this->get_metric( $context, 'database.autoloaded_options_size' );
		$count = $this->get_metric( $context, 'database.autoloaded_options_count' );

		if ( ! $context->has_metric( 'database.autoloaded_options_size' ) ) {
			return $this->result_unavailable( __( 'Autoloaded options size metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $size ) {
			return $this->result_unavailable( __( 'Autoloaded options size data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$warn      = (int) ( $config['warning_threshold_bytes'] ?? 1048576 );
		$crit      = (int) ( $config['critical_threshold_bytes'] ?? 5242880 );
		$size_int  = (int) $size;
		$count_int = null !== $count ? (int) $count : 0;

		if ( $size_int >= $crit ) {
			return $this->result_fail(
				__( 'Critically Large Autoloaded Options Payload', 'wp-performance-doctor' ),
				sprintf(
					__( 'Autoloaded options total %1$s (%2$d options).', 'wp-performance-doctor' ),
					$this->format_bytes( $size_int ),
					$count_int
				),
				array(
					'actual_value'   => $this->format_bytes( $size_int ),
					'expected_value' => '< ' . $this->format_bytes( $warn ),
					'unit'           => 'bytes',
					'count'          => $count_int,
				),
				__( 'Large autoloaded options are loaded into memory on every page load, increasing response time and memory usage.', 'wp-performance-doctor' ),
				__( 'Review and reduce autoloaded options, especially from inactive plugins.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify large autoloaded options in the database options table.', 'wp-performance-doctor' ),
						__( 'Deactivate or remove plugins that store large autoloaded options.', 'wp-performance-doctor' ),
						__( 'Consider using wp_options autoload selectively.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		if ( $size_int >= $warn ) {
			return $this->result_warning(
				__( 'Large Autoloaded Options Payload', 'wp-performance-doctor' ),
				sprintf(
					__( 'Autoloaded options total %1$s (%2$d options).', 'wp-performance-doctor' ),
					$this->format_bytes( $size_int ),
					$count_int
				),
				array(
					'actual_value'   => $this->format_bytes( $size_int ),
					'expected_value' => '< ' . $this->format_bytes( $warn ),
					'unit'           => 'bytes',
					'count'          => $count_int,
				),
				__( 'Autoloaded options are loaded on every page load and may affect performance.', 'wp-performance-doctor' ),
				__( 'Review autoloaded options for unnecessary data.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify large autoloaded options in the database options table.', 'wp-performance-doctor' ),
						__( 'Remove unnecessary autoloaded options.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Autoloaded Options Size Is Reasonable', 'wp-performance-doctor' ),
			sprintf(
				__( 'Autoloaded options total %1$s (%2$d options).', 'wp-performance-doctor' ),
				$this->format_bytes( $size_int ),
				$count_int
			),
			array(
				'actual_value'   => $this->format_bytes( $size_int ),
				'expected_value' => '< ' . $this->format_bytes( $warn ),
			)
		);
	}
}
