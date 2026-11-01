<?php
/**
 * WordPress search engine visibility diagnostic rule.
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
 * Rule: Search engine visibility disabled.
 *
 * The blog_public option being 0 discourages search engines from indexing.
 * May be intentional for staging sites.
 */
final class SearchVisibilityRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.search_engine_visibility',
				'title'            => __( 'Search Engine Visibility Disabled', 'wp-performance-doctor' ),
				'description'      => __( 'The site is configured to discourage search engines from indexing it.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array(),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		// We don't have a direct metric for blog_public, but we can check
		// the environment type as context. This rule is informational since
		// it may be intentional (staging/dev sites).
		return $this->result_info(
			__( 'Search Engine Visibility Setting', 'wp-performance-doctor' ),
			__( 'Check Settings > Reading to confirm search engine visibility is set as intended.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => 'check_required',
				'expected_value' => 'enabled for production',
			)
		);
	}
}
