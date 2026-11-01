<?php
/**
 * External assets diagnostic rule.
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
 * Rule: Large number of external assets.
 */
final class ExternalAssetsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'frontend.external_assets',
				'title'            => __( 'Many External Assets', 'wp-performance-doctor' ),
				'description'      => __( 'A large number of assets are loaded from external domains.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::FRONTEND,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'frontend.external_asset_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold' => 5,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'frontend.external_asset_count' );

		if ( ! $context->has_metric( 'frontend.external_asset_count' ) ) {
			return $this->result_unavailable( __( 'External asset count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $count ) {
			return $this->result_unavailable( __( 'External asset count data is not available.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold'] ?? 5 );
		$count_int = (int) $count;

		if ( $count_int >= $threshold ) {
			return $this->result_warning(
				__( 'Many External Assets', 'wp-performance-doctor' ),
				sprintf( __( '%d assets are loaded from external domains.', 'wp-performance-doctor' ), $count_int ),
				array(
					'actual_value'   => $count_int,
					'expected_value' => '< ' . $threshold,
					'unit'           => 'count',
				),
				__( 'External assets add DNS lookups and depend on third-party availability.', 'wp-performance-doctor' ),
				__( 'Consider self-hosting assets where possible.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Identify external assets and consider self-hosting them.', 'wp-performance-doctor' ),
						__( 'Use resource hints (preconnect, dns-prefetch) for essential external assets.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'External Asset Count Is Reasonable', 'wp-performance-doctor' ),
			sprintf( __( '%d assets from external domains.', 'wp-performance-doctor' ), $count_int ),
			array(
				'actual_value'   => $count_int,
				'expected_value' => '< ' . $threshold,
			)
		);
	}
}
