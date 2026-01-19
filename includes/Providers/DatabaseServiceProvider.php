<?php
/**
 * Database Service Provider
 *
 * Registers database services: Connection, Analyzer, Optimizer.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Database\WpdbConnection;
use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Database\OrphanedTables;
use WPAdminHealth\Database\Optimizer;
use WPAdminHealth\Database\RevisionsManager;
use WPAdminHealth\Database\TransientsCleaner;
use WPAdminHealth\Database\OrphanedCleaner;
use WPAdminHealth\Database\TrashCleaner;

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
 * @since 1.2.0 Added interface bindings for domain services.
 */
class DatabaseServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		// Interface identifiers (primary).
		ConnectionInterface::class,
		AnalyzerInterface::class,
		RevisionsManagerInterface::class,
		TransientsCleanerInterface::class,
		OrphanedCleanerInterface::class,
		TrashCleanerInterface::class,
		OptimizerInterface::class,
		// Class-string identifier for classes without interfaces.
		OrphanedTables::class,
		// String aliases (backward compatibility).
		'db.connection',
		'db.analyzer',
		'db.orphaned_tables',
		'db.optimizer',
		'db.revisions_manager',
		'db.transients_cleaner',
		'db.orphaned_cleaner',
		'db.trash_cleaner',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register WPDB Connection as singleton.
		$this->container->singleton(
			ConnectionInterface::class,
			function () {
				return new WpdbConnection();
			}
		);

		$this->container->alias( 'db.connection', ConnectionInterface::class );

		// Register Analyzer with ConnectionInterface and CacheInterface injection.
		$this->container->bind(
			AnalyzerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				$cache      = $container->get( CacheInterface::class );
				return new Analyzer( $connection, $cache );
			}
		);

		$this->container->alias( 'db.analyzer', AnalyzerInterface::class );

		// Register Orphaned Tables detector with ConnectionInterface and CacheInterface injection.
		$this->container->bind(
			OrphanedTables::class,
			function ( $container ) {
				return new OrphanedTables(
					$container->get( ConnectionInterface::class ),
					$container->get( CacheInterface::class )
				);
			}
		);
		$this->container->alias( 'db.orphaned_tables', OrphanedTables::class );

		// Register Revisions Manager with ConnectionInterface injection.
		$this->container->singleton(
			RevisionsManagerInterface::class,
			function ( $container ) {
				return new RevisionsManager(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'db.revisions_manager', RevisionsManagerInterface::class );

		// Register Transients Cleaner with ConnectionInterface injection.
		$this->container->singleton(
			TransientsCleanerInterface::class,
			function ( $container ) {
				return new TransientsCleaner(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'db.transients_cleaner', TransientsCleanerInterface::class );

		// Register Orphaned Cleaner with ConnectionInterface injection.
		$this->container->singleton(
			OrphanedCleanerInterface::class,
			function ( $container ) {
				return new OrphanedCleaner(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'db.orphaned_cleaner', OrphanedCleanerInterface::class );

		// Register Trash Cleaner with ConnectionInterface injection.
		$this->container->singleton(
			TrashCleanerInterface::class,
			function ( $container ) {
				return new TrashCleaner(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'db.trash_cleaner', TrashCleanerInterface::class );

		// Register Optimizer with ConnectionInterface injection.
		$this->container->singleton(
			OptimizerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				return new Optimizer( $connection );
			}
		);
		$this->container->alias( 'db.optimizer', OptimizerInterface::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Database services don't need bootstrapping.
	}
}
