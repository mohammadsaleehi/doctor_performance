<?php
/**
 * Frontend and network analyzer for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Gathers frontend and network analysis data (read-only).
 *
 * Collects information about enqueued assets, response characteristics,
 * and resource loading configuration. Does not measure browser-side
 * Core Web Vitals (those require client-side instrumentation).
 */
final class FrontendCollector implements CollectorInterface {


	public function get_id(): string {
		return 'frontend';
	}

	/**
	 * Collect frontend metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		global $wp_styles, $wp_scripts;

		$data = array();

		// Response information.
		$data['http_status']  = null;
		$data['content_type'] = null;

		// Enqueued assets.
		$enqueued_styles  = array();
		$enqueued_scripts = array();

		if ( isset( $wp_styles ) && is_a( $wp_styles, 'WP_Styles' ) ) {
			$enqueued_styles = $wp_styles->queue;
		}

		if ( isset( $wp_scripts ) && is_a( $wp_scripts, 'WP_Scripts' ) ) {
			$enqueued_scripts = $wp_scripts->queue;
		}

		$data['styles_count']  = count( $enqueued_styles );
		$data['scripts_count'] = count( $enqueued_scripts );

		// External assets.
		$data['external_styles_count']  = 0;
		$data['external_scripts_count'] = 0;
		$data['external_asset_count']   = 0;

		$home_url = home_url();

		foreach ( $enqueued_styles as $handle ) {
			$style = $wp_styles->registered[ $handle ] ?? null;
			if ( $style && isset( $style->src ) ) {
				if ( $this->is_external_url( $style->src, $home_url ) ) {
					++$data['external_styles_count'];
				}
			}
		}

		foreach ( $enqueued_scripts as $handle ) {
			$script = $wp_scripts->registered[ $handle ] ?? null;
			if ( $script && isset( $script->src ) ) {
				if ( $this->is_external_url( $script->src, $home_url ) ) {
					++$data['external_scripts_count'];
				}
			}
		}

		$data['external_asset_count'] = $data['external_styles_count'] + $data['external_scripts_count'];

		// Loading strategies.
		$data['deferred_scripts'] = $this->count_deferred( $wp_scripts, $enqueued_scripts );
		$data['async_scripts']    = $this->count_async( $wp_scripts, $enqueued_scripts );

		// Total asset count.
		$data['total_assets'] = $data['styles_count'] + $data['scripts_count'];

		return $data;
	}

	/**
	 * Check if a URL is external to the home URL.
	 *
	 * @param string $url      URL to check.
	 * @param string $home_url Home URL.
	 * @return bool
	 */
	private function is_external_url( string $url, string $home_url ): bool {
		$url_parts  = @parse_url( $url );
		$home_parts = @parse_url( $home_url );

		if ( false === $url_parts || false === $home_parts ) {
			return false;
		}

		$url_host  = $url_parts['host'] ?? '';
		$home_host = $home_parts['host'] ?? '';

		return $url_host !== '' && $url_host !== $home_host;
	}

	/**
	 * Count deferred scripts.
	 *
	 * @param WP_Scripts|null $wp_scripts Scripts object.
	 * @param string[]        $queue      Enqueued script handles.
	 * @return int
	 */
	private function count_deferred( $wp_scripts, array $queue ): int {
		$count = 0;

		if ( ! isset( $wp_scripts ) || ! is_a( $wp_scripts, 'WP_Scripts' ) ) {
			return 0;
		}

		foreach ( $queue as $handle ) {
			$script = $wp_scripts->registered[ $handle ] ?? null;
			if ( $script && isset( $script->extra ) && is_array( $script->extra ) ) {
				if ( isset( $script->extra['defer'] ) && $script->extra['defer'] ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Count async scripts.
	 *
	 * @param WP_Scripts|null $wp_scripts Scripts object.
	 * @param string[]        $queue      Enqueued script handles.
	 * @return int
	 */
	private function count_async( $wp_scripts, array $queue ): int {
		$count = 0;

		if ( ! isset( $wp_scripts ) || ! is_a( $wp_scripts, 'WP_Scripts' ) ) {
			return 0;
		}

		foreach ( $queue as $handle ) {
			$script = $wp_scripts->registered[ $handle ] ?? null;
			if ( $script && isset( $script->extra ) && is_array( $script->extra ) ) {
				if ( isset( $script->extra['async'] ) && $script->extra['async'] ) {
					++$count;
				}
			}
		}

		return $count;
	}
}
