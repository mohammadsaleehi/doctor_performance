<?php
/**
 * Recommendation provider converting findings into actionable advice.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

/**
 * Generates prioritized recommendations from diagnostic findings.
 *
 * Each recommendation contains the related finding, problem summary,
 * expected benefit, difficulty, risk, and remediation steps.
 */
final class RecommendationProvider {

	/**
	 * Generate recommendations from open findings.
	 *
	 * @param array<array<string, mixed>> $findings Open finding data arrays.
	 * @return array<array<string, mixed>>
	 */
	public function from_findings( array $findings ): array {
		$recommendations = array();

		foreach ( $findings as $finding ) {
			$rec = $this->build_recommendation( $finding );
			if ( null !== $rec ) {
				$recommendations[] = $rec;
			}
		}

		// Sort by severity weight (highest first), then confidence.
		usort(
			$recommendations,
			function ( $a, $b ) {
				$sev_order = array(
					'critical' => 0,
					'high'     => 1,
					'medium'   => 2,
					'low'      => 3,
					'info'     => 4,
				);
				$a_sev     = $sev_order[ $a['severity'] ?? 'info' ] ?? 4;
				$b_sev     = $sev_order[ $b['severity'] ?? 'info' ] ?? 4;
				if ( $a_sev !== $b_sev ) {
					return $a_sev <=> $b_sev;
				}
				return ( $b['confidence'] ?? 'low' ) <=> ( $a['confidence'] ?? 'low' );
			}
		);

		return $recommendations;
	}

	/**
	 * Build a single recommendation from a finding.
	 *
	 * @param array<string, mixed> $finding Finding data.
	 * @return array<string, mixed>|null
	 */
	private function build_recommendation( array $finding ): ?array {
		$rule_id     = $finding['rule_id'] ?? '';
		$category    = $finding['category'] ?? '';
		$severity    = $finding['severity'] ?? 'info';
		$evidence    = $this->decode_json( $finding['evidence'] ?? '' );
		$remediation = $this->decode_json( $finding['remediation'] ?? '' );

		$difficulty = $remediation['difficulty'] ?? 'medium';
		$risk       = $remediation['risk'] ?? 'low';
		$manual     = $remediation['requires_manual_action'] ?? true;

		return array(
			'id'                 => $finding['fingerprint'] ?? $rule_id,
			'rule_id'            => $rule_id,
			'title'              => $finding['title'] ?? $rule_id,
			'problem'            => $finding['description'] ?? '',
			'why_matters'        => $finding['impact'] ?? '',
			'expected_benefit'   => $this->expected_benefit( $severity ),
			'difficulty'         => $difficulty,
			'risk'               => $risk,
			'manual_action'      => $manual,
			'severity'           => $severity,
			'confidence'         => $finding['confidence'] ?? 'low',
			'steps'              => $remediation['steps'] ?? array(),
			'recommendation'     => $finding['recommendation'] ?? '',
			'related_finding_id' => $finding['id'] ?? null,
			'first_detected'     => $finding['first_detected'] ?? '',
			'last_detected'      => $finding['last_detected'] ?? '',
			'category'           => $category,
			'evidence'           => $evidence,
		);
	}

	/**
	 * Get expected benefit description based on severity.
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function expected_benefit( string $severity ): string {
		return match ( $severity ) {
			'critical' => __( 'Resolving this may prevent site errors or outages.', 'wp-performance-doctor' ),
			'high'     => __( 'Resolving this should noticeably improve performance or reliability.', 'wp-performance-doctor' ),
			'medium'   => __( 'Resolving this may improve performance or reduce risk.', 'wp-performance-doctor' ),
			'low'      => __( 'Resolving this is a minor optimization.', 'wp-performance-doctor' ),
			'info'     => __( 'Review for potential improvement.', 'wp-performance-doctor' ),
			default    => __( 'Review for potential improvement.', 'wp-performance-doctor' ),
		};
	}

	/**
	 * Decode a JSON-encoded value safely.
	 *
	 * @param string|array $value JSON string or array.
	 * @return array
	 */
	private function decode_json( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ? $decoded : array();
	}
}
