<?php
/**
 * Metric data model for WP Performance Doctor monitoring.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Monitoring;

/**
 * A single normalized metric collected from a subsystem.
 *
 * Represents a measurement with value, unit, category, status, and metadata.
 * Statuses: available, unavailable, unsupported, error.
 */
final class Metric {

	public const STATUS_AVAILABLE   = 'available';
	public const STATUS_UNAVAILABLE = 'unavailable';
	public const STATUS_UNSUPPORTED = 'unsupported';
	public const STATUS_ERROR       = 'error';

	public const CATEGORY_SYSTEM    = 'system';
	public const CATEGORY_WORDPRESS = 'wordpress';
	public const CATEGORY_DATABASE  = 'database';
	public const CATEGORY_CACHE     = 'cache';
	public const CATEGORY_PHP       = 'php';
	public const CATEGORY_FRONTEND  = 'frontend';

	/**
	 * Metric identifier (e.g. php.memory_limit).
	 *
	 * @var string
	 */
	public string $metric;

	/**
	 * Metric value.
	 *
	 * @var mixed
	 */
	public mixed $value;

	/**
	 * Unit of measurement (e.g. bytes, seconds).
	 *
	 * @var string
	 */
	public string $unit;

	/**
	 * Metric category (e.g. system, WordPress).
	 *
	 * @var string
	 */
	public string $category;

	/**
	 * Availability status (available, unavailable, unsupported, error).
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Data source / collector name.
	 *
	 * @var string
	 */
	public string $source;

	/**
	 * ISO 8601 collection timestamp.
	 *
	 * @var string
	 */
	public string $collected_at;

	/**
	 * Optional additional metadata.
	 *
	 * @var array
	 */
	public array $metadata;

	/**
	 * Measurement context (frontend, admin, rest, ajax, cron).
	 *
	 * @var string
	 */
	public string $measurement_context;

	/**
	 * Page URL where the measurement was taken (for frontend context).
	 *
	 * @var string
	 */
	public string $page_url;

	/**
	 * Constructor.
	 *
	 * @param string $metric           Metric identifier (e.g. php.memory_limit).
	 * @param mixed  $value            Metric value.
	 * @param string $unit             Unit of measurement (e.g. bytes, seconds).
	 * @param string $category         Metric category.
	 * @param string $status           Availability status.
	 * @param string $source           Data source (collector name).
	 * @param string $collected_at     ISO 8601 timestamp.
	 * @param array  $metadata         Optional additional metadata.
	 * @param string $measurement_context Measurement context (frontend, admin, rest, ajax, cron).
	 * @param string $page_url         Page URL where measurement was taken (frontend context).
	 */
	public function __construct(
		string $metric,
		mixed $value,
		string $unit,
		string $category,
		string $status = self::STATUS_AVAILABLE,
		string $source = '',
		string $collected_at = '',
		array $metadata = array(),
		string $measurement_context = '',
		string $page_url = ''
	) {
		$this->metric              = $metric;
		$this->value               = $value;
		$this->unit                = $unit;
		$this->category            = $category;
		$this->status              = $status;
		$this->source              = $source;
		$this->collected_at        = '' !== $collected_at ? $collected_at : gmdate( 'Y-m-d H:i:s' );
		$this->metadata            = $metadata;
		$this->measurement_context = $measurement_context;
		$this->page_url            = $page_url;
	}

	/**
	 * Convert the metric to an array representation.
	 *
	 * @return array{
	 *     metric: string,
	 *     value: mixed,
	 *     unit: string,
	 *     category: string,
	 *     status: string,
	 *     source: string,
	 *     collected_at: string,
	 *     metadata: array,
	 *     measurement_context: string,
	 *     page_url: string
	 * }
	 */
	public function to_array(): array {
		return array(
			'metric'               => $this->metric,
			'value'                => $this->value,
			'unit'                 => $this->unit,
			'category'             => $this->category,
			'status'               => $this->status,
			'source'               => $this->source,
			'collected_at'         => $this->collected_at,
			'metadata'             => $this->metadata,
			'measurement_context'  => $this->measurement_context,
			'page_url'             => $this->page_url,
		);
	}

	/**
	 * Create an unavailable metric.
	 *
	 * @param string $metric  Metric identifier.
	 * @param string $category Category.
	 * @param string $source  Data source.
	 * @param array  $metadata Optional metadata.
	 * @return self
	 */
	public static function unavailable(
		string $metric,
		string $category,
		string $source = '',
		array $metadata = array()
	): self {
		return new self(
			$metric,
			null,
			'',
			$category,
			self::STATUS_UNAVAILABLE,
			$source,
			'',
			$metadata
		);
	}

	/**
	 * Create an unsupported metric.
	 *
	 * @param string $metric  Metric identifier.
	 * @param string $category Category.
	 * @param string $source  Data source.
	 * @param array  $metadata Optional metadata.
	 * @return self
	 */
	public static function unsupported(
		string $metric,
		string $category,
		string $source = '',
		array $metadata = array()
	): self {
		return new self(
			$metric,
			null,
			'',
			$category,
			self::STATUS_UNSUPPORTED,
			$source,
			'',
			$metadata
		);
	}

	/**
	 * Create an error metric.
	 *
	 * @param string $metric  Metric identifier.
	 * @param string $category Category.
	 * @param string $source  Data source.
	 * @param string $message  Error message.
	 * @return self
	 */
	public static function error(
		string $metric,
		string $category,
		string $source = '',
		string $message = ''
	): self {
		return new self(
			$metric,
			null,
			'',
			$category,
			self::STATUS_ERROR,
			$source,
			'',
			array( 'error' => $message )
		);
	}

	/**
	 * Check if the metric is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return self::STATUS_AVAILABLE === $this->status;
	}
}
