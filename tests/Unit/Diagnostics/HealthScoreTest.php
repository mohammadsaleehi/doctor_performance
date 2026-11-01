<?php
/**
 * HealthScoreCalculator unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Diagnostics;

use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Rules\Severity;
use PHPUnit\Framework\TestCase;

/**
 * HealthScoreTest class.
 */
final class HealthScoreTest extends TestCase {

	/**
	 * Healthy site with no findings scores 100.
	 */
	public function test_healthy_site_score_100(): void {
		$calc = new HealthScoreCalculator();
		$result = $calc->calculate( array(), 10 );
		$this->assertSame( 100, $result['score'] );
		$this->assertTrue( $result['sufficient'] );
	}

	/**
	 * Critical finding deducts 25 points.
	 */
	public function test_critical_finding_deduction(): void {
		$calc = new HealthScoreCalculator();
		$findings = array(
			array( 'severity' => Severity::CRITICAL, 'confidence' => 'high' ),
		);
		$result = $calc->calculate( $findings, 10 );
		$this->assertSame( 75, $result['score'] ); // 100 - 25
		$this->assertArrayHasKey( Severity::CRITICAL, $result['breakdown'] );
		$this->assertSame( 1, $result['breakdown'][ Severity::CRITICAL ]['count'] );
	}

	/**
	 * Multiple findings deduct cumulatively (capped at 100).
	 */
	public function test_multiple_findings_deduction(): void {
		$calc = new HealthScoreCalculator();
		$findings = array(
			array( 'severity' => Severity::CRITICAL, 'confidence' => 'high' ),
			array( 'severity' => Severity::HIGH, 'confidence' => 'high' ),
			array( 'severity' => Severity::HIGH, 'confidence' => 'high' ),
			array( 'severity' => Severity::MEDIUM, 'confidence' => 'high' ),
			array( 'severity' => Severity::LOW, 'confidence' => 'high' ),
		);
		$result = $calc->calculate( $findings, 10 );
		$this->assertLessThan( 100, $result['score'] );
		$this->assertGreaterThanOrEqual( 0, $result['score'] );
	}

	/**
	 * Info findings do not deduct points.
	 */
	public function test_info_finding_no_deduction(): void {
		$calc = new HealthScoreCalculator();
		$findings = array(
			array( 'severity' => Severity::INFO, 'confidence' => 'high' ),
		);
		$result = $calc->calculate( $findings, 10 );
		$this->assertSame( 100, $result['score'] );
	}

	/**
	 * Low confidence reduces deduction.
	 */
	public function test_low_confidence_reduces_deduction(): void {
		$calc = new HealthScoreCalculator();
		$high = $calc->calculate( array( array( 'severity' => Severity::HIGH, 'confidence' => 'high' ) ), 10 );
		$low  = $calc->calculate( array( array( 'severity' => Severity::HIGH, 'confidence' => 'low' ) ), 10 );
		$this->assertGreaterThan( $high['score'], $low['score'] );
	}

	/**
	 * Insufficient coverage returns null score.
	 */
	public function test_insufficient_coverage(): void {
		$calc = new HealthScoreCalculator();
		$result = $calc->calculate( array(), 3 );
		$this->assertNull( $result['score'] );
		$this->assertFalse( $result['sufficient'] );
	}

	/**
	 * Score label for null is "Insufficient data".
	 */
	public function test_score_label_null(): void {
		$this->assertSame( 'Insufficient data', HealthScoreCalculator::score_label( null ) );
	}

	/**
	 * Score label for excellent score.
	 */
	public function test_score_label_excellent(): void {
		$this->assertSame( 'Excellent', HealthScoreCalculator::score_label( 95 ) );
	}

	/**
	 * Score label for critical score.
	 */
	public function test_score_label_critical(): void {
		$this->assertSame( 'Critical', HealthScoreCalculator::score_label( 20 ) );
	}
}
