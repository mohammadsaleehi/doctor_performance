<?php
/**
 * Metric unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Monitoring\Metric;
use PHPUnit\Framework\TestCase;

/**
 * MetricTest class.
 *
 * @covers \WPPerformanceDoctor\Monitoring\Metric
 * @coversNothing
 */
final class MetricTest extends TestCase
{

	/**
	 * Assert a metric is created with correct properties.
	 */
	public function test_metric_creation(): void
	{
		$metric = new Metric(
			'php.memory_limit',
			268435456,
			'bytes',
			Metric::CATEGORY_SYSTEM,
			Metric::STATUS_AVAILABLE,
			'system',
			'2026-10-31 12:00:00',
			array( 'key' => 'value' )
		);

		$this->assertSame( 'php.memory_limit', $metric->metric );
		$this->assertSame( 268435456, $metric->value );
		$this->assertSame( 'bytes', $metric->unit );
		$this->assertSame( Metric::CATEGORY_SYSTEM, $metric->category );
		$this->assertSame( Metric::STATUS_AVAILABLE, $metric->status );
		$this->assertSame( 'system', $metric->source );
		$this->assertSame( '2026-10-31 12:00:00', $metric->collected_at );
		$this->assertSame( array( 'key' => 'value' ), $metric->metadata );
	}

	/**
	 * Assert to_array() returns correct structure.
	 */
	public function test_to_array(): void
	{
		$metric = new Metric(
			'test.metric',
			'value',
			'unit',
			'test',
			'available',
			'source',
			'2026-10-31 12:00:00'
		);

		$expected = array(
			'metric'       => 'test.metric',
			'value'        => 'value',
			'unit'         => 'unit',
			'category'     => 'test',
			'status'       => 'available',
			'source'       => 'source',
			'collected_at' => '2026-10-31 12:00:00',
			'metadata'     => array(),
		);

		$this->assertSame( $expected, $metric->to_array() );
	}

	/**
	 * Assert is_available() returns true for available status.
	 */
	public function test_is_available_true(): void
	{
		$metric = new Metric( 'test', 'value', 'unit', 'cat', Metric::STATUS_AVAILABLE );
		$this->assertTrue( $metric->is_available() );
	}

	/**
	 * Assert is_available() returns false for non-available statuses.
	 */
	public function test_is_available_false(): void
	{
		$metric = new Metric( 'test', null, '', 'cat', Metric::STATUS_UNAVAILABLE );
		$this->assertFalse( $metric->is_available() );

		$metric = new Metric( 'test', null, '', 'cat', Metric::STATUS_UNSUPPORTED );
		$this->assertFalse( $metric->is_available() );

		$metric = new Metric( 'test', null, '', 'cat', Metric::STATUS_ERROR );
		$this->assertFalse( $metric->is_available() );
	}

	/**
	 * Assert Metric::unavailable() creates correct metric.
	 */
	public function test_unavailable(): void
	{
		$metric = Metric::unavailable( 'test.metric', 'system', 'collector', array( 'key' => 'val' ) );

		$this->assertSame( 'test.metric', $metric->metric );
		$this->assertNull( $metric->value );
		$this->assertSame( '', $metric->unit );
		$this->assertSame( 'system', $metric->category );
		$this->assertSame( Metric::STATUS_UNAVAILABLE, $metric->status );
		$this->assertSame( 'collector', $metric->source );
		$this->assertSame( array( 'key' => 'val' ), $metric->metadata );
	}

	/**
	 * Assert Metric::unsupported() creates correct metric.
	 */
	public function test_unsupported(): void
	{
		$metric = Metric::unsupported( 'test.metric', 'database', 'collector' );

		$this->assertSame( Metric::STATUS_UNSUPPORTED, $metric->status );
		$this->assertNull( $metric->value );
	}

	/**
	 * Assert Metric::error() creates correct metric.
	 */
	public function test_error(): void
	{
		$metric = Metric::error( 'test.metric', 'system', 'collector', 'Something went wrong' );

		$this->assertSame( Metric::STATUS_ERROR, $metric->status );
		$this->assertNull( $metric->value );
		$this->assertSame( 'Something went wrong', $metric->metadata['error'] );
	}
}
