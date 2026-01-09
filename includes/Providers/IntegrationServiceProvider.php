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
use WPAdminHealth\Integrations\IntegrationManager;

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
		'integrations',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Integration Manager as singleton.
		$this->container->singleton(
			IntegrationManager::class,
			function () {
				return new IntegrationManager();
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
