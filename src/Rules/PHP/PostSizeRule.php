<?php
/**
 * PHP post max size diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\PHP;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: POST max size smaller than upload max filesize.
 *
 * post_max_size should be >= upload_max_filesize for file uploads to work.
 */
final class PostSizeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_post_max_size',
				'title'            => __( 'PHP Post Max Size Is Too Small', 'wp-performance-doctor' ),
				'description'      => __( 'post_max_size may be smaller than upload_max_filesize, preventing file uploads.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.post_max_size', 'system.upload_max_filesize' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$post_size = $this->get_metric( $context, 'system.post_max_size' );
		$upload    = $this->get_metric( $context, 'system.upload_max_filesize' );
		$post_raw  = $context->get_metric_record( 'system.post_max_size_raw' );

		if ( ! $context->has_metric( 'system.post_max_size' ) || ! $context->has_metric( 'system.upload_max_filesize' ) ) {
			return $this->result_unavailable( __( 'Post max size or upload max filesize metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $post_size || null === $upload ) {
			return $this->result_pass(
				__( 'Post Size and Upload Size Are Unlimited', 'wp-performance-doctor' ),
				__( 'Both post_max_size and upload_max_filesize are unlimited.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => 'unlimited',
				)
			);
		}

		if ( (int) $post_size < (int) $upload ) {
			return $this->result_fail(
				__( 'PHP Post Max Size Is Smaller Than Upload Max Filesize', 'wp-performance-doctor' ),
				sprintf(
					__( 'post_max_size is %1$s but upload_max_filesize is %2$s. File uploads larger than the post limit will fail.', 'wp-performance-doctor' ),
					$this->format_bytes( $post_size ),
					$this->format_bytes( $upload )
				),
				array(
					'actual_value'   => $this->format_bytes( $post_size ),
					'expected_value' => '>= ' . $this->format_bytes( $upload ),
					'unit'           => 'bytes',
					'raw_value'      => $post_raw['value'] ?? null,
				),
				__( 'File uploads larger than post_max_size will fail even if upload_max_filesize allows them.', 'wp-performance-doctor' ),
				__( 'Set post_max_size to be equal to or greater than upload_max_filesize.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini or .user.ini and set post_max_size >= upload_max_filesize.', 'wp-performance-doctor' ),
						__( 'Or contact your hosting provider to adjust the post size limit.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'PHP Post Max Size Is Adequate', 'wp-performance-doctor' ),
			sprintf(
				__( 'post_max_size is %1$s, sufficient for upload_max_filesize of %2$s.', 'wp-performance-doctor' ),
				$this->format_bytes( $post_size ),
				$this->format_bytes( $upload )
			),
			array(
				'actual_value'   => $this->format_bytes( $post_size ),
				'expected_value' => '>= ' . $this->format_bytes( $upload ),
			)
		);
	}
}
