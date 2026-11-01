<?php
/**
 * OPcache diagnostic rule.
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
 * Rule: OPcache disabled.
 *
 * Checks system.opcache_enabled. OPcache significantly improves PHP performance.
 */
final class OpcacheRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_opcache_enabled',
				'title'            => __( 'OPcache Is Disabled', 'wp-performance-doctor' ),
				'description'      => __( 'OPcache is not enabled, which can significantly reduce PHP performance.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::HIGH,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.opcache_available', 'system.opcache_enabled' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$available = $this->get_metric( $context, 'system.opcache_available' );
		$enabled   = $this->get_metric( $context, 'system.opcache_enabled' );

		if ( ! $context->has_metric( 'system.opcache_available' ) || ! $context->has_metric( 'system.opcache_enabled' ) ) {
			return $this->result_unavailable( __( 'OPcache metrics are not available.', 'wp-performance-doctor' ) );
		}

		$available_truthy = $this->is_truthy( $available );
		$enabled_truthy   = $this->is_truthy( $enabled );

		if ( ! $available_truthy ) {
			return $this->result_info(
				__( 'OPcache Not Available', 'wp-performance-doctor' ),
				__( 'The OPcache extension is not loaded on this server.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'not available',
					'expected_value' => 'available',
				)
			);
		}

		if ( ! $enabled_truthy ) {
			return $this->result_fail(
				__( 'OPcache Is Disabled', 'wp-performance-doctor' ),
				__( 'OPcache is installed but not enabled. Enabling OPcache can significantly improve PHP performance.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'disabled',
					'expected_value' => 'enabled',
					'unit'           => 'boolean',
				),
				__( 'Without OPcache, PHP must recompile scripts on every request, increasing CPU usage and response time.', 'wp-performance-doctor' ),
				__( 'Enable OPcache by setting opcache.enable = 1 in php.ini.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini and set opcache.enable = 1.', 'wp-performance-doctor' ),
						__( 'Restart the web server or PHP-FPM after changing the setting.', 'wp-performance-doctor' ),
						__( 'Or contact your hosting provider to enable OPcache.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'OPcache Is Enabled', 'wp-performance-doctor' ),
			__( 'OPcache is installed and enabled.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'enabled',
				'expected_value' => 'enabled',
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
