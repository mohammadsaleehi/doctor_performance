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
}
