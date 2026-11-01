<?php
/**
 * Page cache diagnostic rule.
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
 * Rule: Page caching not detected.
 *
 * The presence of a cache plugin does not prove page caching works.
 * Absence of a known plugin does not prove no page cache exists.
 */
final class PageCacheRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'cache.page_cache_detected',
				'title'            => __( 'No Page Cache Detected', 'wp-performance-doctor' ),
				'description'      => __( 'No page caching mechanism was detected.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::CACHE,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'cache.page_cache_detected', 'cache.cache_plugins' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$detected = $this->get_metric( $context, 'cache.page_cache_detected' );
		$plugins  = $this->get_metric( $context, 'cache.cache_plugins' );

		if ( ! $context->has_metric( 'cache.page_cache_detected' ) ) {
			return $this->result_unavailable( __( 'Page cache detection metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $detected ) {
			return $this->result_unavailable( __( 'Page cache detection data is not available.', 'wp-performance-doctor' ) );
		}

		$indicators  = $this->decode_json( $detected );
		$plugin_list = $this->decode_json( $plugins );

		$found_any = false;
		if ( is_array( $indicators ) ) {
			foreach ( $indicators as $key => $value ) {
				if ( 'headers' === $key ) {
					continue;
				}
				if ( $this->is_truthy( $value ) ) {
					$found_any = true;
					break;
				}
			}
		}

		if ( $found_any ) {
			return $this->result_pass(
				__( 'Page Cache Detected', 'wp-performance-doctor' ),
				__( 'A page caching mechanism was detected.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'detected',
					'expected_value' => 'detected',
				)
			);
		}

		$has_cache_plugin = ! empty( $plugin_list );

		return $this->result_warning(
			__( 'No Page Cache Detected', 'wp-performance-doctor' ),
			__( 'No server-level or plugin-based page caching was detected. Page caching can significantly improve frontend performance.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'none detected',
				'expected_value' => 'page cache active',
				'unit'           => 'string',
				'has_plugin'     => $has_cache_plugin,
			),
			__( 'Without page caching, WordPress must generate HTML dynamically on every request.', 'wp-performance-doctor' ),
			__( 'Consider enabling page caching through your hosting provider or a caching plugin.', 'wp-performance-doctor' ),
			array(
				'steps'                  => array(
					__( 'Check with your hosting provider about server-level page caching.', 'wp-performance-doctor' ),
					__( 'Consider installing a page caching plugin.', 'wp-performance-doctor' ),
				),
				'risk'                   => 'low',
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
