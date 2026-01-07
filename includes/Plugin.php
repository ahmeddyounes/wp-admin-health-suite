<?php
/**
 * Main Plugin Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Main plugin class using singleton pattern.
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Admin class instance.
	 *
	 * @var Admin|null
	 */
	private $admin;

	/**
	 * Database class instance.
	 *
	 * @var Database|null
	 */
	private $database;

	/**
	 * Assets class instance.
	 *
	 * @var Assets|null
	 */
	private $assets;

	/**
	 * REST API class instance.
	 *
	 * @var REST_API|null
	 */
	private $rest_api;

	/**
	 * Scheduler class instance.
	 *
	 * @var Scheduler|null
	 */
	private $scheduler;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->version     = WP_ADMIN_HEALTH_VERSION;
		$this->plugin_name = 'wp-admin-health-suite';
		$this->plugin_path = WP_ADMIN_HEALTH_PLUGIN_DIR;
		$this->plugin_url  = WP_ADMIN_HEALTH_PLUGIN_URL;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Loads all dependencies and hooks into plugins_loaded.
	 *
	 * @return void
	 */
	public function init() {
		// Check for upgrades.
		Installer::maybe_upgrade();

		// Load dependencies.
		$this->load_dependencies();

		// Hook for plugin initialization.
		do_action( 'wpha_init' );
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Initialize Admin class.
		$this->admin = new Admin( $this->version, $this->plugin_name );

		// Initialize Database class.
		$this->database = new Database( $this->version );

		// Initialize Assets class.
		$this->assets = new Assets( $this->version, $this->plugin_url );

		// Initialize REST API class.
		$this->rest_api = new REST_API( $this->version );

		// Initialize Scheduler class.
		$this->scheduler = new Scheduler();

		// Hook for after dependencies loaded.
		do_action( 'wpha_dependencies_loaded' );
	}

	/**
	 * Plugin activation handler.
	 *
	 * @return void
	 */
	public function activate() {
		// Run activation hook.
		do_action( 'wpha_activate' );

		// Run installer.
		Installer::install();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Hook for after activation.
		do_action( 'wpha_activated' );
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Run deactivation hook.
		do_action( 'wpha_deactivate' );

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Hook for after deactivation.
		do_action( 'wpha_deactivated' );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Get scheduler instance.
	 *
	 * @return Scheduler|null
	 */
	public function get_scheduler() {
		return $this->scheduler;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Cloning is forbidden.', 'wp-admin-health-suite' ),
			'1.0.0'
		);
	}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing instances of this class is forbidden.', 'wp-admin-health-suite' ),
			'1.0.0'
		);
	}
}
