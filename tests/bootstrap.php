<?php
/**
 * Test bootstrap for WP Performance Doctor.
 *
 * Loads the Composer autoloader and defines plugin constants that would
 * normally be defined by the main plugin file. WordPress is not available
 * in the unit test context, so constants are defined here to allow testing
 * of plugin bootstrap logic without a full WordPress installation.
 */

$autoloader = __DIR__ . '/../../vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

if ( ! defined( 'WPD_PERFORMANCE_DOCTOR_VERSION' ) ) {
	define( 'WPD_PERFORMANCE_DOCTOR_VERSION', '0.1.0' );
}

if ( ! defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE' ) ) {
	define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE', __DIR__ . '/../wp-performance-doctor.php' );
}

if ( ! defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH' ) ) {
	define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH', dirname( __DIR__ ) );
}

if ( ! defined( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_URL' ) ) {
	define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_URL', '' );
}

if ( ! defined( 'WPD_PERFORMANCE_DOCTOR_TEXT_DOMAIN' ) ) {
	define( 'WPD_PERFORMANCE_DOCTOR_TEXT_DOMAIN', 'wp-performance-doctor' );
}
