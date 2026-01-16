<?php
/**
 * Table Checker Interface
 *
 * Contract for table existence and metadata operations with caching.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface TableCheckerInterface
 *
 * Defines the contract for checking table existence and retrieving table metadata.
 * Provides caching to avoid repeated database queries for the same table.
 *
 * @since 1.3.0
 */
interface TableCheckerInterface {

	/**
	 * Check if a table exists.
	 *
	 * Results are cached for the duration of the request.
	 *
	 * @since 1.3.0
	 *
	 * @param string $table_name Full table name to check.
	 * @return bool True if table exists, false otherwise.
	 */
	public function exists( string $table_name ): bool;

	/**
	 * Check if multiple tables exist.
	 *
	 * @since 1.3.0
	 *
	 * @param array $table_names Array of table names to check.
	 * @return array Associative array of table_name => exists boolean.
	 */
	public function exists_multiple( array $table_names ): array;

	/**
	 * Get the plugin's scan history table name.
	 *
	 * @since 1.3.0
	 *
	 * @return string Full table name with prefix.
	 */
	public function get_scan_history_table(): string;

	/**
	 * Get the plugin's query log table name.
	 *
	 * @since 1.3.0
	 *
	 * @return string Full table name with prefix.
	 */
	public function get_query_log_table(): string;

	/**
	 * Get the plugin's ajax log table name.
	 *
	 * @since 1.3.0
	 *
	 * @return string Full table name with prefix.
	 */
	public function get_ajax_log_table(): string;

	/**
	 * Check if the scan history table exists.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if table exists.
	 */
	public function scan_history_exists(): bool;

	/**
	 * Check if the query log table exists.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if table exists.
	 */
	public function query_log_exists(): bool;

	/**
	 * Check if the ajax log table exists.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if table exists.
	 */
	public function ajax_log_exists(): bool;

	/**
	 * Clear the existence cache.
	 *
	 * Useful after table creation or deletion.
	 *
	 * @since 1.3.0
	 *
	 * @param string|null $table_name Specific table to clear, or null for all.
	 * @return void
	 */
	public function clear_cache( ?string $table_name = null ): void;
}
