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

	private static ?self $instance = null;

	private Container $container;

	private function __construct() {
		$this->container = new Container();
		$this->container->set( Container::class, $this->container );
		$this->container->set( self::class, $this );
	}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function container(): Container {
		return $this->container;
	}

	public function init(): void {
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_integration' ) );
	}

	public function register_admin_integration(): void {
		if ( class_exists( \WPPerformanceDoctor\Admin\Admin::class ) ) {
			\WPPerformanceDoctor\Admin\Admin::get_instance()->init();
		}
	}
}
