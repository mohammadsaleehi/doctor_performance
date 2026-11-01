<?php
/**
 * Monitoring view for WP Performance Doctor admin.
 *
 * Displays collected metrics organized by category.
 *
 * @package WP-Performance-Doctor
 */

use WPPerformanceDoctor\Admin\Formatter\MetricFormatter;
use WPPerformanceDoctor\Monitoring\Metric;
?>
<div class="wrap wp-performance-doctor wpd-monitoring">

	<?php
	$enabled       = ! empty( $data['enabled'] );
	$monitoring    = array(
		'enabled'         => $data['enabled'] ?? false,
		'interval'        => $data['interval'] ?? 0,
		'last_collection' => $data['last_collection'] ?? null,
		'last_status'     => $data['last_status'] ?? null,
		'duration'        => $data['collection_duration'] ?? null,
		'next_collection' => $data['next_collection'] ?? null,
	);
	$snapshots = $data['snapshots'] ?? array();
	$system    = $data['system'] ?? array();
	$wordpress = $data['wordpress'] ?? array();
	$database  = $data['database'] ?? array();
	$cache     = $data['cache'] ?? array();
	$php       = $data['php'] ?? array();
	$frontend  = $data['frontend'] ?? array();
	?>

	<!-- Page header -->
	<header class="wpd-page-header wpd-page-header--stacked">
		<div class="wpd-header-brand">
			<div class="wpd-header-title">
				<?php echo ''; // wpd_icon placeholder ?>
				<?php esc_html_e( 'Monitoring', 'wp-performance-doctor' ); ?>
			</div>
			<p class="wpd-header-description">
				<?php esc_html_e( 'Real-time performance metrics collected from your WordPress installation.', 'wp-performance-doctor' ); ?>
			</p>
		</div>