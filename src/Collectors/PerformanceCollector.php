<?php
/**
 * Performance diagnostic data collector for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers performance-related diagnostic data (read-only).
 *
 * Collects PHP runtime measurements, OPcache status, system resource info,
 * cache detection, frontend asset counts, and database configuration.
 * Follows the same CollectorInterface as SystemCollector, DatabaseCollector,
 * CacheCollector, FrontendCollector, PhpProfilerCollector, and
 * WordPressCollector.
 *
 * All measurements are non-invasive and do not modify server or WordPress
 * configuration. Expensive operations are only performed in on-demand mode
 * when explicitly triggered by an administrator.
 */
final class PerformanceCollector implements CollectorInterface {

	/**
	 * Collector mode: passive (lightweight, runs during normal monitoring).
	 *
	 * @var string
	 */
	public const MODE_PASSIVE = 'passive';

	/**
	 * Collector mode: on-demand deep profiling (administrator-triggered).
	 *
	 * @var string
	 */
	public const MODE_ON_DEMAND = 'on_demand';

	/**
	 * Slow query threshold in seconds for database profiling.
	 *
	 * @var float
	 */
	public const SLOW_QUERY_THRESHOLD = 0.5;

	/**
	 * Maximum number of slow query fingerprints to retain.
	 *
	 * @var int
	 */
	public const MAX_SLOW_QUERIES = 20;

	/**
	 * Maximum number of frontend resources to record.
	 *
	 * @var int
	 */
	public const MAX_RESOURCES = 50;

	/**
	 * Unique collector identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'performance';
	}

	/**
	 * Collect performance metrics.
	 *
	 * @param string $mode Collection mode ('passive' or 'on_demand').
	 * @return array<string, mixed> Raw collector data keyed by metric identifier.
	 */
	public function collect( string $mode = self::MODE_PASSIVE ): array {
		$data = array();

		// Common lightweight metrics collected in both modes.
		$data = array_merge( $data, $this->collect_php_runtime() );
		$data = array_merge( $data, $this->collect_opcache() );
		$data = array_merge( $data, $this->collect_system_resources() );
		$data = array_merge( $data, $this->collect_cache_detection() );
		$data = array_merge( $data, $this->collect_frontend_assets() );
		$data = array_merge( $data, $this->collect_database_config() );
		$data = array_merge( $data, $this->collect_profiling_context( $mode ) );

		// On-demand mode performs deeper measurements.
		if ( self::MODE_ON_DEMAND === $mode ) {
			$data = array_merge( $data, $this->collect_database_profiling() );
			$data = array_merge( $data, $this->collect_http_network() );
			$data = array_merge( $data, $this->collect_frontend_profiling_hints() );
		}

		return $data;
	}

