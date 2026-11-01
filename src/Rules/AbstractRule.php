<?php
/**
 * Abstract base class for diagnostic rules.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules;

use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\Confidence;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Base implementation of DiagnosticRuleInterface.
 *
 * Provides default implementations for metadata, configuration,
 * enabled state, and common helper methods. Subclasses must implement
 * evaluate_rule() and declare their metadata via the constructor.
 */
abstract class AbstractRule implements DiagnosticRuleInterface {

	/**
	 * @var string
	 */
	private string $id;

	/**
	 * @var string
	 */
	private string $title;

	/**
	 * @var string
	 */
	private string $description;

	/**
	 * @var string
	 */
	private string $category;

	/**
	 * @var string
	 */
	private string $severity;

	/**
	 * @var string
	 */
	private string $confidence;

	/**
	 * @var string
	 */
	private string $version;

	/**
	 * @var string[]
	 */
	private array $required_metrics;

	/**
	 * @var string
	 */
	private string $cost;

	/**
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array{
	 *     id: string,
	 *     title: string,
	 *     description: string,
	 *     category: string,
	 *     severity: string,
	 *     confidence: string,
	 *     version: string,
	 *     required_metrics?: string[],
	 *     cost?: string,
	 *     enabled?: bool,
	 *     config?: array<string, mixed>
	 * } $metadata Rule metadata.
	 */
	public function __construct( array $metadata ) {
		$this->id               = $metadata['id'];
		$this->title            = $metadata['title'];
		$this->description      = $metadata['description'];
		$this->category         = $metadata['category'];
		$this->severity         = $metadata['severity'];
		$this->confidence       = $metadata['confidence'];
		$this->version          = $metadata['version'] ?? '1.0.0';
		$this->required_metrics = $metadata['required_metrics'] ?? array();
		$this->cost             = $metadata['cost'] ?? 'cheap';
		$this->enabled          = $metadata['enabled'] ?? true;
		$this->config           = $metadata['config'] ?? array();
	}

	public function get_id(): string {
		return $this->id;
	}

