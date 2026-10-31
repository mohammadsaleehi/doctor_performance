<?php
/**
 * WP Performance Doctor
 *
 * @package   WPPerformanceDoctor
 * @author    WP Performance Doctor Team
 * @license   MIT
 * @link      https://example.com/wp-performance-doctor
 */

/**
 * Plugin Name: WP Performance Doctor
 * Plugin URI: https://example.com/wp-performance-doctor
 * Description: Measure, diagnose, fix, and verify WordPress performance.
 * Version: 0.1.0
 * Author: WP Performance Doctor Team
 * Author URI: https://example.com
 * Text Domain: wp-performance-doctor
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPD_PERFORMANCE_DOCTOR_VERSION', '0.1.0' );
define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_FILE', __FILE__ );
define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_PATH', __DIR__ );
define( 'WPD_PERFORMANCE_DOCTOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPD_PERFORMANCE_DOCTOR_TEXT_DOMAIN', 'wp-performance-doctor' );

$autoloader = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

use WPPerformanceDoctor\Core\Activator;
use WPPerformanceDoctor\Core\Deactivator;
use WPPerformanceDoctor\Core\Plugin;

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

if ( is_admin() ) {
	Plugin::get_instance()->init();
}
