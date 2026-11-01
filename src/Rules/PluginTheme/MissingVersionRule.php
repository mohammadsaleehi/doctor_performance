<?php
/**
 * Active plugins with missing version information diagnostic rule.
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
 * Rule: Plugins or themes with missing version information.
 */
final class MissingVersionRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.missing_plugin_versions',
				'title'            => __( 'Plugins with Missing Version Information', 'wp-performance-doctor' ),
				'description'      => __( 'Some active plugins do not report version information.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PLUGIN_THEME,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.active_plugins' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$plugins = $this->get_metric( $context, 'wordpress.active_plugins' );

		if ( ! $context->has_metric( 'wordpress.active_plugins' ) ) {
			return $this->result_unavailable( __( 'Active plugins metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $plugins ) {
			return $this->result_unavailable( __( 'Active plugins data is not available.', 'wp-performance-doctor' ) );
		}

		$plugin_list = $this->decode_json( $plugins );
		if ( empty( $plugin_list ) ) {
			return $this->result_pass(
				__( 'No Active Plugins', 'wp-performance-doctor' ),
				__( 'There are no active plugins.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => '0',
					'expected_value' => 'context',
				)
			);
		}

		$missing_version = array();
		foreach ( $plugin_list as $file => $label ) {
			if ( ! is_string( $label ) || ! preg_match( '/\d+\.\d+/', $label ) ) {
				$missing_version[] = basename( $file );
			}
		}

		if ( ! empty( $missing_version ) ) {
			return $this->result_warning(
				__( 'Plugins with Missing Version Information', 'wp-performance-doctor' ),
				sprintf(
					__( '%d plugin(s) do not report version information.', 'wp-performance-doctor' ),
					count( $missing_version )
				),
				array(
					'actual_value'   => implode( ', ', $missing_version ),
					'expected_value' => 'all plugins have version info',
					'unit'           => 'count',
				),
				__( 'Missing version information makes it difficult to determine if plugins need updates.', 'wp-performance-doctor' ),
				__( 'Contact plugin developers or check the plugin repository for version information.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Review plugins with missing version information.', 'wp-performance-doctor' ),
						__( 'Check for updates manually.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'All Plugins Have Version Information', 'wp-performance-doctor' ),
			__( 'All active plugins report version information.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'complete',
				'expected_value' => 'complete',
			)
		);
	}
}
