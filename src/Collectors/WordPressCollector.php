<?php
/**
 * WordPress diagnostic data collector for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers WordPress core diagnostic data (read-only).
 *
 * Uses native WordPress APIs whenever possible. Avoids expensive
 * operations on every request.
 */
final class WordPressCollector implements CollectorInterface {

	public function get_id(): string
	{
		return 'wordpress';
	}

	/**
	 * Collect WordPress metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		global $wp_version, $wpdb;

		$data = array();

		// Core version.
		$data['wordpress_version']           = $wp_version ?? '';
		$data['wordpress_installation_type'] = $this->get_installation_type();
		$data['wordpress_environment_type']  = wp_get_environment_type();

		// Multisite.
		$data['multisite']  = is_multisite();
		$data['blog_count'] = function_exists( 'get_blog_count' ) ? (int) get_blog_count() : 1;

		// URLs.
		$data['site_url'] = get_site_url();
		$data['home_url'] = home_url();

		// Theme.
		$theme                        = wp_get_theme();
		$data['active_theme']         = $theme->get( 'Name' );
		$data['active_theme_version'] = $theme->get( 'Version' );
		$data['active_theme_parent']  = $theme->parent() ? $theme->parent()->get( 'Name' ) : null;

		// Plugins.
		$data['plugin_count']           = count( get_option( 'active_plugins', array() ) );
		$data['installed_plugin_count'] = $this->get_installed_plugin_count();
		$data['active_plugins']         = $this->get_active_plugins_with_versions();

		// WordPress memory limits.
		$data['wp_memory_limit']     = $this->parse_ini_size( WP_MEMORY_LIMIT ?? '' );
		$data['wp_max_memory_limit'] = $this->parse_ini_size( WP_MAX_MEMORY_LIMIT ?? '' );

		// Debug mode.
		$data['debug_mode']    = defined( 'WP_DEBUG' ) && WP_DEBUG ? true : false;
		$data['debug_display'] = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? true : false;

		// Cache.
		$data['object_cache_available'] = wp_using_ext_object_cache();
		$data['object_cache_type']      = $this->get_object_cache_type();

		// Permalinks.
		$data['permalink_structure'] = get_option( 'permalink_structure', '' );

		// Timezone.
		$data['timezone']        = get_option( 'timezone_string', '' );
		$data['timezone_offset'] = (float) get_option( 'gmt_offset', 0 );

		// Cron.
		$data['cron_events_count'] = $this->get_cron_event_count();

		// Database prefix (internal only, not sensitive).
		$data['wpdb_prefix'] = $wpdb->prefix ?? '';

		return $data;
	}

	/**
	 * Determine WordPress installation type.
	 *
	 * @return string
	 */
	private function get_installation_type(): string {
		if ( is_multisite() ) {
			return 'multisite';
		}

		if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
			return 'installing';
		}

		return 'single';
	}

	/**
	 * Get count of installed plugins (including inactive).
	 *
	 * @return int
	 */
	private function get_installed_plugin_count(): int {
		$plugins_dir = WP_PLUGIN_DIR;
		if ( ! is_dir( $plugins_dir ) ) {
			return 0;
		}

		$files = scandir( $plugins_dir );
		if ( false === $files ) {
			return 0;
		}

		$count = 0;
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$plugin_file = $plugins_dir . '/' . $file . '/' . $file . '.php';
			if ( is_file( $plugin_file ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get active plugins with versions.
	 *
	 * @return array<string, string>
	 */
	private function get_active_plugins_with_versions(): array {
		$active_plugins = get_option( 'active_plugins', array() );
		$result         = array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
			if ( ! empty( $plugin_data['Name'] ) ) {
				$result[ $plugin_file ] = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
			}
		}

		return $result;
	}

	/**
	 * Get object cache type.
	 *
	 * @return string|null
	 */
	private function get_object_cache_type(): ?string {
		global $wp_object_cache;

		if ( ! isset( $wp_object_cache ) ) {
			return null;
		}

		$class = get_class( $wp_object_cache );

		if ( strpos( $class, 'Redis' ) !== false ) {
			return 'redis';
		}

		if ( strpos( $class, 'Memcached' ) !== false ) {
			return 'memcached';
		}

		if ( strpos( $class, 'File' ) !== false ) {
			return 'file';
		}

		return 'unknown';
	}

	/**
	 * Get count of scheduled cron events.
	 *
	 * @return int
	 */
	private function get_cron_event_count(): int {
		if ( ! function_exists( 'wp_get_schedule' ) ) {
			return 0;
		}

		$cron = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $cron as $timestamp => $hooks ) {
			if ( is_array( $hooks ) ) {
				$count += count( $hooks );
			}
		}

		return $count;
	}

	/**
	 * Parse an ini size string into bytes.
	 *
	 * @param string $size Size value.
	 * @return int|null Size in bytes, or null if unparseable/unlimited.
	 */
	private function parse_ini_size( string $size ): ?int {
		if ( '' === $size ) {
			return null;
		}

		$size = trim( $size );
		if ( $size === '-1' ) {
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
