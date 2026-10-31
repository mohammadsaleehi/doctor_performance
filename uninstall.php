<?php
/**
 * Uninstallation routine for WP Performance Doctor.
 *
 * @package WP-Performance-Doctor
 */

namespace WPPerformanceDoctor\Core;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

Uninstall::run();
