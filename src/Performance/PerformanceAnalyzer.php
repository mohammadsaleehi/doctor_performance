<?php
/**
 * Performance bottleneck analyzer for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Performance;

use WPPerformanceDoctor\Rules\Severity;

/**
 * Analyzes collected performance metrics and identifies likely bottlenecks.
 *
 * Each identified issue contains a title, category, severity, confidence,
 * evidence, actual measured values, expected/comparison values (when
 * justified), explanation, likely impact, possible causes, recommended next
 * steps, verification method, and measurement limitations.
 *
 * Never generates an issue solely because a metric is missing. Uses distinct
 * states: pass, warning, fail, unavailable, unsupported.
 *
 * Web Vitals thresholds follow established guidance:
 * - LCP: Good < 2.5s, Needs Improvement 2.5s-4s, Poor > 4s
 * - CLS: Good < 0.1, Needs Improvement 0.1-0.25, Poor > 0.25
 * - INP: Good < 200ms, Needs Improvement 200-500ms, Poor > 500ms
 * - TTFB: Good < 800ms, Needs Improvement 800ms-1.8s, Poor > 1.8s
 */
final class PerformanceAnalyzer {

	/**
	 * Web Vitals rating thresholds.
	 *
	 * @var array<string, array{good: float, needs_improvement: float}>
	 */
	private const VITAL_THRESHOLDS = array(
		'frontend.largest_contentful_paint'  => array(
			'good'              => 2500,
			'needs_improvement' => 4000,
			'unit'              => 'ms',
			'lower_is_better'   => true,
		),
		'frontend.cumulative_layout_shift'   => array(
			'good'              => 0.1,
			'needs_improvement' => 0.25,
			'unit'              => 'score',
			'lower_is_better'   => true,
		),
		'frontend.interaction_to_next_paint' => array(
			'good'              => 200,
			'needs_improvement' => 500,
			'unit'              => 'ms',
			'lower_is_better'   => true,
		),
		'frontend.navigation_timing_ttfb'    => array(
			'good'              => 800,
			'needs_improvement' => 1800,
			'unit'              => 'ms',
			'lower_is_better'   => true,
		),
	);

	/**
	 * Analyze performance metrics and return bottleneck findings.
	 *
	 * @param array $metrics Array of metric data arrays (keyed by metric identifier).
	 * @return array Array of finding data arrays.
	 */
	public function analyze( array $metrics ): array {
		$findings = array();

		$f_mem     = $this->analyze_php_memory( $metrics );
		$f_opc     = $this->analyze_opcache( $metrics );
		$f_db      = $this->analyze_database_queries( $metrics );
		$f_cache   = $this->analyze_cache( $metrics );
		$f_load    = $this->analyze_system_load( $metrics );
		$f_front   = $this->analyze_frontend_assets( $metrics );
		$f_browser = $this->analyze_browser_metrics( $metrics );

		if ( $f_mem ) {
			$findings[] = $f_mem; }
		if ( $f_opc ) {
			$findings[] = $f_opc; }
		if ( $f_db ) {
			$findings[] = $f_db; }
		if ( $f_cache ) {
			$findings[] = $f_cache; }
		if ( $f_load ) {
			$findings[] = $f_load; }
		if ( $f_front ) {
			$findings[] = $f_front; }
		if ( ! empty( $f_browser ) ) {
			$findings = array_merge( $findings, $f_browser );
		}

		return $findings;
	}

