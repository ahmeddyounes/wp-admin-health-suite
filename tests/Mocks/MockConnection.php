<?php
/**
 * Mock Database Connection for Unit Testing
 *
 * Provides a testable implementation of ConnectionInterface
 * that doesn't require an actual database connection.
 *
 * @package WPAdminHealth\Tests\Mocks
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Contracts\ConnectionInterface;

/**
 * Mock database connection for testing.
 *
 * Records all queries made and allows setting up expected results.
 */
class MockConnection implements ConnectionInterface {

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $prefix = 'wp_';

	/**
	 * Recorded queries.
	 *
	 * @var array<array{query: string, time: float}>
	 */
	private array $queries = array();

	/**
	 * Expected results for queries.
	 *
	 * @var array<string, mixed>
	 */
	private array $expected_results = array();

	/**
	 * Default result to return when no match found.
	 *
	 * @var mixed
	 */
	private $default_result = null;

	/**
	 * Last insert ID.
	 *
	 * @var int
	 */
	private int $insert_id = 0;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Number of rows affected by last query.
	 *
	 * @var int
	 */
	private int $rows_affected = 1;

	/**
	 * Set the table prefix.
	 *
	 * @param string $prefix Table prefix.
	 * @return void
	 */
	public function set_prefix( string $prefix ): void {
		$this->prefix = $prefix;
	}

	/**
	 * Set expected result for a query pattern.
	 *
	 * Supports SQL LIKE-style wildcards:
	 * - Use `%%` for multi-character wildcard (matches zero or more characters)
	 * - Use `__` for single-character wildcard (matches exactly one character)
	 *
	 * @param string $pattern Query pattern.
	 * @param mixed  $result  Expected result.
	 * @return void
	 */
	public function set_expected_result( string $pattern, $result ): void {
		$this->expected_results[ $pattern ] = $result;
	}

	/**
	 * Set default result for unmatched queries.
	 *
	 * @param mixed $result Default result.
	 * @return void
	 */
	public function set_default_result( $result ): void {
		$this->default_result = $result;
	}

	/**
	 * Set last insert ID.
	 *
	 * @param int $id Insert ID.
	 * @return void
	 */
	public function set_insert_id( int $id ): void {
		$this->insert_id = $id;
	}

	/**
	 * Set last error.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	public function set_last_error( string $error ): void {
		$this->last_error = $error;
	}

	/**
	 * Set rows affected.
	 *
	 * @param int $count Number of rows affected.
	 * @return void
	 */
	public function set_rows_affected( int $count ): void {
		$this->rows_affected = $count;
	}

	/**
	 * Get all recorded queries.
	 *
	 * @return array<array{query: string, time: float}>
	 */
	public function get_queries(): array {
		return $this->queries;
	}

	/**
	 * Get the last recorded query.
	 *
	 * @return array{query: string, time: float}|null
	 */
	public function get_last_query(): ?array {
		return ! empty( $this->queries ) ? end( $this->queries ) : null;
	}

	/**
	 * Reset recorded queries.
	 *
	 * @return void
	 */
	public function reset_queries(): void {
		$this->queries = array();
	}

