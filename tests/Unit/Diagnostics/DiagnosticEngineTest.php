<?php
/**
 * DiagnosticEngine unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Diagnostics;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\DiagnosticEngine;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Rules\PHP\MemoryLimitRule;
use WPPerformanceDoctor\Rules\PHP\OpcacheRule;
use PHPUnit\Framework\TestCase;

/**
 * DiagnosticEngineTest class.
 */
final class DiagnosticEngineTest extends TestCase {

	private function makeContext( array $metrics ): DiagnosticContext {
		return new DiagnosticContext( $metrics );
	}

	public function test_engine_runs_rules(): void {
		$registry = new RuleRegistry();
		$registry->register( new MemoryLimitRule() );
		$registry->register( new OpcacheRule() );

		$engine = new DiagnosticEngine( $registry );
		$metrics = array(
			array(
				'metric_identifier'   => 'system.memory_limit',
				'metric_value'        => 67108864,
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
		$result  = $engine->run( $context );

		$this->assertArrayHasKey( 'results', $result );
		$this->assertArrayHasKey( 'findings', $result );
		$this->assertArrayHasKey( 'duration', $result );
		$this->assertArrayHasKey( 'rules_evaluated', $result );
		$this->assertGreaterThan( 0, $result['rules_evaluated'] );
		$this->assertIsFloat( $result['duration'] );
	}

	public function test_rule_exception_is_isolated(): void {
		$registry = new RuleRegistry();
		$registry->register( new MemoryLimitRule() );

		$engine = new DiagnosticEngine( $registry );
		$context = new DiagnosticContext( array() ); // No metrics

		$result = $engine->run( $context );
		// Memory limit rule will return 'unavailable' since metric is missing,
		// not crash the engine.
		$this->assertArrayHasKey( 'system.php_memory_limit', $result['results'] );
		$ruleResult = $result['results']['system.php_memory_limit'];
		$this->assertSame( 'unavailable', $ruleResult->outcome );
		$this->assertTrue( $ruleResult->skipped );
	}

	public function test_disabled_rules_are_not_evaluated(): void {
		$registry = new RuleRegistry();
		$rule     = new MemoryLimitRule();
		$rule->set_enabled( false );
		$registry->register( $rule );

		$engine = new DiagnosticEngine( $registry );
		$metrics = array(
			array(
				'metric_identifier'   => 'system.memory_limit',
				'metric_value'        => 67108864,
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
		$result  = $engine->run( $context );

		// Disabled rules are not evaluated at all, so they won't appear in results.
		$this->assertArrayNotHasKey( 'system.php_memory_limit', $result['results'] );
		$this->assertSame( 0, $result['rules_evaluated'] );
	}
}
