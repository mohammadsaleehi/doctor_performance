<?php
/**
 * Capability management for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Security;

/**
 * Centralizes capability checks for administrative actions.
 */
final class CapabilityManager {

	public function can_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}
