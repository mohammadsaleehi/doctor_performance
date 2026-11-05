<?php
/**
 * Historical comparison tests for browser measurements.
 *
 * Verifies that only compatible metrics (same identifier, unit, measurement
 * context, and page URL for frontend metrics) are compared, and that missing
 * data is distinguished from zero.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Performance;

use WPPerformanceDoctor\Performance\HistoricalComparison;
use PHPUnit\Framework\TestCase;

/**
 * HistoricalBrowserComparisonTest class.
 *
 * @covers \WPPerformanceDoctor\Performance\HistoricalComparison
 * @coversNothing
 */
final class HistoricalBrowserComparisonTest extends TestCase
{
	/**
	 * Build an available frontend metric row.
	 *
	 * @param float|int $value Measured value.
	 * @param string    $unit Unit.
	 * @param string    $context Measurement context.
	 * @param string    $url Page URL.
	 * @param string    $timestamp Collection timestamp.
	 * @return array
	 */
	private static function metric( $value, string $unit, string $context, string $url, string $timestamp ): array {
		return array(
			'metric_value'        => $value,
			'unit'                => $unit,
			'availability_status' => 'available',
			'measurement_context' => $context,
			'page_url'            => $url,
			'collected_at'        => $timestamp,
		);
	}

	/**
	 * Assert matching frontend TTFB metrics are compared.
	 */
	public function test_matching_frontend_ttfb_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.navigation_timing_ttfb' => self::metric( 400, 'ms', 'frontend', 'https://example.com/', '2026-10-31 12:00:00' ),
		);
		$current = array(
			'frontend.navigation_timing_ttfb' => self::metric( 500, 'ms', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'frontend.navigation_timing_ttfb', $result );
		$this->assertTrue( $result['frontend.navigation_timing_ttfb']['available'] );
		$this->assertEqualsWithDelta( 400, $result['frontend.navigation_timing_ttfb']['previous'], 0.001 );
		$this->assertEqualsWithDelta( 500, $result['frontend.navigation_timing_ttfb']['current'], 0.001 );
		$this->assertEqualsWithDelta( 100, $result['frontend.navigation_timing_ttfb']['difference'], 0.001 );
		$this->assertEqualsWithDelta( 25.0, $result['frontend.navigation_timing_ttfb']['percent_change'], 0.001 );
		$this->assertSame( 'frontend', $result['frontend.navigation_timing_ttfb']['context'] );
		$this->assertSame( 'https://example.com/', $result['frontend.navigation_timing_ttfb']['page_url'] );
		$this->assertSame( '2026-11-01 12:00:00', $result['frontend.navigation_timing_ttfb']['current_timestamp'] );
	}

	/**
	 * Assert admin vs frontend context metrics are not compared.
	 */
	public function test_context_mismatch_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.navigation_timing_ttfb' => self::metric( 400, 'ms', 'admin', 'https://example.com/wp-admin/', '2026-10-31 12:00:00' ),
		);
		$current = array(
			'frontend.navigation_timing_ttfb' => self::metric( 500, 'ms', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'frontend.navigation_timing_ttfb', $result );
		$this->assertFalse( $result['frontend.navigation_timing_ttfb']['available'] );
		$this->assertNotEmpty( $result['frontend.navigation_timing_ttfb']['reason'] );
		$this->assertStringContainsString( 'Incompatible measurement context', $result['frontend.navigation_timing_ttfb']['reason'] );
	}

	/**
	 * Assert frontend metrics on different page URLs are not compared.
	 */
	public function test_frontend_url_mismatch_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.navigation_timing_ttfb' => self::metric( 400, 'ms', 'frontend', 'https://example.com/', '2026-10-31 12:00:00' ),
		);
		$current = array(
			'frontend.navigation_timing_ttfb' => self::metric( 500, 'ms', 'frontend', 'https://example.com/blog/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertArrayHasKey( 'frontend.navigation_timing_ttfb', $result );
		$this->assertFalse( $result['frontend.navigation_timing_ttfb']['available'] );
		$this->assertStringContainsString( 'Incompatible page URL', $result['frontend.navigation_timing_ttfb']['reason'] );
	}

	/**
	 * Assert incompatible units are not compared.
	 */
	public function test_incompatible_units_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.cumulative_layout_shift' => self::metric( 0.1, 'score', 'frontend', 'https://example.com/', '2026-10-31 12:00:00' ),
		);
		$current = array(
			'frontend.cumulative_layout_shift' => self::metric( 0.2, 'ms', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertFalse( $result['frontend.cumulative_layout_shift']['available'] );
		$this->assertStringContainsString( 'Incompatible units', $result['frontend.cumulative_layout_shift']['reason'] );
	}

	/**
	 * Assert missing data (metric absent in previous) is not compared.
	 */
	public function test_missing_metric_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array();
		$current = array(
			'frontend.largest_contentful_paint' => self::metric( 2000, 'ms', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertSame( array(), $result );
	}

	/**
	 * Assert zero is distinguished from missing (zero value compares normally).
	 */
	public function test_zero_value_compared_normally(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.cumulative_layout_shift' => self::metric( 0, 'score', 'frontend', 'https://example.com/', '2026-10-31 12:00:00' ),
		);
		$current = array(
			'frontend.cumulative_layout_shift' => self::metric( 0.15, 'score', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertTrue( $result['frontend.cumulative_layout_shift']['available'] );
		$this->assertEqualsWithDelta( 0, $result['frontend.cumulative_layout_shift']['previous'], 0.001 );
		$this->assertEqualsWithDelta( 0.15, $result['frontend.cumulative_layout_shift']['current'], 0.001 );
	}

	/**
	 * Assert unavailable metrics are not compared.
	 */
	public function test_unavailable_metric_not_compared(): void
	{
		$comparison = new HistoricalComparison();
		$previous   = array(
			'frontend.navigation_timing_ttfb' => array(
				'metric_value'        => null,
				'unit'                => 'ms',
				'availability_status' => 'unavailable',
				'measurement_context' => 'frontend',
				'page_url'            => 'https://example.com/',
				'collected_at'        => '2026-10-31 12:00:00',
			),
		);
		$current = array(
			'frontend.navigation_timing_ttfb' => self::metric( 500, 'ms', 'frontend', 'https://example.com/', '2026-11-01 12:00:00' ),
		);
		$result = $comparison->compare( $previous, $current );

		$this->assertFalse( $result['frontend.navigation_timing_ttfb']['available'] );
		$this->assertNotEmpty( $result['frontend.navigation_timing_ttfb']['reason'] );
	}
}
