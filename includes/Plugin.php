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
 *
 * @since 1.0.0
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
	 * Settings class instance.
	 *
	 * @var Settings|null
	 */
	private $settings;

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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		// Check for upgrades.
		Installer::maybe_upgrade();

		// Load dependencies.
		$this->load_dependencies();

		/**
		 * Fires after plugin initialization is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_init
		 */
		do_action( 'wpha_init' );
	}

	/**
	 * Load plugin dependencies.
	 *
	 * @since 1.0.0
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

		// Initialize Settings class.
		$this->settings = new Settings();

		/**
		 * Fires after all plugin dependencies are loaded.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_dependencies_loaded
		 */
		do_action( 'wpha_dependencies_loaded' );
	}

	/**
	 * Plugin activation handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate() {
		/**
		 * Fires before plugin activation tasks are run.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_activate
		 */
		do_action( 'wpha_activate' );

		// Run installer.
		Installer::install();

		// Flush rewrite rules.
		flush_rewrite_rules();

		/**
		 * Fires after plugin activation is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_activated
		 */
		do_action( 'wpha_activated' );
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deactivate() {
		/**
		 * Fires before plugin deactivation tasks are run.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_deactivate
		 */
		do_action( 'wpha_deactivate' );

		// Flush rewrite rules.
		flush_rewrite_rules();

		/**
		 * Fires after plugin deactivation is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_deactivated
		 */
		do_action( 'wpha_deactivated' );
	}

	/**
	 * Get plugin version.
	 *
	 * @since 1.0.0
	 *
	 * @return string Plugin version number.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get plugin name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Plugin slug name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin path.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute path to plugin directory.
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Get plugin URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Plugin directory URL.
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Get scheduler instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Scheduler|null Scheduler instance or null if not initialized.
	 */
	public function get_scheduler() {
		return $this->scheduler;
	}

	/**
	 * Get settings instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings|null Settings instance or null if not initialized.
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
