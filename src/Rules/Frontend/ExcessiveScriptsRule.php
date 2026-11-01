<?php
/**
 * Excessive enqueued scripts diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\Frontend;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Excessive enqueued script count.
 */
final class ExcessiveScriptsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'frontend.excessive_scripts',
				'title'            => __( 'Excessive Enqueued Scripts', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of JavaScript files are enqueued.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::FRONTEND,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'frontend.scripts_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 15,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'frontend.scripts_count' );

		if ( ! $context->has_metric( 'frontend.scripts_count' ) ) {
			return $this->result_unavailable( __( 'Scripts count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Scripts count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 15 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'Excessive Enqueued Scripts', 'wp-performance-doctor' ),
				sprintf( __( '%d JavaScript files are enqueued.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'Many JavaScript files increase page load time and may block rendering.', 'wp-performance-doctor' ),
				__( 'Review and combine or defer unnecessary JavaScript files.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify plugins that enqueue many JavaScript files.', 'wp-performance-doctor' ),
						__( 'Use asset optimization to combine and defer scripts.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Script Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d JavaScript files enqueued.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
