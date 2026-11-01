<?php
/**
 * Active plugin count diagnostic rule.
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
 * Rule: Active plugin count as contextual information.
 *
 * A large plugin count is not automatically a performance problem.
 */
final class PluginCountRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.plugin_count',
				'title'            => __( 'Active Plugin Count', 'wp-performance-doctor' ),
				'description'      => __( 'Reports the number of active plugins as context.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PLUGIN_THEME,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
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

		if ( ! $context->has_metric( 'wordpress.plugin_count' ) ) {
			return $this->result_unavailable( __( 'Plugin count metric is not available.', 'wp-performance-doctor' ) );
		}

		$active_int    = null !== $active ? (int) $active : 0;
		$installed_int = null !== $installed ? (int) $installed : 0;

		return $this->result_info(
			sprintf( __( '%1$d Active Plugins (%2$d Installed)', 'wp-performance-doctor' ), $active_int, $installed_int ),
			sprintf(
				__( '%1$d of %2$d installed plugins are active.', 'wp-performance-doctor' ),
				$active_int,
				$installed_int
			),
			array(
				'actual_value'   => $active_int,
				'expected_value' => 'context',
				'unit'           => 'count',
				'installed'      => $installed_int,
			)
		);
	}
}
