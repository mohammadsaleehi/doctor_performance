<?php
/**
 * Autoloading regression tests for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Core\Activator;
use WPPerformanceDoctor\Core\Deactivator;
use WPPerformanceDoctor\Core\Plugin;
use WPPerformanceDoctor\Core\Uninstall;

use PHPUnit\Framework\TestCase;

/**
 * AutoloadingTest class.
 *
 * @coversNothing
 */
final class AutoloadingTest extends TestCase {


	/**
	 * Data provider for classes that must be autoloadable.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provider_classes(): array {
		return array(
			'Plugin'      => array( Plugin::class ),
			'Activator'   => array( Activator::class ),
			'Deactivator' => array( Deactivator::class ),
			'Uninstall'   => array( Uninstall::class ),
		);
	}

	/**
	 * Assert each core class is autoloadable via Composer.
	 *
	 * @dataProvider provider_classes
	 * @param string $fully_qualified_class_name Fully-qualified class name.
	 */
	public function test_core_classes_are_autoloadable( $fully_qualified_class_name ): void {
		$this->assertTrue(
			class_exists( $fully_qualified_class_name ),
			sprintf( 'Class %s should be autoloadable.', $fully_qualified_class_name )
		);
	}

	/**
	 * Assert Plugin class is declared final.
	 */
	public function test_plugin_is_final(): void {
		$reflection = new \ReflectionClass( Plugin::class );
		$this->assertTrue( $reflection->isFinal() );
	}

	/**
	 * Assert Activator class is declared final.
	 */
	public function test_activator_is_final(): void {
		$reflection = new \ReflectionClass( Activator::class );
		$this->assertTrue( $reflection->isFinal() );
	}

	/**
	 * Assert Deactivator class is declared final.
	 */
	public function test_deactivator_is_final(): void {
		$reflection = new \ReflectionClass( Deactivator::class );
		$this->assertTrue( $reflection->isFinal() );
	}

	/**
	 * Assert Uninstall class is declared final.
	 */
	public function test_uninstall_is_final(): void {
		$reflection = new \ReflectionClass( Uninstall::class );
		$this->assertTrue( $reflection->isFinal() );
	}
}
