<?php
/**
 * WordPress permalink diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\WordPress;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Default permalink configuration.
 *
 * The default "plain" permalink structure is not SEO-friendly.
 */
final class PermalinkRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.permalink_structure',
				'title'            => __( 'Default Permalink Structure', 'wp-performance-doctor' ),
				'description'      => __( 'The site is using the default plain permalink structure.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.permalink_structure' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$structure = $this->get_metric( $context, 'wordpress.permalink_structure' );

		if ( ! $context->has_metric( 'wordpress.permalink_structure' ) ) {
			return $this->result_unavailable( __( 'Permalink structure metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $structure || '' === $structure ) {
			return $this->result_fail(
				__( 'Default Permalink Structure', 'wp-performance-doctor' ),
				__( 'Permalinks are set to the default plain structure (?p=123), which is not SEO-friendly.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'default',
					'expected_value' => 'post_name or custom structure',
					'unit'           => 'string',
				),
				__( 'Default permalinks are less readable and may affect search engine optimization.', 'wp-performance-doctor' ),
				__( 'Change the permalink structure to "Post name" or another readable format in Settings > Permalinks.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Go to Settings > Permalinks in the WordPress admin.', 'wp-performance-doctor' ),
						__( 'Select "Post name" or a custom structure.', 'wp-performance-doctor' ),
						__( 'Save changes to update permalink rules.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'Permalink Structure Is Configured', 'wp-performance-doctor' ),
			sprintf( __( 'Permalink structure is "%s".', 'wp-performance-doctor' ), esc_html( (string) $structure ) ),
			array(
				'actual_value'   => (string) $structure,
				'expected_value' => 'non-default',
			)
		);
	}
}
