<?php
/**
 * Database diagnostic data collector for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers database performance and configuration data (read-only).
 *
 * Uses $wpdb and prepared queries. Never executes destructive SQL.
 * Never modifies database records.
 */
final class DatabaseCollector implements CollectorInterface {


	public function get_id(): string {
		return 'database';
	}

	/**
	 * Collect database metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		global $wpdb;

		$data = array();

		// Basic connection info (safe subset).
		$data['db_server']  = 'mysql'; // $wpdb uses MySQL/MariaDB.
		$data['db_prefix']  = $wpdb->prefix ?? '';
		$data['db_name']    = $wpdb->dbname ?? '';
		$data['db_charset'] = $wpdb->charset ?? '';
		$data['db_collate'] = $wpdb->collate ?? '';

		// Database version.
		$data['db_version'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$data['db_version'] = $wpdb->get_var( $wpdb->prepare( 'SELECT VERSION()' ) );
		}

		// Database engine type.
		$data['db_engine_type'] = 'mysql';

		// Table count.
		$data['table_count'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$data['table_count'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s',
					$wpdb->dbname
				)
			);
		}

		// Autoloaded options size and count.
		$data['autoloaded_options_size']  = null;
		$data['autoloaded_options_count'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$autoload_size                   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
				)
			);
			$data['autoloaded_options_size'] = $autoload_size ? (int) $autoload_size : 0;

			$autoload_count                   = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'"
				)
			);
			$data['autoloaded_options_count'] = $autoload_count ? (int) $autoload_count : 0;
		}

		// Total options count.
		$data['total_options_count'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$total                       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options}" ) );
			$data['total_options_count'] = $total ? (int) $total : 0;
		}

		// Transient count.
		$data['transient_count'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$transient_count         = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_%'
				)
			);
			$data['transient_count'] = $transient_count ? (int) $transient_count : 0;
		}

		// Revisions count.
		$data['revisions_count'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$revisions               = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
					'revision'
				)
			);
			$data['revisions_count'] = $revisions ? (int) $revisions : 0;
		}

		// Table sizes.
		$data['table_sizes'] = array();
		if ( method_exists( $wpdb, 'get_results' ) ) {
			$tables = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT table_name AS name,
					 (data_length + index_length) AS size
				  FROM information_schema.tables
				  WHERE table_schema = %s
				  ORDER BY size DESC
				  LIMIT 10',
					$wpdb->dbname
				)
			);
			if ( is_array( $tables ) ) {
				foreach ( $tables as $table ) {
					$data['table_sizes'][ $table->name ] = (int) $table->size;
				}
			}
		}

		// Database size.
		$data['database_size'] = null;
		if ( method_exists( $wpdb, 'get_var' ) ) {
			$db_size               = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s',
					$wpdb->dbname
				)
			);
			$data['database_size'] = $db_size ? (int) $db_size : 0;
		}

		return $data;
	}
}
