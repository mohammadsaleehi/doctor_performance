<?php
/**
 * Dashboard dependency injection and data retrieval tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Admin\Pages\DashboardPage;
use WPPerformanceDoctor\Core\Plugin;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;
use PHPUnit\Framework\TestCase;

/**
 * DashboardTest class.
 *
 * @coversNothing
 */
final class DashboardTest extends TestCase
{

	/**
	 * Reset DashboardPage singleton before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$reflection = new \ReflectionClass( DashboardPage::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( $reflection, null );
	}

	/**
	 * Reset Plugin singleton before each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void
	{
		parent::tearDown();
		$reflection = new \ReflectionClass( Plugin::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( $reflection, null );
	}

	/**
	 * Assert DashboardPage::get_dashboard_data() returns empty state when no service/storage.
	 */
	public function test_dashboard_data_without_service_storage(): void {
		$dashboard = DashboardPage::get_instance();
		$method    = new \ReflectionMethod( $dashboard, 'get_dashboard_data' );
		$method->setAccessible( true );

		$data = $method->invoke( $dashboard );

		$this->assertFalse( $data['enabled'] );
		$this->assertSame( 0, $data['interval'] );
		$this->assertEmpty( $data['system'] );
		$this->assertEmpty( $data['wordpress'] );
		$this->assertEmpty( $data['database'] );
		$this->assertEmpty( $data['cache'] );
		$this->assertFalse( $data['has_data'] );
	}

	/**
	 * Assert DashboardPage::get_dashboard_data() returns enabled state when service is available.
	 */
	public function test_dashboard_data_with_service_only(): void {
		$storage   = new Storage( '0.1.0' );
		$service   = new Service( $storage, true, 3600, 0.1 );

		$dashboard = DashboardPage::get_instance();
		$dashboard->set_service( $service );
		$dashboard->set_storage( $storage );

		$method    = new \ReflectionMethod( $dashboard, 'get_dashboard_data' );
		$method->setAccessible( true );

		$data = $method->invoke( $dashboard );

		// Service is available so enabled should be true
		$this->assertTrue( $data['enabled'] );
		$this->assertSame( 3600, $data['interval'] );
	}

	/**
	 * Assert DashboardPage renders without service/storage (empty state, no throw).
	 */
	public function test_dashboard_render_without_service_storage(): void {
		$dashboard = DashboardPage::get_instance();
		$threw     = false;
		try {
			$dashboard->render();
		} catch ( \Throwable $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'DashboardPage::render() should not throw without service/storage.' );
	}

	/**
	 * Assert Service::is_enabled() returns true when enabled.
	 */
	public function test_service_is_enabled(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true );
		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Assert Service::is_enabled() returns false when disabled.
	 */
	public function test_service_is_disabled(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, false );
		$this->assertFalse( $service->is_enabled() );
	}

	/**
	 * Assert Service defaults to enabled when not specified.
	 */
	public function test_service_defaults_to_enabled(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage );
		$this->assertTrue( $service->is_enabled() );
	}

	/**
	 * Assert Service::get_interval() returns configured interval.
	 */
	public function test_service_get_interval(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true, 7200 );
		$this->assertSame( 7200, $service->get_interval() );
	}

	/**
	 * Assert Service defaults interval to DEFAULT_INTERVAL (3600).
	 */
	public function test_service_default_interval(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true );
		$this->assertSame( 3600, $service->get_interval() );
	}

	/**
	 * Assert Service::get_sample_rate() returns configured sample rate.
	 */
	public function test_service_get_sample_rate(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true, 3600, 0.5 );
		$this->assertSame( 0.5, $service->get_sample_rate() );
	}

	/**
	 * Assert Service defaults sample rate to 0.1.
	 */
	public function test_service_default_sample_rate(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true );
		$this->assertSame( 0.1, $service->get_sample_rate() );
	}

	/**
	 * Assert DashboardPage::set_service() and set_storage() work correctly.
	 */
	public function test_dashboard_page_set_service_storage(): void {
		$storage = new Storage( '0.1.0' );
		$service = new Service( $storage, true );

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

	/**
	 * Assert DashboardPage is a singleton.
	 */
	public function test_dashboard_page_is_singleton(): void {
		$instance1 = DashboardPage::get_instance();
		$instance2 = DashboardPage::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Assert Plugin::get_instance() is a singleton.
	 */
	public function test_plugin_is_singleton(): void {
		$instance1 = Plugin::get_instance();
		$instance2 = Plugin::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}
}
