<?php
/**
 * Scheduler Registry
 *
 * Manages registration and execution of schedulable tasks.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

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
	 * Registered tasks.
	 *
	 * @var array<string, SchedulableInterface>
	 */
	private array $tasks = array();

	/**
	 * {@inheritdoc}
	 */
	public function register( SchedulableInterface $task ): void {
		$this->tasks[ $task->get_task_id() ] = $task;

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
	 * Acquire a lock for task execution.
	 *
	 * Uses MySQL advisory locks (GET_LOCK) for truly atomic locking.
	 * Falls back to transients if GET_LOCK is unavailable.
	 *
	 * @since 1.2.0
	 * @since 1.2.1 Use MySQL GET_LOCK for atomic operations.
	 *
	 * @param string $task_id The task identifier.
	 * @return bool True if lock acquired, false if task is already locked.
	 */
	private function acquire_lock( string $task_id ): bool {
		global $wpdb;

		$lock_name = 'wpha_task_' . md5( $task_id );

		// Try MySQL advisory lock first (atomic operation).
		// GET_LOCK returns: 1 = acquired, 0 = already held, NULL = error.
		// Using timeout of 0 means don't wait, return immediately.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT GET_LOCK(%s, 0)',
				$lock_name
			)
		);

		if ( 1 === (int) $result ) {
			// Store lock info in transient for debugging/monitoring.
			$lock_value = array(
				'started_at' => time(),
				'pid'        => getmypid(),
			);
			set_transient( self::LOCK_PREFIX . $task_id, $lock_value, self::LOCK_TIMEOUT );
			return true;
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
		global $wpdb;

		$lock_name = 'wpha_task_' . md5( $task_id );

		// Release MySQL advisory lock.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT RELEASE_LOCK(%s)',
				$lock_name
			)
		);

		// Also clean up the transient.
		delete_transient( self::LOCK_PREFIX . $task_id );

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
				'settings_schema'   => $task->get_settings_schema(),
			);
		}

		return $definitions;
	}
}
