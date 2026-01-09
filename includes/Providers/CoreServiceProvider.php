<?php
/**
 * Core Service Provider
 *
 * Registers core services: Settings, Cache.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Settings;
use WPAdminHealth\Cache\Cache_Factory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CoreServiceProvider
 *
 * Registers essential core services that other services depend on.
 *
 * @since 1.1.0
 */
class CoreServiceProvider extends Service_Provider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		SettingsInterface::class,
		CacheInterface::class,
		'settings',
		'cache',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Settings as singleton.
		$this->container->singleton(
			SettingsInterface::class,
			function () {
				return new Settings();
			}
		);

		// Create alias for convenience.
		$this->container->alias( 'settings', SettingsInterface::class );

		// Register Cache as singleton.
		$this->container->singleton(
			CacheInterface::class,
			function () {
				return Cache_Factory::get_instance();
			}
		);

		// Create alias for convenience.
		$this->container->alias( 'cache', CacheInterface::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Initialize settings.
		$settings = $this->container->get( SettingsInterface::class );

		if ( method_exists( $settings, 'init' ) ) {
			$settings->init();
		}
	}
}
