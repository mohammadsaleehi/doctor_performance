<?php
/**
 * Integration tests for WP Performance Doctor monitoring.
 *
 * Tests end-to-end profiler behavior across request types.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Integration;

use WPPerformanceDoctor\Collectors\PhpProfilerCollector;
use WPPerformanceDoctor\Collectors\PerformanceCollector;
use WPPerformanceDoctor\Monitoring\Service;
use PHPUnit\Framework\TestCase;

/**
 * ProfilerIntegrationTest class.
 *
 * @coversNothing
 */
final class ProfilerIntegrationTest extends TestCase {

	/**
	 * Reset static state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		PhpProfilerCollector::reset_state();
	}

	/**
	 * Reset static state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		parent::tearDown();
		PhpProfilerCollector::reset_state();

		if ( defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', false, true );
		}
		if ( defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', false, true );
		}
		if ( defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', false, true );
		}
		unset( $_SERVER['PATH_INFO'] );
	}

	/**
	 * Assert PhpProfilerCollector::register_hooks() can be called without WordPress.
	 */
	public function test_register_hooks_without_wordpress(): void {
		$collector = new PhpProfilerCollector();
		$threw     = false;
		try {
			$collector->register_hooks();
		} catch ( \Throwable $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'register_hooks() should not throw when add_action() is not available.' );
	}

	/**
	 * Assert PhpProfilerCollector::collect() returns excluded data when excluded.
	 */
	public function test_collect_returns_excluded_when_excluded(): void {
		$collector = new PhpProfilerCollector();
		$collector->register_hooks();

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		$data = $collector->collect();

		$this->assertNull( $data['request_start_time'] );
		$this->assertNull( $data['request_duration'] );
		$this->assertFalse( $data['sampled'] );
		$this->assertTrue( $data['is_cron'] );
	}

	/**
	 * Assert PhpProfilerCollector::collect() returns valid data structure when not excluded.
	 */
	public function test_collect_returns_timing_when_not_excluded(): void {
		$collector = new PhpProfilerCollector();
		$collector->register_hooks();
		$collector->capture_start();

		$data = $collector->collect();

		$this->assertArrayHasKey( 'request_start_time', $data );
		$this->assertArrayHasKey( 'request_end_time', $data );
		$this->assertArrayHasKey( 'request_duration', $data );
		$this->assertArrayHasKey( 'memory_usage_start', $data );
		$this->assertArrayHasKey( 'memory_usage_end', $data );
		$this->assertArrayHasKey( 'peak_memory_usage', $data );
		$this->assertArrayHasKey( 'request_type', $data );
		$this->assertArrayHasKey( 'is_admin', $data );
		$this->assertArrayHasKey( 'is_rest', $data );
		$this->assertArrayHasKey( 'is_ajax', $data );
		$this->assertArrayHasKey( 'is_cron', $data );
		$this->assertArrayHasKey( 'sampled', $data );
		$this->assertArrayHasKey( 'sample_rate', $data );
	}

	/**
	 * Assert that get_request_type() returns a valid type string.
	 */
	public function test_get_request_type_returns_valid_type(): void {
		$collector = new PhpProfilerCollector();
		$method    = new \ReflectionMethod( $collector, 'get_request_type' );
		$method->setAccessible( true );

		$result = $method->invoke( $collector );
		$this->assertIsString( $result );
		$this->assertContains( $result, array( 'frontend', 'admin', 'rest', 'ajax', 'cron' ) );
	}

	/**
	 * Assert that collect() excludes cron requests when DOING_CRON is true.
	 */
	public function test_collect_excludes_cron(): void {
		$collector = new PhpProfilerCollector();
		$collector->register_hooks();

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		$data = $collector->collect();

		$this->assertNull( $data['request_start_time'] );
		$this->assertNull( $data['request_duration'] );
		$this->assertFalse( $data['sampled'] );
		$this->assertTrue( $data['is_cron'] );
	}

	/**
	 * Assert that collect() excludes REST monitoring requests.
	 */
	public function test_collect_excludes_rest_monitoring(): void {
		$collector = new PhpProfilerCollector();
		$collector->register_hooks();

		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}
		$_SERVER['PATH_INFO'] = '/wp-json/wp-performance-doctor/v1/monitoring/collect';

		$data = $collector->collect();

		$this->assertNull( $data['request_start_time'] );
		$this->assertNull( $data['request_duration'] );
		$this->assertFalse( $data['sampled'] );
		$this->assertTrue( $data['is_rest'] );
	}

	/**
	 * Assert that PerformanceCollector exists but is not integrated into Service.
	 */
	public function test_performance_collector_not_integrated(): void {
		$this->assertTrue( class_exists( PerformanceCollector::class ) );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, new PerformanceCollector() );
		$this->assertSame( 'performance', ( new PerformanceCollector() )->get_id() );
	}

	/**
	 * Assert there are exactly 6 active collector classes.
	 */
	public function test_active_collector_count(): void {
		$collector_classes = array(
			'WPPerformanceDoctor\Collectors\SystemCollector',
			'WPPerformanceDoctor\Collectors\WordPressCollector',
			'WPPerformanceDoctor\Collectors\DatabaseCollector',
			'WPPerformanceDoctor\Collectors\CacheCollector',
			'WPPerformanceDoctor\Collectors\PhpProfilerCollector',
			'WPPerformanceDoctor\Collectors\FrontendCollector',
		);

		foreach ( $collector_classes as $class ) {
			$this->assertTrue( class_exists( $class ), sprintf( 'Class %s should exist', $class ) );
			$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, new $class() );
		}

		$this->assertCount( 6, $collector_classes );
	}

	/**
	 * Assert there are exactly 7 collector classes total (including PerformanceCollector stub).
	 */
	public function test_total_collector_class_count(): void {
		$all_collector_classes = array(
			'WPPerformanceDoctor\Collectors\SystemCollector',
			'WPPerformanceDoctor\Collectors\WordPressCollector',
			'WPPerformanceDoctor\Collectors\DatabaseCollector',
			'WPPerformanceDoctor\Collectors\CacheCollector',
			'WPPerformanceDoctor\Collectors\PhpProfilerCollector',
			'WPPerformanceDoctor\Collectors\FrontendCollector',
			'WPPerformanceDoctor\Collectors\PerformanceCollector',
		);

		foreach ( $all_collector_classes as $class ) {
			$this->assertTrue( class_exists( $class ), sprintf( 'Class %s should exist', $class ) );
		}

		$this->assertCount( 7, $all_collector_classes );
	}
}
