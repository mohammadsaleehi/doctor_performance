<?php
/**
 * Database schema definitions for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Database;

/**
 * Database schema definitions for diagnostic report storage.
 */
final class Schema {

	public const TABLE_REPORTS = 'wp_reports';

	public function get_tables(): array {
		return array( self::TABLE_REPORTS );
	}
}