	/**
	 * Reset all state.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->queries          = array();
		$this->expected_results = array();
		$this->default_result   = null;
		$this->insert_id        = 0;
		$this->last_error       = '';
		$this->rows_affected    = 1;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_var( string $query, int $x = 0, int $y = 0 ) {
		$this->record_query( $query );
		return $this->find_result( $query );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_row( string $query, string $output = 'OBJECT', int $y = 0 ) {
		$this->record_query( $query );
		return $this->find_result( $query );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_col( string $query, int $x = 0 ): array {
		$this->record_query( $query );
		$result = $this->find_result( $query );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_results( string $query, string $output = 'OBJECT' ): array {
		$this->record_query( $query );
		$result = $this->find_result( $query );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function query( string $query ) {
		$this->record_query( $query );
		$result = $this->find_result( $query );
		return null !== $result ? $result : true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare( string $query, ...$args ): ?string {
		if ( empty( $args ) ) {
			return $query;
		}

		// If args is a single array, unpack it (matches WPDB_Connection behavior).
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		// Simple placeholder replacement for testing.
		$prepared = $query;

		foreach ( $args as $arg ) {
			if ( is_int( $arg ) ) {
				$prepared = preg_replace( '/%d/', (string) $arg, $prepared, 1 );
			} elseif ( is_float( $arg ) ) {
				$prepared = preg_replace( '/%f/', (string) $arg, $prepared, 1 );
			} else {
				$prepared = preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $prepared, 1 );
			}
		}

		return $prepared;
	}

	/**
	 * {@inheritdoc}
	 */
	public function insert( string $table, array $data, $format = null ) {
		$columns = implode( ', ', array_keys( $data ) );
		$values  = implode( ', ', array_map(
			array( $this, 'format_value' ),
			array_values( $data )
		) );

		$query = sprintf( 'INSERT INTO %s (%s) VALUES (%s)', $table, $columns, $values );
		$this->record_query( $query );

		// Return false if an error has been set.
		if ( '' !== $this->last_error ) {
			return false;
		}

		return $this->rows_affected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		$set_parts   = array();
		$where_parts = array();

		foreach ( $data as $key => $value ) {
			$set_parts[] = sprintf( '%s = %s', $key, $this->format_value( $value ) );
		}

		foreach ( $where as $key => $value ) {
			$where_parts[] = sprintf( '%s = %s', $key, $this->format_value( $value ) );
		}

		$query = sprintf( 'UPDATE %s SET %s WHERE %s', $table, implode( ', ', $set_parts ), implode( ' AND ', $where_parts ) );
		$this->record_query( $query );

		// Return false if an error has been set.
		if ( '' !== $this->last_error ) {
			return false;
		}

		return $this->rows_affected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $table, array $where, $where_format = null ) {
		$where_parts = array();

		foreach ( $where as $key => $value ) {
			$where_parts[] = sprintf( '%s = %s', $key, $this->format_value( $value ) );
		}

		$query = sprintf( 'DELETE FROM %s WHERE %s', $table, implode( ' AND ', $where_parts ) );
		$this->record_query( $query );

		// Return false if an error has been set.
		if ( '' !== $this->last_error ) {
			return false;
		}

		return $this->rows_affected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_prefix(): string {
		return $this->prefix;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_insert_id(): int {
		return $this->insert_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * {@inheritdoc}
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_rows_affected(): int {
		return $this->rows_affected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_posts_table(): string {
		return $this->prefix . 'posts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_postmeta_table(): string {
		return $this->prefix . 'postmeta';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_comments_table(): string {
		return $this->prefix . 'comments';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_commentmeta_table(): string {
		return $this->prefix . 'commentmeta';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_options_table(): string {
		return $this->prefix . 'options';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_terms_table(): string {
		return $this->prefix . 'terms';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_termmeta_table(): string {
		return $this->prefix . 'termmeta';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_users_table(): string {
		return $this->prefix . 'users';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_usermeta_table(): string {
		return $this->prefix . 'usermeta';
	}

	/**
	 * {@inheritdoc}
	 */
	public function table_exists( string $table_name ): bool {
		// Check if a result has been set for a SHOW TABLES or table existence query.
		$query = "SHOW TABLES LIKE '{$table_name}'";
		$this->record_query( $query );
		$result = $this->find_result( $query );

		// If a result is set, use it; otherwise default to true.
		if ( null !== $result ) {
			return (bool) $result;
		}

		return true;
	}

	/**
	 * Format a value for SQL query.
	 *
	 * @param mixed $value Value to format.
	 * @return string Formatted value.
	 */
	private function format_value( $value ): string {
		if ( null === $value ) {
			return 'NULL';
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_string( $value ) ) {
			return "'" . addslashes( $value ) . "'";
		}
		return (string) $value;
	}

	/**
	 * Record a query.
	 *
	 * @param string $query Query string.
	 * @return void
	 */
	private function record_query( string $query ): void {
		$this->queries[] = array(
			'query' => $query,
			'time'  => microtime( true ),
		);
	}

	/**
	 * Find result for a query.
	 *
	 * @param string $query Query string.
	 * @return mixed
	 */
	private function find_result( string $query ) {
		// Try exact match first.
		if ( isset( $this->expected_results[ $query ] ) ) {
			return $this->expected_results[ $query ];
		}

		// Try pattern matching with wildcards (%% and __).
		foreach ( $this->expected_results as $pattern => $result ) {
			// Skip patterns without wildcards (already handled by exact match).
			if ( strpos( $pattern, '%%' ) === false && strpos( $pattern, '__' ) === false ) {
				continue;
			}

			// Use placeholders that don't contain the wildcard characters.
			$placeholder_multi  = '<<<MULTI>>>';
			$placeholder_single = '<<<SINGLE>>>';

			// Replace wildcards with placeholders.
			$temp = str_replace( '%%', $placeholder_multi, $pattern );
			$temp = str_replace( '__', $placeholder_single, $temp );

			// Escape for regex.
			$temp = preg_quote( $temp, '/' );

			// Replace escaped placeholders with regex patterns.
			$regex = '/^' . str_replace(
				array( preg_quote( $placeholder_multi, '/' ), preg_quote( $placeholder_single, '/' ) ),
				array( '.*', '.' ),
				$temp
			) . '$/i';

			if ( preg_match( $regex, $query ) ) {
				return $result;
			}
		}

		return $this->default_result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_num_queries(): int {
		return count( $this->queries );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_query_log(): array {
		return array_map(
			function ( $query ) {
				return array(
					$query['query'],
					$query['time'],
					'MockConnection',
				);
			},
			$this->queries
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function suppress_errors( bool $suppress = true ): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function show_errors( bool $show = true ): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_database_name(): string {
		return 'test_database';
	}
}
