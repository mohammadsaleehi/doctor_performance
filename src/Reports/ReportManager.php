<?php
/**
 * Report manager for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Reports;

/**
 * Stores and retrieves diagnostic reports.
 */
final class ReportManager {

	public function __construct() {}

	public function save( array $report ): void {
		// Reserved for future persistence layer.
	}

	public function get( string $id ): ?array {
		return null;
	}
}
