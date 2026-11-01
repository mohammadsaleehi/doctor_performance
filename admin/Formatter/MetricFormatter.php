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
			'system.php_version'               => __( 'PHP Version', 'wp-performance-doctor' ),
			'system.php_sapi'                  => __( 'PHP SAPI', 'wp-performance-doctor' ),
			'system.memory_limit'              => __( 'PHP Memory Limit', 'wp-performance-doctor' ),
			'system.memory_usage'              => __( 'Memory Usage', 'wp-performance-doctor' ),
			'system.peak_memory_usage'         => __( 'Peak Memory Usage', 'wp-performance-doctor' ),
			'system.max_execution_time'        => __( 'Max Execution Time', 'wp-performance-doctor' ),
			'system.max_input_time'            => __( 'Max Input Time', 'wp-performance-doctor' ),
			'system.max_input_vars'            => __( 'Max Input Vars', 'wp-performance-doctor' ),
			'system.upload_max_filesize'       => __( 'Upload Max Filesize', 'wp-performance-doctor' ),
			'system.post_max_size'             => __( 'Post Max Size', 'wp-performance-doctor' ),
			'system.opcache_enabled'           => __( 'OPcache', 'wp-performance-doctor' ),
			'system.opcache_available'         => __( 'OPcache Available', 'wp-performance-doctor' ),
			'system.load_average'              => __( 'Load Average', 'wp-performance-doctor' ),
			'system.disk_free_space'           => __( 'Free Disk Space', 'wp-performance-doctor' ),
			'system.disk_total_space'          => __( 'Total Disk Space', 'wp-performance-doctor' ),
			'system.timezone'                  => __( 'Timezone', 'wp-performance-doctor' ),
			'system.server_software'           => __( 'Server Software', 'wp-performance-doctor' ),
			'system.php_extensions'            => __( 'PHP Extensions', 'wp-performance-doctor' ),
			// WordPress.
			'wordpress.wordpress_version'      => __( 'WordPress Version', 'wp-performance-doctor' ),
			'wordpress.active_theme'           => __( 'Active Theme', 'wp-performance-doctor' ),
			'wordpress.plugin_count'           => __( 'Active Plugins', 'wp-performance-doctor' ),
			'wordpress.multisite'              => __( 'Multisite', 'wp-performance-doctor' ),
			'wordpress.object_cache_available' => __( 'Object Cache', 'wp-performance-doctor' ),
			'wordpress.wp_memory_limit'        => __( 'WP Memory Limit', 'wp-performance-doctor' ),
			// Database.
			'database.database_size'           => __( 'Database Size', 'wp-performance-doctor' ),
			'database.table_count'             => __( 'Table Count', 'wp-performance-doctor' ),
			'database.autoloaded_options_size' => __( 'Autoloaded Options Size', 'wp-performance-doctor' ),
			'database.db_version'              => __( 'Database Version', 'wp-performance-doctor' ),
			'database.db_engine_type'          => __( 'Database Engine', 'wp-performance-doctor' ),
			// Cache.
			'cache.opcache_enabled'            => __( 'OPcache', 'wp-performance-doctor' ),
			'cache.object_cache_available'     => __( 'Object Cache', 'wp-performance-doctor' ),
			'cache.redis_available'            => __( 'Redis', 'wp-performance-doctor' ),
			'cache.memcached_available'        => __( 'Memcached', 'wp-performance-doctor' ),
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
	 * @return string Safe HTML/plain text ready for esc_html() in views.
	 */
	public static function format( array $metric ): string {
		$identifier = $metric['metric_identifier'] ?? '';
		$value      = $metric['metric_value'] ?? null;
		$unit       = $metric['unit'] ?? '';
		$status     = $metric['availability_status'] ?? Metric::STATUS_AVAILABLE;

		if ( ! self::is_available( $status ) ) {
			return esc_html( self::status_text( $status ) );
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

		switch ( $unit ) {
			case 'bytes':
				return esc_html( self::format_bytes( $value ) );
			case 'boolean':
				return esc_html( self::format_boolean( $value, $identifier ) );
			case 'count':
				return esc_html( self::format_count( $value ) );
			case 'seconds':
				return esc_html( self::format_seconds( $value ) );
			case 'load':
				return esc_html( self::format_load( $value ) );
			case 'string':
			default:
				return esc_html( self::format_string( $value, $identifier ) );
		}
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
	 * Get neutral status text for an unavailable metric.
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
		if ( ! is_numeric( $value ) ) {
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
	 * @return string Enabled/Disabled/Available/Unavailable/Yes/No.
	 */
	public static function format_boolean( $value, string $identifier ): string {
		$truthy = self::is_truthy( $value );

		if ( str_contains( $identifier, 'available' ) || str_contains( $identifier, '_active' ) ) {
			return $truthy ? __( 'Available', 'wp-performance-doctor' ) : __( 'Unavailable', 'wp-performance-doctor' );
		}

		if ( str_contains( $identifier, 'enabled' ) || str_contains( $identifier, 'active' ) ) {
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
		if ( ! is_numeric( $value ) ) {
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
		if ( ! is_numeric( $value ) ) {
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
		if ( ! is_numeric( $value ) ) {
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
			$html     .= '<span class="wpd-badge wpd-badge--small">' . $extension . '</span> ';
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

		$html = '<ul class="wpd-list">';
		foreach ( $plugins as $file => $label ) {
			$label = esc_html( (string) $label );
			$html .= '<li><span class="wpd-plugin-name">' . esc_html( basename( $file ) ) . '</span>' .
				'<span class="wpd-plugin-version">' . $label . '</span></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format table sizes, showing the five largest tables.
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

		$html = '<ul class="wpd-list">';
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

		$html  = '<ul class="wpd-list">';
		$found = false;
		foreach ( $indicators as $key => $detected ) {
			if ( empty( $names[ $key ] ) || 'headers' === $key ) {
				continue;
			}
			if ( self::is_truthy( $detected ) ) {
				$found = true;
				$label = esc_html( $names[ $key ] );
				$html .= '<li><span class="wpd-badge wpd-badge--success">' . $label . '</span></li>';
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

		$html = '<ul class="wpd-list">';
		foreach ( $plugins as $plugin ) {
			$html .= '<li><span class="wpd-badge">' . esc_html( (string) $plugin ) . '</span></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Format an ISO 8601 / MySQL datetime into a readable admin date/time.
	 *
	 * @param string $datetime Datetime string.
	 * @return string Formatted date/time or 'Never'.
	 */
	public static function format_date( ?string $datetime ): string {
		if ( empty( $datetime ) ) {
			return __( 'Never', 'wp-performance-doctor' );
		}

		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return esc_html( $datetime );
		}

		return date_i18n( __( 'M j, Y g:i a', 'wp-performance-doctor' ), $timestamp );
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
	 * Title-case a phrase.
	 *
	 * @param string $text Text to title-case.
	 * @return string
	 */
	private static function title_case( string $text ): string {
		return ucwords( strtolower( trim( $text ) ) );
	}
}
