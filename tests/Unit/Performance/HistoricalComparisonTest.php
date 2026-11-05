<?php
/**
 * HistoricalComparison unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Performance;

use WPPerformanceDoctor\Performance\HistoricalComparison;
use PHPUnit\Framework\TestCase;

/**
 * HistoricalComparisonTest class.
 *
 * @covers \WPPerformanceDoctor\Performance\HistoricalComparison
 * @coversNothing
 */
final class HistoricalComparisonTest extends TestCase
{

	/**
	 * Assert compare returns empty array when no common metrics.
	 */
	public function test_no_common_metrics_returns_empty(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.memory_limit' => array(
				'metric_value'        => 268435456,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'system.memory_usage' => array(
				'metric_value'        => 1000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );
		$this->assertSame( array(), $result );
	}

	/**
	 * Assert comparison shows difference for same metric with same unit.
	 */
	public function test_same_metric_same_unit_shows_difference(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.memory_usage' => array(
				'metric_value'        => 50000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
				'collected_at'        => '2026-10-31 12:00:00',
			),
		);
		$current = array(
			'php.memory_usage' => array(
				'metric_value'        => 60000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
				'collected_at'        => '2026-11-01 12:00:00',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'php.memory_usage', $result );
		$comp = $result['php.memory_usage'];
		$this->assertTrue( $comp['available'] );
		$this->assertEqualsWithDelta( 50000000, $comp['previous'], 0.001 );
		$this->assertEqualsWithDelta( 60000000, $comp['current'], 0.001 );
		$this->assertEqualsWithDelta( 10000000, $comp['difference'], 0.001 );
		$this->assertEqualsWithDelta( 20.0, $comp['percent_change'], 0.001 );
		$this->assertSame( 'bytes', $comp['unit'] );
		$this->assertFalse( $comp['improved'] ); // higher memory = worse
	}

	/**
	 * Assert unavailable metrics are not compared.
	 */
	public function test_unavailable_metric_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.memory_usage' => array(
				'metric_value'        => 50000000,
				'unit'                => 'bytes',
				'availability_status' => 'unavailable',
			),
		);
		$current = array(
			'php.memory_usage' => array(
				'metric_value'        => 60000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'php.memory_usage', $result );
		$this->assertFalse( $result['php.memory_usage']['available'] );
		$this->assertNotEmpty( $result['php.memory_usage']['reason'] );
	}

	/**
	 * Assert incompatible units are not compared.
	 */
	public function test_incompatible_units_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'system.load_average' => array(
				'metric_value'        => 1.5,
				'unit'                => 'load',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'system.load_average' => array(
				'metric_value'        => 2.0,
				'unit'                => 'seconds',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'system.load_average', $result );
		$this->assertFalse( $result['system.load_average']['available'] );
		$this->assertStringContainsString( 'Incompatible units', $result['system.load_average']['reason'] );
	}

	/**
	 * Assert non-numeric values are not compared.
	 */
	public function test_non_numeric_values_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.sapi' => array(
				'metric_value'        => 'cli',
				'unit'                => 'string',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'php.sapi' => array(
				'metric_value'        => 'apache2handler',
				'unit'                => 'string',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'php.sapi', $result );
		$this->assertFalse( $result['php.sapi']['available'] );
	}

	/**
	 * Assert lower memory usage is marked as improved.
	 */
	public function test_lower_memory_is_improved(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.memory_usage' => array(
				'metric_value'        => 60000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'php.memory_usage' => array(
				'metric_value'        => 50000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertTrue( $result['php.memory_usage']['improved'] );
		$this->assertEqualsWithDelta( -10000000, $result['php.memory_usage']['difference'], 0.001 );
	}

	/**
	 * Assert higher OPcache hit rate is marked as improved.
	 */
	public function test_higher_opcache_hit_rate_is_improved(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.opcache_hit_rate' => array(
				'metric_value'        => 0.85,
				'unit'                => 'float',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'php.opcache_hit_rate' => array(
				'metric_value'        => 0.92,
				'unit'                => 'float',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertTrue( $result['php.opcache_hit_rate']['improved'] );
		$this->assertEqualsWithDelta( 0.07, $result['php.opcache_hit_rate']['difference'], 0.001 );
	}

	/**
	 * Assert worse memory usage is marked as worsened.
	 */
	public function test_higher_memory_is_worsened(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.memory_usage' => array(
				'metric_value'        => 50000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'php.memory_usage' => array(
				'metric_value'        => 60000000,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertFalse( $result['php.memory_usage']['improved'] );
	}

	/**
	 * Assert metrics with unknown improvement direction return null for improved.
	 */
	public function test_unknown_direction_improved_is_null(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'php.opcache_memory_consumption' => array(
				'metric_value'        => 32,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$current = array(
			'php.opcache_memory_consumption' => array(
				'metric_value'        => 64,
				'unit'                => 'bytes',
				'availability_status' => 'available',
			),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'php.opcache_memory_consumption', $result );
		$this->assertTrue( $result['php.opcache_memory_consumption']['available'] );
		$this->assertNull( $result['php.opcache_memory_consumption']['improved'] );
	}
}
