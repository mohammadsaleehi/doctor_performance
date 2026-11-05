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

/**
 * Provide global shims for WordPress functions not available without
 * WordPress bootstrap. These allow unit tests to run without a full
 * WordPress installation.
 */
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return (string) $str;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = null ) {
		if ( $max === null ) {
			$max = mt_getrandmax();
		}
		return mt_rand( $min, $max );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text ) {
		echo (string) $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text ) {
		echo (string) $text;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return '/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = '' ) {
		return 'test-nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {
		return true;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script() {
		return true;
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = false ) {
		if ( false === $timestamp ) {
			$timestamp = time();
		}
		return date( $format, $timestamp );
	}
}

if ( ! function_exists( 'sanitize_title_with_dashes' ) ) {
	function sanitize_title_with_dashes( $title ) {
		return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $title ) ) );
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'OBJECT_K' ) ) {
	define( 'OBJECT_K', 'OBJECT_K' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '' );
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'timestamp', $gmt = null ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}
		return time();
	}
}

