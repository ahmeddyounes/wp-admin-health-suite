<?php
/**
 * Plugin Name: WP Admin Health Suite
 * Plugin URI: https://github.com/yourusername/wp-admin-health-suite
 * Description: A comprehensive suite for monitoring and maintaining WordPress admin health and performance.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-admin-health-suite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Define plugin constants.
define( 'WP_ADMIN_HEALTH_VERSION', '1.0.0' );
define( 'WP_ADMIN_HEALTH_PLUGIN_FILE', __FILE__ );
define( 'WP_ADMIN_HEALTH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_ADMIN_HEALTH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_ADMIN_HEALTH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require the autoloader.
require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/autoload.php';

/**
 * Main plugin class initialization.
 *
 * @return void
 */
function wp_admin_health_init() {
	// Load plugin text domain for translations.
	load_plugin_textdomain(
		'wp-admin-health-suite',
		false,
		dirname( WP_ADMIN_HEALTH_PLUGIN_BASENAME ) . '/languages'
	);

	// Initialize the plugin (placeholder for future initialization logic).
	// Future: Initialize main plugin class here.
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\wp_admin_health_init' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wp_admin_health_activate() {
	// Activation logic here (if needed in future).
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\wp_admin_health_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wp_admin_health_deactivate() {
	// Deactivation logic here (if needed in future).
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\wp_admin_health_deactivate' );
