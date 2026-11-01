<?php
/**
 * Diagnostic context providing normalized metrics to rules.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * Provides rules with the latest normalized metrics, WordPress environment
 * information, configuration, and relevant snapshot metadata.
 *
 * Metrics are looked up by dot-notation identifier (e.g. system.memory_limit).
 * Unavailable metrics return null and are never treated as zero.
 */
final class DiagnosticContext {

	/**
	 * Latest metrics keyed by identifier.
	 *
	 * @var array<string, array{value: mixed, unit: string, status: string, category: string, source: string, collected_at: string}>
	 */
	private array $metrics;

	/**
	 * WordPress environment type.
	 *
	 * @var string
	 */
	private string $environment_type;

	/**
	 * Whether multisite is enabled.
	 *
	 * @var bool
	 */
	private bool $is_multisite;

	/**
	 * WordPress version.
	 *
	 * @var string
	 */
	private string $wp_version;

	/**
	 * Related monitoring snapshot ID.
	 *
	 * @var int|null
	 */
	private ?int $snapshot_id;

	/**
	 * Snapshot collection timestamp.
	 *
	 * @var string|null
	 */
	private ?string $snapshot_timestamp;

	/**
	 * Snapshot collection duration in seconds.
	 *
	 * @var float|null
	 */
	private ?float $snapshot_duration;

	/**
	 * Constructor.
	 *
	 * @param array      $latest_metrics Latest metric rows from Storage.
	 * @param int|null   $snapshot_id Related snapshot ID.
	 * @param array|null $snapshot Snapshot metadata.
	 */
	public function __construct( array $latest_metrics, ?int $snapshot_id = null, ?array $snapshot = null ) {
		$this->metrics = array();

		foreach ( $latest_metrics as $metric ) {
			$identifier = $metric['metric_identifier'] ?? '';
			if ( '' === $identifier ) {
				continue;
			}

			$this->metrics[ $identifier ] = array(
				'value'        => $metric['metric_value'] ?? null,
				'unit'         => $metric['unit'] ?? '',
				'status'       => $metric['availability_status'] ?? Metric::STATUS_UNAVAILABLE,
				'category'     => $metric['category'] ?? '',
				'source'       => $metric['source'] ?? '',
				'collected_at' => $metric['collected_at'] ?? '',
			);
		}

		$this->snapshot_id        = $snapshot_id;
		$this->snapshot_timestamp = $snapshot['timestamp'] ?? null;
		$this->snapshot_duration  = $snapshot['collection_duration'] ?? null;

		// Extract environment info from metrics if available.
		$wp_env_metric          = $this->metrics['wordpress.wordpress_environment_type'] ?? null;
		$this->environment_type = $wp_env_metric && Metric::STATUS_AVAILABLE === $wp_env_metric['status']
			? (string) $wp_env_metric['value']
			: 'production';

		$multisite_metric   = $this->metrics['wordpress.multisite'] ?? null;
		$this->is_multisite = $multisite_metric && Metric::STATUS_AVAILABLE === $multisite_metric['status']
			? $this->is_truthy( $multisite_metric['value'] )
			: false;

		$wp_ver_metric    = $this->metrics['wordpress.wordpress_version'] ?? null;
		$this->wp_version = $wp_ver_metric && Metric::STATUS_AVAILABLE === $wp_ver_metric['status']
			? (string) $wp_ver_metric['value']
			: '';
	}

	/**
	 * Get a metric value by identifier.
	 *
	 * Returns null when the metric is missing or unavailable.
	 * Never treats unavailable as zero.
	 *
	 * @param string $identifier Metric identifier (e.g. system.memory_limit).
	 * @return mixed|null
	 */
	public function get_metric( string $identifier ) {
		$metric = $this->metrics[ $identifier ] ?? null;
		if ( null === $metric ) {
			return null;
		}
		if ( Metric::STATUS_AVAILABLE !== $metric['status'] ) {
			return null;
		}
		return $metric['value'];
	}

	/**
	 * Get the full metric record by identifier.
	 *
	 * @param string $identifier Metric identifier.
	 * @return array|null
	 */
	public function get_metric_record( string $identifier ): ?array {
		return $this->metrics[ $identifier ] ?? null;
	}

	/**
	 * Check if a metric exists and is available.
	 *
	 * @param string $identifier Metric identifier.
	 * @return bool
	 */
	public function has_metric( string $identifier ): bool {
		$metric = $this->metrics[ $identifier ] ?? null;
		return null !== $metric && Metric::STATUS_AVAILABLE === $metric['status'];
	}

	/**
	 * Check if a metric exists but is unavailable/unsupported/error.
	 *
	 * @param string $identifier Metric identifier.
	 * @return bool
	 */
	public function is_metric_unavailable( string $identifier ): bool {
		$metric = $this->metrics[ $identifier ] ?? null;
		return null !== $metric && Metric::STATUS_AVAILABLE !== $metric['status'];
	}

	/**
	 * Check whether all required metrics are available.
	 *
	 * @param string[] $identifiers Metric identifiers.
	 * @return bool
	 */
	public function has_all_metrics( array $identifiers ): bool {
		foreach ( $identifiers as $identifier ) {
			if ( ! $this->has_metric( $identifier ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get missing metric identifiers.
	 *
	 * @param string[] $identifiers Metric identifiers.
	 * @return string[]
	 */
	public function missing_metrics( array $identifiers ): array {
		$missing = array();
		foreach ( $identifiers as $identifier ) {
			if ( ! $this->has_metric( $identifier ) ) {
				$missing[] = $identifier;
			}
		}
		return $missing;
	}

	/**
	 * Get all available metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_available_metrics(): array {
		$available = array();
		foreach ( $this->metrics as $id => $metric ) {
			if ( Metric::STATUS_AVAILABLE === $metric['status'] ) {
				$available[ $id ] = $metric['value'];
			}
		}
		return $available;
	}

	/**
	 * Get all metrics (including unavailable).
	 *
	 * @return array<string, array>
	 */
	public function get_all_metrics(): array {
		return $this->metrics;
	}

	/**
	 * Get the WordPress environment type.
	 *
	 * @return string
	 */
	public function get_environment_type(): string {
		return $this->environment_type;
	}

	/**
	 * Check if the site is a multisite installation.
	 *
	 * @return bool
	 */
	public function is_multisite(): bool {
		return $this->is_multisite;
	}

	/**
	 * Get the WordPress version.
	 *
	 * @return string
	 */
	public function get_wp_version(): string {
		return $this->wp_version;
	}

	/**
	 * Get the related snapshot ID.
	 *
	 * @return int|null
	 */
	public function get_snapshot_id(): ?int {
		return $this->snapshot_id;
	}

	/**
	 * Get the snapshot collection timestamp.
	 *
	 * @return string|null
	 */
	public function get_snapshot_timestamp(): ?string {
		return $this->snapshot_timestamp;
	}

	/**
	 * Get the snapshot collection duration.
	 *
	 * @return float|null
	 */
	public function get_snapshot_duration(): ?float {
		return $this->snapshot_duration;
	}

	/**
	 * Coerce a value to boolean truthiness.
	 *
	 * @param mixed $value Value to coerce to boolean truthiness.
	 * @return bool
	 */
	private function is_truthy( $value ): bool {
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
}
