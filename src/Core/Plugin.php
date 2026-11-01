<?php
/**
 * Main application bootstrap for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Core;

use WPPerformanceDoctor\Admin\Admin;
use WPPerformanceDoctor\Collectors\PhpProfilerCollector;
use WPPerformanceDoctor\Diagnostics\DiagnosticEngine;
use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RecommendationProvider;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Rules\Cache\CachePluginRule;
use WPPerformanceDoctor\Rules\Cache\MemcachedRule;
use WPPerformanceDoctor\Rules\Cache\ObjectCacheRule;
use WPPerformanceDoctor\Rules\Cache\OpcacheCacheRule;
use WPPerformanceDoctor\Rules\Cache\PageCacheRule;
use WPPerformanceDoctor\Rules\Cache\RedisRule;
use WPPerformanceDoctor\Rules\Database\AutoloadedOptionsCountRule;
use WPPerformanceDoctor\Rules\Database\AutoloadedOptionsSizeRule;
use WPPerformanceDoctor\Rules\Database\DatabaseCompatibilityRule;
use WPPerformanceDoctor\Rules\Database\LargeTablesRule;
use WPPerformanceDoctor\Rules\Database\RevisionsRule;
use WPPerformanceDoctor\Rules\Database\TableCountRule;
use WPPerformanceDoctor\Rules\Database\TableEngineRule;
use WPPerformanceDoctor\Rules\Database\TransientCountRule;
use WPPerformanceDoctor\Rules\DiagnosticRuleInterface;
use WPPerformanceDoctor\Rules\Frontend\AssetCountRule;
use WPPerformanceDoctor\Rules\Frontend\BlockingScriptsRule;
use WPPerformanceDoctor\Rules\Frontend\ExcessiveScriptsRule;
use WPPerformanceDoctor\Rules\Frontend\ExcessiveStylesRule;
use WPPerformanceDoctor\Rules\Frontend\ExternalAssetsRule;
use WPPerformanceDoctor\Rules\PHP\DiskSpaceRule;
use WPPerformanceDoctor\Rules\PHP\ExecutionTimeRule;
use WPPerformanceDoctor\Rules\PHP\LoadAverageRule;
use WPPerformanceDoctor\Rules\PHP\MemoryLimitRule;
use WPPerformanceDoctor\Rules\PHP\MemoryUsageRule;
use WPPerformanceDoctor\Rules\PHP\OpcacheRule;
use WPPerformanceDoctor\Rules\PHP\PhpExtensionsRule;
use WPPerformanceDoctor\Rules\PHP\PhpVersionRule;
use WPPerformanceDoctor\Rules\PHP\PostSizeRule;
use WPPerformanceDoctor\Rules\PHP\UploadSizeRule;
use WPPerformanceDoctor\Rules\PluginTheme\ActiveThemeRule;
use WPPerformanceDoctor\Rules\PluginTheme\InactivePluginsRule;
use WPPerformanceDoctor\Rules\PluginTheme\MissingVersionRule;
use WPPerformanceDoctor\Rules\PluginTheme\PluginCountRule;
use WPPerformanceDoctor\Rules\PluginTheme\PluginUpdateRule;
use WPPerformanceDoctor\Rules\Profiler\HighMemoryRule;
use WPPerformanceDoctor\Rules\Profiler\HighMemoryUseRule;
use WPPerformanceDoctor\Rules\Profiler\RequestTypeRule;
use WPPerformanceDoctor\Rules\Profiler\SampleCountRule;
use WPPerformanceDoctor\Rules\Profiler\SlowRequestsRule;
use WPPerformanceDoctor\Rules\WordPress\CoreUpdateRule;
use WPPerformanceDoctor\Rules\WordPress\CronRule;
use WPPerformanceDoctor\Rules\WordPress\DebugDisplayRule;
use WPPerformanceDoctor\Rules\WordPress\EnvironmentRule;
use WPPerformanceDoctor\Rules\WordPress\MultisiteRule;
use WPPerformanceDoctor\Rules\WordPress\PermalinkRule;
use WPPerformanceDoctor\Rules\WordPress\SearchVisibilityRule;
use WPPerformanceDoctor\Rules\WordPress\WpMaxMemoryLimitRule;
use WPPerformanceDoctor\Rules\WordPress\WpMemoryLimitRule;
use WPPerformanceDoctor\Security\CapabilityManager;
use WPPerformanceDoctor\Security\NonceManager;

	/**
	 * Main application bootstrap.
	 *
	 * Owns the dependency injection container and wires plugin components together.
	 * Core stays independent of the admin UI.
	 */
