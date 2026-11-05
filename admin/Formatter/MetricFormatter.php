<?php
/**
 * Metric formatting helper for WP Performance Doctor admin dashboard.
 *
 * Converts raw stored metric values into human-readable, safe-for-display
 * representations based on metric identifier, unit, type and availability
 * status. Never exposes raw dot-notation identifiers or unformatted database
 * values as primary UI labels.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Formatter;

use WPPerformanceDoctor\Monitoring\Metric;

/**
 * Formats monitoring metrics for display.
 *
 * All public methods are stateless and safe to call from views.
 */
final class MetricFormatter {

	/**
	 * Human-readable labels keyed by metric identifier.
	 *
	 * Populated lazily on first use because property defaults cannot contain
	 * function calls.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $labels = null;

	/**
	 * Web Vitals rating thresholds.
	 *
	 * Values are in the metric's native unit (ms for durations, score for CLS).
	 * Lower is better for all of these metrics.
	 *
	 * @var array<string, array{good: float, needs_improvement: float, unit: string}>
	 */
	private const VITAL_THRESHOLDS = array(
		'frontend.navigation_timing_ttfb'    => array(
			'good'              => 800,
			'needs_improvement' => 1800,
			'unit'              => 'ms',
		),
		'frontend.largest_contentful_paint'  => array(
			'good'              => 2500,
			'needs_improvement' => 4000,
			'unit'              => 'ms',
		),
		'frontend.cumulative_layout_shift'   => array(
			'good'              => 0.1,
			'needs_improvement' => 0.25,
			'unit'              => 'score',
		),
		'frontend.interaction_to_next_paint' => array(
			'good'              => 200,
			'needs_improvement' => 500,
			'unit'              => 'ms',
		),
	);

	/**
	 * Profiling mode labels.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $mode_labels = null;

	/**
	 * Request context labels.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $context_labels = null;

	/**
	 * Session status labels.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $session_status_labels = null;

	/**
	 * Metric category display labels.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $category_labels = null;

	/**
	 * Status badge configuration keyed by availability status.
	 *
	 * Populated lazily on first use because property defaults cannot contain
	 * function calls.
	 *
	 * @var array<string, array{text: string, class: string}>|null
	 */
	private static ?array $status_badges = null;

