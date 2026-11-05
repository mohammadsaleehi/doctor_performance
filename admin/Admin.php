<?php
/**
 * Admin integration for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin;

use WPPerformanceDoctor\Core\Plugin;
use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RecommendationProvider;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * Admin integration for WP Performance Doctor.
 *
 * Presentation and WordPress admin integration only.
 */
final class Admin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Initialize admin integration.
	 *
	 * @param Service|null                $monitoring_service Monitoring service.
	 * @param Storage|null                $storage Monitoring storage.
	 * @param DiagnosticService|null      $diagnostic_service Diagnostic service.
	 * @param RuleRegistry|null           $rule_registry Rule registry.
	 * @param HealthScoreCalculator|null  $health_score_calculator Health score calculator.
	 * @param RecommendationProvider|null $recommendation_provider Recommendation provider.
	 * @return void
	 */
	public function init(
		?Service $monitoring_service = null,
		?Storage $storage = null,
		?DiagnosticService $diagnostic_service = null,
		?RuleRegistry $rule_registry = null,
		?HealthScoreCalculator $health_score_calculator = null,
		?RecommendationProvider $recommendation_provider = null
	): void {
		$this->monitoring_service      = $monitoring_service;
		$this->storage                 = $storage;
		$this->diagnostic_service      = $diagnostic_service;
		$this->rule_registry           = $rule_registry;
		$this->health_score_calculator = $health_score_calculator;
		$this->recommendation_provider = $recommendation_provider;

		// Wire monitoring service/storage into Menu so pages receive them.
		Menu::get_instance()->set_monitoring( $monitoring_service, $storage );
		Menu::get_instance()->set_diagnostics( $diagnostic_service, $rule_registry, $health_score_calculator, $recommendation_provider );

		add_action( 'admin_menu', array( Menu::get_instance(), 'register' ) );
		add_action( 'admin_init', array( $this, 'register_assets' ) );

		// Register settings.
		if ( class_exists( \WPPerformanceDoctor\Admin\Pages\SettingsPage::class ) ) {
			\WPPerformanceDoctor\Admin\Pages\SettingsPage::get_instance()->register();
		}
	}

	/**
	 * Get monitoring service.
	 *
	 * @return Service|null
	 */
	public function monitoring_service(): ?Service {
		return $this->monitoring_service;
	}

	/**
	 * Get monitoring storage.
	 *
	 * @return Storage|null
	 */
	public function storage(): ?Storage {
		return $this->storage;
	}

	/**
	 * Get diagnostic service.
	 *
	 * @return DiagnosticService|null
	 */
	public function diagnostic_service(): ?DiagnosticService {
		return $this->diagnostic_service;
	}

	/**
	 * Get rule registry.
	 *
	 * @return RuleRegistry|null
	 */
	public function rule_registry(): ?RuleRegistry {
		return $this->rule_registry;
	}

	/**
	 * Register admin assets.
	 *
	 * Assets are loaded on dashboard and diagnostics pages.
	 * File modification times are used as version strings so browser caches
	 * are invalidated when the assets change.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Only load assets on plugin admin pages.
		$plugin_pages = array(
			'wp-performance-doctor',
			'wp-performance-doctor-monitoring',
			'wp-performance-doctor-performance',
			'wp-performance-doctor-diagnostics',
			'wp-performance-doctor-recommendations',
			'wp-performance-doctor-history',
			'wp-performance-doctor-settings',
		);

		if ( ! in_array( $page, $plugin_pages, true ) ) {
			return;
		}

		$css_path = \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/assets/css/admin.css';
		$js_path  = \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/assets/js/admin.js';

		$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : \WPD_PERFORMANCE_DOCTOR_VERSION;
		$js_version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : \WPD_PERFORMANCE_DOCTOR_VERSION;

		wp_enqueue_style(
			'wp-performance-doctor-admin',
			\WPD_PERFORMANCE_DOCTOR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$css_version
		);

		wp_enqueue_script(
			'wp-performance-doctor-admin',
			\WPD_PERFORMANCE_DOCTOR_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			$js_version,
			true
		);

		// Localize JavaScript configuration for dashboard and diagnostics pages.
		$collect_nonce = wp_create_nonce( 'wpd_collect' );
		$rest_nonce    = wp_create_nonce( 'wp_rest' );

		$dashboard_config = array(
			'restUrl'     => \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/monitoring/collect' ) ),
			'nonce'       => $collect_nonce,
			'wpRestNonce' => $rest_nonce,
			'strings'     => array(
				'label'          => __( 'Collect Now', 'wp-performance-doctor' ),
				'collecting'     => __( 'Collecting…', 'wp-performance-doctor' ),
				'success'        => __( 'Collection complete.', 'wp-performance-doctor' ),
				'error'          => __( 'Collection failed.', 'wp-performance-doctor' ),
				'runDiagnostics' => __( 'Run Diagnostics', 'wp-performance-doctor' ),
			),
		);

		$diagnostics_config = array(
			'restUrl'     => \esc_url_raw( \rest_url( \WPPerformanceDoctor\REST\RestController::NAMESPACE . '/diagnostics/run' ) ),
			'nonce'       => $collect_nonce,
			'wpRestNonce' => $rest_nonce,
			'strings'     => array(
				'label'    => __( 'Run Diagnostics', 'wp-performance-doctor' ),
				'running'  => __( 'Running diagnostics…', 'wp-performance-doctor' ),
				'complete' => __( 'Diagnostics complete.', 'wp-performance-doctor' ),
				'error'    => __( 'Diagnostics failed.', 'wp-performance-doctor' ),
			),
		);

		wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdDashboard',
			$dashboard_config
		);

		wp_localize_script(
			'wp-performance-doctor-admin',
			'wpdDiagnostics',
			$diagnostics_config
		);
	}
}

/**
 * Inline SVG icons for WP Performance Doctor admin UI.
 *
 * @param string $name Icon name.
 * @return string SVG markup.
 */
