<?php
/**
 * Settings page for WP Performance Doctor admin.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Monitoring\Service;

/**
 * Settings page for WP Performance Doctor admin.
 *
 * Provides configuration options for monitoring, diagnostics, and
 * general plugin settings.
 */
final class SettingsPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Monitoring service.
	 *
	 * @var Service|null
	 */
	private ?Service $service = null;

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
	 * Set monitoring service.
	 *
	 * @param Service $service Monitoring service.
	 * @return self
	 */
	public function set_service( Service $service ): self {
		$this->service = $service;
		return $this;
	}

	/**
	 * Register settings page and menu items.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the Settings submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'wp-performance-doctor',
			__( 'Settings', 'wp-performance-doctor' ),
			__( 'Settings', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'wpd_settings', 'wpd_monitoring_enabled' );
		register_setting( 'wpd_settings', 'wpd_monitoring_interval' );
		register_setting( 'wpd_settings', 'wpd_profiler_sample_rate' );
		register_setting( 'wpd_settings', 'wpd_diagnostics_enabled' );
		register_setting( 'wpd_settings', 'wpd_diagnostic_interval' );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/settings.php';
	}
}
