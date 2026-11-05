<?php
/**
 * Data normalizer for WP Performance Doctor monitoring.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Monitoring;

use WPPerformanceDoctor\Contracts\CollectorInterface;

/**
 * Converts raw collector output into normalized Metric objects.
 *
 * Collectors return raw associative arrays. The Normalizer validates,
 * converts, and tags each value as a Metric with consistent identifiers,
 * units, categories, timestamps, and availability status.
 */
final class Normalizer {

	/**
	 * Source collector name.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * ISO 8601 collection timestamp.
	 *
	 * @var string
	 */
	private $collected_at;

	/**
	 * Measurement context (frontend, admin, rest, ajax, cron).
	 *
	 * @var string
	 */
	private $measurement_context;

	/**
	 * Page URL where measurement was taken (for frontend context).
	 *
	 * @var string
	 */
	private $page_url;

	/**
	 * Constructor.
	 *
	 * @param string $source               Collector/source name.
	 * @param string $collected_at         ISO 8601 timestamp for all metrics.
	 * @param string $measurement_context  Measurement context.
	 * @param string $page_url             Page URL where measurement was taken.
	 */
	public function __construct(
		string $source,
		string $collected_at = '',
		string $measurement_context = '',
		string $page_url = ''
	) {
		$this->source              = $source;
		$this->collected_at        = '' !== $collected_at ? $collected_at : gmdate( 'Y-m-d H:i:s' );
		$this->measurement_context = $measurement_context;
		$this->page_url            = $page_url;
	}

	/**
	 * Normalize a raw collector result into Metric objects.
	 *
	 * @param string                                              $category   Metric category.
	 * @param array<string, mixed>                                $raw  Raw collector data.
	 * @param array<string, array{unit: string, status?: string}> $schema Metric schema defining units and default statuses.
	 * @return Metric[]
	 */
	public function normalize( string $category, array $raw, array $schema = array() ): array {
		$metrics = array();

		foreach ( $raw as $key => $value ) {
			$metric_id = $this->build_metric_id( $category, $key );
			$unit      = $schema[ $key ]['unit'] ?? '';
			$status    = $schema[ $key ]['status'] ?? Metric::STATUS_AVAILABLE;

			if ( null === $value ) {
				$status = Metric::STATUS_UNAVAILABLE;
			}

			$metrics[] = new Metric(
				$metric_id,
				$value,
				$unit,
				$category,
				$status,
				$this->source,
				$this->collected_at,
				array(),
				$this->measurement_context,
				$this->page_url
			);
		}

		return $metrics;
	}

	/**
	 * Add unavailable metrics for keys that were expected but missing.
	 *
	 * @param string               $category   Metric category.
	 * @param string[]             $expected   Expected metric keys.
	 * @param array<string, mixed> $raw  Raw collector data.
	 * @param string               $unit       Default unit.
	 * @return Metric[]
	 */
	public function missing_metrics( string $category, array $expected, array $raw, string $unit = '' ): array {
		$metrics = array();

		foreach ( $expected as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				$metric_id = $this->build_metric_id( $category, $key );
				$metrics[] = Metric::unavailable(
					$metric_id,
					$category,
					$this->source,
					array( 'unit' => $unit )
				);
			}
		}

		return $metrics;
	}

	/**
	 * Build a dot-separated metric identifier.
	 *
	 * @param string $category Category.
	 * @param string $key      Metric key.
	 * @return string
	 */
	private function build_metric_id( string $category, string $key ): string {
		return $category . '.' . $key;
	}

	/**
	 * Get the source name.
	 *
	 * @return string
	 */
	public function get_source(): string {
		return $this->source;
	}
}
