<?php
/**
 * Dependency injection container for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Core;

/**
 * Lightweight dependency injection container.
 *
 * Minimal PSR-11-compatible container used to share plugin services without
 * introducing a full framework.
 */
class Container implements \Psr\Container\ContainerInterface {

	/** @var array<string, mixed> */
	private array $services = array();

	public function set( string $id, mixed $service ): void {
		$this->services[ $id ] = $service;
	}

	public function get( string $id ): mixed {
		if ( ! $this->has( $id ) ) {
			throw new \InvalidArgumentException( sprintf( 'Service "%s" not found.', $id ) );
		}

		return $this->services[ $id ];
	}

	public function has( string $id ): bool {
		return array_key_exists( $id, $this->services );
	}
}