	public function get_title(): string {
		return $this->title;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function get_category(): string {
		return $this->category;
	}

	public function get_severity(): string {
		return $this->severity;
	}

	public function get_confidence(): string {
		return $this->confidence;
	}

	public function get_version(): string {
		return $this->version;
	}

	public function get_required_metrics(): array {
		return $this->required_metrics;
	}

	public function get_cost(): string {
		return $this->cost;
	}

	public function is_enabled(): bool {
		return $this->enabled;
	}

	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	public function get_config(): array {
		return $this->config;
	}

	public function set_config( array $config ): void {
		$this->config = $config;
	}

	public function validate_config( array $config ): array {
		return array(
			'valid'  => true,
			'errors' => array(),
		);
	}

	/**
	 * Evaluate the rule. Must be implemented by subclasses.
	 *
	 * @param DiagnosticContext $context Diagnostic context.
	 * @return RuleResult
	 */
	abstract public function evaluate_rule( DiagnosticContext $context ): RuleResult;

	public function evaluate( DiagnosticContext $context ): RuleResult {
		if ( ! $this->enabled ) {
			return new RuleResult(
				array(
					'rule_id'     => $this->id,
					'category'    => $this->category,
					'outcome'     => 'skipped',
					'skipped'     => true,
					'skip_reason' => 'disabled',
				)
			);
		}

		$start = microtime( true );

		try {
			return $this->evaluate_rule( $context );
		} catch ( \Throwable $e ) {
			$duration = microtime( true ) - $start;

			return new RuleResult(
				array(
					'rule_id'  => $this->id,
					'category' => $this->category,
					'outcome'  => 'error',
					'duration' => $duration,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get a metric value from the context by identifier.
	 *
	 * @param DiagnosticContext $context Diagnostic context.
	 * @param string            $identifier Metric identifier (e.g. system.memory_limit).
	 * @return mixed|null The metric value or null if not available.
	 */
	protected function get_metric( DiagnosticContext $context, string $identifier ) {
		return $context->get_metric( $identifier );
	}

	/**
	 * Check if a metric is available in the context.
	 *
	 * @param DiagnosticContext $context Diagnostic context.
	 * @param string            $identifier Metric identifier.
	 * @return bool
	 */
	protected function has_metric( DiagnosticContext $context, string $identifier ): bool {
		return $context->has_metric( $identifier );
	}

	/**
	 * Create a pass result.
	 *
	 * @param string|null               $title Title.
	 * @param string|null               $message Message.
	 * @param array<string, mixed>|null $data Additional data.
	 * @return RuleResult
	 */
	protected function result_pass( ?string $title = null, ?string $message = null, ?array $data = null ): RuleResult {
		return new RuleResult(
			array(
				'rule_id'      => $this->id,
				'rule_version' => $this->version,
				'category'     => $this->category,
				'outcome'      => 'pass',
				'title'        => $title ?? $this->title,
				'description'  => $message ?? $this->description,
				'evidence'     => $data,
				'duration'     => 0.0,
			)
		);
	}

	/**
	 * Create an info result.
	 *
	 * @param string|null               $title Title.
	 * @param string|null               $message Message.
	 * @param array<string, mixed>|null $data Additional data.
	 * @return RuleResult
	 */
	protected function result_info( ?string $title = null, ?string $message = null, ?array $data = null ): RuleResult {
		return new RuleResult(
			array(
				'rule_id'      => $this->id,
				'rule_version' => $this->version,
				'category'     => $this->category,
				'outcome'      => 'info',
				'severity'     => \WPPerformanceDoctor\Rules\Severity::INFO,
				'confidence'   => \WPPerformanceDoctor\Rules\Confidence::HIGH,
				'title'        => $title ?? $this->title,
				'description'  => $message ?? $this->description,
				'evidence'     => $data,
				'fingerprint'  => $this->build_fingerprint( $data ?? array() ),
				'duration'     => 0.0,
			)
		);
	}

	/**
	 * Create a warning result.
	 *
	 * @param string|null          $title Title.
	 * @param string|null          $message Message.
	 * @param array<string, mixed> $evidence Evidence data.
	 * @param string               $impact Impact description.
	 * @param string               $recommendation Recommendation.
	 * @param array<string, mixed> $remediation Remediation details.
	 * @param string|null          $confidence Confidence level.
	 * @return RuleResult
	 */
	protected function result_warning(
		?string $title,
		?string $message,
		array $evidence,
		string $impact,
		string $recommendation,
		array $remediation,
		?string $confidence = null
	): RuleResult {
		return new RuleResult(
			array(
				'rule_id'        => $this->id,
				'rule_version'   => $this->version,
				'category'       => $this->category,
				'outcome'        => 'warning',
				'severity'       => $this->severity,
				'confidence'     => $confidence ?? $this->confidence,
				'title'          => $title ?? $this->title,
				'description'    => $message ?? $this->description,
				'evidence'       => $evidence,
				'impact'         => $impact,
				'recommendation' => $recommendation,
				'remediation'    => $remediation,
				'fingerprint'    => $this->build_fingerprint( $evidence ),
				'duration'       => 0.0,
			)
		);
	}

	/**
	 * Create a fail result.
	 *
	 * @param string|null          $title Title.
	 * @param string|null          $message Message.
	 * @param array<string, mixed> $evidence Evidence data.
	 * @param string               $impact Impact description.
	 * @param string               $recommendation Recommendation.
	 * @param array<string, mixed> $remediation Remediation details.
	 * @param string|null          $confidence Confidence level.
	 * @return RuleResult
	 */
	protected function result_fail(
		?string $title,
		?string $message,
		array $evidence,
		string $impact,
		string $recommendation,
		array $remediation,
		?string $confidence = null
	): RuleResult {
		return new RuleResult(
			array(
				'rule_id'        => $this->id,
				'rule_version'   => $this->version,
				'category'       => $this->category,
				'outcome'        => 'fail',
				'severity'       => $this->severity,
				'confidence'     => $confidence ?? $this->confidence,
				'title'          => $title ?? $this->title,
				'description'    => $message ?? $this->description,
				'evidence'       => $evidence,
				'impact'         => $impact,
				'recommendation' => $recommendation,
				'remediation'    => $remediation,
				'fingerprint'    => $this->build_fingerprint( $evidence ),
				'duration'       => 0.0,
			)
		);
	}

	/**
	 * Create an unavailable result (metric missing).
	 *
	 * @param string $reason Reason for unavailability.
	 * @return RuleResult
	 */
	protected function result_unavailable( string $reason ): RuleResult {
		return new RuleResult(
			array(
				'rule_id'     => $this->id,
				'category'    => $this->category,
				'outcome'     => 'unavailable',
				'skipped'     => true,
				'skip_reason' => $reason,
				'duration'    => 0.0,
			)
		);
	}

	/**
	 * Create an unsupported result.
	 *
	 * @param string $reason Reason the check is unsupported.
	 * @return RuleResult
	 */
	protected function result_unsupported( string $reason ): RuleResult {
		return new RuleResult(
			array(
				'rule_id'     => $this->id,
				'category'    => $this->category,
				'outcome'     => 'unsupported',
				'skipped'     => true,
				'skip_reason' => $reason,
				'duration'    => 0.0,
			)
		);
	}

	/**
	 * Format bytes into human-readable binary units.
	 *
	 * @param mixed $value Raw byte value.
	 * @return string Human-readable size.
	 */
	protected function format_bytes( $value ): string {
		if ( ! is_numeric( $value ) ) {
			return __( 'Not available', 'wp-performance-doctor' );
		}

		$bytes = (int) $value;
		if ( 0 === $bytes ) {
			return '0 B';
		}

		$units = array( 'TB', 'GB', 'MB', 'KB', 'B' );
		$pow   = array( 1099511627776, 1073741824, 1048576, 1024, 1 );

		foreach ( $units as $index => $unit ) {
			if ( $bytes >= $pow[ $index ] ) {
				$number = $bytes / $pow[ $index ];
				if ( 1.0 === $number ) {
					return '1 ' . $unit;
				}
				return rtrim( rtrim( number_format( $number, 2 ), '0' ), '.' ) . ' ' . $unit;
			}
		}

		return $bytes . ' B';
	}

	/**
	 * Format a boolean value with context-aware labels.
	 *
	 * @param mixed  $value Raw boolean-ish value.
	 * @param string $identifier Metric identifier.
	 * @return string
	 */
	protected function format_boolean( $value, string $identifier ): string {
		$truthy = $this->is_truthy( $value );

		if ( str_contains( $identifier, 'available' ) || str_contains( $identifier, '_active' ) ) {
			return $truthy ? __( 'Available', 'wp-performance-doctor' ) : __( 'Unavailable', 'wp-performance-doctor' );
		}

		if ( str_contains( $identifier, 'enabled' ) || str_contains( $identifier, 'active' ) ) {
			return $truthy ? __( 'Enabled', 'wp-performance-doctor' ) : __( 'Disabled', 'wp-performance-doctor' );
		}

		return $truthy ? __( 'Yes', 'wp-performance-doctor' ) : __( 'No', 'wp-performance-doctor' );
	}

	/**
	 * Coerce a value to boolean truthiness.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	protected function is_truthy( $value ): bool {
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

	/**
	 * Decode a JSON-encoded array from a metric value.
	 *
	 * @param mixed $value JSON string or array.
	 * @return array
	 */
	protected function decode_json( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Build a fingerprint from rule id and evidence values.
	 *
	 * @param array $evidence Evidence data.
	 * @return string
	 */
	protected function build_fingerprint( array $evidence ): string {
		$parts = array(
			$this->id,
			(string) ( $evidence['actual_value'] ?? '' ),
			(string) ( $evidence['expected_value'] ?? '' ),
		);

		return hash( 'sha256', implode( '|', $parts ) );
	}
}
