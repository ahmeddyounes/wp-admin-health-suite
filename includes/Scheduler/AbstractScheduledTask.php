<?php
/**
 * Abstract Scheduled Task
 *
 * Base class for scheduled task implementations.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Scheduler\Contracts\SchedulableInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AbstractScheduledTask
 *
 * Provides common functionality for scheduled tasks.
 *
 * @since 1.2.0
 * @since 1.7.0 Added TaskResult and ProgressStore integration.
 * @since 1.8.0 Added lifecycle hooks for task observability.
 */
abstract class AbstractScheduledTask implements SchedulableInterface {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = '';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = '';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'daily';

	/**
	 * Settings option key for enabled status.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = '';

	/**
	 * Start time of the current execution.
	 *
	 * Used by long-running tasks for timeout management.
	 *
	 * @var float
	 */
	protected float $start_time = 0.0;

	/**
	 * Time limit for the current execution in seconds.
	 *
	 * @var int
	 */
	protected int $time_limit = 0;

	/**
	 * Default time limit in seconds for long-running tasks.
	 *
	 * @var int
	 */
	protected int $default_time_limit = 25;

	/**
	 * Safety buffer in seconds to stop before hitting the time limit.
	 *
	 * Used both for deriving an effective time limit from PHP max_execution_time
	 * and for deciding when to stop processing.
	 *
	 * @var int
	 */
	protected int $time_buffer = 3;

	/**
	 * Minimum effective time limit in seconds.
	 *
	 * @var int
	 */
	protected int $minimum_time_limit = 5;

	/**
	 * Option key for persisting task progress.
	 *
	 * Leave empty to disable progress persistence.
	 *
	 * @var string
	 */
	protected string $progress_option_key = '';

	/**
	 * Progress store instance for this task.
	 *
	 * @since 1.7.0
	 * @var ProgressStore|null
	 */
	private ?ProgressStore $progress_store = null;

