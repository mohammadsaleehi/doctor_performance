<?php
/**
 * Disk space diagnostic rule.
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
 * Rule: Extremely low available disk space.
 *
 * Checks system.disk_free_space relative to system.disk_total_space.
 * Does not generate findings when disk metrics are unavailable.
 */
final class DiskSpaceRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.disk_free_space',
				'title'            => __( 'Low Available Disk Space', 'wp-performance-doctor' ),
				'description'      => __( 'Available disk space is critically low, which may cause failures.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::HIGH,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.disk_free_space', 'system.disk_total_space' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'warning_threshold_pct' => 10,
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$free  = $this->get_metric( $context, 'system.disk_free_space' );
		$total = $this->get_metric( $context, 'system.disk_total_space' );

		if ( ! $context->has_metric( 'system.disk_free_space' ) || ! $context->has_metric( 'system.disk_total_space' ) ) {
			return $this->result_unavailable( __( 'Disk space metrics are not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $free || null === $total || (int) $total === 0 ) {
			return $this->result_unavailable( __( 'Disk space data is incomplete or unavailable.', 'wp-performance-doctor' ) );
		}

		$config    = $this->get_config();
		$threshold = (int) ( $config['warning_threshold_pct'] ?? 10 );
		$free_pct  = (int) ( ( (int) $free / (int) $total ) * 100 );
		$free_mb   = (int) ( (int) $free / ( 1024 * 1024 ) );
		$total_mb  = (int) ( (int) $total / ( 1024 * 1024 ) );

		if ( $free_pct <= $threshold ) {
			return $this->result_fail(
				__( 'Low Available Disk Space', 'wp-performance-doctor' ),
				sprintf(
					__( 'Only %1$d MB (%2$d%%) of %3$d MB disk space remains.', 'wp-performance-doctor' ),
					$free_mb,
					$free_pct,
					$total_mb
				),
				array(
					'actual_value'   => $free_mb . ' MB',
					'expected_value' => '> ' . $threshold . '% of total',
					'unit'           => 'MB',
					'usage_percent'  => $free_pct,
				),
				__( 'Critically low disk space can cause database write failures, upload failures, and site outages.', 'wp-performance-doctor' ),
				__( 'Free up disk space or increase storage capacity through your hosting provider.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Remove old backups, logs, and temporary files.', 'wp-performance-doctor' ),
						__( 'Contact your hosting provider to increase storage capacity.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'high',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Disk Space Is Adequate', 'wp-performance-doctor' ),
			sprintf(
				__( '%1$d MB (%2$d%%) of %3$d MB disk space is available.', 'wp-performance-doctor' ),
				$free_mb,
				$free_pct,
				$total_mb
			),
			array(
				'actual_value'   => $free_mb . ' MB',
				'expected_value' => '> ' . $threshold . '% of total',
			)
		);
	}
}