final class Plugin {

	/**
	 * Diagnostic cron hook name.
	 */
	public const DIAGNOSTIC_CRON_HOOK = 'wpd_diagnostic_evaluate';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Monitoring service.
	 *
	 * @var \WPPerformanceDoctor\Monitoring\Service|null
	 */
	private ?\WPPerformanceDoctor\Monitoring\Service $monitoring_service = null;

	/**
	 * Monitoring storage.
	 *
	 * @var \WPPerformanceDoctor\Monitoring\Storage|null
	 */
	private ?\WPPerformanceDoctor\Monitoring\Storage $storage = null;

	/**
	 * Diagnostic rule registry.
	 *
	 * @var RuleRegistry|null
	 */
	private ?RuleRegistry $rule_registry = null;

	/**
	 * Diagnostic engine.
	 *
	 * @var DiagnosticEngine|null
	 */
	private ?DiagnosticEngine $diagnostic_engine = null;

	/**
	 * Diagnostic service.
	 *
	 * @var DiagnosticService|null
	 */
	private ?DiagnosticService $diagnostic_service = null;

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
	private function __construct() {
		$this->container = new Container();
		$this->container->set( Container::class, $this->container );
		$this->container->set( self::class, $this );

		$this->storage            = new \WPPerformanceDoctor\Monitoring\Storage( WPD_PERFORMANCE_DOCTOR_VERSION );
		$enabled                  = function_exists( 'get_option' ) ? (bool) get_option( 'wpd_monitoring_enabled', true ) : true;
		$interval                 = function_exists( 'get_option' ) ? (int) get_option( 'wpd_monitoring_interval', 3600 ) : 3600;
		$sample_rate              = function_exists( 'get_option' ) ? (float) get_option( 'wpd_profiler_sample_rate', 0.1 ) : 0.1;
		$this->monitoring_service = new \WPPerformanceDoctor\Monitoring\Service(
			$this->storage,
			$enabled,
			$interval,
			$sample_rate
		);

		// Initialize diagnostic components.
		$this->rule_registry = new RuleRegistry();
		$this->register_builtin_rules( $this->rule_registry );

		$this->diagnostic_engine = new DiagnosticEngine( $this->rule_registry );

		$this->health_score_calculator = new HealthScoreCalculator();
		$this->recommendation_provider = new RecommendationProvider();

		$this->diagnostic_service = new DiagnosticService(
			$this->rule_registry,
			$this->diagnostic_engine,
			$this->storage,
			$this->monitoring_service
		);
		$this->diagnostic_service->set_health_score_calculator( $this->health_score_calculator );
		$this->diagnostic_service->set_recommendation_provider( $this->recommendation_provider );
	}

