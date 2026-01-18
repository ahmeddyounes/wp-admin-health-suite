<?php
/**
 * Progress Store
 *
 * Shared progress persistence for scheduled tasks.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ProgressStore
 *
 * Provides shared progress persistence functionality for scheduled tasks.
 * Allows tasks to save and resume their state across multiple executions.
 *
 * @since 1.7.0
 * @since 1.8.0 Added pruning for stale progress data.
 */
class ProgressStore {

	/**
	 * Prefix for all progress option keys.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'wpha_progress_';

	/**
	 * Default option key when none is specified.
	 *
	 * @var string
	 */
	private string $default_option_key;

	/**
	 * Constructor.
	 *
	 * @param string $default_option_key Default option key for this store instance.
	 */
	public function __construct( string $default_option_key = '' ) {
		$this->default_option_key = $default_option_key;
	}

	/**
	 * Create a new store for a specific task.
	 *
	 * @param string $task_id Task identifier.
	 * @return self
	 */
	public static function for_task( string $task_id ): self {
		return new self( self::OPTION_PREFIX . $task_id );
	}

	/**
	 * Get the option key for a task.
	 *
	 * @param string $task_id Task identifier (optional if using default).
	 * @return string Option key.
	 */
	public function get_option_key( string $task_id = '' ): string {
		if ( '' !== $task_id ) {
			return self::OPTION_PREFIX . $task_id;
		}

		return $this->default_option_key;
	}

	/**
	 * Load progress data.
	 *
	 * @param string $option_key Option key to load from. Uses default if empty.
	 * @return array<string, mixed> Progress data or empty array.
	 */
	public function load( string $option_key = '' ): array {
		$key = $option_key ?: $this->default_option_key;

		if ( '' === $key ) {
			return array();
		}

		$progress = get_option( $key, array() );

		return is_array( $progress ) ? $progress : array();
	}

	/**
	 * Save progress data.
	 *
	 * @param array<string, mixed> $data       Progress data to save.
	 * @param string               $option_key Option key to save to. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function save( array $data, string $option_key = '' ): bool {
		$key = $option_key ?: $this->default_option_key;

		if ( '' === $key ) {
			return false;
		}

		// Add timestamp for tracking.
		if ( ! isset( $data['saved_at'] ) ) {
			$data['saved_at'] = current_time( 'mysql' );
		}

		return update_option( $key, $data, false );
	}

	/**
	 * Clear progress data.
	 *
	 * @param string $option_key Option key to clear. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function clear( string $option_key = '' ): bool {
		$key = $option_key ?: $this->default_option_key;

		if ( '' === $key ) {
			return false;
		}

		return delete_option( $key );
	}

	/**
	 * Check if progress data exists.
	 *
	 * @param string $option_key Option key to check. Uses default if empty.
	 * @return bool True if progress exists.
	 */
	public function has_progress( string $option_key = '' ): bool {
		$progress = $this->load( $option_key );
		return ! empty( $progress );
	}

	/**
	 * Get the timestamp when progress was last saved.
	 *
	 * @param string $option_key Option key to check. Uses default if empty.
	 * @return string|null Timestamp or null if not set.
	 */
	public function get_saved_at( string $option_key = '' ): ?string {
		$progress = $this->load( $option_key );
		return $progress['saved_at'] ?? null;
	}

	/**
	 * Get the interrupted timestamp if task was interrupted.
	 *
	 * @param string $option_key Option key to check. Uses default if empty.
	 * @return string|null Timestamp or null if not interrupted.
	 */
	public function get_interrupted_at( string $option_key = '' ): ?string {
		$progress = $this->load( $option_key );
		return $progress['interrupted_at'] ?? null;
	}

	/**
	 * Get completed tasks from progress.
	 *
	 * @param string $option_key Option key to check. Uses default if empty.
	 * @return array<string> List of completed task identifiers.
	 */
	public function get_completed_tasks( string $option_key = '' ): array {
		$progress = $this->load( $option_key );
		$tasks    = $progress['completed_tasks'] ?? array();

		return is_array( $tasks ) ? $tasks : array();
	}

