<?php
/**
 * Diagnostic rule interface contract.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;

/**
 * A deterministic diagnostic rule.
 *
 * Rules evaluate collected monitoring data and produce structured results.
 * They must never modify WordPress configuration or database state.
 * Each rule declares its required metrics, default severity, and configuration.
 */
interface DiagnosticRuleInterface {

	/**
	 * Return a unique rule identifier (e.g. system.php_memory_limit).
	 */
	public function get_id(): string;

	/**
	 * Return a human-readable rule title.
	 */
	public function get_title(): string;

	/**
	 * Return a short rule description.
	 */
	public function get_description(): string;

	/**
	 * Return the rule category.
	 */
	public function get_category(): string;

	/**
	 * Return the default severity level.
	 */
	public function get_severity(): string;

	/**
	 * Return the default confidence level.
	 */
	public function get_confidence(): string;

	/**
	 * Return the rule version string (semver).
	 */
	public function get_version(): string;

	/**
	 * Return metric identifiers required by this rule.
	 *
	 * @return string[]
	 */
	public function get_required_metrics(): array;

	/**
	 * Return whether the rule is cheap, moderate, or expensive to evaluate.
	 *
	 * @return string One of: cheap, moderate, expensive.
	 */
	public function get_cost(): string;

	/**
	 * Return whether the rule is currently enabled.
	 */
	public function is_enabled(): bool;

	/**
	 * Enable or disable the rule.
	 */
	public function set_enabled( bool $enabled ): void;

	/**
	 * Return the rule configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array;

	/**
	 * Set rule configuration values.
	 *
	 * @param array<string, mixed> $config Configuration values.
	 */
	public function set_config( array $config ): void;

	/**
	 * Validate configuration values.
	 *
	 * @param array<string, mixed> $config Configuration values.
	 * @return array{valid: bool, errors: string[]}
	 */
	public function validate_config( array $config ): array;

	/**
	 * Evaluate the rule against a diagnostic context.
	 *
	 * @param DiagnosticContext $context Normalized diagnostic context.
	 * @return RuleResult
	 */
	public function evaluate( DiagnosticContext $context ): RuleResult;
}
