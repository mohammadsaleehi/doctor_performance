<?php
/**
 * Storage layer for WP Performance Doctor monitoring data.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Monitoring;

use WPPerformanceDoctor\Monitoring\Schema;
use WPPerformanceDoctor\Monitoring\Metric;
use WPPerformanceDoctor\Rules\RuleStatus;

/**
 * Persistent storage for monitoring snapshots and metrics.
 *
 * Uses WordPress database APIs and dbDelta() for schema management.
 * Supports snapshot creation, metric persistence, historical retrieval,
 * and retention-based cleanup.
 */
final class Storage {

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table names are validated via preg_match() before use in queries. All user data uses %s/%d placeholders.

	/**
	 * Plugin version for schema tracking.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( string $version = '0.2.0' ) {
		$this->version = $version;
	}

	/**
	 * Install or update the database schema.
	 *
	 * @return void
	 */
	public function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( Schema::get_snapshots_sql() );
		dbDelta( Schema::get_metrics_sql() );
		dbDelta( Schema::get_diagnostic_runs_sql() );
		dbDelta( Schema::get_diagnostic_findings_sql() );
		dbDelta( Schema::get_finding_history_sql() );

		// Store schema version.
		\update_option( 'wpd_monitoring_schema_version', Schema::SCHEMA_VERSION );
	}

	/**
	 * Check if the schema is installed.
	 *
	 * @return bool
	 */
	public function is_schema_installed(): bool {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wpd_monitoring_snapshots',
			$wpdb->prefix . 'wpd_monitoring_metrics',
			$wpdb->prefix . 'wpd_diagnostic_runs',
			$wpdb->prefix . 'wpd_diagnostic_findings',
			$wpdb->prefix . 'wpd_finding_history',
		);

		foreach ( $tables as $table ) {
			if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is validated via preg_match above.
			$sql = $wpdb->prepare( 'SHOW TABLES LIKE \'' . $table . '\' ' );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
			if ( $wpdb->get_var( $sql ) !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create a monitoring snapshot with metrics.
	 *
	 * @param Metric[] $metrics          Collected metrics.
	 * @param float    $duration         Collection duration in seconds.
	 * @param string   $status           Collection status.
	 * @param string   $trigger_type     Trigger type ('cron' or 'manual').
	 * @return int|false Snapshot ID on success, false on failure.
	 */
	public function save_snapshot(
		array $metrics,
		float $duration,
		string $status = 'completed',
		string $trigger_type = 'manual'
	) {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$metrics_table   = $wpdb->prefix . 'wpd_monitoring_metrics';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $metrics_table ) ) {
			return false;
		}

		$timestamp      = \current_time( 'mysql' );
		$schema_version = Schema::SCHEMA_VERSION;

		// Insert snapshot.
		$inserted = $wpdb->insert(
			$snapshots_table,
			array(
				'timestamp'           => $timestamp,
				'collection_duration' => $duration,
				'collection_status'   => $status,
				'trigger_type'        => $trigger_type,
				'schema_version'      => $schema_version,
			),
			array(
				'%s',
				'%f',
				'%s',
				'%s',
				'%d',
			)
		);

		if ( false === $inserted ) {
			return false;
		}

		$snapshot_id = (int) $wpdb->insert_id;

		// Insert metrics in batches.
		$batch_size = 100;
		$batch      = array();

		foreach ( $metrics as $metric ) {
			if ( ! $metric instanceof Metric ) {
				continue;
			}

			$batch[] = array(
				'snapshot_id'         => $snapshot_id,
				'metric_identifier'   => $metric->metric,
				'metric_value'        => is_scalar( $metric->value ) ? (string) $metric->value : wp_json_encode( $metric->value ),
				'unit'                => $metric->unit,
				'category'            => $metric->category,
				'availability_status' => $metric->status,
				'source'              => $metric->source,
				'collected_at'        => $metric->collected_at,
				'metadata'            => wp_json_encode( $metric->metadata ),
			);

			if ( count( $batch ) >= $batch_size ) {
				$this->insert_metrics_batch( $metrics_table, $batch );
				$batch = array();
			}
		}

		if ( ! empty( $batch ) ) {
			$this->insert_metrics_batch( $metrics_table, $batch );
		}

		return $snapshot_id;
	}

	/**
	 * Insert a batch of metrics using a single multi-row INSERT.
	 *
	 * @param string $table  Metrics table name.
	 * @param array  $batch  Array of metric data arrays.
	 * @return void
	 */
	private function insert_metrics_batch( string $table, array $batch ): void {
		global $wpdb;

		if ( empty( $batch ) ) {
			return;
		}

		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return;
		}

		$values = array();

		foreach ( $batch as $row ) {
			$values[] = sprintf(
				'(%d, %s, %s, %s, %s, %s, %s, %s, %s)',
				(int) $row['snapshot_id'],
				$wpdb->prepare( '%s', $row['metric_identifier'] ),
				$wpdb->prepare( '%s', $row['metric_value'] ),
				$wpdb->prepare( '%s', $row['unit'] ),
				$wpdb->prepare( '%s', $row['category'] ),
				$wpdb->prepare( '%s', $row['availability_status'] ),
				$wpdb->prepare( '%s', $row['source'] ),
				$wpdb->prepare( '%s', $row['collected_at'] ),
				$wpdb->prepare( '%s', $row['metadata'] )
			);
		}

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table is validated via preg_match above.
			$sql = 'INSERT INTO ' . $table . ' (snapshot_id, metric_identifier, metric_value, unit, category, availability_status, source, collected_at, metadata) VALUES ' . implode( ',', $values );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql contains only prepared values.
			$wpdb->query( $wpdb->prepare( $sql ) );
		}
	}

	/**
	 * Get the latest snapshot ID.
	 *
	 * @return int|null
	 */
	public function get_latest_snapshot_id(): ?int {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
		$sql = $wpdb->prepare( 'SELECT id FROM ' . $snapshots_table . ' ORDER BY id DESC LIMIT 1' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$id = $wpdb->get_var( $sql );

		return $id ? (int) $id : null;
	}

	/**
	 * Get a snapshot with its metrics.
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array|null Snapshot data with metrics, or null if not found.
	 */
	public function get_snapshot( int $snapshot_id ): ?array {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$metrics_table   = $wpdb->prefix . 'wpd_monitoring_metrics';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return null;
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $metrics_table ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
			'SELECT * FROM ' . $snapshots_table . ' WHERE id = %d',
			$snapshot_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$snapshot = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! $snapshot ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $metrics_table is validated via preg_match above.
		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $metrics_table is validated via preg_match above.
			'SELECT * FROM ' . $metrics_table . ' WHERE snapshot_id = %d ORDER BY category, metric_identifier',
			$snapshot_id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$metrics = $wpdb->get_results( $sql, ARRAY_A );

		return array(
			'snapshot' => $snapshot,
			'metrics'  => $metrics ? $metrics : array(),
		);
	}

	/**
	 * Get snapshots with pagination.
	 *
	 * @param int $page  Page number (1-based).
	 * @param int $per_page Items per page.
	 * @return array{items: array, total: int}
	 */
	public function get_snapshots( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $snapshots_table )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table is validated via preg_match above.
			'SELECT * FROM ' . $snapshots_table . ' ORDER BY id DESC LIMIT %d OFFSET %d',
			$per_page,
			$offset
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$items = $wpdb->get_results( $sql, ARRAY_A );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get metrics for a specific category from the latest snapshot.
	 *
	 * @param string $category Category name.
	 * @return array Metric data arrays.
	 */
	public function get_latest_metrics_by_category( string $category ): array {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$metrics_table   = $wpdb->prefix . 'wpd_monitoring_metrics';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return array();
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $metrics_table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names validated via preg_match above.
		$sql = $wpdb->prepare(
			'SELECT m.* FROM ' . $metrics_table . ' m
			INNER JOIN ' . $snapshots_table . ' s ON m.snapshot_id = s.id
			WHERE m.category = %s
			ORDER BY s.id DESC, m.metric_identifier
			LIMIT 100',
			$category
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$metrics = $wpdb->get_results( $sql, ARRAY_A );

		return $metrics ? $metrics : array();
	}

	/**
	 * Get metric history for a specific metric identifier.
	 *
	 * @param string $metric_id Metric identifier.
	 * @param int    $limit     Maximum number of history points.
	 * @return array Historical metric data arrays.
	 */
	public function get_metric_history( string $metric_id, int $limit = 30 ): array {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$metrics_table   = $wpdb->prefix . 'wpd_monitoring_metrics';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return array();
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $metrics_table ) ) {
			return array();
		}

		$limit = max( 1, min( 1000, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names validated via preg_match above.
		$sql = $wpdb->prepare(
			'SELECT m.metric_value, m.unit, m.availability_status, s.timestamp FROM ' . $metrics_table . ' m
			INNER JOIN ' . $snapshots_table . ' s ON m.snapshot_id = s.id
			WHERE m.metric_identifier = %s
			ORDER BY s.id DESC
			LIMIT %d',
			$metric_id,
			$limit
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql was prepared on previous line.
		$history = $wpdb->get_results( $sql, ARRAY_A );

		return $history ? $history : array();
	}

	/**
	 * Clean up expired monitoring data based on retention days.
	 *
	 * @param int $retention_days Number of days to retain data.
	 * @return int Number of deleted snapshots.
	 */
	public function cleanup_expired( int $retention_days = 30 ): int {
		global $wpdb;

		$snapshots_table = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$metrics_table   = $wpdb->prefix . 'wpd_monitoring_metrics';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $snapshots_table ) ) {
			return 0;
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $metrics_table ) ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Get snapshot IDs to delete.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table validated via preg_match above.
		$expired_ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table validated via preg_match above.
			$wpdb->prepare(
				'SELECT id FROM ' . $snapshots_table . ' WHERE timestamp < %s',
				$cutoff
			)
		);

		if ( empty( $expired_ids ) ) {
			return 0;
		}

		// Delete metrics in batches.
		$batch_size        = 100;
		$deleted_snapshots = 0;

		$expired_count = count( $expired_ids );
		for ( $i = 0; $i < $expired_count; $i += $batch_size ) {
			$batch        = array_slice( $expired_ids, $i, $batch_size );
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $metrics_table validated via preg_match above.
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $metrics_table validated via preg_match above.
				$wpdb->prepare(
					'DELETE FROM ' . $metrics_table . ' WHERE snapshot_id IN (' . $placeholders . ')',
					$batch
				)
			);
		}

		// Delete snapshots in batches.
		for ( $i = 0; $i < $expired_count; $i += $batch_size ) {
			$batch        = array_slice( $expired_ids, $i, $batch_size );
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table validated via preg_match above.
			$deleted = $wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $snapshots_table validated via preg_match above.
				$wpdb->prepare(
					'DELETE FROM ' . $snapshots_table . ' WHERE id IN (' . $placeholders . ')',
					$batch
				)
			);

			if ( false !== $deleted ) {
				$deleted_snapshots += (int) $deleted;
			}
		}

		return $deleted_snapshots;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table names are validated via preg_match() before use. All user data uses %s/%d placeholders.

	/**
	 * Create a diagnostic run record.
	 *
	 * @param int|null $snapshot_id   Related snapshot ID.
	 * @param string   $trigger_type  Trigger type ('manual' or 'cron').
	 * @return int|false Run ID on success, false on failure.
	 */
	public function create_diagnostic_run( ?int $snapshot_id = null, string $trigger_type = 'manual' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_runs';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return false;
		}

		$timestamp = \current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'snapshot_id'  => $snapshot_id,
				'started_at'   => $timestamp,
				'status'       => 'running',
				'trigger_type' => $trigger_type,
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a diagnostic run with completion data.
	 *
	 * @param int   $run_id Run ID.
	 * @param array $data   Run completion data.
	 * @return bool
	 */
	public function update_diagnostic_run( int $run_id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_runs';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return false;
		}

		$fields = array();
		$format = array();

		if ( isset( $data['completed_at'] ) ) {
			$fields['completed_at'] = $data['completed_at'];
			$format[]               = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$fields['status'] = $data['status'];
			$format[]         = '%s';
		}
		if ( isset( $data['rules_evaluated'] ) ) {
			$fields['rules_evaluated'] = $data['rules_evaluated'];
			$format[]                  = '%d';
		}
		if ( isset( $data['findings_count'] ) ) {
			$fields['findings_count'] = $data['findings_count'];
			$format[]                 = '%d';
		}
		if ( isset( $data['critical_count'] ) ) {
			$fields['critical_count'] = $data['critical_count'];
			$format[]                 = '%d';
		}
		if ( isset( $data['high_count'] ) ) {
			$fields['high_count'] = $data['high_count'];
			$format[]             = '%d';
		}
		if ( isset( $data['medium_count'] ) ) {
			$fields['medium_count'] = $data['medium_count'];
			$format[]               = '%d';
		}
		if ( isset( $data['low_count'] ) ) {
			$fields['low_count'] = $data['low_count'];
			$format[]            = '%d';
		}
		if ( isset( $data['info_count'] ) ) {
			$fields['info_count'] = $data['info_count'];
			$format[]             = '%d';
		}
		if ( isset( $data['execution_duration'] ) ) {
			$fields['execution_duration'] = $data['execution_duration'];
			$format[]                     = '%f';
		}
		if ( isset( $data['health_score'] ) ) {
			$fields['health_score'] = $data['health_score'];
			$format[]               = '%d';
		}
		if ( isset( $data['error_message'] ) ) {
			$fields['error_message'] = $data['error_message'];
			$format[]                = '%s';
		}

		if ( empty( $fields ) ) {
			return true;
		}

		return false !== $wpdb->update(
			$table,
			$fields,
			array( 'id' => $run_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Save diagnostic findings, deduplicating by fingerprint.
	 *
	 * @param int                                        $run_id   Related run ID.
	 * @param \WPPerformanceDoctor\Diagnostics\Finding[] $findings Findings to save.
	 * @return array{inserted: int, updated: int, resolved: int}
	 */
	public function save_diagnostic_findings( int $run_id, array $findings ): array {
		global $wpdb;

		$findings_table = $wpdb->prefix . 'wpd_diagnostic_findings';
		$runs_table     = $wpdb->prefix . 'wpd_diagnostic_runs';
		$history_table  = $wpdb->prefix . 'wpd_finding_history';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $findings_table ) ) {
			// phpcs:ignore WordPress.Arrays.ArrayDeclaration.NotAssociative -- Early return for invalid table.
			return array(
				'inserted' => 0,
				'updated'  => 0,
				'resolved' => 0,
			);
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $runs_table ) ) {
			// phpcs:ignore WordPress.Arrays.ArrayDeclaration.NotAssociative -- Early return for invalid table.
			return array(
				'inserted' => 0,
				'updated'  => 0,
				'resolved' => 0,
			);
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $history_table ) ) {
			// phpcs:ignore WordPress.Arrays.ArrayDeclaration.NotAssociative -- Early return for invalid table.
			return array(
				'inserted' => 0,
				'updated'  => 0,
				'resolved' => 0,
			);
		}

		$now      = \current_time( 'mysql' );
		$inserted = 0;
		$updated  = 0;
		$resolved = 0;

		foreach ( $findings as $finding ) {
			// Check if finding already exists by fingerprint.
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT id, status, severity FROM ' . $findings_table . ' WHERE fingerprint = %s',
					$finding->fingerprint
				),
				ARRAY_A
			);

			$finding_data = array(
				'rule_id'        => $finding->rule_id,
				'rule_version'   => $finding->rule_version,
				'fingerprint'    => $finding->fingerprint,
				'title'          => $finding->title,
				'description'    => $finding->description,
				'category'       => $finding->category,
				'severity'       => $finding->severity,
				'confidence'     => $finding->confidence,
				'status'         => $finding->status,
				'impact'         => $finding->impact,
				'recommendation' => $finding->recommendation,
				'evidence'       => wp_json_encode( $finding->evidence ),
				'remediation'    => wp_json_encode( $finding->remediation ),
				'first_detected' => $finding->first_detected,
				'last_detected'  => $finding->last_detected,
				'resolved_at'    => $finding->resolved_at ?: null,
				'snapshot_id'    => $finding->snapshot_id,
				'run_id'         => $run_id,
			);

			$format = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
			);

			if ( null === $existing ) {
				$inserted = $wpdb->insert(
					$findings_table,
					$finding_data,
					$format
				) ? $inserted + 1 : $inserted;
			} else {
				$existing_id  = (int) $existing['id'];
				$old_status   = $existing['status'];
				$old_severity = $existing['severity'];

				// Respect the administrator's lifecycle decision. An acknowledged
				// or ignored finding must not be flipped back to "open" merely
				// because the same condition was re-detected. A resolved finding
				// that reappears should be reopened (recurring problem).
				if ( in_array( $old_status, array( RuleStatus::ACKNOWLEDGED, RuleStatus::IGNORED ), true ) ) {
					$update_data   = array(
						'last_detected' => $finding->last_detected,
						'run_id'        => $run_id,
					);
					$update_format = array( '%s', '%d' );
				} elseif ( RuleStatus::RESOLVED === $old_status ) {
					$update_data   = array(
						'last_detected' => $finding->last_detected,
						'status'        => RuleStatus::OPEN,
						'resolved_at'   => null,
						'run_id'        => $run_id,
					);
					$update_format = array( '%s', '%s', '%s', '%d' );
				} else {
					// Existing finding is open: update normally.
					$update_data   = array(
						'last_detected' => $finding->last_detected,
						'status'        => $finding->status,
						'severity'      => $finding->severity,
						'run_id'        => $run_id,
					);
					$update_format = array( '%s', '%s', '%s', '%d' );
				}

				$wpdb->update(
					$findings_table,
					$update_data,
					array( 'id' => $existing_id ),
					$update_format,
					array( '%d' )
				);
				++$updated;

				// Record history for status/severity changes.
				if ( $old_status !== $finding->status || $old_severity !== $finding->severity ) {
					$wpdb->insert(
						$history_table,
						array(
							'finding_id'   => $existing_id,
							'action'       => 'status_changed',
							'old_status'   => $old_status,
							'new_status'   => $finding->status,
							'old_severity' => $old_severity,
							'new_severity' => $finding->severity,
							'created_at'   => $now,
							'notes'        => '',
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
					);
				}
			}
		}

		return array(
			'inserted' => $inserted,
			'updated'  => $updated,
			'resolved' => $resolved,
		);
	}

	/**
	 * Get the latest diagnostic run.
	 *
	 * @return array|null
	 */
	public function get_latest_diagnostic_run(): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_runs';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare( 'SELECT * FROM ' . $table . ' ORDER BY id DESC LIMIT 1' ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get diagnostic runs with pagination.
	 *
	 * @param int $page Page number (1-based).
	 * @param int $per_page Items per page.
	 * @return array{items: array, total: int}
	 */
	public function get_diagnostic_runs( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_runs';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table )
		);

		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' ORDER BY id DESC LIMIT %d OFFSET %d',
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get findings with optional filters and pagination.
	 *
	 * @param array $filters Optional filters (severity, category, status, search).
	 * @param int   $page Page number.
	 * @param int   $per_page Items per page.
	 * @return array{items: array, total: int}
	 */
	public function get_diagnostic_findings( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array();
		$format = array();

		if ( ! empty( $filters['severity'] ) ) {
			$where[]  = 'severity = %s';
			$format[] = $filters['severity'];
		}
		if ( ! empty( $filters['category'] ) ) {
			$where[]  = 'category = %s';
			$format[] = $filters['category'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$format[] = $filters['status'];
		}
		if ( ! empty( $filters['confidence'] ) ) {
			$where[]  = 'confidence = %s';
			$format[] = $filters['confidence'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[]  = '(title LIKE %s OR rule_id LIKE %s)';
			$format[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$format[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated, values use placeholders.
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' ' . $where_sql, $format )
		);

		$order = isset( $filters['order'] ) && $filters['order'] === 'asc' ? 'ASC' : 'DESC';
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated, values use placeholders.
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' ' . $where_sql . ' ORDER BY ' .
				'CASE severity WHEN \'critical\' THEN 1 WHEN \'high\' THEN 2 WHEN \'medium\' THEN 3 WHEN \'low\' THEN 4 ELSE 5 END, ' .
				'last_detected ' . $order . ' LIMIT %d OFFSET %d',
				array_merge( $format, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Get a single finding by ID.
	 *
	 * @param int $finding_id Finding ID.
	 * @return array|null
	 */
	public function get_diagnostic_finding( int $finding_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return null;
		}

		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $finding_id ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Update finding status (acknowledge, ignore, resolve, restore).
	 *
	 * @param int         $finding_id Finding ID.
	 * @param string      $new_status New status.
	 * @param string      $action Action description for history.
	 * @param string|null $notes Optional notes.
	 * @return bool
	 */
	public function update_finding_status( int $finding_id, string $new_status, string $action, ?string $notes = null ): bool {
		global $wpdb;

		$findings_table = $wpdb->prefix . 'wpd_diagnostic_findings';
		$history_table  = $wpdb->prefix . 'wpd_finding_history';

		if ( ! preg_match( '/^[a-z0-9_]+$/', $findings_table ) ) {
			return false;
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $history_table ) ) {
			return false;
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT status, severity FROM ' . $findings_table . ' WHERE id = %d', $finding_id ),
			ARRAY_A
		);

		if ( null === $existing ) {
			return false;
		}

		$now           = \current_time( 'mysql' );
		$update_data   = array( 'status' => $new_status );
		$update_format = array( '%s' );

		if ( RuleStatus::RESOLVED === $new_status && RuleStatus::OPEN === $existing['status'] ) {
			$update_data['resolved_at'] = $now;
			$update_format[]            = '%s';
		} elseif ( RuleStatus::OPEN === $new_status && RuleStatus::RESOLVED === $existing['status'] ) {
			$update_data['resolved_at'] = null;
			$update_format[]            = '%s';
		}

		$result = $wpdb->update(
			$findings_table,
			$update_data,
			array( 'id' => $finding_id ),
			$update_format,
			array( '%d' )
		);

		if ( false !== $result ) {
			$wpdb->insert(
				$history_table,
				array(
					'finding_id'   => $finding_id,
					'action'       => $action,
					'old_status'   => $existing['status'],
					'new_status'   => $new_status,
					'old_severity' => $existing['severity'],
					'new_severity' => $existing['severity'],
					'created_at'   => $now,
					'notes'        => $notes ?: '',
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Get finding history by finding ID.
	 *
	 * @param int $finding_id Finding ID.
	 * @return array
	 */
	public function get_finding_history( int $finding_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_finding_history';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE finding_id = %d ORDER BY created_at DESC',
				$finding_id
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Get counts of findings by severity.
	 *
	 * @return array<string, int>
	 */
	public function get_finding_counts_by_severity(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return array(
				'critical' => 0,
				'high'     => 0,
				'medium'   => 0,
				'low'      => 0,
				'info'     => 0,
			);
		}

		$counts = array(
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'info'     => 0,
		);

		foreach ( $counts as $severity => &$count ) {
			$count = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . $table . ' WHERE severity = %s AND status = %s',
					$severity,
					RuleStatus::OPEN
				)
			);
		}
		unset( $count );

		return $counts;
	}

	/**
	 * Get total open finding count.
	 *
	 * @return int
	 */
	public function get_open_finding_count(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE status = %s', RuleStatus::OPEN )
		);
	}

	/**
	 * Get all unique rule IDs that have findings.
	 *
	 * @return string[]
	 */
	public function get_rules_with_findings(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpd_diagnostic_findings';
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return array();
		}

		$rows = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table validated via preg_match above.
			$wpdb->prepare( 'SELECT DISTINCT rule_id FROM ' . $table )
		);

		return $rows ? array_map( 'strval', $rows ) : array();
	}
}
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
