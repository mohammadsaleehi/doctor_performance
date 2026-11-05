<?php
/**
 * Monitoring page for WP Performance Doctor admin.
 *
 * Displays collected monitoring metrics organized by category, scoped to a
 * selected snapshot, with KPI overview cards, detailed metric sections,
 * historical trends, and a functional Collect Now action.
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
				'restUrl'   => esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) ),
				'nonce'     => wp_create_nonce( 'wpd_collect' ),
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'   => array(
					'collecting' => __( 'Collecting…', 'wp-performance-doctor' ),
					'collect'    => __( 'Collect Now', 'wp-performance-doctor' ),
					'success'    => __( 'Collection complete.', 'wp-performance-doctor' ),
					'error'      => __( 'Collection failed. Please try again.', 'wp-performance-doctor' ),
					'snapshotSaved' => __( 'Snapshot #%d created.', 'wp-performance-doctor' ),
				),
			)
		);

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/monitoring.php';
	}

	/**
	 * Get monitoring data scoped to a specific snapshot.
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
			'selected_snapshot'   => null,
			'selected_snapshot_id'=> null,
			'is_viewing_older'    => false,
			'kpi'                 => array(),
			'history'             => array(),
			'profiler_stats'      => array(),
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
				$data['interval'] = (int) $this->service->get_interval();
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

		$latest_completed_id = null;
		if ( $this->storage ) {
			try {
				$latest_completed_id = $this->storage->get_latest_completed_snapshot_id();
			} catch ( \Throwable $e ) {
				$latest_completed_id = null;
			}
		}

		// Determine which snapshot to display.
		$selected_snapshot_id = null;
		if ( $this->storage ) {
			try {
				$raw_snapshot_id = filter_input( INPUT_GET, 'snapshot', FILTER_SANITIZE_NUMBER_INT );
				if ( $raw_snapshot_id !== false && $raw_snapshot_id !== null && '' !== $raw_snapshot_id ) {
					$requested_id = (int) $raw_snapshot_id;
					// Validate that the requested snapshot actually exists.
					if ( $this->snapshot_exists( $requested_id ) ) {
						$selected_snapshot_id = $requested_id;
					}
				}
			} catch ( \Throwable $e ) {
				$selected_snapshot_id = null;
			}

			if ( null === $selected_snapshot_id ) {
				$selected_snapshot_id = $latest_completed_id;
			}

			try {
				$snapshot_result   = $this->storage->get_snapshots( 1, 20 );
				$data['snapshots'] = $snapshot_result['items'] ?? array();
			} catch ( \Throwable $e ) {
				$data['snapshots'] = array();
			}

			if ( null !== $selected_snapshot_id ) {
				try {
					$snapshot_data = $this->storage->get_snapshot( $selected_snapshot_id );
					if ( $snapshot_data ) {
						$data['selected_snapshot'] = $snapshot_data['snapshot'];
						$data['selected_snapshot_id'] = $selected_snapshot_id;
						$data['is_viewing_older'] = ( null !== $latest_completed_id && $selected_snapshot_id !== $latest_completed_id );

						$metrics_by_category = $this->group_metrics_by_category( $snapshot_data['metrics'] );

						$data['system']    = $metrics_by_category['system'] ?? array();
						$data['wordpress'] = $metrics_by_category['wordpress'] ?? array();
						$data['database']  = $metrics_by_category['database'] ?? array();
						$data['cache']     = $metrics_by_category['cache'] ?? array();
						$data['php']       = $metrics_by_category['php_profiler'] ?? array();
						$data['frontend']  = $metrics_by_category['frontend'] ?? array();

						$data['collection_duration'] = (float) ( $snapshot_data['snapshot']['collection_duration'] ?? 0 );

						$data['has_data'] = ! empty( $data['system'] ) ||
							! empty( $data['wordpress'] ) ||
							! empty( $data['database'] ) ||
							! empty( $data['cache'] );

						$data['kpi'] = $this->compute_kpi_summaries( $data['system'], $data['wordpress'], $data['database'], $data['cache'] );
						$data['profiler_stats'] = $this->compute_profiler_stats( $data['php'] );
					}
				} catch ( \Throwable $e ) {
					$data['selected_snapshot'] = null;
					$data['selected_snapshot_id'] = null;
					$data['is_viewing_older'] = false;
					$data['system']    = array();
					$data['wordpress'] = array();
					$data['database']  = array();
					$data['cache']     = array();
					$data['php']       = array();
					$data['frontend']  = array();
					$data['kpi']       = array();
					$data['profiler_stats'] = array();
				}
			}

			// Historical trend data (independent of selected snapshot).
			try {
				$data['history'] = $this->compute_history();
			} catch ( \Throwable $e ) {
				$data['history'] = array();
			}
		}

		$data['rest_url'] = esc_url_raw( rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) );
		$data['nonce']    = wp_create_nonce( 'wpd_collect' );

		return $data;
	}

	/**
	 * Check whether a snapshot with the given ID exists.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return bool
	 */
	private function snapshot_exists( int $snapshot_id ): bool {
		if ( ! $this->storage ) {
			return false;
		}

		try {
			$data = $this->storage->get_snapshot( $snapshot_id );
			return ! empty( $data );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Group flat metric rows by their category.
	 *
	 * @param array $metrics Flat metric data arrays.
	 * @return array<string, array> Metrics grouped by category key.
	 */
	private function group_metrics_by_category( array $metrics ): array {
		$grouped = array(
			'system'     => array(),
			'wordpress'  => array(),
			'database'   => array(),
			'cache'      => array(),
			'php_profiler' => array(),
			'frontend'   => array(),
		);

		foreach ( $metrics as $metric ) {
			$category = $metric['category'] ?? '';
			if ( isset( $grouped[ $category ] ) ) {
				$grouped[ $category ][] = $metric;
			}
		}

		return $grouped;
	}

	/**
	 * Find a metric by identifier within a category's metric list.
	 *
	 * @param array $metrics Metric rows.
	 * @param string $identifier Metric identifier.
	 * @return array|null
	 */
	private function find_metric( array $metrics, string $identifier ): ?array {
		foreach ( $metrics as $metric ) {
			if ( ( $metric['metric_identifier'] ?? '' ) === $identifier ) {
				return $metric;
			}
		}
		return null;
	}

	/**
	 * Compute KPI summary data for the four overview cards.
	 *
	 * @return array<string, array>
	 */
	private function compute_kpi_summaries( array $system, array $wordpress, array $database, array $cache ): array {
		return array(
			'system' => array(
				'primary'      => $this->find_metric( $system, 'system.memory_usage' ),
				'secondary'    => array(
					$this->find_metric( $system, 'system.php_version' ),
					$this->find_metric( $system, 'system.opcache_enabled' ),
				),
				'metric_count' => count( $system ),
			),
			'wordpress' => array(
				'primary'      => $this->find_metric( $wordpress, 'wordpress.wordpress_version' ),
				'secondary'    => array(
					$this->find_metric( $wordpress, 'wordpress.active_theme' ),
					$this->find_metric( $wordpress, 'wordpress.plugin_count' ),
				),
				'metric_count' => count( $wordpress ),
			),
			'database' => array(
				'primary'      => $this->find_metric( $database, 'database.database_size' ),
				'secondary'    => array(
					$this->find_metric( $database, 'database.db_engine_type' ),
					$this->find_metric( $database, 'database.table_count' ),
				),
				'metric_count' => count( $database ),
			),
			'cache' => array(
				'primary'      => $this->find_metric( $cache, 'cache.persistent_object_cache' ),
				'secondary'    => array(
					$this->find_metric( $cache, 'cache.opcache_enabled' ),
					$this->find_metric( $cache, 'cache.redis_available' ),
				),
				'metric_count' => count( $cache ),
			),
		);
	}

	/**
	 * Compute PHP profiler summary statistics from the selected snapshot.
	 *
	 * @param array $phpMetrics Profiler metric rows.
	 * @return array
	 */
	private function compute_profiler_stats( array $phpMetrics ): array {
		$stats = array(
			'request_count'  => 0,
			'avg_duration'   => null,
			'median_duration'=> null,
			'p95_duration'   => null,
			'peak_memory'    => null,
			'request_types'  => array(),
			'sample_rate'    => null,
			'has_samples'    => false,
		);

		$durationMetric = $this->find_metric( $phpMetrics, 'php_profiler.request_duration' );
		$duration = null;
		if ( $durationMetric && self::is_numeric_value( $durationMetric['metric_value'] ?? null ) && 'available' === ( $durationMetric['availability_status'] ?? '' ) ) {
			$duration = (float) $durationMetric['metric_value'];
		}

		$requestTypeMetric = $this->find_metric( $phpMetrics, 'php_profiler.request_type' );
		$requestType = $requestTypeMetric ? ( $requestTypeMetric['metric_value'] ?? null ) : null;

		// Only count valid frontend request samples. Excluded/cron/rest/admin
		// collection requests are not valid frontend samples.
		$isValidSample = $duration !== null && 'frontend' === $requestType;

		if ( $isValidSample ) {
			$stats['request_count']   = 1;
			$stats['avg_duration']    = $duration;
			$stats['median_duration'] = $duration;
			$stats['p95_duration']    = $duration;
			$stats['has_samples']     = true;
		}

		$peakMemoryMetric = $this->find_metric( $phpMetrics, 'php_profiler.peak_memory_usage' );
		if ( $peakMemoryMetric && self::is_numeric_value( $peakMemoryMetric['metric_value'] ?? null ) && 'available' === ( $peakMemoryMetric['availability_status'] ?? '' ) ) {
			$stats['peak_memory'] = (int) $peakMemoryMetric['metric_value'];
		}

		if ( $requestTypeMetric ) {
			$stats['request_types'][ $requestType ] = isset( $stats['request_types'][ $requestType ] ) ? $stats['request_types'][ $requestType ] + 1 : 1;
		}

		$sampleRateMetric = $this->find_metric( $phpMetrics, 'php_profiler.sample_rate' );
		if ( $sampleRateMetric && self::is_numeric_value( $sampleRateMetric['metric_value'] ?? null ) ) {
			$stats['sample_rate'] = (float) $sampleRateMetric['metric_value'];
		}

		return $stats;
	}

	/**
	 * Compute historical trend data for charts.
	 *
	 * @return array
	 */
	private function compute_history(): array {
		$history = array(
			'snapshots'  => array(),
			'memory'     => array(),
			'database'   => array(),
			'peak_memory'=> array(),
			'duration'   => array(),
		);

		if ( ! $this->storage ) {
			return $history;
		}

		try {
			$snapshots = $this->storage->get_snapshots( 1, 30 );
			$items     = $snapshots['items'] ?? array();

			foreach ( array_reverse( $items ) as $snap ) {
				$history['snapshots'][] = array(
					'id'          => (int) ( $snap['id'] ?? 0 ),
					'timestamp'   => $snap['timestamp'] ?? null,
					'status'      => $snap['collection_status'] ?? 'unknown',
					'trigger'     => $snap['trigger_type'] ?? 'manual',
					'duration'    => (float) ( $snap['collection_duration'] ?? 0 ),
					'metric_count'=> (int) ( $snap['metric_count'] ?? 0 ),
				);
			}
		} catch ( \Throwable $e ) {
			$items = array();
		}

		// Metric value history across snapshots.
		$history['memory']     = $this->storage->get_metric_history( 'system.memory_usage', 30 );
		$history['database']   = $this->storage->get_metric_history( 'database.database_size', 30 );
		$history['peak_memory']= $this->storage->get_metric_history( 'system.peak_memory_usage', 30 );

		return $history;
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
}
