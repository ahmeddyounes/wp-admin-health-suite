<?php
/**
 * Bootstrap Service Provider
 *
 * Registers core UI services: Admin services, Assets, RestApi.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Admin\MenuRegistrar;
use WPAdminHealth\Admin\PageRenderer;
use WPAdminHealth\Admin\SettingsViewModel;
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
 * @since 1.4.0 Refactored to use MenuRegistrar and PageRenderer services.
 */
class BootstrapServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		MenuRegistrar::class,
		PageRenderer::class,
		SettingsViewModel::class,
		Assets::class,
		HeartbeatController::class,
		AjaxMonitor::class,
		QueryMonitorInterface::class,
		'admin.menu_registrar',
		'admin.page_renderer',
		'admin.settings_view_model',
		'assets',
		'performance.heartbeat_controller',
		'performance.ajax_monitor',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register SettingsViewModel as singleton.
		$this->container->singleton(
			SettingsViewModel::class,
			function ( $container ) {
				return new SettingsViewModel(
					$container->get( SettingsInterface::class )
				);
			}
		);
		$this->container->alias( 'admin.settings_view_model', SettingsViewModel::class );

		// Register PageRenderer as singleton.
		$this->container->singleton(
			PageRenderer::class,
			function ( $container ) {
				return new PageRenderer(
					$container->get( 'plugin.path' ) . 'templates/admin',
					'manage_options',
					$container->get( SettingsViewModel::class )
				);
			}
		);
		$this->container->alias( 'admin.page_renderer', PageRenderer::class );

		// Register MenuRegistrar as singleton.
		$this->container->singleton(
			MenuRegistrar::class,
			function ( $container ) {
				return new MenuRegistrar(
					$container->get( PageRenderer::class )
				);
			}
		);
		$this->container->alias( 'admin.menu_registrar', MenuRegistrar::class );

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

		// Initialize admin services and Assets only in admin context.
		if ( is_admin() ) {
			// Get MenuRegistrar and register its hooks.
			$menu_registrar = $this->container->get( MenuRegistrar::class );
			$menu_registrar->register();

			$this->container->get( Assets::class );

			/**
			 * Fires after admin initialization.
			 *
			 * @since 1.0.0
			 *
			 * @hook wpha_admin_init
			 */
			do_action( 'wpha_admin_init' );
		}
	}
}
