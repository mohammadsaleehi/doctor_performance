<?php
/**
 * Active theme diagnostic rule.
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
 * Rule: Active theme information.
 */
final class ActiveThemeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.active_theme',
				'title'            => __( 'Active Theme Information', 'wp-performance-doctor' ),
				'description'      => __( 'Reports the active theme and its version.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::PLUGIN_THEME,
				'severity'         => Severity::INFO,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.active_theme', 'wordpress.active_theme_version' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$theme   = $this->get_metric( $context, 'wordpress.active_theme' );
		$version = $this->get_metric( $context, 'wordpress.active_theme_version' );

		if ( ! $context->has_metric( 'wordpress.active_theme' ) ) {
			return $this->result_unavailable( __( 'Active theme metric is not available.', 'wp-performance-doctor' ) );
		}

		$theme_str   = $theme ? (string) $theme : 'unknown';
		$version_str = $version ? (string) $version : 'unknown';

		return $this->result_info(
			sprintf( __( 'Active Theme: %1$s v%2$s', 'wp-performance-doctor' ), $theme_str, $version_str ),
			sprintf( __( 'The active theme is "%1$s" version %2$s.', 'wp-performance-doctor' ), $theme_str, $version_str ),
			array(
				'actual_value'   => $theme_str . ' v' . $version_str,
				'expected_value' => 'context',
			)
		);
	}
}
