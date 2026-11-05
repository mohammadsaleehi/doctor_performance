<?php
/**
 * Database schema definitions for WP Performance Doctor monitoring.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Monitoring;

/**
 * Database schema definitions for monitoring snapshots and metrics.
 */
final class Schema {

	public const TABLE_SNAPSHOTS           = 'wp_wpd_monitoring_snapshots';
	public const TABLE_METRICS             = 'wp_wpd_monitoring_metrics';
	public const TABLE_DIAGNOSTIC_RUNS     = 'wp_wpd_diagnostic_runs';
	public const TABLE_DIAGNOSTIC_FINDINGS = 'wp_wpd_diagnostic_findings';
	public const TABLE_FINDING_HISTORY     = 'wp_wpd_finding_history';
	public const TABLE_PROFILING_SESSIONS  = 'wp_wpd_profiling_sessions';

	public const SCHEMA_VERSION = 4;

	/**
	 * Get all table names.
	 *
	 * @return string[]
	 */
	public function get_tables(): array {
		return array(
			self::TABLE_SNAPSHOTS,
			self::TABLE_METRICS,
			self::TABLE_DIAGNOSTIC_RUNS,
			self::TABLE_DIAGNOSTIC_FINDINGS,
			self::TABLE_FINDING_HISTORY,
			self::TABLE_PROFILING_SESSIONS,
		);
	}

	/**
	 * Get the CREATE TABLE SQL for snapshots.
	 *
	 * @return string
	 */
	public static function get_snapshots_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_monitoring_snapshots';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			collection_duration float NOT NULL DEFAULT 0,
			collection_status varchar(20) NOT NULL DEFAULT 'unknown',
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			schema_version int(11) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY timestamp (timestamp),
			KEY collection_status (collection_status),
			KEY trigger_type (trigger_type)
		) {$charset_collate}";
	}

	/**
	 * Get the CREATE TABLE SQL for metrics.
	 *
	 * @return string
	 */
	public static function get_metrics_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_monitoring_metrics';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_id bigint(20) unsigned NOT NULL DEFAULT 0,
			metric_identifier varchar(100) NOT NULL DEFAULT '',
			metric_value text NOT NULL,
			unit varchar(20) NOT NULL DEFAULT '',
			category varchar(30) NOT NULL DEFAULT '',
			availability_status varchar(20) NOT NULL DEFAULT 'unknown',
			source varchar(50) NOT NULL DEFAULT '',
			collected_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			metadata longtext NOT NULL,
			page_url varchar(500) DEFAULT NULL,
			measurement_context varchar(20) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY snapshot_id (snapshot_id),
			KEY metric_identifier (metric_identifier),
			KEY category (category),
			KEY collected_at (collected_at)
		) {$charset_collate}";
	}

	/**
	 * Get the CREATE TABLE SQL for diagnostic runs.
	 *
	 * @return string
	 */
	public static function get_diagnostic_runs_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_diagnostic_runs';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_id bigint(20) unsigned DEFAULT NULL,
			started_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'running',
			rules_evaluated int(11) NOT NULL DEFAULT 0,
			findings_count int(11) NOT NULL DEFAULT 0,
			critical_count int(11) NOT NULL DEFAULT 0,
			high_count int(11) NOT NULL DEFAULT 0,
			medium_count int(11) NOT NULL DEFAULT 0,
			low_count int(11) NOT NULL DEFAULT 0,
			info_count int(11) NOT NULL DEFAULT 0,
			execution_duration float NOT NULL DEFAULT 0,
			health_score int(11) DEFAULT NULL,
			error_message text DEFAULT NULL,
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			PRIMARY KEY (id),
			KEY snapshot_id (snapshot_id),
			KEY status (status),
			KEY started_at (started_at)
		) {$charset_collate}";
	}

	/**
	 * Get the CREATE TABLE SQL for diagnostic findings.
	 *
	 * @return string
	 */
	public static function get_diagnostic_findings_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_diagnostic_findings';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_id varchar(100) NOT NULL DEFAULT '',
			rule_version varchar(20) NOT NULL DEFAULT '1.0.0',
			fingerprint varchar(64) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			description text DEFAULT NULL,
			category varchar(30) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'info',
			confidence varchar(20) NOT NULL DEFAULT 'low',
			status varchar(20) NOT NULL DEFAULT 'open',
			impact text DEFAULT NULL,
			recommendation text DEFAULT NULL,
			evidence longtext DEFAULT NULL,
			remediation longtext DEFAULT NULL,
			first_detected datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			last_detected datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			resolved_at datetime DEFAULT NULL,
			snapshot_id bigint(20) unsigned DEFAULT NULL,
			run_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY rule_id (rule_id),
			KEY category (category),
			KEY severity (severity),
			KEY status (status),
			KEY snapshot_id (snapshot_id),
			KEY run_id (run_id),
			KEY last_detected (last_detected)
		) {$charset_collate}";
	}

	/**
	 * Get the CREATE TABLE SQL for finding history.
	 *
	 * @return string
	 */
	public static function get_finding_history_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_finding_history';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			finding_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(30) NOT NULL DEFAULT '',
			old_status varchar(20) DEFAULT NULL,
			new_status varchar(20) DEFAULT NULL,
			old_severity varchar(20) DEFAULT NULL,
			new_severity varchar(20) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			notes text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY finding_id (finding_id),
			KEY created_at (created_at)
		) {$charset_collate}";
	}

	/**
	 * Get the CREATE TABLE SQL for profiling sessions.
	 *
	 * @return string
	 */
	public static function get_profiling_sessions_sql(): string {
		global $wpdb;

		$table           = $wpdb->prefix . 'wpd_profiling_sessions';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_id bigint(20) unsigned DEFAULT NULL,
			mode varchar(20) NOT NULL DEFAULT 'passive',
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			started_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			completed_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'running',
			duration float NOT NULL DEFAULT 0,
			request_context varchar(20) DEFAULT NULL,
			metrics_count int(11) NOT NULL DEFAULT 0,
			overhead_warning text DEFAULT NULL,
			target_url varchar(500) DEFAULT NULL,
			measurement_context varchar(20) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY snapshot_id (snapshot_id),
			KEY mode (mode),
			KEY status (status),
			KEY started_at (started_at)
		) {$charset_collate}";
	}
}
