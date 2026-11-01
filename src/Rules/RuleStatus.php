<?php
/**
 * Diagnostic finding statuses.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

/**
 * Finding lifecycle statuses.
 */
final class RuleStatus {

	public const OPEN         = 'open';
	public const ACKNOWLEDGED = 'acknowledged';
	public const RESOLVED     = 'resolved';
	public const IGNORED      = 'ignored';

	/**
	 * All valid statuses.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::OPEN,
			self::ACKNOWLEDGED,
			self::RESOLVED,
			self::IGNORED,
		);
	}

	/**
	 * Human-readable label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( string $status ): string {
		return match ( $status ) {
			self::OPEN       => __( 'Open', 'wp-performance-doctor' ),
			self::ACKNOWLEDGED => __( 'Acknowledged', 'wp-performance-doctor' ),
			self::RESOLVED   => __( 'Resolved', 'wp-performance-doctor' ),
			self::IGNORED    => __( 'Ignored', 'wp-performance-doctor' ),
			default          => __( 'Unknown', 'wp-performance-doctor' ),
		};
	}

	/**
	 * Check whether a status value is valid.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}
}
