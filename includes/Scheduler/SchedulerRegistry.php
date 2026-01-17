<?php
/**
 * Scheduler Registry
 *
 * Manages registration and execution of schedulable tasks.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulableInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulerRegistryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SchedulerRegistry
 *
 * Central registry for all schedulable tasks.
 *
 * @since 1.2.0
 */
class SchedulerRegistry implements SchedulerRegistryInterface {

	/**
	 * Lock timeout in seconds.
	 *
	 * @var int
	 */
	const LOCK_TIMEOUT = 300;

	/**
	 * Lock prefix for transients.
	 *
	 * @var string
	 */
	const LOCK_PREFIX = 'wpha_task_lock_';

	/**
	 * Task hook prefix.
	 *
	 * Task IDs are mapped to WordPress hooks using: wpha_{task_id}
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	const TASK_HOOK_PREFIX = 'wpha_';

	/**
	 * Default Action Scheduler group for recurring tasks.
	 *
	 * @since 1.2.0
	 *
	 * @var string
	 */
	const ACTION_SCHEDULER_GROUP = 'wpha_scheduling';

	/**
	 * Registered tasks.
	 *
	 * @var array<string, SchedulableInterface>
	 */
	private array $tasks = array();

	/**
	 * Map of hook name to task ID for registered task hooks.
	 *
	 * @var array<string, string>
	 */
	private array $hook_to_task_id = array();

	/**
	 * Database connection.
	 *
	 * @since 1.3.0
	 * @var ConnectionInterface|null
	 */
	private ?ConnectionInterface $connection = null;

	/**
	 * {@inheritdoc}
	 */
	public function register( SchedulableInterface $task ): void {
		$task_id = $task->get_task_id();
		$this->tasks[ $task_id ] = $task;

		// Register the WP-Cron hook for this task so WP-Cron / Action Scheduler can execute it.
		$this->register_task_hook( $task_id );

		/**
		 * Fires when a task is registered with the scheduler.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_scheduler_task_registered
		 *
		 * @param SchedulableInterface $task    The registered task.
		 * @param string               $task_id The task identifier.
		 */
		do_action( 'wpha_scheduler_task_registered', $task, $task->get_task_id() );
	}

