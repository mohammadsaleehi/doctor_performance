<?php
/**
 * PHP extensions diagnostic rule.
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
 * Rule: Missing PHP extensions required by WordPress or components.
 *
 * Checks system.php_extensions against a list of recommended extensions.
 */
final class PhpExtensionsRule extends AbstractRule {

	public function __construct() {
		parent::__construct(
			array(
				'id'               => 'system.php_extensions',
				'title'            => __( 'Missing Recommended PHP Extensions', 'wp-performance-doctor' ),
				'description'      => __( 'Some PHP extensions recommended for WordPress are not loaded.', 'wp-performance-doctor' ),
				'category'         => RuleCategory::SYSTEM,
				'severity'         => Severity::LOW,
				'confidence'       => 'medium',
				'version'          => '1.0.0',
				'required_metrics' => array( 'system.php_extensions' ),
				'cost'             => 'cheap',
				'enabled'          => true,
				'config'           => array(
					'recommended' => array( 'opcache', 'mbstring', 'xml', 'mysqli', 'curl', 'zlib' ),
				),
			)
		);
	}

	public function evaluate_rule( DiagnosticContext $context ): RuleResult {
		$extensions = $this->get_metric( $context, 'system.php_extensions' );

		if ( ! $context->has_metric( 'system.php_extensions' ) ) {
			return $this->result_unavailable( __( 'PHP extensions metric is not available.', 'wp-performance-doctor' ) );
		}

		if ( null === $extensions ) {
			return $this->result_unavailable( __( 'PHP extensions data is not available.', 'wp-performance-doctor' ) );
		}

		$loaded = $this->decode_json( $extensions );
		if ( empty( $loaded ) ) {
			return $this->result_unavailable( __( 'No PHP extensions were detected.', 'wp-performance-doctor' ) );
		}

		$config      = $this->get_config();
		$recommended = $config['recommended'] ?? array( 'opcache', 'mbstring', 'xml', 'mysqli', 'curl', 'zlib' );
		$missing     = array();

		foreach ( $recommended as $ext ) {
			if ( ! in_array( $ext, $loaded, true ) ) {
				$missing[] = $ext;
			}
		}

		if ( ! empty( $missing ) ) {
			return $this->result_warning(
				__( 'Missing Recommended PHP Extensions', 'wp-performance-doctor' ),
				sprintf(
					__( 'The following extensions are not loaded: %s.', 'wp-performance-doctor' ),
					implode( ', ', $missing )
				),
				array(
					'actual_value'   => implode( ', ', array_values( $loaded ) ),
					'expected_value' => implode( ', ', $recommended ),
					'unit'           => 'string',
					'missing'        => $missing,
				),
				__( 'Missing extensions may reduce performance or prevent some WordPress features from working.', 'wp-performance-doctor' ),
				__( 'Install the missing PHP extensions through your hosting provider.', 'wp-performance-doctor' ),
				array(
					'steps'                  => array(
						sprintf(
							__( 'Install the missing extensions: %s.', 'wp-performance-doctor' ),
							implode( ', ', $missing )
						),
						__( 'Restart the web server or PHP-FPM after installing extensions.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
			);
		}

		return $this->result_pass(
			__( 'Recommended PHP Extensions Are Loaded', 'wp-performance-doctor' ),
			__( 'All recommended PHP extensions are loaded.', 'wp-performance-doctor' ),
			array(
				'actual_value'   => implode( ', ', array_values( $loaded ) ),
				'expected_value' => implode( ', ', $recommended ),
			)
		);
	}
}
