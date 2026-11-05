<?php
/**
 * Diagnostic service coordinating rule evaluation, finding persistence, and lifecycle.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;
use WPPerformanceDoctor\Rules\DiagnosticRuleInterface;
use WPPerformanceDoctor\Rules\RuleStatus;

/**
 * Coordinates diagnostic runs: building context, evaluating rules,
 * persisting findings, deduplication, lifecycle management, and
 * integration with health score and recommendations.
 */
final class DiagnosticService {

	/**
	 * Rule registry.
	 *
	 * @var RuleRegistry
	 */
	private RuleRegistry $registry;

	/**
	 * Diagnostic engine.
	 *
	 * @var DiagnosticEngine
	 */
	private DiagnosticEngine $engine;

	/**
	 * Monitoring storage.
	 *
	 * @var Storage
	 */
	private Storage $storage;

	/**
	 * Monitoring service (optional).
	 *
	 * @var Service|null
	 */
	private ?Service $monitoring_service;

	/**
	 * Health score calculator (optional).
	 *
	 * @var HealthScoreCalculator|null
	 */
	private ?HealthScoreCalculator $health_score_calculator;

	/**
	 * Recommendation provider (optional).
	 *
	 * @var RecommendationProvider|null
	 */
	private ?RecommendationProvider $recommendation_provider;

	/**
	 * Lock transient key to prevent concurrent diagnostic runs.
	 */
	public const LOCK_TRANSIENT = 'wpd_diagnostic_running';

	/**
	 * Lock timeout in seconds.
	 */
	public const LOCK_TIMEOUT = 600;

	/**
	 * Constructor.
	 *
	 * @param RuleRegistry     $registry Rule registry.
	 * @param DiagnosticEngine $engine Diagnostic engine.
	 * @param Storage          $storage Storage instance.
	 * @param Service|null     $monitoring_service Monitoring service.
	 */
	public function __construct(
		RuleRegistry $registry,
		DiagnosticEngine $engine,
		Storage $storage,
		?Service $monitoring_service = null
	) {
		$this->registry                = $registry;
		$this->engine                  = $engine;
		$this->storage                 = $storage;
		$this->monitoring_service      = $monitoring_service;
		$this->health_score_calculator = null;
		$this->recommendation_provider = null;
	}

	/**
	 * Set health score calculator.
	 *
	 * @param HealthScoreCalculator $calculator Health score calculator.
	 * @return self
	 */
	public function set_health_score_calculator( HealthScoreCalculator $calculator ): self {
		$this->health_score_calculator = $calculator;
		return $this;
	}

	/**
	 * Set recommendation provider.
	 *
	 * @param RecommendationProvider $provider Recommendation provider.
	 * @return self
	 */
	public function set_recommendation_provider( RecommendationProvider $provider ): self {
		$this->recommendation_provider = $provider;
		return $this;
	}

	/**
	 * Run a full diagnostic evaluation.
	 *
	 * Builds context from the latest monitoring snapshot, evaluates all
	 * enabled rules, persists findings, calculates health score, and
	 * generates recommendations.
	 *
	 * @param string     $trigger_type 'manual' or 'cron'.
	 * @param array|null $categories Optional category filter.
	 * @return array{run_id: int|null, success: bool, summary: array, error: string|null}
	 */
	public function run_diagnostics( string $trigger_type = 'manual', ?array $categories = null ): array {
		// Prevent concurrent diagnostic runs.
		if ( \get_transient( self::LOCK_TRANSIENT ) ) {
			return array(
				'run_id'  => null,
				'success' => false,
				'summary' => array(),
				'error'   => 'A diagnostic run is already in progress.',
			);
		}

		\set_transient( self::LOCK_TRANSIENT, '1', self::LOCK_TIMEOUT );

		try {
			return $this->perform_diagnostics( $trigger_type, $categories );
		} finally {
			\delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Perform the actual diagnostic run.
	 *
	 * @param string     $trigger_type Trigger type.
	 * @param array|null $categories Optional category filter.
	 * @return array
	 */
	private function perform_diagnostics( string $trigger_type, ?array $categories ): array {
		$start_time = microtime( true );

		// Get the latest monitoring snapshot.
		$snapshot_id = $this->storage->get_latest_snapshot_id();
		$snapshot    = null;
		$metrics     = array();

		if ( null !== $snapshot_id ) {
			$snapshot = $this->storage->get_snapshot( $snapshot_id );
			if ( null !== $snapshot ) {
				$metrics = $snapshot['metrics'] ?? array();
			}
		}

		// Create diagnostic run record.
		$run_id = $this->storage->create_diagnostic_run( $snapshot_id, $trigger_type );

		if ( false === $run_id ) {
			return array(
				'run_id'  => null,
				'success' => false,
				'summary' => array(),
				'error'   => 'Failed to create diagnostic run record.',
			);
		}

		// Build diagnostic context from latest metrics.
		$context = new DiagnosticContext( $metrics, $snapshot_id, $snapshot );

		// Run the engine.
		$engine_result = $this->engine->run( $context, $categories );

		$results      = $engine_result['results'];
		$raw_findings = $engine_result['findings'];

		// Deduplicate against existing open findings (don't re-insert same fingerprint).
		// save_diagnostic_findings() handles deduplication internally and updates
		// provenance (run_id, snapshot_id, last_detected) for re-detected findings.
		$new_findings = $raw_findings;

		// Persist findings.
		$save_result = $this->storage->save_diagnostic_findings( $run_id, $new_findings );

		// Mark findings resolved if their condition is no longer present.
		$resolved_count = $this->resolve_stale_findings( $results );

		// Calculate health score.
		$health_score  = null;
		$score_details = null;
		if ( null !== $this->health_score_calculator ) {
			$all_findings  = $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 1000 );
			$score_details = $this->health_score_calculator->calculate(
				$all_findings['items'],
				$engine_result['rules_evaluated']
			);
			$health_score  = $score_details['score'];
		}

		// Generate recommendations.
		$recommendations = array();
		if ( null !== $this->recommendation_provider ) {
			$open_findings   = $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 1000 );
			$recommendations = $this->recommendation_provider->from_findings( $open_findings['items'] );
		}

		// Count findings by severity.
		$severity_counts = $this->storage->get_finding_counts_by_severity();

		$duration = microtime( true ) - $start_time;

		// Update the run record.
		$this->storage->update_diagnostic_run(
			$run_id,
			array(
				'completed_at'       => \current_time( 'mysql' ),
				'status'             => 'completed',
				'rules_evaluated'    => $engine_result['rules_evaluated'],
				'findings_count'     => count( $raw_findings ),
				'critical_count'     => $severity_counts['critical'] ?? 0,
				'high_count'         => $severity_counts['high'] ?? 0,
				'medium_count'       => $severity_counts['medium'] ?? 0,
				'low_count'          => $severity_counts['low'] ?? 0,
				'info_count'         => $severity_counts['info'] ?? 0,
				'execution_duration' => $duration,
				'health_score'       => $health_score,
			)
		);

		return array(
			'run_id'  => $run_id,
			'success' => true,
			'summary' => array(
				'rules_evaluated' => $engine_result['rules_evaluated'],
				'rules_skipped'   => $engine_result['rules_skipped'],
				'rules_errored'   => $engine_result['rules_errored'],
				'total_rules'     => $engine_result['total_rules'],
				'findings_count'  => count( $raw_findings ),
				'new_findings'    => $save_result['inserted'],
				'updated_findings' => $save_result['updated'],
				'resolved_count'  => $resolved_count,
				'severity_counts' => $severity_counts,
				'health_score'    => $health_score,
				'score_details'   => $score_details,
				'duration'        => $duration,
			),
			'error'   => null,
		);
	}