	/**
	 * Analyze PHP memory limit vs actual usage.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_php_memory( array $metrics ): ?array {
		$limit = $metrics['php.memory_limit'] ?? null;
		$usage = $metrics['php.memory_usage'] ?? null;
		$peak  = $metrics['php.peak_memory_usage'] ?? null;

		if ( null === $limit ) {
			return null;
		}

		// Only flag when there's evidence of actual memory pressure:
		// peak usage at or above 90% of the configured limit.
		$pressure = false;
		$ratio    = null;
		if ( is_numeric( $peak ) && is_numeric( $limit ) && 0 < (float) $limit ) {
			$ratio = (float) $peak / (float) $limit;
			if ( $ratio >= 0.90 ) {
				$pressure = true;
			}
		}

		if ( $pressure ) {
			return $this->finding(
				array(
					'id'                      => 'perf.php_memory_pressure',
					'title'                   => __( 'High PHP Memory Usage Relative to Limit', 'wp-performance-doctor' ),
					'category'                => 'php',
					'severity'                => Severity::MEDIUM,
					'confidence'              => 'medium',
					'description'             => sprintf(
					/* translators: %d is peak memory usage percentage of limit. */
						__( 'Peak PHP memory usage is %d%% of the configured memory limit.', 'wp-performance-doctor' ),
						round( $ratio * 100 )
					),
					'impact'                  => __( 'The site may experience memory exhaustion during memory-intensive operations such as plugin updates, bulk imports, or large media processing.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'php.memory_limit'      => $limit,
						'php.memory_usage'      => $usage,
						'php.peak_memory_usage' => $peak,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Review which operations consume the most memory (plugins, themes, imports).', 'wp-performance-doctor' ),
							__( 'Consider increasing PHP memory_limit if headroom is consistently below 10%.', 'wp-performance-doctor' ),
							__( 'Contact your hosting provider if you cannot modify server-level PHP configuration.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Based on peak memory usage recorded during a single request snapshot; may not capture worst-case scenarios.', 'wp-performance-doctor' ),
				)
			);
		}

		// No finding when memory usage is within reasonable bounds.
		return null;
	}

	/**
	 * Analyze OPcache status.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_opcache( array $metrics ): ?array {
		$available = $metrics['php.opcache_available'] ?? null;
		$enabled   = $metrics['php.opcache_enabled'] ?? null;

		if ( null === $available ) {
			return null;
		}

		if ( ! $available ) {
			return $this->finding(
				array(
					'id'                      => 'perf.opcache_unavailable',
					'title'                   => __( 'OPcache Not Available', 'wp-performance-doctor' ),
					'category'                => 'php',
					'severity'                => Severity::INFO,
					'confidence'              => 'high',
					'description'             => __( 'OPcache is not installed or not available in this PHP environment.', 'wp-performance-doctor' ),
					'impact'                  => __( 'Without OPcache, PHP must recompile scripts on every request, which may increase CPU usage and response time.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'php.opcache_available' => $available,
						'php.opcache_enabled'   => $enabled,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Install and enable the OPcache extension for your PHP version.', 'wp-performance-doctor' ),
							__( 'Contact your hosting provider if you cannot install PHP extensions.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Reports extension availability only; does not measure actual cache hit rate.', 'wp-performance-doctor' ),
				)
			);
		}

		if ( $available && ! $enabled ) {
			return $this->finding(
				array(
					'id'                      => 'perf.opcache_disabled',
					'title'                   => __( 'OPcache Is Disabled', 'wp-performance-doctor' ),
					'category'                => 'php',
					'severity'                => Severity::MEDIUM,
					'confidence'              => 'high',
					'description'             => __( 'OPcache is installed but disabled in the PHP configuration.', 'wp-performance-doctor' ),
					'impact'                  => __( 'PHP scripts are recompiled on every request, which may increase CPU usage and slow page loads.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'php.opcache_available' => $available,
						'php.opcache_enabled'   => $enabled,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Enable OPcache by setting opcache.enable=1 in php.ini.', 'wp-performance-doctor' ),
							__( 'Restart Apache or PHP-FPM after making the change.', 'wp-performance-doctor' ),
							__( 'Contact your hosting provider if you cannot modify server-level PHP configuration.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Reports configuration status only; does not measure actual performance impact.', 'wp-performance-doctor' ),
				)
			);
		}

		if ( $available && $enabled ) {
			$hit_rate = $metrics['php.opcache_hit_rate'] ?? null;
			if ( is_numeric( $hit_rate ) && (float) $hit_rate < 0.90 ) {
				return $this->finding(
					array(
						'id'                      => 'perf.opcache_low_hit_rate',
						'title'                   => __( 'Low OPcache Hit Rate', 'wp-performance-doctor' ),
						'category'                => 'php',
						'severity'                => Severity::INFO,
						'confidence'              => 'low',
						'description'             => sprintf(
						/* translators: %d is OPcache hit rate percentage. */
							__( 'OPcache hit rate is %d%%, below the desirable 90%% threshold.', 'wp-performance-doctor' ),
							round( $hit_rate * 100 )
						),
						'impact'                  => __( 'A low hit rate means OPcache may be evicting scripts frequently, reducing its performance benefit.', 'wp-performance-doctor' ),
						'evidence'                => array(
							'php.opcache_hit_rate' => $hit_rate,
						),
						'remediation'             => array(
							'steps'                  => array(
								__( 'Increase opcache.memory_consumption in php.ini to cache more scripts.', 'wp-performance-doctor' ),
								__( 'Review which scripts are being evicted most frequently.', 'wp-performance-doctor' ),
							),
							'risk'                   => 'low',
							'requires_manual_action' => true,
							'difficulty'             => 'medium',
						),
						'measurement_limitations' => __( 'Hit rate varies by traffic pattern; single snapshot may not be representative.', 'wp-performance-doctor' ),
					)
				);
			}
		}

		return null;
	}

	/**
	 * Analyze database query profiling data.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_database_queries( array $metrics ): ?array {
		$savequeries = $metrics['database.savequeries_enabled'] ?? null;

		if ( null === $savequeries || ! $savequeries ) {
			return null;
		}

		$slow_count   = $metrics['database.slow_query_count'] ?? null;
		$total_time   = $metrics['database.query_time_total'] ?? null;
		$query_count  = $metrics['database.query_count'] ?? null;
		$slow_queries = $metrics['database.slow_queries'] ?? array();

		if ( null === $slow_count ) {
			return null;
		}

		if ( $slow_count > 0 ) {
			$slowest = null;
			if ( is_array( $slow_queries ) && ! empty( $slow_queries ) ) {
				usort(
					$slow_queries,
					function ( $a, $b ) {
						return (float) ( $b['duration'] ?? 0 ) <=> (float) ( $a['duration'] ?? 0 );
					}
				);
				$slowest = $slow_queries[0];
			}

			return $this->finding(
				array(
					'id'                      => 'perf.database_slow_queries',
					'title'                   => __( 'Slow Database Queries Detected', 'wp-performance-doctor' ),
					'category'                => 'database',
					'severity'                => Severity::MEDIUM,
					'confidence'              => 'medium',
					'description'             => sprintf(
					/* translators: %d is slow query count. */
						__( '%d slow queries detected (exceeding 0.5 second threshold).', 'wp-performance-doctor' ),
						$slow_count
					),
					'impact'                  => __( 'Slow database queries increase page load times and can cause database connection exhaustion under load.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'database.slow_query_count' => $slow_count,
						'database.query_time_total' => $total_time,
						'database.query_count'      => $query_count,
						'database.slow_queries'     => $slowest,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Identify the slowest queries using the recorded query fingerprints.', 'wp-performance-doctor' ),
							__( 'Add database indexes for frequently queried columns.', 'wp-performance-doctor' ),
							__( 'Review plugin and theme database queries for optimization opportunities.', 'wp-performance-doctor' ),
							__( 'Enable MySQL slow query log for deeper analysis.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'hard',
					),
					'measurement_limitations' => __( 'Requires SAVEQUERIES to be enabled; only captures queries during the profiled request.', 'wp-performance-doctor' ),
				)
			);
		}

		return null;
	}

	/**
	 * Analyze cache configuration.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_cache( array $metrics ): ?array {
		$persistent = $metrics['cache.persistent_object_cache'] ?? null;
		$external   = $metrics['cache.object_cache_available'] ?? null;

		if ( null === $persistent ) {
			return null;
		}

		// Absence of persistent cache is an informational hint, not a bottleneck.
		if ( ! $persistent && $external ) {
			return $this->finding(
				array(
					'id'                      => 'perf.cache_no_persistent_backend',
					'title'                   => __( 'No Persistent Object Cache Backend', 'wp-performance-doctor' ),
					'category'                => 'cache',
					'severity'                => Severity::INFO,
					'confidence'              => 'high',
					'description'             => __( 'WordPress object cache is active but using the built-in in-memory implementation, not a persistent backend.', 'wp-performance-doctor' ),
					'impact'                  => __( 'Object cache data is lost between requests, reducing the benefit of caching for repeated database queries.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'cache.object_cache_available'  => $external,
						'cache.persistent_object_cache' => $persistent,
						'cache.object_cache_type'       => $metrics['cache.object_cache_type'] ?? null,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Install and configure a persistent object cache drop-in (e.g., Redis or Memcached).', 'wp-performance-doctor' ),
							__( 'Confirm your hosting provider supports persistent object caching before installing.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Detects cache backend class only; does not measure cache hit/miss ratios.', 'wp-performance-doctor' ),
				)
			);
		}

		return null;
	}

	/**
	 * Analyze system load average.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_system_load( array $metrics ): ?array {
		$load = $metrics['system.load_average'] ?? null;

		if ( null === $load ) {
			return null;
		}

		$cpu_count = null;
		if ( function_exists( 'shell_exec' ) ) {
			$output = shell_exec( 'nproc' );
			if ( is_string( $output ) && ctype_digit( trim( $output ) ) ) {
				$cpu_count = (int) trim( $output );
			}
		}

		if ( null !== $cpu_count && $load > $cpu_count * 2.0 ) {
			return $this->finding(
				array(
					'id'                      => 'perf.system_high_load',
					'title'                   => __( 'High System Load Average', 'wp-performance-doctor' ),
					'category'                => 'system',
					'severity'                => Severity::LOW,
					'confidence'              => 'low',
					'description'             => sprintf(
					/* translators: %s is load average, %d is CPU count. */
						__( 'System load average is %1$s on a system with %2$d CPU(s), indicating possible resource contention.', 'wp-performance-doctor' ),
						number_format( $load, 2 ),
						$cpu_count
					),
					'impact'                  => __( 'High load can slow all requests, including WordPress page loads and admin operations.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'system.load_average' => $load,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Identify processes consuming CPU using system monitoring tools.', 'wp-performance-doctor' ),
							__( 'Contact your hosting provider if load remains consistently high.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Load average is not CPU utilization percentage; a single snapshot may not represent sustained load.', 'wp-performance-doctor' ),
				)
			);
		}

		return null;
	}

	/**
	 * Analyze frontend asset counts.
	 *
	 * @param array $metrics Metric data.
	 * @return array|null Finding or null.
	 */
	private function analyze_frontend_assets( array $metrics ): ?array {
		$total = $metrics['frontend.total_assets'] ?? null;

		if ( null === $total ) {
			return null;
		}

		// Asset count alone is not evidence of slowness - only flag when excessive.
		if ( $total > 50 ) {
			return $this->finding(
				array(
					'id'                      => 'perf.frontend_many_assets',
					'title'                   => __( 'Many Frontend Assets Enqueued', 'wp-performance-doctor' ),
					'category'                => 'frontend',
					'severity'                => Severity::INFO,
					'confidence'              => 'low',
					'description'             => sprintf(
					/* translators: %d is total asset count. */
						__( '%d frontend assets (CSS + JavaScript) are enqueued on this page.', 'wp-performance-doctor' ),
						$total
					),
					'impact'                  => __( 'Many frontend assets may increase page load time through additional HTTP requests and parsing overhead.', 'wp-performance-doctor' ),
					'evidence'                => array(
						'frontend.styles_count'         => $metrics['frontend.styles_count'] ?? null,
						'frontend.scripts_count'        => $metrics['frontend.scripts_count'] ?? null,
						'frontend.external_asset_count' => $metrics['frontend.external_asset_count'] ?? null,
						'frontend.total_assets'         => $total,
					),
					'remediation'             => array(
						'steps'                  => array(
							__( 'Review which plugins enqueue the most assets.', 'wp-performance-doctor' ),
							__( 'Combine or defer non-critical CSS and JavaScript where appropriate.', 'wp-performance-doctor' ),
							__( 'Load assets conditionally only on pages where they are needed.', 'wp-performance-doctor' ),
						),
						'risk'                   => 'low',
						'requires_manual_action' => true,
						'difficulty'             => 'medium',
					),
					'measurement_limitations' => __( 'Counts enqueued assets only; does not measure actual file sizes or load times.', 'wp-performance-doctor' ),
				)
			);
		}

		return null;
	}

	/**
	 * Analyze browser-collected performance metrics (Core Web Vitals, etc.).
	 *
	 * Produces one evidence-based finding per browser metric that falls below
	 * its Web Vitals rating threshold. 'Good' ratings and unavailable metrics
	 * never produce a finding.
	 *
	 * @param array $metrics Metric data keyed by metric identifier.
	 * @return array Findings (possibly empty).
	 */
	private function analyze_browser_metrics( array $metrics ): array {
		$findings = array();

		foreach ( self::VITAL_THRESHOLDS as $metric_id => $threshold ) {
			if ( ! isset( $metrics[ $metric_id ] ) || 'available' !== ( $metrics[ $metric_id ]['availability_status'] ?? '' ) ) {
				continue;
			}

			$value = $metrics[ $metric_id ]['metric_value'] ?? null;
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$rating = $this->rate_vital( $value, $threshold );
			if ( 'good' === $rating ) {
				continue;
			}

			$finding = $this->browser_vital_finding( $metric_id, $value, $threshold, $rating, $metrics );
			if ( $finding ) {
				$findings[] = $finding;
			}
		}

		$resource = $this->analyze_resource_loading( $metrics );
		if ( $resource ) {
			$findings[] = $resource;
		}

		return $findings;
	}

	/**
	 * Build an evidence-based finding for a single Web Vitals metric.
	 *
	 * @param string $metric_id  Metric identifier.
	 * @param float  $value      Measured value.
	 * @param array  $threshold  Threshold configuration.
	 * @param string $rating     'needs_improvement' or 'poor'.
	 * @param array  $metrics    All metrics (for context).
	 * @return array|null Finding or null.
	 */
	private function browser_vital_finding( string $metric_id, float $value, array $threshold, string $rating, array $metrics ): ?array {
		$severity   = $this->rating_to_severity( $rating );
		// Single page-load browser measurements are indicative, not definitive.
		$confidence = 'medium';

		$title_map = array(
			'frontend.largest_contentful_paint'  => __( 'Slow Largest Contentful Paint', 'wp-performance-doctor' ),
			'frontend.cumulative_layout_shift'   => __( 'High Cumulative Layout Shift', 'wp-performance-doctor' ),
			'frontend.interaction_to_next_paint' => __( 'Slow Interaction to Next Paint', 'wp-performance-doctor' ),
			'frontend.navigation_timing_ttfb'    => __( 'Slow Time to First Byte', 'wp-performance-doctor' ),
		);

		$desc_map = array(
			'frontend.largest_contentful_paint'  => __( 'LCP measures when the largest content element becomes visible. A slow LCP indicates the page takes too long to render its main content.', 'wp-performance-doctor' ),
			'frontend.cumulative_layout_shift'   => __( 'CLS measures unexpected layout shifts during page load. A high CLS indicates visual instability that can frustrate users.', 'wp-performance-doctor' ),
			'frontend.interaction_to_next_paint' => __( 'INP measures the latency of all interactions with the page. A high INP indicates the page is slow to respond to user input.', 'wp-performance-doctor' ),
			'frontend.navigation_timing_ttfb'    => __( 'TTFB measures the time from the user request to the first byte of the response. A slow TTFB can indicate server or network issues.', 'wp-performance-doctor' ),
		);

		$impact_map = array(
			'frontend.largest_contentful_paint'  => __( 'Users may perceive the page as slow to load, increasing bounce rates.', 'wp-performance-doctor' ),
			'frontend.cumulative_layout_shift'   => __( 'Unexpected layout shifts can cause users to click the wrong elements, leading to poor user experience.', 'wp-performance-doctor' ),
			'frontend.interaction_to_next_paint' => __( 'Slow interaction response makes the page feel unresponsive and frustrating to use.', 'wp-performance-doctor' ),
			'frontend.navigation_timing_ttfb'    => __( 'Slow server response time delays the start of page rendering, affecting all downstream metrics.', 'wp-performance-doctor' ),
		);

		$remediation_map = array(
			'frontend.largest_contentful_paint'  => array(
				__( 'Optimize server response time (TTFB) so content starts rendering sooner.', 'wp-performance-doctor' ),
				__( 'Serve critical resources early and defer non-critical third-party scripts.', 'wp-performance-doctor' ),
				__( 'Use browser DevTools to identify the LCP element and the resources blocking it.', 'wp-performance-doctor' ),
			),
			'frontend.cumulative_layout_shift'  => array(
				__( 'Reserve space for images, embeds, and ads with explicit width/height attributes.', 'wp-performance-doctor' ),
				__( 'Avoid inserting content above existing content unless triggered by user input.', 'wp-performance-doctor' ),
				__( 'Use browser DevTools to identify which elements shift and when.', 'wp-performance-doctor' ),
			),
			'frontend.interaction_to_next_paint' => array(
				__( 'Break up long JavaScript tasks that block the main thread.', 'wp-performance-doctor' ),
				__( 'Defer non-critical scripts and use event listeners that do not block interaction.', 'wp-performance-doctor' ),
				__( 'Use browser DevTools (Interaction to Next Paint) to identify slow interactions.', 'wp-performance-doctor' ),
			),
			'frontend.navigation_timing_ttfb'    => array(
				__( 'Investigate server-side response time (slow plugins, theme, or database queries).', 'wp-performance-doctor' ),
				__( 'Enable a persistent object cache or page cache where appropriate.', 'wp-performance-doctor' ),
				__( 'Confirm the result is not caused by the network between the browser and the server.', 'wp-performance-doctor' ),
			),
		);

		$context = $metrics[ $metric_id ]['measurement_context'] ?? 'unknown';
		$page_url = $metrics[ $metric_id ]['page_url'] ?? '';
		$collected_at = $metrics[ $metric_id ]['collected_at'] ?? '';

		$id_map = array(
			'frontend.largest_contentful_paint'  => 'perf.browser_lcp',
			'frontend.cumulative_layout_shift'   => 'perf.browser_cls',
			'frontend.interaction_to_next_paint' => 'perf.browser_inp',
			'frontend.navigation_timing_ttfb'    => 'perf.browser_ttfb',
		);

		return $this->finding(
			array(
				'id'                      => $id_map[ $metric_id ] ?? 'perf.browser_web_vitals',
				'title'                   => $title_map[ $metric_id ] ?? __( 'Web Vitals Issue Detected', 'wp-performance-doctor' ),
				'category'                => 'frontend',
				'severity'                => $severity,
				'confidence'              => $confidence,
				'description'             => $desc_map[ $metric_id ] ?? __( 'A browser performance metric is below the recommended threshold.', 'wp-performance-doctor' ),
				'impact'                  => $impact_map[ $metric_id ] ?? __( 'This issue may affect user experience and search rankings.', 'wp-performance-doctor' ),
				'evidence'                => array(
					$metric_id => array(
						'value'              => $value,
						'unit'               => $threshold['unit'],
						'threshold_good'     => $threshold['good'],
						'threshold_needs'    => $threshold['needs_improvement'],
						'rating'             => $rating,
						'measurement_context' => $context,
						'page_url'           => $page_url,
						'collected_at'       => $collected_at,
					),
				),
				'remediation'             => array(
					'steps'                  => $remediation_map[ $metric_id ] ?? array(
						__( 'Review the specific metric in the browser metrics section for details.', 'wp-performance-doctor' ),
						__( 'Use browser DevTools to identify contributing resources or scripts.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
				'measurement_limitations' => __( 'Browser metrics are collected from a single page load and may vary based on network conditions, caching, and the page being measured. This does not identify a specific plugin, theme, query, or server component as the cause.', 'wp-performance-doctor' ),
			)
		);
	}

	/**
	 * Analyze resource loading duration for excessive loading.
	 *
	 * @param array $metrics Metric data keyed by metric identifier.
	 * @return array|null Finding or null.
	 */
	private function analyze_resource_loading( array $metrics ): ?array {
		$duration = $metrics['frontend.resource_loading_duration'] ?? null;
		$count    = $metrics['frontend.resource_count'] ?? null;

		if ( ( ! isset( $duration['availability_status'] ) || 'available' !== $duration['availability_status'] )
			&& ( ! isset( $count['availability_status'] ) || 'available' !== $count['availability_status'] )
		) {
			return null;
		}

		$slow = false;
		$reasons = array();
		if ( isset( $duration['availability_status'] ) && 'available' === $duration['availability_status']
			&& is_numeric( $duration['metric_value'] ) && (float) $duration['metric_value'] > 5000
		) {
			$slow = true;
			$formatted = rtrim( rtrim( number_format( (float) $duration['metric_value'], 1 ), '0' ), '.' ) . ' ms';
			$reasons[] = sprintf(
				/* translators: %s: formatted resource load duration. */
				__( 'Slowest resource took %s to load.', 'wp-performance-doctor' ),
				$formatted
			);
		}

		if ( ! $slow ) {
			return null;
		}

		return $this->finding(
			array(
				'id'                      => 'perf.browser_slow_resources',
				'title'                   => __( 'Slow Browser Resource Loading', 'wp-performance-doctor' ),
				'category'                => 'frontend',
				'severity'                => Severity::LOW,
				'confidence'              => 'low',
				'description'             => __( 'One or more frontend resources took an unusually long time to load, which can delay page rendering.', 'wp-performance-doctor' ),
				'impact'                  => __( 'Slow resource loading can delay LCP and overall page load time.', 'wp-performance-doctor' ),
				'evidence'                => array(
					'frontend.resource_loading_duration' => array(
						'value'          => $duration['metric_value'] ?? null,
						'unit'           => $duration['unit'] ?? 'ms',
						'rating'         => 'needs_improvement',
					),
					'frontend.resource_count' => array(
						'value' => $count['metric_value'] ?? null,
						'unit'  => 'count',
					),
				),
				'remediation'             => array(
					'steps'                  => array(
						__( 'Use browser DevTools to identify the slowest resources.', 'wp-performance-doctor' ),
						__( 'Optimize or defer large third-party resources.', 'wp-performance-doctor' ),
						__( 'Serve images and fonts in optimized, compressed formats.', 'wp-performance-doctor' ),
					),
					'risk'                   => 'low',
					'requires_manual_action' => true,
					'difficulty'             => 'medium',
				),
				'measurement_limitations' => __( 'Resource duration is measured from a single page load and depends on network conditions and browser cache state.', 'wp-performance-doctor' ),
			)
		);
	}

	/**
	 * Rate a vital value against thresholds.
	 *
	 * @param float $value   Measured value.
	 * @param array $threshold Threshold configuration.
	 * @return string 'good', 'needs_improvement', or 'poor'.
	 */
	private function rate_vital( float $value, array $threshold ): string {
		if ( ! empty( $threshold['lower_is_better'] ) && $threshold['lower_is_better'] ) {
			if ( $value <= $threshold['good'] ) {
				return 'good';
			}
			if ( $value <= $threshold['needs_improvement'] ) {
				return 'needs_improvement';
			}
			return 'poor';
		}

		return 'good';
	}

	/**
	 * Compare two ratings and return true if $a is worse than $b.
	 *
	 * @param string $a Rating A.
	 * @param string $b Rating B.
	 * @return bool True if A is worse than B.
	 */
	private function rating_worse( string $a, string $b ): bool {
		$order = array(
			'good'              => 0,
			'needs_improvement' => 1,
			'poor'              => 2,
		);
		return $order[ $a ] > $order[ $b ];
	}

	/**
	 * Convert a rating to a severity level.
	 *
	 * @param string $rating Rating string.
	 * @return string Severity constant.
	 */
	private function rating_to_severity( string $rating ): string {
		return array(
			'good'              => Severity::INFO,
			'needs_improvement' => Severity::LOW,
			'poor'              => Severity::MEDIUM,
		)[ $rating ] ?? Severity::INFO;
	}

	/**
	 * Build a finding data array.
	 *
	 * @param array $args Finding arguments.
	 * @return array Finding data array.
	 */
	private function finding( array $args ): array {
		return array(
			'id'                      => $args['id'],
			'title'                   => $args['title'],
			'category'                => $args['category'],
			'severity'                => $args['severity'],
			'confidence'              => $args['confidence'],
			'description'             => $args['description'],
			'impact'                  => $args['impact'] ?? '',
			'evidence'                => $args['evidence'] ?? array(),
			'remediation'             => $args['remediation'] ?? array(),
			'measurement_limitations' => $args['measurement_limitations'] ?? '',
		);
	}
}
