<?php
/**
 * Memcached diagnostic rule.
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
 * Rule: Memcached unavailable or misconfigured.
 */
final class MemcachedRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'cache.memcached_available',
				'title'            => __( 'Memcached Not Configured as Object Cache', 'wp-performance-doctor' ),
				'description'      => __( 'Memcached extension is available but not configured as the object cache backend.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::CACHE,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'cache.memcached_available', 'cache.memcached_active' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$available = $this->get_metric( $context, 'cache.memcached_available' );
		$active    = $this->get_metric( $context, 'cache.memcached_active' );

		if ( ! $context->has_metric( 'cache.memcached_available' ) ) {
			return $this->result_unavailable( __( 'Memcached availability metric is not available.', 'wp-performance-doctor' ) );
		}

		$available_truthy = $this->is_truthy( $available );
		$active_truthy    = $this->is_truthy( $active );

		if ( ! $available_truthy ) {
			return $this->result_info(
				__( 'Memcached Extension Not Loaded', 'wp-performance-doctor' ),
				__( 'The Memcached PHP extension is not installed.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'not available',
					'expected_value' => 'available',
				)
			);
		}

		if ( $available_truthy && ! $active_truthy ) {
			return $this->result_warning(
				__( 'Memcached Available but Not Active', 'wp-performance-doctor' ),
				__( 'The Memcached extension is loaded but not configured as the object cache backend.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'extension loaded, not active',
					'expected_value' => 'active as object cache',
					'unit'           => 'string',
				),
				__( 'Memcached is available but not being used for object caching.', 'wp-performance-doctor' ),
				__( 'Configure Memcached as the object cache backend if you want to use it.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Install and configure a Memcached object cache plugin.', 'wp-performance-doctor' ),
						__( 'Ensure the Memcached server is running and accessible.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		if ( $active_truthy ) {
			return $this->result_pass(
				__( 'Memcached Is Active as Object Cache', 'wp-performance-doctor' ),
				__( 'Memcached is configured as the object cache backend.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'active',
					'expected_value' => 'active',
				)
			);
		}

		return $this->result_info(
			__( 'Memcached Status Unclear', 'wp-performance-doctor' ),
			__( 'Memcached extension status could not be fully determined.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'unclear',
				'expected_value' => 'available or not available',
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
