<?php
/**
 * Diagnostic rule evaluation engine.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Rules\DiagnosticRuleInterface;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Evaluates diagnostic rules against a diagnostic context.
 *
 * Provides error isolation (one rule failure does not stop others),
 * required-metric validation, result normalization, deduplication,
 * and execution timing.
 */
final class DiagnosticEngine {

	/**
	 * Rule registry.
	 *
	 * @var RuleRegistry
	 */
	private RuleRegistry $registry;

	/**
	 * Maximum execution time in seconds for a full diagnostic run.
	 *
	 * @var int
	 */
	private int $max_execution_time;

	/**
	 * Constructor.
	 *
	 * @param RuleRegistry $registry Rule registry.
	 * @param int          $max_execution_time Maximum execution time in seconds.
	 */
	public function __construct( RuleRegistry $registry, int $max_execution_time = 60 ) {
		$this->registry           = $registry;
		$this->max_execution_time = $max_execution_time;
	}

	/**
	 * Run all enabled rules against the provided context.
	 *
	 * @param DiagnosticContext $context Diagnostic context.
	 * @param string[]|null     $categories Optional category filter.
	 * @return array{results: array<string, RuleResult>, findings: array<Finding>, duration: float, rules_evaluated: int, rules_skipped: int, rules_errored: int}
	 */
	public function run( DiagnosticContext $context, ?array $categories = null ): array {
		$start_time = microtime( true );
		$deadline   = $start_time + $this->max_execution_time;

		$results   = array();
		$findings  = array();
		$evaluated = 0;
		$skipped   = 0;
		$errored   = 0;

		$rules = $categories
			? $this->filter_by_category( $this->registry->get_enabled(), $categories )
			: $this->registry->get_enabled();

		// Sort by cost so cheap rules run first.
		usort(
			$rules,
			function ( DiagnosticRuleInterface $a, DiagnosticRuleInterface $b ) {
				$cost_order = array(
					'cheap'     => 0,
					'moderate'  => 1,
					'expensive' => 2,
				);
				return ( $cost_order[ $a->get_cost() ] ?? 1 ) <=> ( $cost_order[ $b->get_cost() ] ?? 1 );
			}
		);

		foreach ( $rules as $rule ) {
			// Check deadline before each rule.
			if ( microtime( true ) > $deadline ) {
				break;
			}

			// Validate required metrics.
			$required = $rule->get_required_metrics();
			$missing  = $context->missing_metrics( $required );

			if ( ! empty( $missing ) ) {
				$results[ $rule->get_id() ] = $rule->evaluate( $context );
				// The rule's evaluate() will handle missing metrics gracefully.
				++$skipped;
				++$evaluated;
				continue;
			}

			try {
				$result                     = $rule->evaluate( $context );
				$results[ $rule->get_id() ] = $result;
				++$evaluated;

				if ( $result->is_error() ) {
					++$errored;
				} elseif ( $result->is_skipped() ) {
					++$skipped;
				}

				// Convert problem/info results to findings.
				$finding = $result->to_finding(
					$context->get_snapshot_id(),
					null // Run ID set later by DiagnosticService.
				);
				if ( null !== $finding ) {
					$findings[ $finding->fingerprint ] = $finding;
				}
			} catch ( \Throwable $e ) {
				// Rule exception must not crash the engine.
				$results[ $rule->get_id() ] = new RuleResult(
					array(
						'rule_id'  => $rule->get_id(),
						'category' => $rule->get_category(),
						'outcome'  => 'error',
						'error'    => $e->getMessage(),
						'duration' => microtime( true ) - $start_time,
					)
				);
				++$errored;
				++$evaluated;
			}
		}

		$duration = microtime( true ) - $start_time;

		return array(
			'results'         => $results,
			'findings'        => array_values( $findings ),
			'duration'        => $duration,
			'rules_evaluated' => $evaluated,
			'rules_skipped'   => $skipped,
			'rules_errored'   => $errored,
			'total_rules'     => count( $rules ),
		);
	}

	/**
	 * Filter rules by category.
	 *
	 * @param \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[] $rules Rules.
	 * @param string[]                                             $categories Categories to include.
	 * @return \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	private function filter_by_category( array $rules, array $categories ): array {
		$filtered = array();
		foreach ( $rules as $rule ) {
			if ( in_array( $rule->get_category(), $categories, true ) ) {
				$filtered[ $rule->get_id() ] = $rule;
			}
		}
		return $filtered;
	}

	/**
	 * Get severity weights for scoring.
	 *
	 * @return array<string, int>
	 */
	public static function severity_weights(): array {
		return array(
			Severity::CRITICAL => 100,
			Severity::HIGH     => 75,
			Severity::MEDIUM   => 50,
			Severity::LOW      => 25,
			Severity::INFO     => 5,
		);
	}

	/**
	 * Get the maximum execution time.
	 *
	 * @return int
	 */
	public function get_max_execution_time(): int {
		return $this->max_execution_time;
	}
}
