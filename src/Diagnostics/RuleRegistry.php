<?php
/**
 * Rule registry with metadata, enable/disable, and configuration support.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Diagnostics;

use WPPerformanceDoctor\Rules\DiagnosticRuleInterface;

/**
 * Registry of diagnostic rules with support for rule metadata,
 * enable/disable state, and per-rule configuration.
 *
 * Rules can be registered directly or discovered. The registry supports
 * looking up rules by ID, category, and enabled state.
 */
final class RuleRegistry {

	/**
	 * @var \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	private array $rules = array();

	/**
	 * Register a rule.
	 *
	 * @param DiagnosticRuleInterface $rule Rule instance.
	 * @return void
	 */
	public function register( DiagnosticRuleInterface $rule ): void {
		$this->rules[ $rule->get_id() ] = $rule;
	}

	/**
	 * Check whether a rule ID is registered.
	 *
	 * @param string $id Rule ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->rules );
	}

	/**
	 * Get a registered rule by ID.
	 *
	 * @param string $id Rule ID.
	 * @return DiagnosticRuleInterface|null
	 */
	public function get( string $id ): ?DiagnosticRuleInterface {
		return $this->rules[ $id ] ?? null;
	}

	/**
	 * Get all registered rules.
	 *
	 * @return \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	public function get_rules(): array {
		return $this->rules;
	}

	/**
	 * Get rules filtered by category.
	 *
	 * @param string $category Category.
	 * @return \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	public function get_by_category( string $category ): array {
		$filtered = array();
		foreach ( $this->rules as $rule ) {
			if ( $rule->get_category() === $category ) {
				$filtered[ $rule->get_id() ] = $rule;
			}
		}
		return $filtered;
	}

	/**
	 * Get only enabled rules.
	 *
	 * @return \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	public function get_enabled(): array {
		$enabled = array();
		foreach ( $this->rules as $rule ) {
			if ( $rule->is_enabled() ) {
				$enabled[ $rule->get_id() ] = $rule;
			}
		}
		return $enabled;
	}

	/**
	 * Get only disabled rules.
	 *
	 * @return \WPPerformanceDoctor\Rules\DiagnosticRuleInterface[]
	 */
	public function get_disabled(): array {
		$disabled = array();
		foreach ( $this->rules as $rule ) {
			if ( ! $rule->is_enabled() ) {
				$disabled[ $rule->get_id() ] = $rule;
			}
		}
		return $disabled;
	}

	/**
	 * Enable a rule by ID.
	 *
	 * @param string $id Rule ID.
	 * @return bool True if the rule was found and enabled.
	 */
	public function enable( string $id ): bool {
		$rule = $this->get( $id );
		if ( null === $rule ) {
			return false;
		}
		$rule->set_enabled( true );
		return true;
	}

	/**
	 * Disable a rule by ID.
	 *
	 * @param string $id Rule ID.
	 * @return bool True if the rule was found and disabled.
	 */
	public function disable( string $id ): bool {
		$rule = $this->get( $id );
		if ( null === $rule ) {
			return false;
		}
		$rule->set_enabled( false );
		return true;
	}

	/**
	 * Set configuration for a rule by ID.
	 *
	 * @param string               $id Rule ID.
	 * @param array<string, mixed> $config Configuration values.
	 * @return bool True if the rule was found and configured.
	 */
	public function set_config( string $id, array $config ): bool {
		$rule = $this->get( $id );
		if ( null === $rule ) {
			return false;
		}
		$validation = $rule->validate_config( $config );
		if ( ! $validation['valid'] ) {
			return false;
		}
		$rule->set_config( $config );
		return true;
	}

	/**
	 * Get all rule metadata for admin/UI consumption.
	 *
	 * @return array<string, array>
	 */
	public function get_metadata(): array {
		$metadata = array();
		foreach ( $this->rules as $id => $rule ) {
			$metadata[ $id ] = array(
				'id'               => $rule->get_id(),
				'title'            => $rule->get_title(),
				'description'      => $rule->get_description(),
				'category'         => $rule->get_category(),
				'severity'         => $rule->get_severity(),
				'confidence'       => $rule->get_confidence(),
				'version'          => $rule->get_version(),
				'required_metrics' => $rule->get_required_metrics(),
				'cost'             => $rule->get_cost(),
				'enabled'          => $rule->is_enabled(),
				'config'           => $rule->get_config(),
			);
		}
		return $metadata;
	}

	/**
	 * Clear all registered rules.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->rules = array();
	}
}
