<?php
/**
 * PHPUnit bootstrap file for WP Admin Health Suite
 *
 * @package WPAdminHealth
 */

// Define test environment constants
define( 'WP_ADMIN_HEALTH_TESTS_DIR', __DIR__ );

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Register test namespace autoloader for Mocks and other test classes.
spl_autoload_register( function ( $class ) {
	$prefix = 'WPAdminHealth\\Tests\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );

	// Map namespace components to lowercase directories for WordPress conventions.
	$path_parts = explode( '\\', $relative_class );
	$file_name  = array_pop( $path_parts );

	// Convert directory parts to lowercase.
	$path_parts = array_map( 'strtolower', $path_parts );

	// Build the file path.
	$file_path = WP_ADMIN_HEALTH_TESTS_DIR;
	if ( ! empty( $path_parts ) ) {
		$file_path .= '/' . implode( '/', $path_parts );
	}
	$file = $file_path . '/' . $file_name . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Get WordPress tests directory
$_tests_dir = getenv( 'WP_TESTS_DIR' );

// If WP_TESTS_DIR is not set, try common locations
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// If the tests directory doesn't exist, we need to give instructions
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "\n!!! WordPress test suite not found !!!\n";
	echo "To install:\n";
	echo "1. Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
	echo "   (Adjust database credentials as needed)\n";
	echo "2. Or set WP_TESTS_DIR environment variable to your WordPress test library path\n\n";
	exit( 1 );
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for testing
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-admin-health-suite.php';
}

// Register the plugin loader
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test utilities
require_once WP_ADMIN_HEALTH_TESTS_DIR . '/TestCase.php';
