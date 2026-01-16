<?php
/**
 * Connection Interface
 *
 * Contract for database connection operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ConnectionInterface
 *
 * Defines the contract for database operations. Abstracts WPDB to enable
 * testing with mock implementations.
 *
 * @since 1.1.0
 */
interface ConnectionInterface {

	/**
	 * Get a single variable from the database.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $query  SQL query.
	 * @param int      $x      Column index (0-indexed).
	 * @param int      $y      Row index (0-indexed).
	 * @return mixed|null Database query result or null.
	 */
	public function get_var( string $query, int $x = 0, int $y = 0 );

	/**
	 * Get a single row from the database.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query  SQL query.
	 * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N.
	 * @param int    $y      Row index (0-indexed).
	 * @return object|array|null Database row or null.
	 */
	public function get_row( string $query, string $output = 'OBJECT', int $y = 0 );

	/**
	 * Get multiple rows from the database.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query  SQL query.
	 * @param string $output Output type: OBJECT, ARRAY_A, or ARRAY_N.
	 * @return array Array of database rows.
	 */
	public function get_results( string $query, string $output = 'OBJECT' ): array;

	/**
	 * Get a single column from the database.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query SQL query.
	 * @param int    $x     Column index (0-indexed).
	 * @return array Array of column values.
	 */
	public function get_col( string $query, int $x = 0 ): array;

	/**
	 * Prepare a SQL query for safe execution.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Values to substitute into placeholders.
	 * @return string|null Prepared query or null on error.
	 */
	public function prepare( string $query, ...$args ): ?string;

	/**
	 * Execute a database query.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query SQL query.
	 * @return int|bool Number of affected rows or false on error.
	 */
	public function query( string $query );

	/**
	 * Insert a row into a table.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $table  Table name.
	 * @param array        $data   Data to insert (column => value pairs).
	 * @param array|string $format Data format (%s, %d, %f).
	 * @return int|false Number of rows inserted or false on error.
	 */
	public function insert( string $table, array $data, $format = null );

	/**
	 * Update rows in a table.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $table        Table name.
	 * @param array        $data         Data to update (column => value pairs).
	 * @param array        $where        WHERE conditions (column => value pairs).
	 * @param array|string $format       Data format.
	 * @param array|string $where_format WHERE format.
	 * @return int|false Number of rows updated or false on error.
	 */
	public function update( string $table, array $data, array $where, $format = null, $where_format = null );

	/**
	 * Delete rows from a table.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $table        Table name.
	 * @param array        $where        WHERE conditions (column => value pairs).
	 * @param array|string $where_format WHERE format.
	 * @return int|false Number of rows deleted or false on error.
	 */
	public function delete( string $table, array $where, $where_format = null );

	/**
	 * Get the database table prefix.
	 *
	 * @since 1.1.0
	 *
	 * @return string Table prefix.
	 */
	public function get_prefix(): string;

	/**
	 * Get the last inserted ID.
	 *
	 * @since 1.1.0
	 *
	 * @return int Last insert ID.
	 */
	public function get_insert_id(): int;

	/**
	 * Get the last database error.
	 *
	 * @since 1.1.0
	 *
	 * @return string Last error message.
	 */
	public function get_last_error(): string;

	/**
	 * Get the number of rows affected by the last query.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of affected rows.
	 */
	public function get_rows_affected(): int;

	/**
	 * Escape a string for use in a LIKE query.
	 *
	 * @since 1.1.0
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	public function esc_like( string $text ): string;

	/**
	 * Get the posts table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Posts table name.
	 */
	public function get_posts_table(): string;

	/**
	 * Get the postmeta table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Postmeta table name.
	 */
	public function get_postmeta_table(): string;

	/**
	 * Get the comments table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Comments table name.
	 */
	public function get_comments_table(): string;

	/**
	 * Get the commentmeta table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Commentmeta table name.
	 */
	public function get_commentmeta_table(): string;

	/**
	 * Get the options table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Options table name.
	 */
	public function get_options_table(): string;

	/**
	 * Get the terms table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Terms table name.
	 */
	public function get_terms_table(): string;

	/**
	 * Get the termmeta table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Termmeta table name.
	 */
	public function get_termmeta_table(): string;

	/**
	 * Get the users table name.
	 *
	 * Note: In multisite, this is a global table shared across all sites.
	 *
	 * @since 1.4.0
	 *
	 * @return string Users table name.
	 */
	public function get_users_table(): string;

	/**
	 * Get the usermeta table name.
	 *
	 * Note: In multisite, this is a global table shared across all sites.
	 *
	 * @since 1.4.0
	 *
	 * @return string Usermeta table name.
	 */
	public function get_usermeta_table(): string;

	/**
	 * Check if a table exists in the database.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table_name Full table name to check.
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists( string $table_name ): bool;

	/**
	 * Get the number of queries executed.
	 *
	 * @since 1.3.0
	 *
	 * @return int Number of queries.
	 */
	public function get_num_queries(): int;

	/**
	 * Get the query log if SAVEQUERIES is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @return array<array{0: string, 1: float, 2: string}> Query log.
	 */
	public function get_query_log(): array;

	/**
	 * Suppress errors.
	 *
	 * @since 1.3.0
	 *
	 * @param bool $suppress Whether to suppress errors.
	 * @return bool Previous value.
	 */
	public function suppress_errors( bool $suppress = true ): bool;

	/**
	 * Show or hide database errors.
	 *
	 * @since 1.3.0
	 *
	 * @param bool $show Whether to show errors.
	 * @return bool Previous value.
	 */
	public function show_errors( bool $show = true ): bool;

	/**
	 * Get the database character set and collation.
	 *
	 * Used for table creation to ensure proper character encoding.
	 *
	 * @since 1.3.0
	 *
	 * @return string Character set and collation clause (e.g., "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci").
	 */
	public function get_charset_collate(): string;
}
