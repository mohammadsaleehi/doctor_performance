<?php
/**
 * PHP request profiler for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Collectors;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Lightweight PHP request profiler.
 *
 * Measures request timing and memory usage using WordPress lifecycle hooks.
 * Measurement begins at the plugins_loaded hook and ends at the shutdown hook.
 * This does NOT capture time spent before plugins_loaded (e.g., mu-plugin loading,
 * WordPress bootstrap) or after shutdown.
 *
 * Uses configurable sampling to avoid recording every request. Avoids storing
 * sensitive data.
 */
final class PhpProfilerCollector implements CollectorInterface {

	/**
	 * Hook name for profiling start.
	 */
	public const HOOK_START = 'wpd_profiler_start';

	/**
	 * Get the unique collector identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'php_profiler';
	}

	/**
	 * Start time captured at plugins_loaded.
	 *
	 * @var float|null
	 */
	private static ?float $start_time = null;

	/**
	 * Memory usage at plugins_loaded.
	 *
	 * @var int|null
	 */
	private static ?int $start_memory = null;

	/**
	 * Whether the current request is excluded from profiling.
	 *
	 * @var bool
	 */
	private static bool $excluded = false;

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Constructor.
	 *
	 * Hooks are not registered in the constructor to allow explicit control
	 * over when profiling begins. Call register_hooks() to start profiling.
	 */
	public function __construct() {}

	/**
	 * Register profiler lifecycle hooks on every request.
	 *
	 * Hooks capture start time/memory at plugins_loaded and end time at shutdown.
	 * The actual sampling decision is made inside collect().
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		if ( function_exists( 'add_action' ) ) {
			// Capture request start as early as possible.
			add_action( 'plugins_loaded', array( $this, 'capture_start' ), 1 );

			// Capture request end at shutdown.
			add_action( 'shutdown', array( $this, 'capture_end' ), PHP_INT_MAX );
		}

		self::$hooks_registered = true;
	}

	/**
	 * Capture start time and memory at plugins_loaded.
	 *
	 * @return void
	 */
	public function capture_start(): void {
		if ( self::$excluded ) {
			return;
		}

		self::$start_time  = microtime( true );
		self::$start_memory = memory_get_usage( true );
	}

	/**
	 * Capture end time at shutdown.
	 *
	 * @return void
	 */
	public function capture_end(): void {
		if ( self::$excluded || null === self::$start_time ) {
			return;
		}

		// Mark as collected so we don't double-count.
		self::$excluded = true;
	}

	/**
	 * Collect profiling data for the current request.
	 *
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		// Ensure hooks are registered (backward compatibility for direct instantiation).
		$this->register_hooks();

		$data = array();
		// Determine if this request should be excluded from profiling.
		// Monitoring collection requests and internal requests are excluded
		// to avoid recursive profiling.
		$this->determine_exclusion();

		if ( self::$excluded ) {
			return array(
				'request_start_time' => null,
				'request_end_time'   => null,
				'request_duration'   => null,
				'memory_usage_start' => null,
				'memory_usage_end'   => memory_get_usage( true ),
				'peak_memory_usage'  => memory_get_peak_usage( true ),
				'request_type'       => 'excluded',
				'is_admin'           => false,
				'is_rest'            => defined( 'REST_REQUEST' ) && REST_REQUEST,
				'is_ajax'            => defined( 'DOING_AJAX' ) && DOING_AJAX,
				'is_cron'            => defined( 'DOING_CRON' ) && DOING_CRON,
				'sampled'            => false,
				'sample_rate'        => $this->get_sample_rate(),
			);
		}

		// Request timing.
		if ( null !== self::$start_time ) {
			$data['request_start_time'] = self::$start_time;
			$data['request_end_time']   = microtime( true );
			$data['request_duration']   = $data['request_end_time'] - self::$start_time;
		} else {
			$data['request_start_time'] = null;
			$data['request_end_time']   = microtime( true );
			$data['request_duration']   = null;
		}

		// Memory usage.
		$data['memory_usage_start'] = self::$start_memory;
		$data['memory_usage_end']   = memory_get_usage( true );
		$data['peak_memory_usage']  = memory_get_peak_usage( true );

		// Request context.
		$data['request_type'] = $this->get_request_type();
		$data['is_admin']     = \is_admin();
		$data['is_rest']      = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$data['is_ajax']      = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$data['is_cron']      = defined( 'DOING_CRON' ) && DOING_CRON;

		// Sampling info.
		$data['sampled']     = $this->is_sampled();
		$data['sample_rate'] = $this->get_sample_rate();

		return $data;
	}

	/**
	 * Determine if the current request should be excluded from profiling.
	 *
	 * Monitoring collection requests and other internal requests are excluded
	 * to avoid recursive profiling.
	 *
	 * @return void
	 */
	private function determine_exclusion(): void {
		if ( self::$excluded ) {
			return;
		}

		// Exclude monitoring collection requests.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			self::$excluded = true;
			return;
		}

		// Exclude REST API monitoring endpoints.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$path = '';
			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				$path = \sanitize_text_field( \wp_unslash( $_SERVER['PATH_INFO'] ) );
			}
			if ( strpos( $path, 'wp-performance-doctor/v1' ) !== false ) {
				self::$excluded = true;
				return;
			}
		}
	}

	/**
	 * Determine the request type.
	 *
	 * @return string
	 */
	private function get_request_type(): string {
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
	 * Check if current request is sampled for profiling.
	 *
	 * @return bool
	 */
	private function is_sampled(): bool {
		$sample_rate = $this->get_sample_rate();
		if ( $sample_rate >= 1.0 ) {
			return true;
		}

		if ( $sample_rate <= 0.0 ) {
			return false;
		}

		return (float) \wp_rand( 0, 1000000 ) / 1000000.0 < $sample_rate;
	}

	/**
	 * Get the profiling sample rate.
	 *
	 * @return float Sample rate between 0.0 and 1.0.
	 */
	private function get_sample_rate(): float {
		$rate = (float) \get_option( 'wpd_profiler_sample_rate', 0.1 );
		return max( 0.0, min( 1.0, $rate ) );
	}

	/**
	 * Reset static state (for testing).
	 *
	 * @return void
	 */
	public static function reset_state(): void {
		self::$start_time  = null;
		self::$start_memory = null;
		self::$excluded    = false;
	}
}
