<?php
/**
 * Diagnostic severity levels.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

/**
 * Severity levels for diagnostic findings.
 *
 * Severity represents the potential impact if the issue is real.
 */
final class Severity {

	public const CRITICAL = 'critical';
	public const HIGH     = 'high';
	public const MEDIUM   = 'medium';
	public const LOW      = 'low';
	public const INFO     = 'info';

	/**
	 * All valid severity levels.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::CRITICAL,
			self::HIGH,
			self::MEDIUM,
			self::LOW,
			self::INFO,
		);
	}

	/**
	 * Numeric weight for sorting and scoring (higher = more severe).
	 *
	 * @param string $severity Severity level.
	 * @return int Weight value.
	 */
	public static function weight( string $severity ): int {
		return match ( $severity ) {
			self::CRITICAL => 100,
			self::HIGH     => 75,
			self::MEDIUM   => 50,
			self::LOW      => 25,
			self::INFO     => 5,
			default        => 0,
		};
	}

	/**
	 * Human-readable label for a severity level.
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	public static function label( string $severity ): string {
		return match ( $severity ) {
			self::CRITICAL => __( 'Critical', 'wp-performance-doctor' ),
			self::HIGH     => __( 'High', 'wp-performance-doctor' ),
			self::MEDIUM   => __( 'Medium', 'wp-performance-doctor' ),
			self::LOW      => __( 'Low', 'wp-performance-doctor' ),
			self::INFO     => __( 'Info', 'wp-performance-doctor' ),
			default        => __( 'Unknown', 'wp-performance-doctor' ),
		};
	}

	/**
	 * Check whether a severity value is valid.
	 *
	 * @param string $severity Severity level.
	 * @return bool
	 */
	public static function is_valid( string $severity ): bool {
		return in_array( $severity, self::all(), true );
	}
}
