<?php
/**
 * Main Plugin Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Container\ContainerInterface;
use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Integrations\IntegrationManager;
use WPAdminHealth\Providers\BootstrapServiceProvider;
use WPAdminHealth\Providers\CoreServiceProvider;
use WPAdminHealth\Providers\DatabaseServiceProvider;
use WPAdminHealth\Providers\InstallerServiceProvider;
use WPAdminHealth\Providers\IntegrationServiceProvider;
use WPAdminHealth\Providers\MediaServiceProvider;
use WPAdminHealth\Providers\MultisiteServiceProvider;
use WPAdminHealth\Providers\PerformanceServiceProvider;
use WPAdminHealth\Providers\RESTServiceProvider;
use WPAdminHealth\Providers\SchedulerServiceProvider;
use WPAdminHealth\Providers\AIServiceProvider;
use WPAdminHealth\Settings\SettingsServiceProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Main plugin class using singleton pattern with DI container.
 *
 * @since 1.0.0
 * @since 1.1.0 Added dependency injection container support.
 * @since 1.2.0 Removed deprecated properties, all services now use DI container.
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
	 * @var ContainerInterface
	 */
	private ContainerInterface $container;

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Added container parameter for dependency injection.
	 *
	 * @param ContainerInterface|null $container Optional container instance.
	 */
	public function __construct( ?ContainerInterface $container = null ) {
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
	 * @param ContainerInterface|null $container Optional container for testing.
	 * @return Plugin
	 */
	public static function get_instance( ?ContainerInterface $container = null ): Plugin {
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
	 * @return ContainerInterface The container instance.
	 */
	public function get_container(): ContainerInterface {
		return $this->container;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Registers service providers and boots them.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Refactored to use service providers.
	 * @since 1.2.0 Removed legacy dependency loading, all services now via providers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register service providers.
		$this->register_providers();

		// Boot service providers.
		$this->boot_providers();

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
	 * @since 1.2.0 Added InstallerServiceProvider, SchedulerServiceProvider, MultisiteServiceProvider, BootstrapServiceProvider.
	 *
	 * @return void
	 */
	private function register_providers(): void {
		$providers = array(
			CoreServiceProvider::class,
			SettingsServiceProvider::class,
			InstallerServiceProvider::class,
			MultisiteServiceProvider::class,
			BootstrapServiceProvider::class,
			DatabaseServiceProvider::class,
			MediaServiceProvider::class,
			PerformanceServiceProvider::class,
			SchedulerServiceProvider::class, // Must come after Database, Media, Performance.
			IntegrationServiceProvider::class,
			AIServiceProvider::class,
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
			// Security: Validate each provider class to prevent arbitrary class instantiation.
			// This prevents malicious code from being injected via the filter.
			if ( ! is_string( $provider_class ) ) {
				continue;
			}

			if ( ! class_exists( $provider_class ) ) {
				continue;
			}

			// Ensure the class extends ServiceProvider to prevent arbitrary class instantiation.
			if ( ! is_subclass_of( $provider_class, ServiceProvider::class ) ) {
				continue;
			}

			$this->container->register( new $provider_class( $this->container ) );
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
