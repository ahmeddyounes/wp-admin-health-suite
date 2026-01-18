<?php
/**
 * Scheduling Service Interface
 *
 * Contract for the single-authority scheduling service that manages
 * schedule creation, rescheduling, and unscheduling across WP-Cron
 * and Action Scheduler.
 *
 * @package WPAdminHealth\Scheduler\Contracts
 */

namespace WPAdminHealth\Scheduler\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SchedulingServiceInterface
 *
 * Defines the contract for the centralized scheduling service.
 *
 * @since 2.0.0
 */
interface SchedulingServiceInterface {

	/**
	 * Schedule a task.
	 *
	 * Creates or updates the schedule for a task using Action Scheduler
	 * when available, falling back to WP-Cron.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $task_id   Task identifier (e.g., 'database_cleanup').
	 * @param string   $frequency Frequency (daily, weekly, monthly, disabled).
	 * @param int|null $next_run  Next run timestamp. If null, calculates from preferred_time setting.
	 * @return bool True if scheduled successfully.
	 */
	public function schedule( string $task_id, string $frequency, ?int $next_run = null ): bool;

	/**
	 * Reschedule a task.
	 *
	 * Unschedules the existing task and schedules it with new parameters.
	 *
	 * @since 2.0.0
	 *
	 * @param string   $task_id   Task identifier.
	 * @param string   $frequency New frequency.
	 * @param int|null $next_run  Next run timestamp. If null, calculates from preferred_time setting.
	 * @return bool True if rescheduled successfully.
	 */
	public function reschedule( string $task_id, string $frequency, ?int $next_run = null ): bool;

	/**
	 * Unschedule a task.
	 *
	 * Removes all scheduled instances of a task from both Action Scheduler
	 * and WP-Cron.
	 *
	 * @since 2.0.0
	 *
	 * @param string $task_id Task identifier.
	 * @return bool True if unscheduled successfully.
	 */
	public function unschedule( string $task_id ): bool;

	/**
	 * Unschedule all tasks.
	 *
	 * Removes all plugin-scheduled tasks from both Action Scheduler
	 * and WP-Cron.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of tasks unscheduled.
	 */
	public function unschedule_all(): int;

	/**
	 * Get the next scheduled run time for a task.
	 *
	 * @since 2.0.0
	 *
	 * @param string $task_id Task identifier.
	 * @return int|null Unix timestamp or null if not scheduled.
	 */
	public function get_next_run( string $task_id ): ?int;

	/**
	 * Check if a task is scheduled.
	 *
	 * @since 2.0.0
	 *
	 * @param string $task_id Task identifier.
	 * @return bool True if scheduled.
	 */
	public function is_scheduled( string $task_id ): bool;

	/**
	 * Get the current frequency for a scheduled task.
	 *
	 * @since 2.0.0
	 *
	 * @param string $task_id Task identifier.
	 * @return string|null Frequency or null if not scheduled.
	 */
	public function get_frequency( string $task_id ): ?string;

	/**
	 * Reconcile schedules based on current settings.
	 *
	 * Compares current schedules against wpha_settings and adjusts:
	 * - Schedules tasks that should be scheduled but aren't
	 * - Unschedules tasks that should not be scheduled
	 * - Reschedules tasks whose frequency has changed
	 *
	 * @since 2.0.0
	 *
	 * @return array{
	 *   scheduled: array<string>,
	 *   unscheduled: array<string>,
	 *   rescheduled: array<string>,
	 *   unchanged: array<string>,
	 *   errors: array<string, string>
	 * } Summary of reconciliation actions.
	 */
	public function reconcile(): array;

	/**
	 * Get scheduling status for all tasks.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{
	 *   scheduled: bool,
	 *   next_run: int|null,
	 *   frequency: string|null,
	 *   enabled_in_settings: bool,
	 *   frequency_in_settings: string
	 * }> Status for each known task.
	 */
	public function get_status(): array;

	/**
	 * Calculate next run time based on preferred hour.
	 *
	 * @since 2.0.0
	 *
	 * @param int|null $preferred_hour Preferred hour (0-23). If null, uses setting.
	 * @return int Unix timestamp for next run.
	 */
	public function calculate_next_run_time( ?int $preferred_hour = null ): int;

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if Action Scheduler functions are available.
	 */
	public function is_action_scheduler_available(): bool;

	/**
	 * Schedule initial tasks on fresh install.
	 *
	 * Schedules all enabled tasks based on current settings. This method is
	 * specifically designed for use during plugin activation when the settings
	 * have just been created with defaults.
	 *
	 * @since 2.0.0
	 *
	 * @return array{
	 *   scheduled: array<string>,
	 *   skipped: array<string>,
	 *   errors: array<string, string>
	 * } Summary of scheduling actions.
	 */
	public function schedule_initial_tasks(): array;
}
