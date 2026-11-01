<?php
/**
 * Website health score calculator.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Rules\Confidence;
use WPPerformanceDoctor\Rules\RuleStatus;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Calculates a transparent Website Health Score from 0 to 100 based on
 * diagnostic findings.
 *
 * The score is derived from actual findings, weighted by severity and
 * confidence. It is not a certified quality measure.
 */
final class HealthScoreCalculator {

	/**
	 * Severity deduction weights (points deducted per finding).
	 *
	 * @var array<string, int>
	 */
	private array $severity_weights;

	/**
	 * Confidence multipliers.
	 *
	 * @var array<string, float>
	 */
	private array $confidence_multipliers;

	/**
	 * Maximum points that can be deducted.
	 *
	 * @var int
	 */
	private int $max_deduction;

	/**
	 * Minimum number of rules evaluated for a valid score.
	 *
	 * @var int
	 */
	private int $min_coverage;

	/**
	 * Constructor.
	 *
	 * @param array{
	 *     severity_weights?: array<string, int>,
	 *     confidence_multipliers?: array<string, float>,
	 *     max_deduction?: int,
	 *     min_coverage?: int
	 * } $config Configuration.
	 */
	public function __construct( array $config = array() ) {
		$this->severity_weights = $config['severity_weights'] ?? array(
			Severity::CRITICAL => 25,
			Severity::HIGH     => 15,
			Severity::MEDIUM   => 8,
			Severity::LOW      => 3,
			Severity::INFO     => 0,
		);

		$this->confidence_multipliers = $config['confidence_multipliers'] ?? array(
			Confidence::HIGH   => 1.0,
			Confidence::MEDIUM => 0.6,
			Confidence::LOW    => 0.3,
		);

		$this->max_deduction = $config['max_deduction'] ?? 100;
		$this->min_coverage  = $config['min_coverage'] ?? 5;
	}

	/**
	 * Calculate the health score from findings.
	 *
	 * @param array<array<string, mixed>> $findings Finding data arrays (open findings).
	 * @param int                         $rules_evaluated Number of rules evaluated in the latest run.
	 * @return array{
	 *     score: int|null,
	 *     max_score: int,
	 *     deduction: int,
	 *     breakdown: array<string, array{count: int, deduction: int}>,
	 *     coverage: int,
	 *     sufficient: bool,
	 *     reasons: string[]
	 * }
	 */
	public function calculate( array $findings, int $rules_evaluated ): array {
		$sufficient = $rules_evaluated >= $this->min_coverage;

		if ( ! $sufficient ) {
			return array(
				'score'      => null,
				'max_score'  => 100,
				'deduction'  => 0,
				'breakdown'  => array(),
				'coverage'   => $rules_evaluated,
				'sufficient' => false,
				'reasons'    => array(
					sprintf(
						__( 'Insufficient diagnostic coverage: %1$d rules evaluated, minimum %2$d required.', 'wp-performance-doctor' ),
						$rules_evaluated,
						$this->min_coverage
					),
				),
			);
		}

		$deduction = 0;
		$breakdown = array();
		$reasons   = array();

		$severity_order = array(
			Severity::CRITICAL,
			Severity::HIGH,
			Severity::MEDIUM,
			Severity::LOW,
			Severity::INFO,
		);

		foreach ( $severity_order as $severity ) {
			$sev_findings = array_filter(
				$findings,
				function ( $finding ) use ( $severity ) {
					return isset( $finding['severity'] ) && $finding['severity'] === $severity;
				}
			);

			$count  = count( $sev_findings );
			$weight = $this->severity_weights[ $severity ] ?? 0;

			// Info findings do not deduct points.
			if ( 0 === $weight ) {
				continue;
			}

			$sev_ded = 0;

			foreach ( $sev_findings as $finding ) {
				$confidence = $finding['confidence'] ?? Confidence::LOW;
				$multiplier = $this->confidence_multipliers[ $confidence ] ?? 0.3;
				$sev_ded   += (int) max( 1, round( $weight * $multiplier ) );
			}

			if ( $count > 0 ) {
				$breakdown[ $severity ] = array(
					'count'     => $count,
					'deduction' => $sev_ded,
				);
				$deduction             += $sev_ded;

				$label = match ( $severity ) {
					Severity::CRITICAL => __( 'Critical', 'wp-performance-doctor' ),
					Severity::HIGH     => __( 'High', 'wp-performance-doctor' ),
					Severity::MEDIUM   => __( 'Medium', 'wp-performance-doctor' ),
					Severity::LOW      => __( 'Low', 'wp-performance-doctor' ),
					Severity::INFO     => __( 'Info', 'wp-performance-doctor' ),
					default            => $severity,
				};
				$reasons[] = sprintf(
					__( '%1$d %2$s finding(s) deducted %3$d point(s).', 'wp-performance-doctor' ),
					$count,
					$label,
					$sev_ded
				);
			}
		}

		// Cap the deduction.
		$deduction = min( $deduction, $this->max_deduction );
		$score     = max( 0, 100 - $deduction );

		return array(
			'score'      => (int) $score,
			'max_score'  => 100,
			'deduction'  => (int) $deduction,
			'breakdown'  => $breakdown,
			'coverage'   => $rules_evaluated,
			'sufficient' => true,
			'reasons'    => $reasons,
		);
	}

	/**
	 * Get a descriptive label for a score.
	 *
	 * @param int|null $score Health score.
	 * @return string
	 */
	public static function score_label( ?int $score ): string {
		if ( null === $score ) {
			return __( 'Insufficient data', 'wp-performance-doctor' );
		}

		if ( $score >= 90 ) {
			return __( 'Excellent', 'wp-performance-doctor' );
		}
		if ( $score >= 75 ) {
			return __( 'Good', 'wp-performance-doctor' );
		}
		if ( $score >= 60 ) {
			return __( 'Fair', 'wp-performance-doctor' );
		}
		if ( $score >= 40 ) {
			return __( 'Poor', 'wp-performance-doctor' );
		}

		return __( 'Critical', 'wp-performance-doctor' );
	}
}
