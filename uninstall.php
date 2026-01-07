<?php
/**
 * Uninstall script for WP Admin Health Suite
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly or not in uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Only remove data if constant is defined.
if ( ! defined( 'WPHA_DELETE_PLUGIN_DATA' ) ) {
	define( 'WPHA_DELETE_PLUGIN_DATA', false );
}

if ( WPHA_DELETE_PLUGIN_DATA ) {
	// Load the installer class.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-installer.php';

	// Run uninstall.
	\WPAdminHealth\Installer::uninstall();
}
