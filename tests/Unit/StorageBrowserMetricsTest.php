<?php
/**
 * Storage browser metrics tests for WP Performance Doctor.
 *
 * Verifies save_browser_metrics() validation: explicit metric allowlist,
 * session/snapshot association, URL sanitization, and numeric boundaries.
 * Uses a stub wpdb since these tests run without a full WordPress load.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Tests\Unit;

use WPPerformanceDoctor\Monitoring\Storage;
use PHPUnit\Framework\TestCase;

/**
 * StorageBrowserMetricsTest class.
 *
 * @covers \WPPerformanceDoctor\Monitoring\Storage
 * @coversNothing
 */
final class StorageBrowserMetricsTest extends TestCase
{

	/**
	 * A minimal wpdb stub sufficient for save_browser_metrics().
	 *
	 * @param array|null $session Session row returned by get_profiling_session.
	 * @return WpdbStub
	 */
	private static function wpdb_stub( ?array $session = null ): WpdbStub {
		return new WpdbStub( $session );
	}

	/**
	 * Set the global wpdb stub for the duration of a test.
	 *
	 * @param WpdbStub $stub wpdb stub.
	 * @return object Previous wpdb value (or null).
	 */
	private static function set_wpdb( WpdbStub $stub ) {
		global $wpdb;
		$previous = $wpdb ?? null;
		$GLOBALS['wpdb'] = $stub;
		return $previous;
	}

	/**
	 * Restore the global wpdb.
	 *
	 * @param object|null $previous Previous wpdb value.
	 * @return void
	 */
	private static function restore_wpdb( $previous ) {
		global $wpdb;
		if ( null === $previous ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $previous;
		}
	}

	/**
	 * Assert valid browser metrics are stored and associated with the session snapshot.
	 */
	public function test_valid_metrics_stored_with_session_association(): void
	{
		$session = array(
			'id'          => 7,
			'snapshot_id' => 12,
			'status'      => 'running',
		);
		$wpdb = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics(
				7,
				array(
					'frontend.navigation_timing_ttfb' => array(
						'value'               => 350,
						'unit'                => 'ms',
						'category'            => 'frontend',
						'availability_status' => 'available',
						'page_url'            => 'https://example.com/',
						'measurement_context' => 'frontend',
						'metadata'            => array(),
					),
					'frontend.largest_contentful_paint' => array(
						'value'               => 2100,
						'unit'                => 'ms',
						'category'            => 'frontend',
						'availability_status' => 'available',
						'page_url'            => 'https://example.com/',
						'measurement_context' => 'frontend',
						'metadata'            => array(),
					),
				),
				'12'
			);

			$this->assertSame( 2, $stored );
			$this->assertTrue( $wpdb->query_called ?? false );
			$this->assertStringContainsString( 'page_url, measurement_context', $wpdb->last_sql );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert an unknown session returns false.
	 */
	public function test_unknown_session_returns_false(): void
	{
		$wpdb = self::wpdb_stub( null );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics( 999, array( 'frontend.resource_count' => array( 'value' => 5, 'unit' => 'count' ) ), '1' );
			$this->assertFalse( $stored );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert a snapshot_id that does not match the session returns false.
	 */
	public function test_snapshot_mismatch_returns_false(): void
	{
		$session = array( 'id' => 7, 'snapshot_id' => 12, 'status' => 'running' );
		$wpdb    = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics( 7, array( 'frontend.resource_count' => array( 'value' => 5, 'unit' => 'count' ) ), '99' );
			$this->assertFalse( $stored );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert unsupported metric identifiers are skipped.
	 */
	public function test_unsupported_metric_skipped(): void
	{
		$session = array( 'id' => 7, 'snapshot_id' => 12, 'status' => 'running' );
		$wpdb    = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics(
				7,
				array(
					'frontend.resource_count'  => array( 'value' => 5, 'unit' => 'count' ),
					'frontend.not_allowed_metric' => array( 'value' => 1, 'unit' => 'ms' ),
					'php.memory_limit'         => array( 'value' => 1, 'unit' => 'bytes' ),
				),
				'12'
			);
			// Only the allowed metric is stored.
			$this->assertSame( 1, $stored );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert negative and non-finite numeric values are skipped.
	 */
	public function test_invalid_numeric_values_skipped(): void
	{
		$session = array( 'id' => 7, 'snapshot_id' => 12, 'status' => 'running' );
		$wpdb    = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics(
				7,
				array(
					'frontend.navigation_timing_ttfb' => array( 'value' => -50, 'unit' => 'ms' ),
					'frontend.largest_contentful_paint' => array( 'value' => NAN, 'unit' => 'ms' ),
					'frontend.resource_count'         => array( 'value' => 10, 'unit' => 'count' ),
				),
				'12'
			);
			$this->assertSame( 1, $stored );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert resource URLs in metadata are sanitized (query secrets stripped).
	 */
	public function test_resource_urls_sanitized(): void
	{
		$session = array( 'id' => 7, 'snapshot_id' => 12, 'status' => 'running' );
		$wpdb    = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics(
				7,
				array(
					'frontend.resource_count' => array(
						'value'               => 3,
						'unit'                => 'count',
						'page_url'            => 'https://example.com/',
						'measurement_context' => 'frontend',
						'metadata'            => array(
							'resource_urls' => array(
								'https://example.com/style.css',
								'https://example.com/script.js?token=secret123',
								'https://tracker.example.com/collect?session=abc',
							),
						),
					),
				),
				'12'
			);
			$this->assertSame( 1, $stored );
			// esc_url_raw removes the query string. Verify no 'token=' or 'session=' stored.
			$this->assertStringNotContainsString( 'token=', $wpdb->last_sql );
			$this->assertStringNotContainsString( 'session=', $wpdb->last_sql );
		} finally {
			self::restore_wpdb( $previous );
		}
	}

	/**
	 * Assert an empty metrics array stores zero metrics.
	 */
	public function test_empty_metrics_stores_zero(): void
	{
		$session = array( 'id' => 7, 'snapshot_id' => 12, 'status' => 'running' );
		$wpdb    = self::wpdb_stub( $session );
		$previous = self::set_wpdb( $wpdb );
		try {
			$storage = new Storage( '0.1.0' );
			$stored  = $storage->save_browser_metrics( 7, array(), '12' );
			$this->assertSame( 0, $stored );
		} finally {
			self::restore_wpdb( $previous );
		}
	}
}

/**
 * Minimal wpdb stub sufficient for Storage::save_browser_metrics().
 */
	final class WpdbStub
	{
		public string $prefix = 'wp_';
		public ?array $session = null;
		public string $last_sql = '';
		public bool $query_called = false;
		public bool $query_result = true;
		public int $insert_id = 1;

		public function __construct( ?array $session = null ) {
			$this->session = $session;
		}

		public function get_row( $sql, $type = '' ): ?array {
		return $this->session;
	}

	public function prepare( $query, ...$args ): string {
		$this->last_sql = $query;
		$index = 0;
		return preg_replace_callback(
			'/(%s|%d)/',
			static function ( $m ) use ( &$index, $args ) {
				$arg = $args[ $index ] ?? null;
				++$index;
				if ( '%d' === $m[1] ) {
					return (string) (int) $arg;
				}
				return "'" . addslashes( (string) $arg ) . "'";
			},
			$query
		);
	}

	public function query( $sql ): bool {
		$this->query_called = true;
		return $this->query_result;
	}
}
