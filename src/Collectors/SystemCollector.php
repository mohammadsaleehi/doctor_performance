<?php
/**
 * System diagnostic data collector for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers system-level diagnostic data (read-only).
 *
 * Collects PHP, server, and operating system metrics. Never exposes
 * sensitive paths, credentials, or environment variables.
 */
final class SystemCollector implements CollectorInterface {


	public function get_id(): string {
		return 'system';
	}

	/**
	 * Collect system metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		$data = array();

		// PHP version info.
		$data['php_version'] = PHP_VERSION;
		$data['php_sapi']    = PHP_SAPI;
		$data['php_os']      = PHP_OS_FAMILY;

		// PHP configuration.
		$data['memory_limit']            = $this->parse_ini_size( ini_get( 'memory_limit' ) );
		$data['memory_limit_raw']        = ini_get( 'memory_limit' );
		$data['max_execution_time']      = (int) ini_get( 'max_execution_time' );
		$data['max_input_time']          = (int) ini_get( 'max_input_time' );
		$data['max_input_vars']          = (int) ini_get( 'max_input_vars' );
		$data['upload_max_filesize']     = $this->parse_ini_size( ini_get( 'upload_max_filesize' ) );
		$data['upload_max_filesize_raw'] = ini_get( 'upload_max_filesize' );
		$data['post_max_size']           = $this->parse_ini_size( ini_get( 'post_max_size' ) );
		$data['post_max_size_raw']       = ini_get( 'post_max_size' );

		// Memory usage.
		$data['memory_usage']      = memory_get_usage( true );
		$data['peak_memory_usage'] = memory_get_peak_usage( true );

		// Timezone.
		$data['timezone'] = date_default_timezone_get();

		// OPcache.
		$data['opcache_available']          = extension_loaded( 'Zend OPcache' ) || extension_loaded( 'opcache' );
		$data['opcache_enabled']            = $data['opcache_available'] && ( ini_get( 'opcache.enable' ) === '1' || ini_get( 'opcache.enable' ) === true );
		$data['opcache_memory_consumption'] = null;
		$data['opcache_used_memory']        = null;
		if ( $data['opcache_enabled'] && function_exists( 'opcache_get_status' ) ) {
			$opcache_status = @opcache_get_status( false );
			if ( is_array( $opcache_status ) && isset( $opcache_status['memory_consumption'] ) ) {
				$data['opcache_memory_consumption'] = (int) $opcache_status['memory_consumption'];
			}
			if ( is_array( $opcache_status ) && isset( $opcache_status['used_memory'] ) ) {
				$data['opcache_used_memory'] = (int) $opcache_status['used_memory'];
			}
		}

		// PHP extensions relevant to WordPress performance.
		$data['php_extensions'] = $this->get_performance_extensions();

		// Server information (safe subset only).
		$data['server_software'] = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : null;

		// Operating system.
		$data['os_family'] = PHP_OS_FAMILY;

		// Server load average (Unix-like only).
		$data['load_average'] = null;
		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( is_array( $load ) ) {
				$data['load_average'] = (float) $load[0];
			}
		}

		// Disk information (safe subset only).
		$data['disk_free_space']  = null;
		$data['disk_total_space'] = null;
		if ( function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' ) ) {
			$wp_dir = ABSPATH;
			if ( is_dir( $wp_dir ) ) {
				$data['disk_free_space']  = @disk_free_space( $wp_dir );
				$data['disk_total_space'] = @disk_total_space( $wp_dir );
			}
		}

		return $data;
	}

	/**
	 * Parse an ini size string (e.g. "128M", "1G") into bytes.
	 *
	 * @param string $size Ini size value.
	 * @return int|null Size in bytes, or null if unparseable.
	 */
	private function parse_ini_size( string $size ): ?int {
		if ( '' === $size ) {
			return null;
		}

		$size = trim( $size );
		if ( $size === '-1' ) {
			return null; // Unlimited.
		}

		$unit  = strtoupper( substr( $size, -1 ) );
		$value = (float) substr( $size, 0, -1 );

		switch ( $unit ) {
			case 'G':
				return (int) ( $value * 1024 * 1024 * 1024 );
			case 'M':
				return (int) ( $value * 1024 * 1024 );
			case 'K':
				return (int) ( $value * 1024 );
			default:
				return (int) $value;
		}
	}

	/**
	 * Get PHP extensions relevant to WordPress performance.
	 *
	 * @return string[] List of loaded extension names.
	 */
	private function get_performance_extensions(): array {
		$relevant = array(
			'opcache',
			'apcu',
			'memcached',
			'redis',
			'mbstring',
			'xml',
			'mysqli',
			'pdo_mysql',
			'curl',
			'gd',
			'imagick',
			'zip',
			'zlib',
		);

		$loaded = get_loaded_extensions();
		$found  = array();

		foreach ( $relevant as $ext ) {
			if ( in_array( $ext, $loaded, true ) ) {
				$found[] = $ext;
			}
		}

		return $found;
	}
}
