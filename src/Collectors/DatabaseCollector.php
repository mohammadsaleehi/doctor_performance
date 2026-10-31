<?php
/**
 * Database diagnostic data collector.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers database diagnostic data (read-only).
 */
final class DatabaseCollector implements CollectorInterface {

	public function get_id(): string {
		return 'database';
	}

	public function collect(): array {
		global $wpdb;

		$data = array(
			'db_prefix' => $wpdb->prefix ?? '',
			'db_name'   => $wpdb->dbname ?? '',
		);

		if ( method_exists( $wpdb, 'get_var' ) && $wpdb->get_var( 'SELECT VERSION()' ) ) {
			$data['db_version'] = $wpdb->get_var( 'SELECT VERSION()' );
		}

		return $data;
	}
}
