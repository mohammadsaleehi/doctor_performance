<?php
/**
 * Admin menu registration for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin;

use WPPerformanceDoctor\Admin\Pages\DashboardPage;
use WPPerformanceDoctor\Admin\Pages\DiagnosticsPage;
use WPPerformanceDoctor\Admin\Pages\HistoryPage;
use WPPerformanceDoctor\Admin\Pages\MonitoringPage;
use WPPerformanceDoctor\Admin\Pages\PerformancePage;
use WPPerformanceDoctor\Admin\Pages\RecommendationsPage;
use WPPerformanceDoctor\Admin\Pages\SettingsPage;
use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RecommendationProvider;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;
use WPPerformanceDoctor\Security\CapabilityManager;

/**
 * Top-level admin menu registration.
 */
final class Menu {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	private const PAGE_SLUG = 'wp-performance-doctor';

	/**
	 * Monitoring service.
	 *
	 * @var Service|null
	 */
	private ?Service $monitoring_service = null;

	/**
	 * Monitoring storage.
	 *
	 * @var Storage|null
	 */
	private ?Storage $storage = null;

	/**
	 * Diagnostic service.
	 *
	 * @var DiagnosticService|null
	 */
	private ?DiagnosticService $diagnostic_service = null;

	/**
	 * Rule registry.
	 *
	 * @var RuleRegistry|null
	 */
	private ?RuleRegistry $rule_registry = null;

	/**
	 * Health score calculator.
	 *
	 * @var HealthScoreCalculator|null
	 */
	private ?HealthScoreCalculator $health_score_calculator = null;

	/**
	 * Recommendation provider.
	 *
	 * @var RecommendationProvider|null
	 */
	private ?RecommendationProvider $recommendation_provider = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set monitoring service and storage.
	 *
	 * @param Service|null $monitoring_service Monitoring service.
	 * @param Storage|null $storage Monitoring storage.
	 * @return self
	 */
	public function set_monitoring( ?Service $monitoring_service = null, ?Storage $storage = null ): self {
		$this->monitoring_service = $monitoring_service;
		$this->storage            = $storage;
		return $this;
	}

	/**
	 * Set diagnostic components.
	 *
	 * @param DiagnosticService|null      $diagnostic_service Diagnostic service.
	 * @param RuleRegistry|null           $rule_registry Rule registry.
	 * @param HealthScoreCalculator|null  $health_score_calculator Health score calculator.
	 * @param RecommendationProvider|null $recommendation_provider Recommendation provider.
	 * @return self
	 */
	public function set_diagnostics(
		?DiagnosticService $diagnostic_service = null,
		?RuleRegistry $rule_registry = null,
		?HealthScoreCalculator $health_score_calculator = null,
		?RecommendationProvider $recommendation_provider = null
	): self {
		$this->diagnostic_service      = $diagnostic_service;
		$this->rule_registry           = $rule_registry;
		$this->health_score_calculator = $health_score_calculator;
		$this->recommendation_provider = $recommendation_provider;
		return $this;
	}

	/**
	 * Register the admin menu and submenu pages.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! ( new CapabilityManager() )->can_manage_options() ) {
			return;
		}

		// Overview page (main dashboard).
		$overview = DashboardPage::get_instance();
		if ( $this->monitoring_service ) {
			$overview->set_service( $this->monitoring_service );
		}
		if ( $this->storage ) {
			$overview->set_storage( $this->storage );
		}
		if ( $this->health_score_calculator ) {
			$overview->set_health_score_calculator( $this->health_score_calculator );
		}
		if ( $this->diagnostic_service ) {
			$overview->set_diagnostic_service( $this->diagnostic_service );
		}
		if ( $this->rule_registry ) {
			$overview->set_rule_registry( $this->rule_registry );
		}
		if ( $this->recommendation_provider ) {
			$overview->set_recommendation_provider( $this->recommendation_provider );
		}

		$page = add_menu_page(
			__( 'Performance Doctor', 'wp-performance-doctor' ),
			__( 'Performance Doctor', 'wp-performance-doctor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $overview, 'render' ),
			'dashicons-chart-area',
			55
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Overview', 'wp-performance-doctor' ),
			__( 'Overview', 'wp-performance-doctor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $overview, 'render' )
		);

		// Monitoring page.
		$monitoring = MonitoringPage::get_instance();
		if ( $this->monitoring_service ) {
			$monitoring->set_service( $this->monitoring_service );
		}
		if ( $this->storage ) {
			$monitoring->set_storage( $this->storage );
		}
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Monitoring', 'wp-performance-doctor' ),
			__( 'Monitoring', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-monitoring',
			array( $monitoring, 'render' )
		);

		// Performance page.
		$performance = PerformancePage::get_instance();
		if ( $this->monitoring_service ) {
			$performance->set_service( $this->monitoring_service );
		}
		if ( $this->storage ) {
			$performance->set_storage( $this->storage );
		}
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Performance', 'wp-performance-doctor' ),
			__( 'Performance', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-performance',
			array( $performance, 'render' )
		);

		// Diagnostics page.
		$diagnostics = DiagnosticsPage::get_instance();
		if ( $this->diagnostic_service ) {
			$diagnostics->set_diagnostic_service( $this->diagnostic_service );
		}
		if ( $this->rule_registry ) {
			$diagnostics->set_rule_registry( $this->rule_registry );
		}
		if ( $this->health_score_calculator ) {
			$diagnostics->set_health_score_calculator( $this->health_score_calculator );
		}
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Diagnostics', 'wp-performance-doctor' ),
			__( 'Diagnostics', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-diagnostics',
			array( $diagnostics, 'render' )
		);

		// Recommendations page.
		$recommendations = RecommendationsPage::get_instance();
		if ( $this->recommendation_provider ) {
			$recommendations->set_recommendation_provider( $this->recommendation_provider );
		}
		if ( $this->storage ) {
			$recommendations->set_storage( $this->storage );
		}
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Recommendations', 'wp-performance-doctor' ),
			__( 'Recommendations', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-recommendations',
			array( $recommendations, 'render' )
		);

		// History page.
		$history = HistoryPage::get_instance();
		if ( $this->storage ) {
			$history->set_storage( $this->storage );
		}
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'History', 'wp-performance-doctor' ),
			__( 'History', 'wp-performance-doctor' ),
			'manage_options',
			'wp-performance-doctor-history',
			array( $history, 'render' )
		);
	}
}