	/**
	 * Register all built-in diagnostic rules.
	 *
	 * @param RuleRegistry $registry Rule registry.
	 * @return void
	 */
	private function register_builtin_rules( RuleRegistry $registry ): void {
		$rules = array(
			// PHP & Server.
			new MemoryLimitRule(),
			new MemoryUsageRule(),
			new ExecutionTimeRule(),
			new UploadSizeRule(),
			new PostSizeRule(),
			new OpcacheRule(),
			new PhpVersionRule(),
			new PhpExtensionsRule(),
			new DiskSpaceRule(),
			new LoadAverageRule(),
			// WordPress Configuration.
			new DebugDisplayRule(),
			new CronRule(),
			new PermalinkRule(),
			new SearchVisibilityRule(),
			new WpMemoryLimitRule(),
			new WpMaxMemoryLimitRule(),
			new CoreUpdateRule(),
			new EnvironmentRule(),
			new MultisiteRule(),
			// Database.
			new AutoloadedOptionsSizeRule(),
			new AutoloadedOptionsCountRule(),
			new LargeTablesRule(),
			new TransientCountRule(),
			new RevisionsRule(),
			new TableCountRule(),
			new TableEngineRule(),
			new DatabaseCompatibilityRule(),
			// Cache.
			new ObjectCacheRule(),
			new RedisRule(),
			new MemcachedRule(),
			new OpcacheCacheRule(),
			new PageCacheRule(),
			new CachePluginRule(),
			// Plugins & Themes.
			new PluginCountRule(),
			new MissingVersionRule(),
			new InactivePluginsRule(),
			new ActiveThemeRule(),
			new PluginUpdateRule(),
			// PHP Profiler.
			new SlowRequestsRule(),
			new HighMemoryRule(),
			new HighMemoryUseRule(),
			new SampleCountRule(),
			new RequestTypeRule(),
			// Frontend Assets.
			new ExcessiveScriptsRule(),
			new ExcessiveStylesRule(),
			new ExternalAssetsRule(),
			new BlockingScriptsRule(),
			new AssetCountRule(),
		);

		foreach ( $rules as $rule ) {
			if ( $rule instanceof DiagnosticRuleInterface ) {
				$registry->register( $rule );
			}
		}
	}

	/**
	 * Get the singleton instance.
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
	 * Get the dependency injection container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Get the monitoring service.
	 *
	 * @return \WPPerformanceDoctor\Monitoring\Service|null
	 */
	public function monitoring_service(): ?\WPPerformanceDoctor\Monitoring\Service {
		return $this->monitoring_service;
	}

	/**
	 * Get the monitoring storage.
	 *
	 * @return \WPPerformanceDoctor\Monitoring\Storage|null
	 */
	public function storage(): ?\WPPerformanceDoctor\Monitoring\Storage {
		return $this->storage;
	}

	/**
	 * Get the diagnostic rule registry.
	 *
	 * @return RuleRegistry|null
	 */
	public function rule_registry(): ?RuleRegistry {
		return $this->rule_registry;
	}

	/**
	 * Get the diagnostic engine.
	 *
	 * @return DiagnosticEngine|null
	 */
	public function diagnostic_engine(): ?DiagnosticEngine {
		return $this->diagnostic_engine;
	}

	/**
	 * Get the diagnostic service.
	 *
	 * @return DiagnosticService|null
	 */
	public function diagnostic_service(): ?DiagnosticService {
		return $this->diagnostic_service;
	}

	/**
	 * Get the health score calculator.
	 *
	 * @return HealthScoreCalculator|null
	 */
	public function health_score_calculator(): ?HealthScoreCalculator {
		return $this->health_score_calculator;
	}

	/**
	 * Get the recommendation provider.
	 *
	 * @return RecommendationProvider|null
	 */
	public function recommendation_provider(): ?RecommendationProvider {
		return $this->recommendation_provider;
	}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		$this->register_hooks();

