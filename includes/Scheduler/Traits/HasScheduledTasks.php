<?php
/**
 * Has Scheduled Tasks Trait
 *
 * Provides functionality for service providers to register scheduled tasks.
 *
 * @package WPAdminHealth\Scheduler\Traits
 */

namespace WPAdminHealth\Scheduler\Traits;

use WPAdminHealth\Container\ServiceProvider;
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
 *
 * @phpstan-require-extends ServiceProvider
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
	 * @param array<int, class-string<SchedulableInterface>>|null $tasks Optional task class list override.
	 * @return void
	 */
	protected function register_scheduled_tasks( ?array $tasks = null ): void {
		if ( ! $this->container->has( SchedulerRegistryInterface::class ) ) {
			return;
		}

		$registry = $this->container->get( SchedulerRegistryInterface::class );

		if ( ! $registry instanceof SchedulerRegistryInterface ) {
			return;
		}

		$tasks = null === $tasks ? $this->get_scheduled_tasks() : $tasks;

		if ( empty( $tasks ) ) {
			return;
		}

		$tasks = array_values(
			array_unique(
				array_filter(
					$tasks,
					function ( $task ) {
						return is_string( $task ) && '' !== $task;
					}
				)
			)
		);

		foreach ( $tasks as $task_class ) {
			// Tasks must be bound in the container to ensure proper dependency injection.
			if ( ! $this->container->has( $task_class ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'[WP Admin Health Suite] Task %s is not bound in the container. Register it in a service provider.',
							$task_class
						)
					);
				}

				// Nothing else we can do unless the task is in the container.
				continue;
			}

			$task = $this->container->get( $task_class );

			if ( $task instanceof SchedulableInterface ) {
				$registry->register( $task );
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$task_type = function_exists( 'get_debug_type' ) ? get_debug_type( $task ) : gettype( $task );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[WP Admin Health Suite] Task %s resolved to %s and does not implement %s.',
						$task_class,
						$task_type,
						SchedulableInterface::class
					)
				);
			}
		}
	}
}