	/**
	 * Get the WordPress hook name for a task ID.
	 *
	 * @param string $task_id Task identifier.
	 * @return string WordPress hook name.
	 */
	public function get_task_hook( string $task_id ): string {
		return self::TASK_HOOK_PREFIX . $task_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $task_id ): ?SchedulableInterface {
		return $this->tasks[ $task_id ] ?? null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_all(): array {
		return $this->tasks;
	}

	/**
	 * Set the database connection.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection instance.
	 * @return void
	 */
	public function set_connection( ConnectionInterface $connection ): void {
		$this->connection = $connection;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $task_id ): bool {
		return isset( $this->tasks[ $task_id ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_enabled(): array {
		return array_filter(
			$this->tasks,
			function ( SchedulableInterface $task ) {
				return $task->is_enabled();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( string $task_id, array $options = array() ): ?array {
		$task = $this->get( $task_id );

		if ( null === $task ) {
			return null;
		}

		// Attempt to acquire lock to prevent concurrent execution.
		if ( ! $this->acquire_lock( $task_id ) ) {
			return array(
				'success' => false,
				'error'   => 'Task is already running',
				'task_id' => $task_id,
				'skipped' => true,
			);
		}

		/**
		 * Fires before a scheduled task executes.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_scheduler_before_execute
		 *
		 * @param SchedulableInterface $task    The task about to execute.
		 * @param array                $options Task options.
		 */
		do_action( 'wpha_scheduler_before_execute', $task, $options );

		try {
			$result = $task->execute( $options );
		} catch ( \Throwable $e ) {
			// Release lock on error.
			$this->release_lock( $task_id );

			// Log the error.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[WP Admin Health Suite] Task "%s" failed: %s in %s:%d',
						$task_id,
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					)
				);
			}

			/**
			 * Fires when a scheduled task fails.
			 *
			 * @since 1.2.0
			 *
			 * @hook wpha_scheduler_task_failed
			 *
			 * @param SchedulableInterface $task      The task that failed.
			 * @param \Throwable           $exception The exception that was thrown.
			 * @param array                $options   Task options.
			 */
			do_action( 'wpha_scheduler_task_failed', $task, $e, $options );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
				'task_id' => $task_id,
			);
		}

		// Release lock after successful execution.
		$this->release_lock( $task_id );

		/**
		 * Fires after a scheduled task executes.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_scheduler_after_execute
		 *
		 * @param SchedulableInterface $task    The task that executed.
		 * @param array                $result  Execution result.
		 * @param array                $options Task options.
		 */
		do_action( 'wpha_scheduler_after_execute', $task, $result, $options );

		return $result;
	}

	/**
	 * Execute a task from its WP hook.
	 *
	 * This enables WP-Cron and Action Scheduler to execute registered tasks
	 * using their hook names (e.g. "wpha_database_cleanup").
	 *
	 * @param mixed $options Optional task options (must be an array).
	 * @return void
	 */
	public function handle_task_hook( $options = array() ): void {
		$hook = current_filter();
		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		$options = is_array( $options ) ? $options : array();

		$task_id = $this->hook_to_task_id[ $hook ] ?? null;

		// Fallback for hooks not registered via the registry (e.g. legacy hooks).
		if ( null === $task_id && 0 === strpos( $hook, self::TASK_HOOK_PREFIX ) ) {
			$task_id = substr( $hook, strlen( self::TASK_HOOK_PREFIX ) );
		}

		if ( ! is_string( $task_id ) || '' === $task_id ) {
			return;
		}

		$this->execute( $task_id, $options );
	}

	/**
	 * Register the WP hook used to execute a task.
	 *
	 * @param string $task_id Task identifier.
	 * @return void
	 */
	private function register_task_hook( string $task_id ): void {
		$hook = $this->get_task_hook( $task_id );
		$this->hook_to_task_id[ $hook ] = $task_id;

		// Avoid duplicate registrations on repeated task registration.
		if ( has_action( $hook, array( $this, 'handle_task_hook' ) ) ) {
			return;
		}

		add_action( $hook, array( $this, 'handle_task_hook' ), 10, 1 );
	}

	/**
	 * Acquire a lock for task execution.
	 *
	 * Uses MySQL advisory locks (GET_LOCK) for truly atomic locking.
	 * Falls back to an option-based lock if GET_LOCK is unavailable.
	 *
	 * @since 1.2.0
	 * @since 1.2.1 Use MySQL GET_LOCK for atomic operations.
	 *
	 * @param string $task_id The task identifier.
	 * @return bool True if lock acquired, false if task is already locked.
	 */
	private function acquire_lock( string $task_id ): bool {
		$lock_name = 'wpha_task_' . md5( $task_id );
		$lock_key  = self::LOCK_PREFIX . md5( $task_id );

		// Try MySQL advisory lock first (atomic operation).
		// GET_LOCK returns: 1 = acquired, 0 = already held, NULL = error.
		// Using timeout of 0 means don't wait, return immediately.
		if ( $this->connection ) {
			$query  = $this->connection->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name );
			$result = $query ? $this->connection->get_var( $query ) : null;
		} else {
			global $wpdb;
			$result = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT GET_LOCK(%s, 0)',
					$lock_name
				)
			);
		}

		$lock_value = array(
			'started_at' => time(),
			'pid'        => getmypid(),
		);

		if ( 1 === (int) $result ) {
			// Store lock info in transient for debugging/monitoring.
			set_transient( $lock_key, $lock_value, self::LOCK_TIMEOUT );
			return true;
		}

		// If GET_LOCK is unavailable (NULL/error), fall back to an option-based lock.
		if ( null === $result ) {
			// add_option is atomic at the DB level and provides a reasonable fallback for environments
			// where GET_LOCK is unavailable (e.g. some DB proxies).
			$acquired = add_option( $lock_key, $lock_value, '', 'no' );

			if ( $acquired ) {
				set_transient( $lock_key, $lock_value, self::LOCK_TIMEOUT );
				return true;
			}

			$existing = get_option( $lock_key );
			$started  = is_array( $existing ) && isset( $existing['started_at'] ) ? (int) $existing['started_at'] : 0;

			// If the lock appears stale, attempt to recover.
			if ( $started > 0 && ( time() - $started ) > self::LOCK_TIMEOUT ) {
				delete_option( $lock_key );
				$acquired = add_option( $lock_key, $lock_value, '', 'no' );

				if ( $acquired ) {
					set_transient( $lock_key, $lock_value, self::LOCK_TIMEOUT );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Release a lock for task execution.
	 *
	 * @since 1.2.0
	 * @since 1.2.1 Use MySQL RELEASE_LOCK for atomic operations.
	 *
	 * @param string $task_id The task identifier.
	 * @return bool True if lock was released, false otherwise.
	 */
	private function release_lock( string $task_id ): bool {
		$lock_name = 'wpha_task_' . md5( $task_id );
		$lock_key  = self::LOCK_PREFIX . md5( $task_id );

		// Release MySQL advisory lock.
		if ( $this->connection ) {
			$query  = $this->connection->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name );
			$result = $query ? $this->connection->get_var( $query ) : null;
		} else {
			global $wpdb;
			$result = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT RELEASE_LOCK(%s)',
					$lock_name
				)
			);
		}

		// Also clean up the transient.
		delete_transient( $lock_key );

		// Clean up option-based lock fallback (if used).
		delete_option( $lock_key );

		return 1 === (int) $result;
	}

	/**
	 * Get tasks by category/module.
	 *
	 * @param string $category Category name (e.g., 'database', 'media', 'performance').
	 * @return array<string, SchedulableInterface> Filtered tasks.
	 */
	public function get_by_category( string $category ): array {
		$prefix = $category . '_';
		return array_filter(
			$this->tasks,
			function ( SchedulableInterface $task ) use ( $prefix ) {
				return 0 === strpos( $task->get_task_id(), $prefix );
			}
		);
	}

	/**
	 * Get all task definitions for REST API or settings UI.
	 *
	 * @return array Array of task definitions.
	 */
	public function get_task_definitions(): array {
		$definitions = array();

		foreach ( $this->tasks as $task_id => $task ) {
			$definitions[ $task_id ] = array(
				'id'                => $task->get_task_id(),
				'name'              => $task->get_task_name(),
				'description'       => $task->get_description(),
				'default_frequency' => $task->get_default_frequency(),
				'enabled'           => $task->is_enabled(),
				'hook'              => $this->get_task_hook( $task->get_task_id() ),
				'next_run'          => $this->get_next_run( $task->get_task_id() ),
				'settings_schema'   => $task->get_settings_schema(),
			);
		}

		return $definitions;
	}

	/**
	 * Schedule a task using Action Scheduler when available, falling back to WP-Cron.
	 *
	 * @param string $task_id   Task identifier.
	 * @param string $frequency Frequency (daily, weekly, monthly).
	 * @param int    $next_run  Next run timestamp.
	 * @param string $group     Action Scheduler group.
	 * @return void
	 */
	public function schedule_task( string $task_id, string $frequency, int $next_run, string $group = self::ACTION_SCHEDULER_GROUP ): void {
		$hook = $this->get_task_hook( $task_id );

		if ( 'disabled' === $frequency ) {
			$this->unschedule_task( $task_id, $group );
			return;
		}

		$interval = $this->get_interval_seconds( $frequency );
		if ( ! $interval ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), $group );
			as_schedule_recurring_action( $next_run, $interval, $hook, array(), $group );
			return;
		}

