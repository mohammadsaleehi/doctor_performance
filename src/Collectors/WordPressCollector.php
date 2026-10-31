<?php
/**
 * WordPress diagnostic data collector.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers WordPress core diagnostic data (read-only).
 */
final class WordPressCollector implements CollectorInterface {

	public function get_id(): string {
		return 'WordPress';
	}

	public function collect(): array {
		global $wp_version, $wpdb;

		return array(
			'wordpress_version'    => $wp_version ?? '',
			'site_url'             => get_site_url(),
			'home_url'             => home_url(),
			'multisite'            => is_multisite(),
			'blog_count'           => function_exists( 'get_blog_count' ) ? get_blog_count() : 1,
			'active_theme'         => wp_get_theme()->get( 'Name' ),
			'active_theme_version' => wp_get_theme()->get( 'Version' ),
			'plugin_count'         => count( get_option( 'active_plugins', array() ) ),
			'wpdb_prefix'          => $wpdb->prefix ?? '',
			'wpdb_database'        => $wpdb->dbname ?? '',
		);
	}
}
