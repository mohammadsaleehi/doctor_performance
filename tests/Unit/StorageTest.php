<?php
/**
 * Storage unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Monitoring\Storage;
use PHPUnit\Framework\TestCase;

/**
 * StorageTest class.
 *
 * @covers \WPPerformanceDoctor\Monitoring\Storage
 * @coversNothing
 */
final class StorageTest extends TestCase
{

	/**
	 * Assert Storage can be instantiated.
	 */
	public function test_storage_instantiation(): void
	{
		$storage = new Storage( '0.2.0' );
		$this->assertInstanceOf( Storage::class, $storage );
	}

	/**
	 * Assert Schema constants are correct.
	 */
	public function test_schema_constants(): void
	{
		$this->assertSame( 'wp_wpd_monitoring_snapshots', \WPPerformanceDoctor\Monitoring\Schema::TABLE_SNAPSHOTS );
		$this->assertSame( 'wp_wpd_monitoring_metrics', \WPPerformanceDoctor\Monitoring\Schema::TABLE_METRICS );
		$this->assertSame( 4, \WPPerformanceDoctor\Monitoring\Schema::SCHEMA_VERSION );
	}

	/**
	 * Assert Schema get_tables() returns all tables including diagnostic and profiling tables.
	 */
	public function test_schema_get_tables(): void
	{
		$schema = new \WPPerformanceDoctor\Monitoring\Schema();
		$tables = $schema->get_tables();
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_SNAPSHOTS, $tables );
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_METRICS, $tables );
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_DIAGNOSTIC_RUNS, $tables );
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_DIAGNOSTIC_FINDINGS, $tables );
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_FINDING_HISTORY, $tables );
		$this->assertContains( \WPPerformanceDoctor\Monitoring\Schema::TABLE_PROFILING_SESSIONS, $tables );
		$this->assertCount( 6, $tables );
	}

	/**
	 * Assert Metric objects created by collectors have expected structure.
	 */
	public function test_metric_structure(): void
	{
		$metric = new Metric(
			'test.metric',
			42,
			'count',
			'test',
			'available',
			'test_source',
			'2026-10-31 12:00:00',
			array( 'extra' => 'data' )
		);

		$array = $metric->to_array();
		$this->assertArrayHasKey( 'metric', $array );
		$this->assertArrayHasKey( 'value', $array );
		$this->assertArrayHasKey( 'unit', $array );
		$this->assertArrayHasKey( 'category', $array );
		$this->assertArrayHasKey( 'status', $array );
		$this->assertArrayHasKey( 'source', $array );
		$this->assertArrayHasKey( 'collected_at', $array );
		$this->assertArrayHasKey( 'metadata', $array );
	}

	/**
	 * Assert all status constants are defined.
	 */
	public function test_status_constants(): void
	{
		$this->assertSame( 'available', Metric::STATUS_AVAILABLE );
		$this->assertSame( 'unavailable', Metric::STATUS_UNAVAILABLE );
		$this->assertSame( 'unsupported', Metric::STATUS_UNSUPPORTED );
		$this->assertSame( 'error', Metric::STATUS_ERROR );
	}

	/**
	 * Assert all category constants are defined.
	 */
	public function test_category_constants(): void
	{
		$this->assertSame( 'system', Metric::CATEGORY_SYSTEM );
		$this->assertSame( 'wordpress', Metric::CATEGORY_WORDPRESS );
		$this->assertSame( 'database', Metric::CATEGORY_DATABASE );
		$this->assertSame( 'cache', Metric::CATEGORY_CACHE );
		$this->assertSame( 'php', Metric::CATEGORY_PHP );
		$this->assertSame( 'frontend', Metric::CATEGORY_FRONTEND );
	}
}
