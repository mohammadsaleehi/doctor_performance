<?php
/**
 * Diagnostic engine for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

/**
 * Evaluates collected data against registered diagnostic rules.
 */
final class DiagnosticEngine {

	/** @var \WPPerformanceDoctor\Diagnostics\RuleRegistry */
	private $registry;

	public function __construct( RuleRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Run all registered rules against the provided data.
	 *
	 * @param array<string, mixed> $data Collected diagnostic data.
	 * @return array<string, array{status: string, message: string, data?: array<string, mixed>}>
	 */
	public function run( array $data ): array {
		$results = array();

		foreach ( $this->registry->get_rules() as $rule ) {
			$results[ $rule->get_id() ] = $rule->evaluate( $data );
		}

		return $results;
	}
}
