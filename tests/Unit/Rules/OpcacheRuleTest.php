<?php
/**
 * OpcacheRule unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Rules;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Rules\PHP\OpcacheRule;
use PHPUnit\Framework\TestCase;

/**
 * OpcacheRuleTest class.
 */
final class OpcacheRuleTest extends TestCase {

	private function makeContext( array $metrics ): DiagnosticContext {
		return new DiagnosticContext( $metrics );
	}

	/**
	 * Rule passes when OPcache is enabled.
	 */
	public function test_opcache_enabled(): void {
		$rule = new OpcacheRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.opcache_available',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.opcache_enabled',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
		);
		$context = $this->makeContext( $metrics );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'pass', $result->outcome );
	}

	/**
	 * Rule fails when OPcache is available but disabled.
	 */
	public function test_opcache_disabled(): void {
		$rule = new OpcacheRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.opcache_available',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.opcache_enabled',
				'metric_value'        => false,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
		);
		$context = $this->makeContext( $metrics );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'fail', $result->outcome );
		$this->assertSame( 'high', $result->severity );
	}

	/**
	 * Rule returns info when OPcache extension is not available.
	 */
	public function test_opcache_not_available(): void {
		$rule = new OpcacheRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.opcache_available',
				'metric_value'        => false,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.opcache_enabled',
				'metric_value'        => false,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
		);
		$context = $this->makeContext( $metrics );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'info', $result->outcome );
	}

	/**
	 * Rule returns unavailable when metrics missing.
	 */
	public function test_opcache_missing_metrics(): void {
		$rule = new OpcacheRule();
		$context = new DiagnosticContext( array() );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'unavailable', $result->outcome );
		$this->assertTrue( $result->skipped );
	}
}