function wpd_icon( $name ): string {
		$icons = array(
			'server'    => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><rect x="2" y="3" width="20" height="2" rx="2"/><line x1="6" y1="21" x2="6" y2="23"/><line x1="10" y1="21" x2="10" y2="23"/><line x1="14" y1="21" x2="14" y2="23"/><line x1="18" y1="21" x2="18" y2="23"/></svg>',
			'wordpress' => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
			'database'  => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.6 5.4 3 10 3s10-1.4 10-3V5c0-1.6-5.4-3-10-3S12 3.4 12 5z"/><path d="M3 5v14c0 1.6 5.4 3 10 3s10-1.4 10-3V5c0-1.6-5.4-3-10-3S12 3.4 12 5z"/></svg>',
			'cache'     => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15A1.65 1.65 0 0 0 20.5 13H18v-2h1.5c0.32 0 .62-.12.8-.34M4.6 15A1.65 1.65 0 0 1 3.5 13H6v-2H3.5c0.32 0 .62.12.8.34M15.8 9H16v2h-1.5c-0.32 0-.62.12-.8.34M8.2 9H8v2h1.5c0.32 0 .62-.12.8-.34"/></svg>',
			'activity'  => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
			'heart'     => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.49a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.18a5.5 5.5 0 0 0-7.78 7.78c1.16 1.16 2.72 1.71 4.29 1.71s3.13-.55 4.29-1.71l6.61-6.61a5.5 5.5 0 0 0 .1-7.78z"/></svg>',
			'history'   => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7v8a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2z"/><path d="M8 3v1a4 4 0 0 0 4 4h6a2 2 0 0 0 2-2V3z"/></svg>',
			'settings'  => '<svg class="wpd-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15A1.65 1.65 0 0 0 20.5 13H18v-2h1.5c0.32 0 .62-.12.8-.34M4.6 15A1.65 1.65 0 0 1 3.5 13H6v-2H3.5c0.32 0 .62.12.8.34M15.8 9H16v2h-1.5c-0.32 0-.62.12-.8.34M8.2 9H8v2h1.5c0.32 0 .62-.12.8-.34"/></svg>',
		);

		return $icons[ $name ] ?? $icons['activity'];
}

/**
 * Echo a value already escaped by MetricFormatter.
 *
 * MetricFormatter::format() already applies esc_html() to simple values and
 * builds structured HTML from esc_html()'d pieces, so this output is safe
 * to render without further escaping.
 *
 * @param string $html Pre-escaped HTML string.
 * @return void
 */
if ( ! function_exists( 'wpd_formatted_echo' ) ) :
	function wpd_formatted_echo( $html ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput -- Value is pre-escaped by MetricFormatter.
		echo $html;
	}
endif;
