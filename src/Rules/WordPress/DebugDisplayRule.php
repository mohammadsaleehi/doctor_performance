<?php
/**
 * WordPress debug display diagnostic rule.
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
 * Rule: WP_DEBUG_DISPLAY enabled in production.
 *
 * Debug display can expose errors and sensitive information to visitors.
 * Intentional in development environments.
 */
final class DebugDisplayRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.debug_display',
				'title'            => __( 'Debug Output Enabled', 'wp-performance-doctor' ),
				'description'      => __( 'WP_DEBUG_DISPLAY is enabled, which may display PHP errors to site visitors.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::HIGH,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.debug_mode', 'wordpress.debug_display', 'wordpress.wordpress_environment_type' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$debug_mode    = $this->get_metric( $context, 'wordpress.debug_mode' );
		$debug_display = $this->get_metric( $context, 'wordpress.debug_display' );
		$env_type      = $this->get_metric( $context, 'wordpress.wordpress_environment_type' );

		if ( ! $context->has_metric( 'wordpress.debug_display' ) ) {
			return $this->result_unavailable( __( 'Debug display metric is not available.', 'wp-performance-doctor' ) );
		}

		$display_truthy = $this->is_truthy( $debug_display );
		$debug_truthy   = $this->is_truthy( $debug_mode );
		$env            = $env_type ? (string) $env_type : 'production';

		if ( ! $display_truthy ) {
			return $this->result_pass(
				__( 'Debug Output Is Disabled', 'wp-performance-doctor' ),
				__( 'WP_DEBUG_DISPLAY is not enabled.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'disabled',
					'expected_value' => 'disabled',
				)
			);
		}

		// Development environment - debug may be intentional.
		if ( 'development' === $env ) {
			return $this->result_info(
				__( 'Debug Output Enabled in Development', 'wp-performance-doctor' ),
				__( 'WP_DEBUG_DISPLAY is enabled in a development environment, which may be intentional.', 'wp-performance-doctor' ),
				array(
					'actual_value' => 'enabled',
					'environment'  => $env,
				)
			);
		}

		return $this->result_fail(
			__( 'Debug Output Enabled on Production Site', 'wp-performance-doctor' ),
			__( 'WP_DEBUG_DISPLAY is enabled. Errors may be displayed to visitors, exposing sensitive information.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'enabled',
				'expected_value' => 'disabled',
				'unit'           => 'boolean',
				'environment'    => $env,
			),
			__( 'Displayed errors can reveal file paths, database structure, and other sensitive details to visitors.', 'wp-performance-doctor' ),
			__( 'Disable WP_DEBUG_DISPLAY in wp-config.php. Use WP_DEBUG_LOG for error logging instead.', 'wp-performance-doctor' ),
			array(
				'steps'                  => array(
					__( 'Set WP_DEBUG_DISPLAY to false in wp-config.php.', 'wp-performance-doctor' ),
					__( 'Enable WP_DEBUG_LOG to log errors to debug.log instead of displaying them.', 'wp-performance-doctor' ),
				),
				'risk'                   => 'high',
				'requires_manual_action' => true,
				'difficulty'             => 'easy',
			),
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
