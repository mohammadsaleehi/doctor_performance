<?php
/**
 * Autoloaded options count diagnostic rule.
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
 * Rule: Excessive autoloaded options count.
 */
final class AutoloadedOptionsCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'database.autoloaded_options_count',
				'title'            => __( 'Excessive Autoloaded Options Count', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of autoloaded options may slow down page loads.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::DATABASE,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'database.autoloaded_options_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 500,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'database.autoloaded_options_count' );

		if ( ! $context->has_metric( 'database.autoloaded_options_count' ) ) {
			return $this->result_unavailable( __( 'Autoloaded options count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Autoloaded options count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 500 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'Excessive Autoloaded Options Count', 'wp-performance-doctor' ),
				sprintf( __( '%d autoloaded options are loaded on every page load.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'Many autoloaded options increase memory usage on every request.', 'wp-performance-doctor' ),
				__( 'Review and reduce the number of autoloaded options.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify plugins with many autoloaded options.', 'wp-performance-doctor' ),
						__( 'Remove unnecessary autoloaded options.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Autoloaded Options Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d autoloaded options.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
