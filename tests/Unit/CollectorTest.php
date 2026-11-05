<?php
/**
 * Collector unit tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Collectors\CacheCollector;
use WPPerformanceDoctor\Collectors\DatabaseCollector;
use WPPerformanceDoctor\Collectors\FrontendCollector;
use WPPerformanceDoctor\Collectors\PerformanceCollector;
use WPPerformanceDoctor\Collectors\PhpProfilerCollector;
use WPPerformanceDoctor\Collectors\SystemCollector;
use WPPerformanceDoctor\Collectors\WordPressCollector;
use PHPUnit\Framework\TestCase;

/**
 * CollectorTest class.
 *
 * @coversNothing
 */
final class CollectorTest extends TestCase
{

	/**
	 * Reset static state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();
		PhpProfilerCollector::reset_state();
	}

	/**
	 * Assert SystemCollector class exists and has correct ID.
	 */
	public function test_system_collector_id(): void
	{
		$collector = new SystemCollector();
		$this->assertSame( 'system', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert WordPressCollector class exists and has correct ID (lowercase).
	 */
	public function test_wordpress_collector_id(): void
	{
		$collector = new WordPressCollector();
		$this->assertSame( 'wordpress', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert DatabaseCollector class exists and has correct ID.
	 */
	public function test_database_collector_id(): void
	{
		$collector = new DatabaseCollector();
		$this->assertSame( 'database', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert CacheCollector class exists and has correct ID.
	 */
	public function test_cache_collector_id(): void
	{
		$collector = new CacheCollector();
		$this->assertSame( 'cache', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert PhpProfilerCollector class exists and has correct ID.
	 */
	public function test_profiler_collector_id(): void
	{
		$collector = new PhpProfilerCollector();
		$this->assertSame( 'php_profiler', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert FrontendCollector class exists and has correct ID.
	 */
	public function test_frontend_collector_id(): void
	{
		$collector = new FrontendCollector();
		$this->assertSame( 'frontend', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert PerformanceCollector class exists and has correct ID.
	 */
	public function test_performance_collector_id(): void
	{
		$collector = new PerformanceCollector();
		$this->assertSame( 'performance', $collector->get_id() );
		$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
	}

	/**
	 * Assert all collectors implement CollectorInterface.
	 */
	public function test_all_collectors_implement_interface(): void
	{
		$collectors = array(
			new SystemCollector(),
			new WordPressCollector(),
			new DatabaseCollector(),
			new CacheCollector(),
			new PhpProfilerCollector(),
			new FrontendCollector(),
			new PerformanceCollector(),
		);

		foreach ( $collectors as $collector ) {
			$this->assertInstanceOf( \WPPerformanceDoctor\Contracts\CollectorInterface::class, $collector );
			$this->assertIsString( $collector->get_id() );
			$this->assertNotEmpty( $collector->get_id() );
		}
	}

	/**
	 * Regression test: PhpProfilerCollector capture_start sets start_time and start_memory.
	 */
	public function test_profiler_capture_start_sets_metrics(): void
	{
		$collector = new PhpProfilerCollector();
		$collector->capture_start();

		$reflection = new \ReflectionClass( $collector );
		$start_time = $reflection->getProperty( 'start_time' );
		$start_time->setAccessible( true );
		$start_memory = $reflection->getProperty( 'start_memory' );
		$start_memory->setAccessible( true );

		$this->assertNotNull( $start_time->getValue() );
		$this->assertNotNull( $start_memory->getValue() );
		$this->assertIsFloat( $start_time->getValue() );
		$this->assertIsInt( $start_memory->getValue() );
	}

	/**
	 * Regression test: PhpProfilerCollector reset_state clears static state.
	 */
	public function test_profiler_reset_state(): void
	{
		$collector = new PhpProfilerCollector();
		$collector->capture_start();

		PhpProfilerCollector::reset_state();

		$reflection = new \ReflectionClass( $collector );
		$start_time = $reflection->getProperty( 'start_time' );
		$start_time->setAccessible( true );

		$this->assertNull( $start_time->getValue() );
	}

	/**
	 * Regression test: CacheCollector does not check for predis extension (it's a library, not an extension).
	 */
	public function test_cache_collector_no_predis_check(): void
	{
		$collector = new CacheCollector();
		$reflection = new \ReflectionClass( $collector );
		$method = $reflection->getMethod( 'check_redis' );
		$method->setAccessible( true );

		// Should only check for 'redis' extension, not 'predis'.
		$this->assertStringContainsString( 'extension_loaded( \'redis\' )', $this->get_method_body( $method ) );
		$this->assertStringNotContainsString( 'predis', $this->get_method_body( $method ) );
	}

	/**
	 * Get the body of a reflection method as a string.
	 *
	 * @param \ReflectionMethod $method
	 * @return string
	 */
	private function get_method_body( \ReflectionMethod $method ): string
	{
		$file   = $method->getFileName();
		$start  = $method->getStartLine();
		$end    = $method->getEndLine();
		$lines  = file( $file );
		$body   = '';

		for ( $i = $start - 1; $i < $end; $i++ ) {
			$body .= $lines[ $i ];
		}

		return $body;
	}
}
