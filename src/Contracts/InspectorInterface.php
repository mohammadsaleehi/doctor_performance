<?php
/**
 * Inspector interface contract.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Contracts;

/**
 * Inspect a specific WordPress or server subsystem.
 *
 * Inspections are read-only and must not modify configuration or state.
 */
interface InspectorInterface {

	/**
	 * Return a unique identifier for this inspector.
	 */
	public function get_id(): string;

	/**
	 * Run the inspection and return collected data.
	 *
	 * @return array<string, mixed>
	 */
	public function inspect(): array;
}