		if ( is_admin() ) {
			$this->register_admin_integration();
		}
	}

	/**
	 * Register core hooks.
	 */
	private function register_hooks(): void {
		// Install monitoring schema on activation/init.
		add_action( 'init', array( $this, 'ensure_monitoring_schema' ) );

		// Register WP-Cron monitoring schedule.
		add_action( 'init', array( $this, 'register_monitoring_schedule' ) );

		// Register WP-Cron diagnostic schedule.
		add_action( 'init', array( $this, 'register_diagnostic_schedule' ) );

		// Handle WP-Cron collection event.
		add_action( \WPPerformanceDoctor\Monitoring\Service::CRON_HOOK, array( $this, 'handle_cron_collection' ) );

		// Handle WP-Cron diagnostic evaluation event.
		add_action( self::DIAGNOSTIC_CRON_HOOK, array( $this, 'handle_cron_diagnostics' ) );

		// Register REST routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Register lightweight profiler hooks on every request (frontend and admin).
		// These hooks capture start time/memory at plugins_loaded and end time at shutdown.
		// The actual sampling decision is made inside PhpProfilerCollector::collect().
		// Only register when monitoring is enabled.
		if ( $this->monitoring_service && $this->monitoring_service->is_enabled() ) {
			$profiler_collector = new \WPPerformanceDoctor\Collectors\PhpProfilerCollector();
			$profiler_collector->register_hooks();
		}
	}

	/**
	 * Ensure monitoring database schema is installed.
	 *
	 * @return void
	 */
	public function ensure_monitoring_schema(): void {
		if ( $this->storage && ! $this->storage->is_schema_installed() ) {
			$this->storage->install_schema();
		}
	}

	/**
	 * Register monitoring WP-Cron schedule.
	 *
	 * @return void
	 */
	public function register_monitoring_schedule(): void {
		if ( $this->monitoring_service ) {
			$this->monitoring_service->register_schedule();
		}
	}

	/**
	 * Handle WP-Cron collection event.
	 *
	 * @return void
	 */
	public function handle_cron_collection(): void {
		if ( $this->monitoring_service ) {
			$this->monitoring_service->collect( 'cron' );
		}
	}

	/**
	 * Register WP-Cron diagnostic evaluation schedule.
	 *
	 * Reads the diagnostic interval from options and schedules the
	 * diagnostic cron event if diagnostics are enabled.
	 *
	 * @return void
	 */
	public function register_diagnostic_schedule(): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}

		$enabled = (bool) get_option( 'wpd_diagnostics_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		$interval = (int) get_option( 'wpd_diagnostic_interval', 86400 );
		$interval = max( 3600, min( 604800, $interval ) );

		add_filter(
			'cron_schedules',
			function () use ( $interval ) {
				return array(
					'wpd_diagnostic_interval' => array(
						'interval' => $interval,
						'display'  => __( 'WP Performance Doctor Diagnostics', 'wp-performance-doctor' ),
					),
				);
			}
		);

		if ( ! \wp_next_scheduled( self::DIAGNOSTIC_CRON_HOOK ) ) {
			\wp_schedule_event( time(), 'wpd_diagnostic_interval', self::DIAGNOSTIC_CRON_HOOK );
		}
	}

	/**
	 * Handle WP-Cron diagnostic evaluation event.
	 *
	 * Runs a full diagnostic evaluation on the scheduled interval.
	 *
	 * @return void
	 */
	public function handle_cron_diagnostics(): void {
		if ( ! $this->diagnostic_service ) {
			return;
		}

		$enabled = function_exists( 'get_option' ) ? (bool) get_option( 'wpd_diagnostics_enabled', true ) : true;
		if ( ! $enabled ) {
			return;
		}

		$this->diagnostic_service->run_diagnostics( 'cron' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		if ( ! $this->monitoring_service || ! $this->storage ) {
			return;
		}

		$controller = new \WPPerformanceDoctor\REST\RestController(
			$this->monitoring_service,
			$this->storage,
			new CapabilityManager(),
			new NonceManager(),
			$this->diagnostic_service,
			$this->health_score_calculator,
			$this->recommendation_provider,
			$this->rule_registry
		);

		$controller->register_routes();
	}

	/**
	 * Initialize the admin integration.
	 *
	 * @return void
	 */
	public function register_admin_integration(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( class_exists( Admin::class ) ) {
			Admin::get_instance()->init(
				$this->monitoring_service,
				$this->storage,
				$this->diagnostic_service,
				$this->rule_registry,
				$this->health_score_calculator,
				$this->recommendation_provider
			);
		}
	}
}
