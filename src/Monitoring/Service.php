<?php
/**
 * Monitoring service for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Monitoring;

use WPPerformanceDoctor\Collectors\CacheCollector;
use WPPerformanceDoctor\Collectors\DatabaseCollector;
use WPPerformanceDoctor\Collectors\FrontendCollector;
use WPPerformanceDoctor\Collectors\PhpProfilerCollector;
use WPPerformanceDoctor\Collectors\SystemCollector;
use WPPerformanceDoctor\Collectors\WordPressCollector;
use WPPerformanceDoctor\Monitoring\Normalizer;
use WPPerformanceDoctor\Monitoring\Storage;
use WPPerformanceDoctor\Monitoring\Metric;

/**
 * Coordinates monitoring data collection, scheduling, and storage.
 *
 * Manages collector execution, WP-Cron events, locking, and snapshot
 * creation. Prevents overlapping collection jobs.
 */
final class Service {


	/**
	 * Default collection interval in seconds (1 hour).
	 */
	public const DEFAULT_INTERVAL = 3600;

	/**
	 * Minimum collection interval in seconds (5 minutes).
	 */
	public const MIN_INTERVAL = 300;

	/**
	 * Cron hook name.
	 */
	public const CRON_HOOK = 'wpd_monitoring_collect';

	/**
	 * Transient key for collection lock.
	 */
	public const LOCK_TRANSIENT = 'wpd_monitoring_collecting';

	/**
	 * Lock timeout in seconds.
	 */
	public const LOCK_TIMEOUT = 300;

	/**
	 * Storage instance.
	 *
	 * @var Storage
	 */
	private Storage $storage;

	/**
	 * Monitoring enabled flag.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Collection interval in seconds.
	 *
	 * @var int
	 */
	private int $interval;

	/**
	 * Profiling sample rate.
	 *
	 * @var float
	 */
	private float $sample_rate;

	/**
	 * Constructor.
	 *
	 * @param Storage $storage Storage instance.
	 * @param bool    $enabled Whether monitoring is enabled.
	 * @param int     $interval Collection interval in seconds.
	 * @param float   $sample_rate Profiling sample rate.
	 */
	public function __construct(
		Storage $storage,
		bool $enabled = true,
		int $interval = self::DEFAULT_INTERVAL,
		float $sample_rate = 0.1
	) {
		$this->storage     = $storage;
		$this->enabled     = $enabled;
		$this->interval    = $interval;
		$this->sample_rate = $sample_rate;
	}

	/**
	 * Get whether monitoring is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get the collection interval.
	 *
	 * @return int
	 */
	public function get_interval(): int {
		return $this->interval;
	}

	/**
	 * Get the profiling sample rate.
	 *
	 * @return float
	 */
	public function get_sample_rate(): float {
		return $this->sample_rate;
	}

