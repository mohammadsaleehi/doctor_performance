<?php
/**
 * Uninstallation routine for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Core;

/**
 * Uninstall handler for WP Performance Doctor.
 *
 * Conservative: never deletes user data without an explicit documented setting.
 */
final class Uninstall {

	/**
	 * Run the uninstall routine.
	 *
	 * Conservative: never deletes user data without an explicit documented setting.
	 */
	public static function run(): void {
		// No user data is removed by default. Future opt-in settings may be
		// honored here only when explicitly documented and confirmed.
	}
}
