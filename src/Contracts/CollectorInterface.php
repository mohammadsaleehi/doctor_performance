<?php
/**
 * Collector interface contract.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Contracts;

/**
 * Collect raw diagnostic data from a subsystem.
 */
interface CollectorInterface {

	/**
	 * Return a unique identifier for this collector.
	 */
	public function get_id(): string;

	/**
	 * Gather raw data.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array;
}