	/**
	 * Mark findings as resolved when their rule now passes (condition no longer present).
	 *
	 * @param array<string, RuleResult> $results Rule evaluation results.
	 * @return int Number of findings resolved.
	 */
	private function resolve_stale_findings( array $results ): int {
		global $wpdb;

		$findings_table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $findings_table ) ) {
			return 0;
		}

		$rule_ids_with_results = array_keys( $results );
		if ( empty( $rule_ids_with_results ) ) {
			return 0;
		}

		// Get open findings for rules that were evaluated.
		$placeholders = implode( ',', array_fill( 0, count( $rule_ids_with_results ), '%s' ) );
		$sql          = 'SELECT id, fingerprint FROM ' . $findings_table .
			' WHERE status = %s AND rule_id IN (' . $placeholders . ')';
		$rows         = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name validated via preg_match; placeholders are structural %s repetitions.
			$wpdb->prepare(
				$sql,
				array_merge( array( RuleStatus::OPEN ), $rule_ids_with_results )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$resolved = 0;
		$now      = \current_time( 'mysql' );

		foreach ( $rows as $row ) {
			$fingerprint = $row['fingerprint'];
			$rule_id     = null;

			// Find which rule produced this fingerprint by checking results.
			foreach ( $results as $id => $result ) {
				if ( $result->fingerprint === $fingerprint ) {
					$rule_id = $id;
					break;
				}
			}

			// Only a reliable pass result means the condition is no longer present.
			// Unavailable/unsupported metrics must NOT resolve an existing finding;
			// the previous finding should be preserved until reliable evidence clears it.
			if ( null !== $rule_id && isset( $results[ $rule_id ] ) ) {
				$result = $results[ $rule_id ];
				if ( $result->is_pass() ) {
					$wpdb->update(
						$findings_table,
						array(
							'status'      => RuleStatus::RESOLVED,
							'resolved_at' => $now,
						),
						array( 'id' => (int) $row['id'] ),
						array( '%s', '%s' ),
						array( '%d' )
					);

					$wpdb->insert(
						$wpdb->prefix . 'wpd_finding_history',
						array(
							'finding_id'   => (int) $row['id'],
							'action'       => 'auto_resolved',
							'old_status'   => RuleStatus::OPEN,
							'new_status'   => RuleStatus::RESOLVED,
							'old_severity' => '',
							'new_severity' => '',
							'created_at'   => $now,
							'notes'        => 'Condition no longer present.',
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
					);

					++$resolved;
				}
			}
		}

		return $resolved;
	}

	/**
	 * Get the diagnostic engine.
	 *
	 * @return DiagnosticEngine
	 */
	public function get_engine(): DiagnosticEngine {
		return $this->engine;
	}

	/**
	 * Get the rule registry.
	 *
	 * @return RuleRegistry
	 */
	public function get_registry(): RuleRegistry {
		return $this->registry;
	}

	/**
	 * Check whether a diagnostic run is currently in progress.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return (bool) \get_transient( self::LOCK_TRANSIENT );
	}
}
