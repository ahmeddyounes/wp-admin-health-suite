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
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Admin;
use WPAdminHealth\Assets;
use WPAdminHealth\Performance\AjaxMonitor;
use WPAdminHealth\Performance\HeartbeatController;

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
		HeartbeatController::class,
		AjaxMonitor::class,
		QueryMonitorInterface::class,
		'admin',
		'assets',
		'performance.heartbeat_controller',
		'performance.ajax_monitor',
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

		// Register Heartbeat Controller as singleton.
		$this->container->singleton(
			HeartbeatController::class,
			function ( $container ) {
				return new HeartbeatController(
					$container->get( SettingsInterface::class )
				);
			}
		);
		$this->container->alias( 'performance.heartbeat_controller', HeartbeatController::class );

		// Register Ajax Monitor as singleton (instantiated only when enabled).
		$this->container->singleton(
			AjaxMonitor::class,
			function ( $container ) {
				return new AjaxMonitor(
					$container->get( ConnectionInterface::class ),
					$container->get( SettingsInterface::class )
				);
			}
		);
		$this->container->alias( 'performance.ajax_monitor', AjaxMonitor::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Heartbeat control should be active for both admin and frontend.
		$this->container->get( HeartbeatController::class );

		/** @var SettingsInterface $settings */
		$settings = $this->container->get( SettingsInterface::class );

		// Enable optional monitors only when requested in settings.
		if (
			! empty( $settings->get_setting( 'enable_query_monitoring', false ) )
			|| ! empty( $settings->get_setting( 'query_logging_enabled', false ) )
		) {
			$this->container->get( QueryMonitorInterface::class );
		}

		if ( ! empty( $settings->get_setting( 'enable_ajax_monitoring', false ) ) ) {
			$this->container->get( AjaxMonitor::class );
		}

		// Initialize Admin and Assets only in admin context.
		if ( is_admin() ) {
			$this->container->get( Admin::class );
			$this->container->get( Assets::class );
		}
	}
}
