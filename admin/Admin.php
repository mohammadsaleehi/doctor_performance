<?php
/**
 * Admin integration for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin;

use WPPerformanceDoctor\Core\Plugin;

/**
 * Admin integration for WP Performance Doctor.
 *
 * Presentation and WordPress admin integration only.
 */
final class Admin {

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
	 * Initialize admin integration.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( Menu::get_instance(), 'register' ) );
		add_action( 'admin_init', array( $this, 'register_assets' ) );
	}

	/**
	 * Register admin assets.
	 */
	public function register_assets(): void {
		wp_enqueue_style(
			'wp-performance-doctor-admin',
			plugin_dir_url( \WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE ) . 'assets/css/admin.css',
			array(),
			\WPD_PERFORMANCE_DOCTOR_VERSION
		);

		wp_enqueue_script(
			'wp-performance-doctor-admin',
			plugin_dir_url( \WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE ) . 'assets/js/admin.js',
			array(),
			\WPD_PERFORMANCE_DOCTOR_VERSION,
			true
		);
	}
}
