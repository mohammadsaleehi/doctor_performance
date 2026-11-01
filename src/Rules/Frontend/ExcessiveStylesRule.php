<?php
/**
 * Excessive enqueued stylesheets diagnostic rule.
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
 * Rule: Excessive enqueued stylesheet count.
 */
final class ExcessiveStylesRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'frontend.excessive_styles',
				'title'            => __( 'Excessive Enqueued Stylesheets', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of CSS files are enqueued.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::FRONTEND,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'frontend.styles_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 10,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'frontend.styles_count' );

		if ( ! $context->has_metric( 'frontend.styles_count' ) ) {
			return $this->result_unavailable( __( 'Styles count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'Styles count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 10 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'Excessive Enqueued Stylesheets', 'wp-performance-doctor' ),
				sprintf( __( '%d CSS files are enqueued.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'Many CSS files increase page load time and may block rendering.', 'wp-performance-doctor' ),
				__( 'Review and combine unnecessary stylesheets.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify plugins that enqueue many CSS files.', 'wp-performance-doctor' ),
						__( 'Use asset optimization to combine stylesheets.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Stylesheet Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d CSS files enqueued.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
