<?php
/**
 * Admin menu registration for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin;

use WPPerformanceDoctor\Admin\Pages\DashboardPage;
use WPPerformanceDoctor\Admin\Pages\DiagnosticsPage;
use WPPerformanceDoctor\Admin\Pages\RecommendationsPage;
use WPPerformanceDoctor\Admin\Pages\SettingsPage;
use WPPerformanceDoctor\Security\CapabilityManager;

/**
 * Top-level admin menu registration.
 */
final class Menu {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	private const PAGE_SLUG = 'wp-performance-doctor';

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
	 * Register the admin menu and submenu pages.
	 */
	public function register(): void {
		if ( ! ( new CapabilityManager() )->can_manage_options() ) {
			return;
		}

		$page = add_menu_page(
			__( 'Performance Doctor', 'wp-performance-doctor' ),
			__( 'Performance Doctor', 'wp-performance-doctor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( DashboardPage::get_instance(), 'render' ),
			'dashicons-chart-area',
			55
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Dashboard', 'wp-performance-doctor' ),
			__( 'Dashboard', 'wp-performance-doctor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( DashboardPage::get_instance(), 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Diagnostics', 'wp-performance-doctor' ),
			__( 'Diagnostics', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-diagnostics',
			array( DiagnosticsPage::get_instance(), 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Recommendations', 'wp-performance-doctor' ),
			__( 'Recommendations', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-recommendations',
			array( RecommendationsPage::get_instance(), 'render' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', 'wp-performance-doctor' ),
			__( 'Settings', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-settings',
			array( SettingsPage::get_instance(), 'render' )
		);
	}
}
