<?php
/**
 * Diagnostic confidence levels.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

/**
 * Confidence levels for diagnostic findings.
 *
 * Confidence represents how reliable the evidence is.
 */
final class Confidence {

	public const HIGH   = 'high';
	public const MEDIUM = 'medium';
	public const LOW    = 'low';

	/**
	 * All valid confidence levels.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::HIGH,
			self::MEDIUM,
			self::LOW,
		);
	}

	/**
	 * Numeric weight for scoring (higher = more confident).
	 *
	 * @param string $confidence Confidence level.
	 * @return int Weight value.
	 */
	public static function weight( string $confidence ): int {
		return match ( $confidence ) {
			self::HIGH   => 100,
			self::MEDIUM => 50,
			self::LOW    => 10,
			default      => 0,
		};
	}

	/**
	 * Human-readable label.
	 *
	 * @param string $confidence Confidence level.
	 * @return string
	 */
	public static function label( string $confidence ): string {
		return match ( $confidence ) {
			self::HIGH   => __( 'High', 'wp-performance-doctor' ),
			self::MEDIUM => __( 'Medium', 'wp-performance-doctor' ),
			self::LOW    => __( 'Low', 'wp-performance-doctor' ),
			default      => __( 'Unknown', 'wp-performance-doctor' ),
		};
	}

	/**
	 * Check whether a confidence value is valid.
	 *
	 * @param string $confidence Confidence level.
	 * @return bool
	 */
	public static function is_valid( string $confidence ): bool {
		return in_array( $confidence, self::all(), true );
	}
}
