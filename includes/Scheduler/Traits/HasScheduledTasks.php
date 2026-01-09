<?php
/**
 * Has Scheduled Tasks Trait
 *
 * Provides functionality for service providers to register scheduled tasks.
 *
 * @package WPAdminHealth\Scheduler\Traits
 */

namespace WPAdminHealth\Scheduler\Traits;

use WPAdminHealth\Scheduler\Contracts\SchedulableInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulerRegistryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Trait HasScheduledTasks
 *
 * Used by service providers to register their scheduled tasks with the registry.
 *
 * @since 1.2.0
 */
trait HasScheduledTasks {

	/**
	 * Get the tasks to register.
	 *
	 * Override this method in your service provider to define tasks.
	 *
	 * @return array<class-string<SchedulableInterface>> Array of task class names.
	 */
	protected function get_scheduled_tasks(): array {
		return array();
	}

	/**
	 * Register scheduled tasks with the registry.
	 *
	 * Call this method in the service provider's boot() method.
	 *
	 * @return void
	 */
	protected function register_scheduled_tasks(): void {
		if ( ! $this->container->has( SchedulerRegistryInterface::class ) ) {
			return;
		}

		$registry = $this->container->get( SchedulerRegistryInterface::class );
		$tasks    = $this->get_scheduled_tasks();

		foreach ( $tasks as $task_class ) {
			if ( ! class_exists( $task_class ) ) {
				continue;
			}

			// Tasks must be bound in the container to ensure proper dependency injection.
			if ( ! $this->container->has( $task_class ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'[WP Admin Health Suite] Task class %s is not bound in the container. Register it in a service provider.',
							$task_class
						)
					);
				}
				continue;
			}

			$task = $this->container->get( $task_class );

			if ( $task instanceof SchedulableInterface ) {
				$registry->register( $task );
			}
		}
	}
}
