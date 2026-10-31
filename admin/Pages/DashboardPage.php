<?php
/**
 * Dashboard page for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

/**
 * Dashboard page placeholder.
 */
final class DashboardPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Render the dashboard page.
	 */
	public function render(): void {
		require WP_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/dashboard.php';
	}
}
