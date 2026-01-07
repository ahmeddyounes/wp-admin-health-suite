<?php
/**
 * PSR-4 Autoloader for WP Admin Health Suite
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * PSR-4 compliant autoloader for the plugin.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		// Project-specific namespace prefix.
		$prefix = 'WPAdminHealth\\';

		// Base directory for the namespace prefix.
		$base_dir = WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/';

		// Check if the class uses the namespace prefix.
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			// No, move to the next registered autoloader.
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators in the relative class name.
		// Append with .php.
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
