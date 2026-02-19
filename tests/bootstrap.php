<?php
/**
 * PHPUnit bootstrap file for LearnKit plugin tests.
 *
 * @package LearnKit
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// For now, we'll create a simple mock environment for basic tests
// In production CI/CD, the full WordPress test suite will be available

// Define WordPress constants for testing
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/app/' );
}

// Load the plugin
require_once dirname( __DIR__ ) . '/learnkit.php';

// Mock WordPress functions needed for basic tests
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {
		return true;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {
		return true;
	}
}

// Note: Full WordPress test suite integration will be available in CI/CD environment
// For local testing within Lando, run: lando wp scaffold plugin-tests learnkit
