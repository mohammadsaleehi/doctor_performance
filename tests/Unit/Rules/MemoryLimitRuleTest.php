<?php
/**
 * MemoryLimitRule unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Rules;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Rules\PHP\MemoryLimitRule;
use PHPUnit\Framework\TestCase;

/**
 * MemoryLimitRuleTest class.
 */
final class MemoryLimitRuleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
	}

	private function makeContext( array $metrics ): DiagnosticContext {
		return new DiagnosticContext( $metrics );
	}

	/**
	 * Rule passes when memory limit is at or above recommended minimum.
	 */
	public function test_memory_limit_adequate(): void {
		$rule = new MemoryLimitRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.memory_limit',
				'metric_value'        => 268435456, // 256 MB
				'unit'                => 'bytes',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.memory_limit_raw',
				'metric_value'        => '256M',
				'unit'                => 'string',
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
	 * Rule fails when memory limit is below recommended minimum.
	 */
	public function test_memory_limit_too_low(): void {
		$rule = new MemoryLimitRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.memory_limit',
				'metric_value'        => 67108864, // 64 MB
				'unit'                => 'bytes',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.memory_limit_raw',
				'metric_value'        => '64M',
				'unit'                => 'string',
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
		$this->assertNotNull( $result->fingerprint );
	}

	/**
	 * Rule passes when memory limit is unlimited (-1 stored as null).
	 */
	public function test_memory_limit_unlimited(): void {
		$rule = new MemoryLimitRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'system.memory_limit',
				'metric_value'        => null,
				'unit'                => 'bytes',
				'availability_status' => 'available',
				'category'            => 'system',
				'source'              => 'system',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'system.memory_limit_raw',
				'metric_value'        => '-1',
				'unit'                => 'string',
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
	 * Rule returns unavailable when metric is missing.
	 */
	public function test_memory_limit_missing_metric(): void {
		$rule = new MemoryLimitRule();
		$context = new DiagnosticContext( array() );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'unavailable', $result->outcome );
		$this->assertTrue( $result->skipped );
	}

	/**
	 * Rule has correct metadata.
	 */
	public function test_rule_metadata(): void {
		$rule = new MemoryLimitRule();
		$this->assertSame( 'system.php_memory_limit', $rule->get_id() );
		$this->assertSame( 'system', $rule->get_category() );
		$this->assertSame( 'high', $rule->get_severity() );
		$this->assertTrue( $rule->is_enabled() );
		$this->assertContains( 'system.memory_limit', $rule->get_required_metrics() );
	}
}