	/**
	 * Get the label map, initializing it on first use.
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		if ( null !== self::$labels ) {
			return self::$labels;
		}

		self::$labels = array(
			// System.
			'system.php_version'                           => __( 'PHP Version', 'wp-performance-doctor' ),
			'system.php_sapi'                              => __( 'PHP SAPI', 'wp-performance-doctor' ),
			'system.php_os'                                => __( 'PHP OS', 'wp-performance-doctor' ),
			'system.os_family'                             => __( 'Operating System', 'wp-performance-doctor' ),
			'system.memory_limit'                          => __( 'PHP Memory Limit', 'wp-performance-doctor' ),
			'system.memory_usage'                          => __( 'Memory Usage', 'wp-performance-doctor' ),
			'system.peak_memory_usage'                     => __( 'Peak Memory Usage', 'wp-performance-doctor' ),
			'system.max_execution_time'                    => __( 'Max Execution Time', 'wp-performance-doctor' ),
			'system.max_input_time'                        => __( 'Max Input Time', 'wp-performance-doctor' ),
			'system.max_input_vars'                        => __( 'Max Input Vars', 'wp-performance-doctor' ),
			'system.upload_max_filesize'                   => __( 'Upload Max Filesize', 'wp-performance-doctor' ),
			'system.post_max_size'                         => __( 'Post Max Size', 'wp-performance-doctor' ),
			'system.opcache_enabled'                       => __( 'OPcache', 'wp-performance-doctor' ),
			'system.opcache_available'                     => __( 'OPcache Available', 'wp-performance-doctor' ),
			'system.opcache_memory_consumption'            => __( 'OPcache Memory', 'wp-performance-doctor' ),
			'system.opcache_used_memory'                   => __( 'OPcache Used Memory', 'wp-performance-doctor' ),
			'system.load_average'                          => __( 'Load Average', 'wp-performance-doctor' ),
			'system.disk_free_space'                       => __( 'Free Disk Space', 'wp-performance-doctor' ),
			'system.disk_total_space'                      => __( 'Total Disk Space', 'wp-performance-doctor' ),
			'system.timezone'                              => __( 'Timezone', 'wp-performance-doctor' ),
			'system.server_software'                       => __( 'Server Software', 'wp-performance-doctor' ),
			'system.php_extensions'                        => __( 'PHP Extensions', 'wp-performance-doctor' ),
			// WordPress.
			'wordpress.wordpress_version'                  => __( 'WordPress Version', 'wp-performance-doctor' ),
			'wordpress.wordpress_installation_type'        => __( 'Installation Type', 'wp-performance-doctor' ),
			'wordpress.wordpress_environment_type'         => __( 'Environment', 'wp-performance-doctor' ),
			'wordpress.multisite'                          => __( 'Multisite', 'wp-performance-doctor' ),
			'wordpress.blog_count'                         => __( 'Blog Count', 'wp-performance-doctor' ),
			'wordpress.active_theme'                       => __( 'Active Theme', 'wp-performance-doctor' ),
			'wordpress.active_theme_version'               => __( 'Theme Version', 'wp-performance-doctor' ),
			'wordpress.active_theme_parent'                => __( 'Parent Theme', 'wp-performance-doctor' ),
			'wordpress.plugin_count'                       => __( 'Active Plugins', 'wp-performance-doctor' ),
			'wordpress.installed_plugin_count'             => __( 'Installed Plugins', 'wp-performance-doctor' ),
			'wordpress.active_plugins'                     => __( 'Active Plugins', 'wp-performance-doctor' ),
			'wordpress.wp_memory_limit'                    => __( 'WP Memory Limit', 'wp-performance-doctor' ),
			'wordpress.wp_max_memory_limit'                => __( 'WP Max Memory Limit', 'wp-performance-doctor' ),
			'wordpress.debug_mode'                         => __( 'Debug Mode', 'wp-performance-doctor' ),
			'wordpress.debug_display'                      => __( 'Debug Display', 'wp-performance-doctor' ),
			'wordpress.object_cache_available'             => __( 'Object Cache', 'wp-performance-doctor' ),
			'wordpress.object_cache_type'                  => __( 'Object Cache Type', 'wp-performance-doctor' ),
			'wordpress.permalink_structure'                => __( 'Permalink Structure', 'wp-performance-doctor' ),
			'wordpress.timezone'                           => __( 'Timezone', 'wp-performance-doctor' ),
			'wordpress.timezone_offset'                    => __( 'Timezone Offset', 'wp-performance-doctor' ),
			'wordpress.cron_events_count'                  => __( 'Cron Events', 'wp-performance-doctor' ),
			'wordpress.site_url'                           => __( 'Site URL', 'wp-performance-doctor' ),
			'wordpress.home_url'                           => __( 'Home URL', 'wp-performance-doctor' ),
			'wordpress.wpdb_prefix'                        => __( 'Database Prefix', 'wp-performance-doctor' ),
			// Database.
			'database.db_server'                           => __( 'Database Server', 'wp-performance-doctor' ),
			'database.db_engine_type'                      => __( 'Database Engine', 'wp-performance-doctor' ),
			'database.db_version'                          => __( 'Database Version', 'wp-performance-doctor' ),
			'database.db_name'                             => __( 'Database Name', 'wp-performance-doctor' ),
			'database.db_charset'                          => __( 'Character Set', 'wp-performance-doctor' ),
			'database.db_collate'                          => __( 'Collation', 'wp-performance-doctor' ),
			'database.db_prefix'                           => __( 'Table Prefix', 'wp-performance-doctor' ),
			'database.table_count'                         => __( 'Table Count', 'wp-performance-doctor' ),
			'database.database_size'                       => __( 'Database Size', 'wp-performance-doctor' ),
			'database.table_sizes'                         => __( 'Largest Tables', 'wp-performance-doctor' ),
			'database.autoloaded_options_size'             => __( 'Autoloaded Options Size', 'wp-performance-doctor' ),
			'database.autoloaded_options_count'            => __( 'Autoloaded Options Count', 'wp-performance-doctor' ),
			'database.total_options_count'                 => __( 'Total Options', 'wp-performance-doctor' ),
			'database.transient_count'                     => __( 'Transients', 'wp-performance-doctor' ),
			'database.revisions_count'                     => __( 'Revisions', 'wp-performance-doctor' ),
			// Cache.
			'cache.object_cache_available'                 => __( 'WordPress Object Cache', 'wp-performance-doctor' ),
			'cache.object_cache_active'                    => __( 'Object Cache Active', 'wp-performance-doctor' ),
			'cache.persistent_object_cache'                => __( 'Persistent Object Cache', 'wp-performance-doctor' ),
			'cache.object_cache_type'                      => __( 'Object Cache Backend', 'wp-performance-doctor' ),
			'cache.opcache_available'                      => __( 'OPcache Available', 'wp-performance-doctor' ),
			'cache.opcache_enabled'                        => __( 'OPcache', 'wp-performance-doctor' ),
			'cache.redis_available'                        => __( 'Redis', 'wp-performance-doctor' ),
			'cache.redis_active'                           => __( 'Redis Active', 'wp-performance-doctor' ),
			'cache.memcached_available'                    => __( 'Memcached', 'wp-performance-doctor' ),
			'cache.memcached_active'                       => __( 'Memcached Active', 'wp-performance-doctor' ),
			'cache.page_cache_detected'                    => __( 'Page Cache', 'wp-performance-doctor' ),
			'cache.cache_plugins'                          => __( 'Cache Plugins', 'wp-performance-doctor' ),
			'cache.cache_configured'                       => __( 'Cache Configured', 'wp-performance-doctor' ),
			'cache.transient_storage'                      => __( 'Transient Storage', 'wp-performance-doctor' ),
			// PHP Profiler.
			'php_profiler.request_duration'                => __( 'Request Duration', 'wp-performance-doctor' ),
			'php_profiler.memory_usage_end'                => __( 'Memory Usage', 'wp-performance-doctor' ),
			'php_profiler.peak_memory_usage'               => __( 'Peak Memory Usage', 'wp-performance-doctor' ),
			'php_profiler.request_type'                    => __( 'Request Type', 'wp-performance-doctor' ),
			'php_profiler.is_admin'                        => __( 'Admin Request', 'wp-performance-doctor' ),
			'php_profiler.is_rest'                         => __( 'REST Request', 'wp-performance-doctor' ),
			'php_profiler.is_ajax'                         => __( 'AJAX Request', 'wp-performance-doctor' ),
			'php_profiler.is_cron'                         => __( 'Cron Request', 'wp-performance-doctor' ),
			'php_profiler.sampled'                         => __( 'Sampled', 'wp-performance-doctor' ),
			'php_profiler.sample_rate'                     => __( 'Sample Rate', 'wp-performance-doctor' ),
			// Frontend.
			'frontend.styles_count'                        => __( 'CSS Files', 'wp-performance-doctor' ),
			'frontend.scripts_count'                       => __( 'JavaScript Files', 'wp-performance-doctor' ),
			'frontend.external_asset_count'                => __( 'External Assets', 'wp-performance-doctor' ),
			'frontend.deferred_scripts'                    => __( 'Deferred Scripts', 'wp-performance-doctor' ),
			'frontend.async_scripts'                       => __( 'Async Scripts', 'wp-performance-doctor' ),
			'frontend.total_assets'                        => __( 'Total Assets', 'wp-performance-doctor' ),
			'frontend.http_status'                         => __( 'HTTP Status', 'wp-performance-doctor' ),
			'frontend.content_type'                        => __( 'Content Type', 'wp-performance-doctor' ),
			// Browser navigation timing.
			'frontend.navigation_timing_dns'               => __( 'DNS Lookup', 'wp-performance-doctor' ),
			'frontend.navigation_timing_tcp'               => __( 'TCP Connection', 'wp-performance-doctor' ),
			'frontend.navigation_timing_tls'               => __( 'TLS Negotiation', 'wp-performance-doctor' ),
			'frontend.navigation_timing_request_start'     => __( 'Request Start', 'wp-performance-doctor' ),
			'frontend.navigation_timing_ttfb'              => __( 'Time to First Byte', 'wp-performance-doctor' ),
			'frontend.navigation_timing_response_download' => __( 'Response Download', 'wp-performance-doctor' ),
			'frontend.navigation_timing_dom_interactive'   => __( 'DOM Interactive', 'wp-performance-doctor' ),
			'frontend.navigation_timing_dom_content_loaded' => __( 'DOM Content Loaded', 'wp-performance-doctor' ),
			'frontend.navigation_timing_dom_complete'      => __( 'DOM Complete', 'wp-performance-doctor' ),
			'frontend.navigation_timing_page_load'         => __( 'Page Load Duration', 'wp-performance-doctor' ),
			'frontend.navigation_timing_redirect'          => __( 'Redirect Duration', 'wp-performance-doctor' ),
			// Browser resource timing.
			'frontend.resource_count'                      => __( 'Total Resources', 'wp-performance-doctor' ),
			'frontend.css_resource_count'                  => __( 'CSS Resources', 'wp-performance-doctor' ),
			'frontend.js_resource_count'                   => __( 'JavaScript Resources', 'wp-performance-doctor' ),
			'frontend.image_resource_count'                => __( 'Image Resources', 'wp-performance-doctor' ),
			'frontend.font_resource_count'                 => __( 'Font Resources', 'wp-performance-doctor' ),
			'frontend.external_resource_count'             => __( 'External Resources', 'wp-performance-doctor' ),
			'frontend.resource_transfer_size'              => __( 'Resource Transfer Size', 'wp-performance-doctor' ),
			'frontend.resource_loading_duration'           => __( 'Slowest Resource Load', 'wp-performance-doctor' ),
			// Core Web Vitals.
			'frontend.largest_contentful_paint'            => __( 'Largest Contentful Paint (LCP)', 'wp-performance-doctor' ),
			'frontend.cumulative_layout_shift'             => __( 'Cumulative Layout Shift (CLS)', 'wp-performance-doctor' ),
			'frontend.interaction_to_next_paint'           => __( 'Interaction to Next Paint (INP)', 'wp-performance-doctor' ),
			// Browser support.
			'frontend.browser_support'                     => __( 'Browser Profiling Support', 'wp-performance-doctor' ),
		);

		return self::$labels;
	}

	/**
	 * Get the status badge map, initializing it on first use.
	 *
	 * @return array<string, array{text: string, class: string}>
	 */
	private static function status_badges(): array {
		if ( null !== self::$status_badges ) {
			return self::$status_badges;
		}

		self::$status_badges = array(
			Metric::STATUS_AVAILABLE   => array(
				'text'  => __( 'Available', 'wp-performance-doctor' ),
				'class' => 'wpd-status--available',
			),
			Metric::STATUS_UNAVAILABLE => array(
				'text'  => __( 'Not available', 'wp-performance-doctor' ),
				'class' => 'wpd-status--unavailable',
			),
			Metric::STATUS_UNSUPPORTED => array(
				'text'  => __( 'Unsupported', 'wp-performance-doctor' ),
				'class' => 'wpd-status--unsupported',
			),
			Metric::STATUS_ERROR       => array(
				'text'  => __( 'Error', 'wp-performance-doctor' ),
				'class' => 'wpd-status--error',
			),
		);

		return self::$status_badges;
	}

