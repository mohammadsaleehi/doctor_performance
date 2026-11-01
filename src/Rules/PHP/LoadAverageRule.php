<?php
/**
 * Server load average diagnostic rule.
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
 * Rule: Excessive server load average.
 *
 * Only evaluates when load_average is available and the environment
 * is understood. Does not compare against arbitrary thresholds without
 * CPU information.
 */
final class LoadAverageRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.server_load_average',
				'title'            => __( 'Elevated Server Load Average', 'wp-performance-doctor' ),
				'description'      => __( 'Server load average may indicate resource pressure.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'low',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.load_average' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 4.0,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$load = $this->get_metric( $context, 'system.load_average' );

		if ( ! $context->has_metric( 'system.load_average' ) ) {
			return $this->result_unavailable( __( 'Server load average metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $load ) {
			return $this->result_unavailable( __( 'Load average data is not available on this system.', 'wp-performance-doctor' ) );
		}

		$load_val  = (float) $load;
		$config    = $this->get_config();
		$threshold = (float) ( $config['warning_threshold'] ?? 4.0 );

		if ( $load_val > $threshold ) {
			return $this->result_warning(
				__( 'Elevated Server Load Average', 'wp-performance-doctor' ),
				sprintf(
					__( '1-minute load average is %1$.2f, above the %2$s threshold.', 'wp-performance-doctor' ),
					$load_val,
					number_format( $threshold, 1 )
				),
				array(
					'actual_value'   => number_format( $load_val, 2 ),
					'expected_value' => '< ' . number_format( $threshold, 1 ),
					'unit'           => 'load',
				),
				__( 'Elevated load may indicate resource pressure. Interpret in context of available CPU cores.', 'wp-performance-doctor' ),
				__( 'Investigate high-load processes with your hosting provider.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Review server process list for resource-intensive processes.', 'wp-performance-doctor' ),
						__( 'Contact your hosting provider if load remains elevated.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Server Load Average Is Normal', 'wp-performance-doctor' ),
			sprintf( __( '1-minute load average is %.2f.', 'wp-performance-doctor' ), $load_val ),
			array(
				'actual_value'   => number_format( $load_val, 2 ),
				'expected_value' => '< ' . number_format( $threshold, 1 ),
			)
		);
	}
}
