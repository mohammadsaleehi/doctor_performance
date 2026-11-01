<?php
/**
 * Diagnostic rule result value object.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Rules\Confidence;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\RuleStatus;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Result of evaluating a single diagnostic rule.
 *
 * Encapsulates the finding (if any) plus metadata about the evaluation.
 */
final class RuleResult {

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
	public string $category;

	/**
	 * Evaluation outcome: 'pass', 'fail', 'warning', 'info', 'unavailable', 'unsupported', 'error'.
	 *
	 * @var string
	 */
	public string $outcome;

	/**
	 * @var string|null
	 */
	public ?string $fingerprint;

	/**
	 * Severity when outcome is fail/warning.
	 *
	 * @var string|null
	 */
	public ?string $severity;

	/**
	 * @var string|null
	 */
	public ?string $confidence;

	/**
	 * @var string|null
	 */
	public ?string $title;

	/**
	 * @var string|null
	 */
	public ?string $description;

	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $evidence;

	/**
	 * @var string|null
	 */
	public ?string $impact;

	/**
	 * @var string|null
	 */
	public ?string $recommendation;

	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $remediation;

	/**
	 * Evaluation duration in seconds.
	 *
	 * @var float
	 */
	public float $duration;

	/**
	 * Error message if evaluation failed.
	 *
	 * @var string|null
	 */
	public ?string $error;

	/**
	 * Whether the rule was skipped (e.g. disabled or missing metrics).
	 *
	 * @var bool
	 */
	public bool $skipped;

	/**
	 * Skip reason if skipped.
	 *
	 * @var string|null
	 */
	public ?string $skip_reason;

	/**
	 * Constructor.
	 *
	 * @param array{
	 *     rule_id: string,
	 *     rule_version?: string,
	 *     category: string,
	 *     outcome: string,
	 *     fingerprint?: string|null,
	 *     severity?: string|null,
	 *     confidence?: string|null,
	 *     title?: string|null,
	 *     description?: string|null,
	 *     evidence?: array<string, mixed>|null,
	 *     impact?: string|null,
	 *     recommendation?: string|null,
	 *     remediation?: array<string, mixed>|null,
	 *     duration?: float,
	 *     error?: string|null,
	 *     skipped?: bool,
	 *     skip_reason?: string|null
	 * } $args Result data.
	 */
	public function __construct( array $args ) {
		$this->rule_id        = $args['rule_id'];
		$this->rule_version   = $args['rule_version'] ?? '1.0.0';
		$this->category       = $args['category'];
		$this->outcome        = $args['outcome'];
		$this->fingerprint    = $args['fingerprint'] ?? null;
		$this->severity       = $args['severity'] ?? null;
		$this->confidence     = $args['confidence'] ?? null;
		$this->title          = $args['title'] ?? null;
		$this->description    = $args['description'] ?? null;
		$this->evidence       = $args['evidence'] ?? null;
		$this->impact         = $args['impact'] ?? null;
		$this->recommendation = $args['recommendation'] ?? null;
		$this->remediation    = $args['remediation'] ?? null;
		$this->duration       = $args['duration'] ?? 0.0;
		$this->error          = $args['error'] ?? null;
		$this->skipped        = $args['skipped'] ?? false;
		$this->skip_reason    = $args['skip_reason'] ?? null;
	}

	/**
	 * Check if the result represents a confirmed problem.
	 *
	 * @return bool
	 */
	public function is_problem(): bool {
		return in_array( $this->outcome, array( 'fail', 'warning' ), true );
	}

	/**
	 * Check if the result is informational only.
	 *
	 * @return bool
	 */
	public function is_info(): bool {
		return 'info' === $this->outcome;
	}

	/**
	 * Check if the metric/data was unavailable.
	 *
	 * @return bool
	 */
	public function is_unavailable(): bool {
		return 'unavailable' === $this->outcome;
	}

	/**
	 * Check if the check is unsupported.
	 *
	 * @return bool
	 */
	public function is_unsupported(): bool {
		return 'unsupported' === $this->outcome;
	}

	/**
	 * Check if the check passed (no problem).
	 *
	 * @return bool
	 */
	public function is_pass(): bool {
		return 'pass' === $this->outcome;
	}

	/**
	 * Check if evaluation errored.
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return 'error' === $this->outcome;
	}

	/**
	 * Check if the rule was skipped.
	 *
	 * @return bool
	 */
	public function is_skipped(): bool {
		return $this->skipped;
	}

	/**
	 * Convert to a Finding if this result is a problem or info.
	 *
	 * @param int|null $snapshot_id Related snapshot ID.
	 * @param int|null $run_id Related run ID.
	 * @return Finding|null
	 */
	public function to_finding( ?int $snapshot_id = null, ?int $run_id = null ): ?Finding {
		if ( ! $this->is_problem() && ! $this->is_info() ) {
			return null;
		}

		return new Finding(
			array(
				'rule_id'        => $this->rule_id,
				'rule_version'   => $this->rule_version,
				'title'          => $this->title ? $this->title : $this->rule_id,
				'description'    => $this->description ? $this->description : '',
				'category'       => $this->category,
				'severity'       => $this->severity ? $this->severity : Severity::INFO,
				'confidence'     => $this->confidence ? $this->confidence : Confidence::LOW,
				'status'         => RuleStatus::OPEN,
				'evidence'       => $this->evidence ? $this->evidence : array(),
				'impact'         => $this->impact ? $this->impact : '',
				'recommendation' => $this->recommendation ? $this->recommendation : '',
				'remediation'    => $this->remediation ? $this->remediation : array(),
				'fingerprint'    => $this->fingerprint,
				'snapshot_id'    => $snapshot_id,
				'run_id'         => $run_id,
			)
		);
	}
}
