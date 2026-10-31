<?php
/**
 * Helper functions for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Support;

/**
 * Helper functions for WP Performance Doctor.
 */
final class Helpers {

	public static function sanitize_array( array $data ): array {
		return array_map( 'sanitize_text_field', $data );
	}
}
