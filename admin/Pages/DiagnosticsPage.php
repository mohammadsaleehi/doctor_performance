<?php
/**
 * Diagnostics page for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

/**
 * Diagnostics page placeholder.
 */
final class DiagnosticsPage {

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
	 * Render the diagnostics page.
	 */
	public function render(): void {
		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/diagnostics.php';
	}
}
