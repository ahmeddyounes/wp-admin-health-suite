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
define( 'WP_ADMIN_HEALTH_MIN_PHP_VERSION', '7.4' );
define( 'WP_ADMIN_HEALTH_MIN_WP_VERSION', '6.0' );

/**
 * Check if the plugin requirements are met.
 *
 * @return bool True if requirements are met, false otherwise.
 */
function wp_admin_health_requirements_met() {
	$php_met = version_compare( PHP_VERSION, WP_ADMIN_HEALTH_MIN_PHP_VERSION, '>=' );
	$wp_met  = version_compare( get_bloginfo( 'version' ), WP_ADMIN_HEALTH_MIN_WP_VERSION, '>=' );

	return $php_met && $wp_met;
}

/**
 * Display admin notice for unmet requirements.
 *
 * @return void
 */
function wp_admin_health_requirements_notice() {
	$php_met = version_compare( PHP_VERSION, WP_ADMIN_HEALTH_MIN_PHP_VERSION, '>=' );
	$wp_met  = version_compare( get_bloginfo( 'version' ), WP_ADMIN_HEALTH_MIN_WP_VERSION, '>=' );

	$messages = array();

	if ( ! $php_met ) {
		$messages[] = sprintf(
			/* translators: 1: Current PHP version, 2: Required PHP version */
			__( 'PHP version %1$s is installed. WP Admin Health Suite requires PHP %2$s or higher.', 'wp-admin-health-suite' ),
			PHP_VERSION,
			WP_ADMIN_HEALTH_MIN_PHP_VERSION
		);
	}

	if ( ! $wp_met ) {
		$messages[] = sprintf(
			/* translators: 1: Current WordPress version, 2: Required WordPress version */
			__( 'WordPress version %1$s is installed. WP Admin Health Suite requires WordPress %2$s or higher.', 'wp-admin-health-suite' ),
			get_bloginfo( 'version' ),
			WP_ADMIN_HEALTH_MIN_WP_VERSION
		);
	}

	if ( ! empty( $messages ) ) {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul><li>%s</li></ul></div>',
			esc_html__( 'WP Admin Health Suite cannot be activated:', 'wp-admin-health-suite' ),
			implode( '</li><li>', array_map( 'esc_html', $messages ) )
		);
	}
}

// Check requirements before loading the plugin.
if ( ! wp_admin_health_requirements_met() ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\wp_admin_health_requirements_notice' );
	return;
}

// Require the autoloader.
// Prefer Composer autoload when available (e.g., development with `composer install`).
// Fall back to the built-in PSR-4 autoloader for production/distribution without vendor/.
$composer_autoload = WP_ADMIN_HEALTH_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
} else {
	require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/autoload.php';
}

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

	// Initialize the main plugin singleton.
	$plugin = Plugin::get_instance();
	$plugin->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\wp_admin_health_init' );

/**
 * Plugin activation hook.
 *
 * @param bool $network_wide Whether to activate network-wide.
 * @return void
 */
function wp_admin_health_activate( $network_wide = false ) {
	// Verify requirements during activation.
	$php_met = version_compare( PHP_VERSION, WP_ADMIN_HEALTH_MIN_PHP_VERSION, '>=' );
	$wp_met  = version_compare( get_bloginfo( 'version' ), WP_ADMIN_HEALTH_MIN_WP_VERSION, '>=' );

	if ( ! $php_met || ! $wp_met ) {
		$messages = array();

		if ( ! $php_met ) {
			$messages[] = sprintf(
				'PHP %s is required. You have PHP %s.',
				WP_ADMIN_HEALTH_MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		if ( ! $wp_met ) {
			$messages[] = sprintf(
				'WordPress %s is required. You have WordPress %s.',
				WP_ADMIN_HEALTH_MIN_WP_VERSION,
				get_bloginfo( 'version' )
			);
		}

		wp_die(
			esc_html( implode( ' ', $messages ) ),
			esc_html__( 'Plugin Activation Error', 'wp-admin-health-suite' ),
			array( 'back_link' => true )
		);
	}

	$plugin = Plugin::get_instance();
	$plugin->activate( $network_wide );
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\wp_admin_health_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wp_admin_health_deactivate() {
	$plugin = Plugin::get_instance();
	$plugin->deactivate();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\wp_admin_health_deactivate' );
