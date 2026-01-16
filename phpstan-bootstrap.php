<?php
/**
 * PHPStan bootstrap for WP Admin Health Suite.
 *
 * This file is loaded only during static analysis to provide constants that
 * are normally defined by WordPress at runtime.
 */

// WordPress constant commonly used to gate direct access.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// Plugin constants.
if ( ! defined( 'WP_ADMIN_HEALTH_VERSION' ) ) {
	define( 'WP_ADMIN_HEALTH_VERSION', '0.0.0-phpstan' );
}

if ( ! defined( 'WP_ADMIN_HEALTH_PLUGIN_DIR' ) ) {
	define( 'WP_ADMIN_HEALTH_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'WP_ADMIN_HEALTH_PLUGIN_BASENAME' ) ) {
	define( 'WP_ADMIN_HEALTH_PLUGIN_BASENAME', 'wp-admin-health-suite/wp-admin-health-suite.php' );
}

// Constants used by uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', true );
}
