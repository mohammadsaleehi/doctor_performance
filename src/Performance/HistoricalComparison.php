<?php
/**
 * Historical performance comparison service for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Performance;

/**
 * Compares performance metrics between two snapshots.
 *
 * Only compares metrics with the same identifier, compatible units, valid
 * availability status, and matching measurement context. Clearly labels
 * missing or unavailable values. Does not claim improvement based on
 * unrelated measurements.
 */
final class HistoricalComparison {

	/**
	 * Compare two snapshots by metric identifier.
	 *
	 * Metrics are only compared when they share the same identifier, unit,
	 * availability status, measurement context, and page URL (for frontend
	 * measurements). This prevents comparing an admin page measurement with
	 * a public frontend page measurement.
	 *
	 * @param array $previous Previous snapshot metrics (keyed by metric identifier).
	 * @param array $current  Current snapshot metrics (keyed by metric identifier).
	 * @return array Comparison results keyed by metric identifier.
	 */
	public function compare( array $previous, array $current ): array {
		$results = array();

		foreach ( $current as $metric_id => $current_metric ) {
			if ( ! isset( $previous[ $metric_id ] ) ) {
				continue;
			}

			$prev_metric = $previous[ $metric_id ];

			// Only compare if both are available.
			if ( 'available' !== $current_metric['availability_status']
				|| 'available' !== $prev_metric['availability_status']
			) {
				$results[ $metric_id ] = $this->comparison_result(
					$metric_id,
					$current_metric,
					$prev_metric,
					array(
						'both_available' => false,
						'reason'         => 'One or both measurements are unavailable.',
					)
				);
				continue;
			}

			// Only compare if units match.
			if ( $current_metric['unit'] !== $prev_metric['unit'] ) {
				$results[ $metric_id ] = $this->comparison_result(
					$metric_id,
					$current_metric,
					$prev_metric,
					array(
						'both_available' => false,
						'reason'         => 'Incompatible units: previous uses ' . esc_html( $prev_metric['unit'] ) . ', current uses ' . esc_html( $current_metric['unit'] ) . '.',
					)
				);
				continue;
			}

			// Only compare if measurement contexts match.
			$prev_context = $prev_metric['measurement_context'] ?? 'unknown';
			$curr_context = $current_metric['measurement_context'] ?? 'unknown';
			if ( $prev_context !== $curr_context ) {
				$results[ $metric_id ] = $this->comparison_result(
					$metric_id,
					$current_metric,
					$prev_metric,
					array(
						'both_available' => false,
						'reason'         => 'Incompatible measurement context: previous was ' . esc_html( $prev_context ) . ', current is ' . esc_html( $curr_context ) . '.',
					)
				);
				continue;
			}

			// For frontend measurements, only compare if page URLs match.
			$prev_url = $prev_metric['page_url'] ?? '';
			$curr_url = $current_metric['page_url'] ?? '';
			if ( 'frontend' === $curr_context && $prev_url !== $curr_url ) {
				$results[ $metric_id ] = $this->comparison_result(
					$metric_id,
					$current_metric,
					$prev_metric,
					array(
						'both_available' => false,
						'reason'         => 'Incompatible page URL: previous was ' . esc_html( $prev_url ) . ', current is ' . esc_html( $curr_url ) . '.',
					)
				);
				continue;
			}

			$prev_value = $this->coerce_numeric( $prev_metric['metric_value'] ?? null );
			$curr_value = $this->coerce_numeric( $current_metric['metric_value'] ?? null );

			if ( null === $prev_value || null === $curr_value ) {
				$results[ $metric_id ] = $this->comparison_result(
					$metric_id,
					$current_metric,
					$prev_metric,
					array(
						'both_available' => false,
						'reason'         => 'One or both values are not numeric.',
					)
				);
				continue;
			}

			$difference = $curr_value - $prev_value;
			$percent    = 0.0 === $prev_value ? null : ( $difference / $prev_value ) * 100.0;

			$results[ $metric_id ] = $this->comparison_result(
				$metric_id,
				$current_metric,
				$prev_metric,
				array(
					'both_available' => true,
					'difference'     => $difference,
					'percent_change' => $percent,
					'previous'       => $prev_value,
					'current'        => $curr_value,
					'unit'           => $current_metric['unit'],
					'context'        => $curr_context,
					'page_url'       => $curr_url,
				)
			);
		}

		return $results;
	}

	/**
	 * Compare current snapshot against the previous snapshot.
	 *
	 * @param \WPPerformanceDoctor\Monitoring\Storage $storage Storage instance.
	 * @param int                                     $current_snapshot_id Current snapshot ID.
	 * @return array{previous_snapshot: array|null, comparisons: array}
	 */
	public function compare_with_previous( \WPPerformanceDoctor\Monitoring\Storage $storage, int $current_snapshot_id ): array {
		$current = $storage->get_snapshot( $current_snapshot_id );
		if ( null === $current ) {
			return array(
				'previous_snapshot' => null,
				'comparisons'       => array(),
			);
		}

		$snapshots = $storage->get_snapshots( 1, 2 );
		$previous  = null;
		foreach ( $snapshots['items'] as $snap ) {
			if ( (int) $snap['id'] !== $current_snapshot_id ) {
				$previous = $storage->get_snapshot( (int) $snap['id'] );
				break;
			}
		}

		$previous_metrics = array();
		if ( $previous ) {
			foreach ( $previous['metrics'] as $m ) {
				$previous_metrics[ $m['metric_identifier'] ] = $m;
			}
		}

		$current_metrics = array();
		foreach ( $current['metrics'] as $m ) {
			$current_metrics[ $m['metric_identifier'] ] = $m;
		}

		$comparisons = $this->compare( $previous_metrics, $current_metrics );

		return array(
			'previous_snapshot' => $previous,
			'comparisons'       => $comparisons,
		);
	}

