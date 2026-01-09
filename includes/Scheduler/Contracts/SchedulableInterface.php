<?php
/**
 * Schedulable Interface
 *
 * Contract for scheduled task implementations.
 *
 * @package WPAdminHealth\Scheduler\Contracts
 */

namespace WPAdminHealth\Scheduler\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SchedulableInterface
 *
 * Defines the contract for tasks that can be scheduled.
 *
 * @since 1.2.0
 */
interface SchedulableInterface {

	/**
	 * Get the unique task identifier.
	 *
	 * @return string Task identifier.
	 */
	public function get_task_id(): string;

	/**
	 * Get the task name for display.
	 *
	 * @return string Human-readable task name.
	 */
	public function get_task_name(): string;

	/**
	 * Get the task description.
	 *
	 * @return string Task description.
	 */
	public function get_description(): string;

	/**
	 * Get the default frequency for this task.
	 *
	 * @return string Default frequency (daily, weekly, monthly, custom_days).
	 */
	public function get_default_frequency(): string;

	/**
	 * Execute the scheduled task.
	 *
	 * @param array $options Task options/settings.
	 * @return array Result with 'items_cleaned', 'bytes_freed', and 'success' keys.
	 */
	public function execute( array $options = array() ): array;

	/**
	 * Check if the task is enabled.
	 *
	 * @return bool True if task is enabled.
	 */
	public function is_enabled(): bool;

	/**
	 * Get the task settings schema.
	 *
	 * @return array Array of setting definitions.
	 */
	public function get_settings_schema(): array;
}
