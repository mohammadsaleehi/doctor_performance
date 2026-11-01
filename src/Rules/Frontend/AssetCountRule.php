<?php
/**
 * Total asset count diagnostic rule.
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
 * Rule: Total frontend asset count context.
 */
final class AssetCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'frontend.total_assets',
				'title'            => __( 'Total Frontend Assets', 'wp-performance-doctor' ),
				'description'      => __( 'Reports the total number of enqueued scripts and stylesheets.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::FRONTEND,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'frontend.total_assets' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$total = $this->get_metric( $context, 'frontend.total_assets' );

		if ( ! $context->has_metric( 'frontend.total_assets' ) ) {
			return $this->result_unavailable( __( 'Total assets metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $total ) {
			return $this->result_unavailable( __( 'Total assets data is not available.', 'wp-performance-doctor' ) );
		}

		$total_int = (int) $total;

		return $this->result_info(
			sprintf( __( '%d Total Frontend Assets', 'wp-performance-doctor' ), $total_int ),
			sprintf( __( '%d scripts and stylesheets are enqueued.', 'wp-performance-doctor' ), $total_int ),
			array(
				'actual_value'   => $total_int,
				'expected_value' => 'context',
			)
		);
	}
}
