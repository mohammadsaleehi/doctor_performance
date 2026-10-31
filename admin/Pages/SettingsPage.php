<?php
/**
 * Settings page for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

/**
 * Settings page placeholder.
 */
final class SettingsPage {

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
	 * Render the settings page.
	 */
	public function render(): void {
		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/settings.php';
	}
}
