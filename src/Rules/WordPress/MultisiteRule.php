<?php
/**
 * WordPress multisite diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\WordPress;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Multisite configuration context.
 *
 * Reports multisite status without flagging it as a problem.
 */
final class MultisiteRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.multisite',
				'title'            => __( 'WordPress Multisite', 'wp-performance-doctor' ),
				'description'      => __( 'The site is part of a WordPress multisite network.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.multisite' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$multisite = $this->get_metric( $context, 'wordpress.multisite' );

		if ( ! $context->has_metric( 'wordpress.multisite' ) ) {
			return $this->result_unavailable( __( 'Multisite metric is not available.', 'wp-performance-doctor' ) );
		}

		$is_multi = $this->is_truthy( $multisite );

		return $this->result_info(
			$is_multi ? __( 'Multisite Network', 'wp-performance-doctor' ) : __( 'Single Site Installation', 'wp-performance-doctor' ),
			$is_multi
				? __( 'This is a WordPress multisite installation.', 'wp-performance-doctor' )
				: __( 'This is a single WordPress site installation.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => $is_multi ? 'multisite' : 'single',
				'expected_value' => 'context',
			)
		);
	}

	/**
	 * Coerce a value to boolean truthiness.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	protected function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value > 0.0;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}
}
