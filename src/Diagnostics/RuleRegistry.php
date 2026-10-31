<?php
/**
 * Rule registry for WP Performance Doctor diagnostics.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Contracts\DiagnosticRuleInterface;

/**
 * Registry of diagnostic rules.
 */
final class RuleRegistry {

	/** @var \WPPerformanceDoctor\Contracts\DiagnosticRuleInterface[] */
	private array $rules = array();

	public function register( DiagnosticRuleInterface $rule ): void {
		$this->rules[ $rule->get_id() ] = $rule;
	}

	/** @return \WPPerformanceDoctor\Contracts\DiagnosticRuleInterface[] */
	public function get_rules(): array {
		return $this->rules;
	}

	public function has( string $id ): bool {
		return array_key_exists( $id, $this->rules );
	}
}
