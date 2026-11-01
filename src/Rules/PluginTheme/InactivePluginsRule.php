<?php
/**
 * Inactive plugins diagnostic rule.
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
 * Rule: Installed but inactive plugins.
 */
final class InactivePluginsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.inactive_plugins',
				'title'            => __( 'Inactive Plugins Installed', 'wp-performance-doctor' ),
				'description'      => __( 'There are installed but inactive plugins that may need attention.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PLUGIN_THEME,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.plugin_count', 'wordpress.installed_plugin_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$active    = $this->get_metric( $context, 'wordpress.plugin_count' );
		$installed = $this->get_metric( $context, 'wordpress.installed_plugin_count' );

		if ( ! $context->has_metric( 'wordpress.installed_plugin_count' ) || ! $context->has_metric( 'wordpress.plugin_count' ) ) {
			return $this->result_unavailable( __( 'Plugin count metrics are not available.', 'wp-performance-doctor' ) );
		}

		$active_int    = null !== $active ? (int) $active : 0;
		$installed_int = null !== $installed ? (int) $installed : 0;
		$inactive      = $installed_int - $active_int;

		if ( $inactive > 0 ) {
			return $this->result_info(
				sprintf( __( '%d Inactive Plugins', 'wp-performance-doctor' ), $inactive ),
				sprintf(
					__( '%d plugin(s) are installed but not active.', 'wp-performance-doctor' ),
					$inactive
				),
				array(
					'actual_value'   => $inactive,
					'expected_value' => 'context',
					'unit'           => 'count',
				)
			);
		}

		return $this->result_pass(
			__( 'No Inactive Plugins', 'wp-performance-doctor' ),
			__( 'All installed plugins are active.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => '0 inactive',
				'expected_value' => 'context',
			)
		);
	}
}