	/**
	 * Register WP-Cron schedule and event.
	 *
	 * @return void
	 */
	public function register_schedule(): void {
		if ( ! $this->enabled ) {
			return;
		}

		// Register custom cron schedule via filter.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['wpd_monitoring_interval'] = array(
					'interval' => $this->interval,
					'display'  => __( 'WP Performance Doctor Monitoring', 'wp-performance-doctor' ),
				);
				return $schedules;
			}
		);

		// Schedule the collection event if not already scheduled.
		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			\wp_schedule_event( time(), 'wpd_monitoring_interval', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule WP-Cron events.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = \wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			\wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = \wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Run a full collection cycle.
	 *
	 * Collects from all registered collectors, normalizes data, and
	 * stores a snapshot. Returns the snapshot ID on success.
	 *
	 * @param string $trigger_type 'cron' or 'manual'.
	 * @return int|false Snapshot ID on success, false on failure.
	 */
	public function collect( string $trigger_type = 'manual' ) {
		if ( ! $this->enabled ) {
			return false;
		}

		// Prevent overlapping collection.
		if ( \get_transient( self::LOCK_TRANSIENT ) ) {
			return false;
		}

		\set_transient( self::LOCK_TRANSIENT, '1', self::LOCK_TIMEOUT );

		try {
			return $this->perform_collection( $trigger_type );
		} finally {
			\delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Perform the actual collection.
	 *
	 * @param string $trigger_type Trigger type.
	 * @return int|false Snapshot ID or false.
	 */
	private function perform_collection( string $trigger_type ) {
		$start_time   = microtime( true );
		$collected_at = gmdate( 'Y-m-d H:i:s' );

		$collectors = array(
			new SystemCollector(),
			new WordPressCollector(),
			new DatabaseCollector(),
			new CacheCollector(),
			new PhpProfilerCollector(),
			new FrontendCollector(),
		);

		$all_metrics = array();

		foreach ( $collectors as $collector ) {
			try {
				$raw_data   = $collector->collect();
				$normalizer = new Normalizer( $collector->get_id(), $collected_at );

				// Build schema based on collector type.
				$schema = $this->get_schema_for_collector( $collector->get_id() );

				$metrics     = $normalizer->normalize( $collector->get_id(), $raw_data, $schema );
				// Report expected-but-missing metrics as unavailable.
				$missing = $normalizer->missing_metrics(
					$collector->get_id(),
					array_keys( $schema ),
					$raw_data
				);
				$metrics     = array_merge( $metrics, $missing );
				$all_metrics = array_merge( $all_metrics, $metrics );
			} catch ( \Throwable $e ) {
				// Collection errors are non-fatal; other collectors continue.
				// Log the error for debugging but do not interrupt collection.
				error_log(
					sprintf(
						'WP Performance Doctor collector error (%s): %s',
						get_class( $collector ),
						$e->getMessage()
					)
				);
			}
		}

		$duration = microtime( true ) - $start_time;

		return $this->storage->save_snapshot(
			$all_metrics,
			$duration,
			'completed',
			$trigger_type
		);
	}

	/**
	 * Get normalization schema for a collector.
	 *
	 * @param string $collector_id Collector ID.
	 * @return array<string, array{unit: string, status: string}>
	 */
	private function get_schema_for_collector( string $collector_id ): array {
		$schemas = array(
			'system'       => array(
				'php_version'        => array(
					'unit'   => 'string',
					'status' => 'available',
				),
				'php_sapi'           => array(
					'unit'   => 'string',
					'status' => 'available',
				),
				'memory_limit'       => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'max_execution_time' => array(
					'unit'   => 'seconds',
					'status' => 'available',
				),
				'memory_usage'       => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'peak_memory_usage'  => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'opcache_enabled'    => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'load_average'       => array(
					'unit'   => 'load',
					'status' => 'unavailable',
				),
				'disk_free_space'    => array(
					'unit'   => 'bytes',
					'status' => 'unavailable',
				),
			),
			'wordpress'    => array(
				'wordpress_version'      => array(
					'unit'   => 'string',
					'status' => 'available',
				),
				'multisite'              => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'active_theme'           => array(
					'unit'   => 'string',
					'status' => 'available',
				),
				'plugin_count'           => array(
					'unit'   => 'count',
					'status' => 'available',
				),
				'wp_memory_limit'        => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'object_cache_available' => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
			),
			'database'     => array(
				'db_version'              => array(
					'unit'   => 'string',
					'status' => 'available',
				),
				'table_count'             => array(
					'unit'   => 'count',
					'status' => 'available',
				),
				'database_size'           => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'autoloaded_options_size' => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'total_options_count'     => array(
					'unit'   => 'count',
					'status' => 'available',
				),
			),
			'cache'        => array(
				'object_cache_available' => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'opcache_enabled'        => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'redis_available'        => array(
					'unit'   => 'boolean',
					'status' => 'unavailable',
				),
				'memcached_available'    => array(
					'unit'   => 'boolean',
					'status' => 'unavailable',
				),
			),
			'php_profiler' => array(
				'request_duration'  => array(
					'unit'   => 'seconds',
					'status' => 'available',
				),
				'memory_usage_end'  => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'peak_memory_usage' => array(
					'unit'   => 'bytes',
					'status' => 'available',
				),
				'is_admin'          => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'is_rest'           => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'is_ajax'           => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
				'is_cron'           => array(
					'unit'   => 'boolean',
					'status' => 'available',
				),
			),
			'frontend'     => array(
				'styles_count'         => array(
					'unit'   => 'count',
					'status' => 'available',
				),
				'scripts_count'        => array(
					'unit'   => 'count',
					'status' => 'available',
				),
				'external_asset_count' => array(
					'unit'   => 'count',
					'status' => 'available',
				),
				'total_assets'         => array(
					'unit'   => 'count',
					'status' => 'available',
				),
			),
		);

		return $schemas[ $collector_id ] ?? array();
	}

	/**
	 * Get the last collection timestamp.
	 *
	 * @return string|null
	 */
	public function get_last_collection_time(): ?string {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return null;
		}

		$time = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is validated via preg_match above.
			$wpdb->prepare( 'SELECT timestamp FROM ' . $table . ' ORDER BY id DESC LIMIT 1' )
		);

		return $time ? (string) $time : null;
	}

	/**
	 * Get the last collection status.
	 *
	 * @return string|null
	 */
	public function get_last_collection_status(): ?string {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return null;
		}

		$status = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is validated via preg_match above.
			$wpdb->prepare( 'SELECT collection_status FROM ' . $table . ' ORDER BY id DESC LIMIT 1' )
		);

		return $status ? (string) $status : null;
	}

	/**
	 * Get the next scheduled collection time.
	 *
	 * @return string|null
	 */
	public function get_next_collection_time(): ?string {
		$timestamp = \wp_next_scheduled( self::CRON_HOOK );
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}

	/**
	 * Set monitoring enabled state.
	 *
	 * @param bool $enabled Whether monitoring is enabled.
	 * @return void
	 */
	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;

		if ( $enabled ) {
			$this->register_schedule();
		} else {
			$this->unschedule();
		}
	}

	/**
	 * Set collection interval.
	 *
	 * @param int $interval Interval in seconds.
	 * @return void
	 */
	public function set_interval( int $interval ): void {
		$this->interval = max( self::MIN_INTERVAL, $interval );

		if ( $this->enabled ) {
			$this->unschedule();
			$this->register_schedule();
		}
	}

	/**
	 * Set profiling sample rate.
	 *
	 * @param float $rate Sample rate (0.0 to 1.0).
	 * @return void
	 */
	public function set_sample_rate( float $rate ): void {
		$this->sample_rate = max( 0.0, min( 1.0, $rate ) );
	}
}
