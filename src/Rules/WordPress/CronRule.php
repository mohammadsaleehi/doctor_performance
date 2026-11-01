<?php
/**
 * WordPress cron diagnostic rule.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Rules\WordPress;

use WPPerformanceDoctor\Rules\AbstractRule;
use WPPerformanceDoctor\Diagnostics\DiagnosticContext;
use WPPerformanceDoctor\Diagnostics\RuleResult;
use WPPerformanceDoctor\Rules\RuleCategory;
use WPPerformanceDoctor\Rules\Severity;

/**
 * Rule: Cron disabled or misconfigured.
 *
 * Checks for DISABLE_WP_CRON constant and cron event count.
 */
final class CronRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'wordpress.cron_disabled',
				'title'            => __( 'WordPress Cron May Be Disabled', 'wp-performance-doctor' ),
				'description'      => __( 'WordPress cron appears to be disabled or no cron events are scheduled.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::WORDPRESS,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'wordpress.cron_events_count' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$cron_count = $this->get_metric( $context, 'wordpress.cron_events_count' );

		if ( ! $context->has_metric( 'wordpress.cron_events_count' ) ) {
			return $this->result_unavailable( __( 'Cron events metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $cron_count ) {
			return $this->result_unavailable( __( 'Cron events data is not available.', 'wp-performance-doctor' ) );
		}

		// Check for DISABLE_WP_CRON via environment - can't directly check constant from metrics.
		// We infer from cron event count being 0.
		$count = (int) $cron_count;

		if ( 0 === $count ) {
			return $this->result_warning(
				__( 'No Scheduled Cron Events', 'wp-performance-doctor' ),
				__( 'No WordPress cron events are scheduled. Scheduled tasks may not run.', 'wp-performance-doctor' ),
				array(
					'actual_value'   => '0 events',
					'expected_value' => '> 0 events',
					'unit'           => 'count',
				),
				__( 'Without cron events, scheduled publishing, updates, and maintenance tasks may not execute.', 'wp-performance-doctor' ),
				__( 'Verify that DISABLE_WP_CRON is not set in wp-config.php and that cron is functioning.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Check wp-config.php for define( \'DISABLE_WP_CRON\', true ); and remove it if present.', 'wp-performance-doctor' ),
						__( 'Set up a real system cron job to call wp-cron.php regularly.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'WordPress Cron Is Configured', 'wp-performance-doctor' ),
			sprintf( __( '%d cron events are scheduled.', 'wp-performance-doctor' ), $count ),
			array(
				'actual_value'   => $count . ' events',
				'expected_value' => '> 0 events',
			)
		);
	}
}
