<?php
/**
 * Activity Logger Interface
 *
 * Contract for activity logging operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ActivityLoggerInterface
 *
 * Defines the contract for logging scan and cleanup activities.
 * Centralizes the duplicated activity logging found in controllers.
 *
 * @since 1.3.0
 */
interface ActivityLoggerInterface {

	/**
	 * Log an activity.
	 *
	 * @since 1.3.0
	 *
	 * @param string $scan_type    The type of scan/operation (e.g., 'database_revisions', 'media_delete').
	 * @param int    $items_found  Number of items found.
	 * @param int    $items_cleaned Number of items cleaned/processed.
	 * @param int    $bytes_freed  Number of bytes freed (optional).
	 * @return bool True on success, false on failure.
	 */
	public function log( string $scan_type, int $items_found, int $items_cleaned = 0, int $bytes_freed = 0 ): bool;

	/**
	 * Log a database cleanup activity.
	 *
	 * Helper method for database-related activities.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type   The cleanup type (e.g., 'revisions', 'transients', 'orphaned').
	 * @param array  $result The result data from the cleanup operation.
	 * @return bool True on success, false on failure.
	 */
	public function log_database_cleanup( string $type, array $result ): bool;

	/**
	 * Log a media operation activity.
	 *
	 * Helper method for media-related activities.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type   The operation type (e.g., 'delete', 'restore', 'scan').
	 * @param array  $result The result data from the operation.
	 * @return bool True on success, false on failure.
	 */
	public function log_media_operation( string $type, array $result ): bool;

	/**
	 * Log a performance check activity.
	 *
	 * Helper method for performance-related activities.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type   The check type (e.g., 'query_analysis', 'cache_check').
	 * @param array  $result The result data from the check.
	 * @return bool True on success, false on failure.
	 */
	public function log_performance_check( string $type, array $result ): bool;

	/**
	 * Get recent activity logs.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $limit   Maximum number of entries to return.
	 * @param string $type    Optional type filter (e.g., 'database', 'media').
	 * @return array Array of activity log entries.
	 */
	public function get_recent( int $limit = 10, string $type = '' ): array;

	/**
	 * Check if the activity log table exists.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists(): bool;

	/**
	 * Prune old activity logs based on retention settings.
	 *
	 * @since 1.4.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_old_logs(): int;

	/**
	 * Get the total count of log entries.
	 *
	 * @since 1.4.0
	 *
	 * @return int Total count of log entries.
	 */
	public function get_log_count(): int;
}
