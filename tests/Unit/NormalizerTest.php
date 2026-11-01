<?php
/**
 * Normalizer unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Monitoring\Normalizer;
use PHPUnit\Framework\TestCase;

/**
 * NormalizerTest class.
 *
 * @covers \WPPerformanceDoctor\Monitoring\Normalizer
 * @coversNothing
 */
final class NormalizerTest extends TestCase
{

	/**
	 * Assert normalize() converts raw data to Metric objects.
	 */
	public function test_normalize(): void
	{
		$normalizer = new Normalizer( 'system', '2026-10-31 12:00:00' );

		$raw = array(
			'php_version' => '8.1.0',
			'memory_limit' => 268435456,
		);

		$schema = array(
			'php_version'   => array( 'unit' => 'string' ),
			'memory_limit'  => array( 'unit' => 'bytes' ),
		);

		$metrics = $normalizer->normalize( 'system', $raw, $schema );

		$this->assertCount( 2, $metrics );
		$this->assertInstanceOf( Metric::class, $metrics[0] );
		$this->assertSame( 'system.php_version', $metrics[0]->metric );
		$this->assertSame( '8.1.0', $metrics[0]->value );
		$this->assertSame( 'string', $metrics[0]->unit );
		$this->assertSame( 'system', $metrics[0]->category );
		$this->assertSame( Metric::STATUS_AVAILABLE, $metrics[0]->status );
		$this->assertSame( 'system', $metrics[0]->source );
		$this->assertSame( '2026-10-31 12:00:00', $metrics[0]->collected_at );

		$this->assertSame( 'system.memory_limit', $metrics[1]->metric );
		$this->assertSame( 268435456, $metrics[1]->value );
		$this->assertSame( 'bytes', $metrics[1]->unit );
	}

	/**
	 * Assert null values become unavailable metrics.
	 */
	public function test_normalize_null_value(): void
	{
		$normalizer = new Normalizer( 'system' );

		$raw = array( 'missing_key' => null );
		$schema = array( 'missing_key' => array( 'unit' => 'count' ) );

		$metrics = $normalizer->normalize( 'system', $raw, $schema );

		$this->assertCount( 1, $metrics );
		$this->assertSame( Metric::STATUS_UNAVAILABLE, $metrics[0]->status );
		$this->assertNull( $metrics[0]->value );
	}

	/**
	 * Assert missing_metrics() creates unavailable metrics for missing keys.
	 */
	public function test_missing_metrics(): void
	{
		$normalizer = new Normalizer( 'system' );

		$raw = array( 'existing_key' => 'value' );
		$expected = array( 'existing_key', 'missing_key' );

		$metrics = $normalizer->missing_metrics( 'system', $expected, $raw, 'count' );

		$this->assertCount( 1, $metrics );
		$this->assertSame( 'system.missing_key', $metrics[0]->metric );
		$this->assertSame( Metric::STATUS_UNAVAILABLE, $metrics[0]->status );
		$this->assertSame( 'count', $metrics[0]->metadata['unit'] );
	}

	/**
	 * Assert get_source() returns the source name.
	 */
	public function test_get_source(): void
	{
		$normalizer = new Normalizer( 'test_source' );
		$this->assertSame( 'test_source', $normalizer->get_source() );
	}
}
