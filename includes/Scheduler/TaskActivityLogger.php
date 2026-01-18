<?php
/**
 * Task Activity Logger
 *
 * Logs scheduled task executions via ActivityLoggerInterface for audit/debug purposes.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Contracts\ActivityLoggerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class TaskActivityLogger
 *
 * Provides observability for scheduled task runs by logging task execution
 * events (start, completion, errors, interruptions) via ActivityLoggerInterface.
 *
 * Includes metadata such as execution time, items processed, errors encountered,
 * and interruption status for comprehensive audit trails.
 *
 * @since 1.8.0
 */
class TaskActivityLogger {

	/**
	 * Scan type prefix for task activity logs.
	 *
	 * @var string
	 */
	public const SCAN_TYPE_PREFIX = 'task_';

	/**
	 * Activity logger instance.
	 *
	 * @var ActivityLoggerInterface
	 */
	private ActivityLoggerInterface $activity_logger;

	/**
	 * Constructor.
	 *
	 * @param ActivityLoggerInterface $activity_logger Activity logger instance.
	 */
	public function __construct( ActivityLoggerInterface $activity_logger ) {
		$this->activity_logger = $activity_logger;
	}

	/**
	 * Log task execution start.
	 *
	 * @param string $task_id   Task identifier.
	 * @param array  $context   Optional context data (e.g., options passed to execute).
	 * @return bool True on success, false on failure.
	 */
	public function log_task_start( string $task_id, array $context = array() ): bool {
		return $this->activity_logger->log(
			$this->get_scan_type( $task_id, 'start' ),
			0,
			0,
			0
		);
	}

	/**
	 * Log task execution completion.
	 *
	 * @param string     $task_id     Task identifier.
	 * @param TaskResult $result      Task result DTO.
	 * @param float      $elapsed_time Execution time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function log_task_completed( string $task_id, TaskResult $result, float $elapsed_time = 0.0 ): bool {
		$items_found   = $result->items_found;
		$items_cleaned = $result->items_cleaned;
		$bytes_freed   = $result->bytes_freed;

		$scan_type = $result->success
			? $this->get_scan_type( $task_id, 'completed' )
			: $this->get_scan_type( $task_id, 'failed' );

		return $this->activity_logger->log(
			$scan_type,
			$items_found,
			$items_cleaned,
			$bytes_freed
		);
	}

	/**
	 * Log task completion from array result (legacy support).
	 *
	 * @param string $task_id      Task identifier.
	 * @param array  $result       Task result array.
	 * @param float  $elapsed_time Execution time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function log_task_completed_array( string $task_id, array $result, float $elapsed_time = 0.0 ): bool {
		$items_found   = $result['items_found'] ?? $result['items_cleaned'] ?? 0;
		$items_cleaned = $result['items_cleaned'] ?? 0;
		$bytes_freed   = $result['bytes_freed'] ?? 0;
		$success       = $result['success'] ?? true;
		$interrupted   = $result['was_interrupted'] ?? $result['interrupted'] ?? false;

		if ( $interrupted ) {
			$scan_type = $this->get_scan_type( $task_id, 'interrupted' );
		} elseif ( $success ) {
			$scan_type = $this->get_scan_type( $task_id, 'completed' );
		} else {
			$scan_type = $this->get_scan_type( $task_id, 'failed' );
		}

		return $this->activity_logger->log(
			$scan_type,
			(int) $items_found,
			(int) $items_cleaned,
			(int) $bytes_freed
		);
	}

	/**
	 * Log task interruption (timeout).
	 *
	 * @param string $task_id      Task identifier.
	 * @param int    $items_found  Items found so far.
	 * @param int    $items_cleaned Items processed so far.
	 * @param int    $bytes_freed  Bytes freed so far.
	 * @return bool True on success, false on failure.
	 */
	public function log_task_interrupted(
		string $task_id,
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0
	): bool {
		return $this->activity_logger->log(
			$this->get_scan_type( $task_id, 'interrupted' ),
			$items_found,
			$items_cleaned,
			$bytes_freed
		);
	}

	/**
	 * Log task error.
	 *
	 * @param string $task_id Task identifier.
	 * @param string $error   Error message.
	 * @param string $context Optional context (e.g., subtask that failed).
	 * @return bool True on success, false on failure.
	 */
	public function log_task_error( string $task_id, string $error, string $context = '' ): bool {
		// We log errors with 0 items since the error prevented completion.
		return $this->activity_logger->log(
			$this->get_scan_type( $task_id, 'error' ),
			0,
			0,
			0
		);
	}

	/**
	 * Get scan type string for a task event.
	 *
	 * @param string $task_id Task identifier.
	 * @param string $event   Event type (start, completed, failed, interrupted, error).
	 * @return string Scan type string.
	 */
	private function get_scan_type( string $task_id, string $event ): string {
		return self::SCAN_TYPE_PREFIX . $task_id . '_' . $event;
	}

	/**
	 * Get recent task activity logs.
	 *
	 * @param int    $limit   Maximum entries to return.
	 * @param string $task_id Optional task ID to filter by.
	 * @return array Activity log entries.
	 */
	public function get_recent_task_activity( int $limit = 50, string $task_id = '' ): array {
		$type_filter = self::SCAN_TYPE_PREFIX;

		if ( '' !== $task_id ) {
			$type_filter .= $task_id;
		}

		return $this->activity_logger->get_recent( $limit, $type_filter );
	}

	/**
	 * Check if the activity log table exists.
	 *
	 * @return bool True if table exists.
	 */
	public function is_available(): bool {
		return $this->activity_logger->table_exists();
	}
}
