<?php
/**
 * OPcache cache diagnostic rule (cache category).
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\Cache;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: OPcache disabled (cache category duplicate of PHP rule).
 *
 * Provides cache-category visibility for OPcache status.
 */
final class OpcacheCacheRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'cache.opcache_enabled',
				'title'            => __( 'OPcache Is Disabled', 'wp-performance-doctor' ),
				'description'      => __( 'OPcache is not enabled, reducing PHP performance.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::CACHE,
				'severity'         => Severity::HIGH,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'cache.opcache_available', 'cache.opcache_enabled' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$available = $this->get_metric( $context, 'cache.opcache_available' );
		$enabled   = $this->get_metric( $context, 'cache.opcache_enabled' );

		if ( ! $context->has_metric( 'cache.opcache_available' ) || ! $context->has_metric( 'cache.opcache_enabled' ) ) {
			return $this->result_unavailable( __( 'OPcache metrics are not available.', 'wp-performance-doctor' ) );
		}

		$available_truthy = $this->is_truthy( $available );
		$enabled_truthy   = $this->is_truthy( $enabled );

		if ( ! $available_truthy ) {
			return $this->result_info(
				__( 'OPcache Not Available', 'wp-performance-doctor' ),
				__( 'The OPcache extension is not loaded.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'not available',
					'expected_value' => 'available',
				)
			);
		}

		if ( ! $enabled_truthy ) {
			return $this->result_fail(
				__( 'OPcache Is Disabled', 'wp-performance-doctor' ),
				__( 'OPcache is installed but not enabled.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'disabled',
					'expected_value' => 'enabled',
					'unit'           => 'boolean',
				),
				__( 'OPcache significantly improves PHP performance by caching compiled bytecode.', 'wp-performance-doctor' ),
				__( 'Enable OPcache by setting opcache.enable = 1 in php.ini.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini and set opcache.enable = 1.', 'wp-performance-doctor' ),
						__( 'Restart the web server or PHP-FPM.', 'wp-performance-doctor' ),
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
