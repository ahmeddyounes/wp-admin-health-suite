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
 *
 * @phpstan-type TaskSettingSchema array{
 *   type: string,
 *   default: mixed,
 *   description?: string,
 *   min?: int|float,
 *   max?: int|float
 * }
 * @phpstan-type TaskSettingsSchema array<string, TaskSettingSchema>
 *
 * @phpstan-type TaskExecutionResult array<string, mixed>
 */
interface SchedulableInterface {

	/**
	 * Daily frequency slug.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	public const FREQUENCY_DAILY = 'daily';

	/**
	 * Weekly frequency slug.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	public const FREQUENCY_WEEKLY = 'weekly';

	/**
	 * Monthly frequency slug.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	public const FREQUENCY_MONTHLY = 'monthly';

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
	 * Returned values should be a schedule slug compatible with the scheduler
	 * implementation (e.g. "daily", "weekly", "monthly").
	 *
	 * @return string Default frequency slug.
	 */
	public function get_default_frequency(): string;

	/**
	 * Execute the scheduled task.
	 *
	 * @param array $options Task options/settings.
	 * @return TaskExecutionResult Result data. For consistency across logging/UI,
	 *                              implementations should include a boolean 'success'
	 *                              key and may include 'items_cleaned', 'bytes_freed',
	 *                              'task_id', 'executed_at', and 'error'.
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
	 * @return TaskSettingsSchema Array of setting definitions.
	 */
	public function get_settings_schema(): array;
}
