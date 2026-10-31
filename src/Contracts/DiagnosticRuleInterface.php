<?php
/**
 * Diagnostic rule interface contract.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Contracts;

/**
 * A deterministic diagnostic rule.
 *
 * Rules evaluate collected data and produce results. They must never modify
 * WordPress configuration or database state.
 */
interface DiagnosticRuleInterface {

	/**
	 * Return a unique rule identifier.
	 */
	public function get_id(): string;

	/**
	 * Return a human-readable rule title.
	 */
	public function get_title(): string;

	/**
	 * Evaluate the rule against collected data.
	 *
	 * @param array<string, mixed> $data Collected diagnostic data.
	 * @return array{status: string, message: string, data?: array<string, mixed>}
	 */
	public function evaluate( array $data ): array;
}
