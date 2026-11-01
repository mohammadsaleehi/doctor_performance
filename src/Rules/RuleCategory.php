<?php
/**
 * Diagnostic rule categories.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

/**
 * Rule categories for organizing diagnostic rules.
 */
final class RuleCategory {

	public const SYSTEM       = 'system';
	public const WORDPRESS    = 'wordpress';
	public const DATABASE     = 'database';
	public const CACHE        = 'cache';
	public const PLUGIN_THEME = 'plugin_theme';
	public const PROFILER     = 'profiler';
	public const FRONTEND     = 'frontend';

	/**
	 * All valid categories.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::SYSTEM,
			self::WORDPRESS,
			self::DATABASE,
			self::CACHE,
			self::PLUGIN_THEME,
			self::PROFILER,
			self::FRONTEND,
		);
	}

	/**
	 * Human-readable label for a category.
	 *
	 * @param string $category Category.
	 * @return string
	 */
	public static function label( string $category ): string {
		return match ( $category ) {
			self::SYSTEM      => __( 'PHP & Server', 'wp-performance-doctor' ),
			self::WORDPRESS   => __( 'WordPress Configuration', 'wp-performance-doctor' ),
			self::DATABASE    => __( 'Database', 'wp-performance-doctor' ),
			self::CACHE       => __( 'Caching', 'wp-performance-doctor' ),
			self::PLUGIN_THEME => __( 'Plugins & Themes', 'wp-performance-doctor' ),
			self::PROFILER    => __( 'PHP Request Profiling', 'wp-performance-doctor' ),
			self::FRONTEND    => __( 'Frontend Assets', 'wp-performance-doctor' ),
			default           => __( 'General', 'wp-performance-doctor' ),
		};
	}

	/**
	 * Check whether a category value is valid.
	 *
	 * @param string $category Category.
	 * @return bool
	 */
	public static function is_valid( string $category ): bool {
		return in_array( $category, self::all(), true );
	}
}
