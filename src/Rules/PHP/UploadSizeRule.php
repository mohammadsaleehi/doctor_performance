<?php
/**
 * PHP upload size diagnostic rule.
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
 * Rule: Upload max filesize too restrictive.
 *
 * Compares system.upload_max_filesize against a recommended minimum.
 */
final class UploadSizeRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_upload_max_filesize',
				'title'            => __( 'PHP Upload Max Filesize Is Too Restrictive', 'wp-performance-doctor' ),
				'description'      => __( 'The upload_max_filesize setting may prevent uploading large media files.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::LOW,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.upload_max_filesize' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'recommended_min_mb' => 64,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$size = $this->get_metric( $context, 'system.upload_max_filesize' );
		$raw  = $context->get_metric_record( 'system.upload_max_filesize_raw' );

		if ( ! $context->has_metric( 'system.upload_max_filesize' ) ) {
			return $this->result_unavailable( __( 'Upload max filesize metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $size ) {
			return $this->result_pass(
				__( 'Upload Size Is Unlimited', 'wp-performance-doctor' ),
				__( 'upload_max_filesize is unlimited.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => 'unlimited',
					'expected_value' => '>= 64 MB',
				)
			);
		}

		$config  = $this->get_config();
		$min_mb  = (int) ( $config['recommended_min_mb'] ?? 64 );
		$size_mb = (int) ( $size / ( 1024 * 1024 ) );

		if ( $size_mb < $min_mb ) {
			return $this->result_fail(
				__( 'PHP Upload Max Filesize Is Too Restrictive', 'wp-performance-doctor' ),
				sprintf(
					__( 'upload_max_filesize is %1$d MB, below the recommended %2$d MB.', 'wp-performance-doctor' ),
					$size_mb,
					$min_mb
				),
				array(
					'actual_value'   => $size_mb . ' MB',
					'expected_value' => '>= ' . $min_mb . ' MB',
					'unit'           => 'MB',
					'raw_value'      => $raw['value'] ?? null,
				),
				__( 'Users may be unable to upload large themes, plugins, or media files.', 'wp-performance-doctor' ),
				__( 'Increase upload_max_filesize to at least 64M if your hosting environment permits it.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Edit php.ini or .user.ini and set upload_max_filesize = 64M.', 'wp-performance-doctor' ),
						__( 'Or contact your hosting provider to increase the upload limit.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'easy',
				),
			);
		}

		return $this->result_pass(
			__( 'Upload Max Filesize Is Adequate', 'wp-performance-doctor' ),
			sprintf(
				__( 'upload_max_filesize is %d MB, meeting the recommended minimum.', 'wp-performance-doctor' ),
				$size_mb
			),
			array(
				'actual_value'   => $size_mb . ' MB',
				'expected_value' => '>= ' . $min_mb . ' MB',
			)
		);
	}
}
