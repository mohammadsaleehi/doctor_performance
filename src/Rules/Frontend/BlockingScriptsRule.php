<?php
/**
 * Blocking scripts diagnostic rule.
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
 * Rule: Scripts without defer or async.
 *
 * Uses frontend.enqueue strategy information when available.
 */
final class BlockingScriptsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'frontend.blocking_scripts',
				'title'            => __( 'Scripts Without Defer or Async', 'wp-performance-doctor' ),
				'description'      => __( 'Many scripts are loaded without defer or async, potentially blocking rendering.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::FRONTEND,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'frontend.scripts_count', 'frontend.deferred_scripts', 'frontend.async_scripts' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$scripts  = $this->get_metric( $context, 'frontend.scripts_count' );
		$deferred = $this->get_metric( $context, 'frontend.deferred_scripts' );
		$async    = $this->get_metric( $context, 'frontend.async_scripts' );

		if ( ! $context->has_metric( 'frontend.scripts_count' ) ) {
			return $this->result_unavailable( __( 'Scripts count metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $scripts ) {
			return $this->result_unavailable( __( 'Scripts count data is not available.', 'wp-performance-doctor' ) );
		}

		$scripts_int  = (int) $scripts;
		$deferred_int = null !== $deferred ? (int) $deferred : 0;
		$async_int    = null !== $async ? (int) $async : 0;
		$blocking     = $scripts_int - $deferred_int - $async_int;

		if ( $blocking > 0 && $scripts_int > 0 ) {
			$pct = (int) ( ( $blocking / $scripts_int ) * 100 );

			return $this->result_warning(
				__( 'Scripts Without Defer or Async', 'wp-performance-doctor' ),
				sprintf(
					__( '%1$d of %2$d scripts (%3$d%%) may block rendering.', 'wp-performance-doctor' ),
					$blocking,
					$scripts_int,
					$pct
				),
				array(
					'actual_value'   => $blocking,
					'expected_value' => '0',
					'unit'           => 'count',
					'percent'        => $pct,
				),
				__( 'Blocking scripts can delay page rendering and affect user experience.', 'wp-performance-doctor' ),
				__( 'Add defer or async attributes to non-critical scripts.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Add the defer attribute to non-critical scripts.', 'wp-performance-doctor' ),
						__( 'Use the script_loader_tag filter to modify script attributes.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Scripts Use Defer or Async', 'wp-performance-doctor' ),
			__( 'Scripts are using defer or async attributes where applicable.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => $blocking . ' blocking',
				'expected_value' => '0 blocking',
			)
		);
	}
}
