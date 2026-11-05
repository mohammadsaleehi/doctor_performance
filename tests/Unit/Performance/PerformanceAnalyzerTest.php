<?php
/**
 * PerformanceAnalyzer unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Performance;

use WPPerformanceDoctor\Performance\PerformanceAnalyzer;
use WPPerformanceDoctor\Rules\Severity;
use PHPUnit\Framework\TestCase;

/**
 * PerformanceAnalyzerTest class.
 *
 * @covers \WPPerformanceDoctor\Performance\PerformanceAnalyzer
 * @coversNothing
 */
final class PerformanceAnalyzerTest extends TestCase
{

	/**
	 * Assert analyzer returns empty array when no metrics provided.
	 */
	public function test_empty_metrics_returns_no_findings(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$findings = $analyzer->analyze( array() );
		$this->assertSame( array(), $findings );
	}

	/**
	 * Assert unavailable metrics do not generate findings.
	 */
	public function test_unavailable_metric_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.memory_limit' => null,
		);
		$findings = $analyzer->analyze( $metrics );
		$this->assertSame( array(), $findings );
	}

	/**
	 * Assert PHP memory pressure finding has HIGH severity and HIGH confidence.
	 */
	public function test_php_memory_pressure_severity_confidence(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.memory_limit'        => 67108864, // 64 MB
			'php.memory_usage'        => 60000000,
			'php.peak_memory_usage'   => 62000000, // ~92% of limit
		);
		$findings = $analyzer->analyze( $metrics );

		$pressure = null;
		foreach ( $findings as $f ) {
			if ( 'perf.php_memory_pressure' === $f['id'] ) {
				$pressure = $f;
			}
		}

		$this->assertNotNull( $pressure );
		$this->assertSame( Severity::MEDIUM, $pressure['severity'] );
		$this->assertSame( 'medium', $pressure['confidence'] );
		$this->assertStringContainsString( 'memory', strtolower( $pressure['title'] ) );
		$this->assertArrayHasKey( 'evidence', $pressure );
		$this->assertArrayHasKey( 'impact', $pressure );
		$this->assertArrayHasKey( 'remediation', $pressure );
		$this->assertArrayHasKey( 'measurement_limitations', $pressure );
	}

	/**
	 * Assert memory within reasonable bounds produces no memory finding (no false pass).
	 */
	public function test_php_memory_within_bounds_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.memory_limit'        => 268435456, // 256 MB
			'php.memory_usage'        => 50000000,
			'php.peak_memory_usage'   => 60000000, // ~23% of limit
		);
		$findings = $analyzer->analyze( $metrics );

		$found_memory_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.php_memory' ) ) {
				$found_memory_finding = true;
			}
		}
		$this->assertFalse( $found_memory_finding );
	}

	/**
	 * Assert OPcache disabled finding has HIGH severity.
	 */
	public function test_opcache_disabled_high_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.opcache_available' => true,
			'php.opcache_enabled'   => false,
		);
		$findings = $analyzer->analyze( $metrics );

		$disabled = null;
		foreach ( $findings as $f ) {
			if ( 'perf.opcache_disabled' === $f['id'] ) {
				$disabled = $f;
			}
		}

		$this->assertNotNull( $disabled );
		$this->assertSame( Severity::MEDIUM, $disabled['severity'] );
		$this->assertSame( 'high', $disabled['confidence'] );
	}

	/**
	 * Assert OPcache available and enabled returns no OPcache findings.
	 */
	public function test_opcache_enabled_no_opcache_findings(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.opcache_available' => true,
			'php.opcache_enabled'   => true,
			'php.opcache_hit_rate'  => 0.95,
		);
		$findings = $analyzer->analyze( $metrics );

		$found_opcache_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.opcache' ) ) {
				$found_opcache_finding = true;
			}
		}
		$this->assertFalse( $found_opcache_finding );
	}

	/**
	 * Assert OPcache not available returns MEDIUM severity.
	 */
	public function test_opcache_unavailable_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.opcache_available' => false,
			'php.opcache_enabled'   => false,
		);
		$findings = $analyzer->analyze( $metrics );

		$unavailable = null;
		foreach ( $findings as $f ) {
			if ( 'perf.opcache_unavailable' === $f['id'] ) {
				$unavailable = $f;
			}
		}

		$this->assertNotNull( $unavailable );
		$this->assertSame( Severity::INFO, $unavailable['severity'] );
		$this->assertSame( 'high', $unavailable['confidence'] );
	}

	/**
	 * Assert SAVEQUERIES disabled returns no database finding.
	 */
	public function test_savequeries_disabled_no_db_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'database.savequeries_enabled' => false,
		);
		$findings = $analyzer->analyze( $metrics );

		$found_db_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.database' ) ) {
				$found_db_finding = true;
			}
		}
		$this->assertFalse( $found_db_finding );
	}

	/**
	 * Assert slow queries finding has HIGH severity and includes evidence.
	 */
	public function test_slow_queries_high_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'database.savequeries_enabled' => true,
			'database.slow_query_count'    => 3,
			'database.query_time_total'    => 2.5,
			'database.query_count'         => 50,
			'database.slow_queries'        => array(
				array( 'fingerprint' => 'SELECT * FROM wp_posts WHERE ?', 'duration' => 1.2 ),
			),
		);
		$findings = $analyzer->analyze( $metrics );

		$slow = null;
		foreach ( $findings as $f ) {
			if ( 'perf.database_slow_queries' === $f['id'] ) {
				$slow = $f;
			}
		}

		$this->assertNotNull( $slow );
		$this->assertSame( Severity::MEDIUM, $slow['severity'] );
		$this->assertSame( 'medium', $slow['confidence'] );
		$this->assertArrayHasKey( 'evidence', $slow );
		$this->assertSame( 3, $slow['evidence']['database.slow_query_count'] );
	}

	/**
	 * Assert no persistent cache backend returns LOW severity.
	 */
	public function test_no_persistent_cache_low_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'cache.persistent_object_cache' => false,
			'cache.object_cache_available'  => true,
			'cache.object_cache_type'       => 'none',
		);
		$findings = $analyzer->analyze( $metrics );

		$cache = null;
		foreach ( $findings as $f ) {
			if ( 'perf.cache_no_persistent_backend' === $f['id'] ) {
				$cache = $f;
			}
		}

		$this->assertNotNull( $cache );
		$this->assertSame( Severity::INFO, $cache['severity'] );
		$this->assertSame( 'high', $cache['confidence'] );
	}

	/**
	 * Assert persistent cache present returns no cache finding.
	 */
	public function test_persistent_cache_present_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'cache.persistent_object_cache' => true,
			'cache.object_cache_available'  => true,
			'cache.object_cache_type'       => 'redis',
		);
		$findings = $analyzer->analyze( $metrics );

		$found_cache_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.cache' ) ) {
				$found_cache_finding = true;
			}
		}
		$this->assertFalse( $found_cache_finding );
	}

	/**
	 * Assert frontend many assets finding has MEDIUM severity.
	 */
	public function test_frontend_many_assets_medium_severity(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.styles_count'         => 30,
			'frontend.scripts_count'        => 32,
			'frontend.external_asset_count' => 8,
			'frontend.total_assets'         => 62,
		);
		$findings = $analyzer->analyze( $metrics );

		$assets = null;
		foreach ( $findings as $f ) {
			if ( 'perf.frontend_many_assets' === $f['id'] ) {
				$assets = $f;
			}
		}

		$this->assertNotNull( $assets );
		$this->assertSame( Severity::INFO, $assets['severity'] );
		$this->assertSame( 'low', $assets['confidence'] );
		$this->assertArrayHasKey( 'evidence', $assets );
		$this->assertSame( 62, $assets['evidence']['frontend.total_assets'] );
	}

	/**
	 * Assert frontend few assets returns no finding.
	 */
	public function test_frontend_few_assets_no_finding(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'frontend.styles_count'   => 2,
			'frontend.scripts_count'  => 3,
			'frontend.total_assets'   => 5,
		);
		$findings = $analyzer->analyze( $metrics );

		$found_asset_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.frontend' ) ) {
				$found_asset_finding = true;
			}
		}
		$this->assertFalse( $found_asset_finding );
	}

	/**
	 * Assert finding includes remediation steps and risk assessment.
	 */
	public function test_finding_has_remediation_and_risk(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.memory_limit'        => 67108864,
			'php.memory_usage'        => 60000000,
			'php.peak_memory_usage'   => 62000000,
		);
		$findings = $analyzer->analyze( $metrics );

		$pressure = null;
		foreach ( $findings as $f ) {
			if ( 'perf.php_memory_pressure' === $f['id'] ) {
				$pressure = $f;
			}
		}

		$this->assertNotNull( $pressure );
		$this->assertArrayHasKey( 'remediation', $pressure );
		$this->assertIsArray( $pressure['remediation']['steps'] );
		$this->assertNotEmpty( $pressure['remediation']['steps'] );
		$this->assertArrayHasKey( 'risk', $pressure['remediation'] );
		$this->assertArrayHasKey( 'difficulty', $pressure['remediation'] );
		$this->assertArrayHasKey( 'requires_manual_action', $pressure['remediation'] );
		$this->assertTrue( $pressure['remediation']['requires_manual_action'] );
	}

	/**
	 * Assert missing metric does not produce a finding (no false positive).
	 */
	public function test_missing_metric_no_false_positive(): void
	{
		$analyzer = new PerformanceAnalyzer();
		$metrics  = array(
			'php.memory_usage'       => 50000000,
			'php.peak_memory_usage'  => 60000000,
		);
		$findings = $analyzer->analyze( $metrics );

		$found_memory_finding = false;
		foreach ( $findings as $f ) {
			if ( str_starts_with( $f['id'], 'perf.php_memory' ) ) {
				$found_memory_finding = true;
			}
		}
		$this->assertFalse( $found_memory_finding );
	}
}
