<?php
/**
 * Cache diagnostic data collector for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers cache diagnostic data (read-only).
 *
 * Detects object cache status, persistent cache backends, OPcache,
 * page caching indicators, and known cache plugins. Never flushes,
 * clears, or modifies caches.
 */
final class CacheCollector implements CollectorInterface {


	public function get_id(): string {
		return 'cache';
	}

	/**
	 * Collect cache metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		$data = array();

		// WordPress object cache.
		$data['object_cache_available'] = wp_using_ext_object_cache();
		$data['object_cache_type']      = null;
		$data['object_cache_active']    = false;

		global $wp_object_cache;
		if ( isset( $wp_object_cache ) ) {
			$class                       = get_class( $wp_object_cache );
			$data['object_cache_type']   = $this->detect_cache_type( $class );
			$data['object_cache_active'] = true;
		}

		// Redis availability.
		$data['redis_available'] = $this->check_redis();
		$data['redis_active']    = false;
		if ( $data['object_cache_active'] && is_string( $data['object_cache_type'] ) && $data['object_cache_type'] === 'redis' ) {
			$data['redis_active'] = true;
		}

		// Memcached availability.
		$data['memcached_available'] = $this->check_memcached();
		$data['memcached_active']    = false;
		if ( $data['object_cache_active'] && is_string( $data['object_cache_type'] ) && $data['object_cache_type'] === 'memcached' ) {
			$data['memcached_active'] = true;
		}

		// OPcache status.
		$data['opcache_available'] = extension_loaded( 'Zend OPcache' ) || extension_loaded( 'opcache' );
		$data['opcache_enabled']   = $data['opcache_available'] && ( ini_get( 'opcache.enable' ) === '1' || ini_get( 'opcache.enable' ) === true );

		// Page caching indicators.
		$data['page_cache_detected'] = $this->detect_page_cache();
		$data['cache_plugins']       = $this->detect_cache_plugins();

		// Transient storage.
		$data['transient_storage'] = $this->get_transient_storage_info();

		// Cache configuration.
		$data['cache_configured'] = $data['object_cache_available'] || $data['page_cache_detected'];

		return $data;
	}

	/**
	 * Detect cache backend type from class name.
	 *
	 * @param string $class Class name.
	 * @return string|null
	 */
	private function detect_cache_type( string $class ): ?string {
		$class_lower = strtolower( $class );

		if ( strpos( $class_lower, 'redis' ) !== false ) {
			return 'redis';
		}

		if ( strpos( $class_lower, 'memcached' ) !== false ) {
			return 'memcached';
		}

		if ( strpos( $class_lower, 'file' ) !== false ) {
			return 'file';
		}

		return 'unknown';
	}

	/**
	 * Check if Redis extension is available.
	 *
	 * @return bool
	 */
	private function check_redis(): bool
	{
		return extension_loaded( 'redis' );
	}
	/**
	 * Check if Memcached extension is available.
	 *
	 * @return bool
	 */
	private function check_memcached(): bool {
		return extension_loaded( 'memcached' ) || extension_loaded( 'memcache' );
	}

	/**
	 * Detect page caching indicators.
	 *
	 * @return array<string, bool|string|null>
	 */
	private function detect_page_cache(): array {
		$indicators = array(
			'wp_cache_enabled' => false,
			'w3_total_cache'   => false,
			'wp_rocket'        => false,
			'simple_cache'     => false,
			'headers'          => array(),
		);

		// Check for known cache plugin files.
		$cache_plugin_files = array(
			'wp-cache-phase2.php'                  => 'wp_cache_enabled',
			'w3-total-cache/W3TotalCache.php'      => 'w3_total_cache',
			'wp-rocket/inc/Client-side.php'        => 'wp_rocket',
			'simple-simple-cache/simple-cache.php' => 'simple_cache',
		);

		foreach ( $cache_plugin_files as $file => $key ) {
			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( file_exists( $path ) ) {
				$indicators[ $key ] = true;
			}
		}

		return $indicators;
	}

	/**
	 * Detect known cache plugins.
	 *
	 * @return string[]
	 */
	private function detect_cache_plugins(): array {
		$known_plugins = array(
			'wp-super-cache/wp-cache.php'          => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php'    => 'W3 Total Cache',
			'wp-rocket/wp-rocket.php'              => 'WP Rocket',
			'redis-cache/redis-cache.php'          => 'Redis Cache',
			'memcached/is-memcached.php'           => 'Memcached',
			'simple-simple-cache/simple-cache.php' => 'Simple Cache',
		);

		$found = array();
		foreach ( $known_plugins as $plugin_file => $plugin_name ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( file_exists( $path ) ) {
				$found[] = $plugin_name;
			}
		}

		return $found;
	}

	/**
	 * Get transient storage information.
	 *
	 * @return array<string, mixed>
	 */
	private function get_transient_storage_info(): array {
		global $wpdb;

		$info = array(
			'using_wp_options' => true,
			'count'            => null,
		);

		if ( method_exists( $wpdb, 'get_var' ) ) {
			$count         = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_%',
					'_transient_timeout_%'
				)
			);
			$info['count'] = $count ? (int) $count : 0;
		}

		return $info;
	}
}
