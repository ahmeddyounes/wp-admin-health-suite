<?php
/**
 * Scheduler Service Provider
 *
 * Registers the Scheduler service and task registry.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Scheduler\SchedulerRegistry;
use WPAdminHealth\Scheduler\Contracts\SchedulerRegistryInterface;
use WPAdminHealth\Database\Tasks\DatabaseCleanupTask;
use WPAdminHealth\Media\Tasks\MediaScanTask;
use WPAdminHealth\Performance\Tasks\PerformanceCheckTask;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SchedulerServiceProvider
 *
 * Registers task scheduling services.
 *
 * @since 1.2.0
 */
class SchedulerServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		SchedulerRegistryInterface::class,
		'scheduler.registry',
		DatabaseCleanupTask::class,
		MediaScanTask::class,
		PerformanceCheckTask::class,
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register the SchedulerRegistry.
		$this->container->singleton(
			SchedulerRegistryInterface::class,
			function () {
				return new SchedulerRegistry();
			}
		);
		$this->container->alias( 'scheduler.registry', SchedulerRegistryInterface::class );

		// Register task classes.
		$this->register_tasks();
	}

	/**
	 * Register scheduled task classes.
	 *
	 * @return void
	 */
	private function register_tasks(): void {
		// Register DatabaseCleanupTask.
		$this->container->bind(
			DatabaseCleanupTask::class,
			function ( $container ) {
				return new DatabaseCleanupTask(
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$container->get( OptimizerInterface::class )
				);
			}
		);

		// Register MediaScanTask.
		$this->container->bind(
			MediaScanTask::class,
			function ( $container ) {
				return new MediaScanTask(
					$container->get( ScannerInterface::class ),
					$container->get( DuplicateDetectorInterface::class ),
					$container->get( LargeFilesInterface::class ),
					$container->get( AltTextCheckerInterface::class )
				);
			}
		);

		// Register PerformanceCheckTask.
		$this->container->bind(
			PerformanceCheckTask::class,
			function ( $container ) {
				return new PerformanceCheckTask(
					$container->get( AutoloadAnalyzerInterface::class ),
					$container->get( QueryMonitorInterface::class ),
					$container->get( PluginProfilerInterface::class )
				);
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Register tasks with the registry.
		$this->register_tasks_with_registry();

		// Hook the registry into WP-Cron execution.
		$this->setup_cron_hooks();
	}

	/**
	 * Register tasks with the scheduler registry.
	 *
	 * @return void
	 */
	private function register_tasks_with_registry(): void {
		$registry = $this->container->get( SchedulerRegistryInterface::class );

		// Register all task types.
		$task_classes = array(
			DatabaseCleanupTask::class,
			MediaScanTask::class,
			PerformanceCheckTask::class,
		);

		foreach ( $task_classes as $task_class ) {
			if ( $this->container->has( $task_class ) ) {
				$task = $this->container->get( $task_class );
				$registry->register( $task );
			}
		}

		/**
		 * Fires after all default tasks are registered.
		 *
		 * Use this hook to register custom tasks.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_scheduler_tasks_registered
		 *
		 * @param SchedulerRegistryInterface $registry The scheduler registry.
		 */
		do_action( 'wpha_scheduler_tasks_registered', $registry );
	}

	/**
	 * Set up WP-Cron hooks for task execution.
	 *
	 * @return void
	 */
	private function setup_cron_hooks(): void {
		// Hook for executing tasks from the registry.
		add_action( 'wpha_execute_registered_task', array( $this, 'execute_registered_task' ), 10, 2 );

		// Hook individual task execution to cron hooks.
		add_action( 'wpha_database_cleanup', array( $this, 'execute_database_cleanup' ) );
		add_action( 'wpha_media_scan', array( $this, 'execute_media_scan' ) );
		add_action( 'wpha_performance_check', array( $this, 'execute_performance_check' ) );
	}

	/**
	 * Execute a registered task.
	 *
	 * @param string $task_id Task identifier.
	 * @param array  $options Task options.
	 * @return void
	 */
	public function execute_registered_task( string $task_id, array $options = array() ): void {
		$registry = $this->container->get( SchedulerRegistryInterface::class );
		$registry->execute( $task_id, $options );
	}

	/**
	 * Execute database cleanup task.
	 *
	 * @return void
	 */
	public function execute_database_cleanup(): void {
		$this->execute_registered_task( 'database_cleanup' );
	}

	/**
	 * Execute media scan task.
	 *
	 * @return void
	 */
	public function execute_media_scan(): void {
		$this->execute_registered_task( 'media_scan' );
	}

	/**
	 * Execute performance check task.
	 *
	 * @return void
	 */
	public function execute_performance_check(): void {
		$this->execute_registered_task( 'performance_check' );
	}
}
