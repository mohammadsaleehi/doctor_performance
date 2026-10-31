<?php
/**
 * Nonce management for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Security;

/**
 * Centralizes nonce creation and verification.
 */
final class NonceManager {

	public function create( string $action ): string {
		return wp_create_nonce( $action );
	}

	public function verify( string $action, string $nonce ): bool {
		return wp_verify_nonce( $nonce, $action );
	}
}
