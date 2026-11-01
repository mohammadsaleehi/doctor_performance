<?php
/**
 * Plugin update availability diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\PluginTheme;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Plugin update availability context.
 *
 * Does not perform external vulnerability checks. Reports that update
 * checking should be done through the WordPress admin.
 */
final class PluginUpdateRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.plugin_updates',
				'title'            => __( 'Plugin Updates', 'wp-performance-doctor' ),
				'description'      => __( 'Check for plugin updates through the WordPress admin.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PLUGIN_THEME,
				'severity'         => Severity::INFO,
				'confidence'       => 'low',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.plugin_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$count = $this->get_metric( $context, 'wordpress.plugin_count' );

		if ( ! $context->has_metric( 'wordpress.plugin_count' ) ) {
			return $this->result_unavailable( __( 'Plugin count metric is not available.', 'wp-performance-doctor' ) );
		}

		return $this->result_info(
			__( 'Check for Plugin Updates', 'wp-performance-doctor' ),
			__( 'Visit Dashboard > Updates to check for available plugin updates.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'check_required',
				'expected_value' => 'up to date',
			)
		);
	}
}
