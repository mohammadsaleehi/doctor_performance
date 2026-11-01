<?php
/**
 * Object cache diagnostic rule.
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
 * Rule: Persistent object cache not detected.
 *
 * Distinguishes WordPress's in-memory object cache from a persistent
 * backend (Redis, Memcached).
 */
final class ObjectCacheRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'cache.object_cache_available',
				'title'            => __( 'No Persistent Object Cache Detected', 'wp-performance-doctor' ),
				'description'      => __( 'WordPress is using its default in-memory object cache instead of a persistent backend.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::CACHE,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'cache.object_cache_available', 'cache.object_cache_type' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$available = $this->get_metric( $context, 'cache.object_cache_available' );
		$type      = $this->get_metric( $context, 'cache.object_cache_type' );

		if ( ! $context->has_metric( 'cache.object_cache_available' ) ) {
			return $this->result_unavailable( __( 'Object cache availability metric is not available.', 'wp-performance-doctor' ) );
		}

		$available_truthy = $this->is_truthy( $available );
		$type_str         = $type ? (string) $type : 'none';

		if ( ! $available_truthy ) {
			return $this->result_warning(
				__( 'No Persistent Object Cache Detected', 'wp-performance-doctor' ),
				__( 'WordPress is using its default non-persistent object cache. Database queries are not cached across requests.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'none',
					'expected_value' => 'redis or memcached',
					'unit'           => 'string',
				),
				__( 'Without a persistent object cache, repeated database queries are executed on every page load.', 'wp-performance-doctor' ),
				__( 'Consider installing and configuring Redis or Memcached as a persistent object cache backend.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Install a persistent object cache plugin (e.g., Redis Object Cache).', 'wp-performance-doctor' ),
						__( 'Configure Redis or Memcached as the object cache backend.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		// Persistent cache detected but not Redis/Memcached.
		if ( ! in_array( $type_str, array( 'redis', 'memcached' ), true ) ) {
			return $this->result_info(
				__( 'Object Cache Backend: ' . ucfirst( $type_str ), 'wp-performance-doctor' ),
				sprintf( __( 'Object cache backend is "%s".', 'wp-performance-doctor' ), $type_str ),
				array(
					'actual_value'   => $type_str,
					'expected_value' => 'redis or memcached',
				)
			);
		}

		return $this->result_pass(
			__( 'Persistent Object Cache Detected', 'wp-performance-doctor' ),
			sprintf( __( 'Persistent object cache backend: %s.', 'wp-performance-doctor' ), $type_str ),
			array(
				'actual_value'   => $type_str,
				'expected_value' => 'persistent backend',
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