	/**
	 * Get errors from progress.
	 *
	 * @param string $option_key Option key to check. Uses default if empty.
	 * @return array<string, string> Errors indexed by task identifier.
	 */
	public function get_errors( string $option_key = '' ): array {
		$progress = $this->load( $option_key );
		$errors   = $progress['errors'] ?? array();

		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Save progress for an interrupted task.
	 *
	 * Convenience method that adds standard interruption tracking fields.
	 *
	 * @param array<string, mixed> $data       Progress data to save.
	 * @param string               $option_key Option key to save to. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function save_interrupted( array $data, string $option_key = '' ): bool {
		$data['interrupted_at'] = current_time( 'mysql' );
		return $this->save( $data, $option_key );
	}

	/**
	 * Update specific fields in the progress data.
	 *
	 * @param array<string, mixed> $updates    Fields to update.
	 * @param string               $option_key Option key to update. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function update( array $updates, string $option_key = '' ): bool {
		$progress = $this->load( $option_key );
		$progress = array_merge( $progress, $updates );
		return $this->save( $progress, $option_key );
	}

	/**
	 * Add a completed task to the progress.
	 *
	 * @param string $task_id    Completed task identifier.
	 * @param string $option_key Option key to update. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function add_completed_task( string $task_id, string $option_key = '' ): bool {
		$progress                    = $this->load( $option_key );
		$completed                   = $progress['completed_tasks'] ?? array();
		$completed[]                 = $task_id;
		$progress['completed_tasks'] = array_unique( $completed );
		return $this->save( $progress, $option_key );
	}

	/**
	 * Add an error to the progress.
	 *
	 * @param string $task_id    Task identifier that had the error.
	 * @param string $error      Error message.
	 * @param string $option_key Option key to update. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function add_error( string $task_id, string $error, string $option_key = '' ): bool {
		$progress                      = $this->load( $option_key );
		$errors                        = $progress['errors'] ?? array();
		$errors[ $task_id ]            = $error;
		$progress['errors']            = $errors;
		return $this->save( $progress, $option_key );
	}

	/**
	 * Increment a counter in the progress data.
	 *
	 * @param string $field      Field name to increment.
	 * @param int    $amount     Amount to add (can be negative).
	 * @param string $option_key Option key to update. Uses default if empty.
	 * @return bool True on success, false on failure.
	 */
	public function increment( string $field, int $amount = 1, string $option_key = '' ): bool {
		$progress           = $this->load( $option_key );
		$current            = (int) ( $progress[ $field ] ?? 0 );
		$progress[ $field ] = $current + $amount;
		return $this->save( $progress, $option_key );
	}

	/**
	 * Check if progress is stale (older than a threshold).
	 *
	 * @param int    $max_age_seconds Maximum age in seconds (default: 1 hour).
	 * @param string $option_key      Option key to check. Uses default if empty.
	 * @return bool True if progress is stale or doesn't exist.
	 */
	public function is_stale( int $max_age_seconds = 3600, string $option_key = '' ): bool {
		$saved_at = $this->get_saved_at( $option_key );

		if ( null === $saved_at ) {
			return true;
		}

		$saved_timestamp = strtotime( $saved_at );

		if ( false === $saved_timestamp ) {
			return true;
		}

		return ( time() - $saved_timestamp ) > $max_age_seconds;
	}

	/**
	 * Clear all progress for all tasks (for admin reset).
	 *
	 * @return int Number of progress records cleared.
	 */
	public function clear_all(): int {
		global $wpdb;

		$pattern = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return (int) $deleted;
	}

	/**
	 * List all tasks with saved progress.
	 *
	 * @return array<string, array<string, mixed>> Progress data indexed by task ID.
	 */
	public function list_all(): array {
		global $wpdb;

		$prefix  = self::OPTION_PREFIX;
		$pattern = $wpdb->esc_like( $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		$result = array();

		foreach ( $rows as $row ) {
			$task_id = str_replace( $prefix, '', $row->option_name );
			$value   = maybe_unserialize( $row->option_value );

			if ( is_array( $value ) ) {
				$result[ $task_id ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Prune stale progress data older than a threshold.
	 *
	 * Removes progress entries that haven't been updated within the max age.
	 * This prevents orphaned progress data from accumulating.
	 *
	 * @since 1.8.0
	 *
	 * @param int $max_age_seconds Maximum age in seconds (default: 24 hours).
	 * @return int Number of stale progress entries removed.
	 */
	public function prune_stale( int $max_age_seconds = 86400 ): int {
		$all_progress = $this->list_all();
		$pruned       = 0;
		$cutoff       = time() - $max_age_seconds;

		foreach ( $all_progress as $task_id => $progress ) {
			$saved_at = $progress['saved_at'] ?? null;

			if ( null === $saved_at ) {
				// No timestamp - consider it stale and remove.
				$this->clear( $this->get_option_key( $task_id ) );
				++$pruned;
				continue;
			}

			$saved_timestamp = strtotime( $saved_at );

			if ( false === $saved_timestamp || $saved_timestamp < $cutoff ) {
				$this->clear( $this->get_option_key( $task_id ) );
				++$pruned;
			}
		}

		return $pruned;
	}

	/**
	 * Get count of all stored progress entries.
	 *
	 * @since 1.8.0
	 *
	 * @return int Number of progress entries.
	 */
	public function count(): int {
		global $wpdb;

		$pattern = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		return absint( $count );
	}

	/**
	 * Get statistics about stored progress data.
	 *
	 * @since 1.8.0
	 *
	 * @return array<string, mixed> Statistics including count, stale count, oldest entry.
	 */
	public function get_statistics(): array {
		$all_progress = $this->list_all();
		$total        = count( $all_progress );
		$stale        = 0;
		$oldest       = null;
		$newest       = null;
		$interrupted  = 0;
		$cutoff       = time() - 86400; // 24 hours.

		foreach ( $all_progress as $task_id => $progress ) {
			$saved_at = $progress['saved_at'] ?? null;

			if ( isset( $progress['interrupted_at'] ) ) {
				++$interrupted;
			}

			if ( null === $saved_at ) {
				++$stale;
				continue;
			}

			$saved_timestamp = strtotime( $saved_at );

			if ( false === $saved_timestamp ) {
				++$stale;
				continue;
			}

			if ( $saved_timestamp < $cutoff ) {
				++$stale;
			}

			if ( null === $oldest || $saved_timestamp < $oldest ) {
				$oldest = $saved_timestamp;
			}

			if ( null === $newest || $saved_timestamp > $newest ) {
				$newest = $saved_timestamp;
			}
		}

		return array(
			'total'        => $total,
			'stale'        => $stale,
			'interrupted'  => $interrupted,
			'oldest'       => $oldest ? gmdate( 'Y-m-d H:i:s', $oldest ) : null,
			'newest'       => $newest ? gmdate( 'Y-m-d H:i:s', $newest ) : null,
		);
	}
}
