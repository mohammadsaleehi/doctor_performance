<?php
/**
 * Recommendation manager for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Recommendations;

/**
 * Converts diagnostic results into actionable recommendations.
 */
final class RecommendationManager {

	public function __construct() {}

	/**
	 * Generate recommendations from diagnostic results.
	 *
	 * @param array<string, array{status: string, message: string}> $results
	 * @return array<string, array{title: string, advice: string}>
	 */
	public function generate( array $results ): array {
		$recommendations = array();

		foreach ( $results as $id => $result ) {
			if ( ( $result['status'] ?? '' ) !== 'pass' ) {
				$recommendations[ $id ] = array(
					'title'  => ucfirst( str_replace( '_', ' ', $id ) ),
					'advice' => $result['message'] ?? 'Review this diagnostic result.',
				);
			}
		}

		return $recommendations;
	}
}
