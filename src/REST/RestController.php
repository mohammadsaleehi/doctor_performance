<?php
/**
 * REST API controller for WP Performance Doctor monitoring.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\REST;

use WPPerformanceDoctor\Diagnostics\DiagnosticService;
use WPPerformanceDoctor\Diagnostics\HealthScoreCalculator;
use WPPerformanceDoctor\Diagnostics\RecommendationProvider;
use WPPerformanceDoctor\Diagnostics\RuleRegistry;
use WPPerformanceDoctor\Monitoring\Service;
use WPPerformanceDoctor\Monitoring\Storage;
use WPPerformanceDoctor\Performance\HistoricalComparison;
use WPPerformanceDoctor\Performance\PerformanceAnalyzer;
use WPPerformanceDoctor\Security\CapabilityManager;
use WPPerformanceDoctor\Security\NonceManager;

/**
 * Secured REST controller for WP Performance Doctor monitoring.
 *
 * Exposes monitoring data via wp-performance-doctor/v1 namespace.
 * All endpoints require manage_options capability.
 */
final class RestController {


	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'wp-performance-doctor/v1';

	/**
	 * Monitoring service.
	 *
	 * @var Service
	 */
	private Service $service;

	/**
	 * Storage instance.
	 *
	 * @var Storage
	 */
	private Storage $storage;

	/**
	 * Diagnostic service.
	 *
	 * @var DiagnosticService|null
	 */
	private ?DiagnosticService $diagnostic_service;

	/**
	 * Health score calculator.
	 *
	 * @var HealthScoreCalculator|null
	 */
	private ?HealthScoreCalculator $health_score_calculator;

	/**
	 * Recommendation provider.
	 *
	 * @var RecommendationProvider|null
	 */
	private ?RecommendationProvider $recommendation_provider;

	/**
	 * Rule registry.
	 *
	 * @var RuleRegistry|null
	 */
	private ?RuleRegistry $rule_registry;

	/**
	 * Capability manager.
	 *
	 * @var CapabilityManager
	 */
	private CapabilityManager $capability_manager;

	/**
	 * Nonce manager.
	 *
	 * @var NonceManager
	 */
	private NonceManager $nonce_manager;

