<?php
/**
 * Database Service Provider
 *
 * Registers database services: Connection, Analyzer, Optimizer.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Database\WPDB_Connection;
use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Database\Orphaned_Tables;
use WPAdminHealth\Database\Optimizer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class DatabaseServiceProvider
 *
 * Registers database-related services.
 *
 * @since 1.1.0
 */
class DatabaseServiceProvider extends Service_Provider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		ConnectionInterface::class,
		AnalyzerInterface::class,
		'db.connection',
		'db.analyzer',
		'db.orphaned_tables',
		'db.optimizer',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register WPDB Connection as singleton.
		$this->container->singleton(
			ConnectionInterface::class,
			function () {
				return new WPDB_Connection();
			}
		);

		$this->container->alias( 'db.connection', ConnectionInterface::class );

		// Register Analyzer.
		$this->container->bind(
			AnalyzerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				$cache      = $container->get( CacheInterface::class );

				// Check if Analyzer supports constructor injection.
				$reflection = new \ReflectionClass( Analyzer::class );
				$constructor = $reflection->getConstructor();

				if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
					return new Analyzer( $connection, $cache );
				}

				// Fallback for legacy Analyzer without DI.
				return new Analyzer();
			}
		);

		$this->container->alias( 'db.analyzer', AnalyzerInterface::class );

		// Register Orphaned Tables detector.
		$this->container->bind(
			'db.orphaned_tables',
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				$cache      = $container->get( CacheInterface::class );

				// Check if class supports constructor injection.
				$reflection = new \ReflectionClass( Orphaned_Tables::class );
				$constructor = $reflection->getConstructor();

				if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
					return new Orphaned_Tables( $connection, $cache );
				}

				// Fallback for legacy class without DI.
				return new Orphaned_Tables();
			}
		);

		// Register Optimizer.
		$this->container->bind(
			'db.optimizer',
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );

				// Check if Optimizer supports constructor injection.
				if ( class_exists( Optimizer::class ) ) {
					$reflection = new \ReflectionClass( Optimizer::class );
					$constructor = $reflection->getConstructor();

					if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
						return new Optimizer( $connection );
					}

					return new Optimizer();
				}

				return null;
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Database services don't need bootstrapping.
	}
}
