<?php
/**
 * Cache plugin diagnostic rule.
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
 * Rule: Known cache plugin configuration concerns.
 */
final class CachePluginRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'cache.cache_plugins',
				'title'            => __( 'Cache Plugins Detected', 'wp-performance-doctor' ),
				'description'      => __( 'Known caching plugins are installed.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::CACHE,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'cache.cache_plugins' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$plugins = $this->get_metric( $context, 'cache.cache_plugins' );

		if ( ! $context->has_metric( 'cache.cache_plugins' ) ) {
			return $this->result_unavailable( __( 'Cache plugins metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $plugins ) {
			return $this->result_unavailable( __( 'Cache plugins data is not available.', 'wp-performance-doctor' ) );
		}

		$plugin_list = $this->decode_json( $plugins );

		if ( empty( $plugin_list ) ) {
			return $this->result_info(
				__( 'No Known Cache Plugins', 'wp-performance-doctor' ),
				__( 'No known caching plugins were detected in the plugins directory.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'none',
					'expected_value' => 'context',
				)
			);
		}

		return $this->result_info(
			__( 'Cache Plugins Detected: ' . implode( ', ', $plugin_list ), 'wp-performance-doctor' ),
			sprintf(
				__( 'The following caching plugins are installed: %s.', 'wp-performance-doctor' ),
				implode( ', ', $plugin_list )
			),
			array(
				'actual_value'   => implode( ', ', $plugin_list ),
				'expected_value' => 'context',
			)
		);
	}
}
