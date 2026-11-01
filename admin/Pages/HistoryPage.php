<?php
/**
 * History page for WP Performance Doctor admin.
 *
 * Displays historical monitoring snapshots and diagnostic runs.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Admin\Pages;

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Storage;

/**
 * History page displaying historical monitoring and diagnostic records.
 */
final class HistoryPage {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Storage instance.
	 *
	 * @var Storage|null
	 */
	private ?Storage $storage = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Set storage instance.
	 *
	 * @param Storage $storage Storage instance.
	 * @return self
	 */
	public function set_storage( Storage $storage ): self {
		$this->storage = $storage;
		return $this;
	}

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
	 * Render the history page.
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_history_data();

		require \WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH . '/admin/Views/history.php';
	}

	/**
	 * Get history data from storage.
	 *
	 * @return array<string, mixed>
	 */
	private function get_history_data(): array {
		$data = array(
			'snapshots' => array(),
			'runs'      => array(),
			'has_data'  => false,
		);

		if ( $this->storage ) {
			try {
				$snapshot_result = $this->storage->get_snapshots( 1, 20 );
				$data['snapshots'] = $snapshot_result['items'] ?? array();
			} catch ( \Throwable $e ) {
				$data['snapshots'] = array();
			}

			try {
				$data['runs'] = $this->storage->get_diagnostic_runs( 1, 20 );
			} catch ( \Throwable $e ) {
				$data['runs'] = array();
			}

			$data['has_data'] = ! empty( $data['snapshots'] ) || ! empty( $data['runs'] );
		}

		return $data;
	}
}
