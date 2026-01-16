<?php
/**
 * Core Service Provider
 *
 * Registers core services: Cache.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Cache\CacheFactory;
use WPAdminHealth\HealthCalculator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CoreServiceProvider
 *
 * Registers essential core services that other services depend on.
 * Note: Settings are now handled by SettingsServiceProvider.
 *
 * @since 1.1.0
 * @since 1.2.0 Removed Settings binding (moved to SettingsServiceProvider).
 */
class CoreServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		CacheInterface::class,
		'cache',
		HealthCalculator::class,
		'health_calculator',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Cache as singleton.
		$this->container->singleton(
			CacheInterface::class,
			function () {
				return CacheFactory::get_instance();
			}
		);

		// Create alias for convenience.
		$this->container->alias( 'cache', CacheInterface::class );

		// Register HealthCalculator as singleton.
		$this->container->singleton(
			HealthCalculator::class,
			function ( $container ) {
				return new HealthCalculator(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'health_calculator', HealthCalculator::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Core services don't need bootstrapping.
	}
}
