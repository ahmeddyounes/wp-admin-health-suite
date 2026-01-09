<?php
/**
 * Scheduler Registry Interface
 *
 * Contract for the scheduler registry that manages schedulable tasks.
 *
 * @package WPAdminHealth\Scheduler\Contracts
 */

namespace WPAdminHealth\Scheduler\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SchedulerRegistryInterface
 *
 * Defines the contract for task registration and management.
 *
 * @since 1.2.0
 */
interface SchedulerRegistryInterface {

	/**
	 * Register a schedulable task.
	 *
	 * @param SchedulableInterface $task Task to register.
	 * @return void
	 */
	public function register( SchedulableInterface $task ): void;

	/**
	 * Get a registered task by its ID.
	 *
	 * @param string $task_id Task identifier.
	 * @return SchedulableInterface|null Task instance or null if not found.
	 */
	public function get( string $task_id ): ?SchedulableInterface;

	/**
	 * Get all registered tasks.
	 *
	 * @return array<string, SchedulableInterface> Array of registered tasks keyed by task ID.
	 */
	public function get_all(): array;

	/**
	 * Check if a task is registered.
	 *
	 * @param string $task_id Task identifier.
	 * @return bool True if task is registered.
	 */
	public function has( string $task_id ): bool;

	/**
	 * Get all enabled tasks.
	 *
	 * @return array<string, SchedulableInterface> Array of enabled tasks.
	 */
	public function get_enabled(): array;

	/**
	 * Execute a task by its ID.
	 *
	 * @param string $task_id Task identifier.
	 * @param array  $options Task options.
	 * @return array|null Execution result or null if task not found.
	 */
	public function execute( string $task_id, array $options = array() ): ?array;
}
