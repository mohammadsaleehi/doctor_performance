<?php
/**
 * Main application bootstrap for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Core;
/**
 * Main application bootstrap.
 *
 * Owns the dependency container and wires plugin components together.
 * Core stays independent of the admin UI.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->container->set( Container::class, $this->container );
		$this->container->set( self::class, $this );
	}

	/**
	 * Get the singleton instance.
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
	 * Get the dependency injection container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		$this->register_hooks();

		if ( is_admin() ) {
			$this->register_admin_integration();
		}
	}

	/**
	 * Register core hooks.
	 */
	private function register_hooks(): void {
		// Register core hooks here.
	}

	/**
	 * Initialize the admin integration.
	 */
	public function register_admin_integration(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( class_exists( \WPPerformanceDoctor\Admin\Admin::class ) ) {
			\WPPerformanceDoctor\Admin\Admin::get_instance()->init();
		}
	}
}
