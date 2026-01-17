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
 *
 * @phpstan-type TaskDefinition array{
 *   id: string,
 *   name: string,
 *   description: string,
 *   default_frequency: string,
 *   enabled: bool,
 *   hook: string,
 *   next_run: int|null,
 *   settings_schema: array<string, mixed>
 * }
 */
interface SchedulerRegistryInterface {

	/**
	 * Default Action Scheduler group used for recurring scheduled tasks.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	public const ACTION_SCHEDULER_GROUP = 'wpha_scheduling';

	/**
	 * Task hook prefix.
	 *
	 * Task IDs are mapped to WordPress hooks using: wpha_{task_id}
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	public const TASK_HOOK_PREFIX = 'wpha_';

	/**
	 * Register a schedulable task.
	 *
	 * @param SchedulableInterface $task Task to register.
	 * @return void
	 */
	public function register( SchedulableInterface $task ): void;

	/**
	 * Get the WordPress hook name for a task ID.
	 *
	 * @since 1.2.0
	 *
	 * @param string $task_id Task identifier.
	 * @return string WordPress hook name.
	 */
	public function get_task_hook( string $task_id ): string;

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
	 * Get tasks by category/module.
	 *
	 * Categories are derived from the task ID prefix: {category}_{name}
	 * (e.g. "database_cleanup", "media_scan").
	 *
	 * @since 1.2.0
	 *
	 * @param string $category Category name (e.g., 'database', 'media', 'performance').
	 * @return array<string, SchedulableInterface> Filtered tasks.
	 */
	public function get_by_category( string $category ): array;

	/**
	 * Get all task definitions for REST API or settings UI.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, TaskDefinition> Task definitions keyed by task ID.
	 */
	public function get_task_definitions(): array;

	/**
	 * Schedule a task using Action Scheduler when available, falling back to WP-Cron.
	 *
	 * @since 1.2.0
	 *
	 * @param string $task_id   Task identifier.
	 * @param string $frequency Frequency (daily, weekly, monthly, disabled).
	 * @param int    $next_run  Next run timestamp.
	 * @param string $group     Action Scheduler group.
	 * @return void
	 */
	public function schedule_task( string $task_id, string $frequency, int $next_run, string $group = self::ACTION_SCHEDULER_GROUP ): void;

	/**
	 * Unschedule a task from Action Scheduler and WP-Cron.
	 *
	 * @since 1.2.0
	 *
	 * @param string $task_id Task identifier.
	 * @param string $group   Action Scheduler group.
	 * @return void
	 */
	public function unschedule_task( string $task_id, string $group = self::ACTION_SCHEDULER_GROUP ): void;

	/**
	 * Get the next scheduled run for a task.
	 *
	 * @since 1.2.0
	 *
	 * @param string $task_id Task identifier.
	 * @param string $group   Action Scheduler group.
	 * @return int|null Timestamp or null if not scheduled.
	 */
	public function get_next_run( string $task_id, string $group = self::ACTION_SCHEDULER_GROUP ): ?int;

	/**
	 * Execute a task by its ID.
	 *
	 * @param string $task_id Task identifier.
	 * @param array  $options Task options.
	 * @return array|null Execution result or null if task not found.
	 */
	public function execute( string $task_id, array $options = array() ): ?array;

	/**
	 * Execute a task from its WP hook.
	 *
	 * Intended to be used as the callback for task hooks registered via the registry.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $options Optional task options (must be an array).
	 * @return void
	 */
	public function handle_task_hook( $options = array() ): void;
}
