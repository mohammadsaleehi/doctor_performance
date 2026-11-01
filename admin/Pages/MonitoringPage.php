<?php
/**
 * Monitoring page for WP Performance Doctor admin.
 *
 * Displays real monitoring metrics organized by category.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * Monitoring page displaying collected metrics and snapshot history.
 */
final class MonitoringPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Monitoring service.
	 *
	 * @var Service|null
	 */
	private ?Service $service = null;

	/**
	 * Storage instance.
	 *
	 * @var Storage|null
	 */
	private ?Storage $storage = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Set monitoring service.
	 *
	 * @param Service $service Monitoring service.
	 * @return self
	 */
	public function set_service( Service $service ): self {
		$this->service = $service;
		return $this;
	}

	/**
	 * Set storage instance.
	 *
	 * @param Storage $storage Storage instance.
	 * @return self
	 */
	public function set_storage( Storage $storage ): self {
		$this->storage = $storage;
		return $this;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Render the monitoring page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_monitoring_data();

		\wp_enqueue_script( 'wp-performance-doctor-admin' );

		wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdMonitoring',
			array(
				'restUrl'  => esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) ),
				'nonce'    => wp_create_nonce( 'wpd_collect' ),
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'collecting' => __( 'Collecting…', 'wp-performance-doctor' ),
					'collect'    => __( 'Collect Now', 'wp-performance-doctor' ),
					'success'    => __( 'Collection complete.', 'wp-performance-doctor' ),
					'error'      => __( 'Collection failed. Please try again.', 'wp-performance-doctor' ),
				),
			)
		);

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/monitoring.php';
	}

	/**
	 * Get monitoring data from storage.
	 *
	 * @return array<string, mixed>
	 */
	private function get_monitoring_data(): array {
		$data = array(
			'enabled'             => false,
			'interval'            => 0,
			'last_collection'     => null,
			'last_status'         => null,
			'next_collection'     => null,
			'collection_duration' => null,
			'system'              => array(),
			'wordpress'           => array(),
			'database'            => array(),
			'cache'               => array(),
			'php'                 => array(),
			'frontend'            => array(),
			'has_data'            => false,
			'snapshots'           => array(),
			'rest_url'            => '',
			'nonce'               => '',
		);

		if ( $this->service ) {
			try {
				$data['enabled'] = $this->service->is_enabled();
			} catch ( \Throwable $e ) {
				$data['enabled'] = false;
			}

			try {
				$data['interval'] = $this->service->get_interval();
			} catch ( \Throwable $e ) {
				$data['interval'] = 0;
			}

			try {
				$data['last_collection'] = $this->service->get_last_collection_time();
				$data['last_status']     = $this->service->get_last_collection_status();
				$data['next_collection'] = $this->service->get_next_collection_time();
			} catch ( \Throwable $e ) {
				$data['last_collection'] = null;
				$data['last_status']     = null;
				$data['next_collection'] = null;
			}
		}

		if ( $this->storage ) {
			try {
				$data['system']    = $this->storage->get_latest_metrics_by_category( 'system' );
				$data['wordpress'] = $this->storage->get_latest_metrics_by_category( 'WordPress' );
				$data['database']  = $this->storage->get_latest_metrics_by_category( 'database' );
				$data['cache']     = $this->storage->get_latest_metrics_by_category( 'cache' );
				$data['php']       = $this->storage->get_latest_metrics_by_category( 'php_profiler' );
				$data['frontend']  = $this->storage->get_latest_metrics_by_category( 'frontend' );
			} catch ( \Throwable $e ) {
				$data['system']    = array();
				$data['wordpress'] = array();
				$data['database']  = array();
				$data['cache']     = array();
				$data['php']       = array();
				$data['frontend']  = array();
			}

			$data['has_data'] = ! empty( $data['system'] ) ||
				! empty( $data['wordpress'] ) ||
				! empty( $data['database'] ) ||
				! empty( $data['cache'] );

			try {
				$snapshot_result   = $this->storage->get_snapshots( 1, 10 );
				$data['snapshots'] = $snapshot_result['items'] ?? array();
			} catch ( \Throwable $e ) {
				$data['snapshots'] = array();
			}

			$data['collection_duration'] = $this->get_latest_collection_duration();
		}

		$data['rest_url'] = esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) );
		$data['nonce']    = wp_create_nonce( 'wpd_collect' );

		return $data;
	}

	/**
	 * Get the latest snapshot collection duration in seconds.
	 *
	 * @return float|null Duration in seconds, or null when no snapshot exists.
	 */
	private function get_latest_collection_duration(): ?float {
		if ( ! $this->storage ) {
			return null;
		}

		try {
			$snapshot_id = $this->storage->get_latest_snapshot_id();
			if ( null === $snapshot_id ) {
				return null;
			}

			$snapshot = $this->storage->get_snapshot( $snapshot_id );
			if ( ! $snapshot || ! isset( $snapshot['snapshot']['collection_duration'] ) ) {
				return null;
			}

			return (float) $snapshot['snapshot']['collection_duration'];
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
