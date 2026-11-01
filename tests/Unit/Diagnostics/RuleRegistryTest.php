<?php
/**
 * RuleRegistry unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Diagnostics;

use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Rules\PHP\MemoryLimitRule;
use WPPerformanceDoctor\Rules\PHP\OpcacheRule;
use PHPUnit\Framework\TestCase;

/**
 * RuleRegistryTest class.
 */
final class RuleRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset singleton-like state by creating fresh registry.
	}

	public function test_register_and_get(): void {
		$registry = new RuleRegistry();
		$rule     = new MemoryLimitRule();
		$registry->register( $rule );
		$this->assertTrue( $registry->has( 'system.php_memory_limit' ) );
		$this->assertSame( $rule, $registry->get( 'system.php_memory_limit' ) );
		$this->assertFalse( $registry->has( 'nonexistent.rule' ) );
		$this->assertNull( $registry->get( 'nonexistent.rule' ) );
	}

	public function test_get_enabled_and_disabled(): void {
		$registry = new RuleRegistry();
		$rule1    = new MemoryLimitRule();
		$rule2    = new OpcacheRule();
		$rule2->set_enabled( false );
		$registry->register( $rule1 );
		$registry->register( $rule2 );

		$enabled  = $registry->get_enabled();
		$disabled = $registry->get_disabled();
		$this->assertCount( 1, $enabled );
		$this->assertCount( 1, $disabled );
		$this->assertArrayHasKey( 'system.php_memory_limit', $enabled );
		$this->assertArrayHasKey( 'system.php_opcache_enabled', $disabled );
	}

	public function test_enable_disable(): void {
		$registry = new RuleRegistry();
		$rule     = new MemoryLimitRule();
		$registry->register( $rule );
		$this->assertTrue( $registry->enable( 'system.php_memory_limit' ) );
		$this->assertTrue( $rule->is_enabled() );
		$this->assertTrue( $registry->disable( 'system.php_memory_limit' ) );
		$this->assertFalse( $rule->is_enabled() );
		$this->assertFalse( $registry->enable( 'nonexistent.rule' ) );
		$this->assertFalse( $registry->disable( 'nonexistent.rule' ) );
	}

	public function test_get_metadata(): void {
		$registry = new RuleRegistry();
		$rule     = new MemoryLimitRule();
		$registry->register( $rule );
		$metadata = $registry->get_metadata();
		$this->assertArrayHasKey( 'system.php_memory_limit', $metadata );
		$this->assertSame( 'system.php_memory_limit', $metadata['system.php_memory_limit']['id'] );
		$this->assertSame( 'system', $metadata['system.php_memory_limit']['category'] );
		$this->assertTrue( $metadata['system.php_memory_limit']['enabled'] );
	}
}
