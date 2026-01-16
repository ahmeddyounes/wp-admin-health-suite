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
use WPAdminHealth\Services\ConfigurationService;
use WPAdminHealth\Services\ActivityLogger;
use WPAdminHealth\Services\TableChecker;

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
		'config',
		'activity_logger',
		'table_checker',
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
				return new ActivityLogger( $connection );
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
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Foundation services don't need bootstrapping.
	}
}