	/**
	 * {@inheritdoc}
	 */
	public function get_task_id(): string {
		return $this->task_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_task_name(): string {
		return $this->task_name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_frequency(): string {
		return $this->default_frequency;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(): bool {
		if ( empty( $this->enabled_option_key ) ) {
			return true;
		}

		$settings = get_option( 'wpha_settings', array() );
		return ! empty( $settings[ $this->enabled_option_key ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array();
	}

	/**
	 * Initialize execution context for long-running tasks.
	 *
	 * Sets the start time and configures the time limit.
	 *
	 * @param array $options Task options.
	 * @return void
	 */
	protected function init_execution_context( array $options = array() ): void {
		$this->start_time = microtime( true );
		$this->configure_time_limit( $options );
	}

	/**
	 * Configure the time limit based on PHP settings and options.
	 *
	 * Supports an optional `time_limit` override in `$options` for manual runs/tests.
	 *
	 * @param array $options Task options.
	 * @return void
	 */
	protected function configure_time_limit( array $options = array() ): void {
		// Allow overriding via options (useful for manual runs or testing).
		if ( array_key_exists( 'time_limit', $options ) ) {
			$raw_time_limit = $options['time_limit'];

			if ( is_int( $raw_time_limit ) && $raw_time_limit > 0 ) {
				$this->time_limit = $raw_time_limit;
				return;
			}

			if ( is_string( $raw_time_limit ) && ctype_digit( $raw_time_limit ) ) {
				$time_limit = (int) $raw_time_limit;
				if ( $time_limit > 0 ) {
					$this->time_limit = $time_limit;
					return;
				}
			}
		}

		// Try to determine the PHP max_execution_time.
		$max_execution_time = (int) ini_get( 'max_execution_time' );

		// If max_execution_time is 0 (unlimited) or not set, use our default.
		if ( $max_execution_time <= 0 ) {
			$this->time_limit = max( $this->default_time_limit, $this->minimum_time_limit );
			return;
		}

		// Use the smaller of PHP's limit (minus buffer) or our default.
		$this->time_limit = min(
			$max_execution_time - $this->time_buffer,
			$this->default_time_limit
		);

		// Ensure we have at least some time to work.
		$this->time_limit = max( $this->time_limit, $this->minimum_time_limit );
	}

	/**
	 * Check if the time limit is approaching.
	 *
	 * @return bool True if we should stop processing.
	 */
	protected function is_time_limit_approaching(): bool {
		if ( $this->start_time <= 0.0 || $this->time_limit <= 0 ) {
			return false;
		}

		$elapsed = microtime( true ) - $this->start_time;
		return $elapsed >= ( $this->time_limit - $this->time_buffer );
	}

	/**
	 * Get the remaining time in seconds.
	 *
	 * @return float Remaining time in seconds.
	 */
	protected function get_remaining_time(): float {
		if ( $this->start_time <= 0.0 || $this->time_limit <= 0 ) {
			return 0.0;
		}

		$elapsed = microtime( true ) - $this->start_time;
		return max( 0, $this->time_limit - $elapsed - $this->time_buffer );
	}

	/**
	 * Get the option key used for persisting task progress.
	 *
	 * @return string Progress option key or empty string when disabled.
	 */
	protected function get_progress_option_key(): string {
		return $this->progress_option_key;
	}

	/**
	 * Load saved progress from a previous interrupted run.
	 *
	 * @return array Progress data or empty array.
	 */
	protected function load_progress(): array {
		$option_key = $this->get_progress_option_key();
		if ( '' === $option_key ) {
			return array();
		}

		$progress = get_option( $option_key, array() );

		if ( ! empty( $progress ) && is_array( $progress ) ) {
			$this->log( 'Resuming from saved progress' );
		}

		return is_array( $progress ) ? $progress : array();
	}

	/**
	 * Save progress for later continuation.
	 *
	 * @param array $progress Progress data to save.
	 * @return bool True on success, false on failure.
	 */
	protected function save_progress( array $progress ): bool {
		$option_key = $this->get_progress_option_key();
		if ( '' === $option_key ) {
			return false;
		}

		return update_option( $option_key, $progress, false );
	}

	/**
	 * Clear saved progress.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function clear_progress(): bool {
		$option_key = $this->get_progress_option_key();
		if ( '' === $option_key ) {
			return false;
		}

		return delete_option( $option_key );
	}

	/**
	 * Get the current progress for external monitoring.
	 *
	 * @return array Current progress data.
	 */
	public function get_progress(): array {
		return $this->load_progress();
	}

	/**
	 * Check if a previous run was interrupted and needs resuming.
	 *
	 * @return bool True if there's saved progress to resume.
	 */
	public function has_pending_progress(): bool {
		$option_key = $this->get_progress_option_key();
		if ( '' === $option_key ) {
			return false;
		}

		$progress = get_option( $option_key, array() );
		return ! empty( $progress );
	}

	/**
	 * Force clear any saved progress (useful for admin reset).
	 *
	 * @return bool True on success.
	 */
	public function reset_progress(): bool {
		$this->log( 'Progress manually reset' );
		return $this->clear_progress();
	}

	/**
	 * Execute a callback with error recovery.
	 *
	 * @param callable $callback Callback to execute.
	 * @param array    $fallback Fallback result returned on exception.
	 * @param string   $context  Optional context for logging (e.g., "subtask revisions").
	 * @return array Callback result, or fallback with an added 'error' key.
	 */
	protected function execute_with_recovery( callable $callback, array $fallback, string $context = '' ): array {
		try {
			$result = $callback();
			return is_array( $result ) ? $result : $fallback;
		} catch ( \Throwable $e ) {
			$this->log_exception( $e, $context );

			if ( ! array_key_exists( 'error', $fallback ) ) {
				$fallback['error'] = $e->getMessage();
			}

			return $fallback;
		}
	}

	/**
	 * Log an exception in a consistent format.
	 *
	 * @param \Throwable $exception Exception instance.
	 * @param string     $context   Optional context string.
	 * @return void
	 */
	protected function log_exception( \Throwable $exception, string $context = '' ): void {
		if ( '' === $context ) {
			$this->log(
				sprintf(
					'Exception: %s in %s:%d',
					$exception->getMessage(),
					$exception->getFile(),
					$exception->getLine()
				),
				'error'
			);
			return;
		}

		$this->log(
			sprintf(
				'Exception in %s: %s in %s:%d',
				$context,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			),
			'error'
		);
	}

	/**
	 * Create a standard result array.
	 *
	 * @param int  $items_cleaned Number of items cleaned.
	 * @param int  $bytes_freed   Bytes freed.
	 * @param bool $success       Whether execution was successful.
	 * @return array Result array.
	 */
	protected function create_result( int $items_cleaned = 0, int $bytes_freed = 0, bool $success = true ): array {
		return array(
			'items_cleaned' => $items_cleaned,
			'items_found'   => $items_cleaned, // For backwards compatibility.
			'bytes_freed'   => $bytes_freed,
			'success'       => $success,
			'task_id'       => $this->task_id,
			'executed_at'   => current_time( 'mysql' ),
		);
	}

	/**
	 * Create a TaskResult DTO.
	 *
	 * Provides a standardized way to create task results.
	 *
	 * @since 1.7.0
	 *
	 * @param int                   $items_found   Number of items found.
	 * @param int                   $items_cleaned Number of items cleaned.
	 * @param int                   $bytes_freed   Bytes freed.
	 * @param bool                  $success       Whether execution was successful.
	 * @param array<string, string> $errors        Errors encountered.
	 * @param bool                  $interrupted   Whether the task was interrupted.
	 * @param string|null           $next_run      Next scheduled run timestamp.
	 * @return TaskResult
	 */
	protected function create_task_result(
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0,
		bool $success = true,
		array $errors = array(),
		bool $interrupted = false,
		?string $next_run = null
	): TaskResult {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		return new TaskResult(
			$success,
			$items_found,
			$items_cleaned,
			$bytes_freed,
			$errors,
			$interrupted,
			$next_run,
			$this->task_id,
			current_time( 'mysql' ),
			$elapsed_time
		);
	}

	/**
	 * Create a success TaskResult.
	 *
	 * @since 1.7.0
	 *
	 * @param int $items_found   Number of items found.
	 * @param int $items_cleaned Number of items cleaned.
	 * @param int $bytes_freed   Bytes freed.
	 * @return TaskResult
	 */
	protected function create_success_result(
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0
	): TaskResult {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		return TaskResult::success(
			$this->task_id,
			$items_found,
			$items_cleaned,
			$bytes_freed,
			$elapsed_time
		);
	}

	/**
	 * Create a failure TaskResult.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, string> $errors Errors encountered.
	 * @return TaskResult
	 */
	protected function create_failure_result( array $errors = array() ): TaskResult {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		return TaskResult::failure(
			$this->task_id,
			$errors,
			$elapsed_time
		);
	}

	/**
	 * Create an interrupted TaskResult.
	 *
	 * @since 1.7.0
	 *
	 * @param int                   $items_found   Number of items found so far.
	 * @param int                   $items_cleaned Number of items cleaned so far.
	 * @param int                   $bytes_freed   Bytes freed so far.
	 * @param array<string, string> $errors        Errors encountered.
	 * @param string|null           $next_run      Next scheduled run timestamp.
	 * @return TaskResult
	 */
	protected function create_interrupted_result(
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0,
		array $errors = array(),
		?string $next_run = null
	): TaskResult {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		return TaskResult::interrupted(
			$this->task_id,
			$items_found,
			$items_cleaned,
			$bytes_freed,
			$errors,
			$next_run,
			$elapsed_time
		);
	}

	/**
	 * Get the ProgressStore instance for this task.
	 *
	 * @since 1.7.0
	 *
	 * @return ProgressStore
	 */
	protected function get_progress_store(): ProgressStore {
		if ( null === $this->progress_store ) {
			$option_key           = $this->get_progress_option_key();
			$this->progress_store = '' !== $option_key
				? new ProgressStore( $option_key )
				: new ProgressStore();
		}

		return $this->progress_store;
	}

	/**
	 * Save progress for an interrupted task using the ProgressStore.
	 *
	 * Convenience method that adds standard interruption tracking.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, mixed> $data Progress data to save.
	 * @return bool True on success, false on failure.
	 */
	protected function save_interrupted_progress( array $data ): bool {
		$option_key = $this->get_progress_option_key();
		if ( '' === $option_key ) {
			return false;
		}

		return $this->get_progress_store()->save_interrupted( $data, $option_key );
	}

	/**
	 * Log task execution.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, warning, error).
	 * @return void
	 */
	protected function log( string $message, string $level = 'info' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[WP Admin Health Suite] [%s] Task %s: %s',
					strtoupper( $level ),
					$this->task_id,
					$message
				)
			);
		}

		/**
		 * Fires when a task logs a message.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_task_log
		 *
		 * @param string $task_id The task identifier.
		 * @param string $message The log message.
		 * @param string $level   The log level.
		 */
		do_action( 'wpha_task_log', $this->task_id, $message, $level );
	}

	/**
	 * Notify that task execution is starting.
	 *
	 * Called at the beginning of execute() to provide observability.
	 * Subscribers can use this to track task start times, set up monitoring, etc.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $context Execution context (options passed to execute).
	 * @return void
	 */
	protected function notify_task_started( array $context = array() ): void {
		/**
		 * Fires when a scheduled task starts execution.
		 *
		 * @since 1.8.0
		 *
		 * @hook wpha_task_started
		 *
		 * @param string               $task_id    The task identifier.
		 * @param string               $task_name  The human-readable task name.
		 * @param array<string, mixed> $context    Execution context (options passed to execute).
		 * @param float                $start_time Microtime when task started.
		 */
		do_action( 'wpha_task_started', $this->task_id, $this->task_name, $context, $this->start_time );
	}

	/**
	 * Notify that task execution completed successfully.
	 *
	 * Called when execute() completes without interruption or failure.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $result Task result array.
	 * @return void
	 */
	protected function notify_task_completed( array $result ): void {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		/**
		 * Fires when a scheduled task completes successfully.
		 *
		 * @since 1.8.0
		 *
		 * @hook wpha_task_completed
		 *
		 * @param string               $task_id      The task identifier.
		 * @param string               $task_name    The human-readable task name.
		 * @param array<string, mixed> $result       Task result data.
		 * @param float                $elapsed_time Execution time in seconds.
		 */
		do_action( 'wpha_task_completed', $this->task_id, $this->task_name, $result, $elapsed_time );
	}

	/**
	 * Notify that task execution was interrupted due to time limit.
	 *
	 * Called when execute() must stop early due to approaching time limit.
	 * The task should have saved progress for resumption.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $result   Partial task result.
	 * @param array<string, mixed> $progress Saved progress data.
	 * @return void
	 */
	protected function notify_task_interrupted( array $result, array $progress = array() ): void {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		/**
		 * Fires when a scheduled task is interrupted due to time limit.
		 *
		 * @since 1.8.0
		 *
		 * @hook wpha_task_interrupted
		 *
		 * @param string               $task_id      The task identifier.
		 * @param string               $task_name    The human-readable task name.
		 * @param array<string, mixed> $result       Partial task result data.
		 * @param array<string, mixed> $progress     Saved progress for resumption.
		 * @param float                $elapsed_time Execution time in seconds.
		 */
		do_action( 'wpha_task_interrupted', $this->task_id, $this->task_name, $result, $progress, $elapsed_time );
	}

	/**
	 * Notify that task execution failed.
	 *
	 * Called when execute() encounters an unrecoverable error.
	 *
	 * @since 1.8.0
	 *
	 * @param string               $error   Error message.
	 * @param array<string, mixed> $context Additional context (e.g., subtask that failed).
	 * @return void
	 */
	protected function notify_task_failed( string $error, array $context = array() ): void {
		$elapsed_time = $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;

		/**
		 * Fires when a scheduled task fails.
		 *
		 * @since 1.8.0
		 *
		 * @hook wpha_task_failed
		 *
		 * @param string               $task_id      The task identifier.
		 * @param string               $task_name    The human-readable task name.
		 * @param string               $error        Error message.
		 * @param array<string, mixed> $context      Additional context.
		 * @param float                $elapsed_time Execution time in seconds.
		 */
		do_action( 'wpha_task_failed', $this->task_id, $this->task_name, $error, $context, $elapsed_time );
	}

	/**
	 * Get the elapsed execution time in seconds.
	 *
	 * @since 1.8.0
	 *
	 * @return float Elapsed time or 0.0 if not started.
	 */
	protected function get_elapsed_time(): float {
		return $this->start_time > 0 ? microtime( true ) - $this->start_time : 0.0;
	}
}
