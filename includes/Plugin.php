<?php
/**
 * Main Plugin Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Container\Container_Interface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Integrations\IntegrationManager;
use WPAdminHealth\Providers\CoreServiceProvider;
use WPAdminHealth\Providers\DatabaseServiceProvider;
use WPAdminHealth\Providers\IntegrationServiceProvider;
use WPAdminHealth\Providers\MediaServiceProvider;
use WPAdminHealth\Providers\RESTServiceProvider;
use WPAdminHealth\Providers\PerformanceServiceProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Main plugin class using singleton pattern with DI container.
 *
 * @since 1.0.0
 * @since 1.1.0 Added dependency injection container support.
 */
class Plugin {

	/**
	 * Single instance of the class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Dependency injection container.
	 *
	 * @since 1.1.0
	 * @var Container_Interface
	 */
	private Container_Interface $container;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Admin class instance.
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Database class instance.
	 *
	 * @var Database|null
	 */
	private ?Database $database = null;

	/**
	 * Assets class instance.
	 *
	 * @var Assets|null
	 */
	private ?Assets $assets = null;

	/**
	 * REST API class instance.
	 *
	 * @var REST_API|null
	 */
	private ?REST_API $rest_api = null;

	/**
	 * Scheduler class instance.
	 *
	 * @var Scheduler|null
	 */
	private ?Scheduler $scheduler = null;

	/**
	 * Settings class instance (legacy, use container).
	 *
	 * @deprecated 1.1.0 Use container->get(SettingsInterface::class) instead.
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Multisite class instance.
	 *
	 * @var Multisite|null
	 */
	private ?Multisite $multisite = null;

	/**
	 * WooCommerce integration instance (legacy).
	 *
	 * @deprecated 1.1.0 Use IntegrationManager instead.
	 * @var Integrations\WooCommerce|null
	 */
	private $woocommerce = null;

	/**
	 * Elementor integration instance (legacy).
	 *
	 * @deprecated 1.1.0 Use IntegrationManager instead.
	 * @var Integrations\Elementor|null
	 */
	private $elementor = null;

	/**
	 * ACF integration instance (legacy).
	 *
	 * @deprecated 1.1.0 Use IntegrationManager instead.
	 * @var Integrations\ACF|null
	 */
	private $acf = null;

