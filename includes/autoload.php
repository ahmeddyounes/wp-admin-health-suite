<?php
/**
 * PSR-4 Autoloader for WP Admin Health Suite
 *
 * Implements PSR-4 autoloading with the following namespace mapping:
 * - WPAdminHealth\ -> includes/
 *
 * Directory structure must match namespace casing:
 * - WPAdminHealth\Database\ -> includes/Database/
 * - WPAdminHealth\Media\ -> includes/Media/
 * - WPAdminHealth\REST\ -> includes/REST/
 * etc.
 *
 * @package WPAdminHealth
 * @since 1.0.0
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * PSR-4 compliant autoloader for the plugin.
 *
 * @since 1.0.0
 * @since 1.1.0 Added error logging for development environments.
 *
 * @param string $class The fully-qualified class name.
 * @return bool True if the class was loaded, false otherwise.
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
			// Not our namespace, move to the next registered autoloader.
			return false;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators.
		// PSR-4: Namespace segments map directly to directory names (case-sensitive).
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
			return true;
		}

		// Log missing class files in debug mode for development.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'WP Admin Health Suite: Failed to autoload class "%s". Expected file: %s',
					$class,
					$file
				)
			);
		}

		return false;
	}
);
