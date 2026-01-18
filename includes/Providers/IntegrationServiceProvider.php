<?php
/**
 * Integration Service Provider
 *
 * Registers the Integration Manager and discovers integrations.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\IntegrationFactoryInterface;
use WPAdminHealth\Integrations\ACF;
use WPAdminHealth\Integrations\Elementor;
use WPAdminHealth\Integrations\IntegrationFactory;
use WPAdminHealth\Integrations\IntegrationManager;
use WPAdminHealth\Integrations\Multilingual;
use WPAdminHealth\Integrations\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class IntegrationServiceProvider
 *
 * Registers and bootstraps the integration system.
 *
 * @since 1.1.0
 */
class IntegrationServiceProvider extends ServiceProvider {

	/**
	 * Whether this provider should be deferred.
	 *
	 * Deferred because integrations depend on other plugins being loaded.
	 *
	 * @var bool
	 */
	protected bool $deferred = true;

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		IntegrationManager::class,
		IntegrationFactoryInterface::class,
		'integrations',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		$this->register_integrations();
		$this->register_factory();
		$this->register_manager();
	}

	/**
	 * Register individual integration services in the container.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_integrations(): void {
		// Register WooCommerce integration.
		$this->container->bind(
			WooCommerce::class,
			function () {
				return new WooCommerce(
					$this->container->get( ConnectionInterface::class ),
					$this->container->get( CacheInterface::class )
				);
			}
		);

		// Register Elementor integration.
		$this->container->bind(
			Elementor::class,
			function () {
				return new Elementor(
					$this->container->get( ConnectionInterface::class ),
					$this->container->get( CacheInterface::class )
				);
			}
		);

		// Register ACF integration.
		$this->container->bind(
			ACF::class,
			function () {
				return new ACF(
					$this->container->get( ConnectionInterface::class ),
					$this->container->get( CacheInterface::class )
				);
			}
		);

		// Register Multilingual integration.
		$this->container->bind(
			Multilingual::class,
			function () {
				return new Multilingual(
					$this->container->get( ConnectionInterface::class ),
					$this->container->get( CacheInterface::class )
				);
			}
		);
	}

	/**
	 * Register the integration factory.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_factory(): void {
		$this->container->singleton(
			IntegrationFactoryInterface::class,
			function () {
				$factory = new IntegrationFactory( $this->container );

				// Register built-in integrations with the factory.
				$factory->register( 'woocommerce', WooCommerce::class );
				$factory->register( 'elementor', Elementor::class );
				$factory->register( 'acf', ACF::class );
				$factory->register( 'multilingual', Multilingual::class );

				/**
				 * Fires when the integration factory is being configured.
				 *
				 * Allows third-party plugins to register their integrations
				 * with the factory for container-based resolution.
				 *
				 * @since 1.1.0
				 *
				 * @param IntegrationFactory $factory The integration factory instance.
				 */
				do_action( 'wpha_configure_integration_factory', $factory );

				return $factory;
			}
		);
	}

	/**
	 * Register the integration manager.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_manager(): void {
		$this->container->singleton(
			IntegrationManager::class,
			function () {
				$factory = $this->container->get( IntegrationFactoryInterface::class );
				return new IntegrationManager( $factory );
			}
		);

		$this->container->alias( 'integrations', IntegrationManager::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Hook into plugins_loaded to discover and init integrations.
		// This ensures all plugins are loaded before we check for them.
		add_action( 'plugins_loaded', array( $this, 'init_integrations' ), 20 );
	}

	/**
	 * Initialize integrations after all plugins are loaded.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function init_integrations(): void {
		/** @var IntegrationManager $manager */
		$manager = $this->container->get( IntegrationManager::class );

		// Discover and initialize integrations.
		$manager->discover()->init();
	}
}