	/**
	 * Compare current snapshot against a selected historical snapshot.
	 *
	 * @param \WPPerformanceDoctor\Monitoring\Storage $storage Storage instance.
	 * @param int                                     $current_snapshot_id Current snapshot ID.
	 * @param int                                     $historical_snapshot_id Historical snapshot ID.
	 * @return array{previous_snapshot: array|null, comparisons: array}
	 */
	public function compare_with_historical( \WPPerformanceDoctor\Monitoring\Storage $storage, int $current_snapshot_id, int $historical_snapshot_id ): array {
		$current    = $storage->get_snapshot( $current_snapshot_id );
		$historical = $storage->get_snapshot( $historical_snapshot_id );

		if ( null === $current || null === $historical ) {
			return array(
				'previous_snapshot' => null,
				'comparisons'       => array(),
			);
		}

		$historical_metrics = array();
		foreach ( $historical['metrics'] as $m ) {
			$historical_metrics[ $m['metric_identifier'] ] = $m;
		}

		$current_metrics = array();
		foreach ( $current['metrics'] as $m ) {
			$current_metrics[ $m['metric_identifier'] ] = $m;
		}

		$comparisons = $this->compare( $historical_metrics, $current_metrics );

		return array(
			'previous_snapshot' => $historical,
			'comparisons'       => $comparisons,
		);
	}

	/**
	 * Build a comparison result array.
	 *
	 * @param string $metric_id      Metric identifier.
	 * @param array  $current_metric Current metric data.
	 * @param array  $prev_metric    Previous metric data.
	 * @param array  $extra          Extra fields (difference, percent_change, reason, etc.).
	 * @return array Comparison result.
	 */
	private function comparison_result( string $metric_id, array $current_metric, array $prev_metric, array $extra ): array {
		return array(
			'metric_identifier'  => $metric_id,
			'current'            => $this->format_value( $current_metric['metric_value'] ?? null ),
			'previous'           => $this->format_value( $prev_metric['metric_value'] ?? null ),
			'unit'               => $current_metric['unit'] ?? '',
			'available'          => isset( $extra['both_available'] ) && $extra['both_available'],
			'difference'         => $extra['difference'] ?? null,
			'percent_change'     => $extra['percent_change'] ?? null,
			'reason'             => $extra['reason'] ?? null,
			'current_timestamp'  => $current_metric['collected_at'] ?? null,
			'previous_timestamp' => $prev_metric['collected_at'] ?? null,
			'improved'           => $this->is_improved( $metric_id, $extra['difference'] ?? null ),
			'context'            => $extra['context'] ?? null,
			'page_url'           => $extra['page_url'] ?? null,
		);
	}

	/**
	 * Coerce a value to float or null.
	 *
	 * @param mixed $value Value to coerce.
	 * @return float|null
	 */
	private function coerce_numeric( $value ): ?float {
		if ( null === $value ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$parsed = (float) $value;
			return 0.0 === $parsed && '0' !== $value ? null : $parsed;
		}
		return null;
	}

	/**
	 * Format a metric value for display.
	 *
	 * @param mixed $value Value.
	 * @return string|float|null Formatted value.
	 */
	private function format_value( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		return (string) $value;
	}

	/**
	 * Determine whether a difference represents improvement.
	 *
	 * Heuristic: lower is better for durations, memory, and disk usage.
	 * Higher is better for hit rates.
	 *
	 * @param string     $metric_id  Metric identifier.
	 * @param float|null $difference Difference (current - previous).
	 * @return bool|null True if improved, false if worsened, null if unknown.
	 */
	private function is_improved( string $metric_id, ?float $difference ): ?bool {
		if ( null === $difference ) {
			return null;
		}

		// No meaningful change: neither improved nor worsened.
		if ( 0.0 === $difference ) {
			return null;
		}

		// Lower is better metrics.
		$lower_is_better_prefixes = array(
			'php.memory_usage',
			'php.peak_memory_usage',
			'php.request_duration',
			'system.load_average',
			'database.query_time_total',
			'database.slow_query_count',
			'frontend.total_assets',
			'frontend.external_asset_count',
			'frontend.navigation_timing_dns',
			'frontend.navigation_timing_tcp',
			'frontend.navigation_timing_tls',
			'frontend.navigation_timing_ttfb',
			'frontend.navigation_timing_response_download',
			'frontend.navigation_timing_dom_interactive',
			'frontend.navigation_timing_dom_content_loaded',
			'frontend.navigation_timing_dom_complete',
			'frontend.navigation_timing_page_load',
			'frontend.navigation_timing_redirect',
			'frontend.resource_loading_duration',
			'frontend.largest_contentful_paint',
			'frontend.cumulative_layout_shift',
			'frontend.interaction_to_next_paint',
		);

		foreach ( $lower_is_better_prefixes as $prefix ) {
			if ( str_starts_with( $metric_id, $prefix ) ) {
				return $difference < 0;
			}
		}

		// Higher is better metrics.
		if ( str_starts_with( $metric_id, 'php.opcache_hit_rate' ) ) {
			return $difference > 0;
		}

		return null;
	}
}
