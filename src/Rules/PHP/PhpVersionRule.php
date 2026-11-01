<?php
/**
 * PHP version diagnostic rule.
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
 * Rule: Unsupported or outdated PHP runtime.
 *
 * Checks system.php_version against known supported versions.
 * WordPress officially supports PHP 7.4+ but recommends 8.0+.
 */
final class PhpVersionRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_version',
				'title'            => __( 'Outdated PHP Version', 'wp-performance-doctor' ),
				'description'      => __( 'The installed PHP version is outdated and may no longer receive security updates.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::MEDIUM,
				'confidence'       => 'high',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.php_version' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'min_recommended' => '8.0',
					'min_supported'   => '7.4',
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$version = $this->get_metric( $context, 'system.php_version' );

		if ( ! $context->has_metric( 'system.php_version' ) ) {
			return $this->result_unavailable( __( 'PHP version metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $version || '' === $version ) {
			return $this->result_unavailable( __( 'PHP version could not be determined.', 'wp-performance-doctor' ) );
		}

		$version_str = (string) $version;
		$parsed      = $this->parse_version( $version_str );

		if ( null === $parsed ) {
			return $this->result_info(
				__( 'PHP Version Could Not Be Parsed', 'wp-performance-doctor' ),
				sprintf( __( 'PHP version string "%s" is not in a recognized format.', 'wp-performance-doctor' ), $version_str ),
				array(
					'actual_value'   => $version_str,
					'expected_value' => 'X.Y.Z format',
				)
			);
		}

		$config  = $this->get_config();
		$min_rec = $this->parse_version( $config['min_recommended'] ?? '8.0' );
		$min_sup = $this->parse_version( $config['min_supported'] ?? '7.4' );

		// Below minimum supported (EOL).
		if ( $this->version_compare_arrays( $parsed, $min_sup ) < 0 ) {
			return $this->result_fail(
				__( 'PHP Version Is End-of-Life', 'wp-performance-doctor' ),
				sprintf(
					__( 'PHP %1$s is end-of-life and no longer receives security updates. Upgrade to PHP %2$s or higher.', 'wp-performance-doctor' ),
					$version_str,
					$config['min_recommended'] ?? '8.0'
				),
				array(
					'actual_value'   => $version_str,
					'expected_value' => '>= ' . ( $config['min_recommended'] ?? '8.0' ),
					'unit'           => 'string',
				),
				__( 'Running EOL PHP exposes the site to security vulnerabilities and incompatibility with modern WordPress versions.', 'wp-performance-doctor' ),
				__( 'Upgrade PHP to a supported version through your hosting provider.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Contact your hosting provider to upgrade PHP to version 8.0 or higher.', 'wp-performance-doctor' ),
						__( 'Test the site on a staging environment before upgrading production.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'medium',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		// Below recommended but still supported.
		if ( $this->version_compare_arrays( $parsed, $min_rec ) < 0 ) {
			return $this->result_warning(
				__( 'PHP Version Is Below Recommended', 'wp-performance-doctor' ),
				sprintf(
					__( 'PHP %1$s is supported but version %2$s or higher is recommended for better performance and security.', 'wp-performance-doctor' ),
					$version_str,
					$config['min_recommended'] ?? '8.0'
				),
				array(
					'actual_value'   => $version_str,
					'expected_value' => '>= ' . ( $config['min_recommended'] ?? '8.0' ),
					'unit'           => 'string',
				),
				__( 'Newer PHP versions offer better performance and security features.', 'wp-performance-doctor' ),
				__( 'Consider upgrading PHP to version ' . ( $config['min_recommended'] ?? '8.0' ) . ' or higher.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						__( 'Contact your hosting provider to upgrade PHP.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'PHP Version Is Supported', 'wp-performance-doctor' ),
			sprintf( __( 'PHP %s meets the recommended minimum version.', 'wp-performance-doctor' ), $version_str ),
			array(
				'actual_value'   => $version_str,
				'expected_value' => '>= ' . ( $config['min_recommended'] ?? '8.0' ),
			)
		);
	}

	/**
	 * Parse a version string into an array of integers.
	 *
	 * @param string $version Version string (e.g. "8.1.2").
	 * @return array{int, int, int}|null
	 */
	private function parse_version( string $version ): ?array {
		$parts = explode( '.', $version );
		if ( count( $parts ) < 2 ) {
			return null;
		}
		return array(
			(int) $parts[0],
			(int) ( $parts[1] ?? 0 ),
			(int) ( $parts[2] ?? 0 ),
		);
	}

	/**
	 * Compare two version arrays.
	 *
	 * @param array{int, int, int} $a
	 * @param array{int, int, int} $b
	 * @return int -1, 0, or 1.
	 */
	private function version_compare_arrays( array $a, array $b ): int {
		for ( $i = 0; $i < 3; $i++ ) {
			if ( $a[ $i ] < $b[ $i ] ) {
				return -1;
			}
			if ( $a[ $i ] > $b[ $i ] ) {
				return 1;
			}
		}
		return 0;
	}
}