	/**
	 * Collect PHP runtime measurements.
	 *
	 * Reads actual ini values and runtime functions. Does not assume CLI
	 * configuration represents the web request runtime.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_php_runtime(): array {
		$memory_limit_raw = ini_get( 'memory_limit' );
		$memory_limit     = $this->parse_ini_size( $memory_limit_raw );

		return array(
			'php.memory_limit'       => $memory_limit,
			'php.memory_limit_raw'   => $memory_limit_raw,
			'php.max_execution_time' => (int) ini_get( 'max_execution_time' ),
			'php.memory_usage'       => memory_get_usage( true ),
			'php.peak_memory_usage'  => memory_get_peak_usage( true ),
			'php.request_time_float' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : null,
			'php.sapi'               => PHP_SAPI,
			'php.version'            => PHP_VERSION,
		);
	}

	/**
	 * Collect OPcache status and memory.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_opcache(): array {
		$available = extension_loaded( 'Zend OPcache' ) || extension_loaded( 'opcache' );
		$enabled   = $available && ( ini_get( 'opcache.enable' ) === '1' || ini_get( 'opcache.enable' ) === true );

		$data = array(
			'php.opcache_available' => $available,
			'php.opcache_enabled'   => $enabled,
		);

		if ( $enabled && function_exists( 'opcache_get_status' ) ) {
			$status = opcache_get_status( false );
			if ( false !== $status && is_array( $status ) ) {
				if ( isset( $status['memory_consumption'] ) ) {
					$data['php.opcache_memory_consumption'] = (int) $status['memory_consumption'];
				}
				if ( isset( $status['used_memory'] ) ) {
					$data['php.opcache_used_memory'] = (int) $status['used_memory'];
				}
				if ( isset( $status['free_memory'] ) ) {
					$data['php.opcache_free_memory'] = (int) $status['free_memory'];
				}
				if ( isset( $status['opcache_hit_rate'] ) ) {
					$data['php.opcache_hit_rate'] = (float) $status['opcache_hit_rate'];
				}
			}
		}

		return $data;
	}

	/**
	 * Collect system resource metrics (load average, disk, extensions).
	 *
	 * Load average is reported as a raw value, not CPU utilization percentage.
	 * Disk free/total space is filesystem capacity, not I/O throughput.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_system_resources(): array {
		$data = array();

		// Load average (Unix-like only).
		$data['system.load_average'] = null;
		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( is_array( $load ) ) {
				$data['system.load_average'] = (float) $load[0];
			}
		}

		// Disk information (filesystem capacity, not I/O throughput).
		$data['system.disk_free_space']  = null;
		$data['system.disk_total_space'] = null;
		if ( function_exists( 'disk_free_space' ) && function_exists( 'disk_total_space' ) ) {
			$wp_dir = ABSPATH;
			if ( is_dir( $wp_dir ) ) {
				$data['system.disk_free_space']  = disk_free_space( $wp_dir );
				$data['system.disk_total_space'] = disk_total_space( $wp_dir );
			}
		}

		// PHP extensions relevant to performance.
		$data['system.php_extensions'] = $this->get_performance_extensions();

		return $data;
	}

	/**
	 * Collect cache detection metrics.
	 *
	 * Distinguishes built-in WordPress object cache from persistent external
	 * backends (Redis, Memcached). Never claims persistent cache exists just
	 * because the built-in object cache is active.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_cache_detection(): array {
		$external_cache = wp_using_ext_object_cache();

		global $wp_object_cache;
		$detected_type = 'none';
		if ( $external_cache && isset( $wp_object_cache ) ) {
			$detected_type = $this->detect_cache_type( get_class( $wp_object_cache ) );
			if ( null === $detected_type ) {
				$detected_type = 'unknown';
			}
		}

		return array(
			'cache.object_cache_available'  => true,
			'cache.object_cache_active'     => true,
			'cache.object_cache_type'       => $detected_type,
			'cache.persistent_object_cache' => $external_cache,
			'cache.redis_available'         => extension_loaded( 'redis' ),
			'cache.redis_active'            => $external_cache && 'redis' === $detected_type,
			'cache.memcached_available'     => extension_loaded( 'memcached' ) || extension_loaded( 'memcache' ),
			'cache.memcached_active'        => $external_cache && 'memcached' === $detected_type,
			'cache.page_cache_detected'     => $this->detect_page_cache_indicators(),
			'cache.cache_plugins'           => $this->detect_cache_plugins(),
			'cache.cache_configured'        => $external_cache || ! empty( $this->detect_page_cache_indicators() ),
		);
	}

	/**
	 * Collect frontend asset metrics.
	 *
	 * Reports enqueued styles/scripts counts and external asset counts.
	 * Does not measure browser Core Web Vitals (requires client-side
	 * instrumentation).
	 *
	 * @return array<string, mixed>
	 */
	private function collect_frontend_assets(): array {
		global $wp_styles, $wp_scripts;

		$data = array(
			'frontend.styles_count'         => 0,
			'frontend.scripts_count'        => 0,
			'frontend.external_asset_count' => 0,
			'frontend.deferred_scripts'     => 0,
			'frontend.async_scripts'        => 0,
			'frontend.total_assets'         => 0,
			'frontend.measurement_context'  => $this->get_request_context(),
		);

		$enqueued_styles  = array();
		$enqueued_scripts = array();

		if ( isset( $wp_styles ) && is_a( $wp_styles, 'WP_Styles' ) ) {
			$enqueued_styles = $wp_styles->queue;
		}
		if ( isset( $wp_scripts ) && is_a( $wp_scripts, 'WP_Scripts' ) ) {
			$enqueued_scripts = $wp_scripts->queue;
		}

		$data['frontend.styles_count']  = count( $enqueued_styles );
		$data['frontend.scripts_count'] = count( $enqueued_scripts );
		$data['frontend.total_assets']  = count( $enqueued_styles ) + count( $enqueued_scripts );

		$home_url = home_url();

		$external_styles  = 0;
		$external_scripts = 0;

		foreach ( $enqueued_styles as $handle ) {
			$style = $wp_styles->registered[ $handle ] ?? null;
			if ( $style && isset( $style->src ) && $this->is_external_url( $style->src, $home_url ) ) {
				++$external_styles;
			}
		}
		foreach ( $enqueued_scripts as $handle ) {
			$script = $wp_scripts->registered[ $handle ] ?? null;
			if ( $script && isset( $script->src ) && $this->is_external_url( $script->src, $home_url ) ) {
				++$external_scripts;
			}
		}

		$data['frontend.external_asset_count'] = $external_styles + $external_scripts;

		if ( isset( $wp_scripts ) && is_a( $wp_scripts, 'WP_Scripts' ) ) {
			foreach ( $enqueued_scripts as $handle ) {
				$script = $wp_scripts->registered[ $handle ] ?? null;
				if ( $script && isset( $script->extra ) && is_array( $script->extra ) ) {
					if ( isset( $script->extra['defer'] ) && $script->extra['defer'] ) {
						++$data['frontend.deferred_scripts'];
					}
					if ( isset( $script->extra['async'] ) && $script->extra['async'] ) {
						++$data['frontend.async_scripts'];
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Collect database configuration metrics (not query profiling).
	 *
	 * @return array<string, mixed>
	 */
	private function collect_database_config(): array {
		global $wpdb;

		$data = array(
			'database.db_version'     => null,
			'database.db_engine_type' => 'mysql',
			'database.table_count'    => null,
			'database.database_size'  => null,
		);

		if ( method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Read-only VERSION() query using prepare().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time read-only query, no caching needed.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare -- prepare() used for consistency with codebase patterns.
			$data['database.db_version'] = $wpdb->get_var( $wpdb->prepare( 'SELECT VERSION()' ) );
		}
		if ( method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Read-only information_schema query using prepare().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time read-only query, no caching needed.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare -- prepare() used for consistency with codebase patterns.
			$data['database.table_count'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s',
					$wpdb->dbname
				)
			);
		}
		if ( method_exists( $wpdb, 'get_var' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Read-only information_schema query using prepare().
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time read-only query, no caching needed.
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnnecessaryPrepare -- prepare() used for consistency with codebase patterns.
			$db_size                        = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = %s',
					$wpdb->dbname
				)
			);
			$data['database.database_size'] = $db_size ? (int) $db_size : 0;
		}

		return $data;
	}

	/**
	 * Collect profiling context metadata.
	 *
	 * @param string $mode Collection mode.
	 * @return array<string, mixed>
	 */
	private function collect_profiling_context( string $mode ): array {
		return array(
			'performance.profiling_mode'  => $mode,
			'performance.request_context' => $this->get_request_context(),
			'performance.collected_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Collect on-demand database profiling (SAVEQUERIES-based).
	 *
	 * Only runs when SAVEQUERIES is enabled. If disabled, reports unavailable
	 * rather than making assumptions.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_database_profiling(): array {
		global $wpdb;

		$data = array(
			'database.query_count'             => null,
			'database.query_time_total'        => null,
			'database.slow_query_count'        => null,
			'database.slow_queries'            => array(),
			'database.query_type_distribution' => array(),
			'database.savequeries_enabled'     => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		);

		if ( ! ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) ) {
			return $data;
		}

		$queries = $wpdb->queries ?? array();
		$count   = count( $queries );
		$total   = 0.0;
		$slow    = array();
		$types   = array();

		$threshold = (float) apply_filters( 'wpd_slow_query_threshold', self::SLOW_QUERY_THRESHOLD );

		foreach ( $queries as $query ) {
			if ( ! is_array( $query ) || count( $query ) < 2 ) {
				continue;
			}
			$sql    = $query[0] ?? '';
			$time   = (float) ( $query[1] ?? 0 );
			$total += $time;

			if ( $time >= $threshold && count( $slow ) < self::MAX_SLOW_QUERIES ) {
				$slow[] = array(
					'fingerprint' => $this->fingerprint_query( $sql ),
					'duration'    => $time,
				);
			}

			$type = $this->classify_query( $sql );
			if ( ! isset( $types[ $type ] ) ) {
				$types[ $type ] = 0;
			}
			++$types[ $type ];
		}

		$data['database.query_count']             = $count;
		$data['database.query_time_total']        = $total;
		$data['database.slow_query_count']        = count( $slow );
		$data['database.slow_queries']            = array_values( $slow );
		$data['database.query_type_distribution'] = $types;

		return $data;
	}

	/**
	 * Collect HTTP/network profiling hints.
	 *
	 * Does not make unrestricted external requests. Reports only what can be
	 * observed from the current request context.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_http_network(): array {
		return array(
			'network.http_requests_count' => null,
			'network.http_request_time'   => null,
			'network.slow_external_count' => null,
			'network.measurement_note'    => 'External HTTP profiling requires administrator-triggered measurement.',
		);
	}

	/**
	 * Collect frontend profiling hints for manual browser measurement.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_frontend_profiling_hints(): array {
		return array(
			'frontend.navigation_timing_available' => null,
			'frontend.resource_timing_available'   => null,
			'frontend.ttfb'                        => null,
			'frontend.load_duration'               => null,
			'frontend.measurement_method'          => 'manual_browser_profile',
		);
	}

	/**
	 * Detect cache backend type from class name.
	 *
	 * @param string $class_name Class name.
	 * @return string|null
	 */
	private function detect_cache_type( string $class_name ): ?string {
		$class_lower = strtolower( $class_name );

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
	 * Detect page caching indicators.
	 *
	 * @return array<string, bool|string>
	 */
	private function detect_page_cache_indicators(): array {
		$indicators = array(
			'wp_cache_enabled' => false,
			'w3_total_cache'   => false,
			'wp_rocket'        => false,
			'simple_cache'     => false,
			'headers'          => array(),
		);

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
	 * Get PHP extensions relevant to WordPress performance.
	 *
	 * @return string[]
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

	/**
	 * Check if a URL is external to the home URL.
	 *
	 * @param string $url      URL to check.
	 * @param string $home_url Home URL.
	 * @return bool
	 */
	private function is_external_url( string $url, string $home_url ): bool {
		$url_parts  = wp_parse_url( $url );
		$home_parts = wp_parse_url( $home_url );

		if ( false === $url_parts || false === $home_parts ) {
			return false;
		}

		$url_host  = $url_parts['host'] ?? '';
		$home_host = $home_parts['host'] ?? '';

		return '' !== $url_host && $url_host !== $home_host;
	}

	/**
	 * Create a normalized query fingerprint (sanitized, no sensitive values).
	 *
	 * Strips string and numeric literals to avoid storing PII.
	 *
	 * @param string $sql SQL query.
	 * @return string Sanitized fingerprint (max 128 chars).
	 */
	private function fingerprint_query( string $sql ): string {
		$normalized = preg_replace( "/'[^']*'/", '?', $sql );
		$normalized = preg_replace( '/\b\d+\b/', '?', $normalized );
		$normalized = preg_replace( '/\s+/', ' ', trim( $normalized ) );

		return substr( $normalized, 0, 128 );
	}

	/**
	 * Classify a SQL query by type.
	 *
	 * @param string $sql SQL query.
	 * @return string Query type (select, insert, update, delete, other).
	 */
	private function classify_query( string $sql ): string {
		$first = trim( preg_split( '/\s+/', $sql, 2 )[0] ?? '' );
		$first = strtoupper( $first );

		if ( in_array( $first, array( 'SELECT', 'WITH' ), true ) ) {
			return 'select';
		}
		if ( 'INSERT' === $first ) {
			return 'insert';
		}
		if ( 'UPDATE' === $first ) {
			return 'update';
		}
		if ( 'DELETE' === $first ) {
			return 'delete';
		}

		return 'other';
	}

	/**
	 * Get the request context (frontend, admin, rest, ajax, cron).
	 *
	 * @return string
	 */
	private function get_request_context(): string {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return 'ajax';
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}
		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}

	/**
	 * Parse an ini size string (e.g. "128M", "1G") into bytes.
	 *
	 * @param string $size Ini size value.
	 * @return int|null Size in bytes, or null if unparseable/unlimited.
	 */
	private function parse_ini_size( string $size ): ?int {
		if ( '' === $size ) {
			return null;
		}

		$size = trim( $size );
		if ( '-1' === $size ) {
			return null;
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
}
