<?php
/**
 * Bootstrap and constants regression tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Admin\Admin;
use WPPerformanceDoctor\Admin\Menu;
use WPPerformanceDoctor\Admin\Pages\DashboardPage;
use WPPerformanceDoctor\Admin\Pages\DiagnosticsPage;
use WPPerformanceDoctor\Admin\Pages\RecommendationsPage;
use WPPerformanceDoctor\Admin\Pages\SettingsPage;
use WPPerformanceDoctor\Core\Plugin;
use WPPerformanceDoctor\Core\Uninstall;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;

use PHPUnit\Framework\TestCase;

/**
 * BootstrapTest class.
 *
 * @coversNothing
 */
final class BootstrapTest extends TestCase {


	/**
	 * Assert all required plugin constants are defined.
	 */
	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'WPD_PERFORMANCE_DOCTOR_VERSION' ) );
		$this->assertTrue( defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH' ) );
		$this->assertTrue( defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WPD_PERFORMANCE_DOCTOR_TEXT_DOMAIN' ) );
	}

	/**
	 * Assert constant values are correct.
	 */
	public function test_plugin_constants_have_expected_values(): void {
		$this->assertSame( '0.1.0', WPD_PERFORMANCE_DOCTOR_VERSION );
		$this->assertSame( 'wp-performance-doctor', WPD_PERFORMANCE_DOCTOR_TEXT_DOMAIN );
		$this->assertStringEndsWith( 'wp-performance-doctor.php', WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE );
		$this->assertStringEndsWith( 'wp-performance-doctor', WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH );
	}

	/**
	 * Assert Admin class can be instantiated without undefined constant errors.
	 */
	public function test_admin_class_instantiates(): void {
		$this->assertInstanceOf( Admin::class, Admin::get_instance() );
	}

	/**
	 * Assert Menu class can be instantiated.
	 */
	public function test_menu_class_instantiates(): void {
		$this->assertInstanceOf( Menu::class, Menu::get_instance() );
	}

	/**
	 * Assert all page classes can be instantiated via singleton and have render method.
	 *
	 * @dataProvider provider_page_classes
	 * @param string $page_class Fully-qualified page class name.
	 */
	public function test_page_classes_instantiate( $page_class ): void {
		$this->assertTrue( class_exists( $page_class ), sprintf( 'Class %s should exist.', $page_class ) );
		$this->assertTrue( method_exists( $page_class, 'render' ), sprintf( 'Class %s should have render() method.', $page_class ) );
		$instance = $page_class::get_instance();
		$this->assertInstanceOf( $page_class, $instance );
	}

	/**
	 * Data provider for admin page classes.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provider_page_classes(): array {
		return array(
			'DashboardPage'       => array( DashboardPage::class ),
			'DiagnosticsPage'     => array( DiagnosticsPage::class ),
			'RecommendationsPage' => array( RecommendationsPage::class ),
			'SettingsPage'        => array( SettingsPage::class ),
		);
	}

	/**
	 * Assert Plugin is a singleton.
	 */
	public function test_plugin_is_singleton(): void {
		$instance1 = Plugin::get_instance();
		$instance2 = Plugin::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Assert Uninstall run does not throw.
	 */
	public function test_uninstall_run_does_not_throw(): void {
		$threw = false;
		try {
			Uninstall::run();
		} catch ( \Throwable $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'Uninstall::run() should not throw an exception.' );
	}

	/**
	 * Assert Service::is_enabled() returns true when monitoring option is '1'.
	 */
	public function test_service_is_enabled_when_option_is_one(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true );
		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Assert Service::is_enabled() returns false when monitoring option is '0'.
	 */
	public function test_service_is_disabled_when_option_is_zero(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, false );
		$this->assertFalse( $service->is_enabled() );
	}

	/**
	 * Assert Service defaults to enabled when monitoring option is missing.
	 */
	public function test_service_defaults_to_enabled_when_option_missing(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage );
		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Assert Menu::set_monitoring() stores service and storage and passes them to DashboardPage.
	 */
	public function test_menu_set_monitoring_wires_dashboard(): void {
		$service = new Service( new Storage( '0.1.0' ), true );
		$storage = new Storage( '0.1.0' );

		$menu = Menu::get_instance();
		$menu->set_monitoring( $service, $storage );

		$reflection = new \ReflectionClass( $menu );
		$prop       = $reflection->getProperty( 'monitoring_service' );
		$prop->setAccessible( true );
		$this->assertSame( $service, $prop->getValue( $menu ) );

		$prop = $reflection->getProperty( 'storage' );
		$prop->setAccessible( true );
		$this->assertSame( $storage, $prop->getValue( $menu ) );
	}

	/**
	 * Assert Admin::get_instance()->init() wires service through Menu to DashboardPage.
	 */
	public function test_admin_init_wires_service_to_menu(): void {
		$service = new Service( new Storage( '0.1.0' ), true );
		$storage = new Storage( '0.1.0' );

		$admin = Admin::get_instance();
		$admin->init( $service, $storage );

		$menu = Menu::get_instance();
		$reflection = new \ReflectionClass( $menu );
		$prop       = $reflection->getProperty( 'monitoring_service' );
		$prop->setAccessible( true );
		$this->assertSame( $service, $prop->getValue( $menu ) );
	}

	/**
	 * Assert DashboardPage::set_service() stores the service for enabled-state rendering.
	 */
	public function test_dashboard_page_set_service(): void {
		$service = new Service( new Storage( '0.1.0' ), true );
		$storage = new Storage( '0.1.0' );

		$dashboard = DashboardPage::get_instance();
		$dashboard->set_service( $service );
		$dashboard->set_storage( $storage );

		$reflection = new \ReflectionClass( $dashboard );
		$prop       = $reflection->getProperty( 'service' );
		$prop->setAccessible( true );
		$this->assertSame( $service, $prop->getValue( $dashboard ) );

		$prop = $reflection->getProperty( 'storage' );
		$prop->setAccessible( true );
		$this->assertSame( $storage, $prop->getValue( $dashboard ) );
	}
}