	/**
	 * Multilingual integration instance (legacy).
	 *
	 * @deprecated 1.1.0 Use IntegrationManager instead.
	 * @var Integrations\Multilingual|null
	 */
	private $multilingual = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Added container parameter for dependency injection.
	 *
	 * @param Container_Interface|null $container Optional container instance.
	 */
	public function __construct( ?Container_Interface $container = null ) {
		$this->container   = $container ?? new Container();
		$this->version     = defined( 'WP_ADMIN_HEALTH_VERSION' ) ? WP_ADMIN_HEALTH_VERSION : '1.0.0';
		$this->plugin_name = 'wp-admin-health-suite';
		$this->plugin_path = defined( 'WP_ADMIN_HEALTH_PLUGIN_DIR' ) ? WP_ADMIN_HEALTH_PLUGIN_DIR : '';
		$this->plugin_url  = defined( 'WP_ADMIN_HEALTH_PLUGIN_URL' ) ? WP_ADMIN_HEALTH_PLUGIN_URL : '';

		// Register core plugin info in container.
		$this->container->instance( 'plugin.version', $this->version );
		$this->container->instance( 'plugin.name', $this->plugin_name );
		$this->container->instance( 'plugin.path', $this->plugin_path );
		$this->container->instance( 'plugin.url', $this->plugin_url );
		$this->container->instance( self::class, $this );
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Container_Interface|null $container Optional container for testing.
	 * @return Plugin
	 */
	public static function get_instance( ?Container_Interface $container = null ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	/**
	 * Set the singleton instance.
	 *
	 * Useful for testing to inject a mock plugin instance.
	 *
	 * @since 1.1.0
	 *
	 * @param Plugin|null $instance Plugin instance or null to reset.
	 * @return void
	 */
	public static function set_instance( ?Plugin $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * Reset the singleton instance.
	 *
	 * Useful for testing to start fresh.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Get the dependency injection container.
	 *
	 * @since 1.1.0
	 *
	 * @return Container_Interface The container instance.
	 */
	public function get_container(): Container_Interface {
		return $this->container;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Registers service providers and loads dependencies.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Refactored to use service providers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Check for upgrades.
		Installer::maybe_upgrade();

		// Register service providers.
		$this->register_providers();

		// Boot service providers.
		$this->boot_providers();

		// Load legacy dependencies (backward compatibility).
		$this->load_dependencies();

		// Add multisite hooks.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', array( $this, 'on_new_site_created' ), 10, 2 );
		}

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
	 * Register all service providers.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_providers(): void {
		$providers = array(
			CoreServiceProvider::class,
			DatabaseServiceProvider::class,
			MediaServiceProvider::class,
			PerformanceServiceProvider::class,
			IntegrationServiceProvider::class,
			RESTServiceProvider::class,
		);

		/**
		 * Filter the service providers to register.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string> $providers Array of provider class names.
		 */
		$providers = apply_filters( 'wpha_service_providers', $providers );

		foreach ( $providers as $provider_class ) {
			if ( class_exists( $provider_class ) ) {
				$this->container->register( new $provider_class( $this->container ) );
			}
		}
	}

	/**
	 * Boot all registered service providers.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function boot_providers(): void {
		$this->container->boot();
	}

	/**
	 * Load plugin dependencies (legacy method for backward compatibility).
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Now delegates to container where possible.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
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

		// Get Settings from container (still set local reference for backward compatibility).
		if ( $this->container->has( SettingsInterface::class ) ) {
			$this->settings = $this->container->get( SettingsInterface::class );
		} else {
			$this->settings = new Settings();
		}

		// Initialize Multisite class if multisite is enabled.
		if ( is_multisite() ) {
			$this->multisite = new Multisite();
			$this->multisite->init();
		}

		// Legacy integration initialization (IntegrationManager handles this now).
		// Keep local references for backward compatibility with get_*() methods.
		$this->init_legacy_integrations();

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
	 * Initialize legacy integration references for backward compatibility.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function init_legacy_integrations(): void {
		// Initialize WooCommerce integration if WooCommerce is active.
		if ( class_exists( \WPAdminHealth\Integrations\WooCommerce::class ) &&
			\WPAdminHealth\Integrations\WooCommerce::is_active() ) {
			$this->woocommerce = new \WPAdminHealth\Integrations\WooCommerce();
			$this->woocommerce->init();
		}

		// Initialize Elementor integration if Elementor is active.
		if ( class_exists( \WPAdminHealth\Integrations\Elementor::class ) &&
			\WPAdminHealth\Integrations\Elementor::is_active() ) {
			$this->elementor = new \WPAdminHealth\Integrations\Elementor();
			$this->elementor->init();
		}

		// Initialize ACF integration if ACF is active.
		if ( class_exists( \WPAdminHealth\Integrations\ACF::class ) &&
			\WPAdminHealth\Integrations\ACF::is_active() ) {
			$this->acf = new \WPAdminHealth\Integrations\ACF();
			$this->acf->init();
		}

		// Initialize Multilingual integration if WPML or Polylang is active.
		if ( class_exists( \WPAdminHealth\Integrations\Multilingual::class ) &&
			\WPAdminHealth\Integrations\Multilingual::is_active() ) {
			$this->multilingual = new \WPAdminHealth\Integrations\Multilingual();
			$this->multilingual->init();
		}
	}

	/**
	 * Plugin activation handler.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether to activate network-wide.
	 * @return void
	 */
	public function activate( $network_wide = false ) {
		/**
		 * Fires before plugin activation tasks are run.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_activate
		 *
		 * @param {bool} $network_wide Whether activating network-wide.
		 */
		do_action( 'wpha_activate', $network_wide );

		if ( is_multisite() && $network_wide ) {
			// Network activation - install on all sites.
			$sites = get_sites( array( 'number' => 999 ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				Installer::install();
				restore_current_blog();
			}
		} else {
			// Single site activation.
			Installer::install();
		}

		// Flush rewrite rules.
		flush_rewrite_rules();

		/**
		 * Fires after plugin activation is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_activated
		 *
		 * @param {bool} $network_wide Whether activated network-wide.
		 */
		do_action( 'wpha_activated', $network_wide );
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
	 * Get multisite instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Multisite|null Multisite instance or null if not initialized.
	 */
	public function get_multisite() {
		return $this->multisite;
	}

	/**
	 * Get WooCommerce integration instance.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_integration_manager()->get('woocommerce') instead.
	 *
	 * @return Integrations\WooCommerce|null WooCommerce integration instance or null if not initialized.
	 */
	public function get_woocommerce() {
		return $this->woocommerce;
	}

	/**
	 * Get Elementor integration instance.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_integration_manager()->get('elementor') instead.
	 *
	 * @return Integrations\Elementor|null Elementor integration instance or null if not initialized.
	 */
	public function get_elementor() {
		return $this->elementor;
	}

	/**
	 * Get ACF integration instance.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_integration_manager()->get('acf') instead.
	 *
	 * @return Integrations\ACF|null ACF integration instance or null if not initialized.
	 */
	public function get_acf() {
		return $this->acf;
	}

	/**
	 * Get Multilingual integration instance.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_integration_manager()->get('multilingual') instead.
	 *
	 * @return Integrations\Multilingual|null Multilingual integration instance or null if not initialized.
	 */
	public function get_multilingual() {
		return $this->multilingual;
	}

	/**
	 * Get the Integration Manager.
	 *
	 * @since 1.1.0
	 *
	 * @return IntegrationManager|null Integration manager instance.
	 */
	public function get_integration_manager(): ?IntegrationManager {
		if ( $this->container->has( IntegrationManager::class ) ) {
			return $this->container->get( IntegrationManager::class );
		}
		return null;
	}

	/**
	 * Resolve a service from the container.
	 *
	 * Convenience method for accessing container services.
	 *
	 * @since 1.1.0
	 *
	 * @template T
	 * @param class-string<T>|string $abstract Service identifier.
	 * @return T|mixed The resolved service.
	 */
	public function make( string $abstract ) {
		return $this->container->get( $abstract );
	}

	/**
	 * Check if a service is bound in the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string $abstract Service identifier.
	 * @return bool True if the service is bound.
	 */
	public function has( string $abstract ): bool {
		return $this->container->has( $abstract );
	}

	/**
	 * Handle new site creation in multisite.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Site $new_site New site object.
	 * @param array    $args     Arguments for the initialization.
	 * @return void
	 */
	public function on_new_site_created( $new_site, $args ) {
		Installer::install_on_new_site( $new_site->blog_id );
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
