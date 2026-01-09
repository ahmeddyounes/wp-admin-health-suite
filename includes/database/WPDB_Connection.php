<?php
/**
 * WPDB Connection Implementation
 *
 * Database connection using WordPress WPDB.
 *
 * @package WPAdminHealth\Database
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class WPDB_Connection
 *
 * Implements ConnectionInterface using WordPress $wpdb.
 * Provides a testable abstraction over direct WPDB usage.
 *
 * @since 1.1.0
 */
class WPDB_Connection implements ConnectionInterface {

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional WPDB instance. Uses global $wpdb if not provided.
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		} else {
			$this->wpdb = $wpdb;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_var( string $query, int $x = 0, int $y = 0 ) {
		return $this->wpdb->get_var( $query, $x, $y );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_row( string $query, string $output = 'OBJECT', int $y = 0 ) {
		return $this->wpdb->get_row( $query, $output, $y );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_results( string $query, string $output = 'OBJECT' ): array {
		$results = $this->wpdb->get_results( $query, $output );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_col( string $query, int $x = 0 ): array {
		$results = $this->wpdb->get_col( $query, $x );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare( string $query, ...$args ): ?string {
		if ( empty( $args ) ) {
			return $query;
		}

		// If args is a single array, unpack it.
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		return $this->wpdb->prepare( $query, ...$args );
	}

	/**
	 * {@inheritdoc}
	 */
	public function query( string $query ) {
		return $this->wpdb->query( $query );
	}

	/**
	 * {@inheritdoc}
	 */
	public function insert( string $table, array $data, $format = null ) {
		return $this->wpdb->insert( $table, $data, $format );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		return $this->wpdb->update( $table, $data, $where, $format, $where_format );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $table, array $where, $where_format = null ) {
		return $this->wpdb->delete( $table, $where, $where_format );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_prefix(): string {
		return $this->wpdb->prefix;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_insert_id(): int {
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_last_error(): string {
		return $this->wpdb->last_error;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_rows_affected(): int {
		return (int) $this->wpdb->rows_affected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function esc_like( string $text ): string {
		return $this->wpdb->esc_like( $text );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_posts_table(): string {
		return $this->wpdb->posts;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_postmeta_table(): string {
		return $this->wpdb->postmeta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_comments_table(): string {
		return $this->wpdb->comments;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_commentmeta_table(): string {
		return $this->wpdb->commentmeta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_options_table(): string {
		return $this->wpdb->options;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_terms_table(): string {
		return $this->wpdb->terms;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_termmeta_table(): string {
		return $this->wpdb->termmeta;
	}

	/**
	 * Get the users table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Users table name.
	 */
	public function get_users_table(): string {
		return $this->wpdb->users;
	}

	/**
	 * Get the usermeta table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Usermeta table name.
	 */
	public function get_usermeta_table(): string {
		return $this->wpdb->usermeta;
	}

	/**
	 * Get the term_relationships table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Term relationships table name.
	 */
	public function get_term_relationships_table(): string {
		return $this->wpdb->term_relationships;
	}

	/**
	 * Get the term_taxonomy table name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Term taxonomy table name.
	 */
	public function get_term_taxonomy_table(): string {
		return $this->wpdb->term_taxonomy;
	}

	/**
	 * Get the database name.
	 *
	 * @since 1.1.0
	 *
	 * @return string Database name.
	 */
	public function get_database_name(): string {
		return defined( 'DB_NAME' ) ? DB_NAME : '';
	}

	/**
	 * Get the database charset.
	 *
	 * @since 1.1.0
	 *
	 * @return string Database charset.
	 */
	public function get_charset(): string {
		return $this->wpdb->charset;
	}

	/**
	 * Get the database collate.
	 *
	 * @since 1.1.0
	 *
	 * @return string Database collate.
	 */
	public function get_collate(): string {
		return $this->wpdb->collate;
	}

	/**
	 * Get all tables in the database.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> List of table names.
	 */
	public function get_tables(): array {
		return $this->wpdb->tables();
	}

	/**
	 * Check if a table exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table Table name.
	 * @return bool True if table exists.
	 */
	public function table_exists( string $table ): bool {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		return $result === $table;
	}

	/**
	 * Get the number of queries executed.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of queries.
	 */
	public function get_num_queries(): int {
		return (int) $this->wpdb->num_queries;
	}

	/**
	 * Get the last query executed.
	 *
	 * @since 1.1.0
	 *
	 * @return string Last query.
	 */
	public function get_last_query(): string {
		return $this->wpdb->last_query;
	}

	/**
	 * Get the query log if SAVEQUERIES is enabled.
	 *
	 * @since 1.1.0
	 *
	 * @return array<array{0: string, 1: float, 2: string}> Query log.
	 */
	public function get_query_log(): array {
		return is_array( $this->wpdb->queries ) ? $this->wpdb->queries : array();
	}

	/**
	 * Suppress errors.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $suppress Whether to suppress errors.
	 * @return bool Previous value.
	 */
	public function suppress_errors( bool $suppress = true ): bool {
		return $this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * Show or hide database errors.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $show Whether to show errors.
	 * @return bool Previous value.
	 */
	public function show_errors( bool $show = true ): bool {
		if ( $show ) {
			return $this->wpdb->show_errors();
		}
		return $this->wpdb->hide_errors();
	}
}
