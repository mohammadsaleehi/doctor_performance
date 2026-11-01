<?php
/**
 * WordPress core update diagnostic rule.
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
 * Rule: WordPress core version update availability.
 *
 * Only fires when trustworthy update information exists. Uses the
 * wordpress.wordpress_version metric as context.
 */
final class CoreUpdateRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.core_update',
				'title'            => __( 'WordPress Core Update Available', 'wp-performance-doctor' ),
				'description'      => __( 'A newer version of WordPress is available.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'low',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.wordpress_version' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$version = $this->get_metric( $context, 'wordpress.wordpress_version' );

		if ( ! $context->has_metric( 'wordpress.wordpress_version' ) ) {
			return $this->result_unavailable( __( 'WordPress version metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $version || '' === $version ) {
			return $this->result_unavailable( __( 'WordPress version could not be determined.', 'wp-performance-doctor' ) );
		}

		// We don't have direct access to the update API data in metrics.
		// This rule provides context for administrators to check updates.
		return $this->result_info(
			__( 'WordPress Core Version', 'wp-performance-doctor' ),
			sprintf(
				__( 'The site is running WordPress %s. Check Dashboard > Updates for available updates.', 'wp-performance-doctor' ),
				esc_html( (string) $version )
			),
			array(
				'actual_value'   => (string) $version,
				'expected_value' => 'latest',
			)
		);
	}
}