	/**
	 * Get a human-readable label for a metric identifier.
	 *
	 * Falls back to a title-cased conversion of the metric key when no
	 * explicit label exists. Never exposes dot-notation identifiers as-is.
	 *
	 * @param string $identifier Metric identifier (e.g. system.php_version).
	 * @return string Translated, human-readable label.
	 */
	public static function label( string $identifier ): string {
		if ( array_key_exists( $identifier, self::labels() ) ) {
			return self::labels()[ $identifier ];
		}

		// Fall back to the segment after the last dot.
		$key = $identifier;
		$pos = strrpos( $identifier, '.' );
		if ( false !== $pos ) {
			$key = substr( $identifier, $pos + 1 );
		}

		return self::title_case( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Format a single stored metric row into a display-ready value string.
	 *
	 * Respects availability_status: unavailable/unsupported/error values are
	 * shown as neutral status text, never as zero or null.
	 *
	 * @param array $metric Stored metric row from Storage.
	 * @return string Safe HTML/plain text ready for output in views.
	 */
	public static function format( array $metric ): string {
		$identifier = $metric['metric_identifier'] ?? '';
		$value      = $metric['metric_value'] ?? null;
		$unit       = $metric['unit'] ?? '';
		$status     = $metric['availability_status'] ?? Metric::STATUS_AVAILABLE;

		if ( ! self::is_available( $status ) ) {
			return self::unavailable_text( $status, $identifier );
		}

		// Complex / structured values first.
		if ( 'system.php_extensions' === $identifier ) {
			return self::format_extensions( $value );
		}
		if ( 'WordPress.active_plugins' === $identifier ) {
			return self::format_active_plugins( $value );
		}
		if ( 'database.table_sizes' === $identifier ) {
			return self::format_table_sizes( $value );
		}
		if ( 'cache.page_cache_detected' === $identifier ) {
			return self::format_page_cache( $value );
		}
		if ( 'cache.cache_plugins' === $identifier ) {
			return self::format_cache_plugins( $value );
		}
		if ( 'cache.transient_storage' === $identifier ) {
			return self::format_transient_storage( $value );
		}
		if ( 'WordPress.object_cache_type' === $identifier ) {
			return self::format_object_cache_type( $value );
		}
		if ( 'cache.object_cache_type' === $identifier ) {
			return self::format_object_cache_type( $value );
		}
		if ( 'WordPress.active_theme' === $identifier ) {
			return self::format_theme( $value );
		}

		// Byte values: format as binary units even when the stored unit is
		// missing (older snapshots collected before the unit was recorded).
		$byte_identifiers = array(
			'system.memory_usage',
			'system.peak_memory_usage',
			'system.memory_limit',
			'system.disk_free_space',
			'system.disk_total_space',
			'system.post_max_size',
			'system.upload_max_filesize',
			'wordpress.wp_memory_limit',
			'wordpress.wp_max_memory_limit',
			'database.database_size',
			'database.autoloaded_options_size',
		);
		if ( in_array( $identifier, $byte_identifiers, true ) && self::is_numeric_value( $value ) ) {
			return esc_html( self::format_bytes( $value ) );
		}

		switch ( $unit ) {
			case 'bytes':
				return esc_html( self::format_bytes( $value ) );
			case 'boolean':
				return esc_html( self::format_boolean( $value, $identifier ) );
			case 'count':
				return esc_html( self::format_count( $value ) );
			case 'seconds':
				return esc_html( self::format_seconds( $value ) );
			case 'ms':
				return esc_html( self::format_ms( $value ) );
			case 'score':
				return esc_html( self::format_score( $value ) );
			case 'load':
				return esc_html( self::format_load( $value ) );
			case 'string':
			default:
				return esc_html( self::format_string( $value, $identifier ) );
		}
	}

	/**
	 * Format a milliseconds value.
	 *
	 * @param mixed $value Raw milliseconds value.
	 * @return string Formatted duration.
	 */
	public static function format_ms( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return rtrim( rtrim( number_format( (float) $value, 1 ), '0' ), '.' ) . ' ms';
	}

	/**
	 * Format a unitless score value (e.g. CLS).
	 *
	 * @param mixed $value Raw score value.
	 * @return string Formatted score.
	 */
	public static function format_score( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return rtrim( rtrim( number_format( (float) $value, 3 ), '0' ), '.' );
	}

	/**
	 * Get neutral status text for an unavailable metric.
	 *
	 * @param string $status Availability status.
	 * @param string $identifier Optional metric identifier for context.
	 * @return string
	 */
	public static function unavailable_text( string $status, string $identifier = '' ): string {
		switch ( $status ) {
			case Metric::STATUS_UNAVAILABLE:
				// Some identifiers are known to be unsupported in this environment.
				if ( self::is_environment_limited( $identifier ) ) {
					return __( 'Not available in this environment', 'wp-performance-doctor' );
				}
				return __( 'Not available', 'wp-performance-doctor' );
			case Metric::STATUS_UNSUPPORTED:
				return __( 'Unsupported', 'wp-performance-doctor' );
			case Metric::STATUS_ERROR:
				return __( 'Error', 'wp-performance-doctor' );
			default:
				return __( 'Not available', 'wp-performance-doctor' );
		}
	}

	/**
	 * Whether a metric is known to be limited by the hosting environment.
	 *
	 * @param string $identifier Metric identifier.
	 * @return bool
	 */
	private static function is_environment_limited( string $identifier ): bool {
		$limited = array(
			'system.load_average',
			'system.disk_free_space',
			'system.opcache_memory_consumption',
			'system.opcache_used_memory',
			'cache.redis_available',
			'cache.memcached_available',
		);
		return in_array( $identifier, $limited, true );
	}

	/**
	 * Get status badge text and CSS class for a metric row.
	 *
	 * @param array $metric Stored metric row from Storage.
	 * @return array{text: string, class: string}
	 */
	public static function status_badge( array $metric ): array {
		$status = $metric['availability_status'] ?? Metric::STATUS_AVAILABLE;

		$badges = self::status_badges();
		if ( array_key_exists( $status, $badges ) ) {
			return $badges[ $status ];
		}

		return $badges[ Metric::STATUS_UNAVAILABLE ];
	}

	/**
	 * Whether a metric availability status represents an available value.
	 *
	 * @param string $status Availability status.
	 * @return bool
	 */
	public static function is_available( string $status ): bool {
		return Metric::STATUS_AVAILABLE === $status;
	}

	/**
	 * Get status text for an availability status.
	 *
	 * @param string $status Availability status.
	 * @return string
	 */
	public static function status_text( string $status ): string {
		$badges = self::status_badges();
		return $badges[ $status ]['text'] ?? $badges[ Metric::STATUS_UNAVAILABLE ]['text'];
	}

	/**
	 * Format bytes into human-readable binary units (GB, MB, KB, B).
	 *
	 * @param mixed $value Raw byte value.
	 * @return string Human-readable size.
	 */
	public static function format_bytes( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}

		$bytes = (int) $value;
		if ( 0 === $bytes ) {
			return '0 B';
		}

		$units = array( 'TB', 'GB', 'MB', 'KB', 'B' );
		$pow   = array( 1099511627776, 1073741824, 1048576, 1024, 1 );

		foreach ( $units as $index => $unit ) {
			if ( $bytes >= $pow[ $index ] ) {
				$number = $bytes / $pow[ $index ];
				if ( 1.0 === $number ) {
					return '1 ' . $unit;
				}
				return rtrim( rtrim( number_format( $number, 2 ), '0' ), '.' ) . ' ' . $unit;
			}
		}

		return $bytes . ' B';
	}

	/**
	 * Format a boolean value with context-aware labels.
	 *
	 * @param mixed  $value Raw boolean-ish value.
	 * @param string $identifier Metric identifier.
	 * @return string Enabled/Disabled/Active/Inactive/Available/Unavailable/Yes/No.
	 */
	public static function format_boolean( $value, string $identifier ): string {
		$truthy = self::is_truthy( $value );

		if ( str_contains( $identifier, 'available' ) || str_ends_with( $identifier, '_available' ) ) {
			return $truthy ? __( 'Available', 'wp-performance-doctor' ) : __( 'Unavailable', 'wp-performance-doctor' );
		}

		if ( str_contains( $identifier, '_active' ) || str_ends_with( $identifier, '_active' ) ) {
			return $truthy ? __( 'Active', 'wp-performance-doctor' ) : __( 'Inactive', 'wp-performance-doctor' );
		}

		if ( str_contains( $identifier, 'enabled' ) || str_ends_with( $identifier, '_enabled' ) || str_ends_with( $identifier, '_mode' ) || str_ends_with( $identifier, '_display' ) ) {
			return $truthy ? __( 'Enabled', 'wp-performance-doctor' ) : __( 'Disabled', 'wp-performance-doctor' );
		}

		return $truthy ? __( 'Yes', 'wp-performance-doctor' ) : __( 'No', 'wp-performance-doctor' );
	}

	/**
	 * Format a count value.
	 *
	 * @param mixed $value Raw count value.
	 * @return string Formatted count.
	 */
	public static function format_count( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return (string) (int) $value;
	}

	/**
	 * Format a seconds value.
	 *
	 * @param mixed $value Raw seconds value.
	 * @return string Formatted duration.
	 */
	public static function format_seconds( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return rtrim( rtrim( number_format( (float) $value, 2 ), '0' ), '.' ) . ' s';
	}

	/**
	 * Format a load average value.
	 *
	 * @param mixed $value Raw load value.
	 * @return string Formatted load.
	 */
	public static function format_load( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return rtrim( rtrim( number_format( (float) $value, 2 ), '0' ), '.' );
	}

	/**
	 * Format a plain string value, collapsing obvious placeholder empties.
	 *
	 * @param mixed  $value Raw string value.
	 * @param string $identifier Metric identifier.
	 * @return string
	 */
	private static function format_string( $value, string $identifier ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		if ( null === $value || '' === $value || 'null' === strtolower( (string) $value ) ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return (string) $value;
	}

	/**
	 * Format PHP extensions as compact badges.
	 *
	 * @param mixed $value JSON-encoded array of extension names.
	 * @return string HTML list of badges.
	 */
	public static function format_extensions( $value ): string {
		$extensions = self::decode_json_array( $value );
		if ( empty( $extensions ) ) {
			return __( 'None detected', 'wp-performance-doctor' );
		}

		$html = '';
		foreach ( $extensions as $extension ) {
			$extension = esc_html( (string) $extension );
			$html     .= '<span class="wpd-badge wpd-badge--small wpd-badge--outline">' . $extension . '</span> ';
		}

		return $html;
	}

	/**
	 * Format active plugins as a readable list.
	 *
	 * @param mixed $value JSON-encoded map of plugin file => "Name Version".
	 * @return string HTML list.
	 */
	public static function format_active_plugins( $value ): string {
		$plugins = self::decode_json_object( $value );
		if ( empty( $plugins ) ) {
			return __( 'None active', 'wp-performance-doctor' );
		}

		$html = '<ul class="wpd-list wpd-list--compact">';
		foreach ( $plugins as $file => $label ) {
			$label = esc_html( (string) $label );
			$html .= '<li><span class="wpd-plugin-name">' . esc_html( basename( $file ) ) . '</span>' .
				'<span class="wpd-plugin-version">' . $label . '</span></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format theme name with optional version.
	 *
	 * @param mixed $value Theme name.
	 * @return string
	 */
	private static function format_theme( $value ): string {
		if ( null === $value || '' === $value || 'null' === strtolower( (string) $value ) ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return esc_html( (string) $value );
	}

	/**
	 * Format object cache backend type.
	 *
	 * @param mixed $value Backend type string.
	 * @return string Human-readable backend name.
	 */
	public static function format_object_cache_type( $value ): string {
		if ( null === $value || '' === $value || 'null' === strtolower( (string) $value ) || 'none' === $value ) {
			return __( 'Built-in (in-memory)', 'wp-performance-doctor' );
		}

		$names = array(
			'redis'     => __( 'Redis', 'wp-performance-doctor' ),
			'memcached' => __( 'Memcached', 'wp-performance-doctor' ),
			'file'      => __( 'File-based', 'wp-performance-doctor' ),
			'unknown'   => __( 'Unknown', 'wp-performance-doctor' ),
		);

		$key = strtolower( (string) $value );
		return $names[ $key ] ?? esc_html( (string) $value );
	}

	/**
	 * Format table sizes, showing the largest tables.
	 *
	 * @param mixed $value JSON-encoded map of table name => size bytes.
	 * @return string HTML list.
	 */
	public static function format_table_sizes( $value ): string {
		$tables = self::decode_json_object( $value );
		if ( empty( $tables ) ) {
			return __( 'No tables detected', 'wp-performance-doctor' );
		}

		arsort( $tables );
		$tables = array_slice( $tables, 0, 5, true );

		$html = '<ul class="wpd-list wpd-list--compact">';
		foreach ( $tables as $name => $size ) {
			$html .= '<li><span class="wpd-table-name">' . esc_html( $name ) . '</span>' .
				'<span class="wpd-table-size">' . esc_html( self::format_bytes( $size ) ) . '</span></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format page cache detection indicators.
	 *
	 * @param mixed $value JSON-encoded object of indicator => status.
	 * @return string HTML list.
	 */
	public static function format_page_cache( $value ): string {
		$indicators = self::decode_json_object( $value );
		if ( empty( $indicators ) ) {
			return __( 'Not detected', 'wp-performance-doctor' );
		}

		$names = array(
			'wp_cache_enabled' => __( 'WP Super Cache', 'wp-performance-doctor' ),
			'w3_total_cache'   => __( 'W3 Total Cache', 'wp-performance-doctor' ),
			'wp_rocket'        => __( 'WP Rocket', 'wp-performance-doctor' ),
			'simple_cache'     => __( 'Simple Cache', 'wp-performance-doctor' ),
			'headers'          => __( 'Response Headers', 'wp-performance-doctor' ),
		);

		$html  = '<ul class="wpd-list wpd-list--compact">';
		$found = false;
		foreach ( $indicators as $key => $detected ) {
			if ( empty( $names[ $key ] ) || 'headers' === $key ) {
				continue;
			}
			if ( self::is_truthy( $detected ) ) {
				$found = true;
				$label = esc_html( $names[ $key ] );
				$html .= '<li><span class="wpd-badge wpd-badge--small wpd-badge--success">' . $label . '</span></li>';
			}
		}
		$html .= '</ul>';

		if ( ! $found ) {
			return __( 'None detected', 'wp-performance-doctor' );
		}

		return $html;
	}

	/**
	 * Format cache plugins list.
	 *
	 * @param mixed $value JSON-encoded array of plugin names.
	 * @return string HTML list.
	 */
	public static function format_cache_plugins( $value ): string {
		$plugins = self::decode_json_array( $value );
		if ( empty( $plugins ) ) {
			return __( 'None detected', 'wp-performance-doctor' );
		}

		$html = '<ul class="wpd-list wpd-list--compact">';
		foreach ( $plugins as $plugin ) {
			$html .= '<li><span class="wpd-badge wpd-badge--small">' . esc_html( (string) $plugin ) . '</span></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format transient storage info.
	 *
	 * @param mixed $value JSON-encoded object.
	 * @return string HTML.
	 */
	public static function format_transient_storage( $value ): string {
		$info  = self::decode_json_object( $value );
		$count = isset( $info['count'] ) && is_numeric( $info['count'] ) ? (int) $info['count'] : 0;
		return _n( '%d transient', '%d transients', $count, 'wp-performance-doctor' );
	}

	/**
	 * Format an ISO 8601 / MySQL datetime into a readable admin date/time.
	 *
	 * Stored monitoring timestamps and cron times are in UTC, so the value is
	 * parsed as UTC before being rendered in the site's local time zone.
	 *
	 * @param string $datetime Datetime string.
	 * @return string Formatted date/time or 'Never'.
	 */
	public static function format_date( ?string $datetime ): string {
		if ( empty( $datetime ) ) {
			return __( 'Never', 'wp-performance-doctor' );
		}

		$date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, new \DateTimeZone( 'UTC' ) );
		if ( false === $date ) {
			$timestamp = strtotime( $datetime );
			if ( false === $timestamp ) {
				return esc_html( $datetime );
			}
			return date_i18n( __( 'M j, Y g:i a', 'wp-performance-doctor' ), $timestamp );
		}

		return date_i18n( __( 'M j, Y g:i a', 'wp-performance-doctor' ), $date->getTimestamp() );
	}

	/**
	 * Decode a JSON value expecting an array.
	 *
	 * @param mixed $value JSON string or array.
	 * @return array
	 */
	private static function decode_json_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Decode a JSON value expecting an object/map.
	 *
	 * @param mixed $value JSON string or array.
	 * @return array
	 */
	private static function decode_json_object( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Coerce a value to boolean truthiness.
	 *
	 * @param mixed $value Value to coerce to boolean truthiness.
	 * @return bool
	 */
	private static function is_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value > 0.0;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Whether a value is numeric and usable.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_numeric_value( $value ): bool {
		return is_numeric( $value ) && '' !== $value;
	}

	/**
	 * Title-case a phrase.
	 *
	 * @param string $text Text to title-case.
	 * @return string
	 */
	private static function title_case( string $text ): string {
		return ucwords( strtolower( trim( $text ) ) );
	}

	/**
	 * Rate a Web Vitals / timing value against its thresholds.
	 *
	 * @param string $identifier Metric identifier.
	 * @param float  $value      Measured value.
	 * @return string|null 'good', 'needs_improvement', 'poor', or null when no threshold exists.
	 */
	public static function vital_rating( string $identifier, float $value ): ?string {
		$threshold = self::VITAL_THRESHOLDS[ $identifier ] ?? null;
		if ( null === $threshold ) {
			return null;
		}

		if ( $value <= $threshold['good'] ) {
			return 'good';
		}
		if ( $value <= $threshold['needs_improvement'] ) {
			return 'needs_improvement';
		}
		return 'poor';
	}

	/**
	 * Human-readable label for a Web Vitals rating.
	 *
	 * @param string|null $rating Rating string.
	 * @return string
	 */
	public static function vital_label( ?string $rating ): string {
		return match ( $rating ) {
			'good'              => __( 'Good', 'wp-performance-doctor' ),
			'needs_improvement' => __( 'Needs improvement', 'wp-performance-doctor' ),
			'poor'              => __( 'Poor', 'wp-performance-doctor' ),
			default             => __( 'Not measured', 'wp-performance-doctor' ),
		};
	}

	/**
	 * CSS class for a Web Vitals rating badge.
	 *
	 * @param string|null $rating Rating string.
	 * @return string
	 */
	public static function vital_class( ?string $rating ): string {
		return match ( $rating ) {
			'good'              => 'wpd-vital--good',
			'needs_improvement' => 'wpd-vital--needs-improvement',
			'poor'              => 'wpd-vital--poor',
			default             => 'wpd-vital--not-measured',
		};
	}

	/**
	 * Human-readable label for a profiling mode.
	 *
	 * @param string $mode Profiling mode.
	 * @return string
	 */
	public static function session_mode_label( string $mode ): string {
		self::init_label_arrays();
		return self::$mode_labels[ $mode ] ?? __( 'Unknown', 'wp-performance-doctor' );
	}

	/**
	 * Human-readable label for a request context.
	 *
	 * @param string $context Request context.
	 * @return string
	 */
	public static function request_context_label( string $context ): string {
		self::init_label_arrays();
		return self::$context_labels[ $context ] ?? __( 'Unknown', 'wp-performance-doctor' );
	}

	/**
	 * Human-readable label for a session status.
	 *
	 * @param string $status Session status.
	 * @return string
	 */
	public static function session_status_label( string $status ): string {
		self::init_label_arrays();
		return self::$session_status_labels[ $status ] ?? \ucfirst( $status );
	}

	/**
	 * Human-readable label for a metric category.
	 *
	 * @param string $category Metric category.
	 * @return string
	 */
	public static function category_label( string $category ): string {
		self::init_label_arrays();
		return self::$category_labels[ $category ] ?? \ucfirst( str_replace( '_', ' ', $category ) );
	}

	/**
	 * Lazily populate the translated label arrays.
	 *
	 * @return void
	 */
	private static function init_label_arrays(): void {
		if ( null !== self::$mode_labels ) {
			return;
		}

		self::$mode_labels = array(
			'on_demand' => __( 'On-demand profile', 'wp-performance-doctor' ),
			'passive'   => __( 'Monitoring snapshot', 'wp-performance-doctor' ),
		);

		self::$context_labels = array(
			'admin'    => __( 'Admin page', 'wp-performance-doctor' ),
			'frontend' => __( 'Public frontend', 'wp-performance-doctor' ),
			'rest'     => __( 'REST API', 'wp-performance-doctor' ),
			'ajax'     => __( 'AJAX request', 'wp-performance-doctor' ),
			'cron'     => __( 'Scheduled task (cron)', 'wp-performance-doctor' ),
		);

		self::$session_status_labels = array(
			'running'   => __( 'Running', 'wp-performance-doctor' ),
			'completed' => __( 'Completed', 'wp-performance-doctor' ),
			'failed'    => __( 'Failed', 'wp-performance-doctor' ),
			'partial'   => __( 'Partial results', 'wp-performance-doctor' ),
		);

		self::$category_labels = array(
			'php'         => __( 'PHP', 'wp-performance-doctor' ),
			'wordpress'   => __( 'WordPress', 'wp-performance-doctor' ),
			'database'    => __( 'Database', 'wp-performance-doctor' ),
			'cache'       => __( 'Cache', 'wp-performance-doctor' ),
			'system'      => __( 'System', 'wp-performance-doctor' ),
			'frontend'    => __( 'Frontend', 'wp-performance-doctor' ),
			'performance' => __( 'Performance', 'wp-performance-doctor' ),
		);
	}

	/**
	 * Group metrics by category with display-ready labels.
	 *
	 * @param array $metrics Stored metric rows.
	 * @return array<string, array<int, array>> Categories keyed by category name.
	 */
	public static function group_metrics( array $metrics ): array {
		$groups = array();
		foreach ( $metrics as $metric ) {
			$category = $metric['category'] ?? 'performance';
			if ( ! isset( $groups[ $category ] ) ) {
				$groups[ $category ] = array();
			}
			$groups[ $category ][] = $metric;
		}

		// Sort categories for a stable, meaningful display order.
		$order  = array( 'php', 'wordpress', 'database', 'cache', 'system', 'frontend', 'performance' );
		$sorted = array();
		foreach ( $order as $category ) {
			if ( isset( $groups[ $category ] ) ) {
				$sorted[ $category ] = $groups[ $category ];
				unset( $groups[ $category ] );
			}
		}
		foreach ( $groups as $category => $category_metrics ) {
			$sorted[ $category ] = $category_metrics;
		}

		return $sorted;
	}

	/**
	 * Format a percentage value.
	 *
	 * @param mixed $value Raw percentage value.
	 * @return string Formatted percentage or 'Not available'.
	 */
	public static function format_percentage( $value ): string {
		if ( ! is_numeric( $value ) || '' === $value ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}
		return rtrim( rtrim( number_format( (float) $value, 1 ), '0' ), '.' ) . '%';
	}

	/**
	 * Get structured display data for a single stored metric row.
	 *
	 * Returns enough information for a view to render the value, unit and
	 * availability status independently, including a clear distinction
	 * between unavailable data and an actual zero value.
	 *
	 * @param array $metric Stored metric row from Storage.
	 * @return array{
	 *     available: bool,
	 *     status: string,
	 *     status_label: string,
	 *     status_class: string,
	 *     display: string,
	 *     unit: string,
	 *     raw_value: mixed,
	 *     label: string
	 * }
	 */
	public static function display_data( array $metric ): array {
		$identifier = $metric['metric_identifier'] ?? '';
		$value      = $metric['metric_value'] ?? null;
		$unit       = $metric['unit'] ?? '';
		$status     = $metric['availability_status'] ?? Metric::STATUS_AVAILABLE;

		$available = self::is_available( $status );
		$badge     = self::status_badge( $metric );

		if ( ! $available ) {
			return array(
				'available'    => false,
				'status'       => $status,
				'status_label' => $badge['text'],
				'status_class' => $badge['class'],
				'display'      => $badge['text'],
				'unit'         => $unit,
				'raw_value'    => null,
				'label'        => self::label( $identifier ),
			);
		}

		$display = self::format( $metric );

		// Boolean-ish values stored without an explicit boolean unit are still
		// shown as readable labels rather than raw 1 / 0.
		if ( '' === $unit && self::is_booleanish( $value ) ) {
			$display = esc_html( self::format_boolean( $value, $identifier ) );
		}

		return array(
			'available'    => true,
			'status'       => $status,
			'status_label' => $badge['text'],
			'status_class' => $badge['class'],
			'display'      => $display,
			'unit'         => $unit,
			'raw_value'    => $value,
			'label'        => self::label( $identifier ),
		);
	}

	/**
	 * Whether a value is boolean-ish (1, 0, true, false, '1', '0', etc.).
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_booleanish( $value ): bool {
		if ( \is_bool( $value ) ) {
			return true;
		}
		if ( \is_numeric( $value ) ) {
			return in_array( (string) $value, array( '0', '1', '0.0', '1.0' ), true );
		}
		if ( \is_string( $value ) ) {
			return in_array( \strtolower( $value ), array( '0', '1', 'true', 'false', 'yes', 'no', 'on', 'off' ), true );
		}
		return false;
	}
}