		// Ensure we don't accidentally leave multiple schedules behind.
		wp_clear_scheduled_hook( $hook );
		wp_schedule_event( $next_run, $this->get_cron_schedule_name( $frequency ), $hook );
	}

	/**
	 * Unschedule a task from Action Scheduler and WP-Cron.
	 *
	 * @param string $task_id Task identifier.
	 * @param string $group   Action Scheduler group.
	 * @return void
	 */
	public function unschedule_task( string $task_id, string $group = self::ACTION_SCHEDULER_GROUP ): void {
		$hook = $this->get_task_hook( $task_id );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), $group );
		}

		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Get the next scheduled run for a task.
	 *
	 * @param string $task_id Task identifier.
	 * @param string $group   Action Scheduler group.
	 * @return int|null Timestamp or null if not scheduled.
	 */
	public function get_next_run( string $task_id, string $group = self::ACTION_SCHEDULER_GROUP ): ?int {
		$hook = $this->get_task_hook( $task_id );

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$next = as_next_scheduled_action( $hook, array(), $group );
		} else {
			$next = wp_next_scheduled( $hook );
		}

		return false === $next ? null : (int) $next;
	}

	/**
	 * Get interval in seconds for a frequency.
	 *
	 * @param string $frequency Frequency name.
	 * @return int|false Interval in seconds, or false if invalid.
	 */
	private function get_interval_seconds( string $frequency ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return $intervals[ $frequency ] ?? false;
	}

	/**
	 * Get WP-Cron schedule name.
	 *
	 * @param string $frequency Frequency.
	 * @return string Schedule name.
	 */
	private function get_cron_schedule_name( string $frequency ): string {
		$schedules = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);

		return $schedules[ $frequency ] ?? 'daily';
	}
}
