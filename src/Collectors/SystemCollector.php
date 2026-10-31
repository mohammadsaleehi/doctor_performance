<?php
/**
 * System diagnostic data collector.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers system-level diagnostic data (read-only).
 */
final class SystemCollector implements CollectorInterface {

	public function get_id(): string {
		return 'system';
	}

	public function collect(): array {
		return array(
			'php_version'        => PHP_VERSION,
			'php_sapi'           => PHP_SAPI,
			'os'                 => PHP_OS_FAMILY,
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'memory_usage'       => memory_get_usage( true ),
			'peak_memory_usage'  => memory_get_peak_usage( true ),
			'timezone'           => date_default_timezone_get(),
		);
	}
}
