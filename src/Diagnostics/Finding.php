<?php
/**
 * Diagnostic finding value object.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Rules\Confidence;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\RuleStatus;
use WPPerformanceDoctor\Rules\Severity;

/**
 * A single diagnostic finding produced by a rule evaluation.
 *
 * Represents a detected problem, potential problem, or informational result.
 * All data is read-only after construction.
 */
final class Finding {

	/**
	 * @var string
	 */
	public string $rule_id;

	/**
	 * @var string
	 */
	public string $rule_version;

	/**
	 * @var string
	 */
	public string $title;

	/**
	 * @var string
	 */
	public string $description;

	/**
	 * @var string
	 */
	public string $category;

	/**
	 * @var string
	 */
	public string $severity;

	/**
	 * @var string
	 */
	public string $confidence;

	/**
	 * @var string
	 */
	public string $status;

	/**
	 * Evidence supporting the finding.
	 *
	 * @var array<string, mixed>
	 */
	public array $evidence;

	/**
	 * Human-readable impact description.
	 *
	 * @var string
	 */
	public string $impact;

	/**
	 * Recommended remediation action.
	 *
	 * @var string
	 */
	public string $recommendation;

	/**
	 * Remediation details.
	 *
	 * @var array<string, mixed>
	 */
	public array $remediation;

	/**
	 * Deduplication fingerprint for detecting duplicate findings.
	 *
	 * @var string
	 */
	public string $fingerprint;

	/**
	 * Timestamp when first detected (ISO 8601).
	 *
	 * @var string
	 */
	public string $first_detected;

	/**
	 * Timestamp when last detected (ISO 8601).
	 *
	 * @var string
	 */
	public string $last_detected;

	/**
	 * Timestamp when resolved (ISO 8601), or empty string.
	 *
	 * @var string
	 */
	public string $resolved_at;

	/**
	 * Related monitoring snapshot ID.
	 *
	 * @var int|null
	 */
	public ?int $snapshot_id;

	/**
	 * Related diagnostic run ID.
	 *
	 * @var int|null
	 */
	public ?int $run_id;

	/**
	 * Constructor.
	 *
	 * @param array{
	 *     rule_id: string,
	 *     rule_version?: string,
	 *     title: string,
	 *     description: string,
	 *     category: string,
	 *     severity: string,
	 *     confidence: string,
	 *     status?: string,
	 *     evidence?: array<string, mixed>,
	 *     impact: string,
	 *     recommendation: string,
	 *     remediation?: array<string, mixed>,
	 *     fingerprint?: string,
	 *     first_detected?: string,
	 *     last_detected?: string,
	 *     resolved_at?: string,
	 *     snapshot_id?: int|null,
	 *     run_id?: int|null
	 * } $args Finding data.
	 */
	public function __construct( array $args ) {
		$this->rule_id        = $args['rule_id'];
		$this->rule_version   = $args['rule_version'] ?? '1.0.0';
		$this->title          = $args['title'];
		$this->description    = $args['description'];
		$this->category       = $args['category'];
		$this->severity       = $args['severity'];
		$this->confidence     = $args['confidence'];
		$this->status         = $args['status'] ?? RuleStatus::OPEN;
		$this->evidence       = $args['evidence'] ?? array();
		$this->impact         = $args['impact'] ?? '';
		$this->recommendation = $args['recommendation'] ?? '';
		$this->remediation    = $args['remediation'] ?? array();
		$this->fingerprint    = $args['fingerprint'] ?? $this->compute_fingerprint( $args );
		$this->first_detected = $args['first_detected'] ?? gmdate( 'Y-m-d H:i:s' );
		$this->last_detected  = $args['last_detected'] ?? $this->first_detected;
		$this->resolved_at    = $args['resolved_at'] ?? '';
		$this->snapshot_id    = $args['snapshot_id'] ?? null;
		$this->run_id         = $args['run_id'] ?? null;
	}

	/**
	 * Compute a stable fingerprint from rule id and key evidence.
	 *
	 * @param array $args Finding arguments.
	 * @return string
	 */
	private function compute_fingerprint( array $args ): string {
		$parts = array(
			$args['rule_id'],
			$args['category'],
			(string) ( $args['evidence']['actual_value'] ?? '' ),
			(string) ( $args['evidence']['expected_value'] ?? '' ),
		);

		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Convert to array for serialization/storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'rule_id'        => $this->rule_id,
			'rule_version'   => $this->rule_version,
			'title'          => $this->title,
			'description'    => $this->description,
			'category'       => $this->category,
			'severity'       => $this->severity,
			'confidence'     => $this->confidence,
			'status'         => $this->status,
			'evidence'       => $this->evidence,
			'impact'         => $this->impact,
			'recommendation' => $this->recommendation,
			'remediation'    => $this->remediation,
			'fingerprint'    => $this->fingerprint,
			'first_detected' => $this->first_detected,
			'last_detected'  => $this->last_detected,
			'resolved_at'    => $this->resolved_at,
			'snapshot_id'    => $this->snapshot_id,
			'run_id'         => $this->run_id,
		);
	}

	/**
	 * Check if the finding is open.
	 *
	 * @return bool
	 */
	public function is_open(): bool {
		return RuleStatus::OPEN === $this->status;
	}

	/**
	 * Check if the finding is resolved.
	 *
	 * @return bool
	 */
	public function is_resolved(): bool {
		return RuleStatus::RESOLVED === $this->status;
	}

	/**
	 * Check if the finding is acknowledged.
	 *
	 * @return bool
	 */
	public function is_acknowledged(): bool {
		return RuleStatus::ACKNOWLEDGED === $this->status;
	}

	/**
	 * Check if the finding is ignored.
	 *
	 * @return bool
	 */
	public function is_ignored(): bool {
		return RuleStatus::IGNORED === $this->status;
	}
}
