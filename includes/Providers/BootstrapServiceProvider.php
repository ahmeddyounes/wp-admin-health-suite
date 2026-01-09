<?php
/**
 * Bootstrap Service Provider
 *
 * Registers core UI services: Admin, Assets, RestApi.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Admin;
use WPAdminHealth\Assets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class BootstrapServiceProvider
 *
 * Registers essential UI and API services.
 *
 * @since 1.2.0
 */
class BootstrapServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		Admin::class,
		Assets::class,
		'admin',
		'assets',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Admin as singleton.
		$this->container->singleton(
			Admin::class,
			function ( $container ) {
				return new Admin(
					$container->get( 'plugin.version' ),
					$container->get( 'plugin.name' )
				);
			}
		);
		$this->container->alias( 'admin', Admin::class );

		// Register Assets as singleton.
		$this->container->singleton(
			Assets::class,
			function ( $container ) {
				return new Assets(
					$container->get( 'plugin.version' ),
					$container->get( 'plugin.url' )
				);
			}
		);
		$this->container->alias( 'assets', Assets::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Initialize Admin and Assets only in admin context.
		if ( is_admin() ) {
			$this->container->get( Admin::class );
			$this->container->get( Assets::class );
		}
	}
}
