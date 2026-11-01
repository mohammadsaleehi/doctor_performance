<?php
/**
 * DebugDisplayRule unit tests.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit\Rules;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Rules\WordPress\DebugDisplayRule;
use PHPUnit\Framework\TestCase;

/**
 * DebugDisplayRuleTest class.
 */
final class DebugDisplayRuleTest extends TestCase {

	private function makeContext( array $metrics ): DiagnosticContext {
		return new DiagnosticContext( $metrics );
	}

	/**
	 * Rule passes when debug display is disabled.
	 */
	public function test_debug_display_disabled(): void {
		$rule = new DebugDisplayRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'wordpress.debug_mode',
				'metric_value'        => false,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.debug_display',
				'metric_value'        => false,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.wordpress_environment_type',
				'metric_value'        => 'production',
				'unit'                => 'string',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
		);
		$context = $this->makeContext( $metrics );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'pass', $result->outcome );
	}

	/**
	 * Rule fails when debug display is enabled on production.
	 */
	public function test_debug_display_enabled_production(): void {
		$rule = new DebugDisplayRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'wordpress.debug_mode',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.debug_display',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.wordpress_environment_type',
				'metric_value'        => 'production',
				'unit'                => 'string',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
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
	 * Rule returns info when debug display is enabled on development.
	 */
	public function test_debug_display_enabled_development(): void {
		$rule = new DebugDisplayRule();
		$metrics = array(
			array(
				'metric_identifier'   => 'wordpress.debug_mode',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.debug_display',
				'metric_value'        => true,
				'unit'                => 'boolean',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
			array(
				'metric_identifier'   => 'wordpress.wordpress_environment_type',
				'metric_value'        => 'development',
				'unit'                => 'string',
				'availability_status' => 'available',
				'category'            => 'wordpress',
				'source'              => 'wordpress',
				'collected_at'        => '2026-10-31 12:00:00',
				'metadata'            => '{}',
			),
		);
		$context = $this->makeContext( $metrics );
		$result  = $rule->evaluate( $context );
		$this->assertSame( 'info', $result->outcome );
	}
}
