<?php
/**
 * Browser metrics analyzer tests for WP Performance Doctor.
 *
 * Verifies that Core Web Vitals and navigation timing findings are
 * evidence-based, use Web Vitals thresholds, and never produce findings
 * for unavailable or "good" metrics.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Performance;

use WPPerformanceDoctor\Performance\PerformanceAnalyzer;
use WPPerformanceDoctor\Rules\Severity;
use PHPUnit\Framework\TestCase;

/**
 * BrowserMetricsAnalyzerTest class.
 *
 * @covers \WPPerformanceDoctor\Performance\PerformanceAnalyzer
 * @coversNothing
 */
final class BrowserMetricsAnalyzerTest extends TestCase
{
	/**
	 * Build an available metric row for a browser metric.
	 *
	 * @param float|int $value Measured value.
	 * @param string    $unit Unit.
	 * @param string    $context Measurement context.
	 * @param string    $url Page URL.
	 * @return array
	 */
	private static function available_metric( $value, string $unit = 'ms', string $context = 'frontend', string $url = 'https://example.com/' ): array {
		return array(
			'metric_value'        => $value,
			'unit'                => $unit,
			'availability_status' => 'available',
			'measurement_context' => $context,
			'page_url'            => $url,
			'collected_at'        => '2026-11-01 12:00:00',
		);
	}

	/**
	 * Build an unavailable metric row.
	 *
	 * @return array
	 */
	private static function unavailable_metric(): array {
		return array(
			'metric_value'        => null,
			'unit'                => 'ms',
			'availability_status' => 'unavailable',
			'measurement_context' => 'frontend',
			'page_url'            => '',
			'collected_at'        => '2026-11-01 12:00:00',
		);
	}

	/**
	 * Assert LCP in the "needs improvement" range produces a LOW finding.
	 */
	public function test_lcp_needs_improvement_low_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.largest_contentful_paint' => self::available_metric( 3000 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_lcp' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::LOW, $finding['severity'] );
		$this->assertSame( 'medium', $finding['confidence'] );
		$this->assertEquals( 3000, $finding['evidence']['frontend.largest_contentful_paint']['value'] );
		$this->assertSame( 'ms', $finding['evidence']['frontend.largest_contentful_paint']['unit'] );
		$this->assertSame( 'needs_improvement', $finding['evidence']['frontend.largest_contentful_paint']['rating'] );
		$this->assertSame( 'frontend', $finding['evidence']['frontend.largest_contentful_paint']['measurement_context'] );
	}

	/**
	 * Assert LCP in the "poor" range produces a MEDIUM finding.
	 */
	public function test_lcp_poor_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.largest_contentful_paint' => self::available_metric( 5000 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_lcp' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::MEDIUM, $finding['severity'] );
		$this->assertSame( 'poor', $finding['evidence']['frontend.largest_contentful_paint']['rating'] );
	}

	/**
	 * Assert good LCP produces no finding.
	 */
	public function test_lcp_good_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.largest_contentful_paint' => self::available_metric( 2000 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$this->assertNull( $this->find_by_id( $findings, 'perf.browser_lcp' ) );
	}

	/**
	 * Assert unavailable TTFB produces no finding (no false pass/fail).
	 */
	public function test_unavailable_ttfb_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.navigation_timing_ttfb' => self::unavailable_metric(),
		);
		$findings = $analyzer->analyze( $metrics );

		$this->assertNull( $this->find_by_id( $findings, 'perf.browser_ttfb' ) );
		$this->assertSame( array(), $findings );
	}

	/**
	 * Assert TTFB above the poor threshold produces a MEDIUM finding.
	 */
	public function test_ttfb_poor_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.navigation_timing_ttfb' => self::available_metric( 2000 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_ttfb' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::MEDIUM, $finding['severity'] );
		$this->assertSame( 'medium', $finding['confidence'] );
		$this->assertEquals( 2000, $finding['evidence']['frontend.navigation_timing_ttfb']['value'] );
	}

	/**
	 * Assert CLS above the poor threshold produces a MEDIUM finding.
	 */
	public function test_cls_poor_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.cumulative_layout_shift' => self::available_metric( 0.3, 'score' ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_cls' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::MEDIUM, $finding['severity'] );
		$this->assertSame( 0.3, $finding['evidence']['frontend.cumulative_layout_shift']['value'] );
		$this->assertSame( 'score', $finding['evidence']['frontend.cumulative_layout_shift']['unit'] );
	}

	/**
	 * Assert INP above the poor threshold produces a MEDIUM finding.
	 */
	public function test_inp_poor_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.interaction_to_next_paint' => self::available_metric( 600 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_inp' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::MEDIUM, $finding['severity'] );
		$this->assertEquals( 600, $finding['evidence']['frontend.interaction_to_next_paint']['value'] );
	}

	/**
	 * Assert multiple below-threshold metrics produce multiple findings.
	 */
	public function test_multiple_browser_metrics_produce_multiple_findings(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.largest_contentful_paint' => self::available_metric( 5000 ),
			'frontend.cumulative_layout_shift'  => self::available_metric( 0.3, 'score' ),
			'frontend.navigation_timing_ttfb'   => self::available_metric( 2000 ),
		);
		$findings = $analyzer->analyze( $metrics );

		$ids = array_map( static function ( $f ) { return $f['id']; }, $findings );
		$this->assertContains( 'perf.browser_lcp', $ids );
		$this->assertContains( 'perf.browser_cls', $ids );
		$this->assertContains( 'perf.browser_ttfb', $ids );
		$this->assertCount( 3, $findings );
	}

	/**
	 * Assert slow resource loading duration produces a LOW informational finding.
	 */
	public function test_slow_resource_loading_low_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.resource_loading_duration' => self::available_metric( 6000 ),
			'frontend.resource_count'            => self::available_metric( 40, 'count' ),
		);
		$findings = $analyzer->analyze( $metrics );

		$finding = $this->find_by_id( $findings, 'perf.browser_slow_resources' );
		$this->assertNotNull( $finding );
		$this->assertSame( Severity::LOW, $finding['severity'] );
		$this->assertSame( 'low', $finding['confidence'] );
	}

	/**
	 * Assert acceptable resource loading produces no resource finding.
	 */
	public function test_acceptable_resource_loading_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.resource_loading_duration' => self::available_metric( 800 ),
			'frontend.resource_count'            => self::available_metric( 15, 'count' ),
		);
		$findings = $analyzer->analyze( $metrics );

		$this->assertNull( $this->find_by_id( $findings, 'perf.browser_slow_resources' ) );
	}

	/**
	 * Assert a metric with a non-available status never yields a finding.
	 */
	public function test_non_available_status_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.largest_contentful_paint' => array(
				'metric_value'        => 5000,
				'unit'                => 'ms',
				'availability_status' => 'unsupported',
				'measurement_context' => 'frontend',
				'page_url'            => 'https://example.com/',
				'collected_at'        => '2026-11-01 12:00:00',
			),
		);
		$findings = $analyzer->analyze( $metrics );

		$this->assertSame( array(), $findings );
	}

	/**
	 * Find a finding by id.
	 *
	 * @param array $findings Findings.
	 * @param string $id Finding id.
	 * @return array|null
	 */
	private function find_by_id( array $findings, string $id ): ?array {
		foreach ( $findings as $finding ) {
			if ( $finding['id'] ?? '' === $id ) {
				return $finding;
			}
		}
		return null;
	}
}