	/**
	 * Constructor.
	 *
	 * @param Service                     $service            Monitoring service.
	 * @param Storage                     $storage            Storage instance.
	 * @param CapabilityManager           $capability_manager Capability manager.
	 * @param NonceManager                $nonce_manager      Nonce manager.
	 * @param DiagnosticService|null      $diagnostic_service Diagnostic service.
	 * @param HealthScoreCalculator|null  $health_score_calculator Health score calculator.
	 * @param RecommendationProvider|null $recommendation_provider Recommendation provider.
	 * @param RuleRegistry|null           $rule_registry Rule registry.
	 */
	public function __construct(
		Service $service,
		Storage $storage,
		CapabilityManager $capability_manager,
		NonceManager $nonce_manager,
		?DiagnosticService $diagnostic_service = null,
		?HealthScoreCalculator $health_score_calculator = null,
		?RecommendationProvider $recommendation_provider = null,
		?RuleRegistry $rule_registry = null
	) {
		$this->service                 = $service;
		$this->storage                 = $storage;
		$this->capability_manager      = $capability_manager;
		$this->nonce_manager           = $nonce_manager;
		$this->diagnostic_service      = $diagnostic_service;
		$this->health_score_calculator = $health_score_calculator;
		$this->recommendation_provider = $recommendation_provider;
		$this->rule_registry           = $rule_registry;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/monitoring/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/snapshots',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_snapshots' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/snapshots/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_snapshot' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/metrics',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/metrics/(?P<metric>[^/]+)/history',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_metric_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'metric' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'  => array(
							'type'    => 'integer',
							'default' => 30,
							'minimum' => 1,
							'maximum' => 1000,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/collect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_collection' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/monitoring/config',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Diagnostic endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/summary',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/health-score',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health_score' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/runs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_runs' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/run',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_diagnostics' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_findings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'severity' => array(
							'type' => 'string',
						),
						'category' => array(
							'type' => 'string',
						),
						'status'   => array(
							'type' => 'string',
						),
						'search'   => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_finding' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings/(?P<id>\d+)/history',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_finding_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings/(?P<id>\d+)/acknowledge',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'acknowledge_finding' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings/(?P<id>\d+)/ignore',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ignore_finding' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/findings/(?P<id>\d+)/restore',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_finding' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/rules',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diagnostic_rules' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/recommendations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_recommendations' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Performance profiling endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/performance/overview',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_performance_overview' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/profile',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'run_performance_profile' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/sessions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profiling_sessions' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/sessions/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profiling_session' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/compare',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'compare_performance' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'previous' => array(
							'type'     => 'integer',
							'required' => false,
						),
						'current'  => array(
							'type'     => 'integer',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/performance/browser-metrics',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_browser_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Check permission for REST endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! $this->capability_manager->can_manage_options() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view monitoring data.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get monitoring status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$data = array(
			'enabled'                => $this->service->is_enabled(),
			'interval'               => $this->service->get_interval(),
			'last_collection_time'   => $this->service->get_last_collection_time(),
			'last_collection_status' => $this->service->get_last_collection_status(),
			'next_collection_time'   => $this->service->get_next_collection_time(),
			'sample_rate'            => $this->service->get_sample_rate(),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get paginated snapshots.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_snapshots( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$result = $this->storage->get_snapshots( $page, $per_page );

		return rest_ensure_response(
			array(
				'snapshots' => $result['items'],
				'total'     => $result['total'],
				'page'      => $page,
				'per_page'  => $per_page,
			)
		);
	}

	/**
	 * Get a single snapshot with metrics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_snapshot( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$data = $this->storage->get_snapshot( $id );

		if ( null === $data ) {
			return new \WP_Error(
				'rest_snapshot_not_found',
				__( 'Snapshot not found.', 'wp-performance-doctor' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get latest metrics by category.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_metrics( \WP_REST_Request $request ): \WP_REST_Response {
		$category = $request->get_param( 'category' );

		if ( '' === $category ) {
			return rest_ensure_response( array() );
		}

		$data = $this->storage->get_latest_metrics_by_category( $category );

		return rest_ensure_response( $data );
	}

	/**
	 * Get metric history.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_metric_history( \WP_REST_Request $request ): \WP_REST_Response {
		$metric = $request->get_param( 'metric' );
		$limit  = (int) $request->get_param( 'limit' );

		$data = $this->storage->get_metric_history( $metric, $limit );

		return rest_ensure_response( $data );
	}

	/**
	 * Trigger manual collection.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function trigger_collection( \WP_REST_Request $request ) {
		// Verify nonce for manual collection.
		$nonce = $request->get_param( 'wpd_nonce' );
		if ( ! $nonce || ! $this->nonce_manager->verify( 'wpd_collect', $nonce ) ) {
			return new \WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		$result = $this->service->collect( 'manual' );

		if ( false === $result ) {
			return new \WP_Error(
				'rest_collection_failed',
				__( 'Collection could not be started. Another collection may be in progress.', 'wp-performance-doctor' ),
				array( 'status' => 409 )
			);
		}

		return rest_ensure_response(
			array(
				'snapshot_id' => $result,
				'status'      => 'started',
			)
		);
	}

	/**
	 * Get monitoring configuration.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_config(): \WP_REST_Response {
		$data = array(
			'enabled'     => $this->service->is_enabled(),
			'interval'    => $this->service->get_interval(),
			'sample_rate' => $this->service->get_sample_rate(),
			'nonce'       => $this->nonce_manager->create( 'wpd_collect' ),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get diagnostic summary.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_diagnostic_summary(): \WP_REST_Response {
		$latest_run      = $this->storage ? $this->storage->get_latest_diagnostic_run() : null;
		$severity_counts = $this->storage ? $this->storage->get_finding_counts_by_severity() : array();
		$open_count      = $this->storage ? $this->storage->get_open_finding_count() : 0;

		return rest_ensure_response(
			array(
				'latest_run'      => $latest_run,
				'open_findings'   => $open_count,
				'severity_counts' => $severity_counts,
				'health_score'    => $latest_run['health_score'] ?? null,
				'rules_evaluated' => $latest_run['rules_evaluated'] ?? 0,
				'last_run_at'     => $latest_run['completed_at'] ?? null,
				'last_run_status' => $latest_run['status'] ?? null,
			)
		);
	}

	/**
	 * Get health score details.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_health_score(): \WP_REST_Response {
		$findings        = $this->storage ? $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 1000 ) : array(
			'items' => array(),
			'total' => 0,
		);
		$latest_run      = $this->storage ? $this->storage->get_latest_diagnostic_run() : null;
		$rules_evaluated = $latest_run['rules_evaluated'] ?? 0;

		$calculator = $this->health_score_calculator ?? new HealthScoreCalculator();
		$details    = $calculator->calculate( $findings['items'], $rules_evaluated );

		return rest_ensure_response( $details );
	}

	/**
	 * Get paginated diagnostic runs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_diagnostic_runs( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$result = $this->storage ? $this->storage->get_diagnostic_runs( $page, $per_page ) : array(
			'items' => array(),
			'total' => 0,
		);

		return rest_ensure_response(
			array(
				'runs'     => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Run diagnostics manually.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_diagnostics( \WP_REST_Request $request ) {
		$nonce = $request->get_param( 'wpd_nonce' );
		if ( ! $nonce || ! $this->nonce_manager->verify( 'wpd_collect', $nonce ) ) {
			return new \WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->diagnostic_service ) {
			return new \WP_Error(
				'rest_diagnostic_service_unavailable',
				__( 'Diagnostic service is not available.', 'wp-performance-doctor' ),
				array( 'status' => 503 )
			);
		}

		$result = $this->diagnostic_service->run_diagnostics( 'manual' );

		if ( ! $result['success'] ) {
			return new \WP_Error(
				'rest_diagnostic_failed',
				$result['error'] ?? __( 'Diagnostic run failed.', 'wp-performance-doctor' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $result['summary'] );
	}

	/**
	 * Get paginated diagnostic findings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_diagnostic_findings( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$filters  = array();
		$severity = $request->get_param( 'severity' );
		$category = $request->get_param( 'category' );
		$status   = $request->get_param( 'status' );
		$search   = $request->get_param( 'search' );

		if ( $severity ) {
			$filters['severity'] = $severity;
		}
		if ( $category ) {
			$filters['category'] = $category;
		}
		if ( $status ) {
			$filters['status'] = $status;
		}
		if ( $search ) {
			$filters['search'] = $search;
		}

		$result = $this->storage ? $this->storage->get_diagnostic_findings( $filters, $page, $per_page ) : array(
			'items' => array(),
			'total' => 0,
		);

		return rest_ensure_response(
			array(
				'findings' => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Get a single diagnostic finding.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_diagnostic_finding( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$data = $this->storage ? $this->storage->get_diagnostic_finding( $id ) : null;

		if ( null === $data ) {
			return new \WP_Error(
				'rest_finding_not_found',
				__( 'Finding not found.', 'wp-performance-doctor' ),
				array( 'status' => 404 )
			);
		}

		// Decode JSON fields.
		if ( isset( $data['evidence'] ) ) {
			$data['evidence'] = json_decode( $data['evidence'], true ) ?? array();
		}
		if ( isset( $data['remediation'] ) ) {
			$data['remediation'] = json_decode( $data['remediation'], true ) ?? array();
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Get finding history.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_finding_history( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$history = $this->storage ? $this->storage->get_finding_history( $id ) : array();

		return rest_ensure_response( array( 'history' => $history ) );
	}

	/**
	 * Acknowledge a finding.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function acknowledge_finding( \WP_REST_Request $request ) {
		return $this->change_finding_status( $request, 'acknowledged', 'acknowledged' );
	}

	/**
	 * Ignore a finding.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ignore_finding( \WP_REST_Request $request ) {
		return $this->change_finding_status( $request, 'ignored', 'ignored' );
	}

	/**
	 * Restore a finding to open.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restore_finding( \WP_REST_Request $request ) {
		return $this->change_finding_status( $request, 'open', 'restored' );
	}

	/**
	 * Change finding status with nonce verification.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $new_status New status.
	 * @param string           $action Action description.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function change_finding_status( \WP_REST_Request $request, string $new_status, string $action ) {
		$nonce = $request->get_param( 'wpd_nonce' );
		if ( ! $nonce || ! $this->nonce_manager->verify( 'wpd_collect', $nonce ) ) {
			return new \WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		$id = (int) $request->get_param( 'id' );

		$result = $this->storage ? $this->storage->update_finding_status( $id, $new_status, $action ) : false;

		if ( false === $result ) {
			return new \WP_Error(
				'rest_finding_update_failed',
				__( 'Failed to update finding status.', 'wp-performance-doctor' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => $new_status,
			)
		);
	}

	/**
	 * Get diagnostic rules metadata.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_diagnostic_rules(): \WP_REST_Response {
		$rules = $this->rule_registry ? $this->rule_registry->get_metadata() : array();

		return rest_ensure_response( array( 'rules' => array_values( $rules ) ) );
	}

	/**
	 * Get recommendations.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_recommendations(): \WP_REST_Response {
		$findings = $this->storage ? $this->storage->get_diagnostic_findings( array( 'status' => 'open' ), 1, 1000 ) : array(
			'items' => array(),
			'total' => 0,
		);

		$provider        = $this->recommendation_provider ?? new RecommendationProvider();
		$recommendations = $provider->from_findings( $findings['items'] );

		return rest_ensure_response( array( 'recommendations' => $recommendations ) );
	}

	// ========================================================================
	// Performance Profiling Endpoints
	// ========================================================================

	/**
	 * Get performance overview with latest metrics and bottlenecks.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_performance_overview(): \WP_REST_Response {
		$metrics     = $this->storage ? $this->storage->get_latest_metrics_by_category( 'performance' ) : array();
		$sessions    = $this->storage ? $this->storage->get_profiling_sessions( 1, 5 ) : array(
			'items' => array(),
			'total' => 0,
		);
		$analyzer    = new PerformanceAnalyzer();
		$bottlenecks = ! empty( $metrics ) ? $analyzer->analyze( $metrics ) : array();

		return rest_ensure_response(
			array(
				'metrics'        => $metrics,
				'sessions'       => $sessions['items'],
				'sessions_total' => $sessions['total'],
				'bottlenecks'    => $bottlenecks,
				'has_data'       => ! empty( $metrics ),
			)
		);
	}

	/**
	 * Run an on-demand performance profile.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_performance_profile( \WP_REST_Request $request ) {
		$nonce = $request->get_param( 'wpd_nonce' );
		if ( ! $nonce || ! $this->nonce_manager->verify( 'wpd_collect', $nonce ) ) {
			return new \WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->service ) {
			return new \WP_Error(
				'rest_service_unavailable',
				__( 'Monitoring service is not available.', 'wp-performance-doctor' ),
				array( 'status' => 503 )
			);
		}

		if ( ! $this->storage ) {
			return new \WP_Error(
				'rest_storage_unavailable',
				__( 'Storage is not available.', 'wp-performance-doctor' ),
				array( 'status' => 503 )
			);
		}

		try {
			$start_time = microtime( true );

			// Use the monitoring service's collect method which now includes PerformanceCollector.
			$snapshot_id = $this->service->collect( 'manual' );

			if ( false === $snapshot_id ) {
				return new \WP_Error(
					'rest_profile_failed',
					__( 'Performance profile could not be started.', 'wp-performance-doctor' ),
					array( 'status' => 500 )
				);
			}

			$duration = microtime( true ) - $start_time;

			// Create a profiling session record.
			$context = $this->get_request_context();
			$session_id = $this->storage->create_profiling_session(
				$snapshot_id,
				'on_demand',
				'manual',
				$context
			);

			if ( false !== $session_id ) {
				$this->storage->update_profiling_session(
					$session_id,
					array(
						'completed_at'  => \current_time( 'mysql' ),
						'status'        => 'completed',
						'duration'      => $duration,
						'metrics_count' => count( $this->storage->get_latest_metrics_by_category( 'performance', $snapshot_id ) ),
					)
				);
			}

			return rest_ensure_response(
				array(
					'snapshot_id' => $snapshot_id,
					'status'      => 'completed',
					'duration'    => $duration,
				)
			);
		} finally {
			\delete_transient( \WPPerformanceDoctor\Monitoring\Service::LOCK_TRANSIENT );
		}
	}

	/**
	 * Get paginated profiling sessions.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_profiling_sessions( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$result = $this->storage ? $this->storage->get_profiling_sessions( $page, $per_page ) : array(
			'items' => array(),
			'total' => 0,
		);

		return rest_ensure_response(
			array(
				'sessions' => $result['items'],
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Get a single profiling session.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_profiling_session( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$data = $this->storage ? $this->storage->get_profiling_session( $id ) : null;

		if ( null === $data ) {
			return new \WP_Error(
				'rest_session_not_found',
				__( 'Profiling session not found.', 'wp-performance-doctor' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Compare performance between two snapshots.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function compare_performance( \WP_REST_Request $request ) {
		$current_id  = (int) $request->get_param( 'current' );
		$previous_id = (int) $request->get_param( 'previous' );

		if ( ! $current_id ) {
			$current_id = $this->storage ? (int) ( $this->storage->get_latest_snapshot_id() ?? 0 ) : 0;
		}

		if ( ! $current_id || ! $previous_id ) {
			return new \WP_Error(
				'rest_invalid_parameter',
				__( 'Both current and previous snapshot IDs are required.', 'wp-performance-doctor' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->storage ) {
			return new \WP_Error(
				'rest_storage_unavailable',
				__( 'Storage is not available.', 'wp-performance-doctor' ),
				array( 'status' => 503 )
			);
		}

		$comparison = new HistoricalComparison();
		$result     = $comparison->compare_with_historical( $this->storage, $current_id, $previous_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Accept browser-collected performance metrics and associate them with a profiling session.
	 *
	 * Validates the session ID, verifies the nonce, and validates every submitted metric
	 * against an explicit allowlist before storing.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_browser_metrics( \WP_REST_Request $request ) {
		$nonce = $request->get_param( 'wpd_nonce' );
		if ( ! $nonce || ! $this->nonce_manager->verify( 'wpd_collect', $nonce ) ) {
			return new \WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid or missing nonce.', 'wp-performance-doctor' ),
				array( 'status' => 403 )
			);
		}

		$session_id = (int) $request->get_param( 'session_id' );
		$snapshot_id = (int) $request->get_param( 'snapshot_id' );
		$raw_metrics = $request->get_param( 'metrics' );

		// Reject excessively large payloads before decoding.
		if ( is_string( $raw_metrics ) && strlen( $raw_metrics ) > 512 * 1024 ) {
			return new \WP_Error(
				'rest_payload_too_large',
				__( 'Submitted payload is too large.', 'wp-performance-doctor' ),
				array( 'status' => 413 )
			);
		}

		// Accept metrics either as an array (direct) or as a JSON-encoded
		// string (when submitted via FormData from the browser).
		if ( is_string( $raw_metrics ) && '' !== $raw_metrics ) {
			$decoded = json_decode( $raw_metrics, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$metrics = $decoded;
			} else {
				$metrics = array();
			}
		} elseif ( is_array( $raw_metrics ) ) {
			$metrics = $raw_metrics;
		} else {
			$metrics = array();
		}

		if ( ! $session_id || ! $snapshot_id ) {
			return new \WP_Error(
				'rest_invalid_parameter',
				__( 'session_id and snapshot_id are required.', 'wp-performance-doctor' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $metrics ) || empty( $metrics ) ) {
			return new \WP_Error(
				'rest_invalid_parameter',
				__( 'metrics must be a non-empty array.', 'wp-performance-doctor' ),
				array( 'status' => 400 )
			);
		}

		// Limit payload size.
		if ( count( $metrics ) > 200 ) {
			return new \WP_Error(
				'rest_payload_too_large',
				__( 'Too many metrics submitted. Maximum is 200.', 'wp-performance-doctor' ),
				array( 'status' => 413 )
			);
		}

		if ( ! $this->storage ) {
			return new \WP_Error(
				'rest_storage_unavailable',
				__( 'Storage is not available.', 'wp-performance-doctor' ),
				array( 'status' => 503 )
			);
		}

		$stored = $this->storage->save_browser_metrics( $session_id, $metrics, (string) $snapshot_id );

		if ( false === $stored ) {
			return new \WP_Error(
				'rest_session_not_found',
				__( 'Profiling session not found.', 'wp-performance-doctor' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'stored'    => $stored,
				'session_id' => $session_id,
				'snapshot_id' => $snapshot_id,
			)
		);
	}

	/**
	 * Get the request context for profiling sessions.
	 *
	 * @return string
	 */
	private function get_request_context(): string {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return 'ajax';
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}
		if ( is_admin() ) {
			return 'admin';
		}

		return 'frontend';
	}
}
