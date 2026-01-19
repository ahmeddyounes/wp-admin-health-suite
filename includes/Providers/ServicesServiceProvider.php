<?php
/**
 * Services Service Provider
 *
 * Registers foundation services: Configuration, ActivityLogger, TableChecker.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ConfigurationInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Services\ConfigurationService;
use WPAdminHealth\Services\ActivityLogger;
use WPAdminHealth\Services\TableChecker;
use WPAdminHealth\Services\ObservabilityEventLogger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ServicesServiceProvider
 *
 * Registers foundation services that eliminate code duplication
 * and centralize common functionality.
 *
 * @since 1.3.0
 */
class ServicesServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		ConfigurationInterface::class,
		ActivityLoggerInterface::class,
		TableCheckerInterface::class,
		ObservabilityEventLogger::class,
		'config',
		'activity_logger',
		'table_checker',
		'observability_logger',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Configuration Service as singleton.
		$this->container->singleton(
			ConfigurationInterface::class,
			function () {
				return new ConfigurationService();
			}
		);
		$this->container->alias( 'config', ConfigurationInterface::class );

		// Register Activity Logger as singleton.
		$this->container->singleton(
			ActivityLoggerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				$settings   = $container->has( SettingsInterface::class )
					? $container->get( SettingsInterface::class )
					: null;
				return new ActivityLogger( $connection, $settings );
			}
		);
		$this->container->alias( 'activity_logger', ActivityLoggerInterface::class );

		// Register Table Checker as singleton.
		$this->container->singleton(
			TableCheckerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				return new TableChecker( $connection );
			}
		);
		$this->container->alias( 'table_checker', TableCheckerInterface::class );

		// Register Observability Event Logger as singleton.
		$this->container->singleton(
			ObservabilityEventLogger::class,
			function ( $container ) {
				$settings = $container->has( SettingsInterface::class )
					? $container->get( SettingsInterface::class )
					: null;
				return new ObservabilityEventLogger( $settings );
			}
		);
		$this->container->alias( 'observability_logger', ObservabilityEventLogger::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Register observability event logger hooks.
		$observability_logger = $this->container->get( ObservabilityEventLogger::class );
		$observability_logger->register();
	}
}
