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
		ConnectionInterface::class,
		AnalyzerInterface::class,
		RevisionsManagerInterface::class,
		TransientsCleanerInterface::class,
		OrphanedCleanerInterface::class,
		TrashCleanerInterface::class,
		OptimizerInterface::class,
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
				$reflection = new \ReflectionClass( OrphanedTables::class );
				$constructor = $reflection->getConstructor();

				if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
					return new OrphanedTables( $connection, $cache );
				}

				// Fallback for legacy class without DI.
				return new OrphanedTables();
			}
		);

		// Register Revisions Manager with interface binding.
		$this->container->singleton(
			RevisionsManagerInterface::class,
			function () {
				return new RevisionsManager();
			}
		);
		$this->container->alias( 'db.revisions_manager', RevisionsManagerInterface::class );

		// Register Transients Cleaner with interface binding.
		$this->container->singleton(
			TransientsCleanerInterface::class,
			function () {
				return new TransientsCleaner();
			}
		);
		$this->container->alias( 'db.transients_cleaner', TransientsCleanerInterface::class );

		// Register Orphaned Cleaner with interface binding.
		$this->container->singleton(
			OrphanedCleanerInterface::class,
			function () {
				return new OrphanedCleaner();
			}
		);
		$this->container->alias( 'db.orphaned_cleaner', OrphanedCleanerInterface::class );

		// Register Trash Cleaner with interface binding.
		$this->container->singleton(
			TrashCleanerInterface::class,
			function () {
				return new TrashCleaner();
			}
		);
		$this->container->alias( 'db.trash_cleaner', TrashCleanerInterface::class );

		// Register Optimizer with interface binding.
		$this->container->singleton(
			OptimizerInterface::class,
			function () {
				return new Optimizer();
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
