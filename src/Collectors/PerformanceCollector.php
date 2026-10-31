<?php
/**
 * Performance diagnostic data collector.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers performance-related diagnostic data (read-only).
 */
final class PerformanceCollector implements CollectorInterface {

	public function get_id(): string {
		return 'performance';
	}

	public function collect(): array {
		return array(
			'memory_usage'      => memory_get_usage( true ),
			'peak_memory_usage' => memory_get_peak_usage( true ),
			'request_time'      => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true ),
		);
	}
}
