<?php
/**
 * Database Optimizer Class
 *
 * Optimizes and repairs WordPress database tables.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database Optimizer class for optimizing and repairing database tables.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements OptimizerInterface.
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 */
class Optimizer implements OptimizerInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Get tables that need optimization based on overhead.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of table names with overhead.
	 */
	public function get_tables_needing_optimization(): array {
		$prefix = $this->connection->get_prefix();
		$query  = $this->connection->prepare(
			"SELECT table_name as 'table',
			data_free as overhead,
			engine
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name LIKE %s
			AND data_free > 0
			ORDER BY data_free DESC",
			DB_NAME,
			$this->connection->esc_like( $prefix ) . '%'
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query );

		$tables = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$tables[] = array(
					'name'     => $row->table,
					'overhead' => absint( $row->overhead ),
					'engine'   => $row->engine,
				);
			}
		}

		return $tables;
	}

	/**
	 * Get the overhead (wasted space) for a specific table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name The name of the table.
	 * @return int|false Overhead in bytes, or false if table not found.
	 */
	public function get_table_overhead( string $table_name ) {
		// Ensure table belongs to WordPress.
		if ( ! $this->is_wordpress_table( $table_name ) ) {
			return false;
		}

		$query = $this->connection->prepare(
			'SELECT data_free as overhead
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name = %s',
			DB_NAME,
			$table_name
		);

		if ( null === $query ) {
			return false;
		}

		$result = $this->connection->get_var( $query );

		return null !== $result ? absint( $result ) : false;
	}

	/**
	 * Optimize a specific table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name The name of the table to optimize.
	 * @return array|false Array with optimization results, or false on failure.
	 */
	public function optimize_table( string $table_name ) {
		// Ensure table belongs to WordPress.
		if ( ! $this->is_wordpress_table( $table_name ) ) {
			return false;
		}

		// Get table info including engine type.
		$table_info = $this->get_table_info( $table_name );
		if ( ! $table_info ) {
			return false;
		}

		// Get size before optimization.
		$size_before = $table_info['size'];
		$engine      = $table_info['engine'];

		// Determine optimization command based on engine.
		$command = $this->get_optimization_command( $engine );

		// Execute optimization.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql used.
		$query  = "$command TABLE `" . esc_sql( $table_name ) . '`';
		$result = $this->connection->query( $query );

		if ( false === $result ) {
			return false;
		}

		// Get size after optimization.
		$table_info_after = $this->get_table_info( $table_name );
		$size_after       = $table_info_after ? $table_info_after['size'] : $size_before;

		return array(
			'table'        => $table_name,
			'engine'       => $engine,
			'size_before'  => $size_before,
			'size_after'   => $size_after,
			'size_reduced' => $size_before - $size_after,
			'command'      => $command,
		);
	}

	/**
	 * Optimize all WordPress tables.
	 *
	 * Uses batch queries to avoid N+1 query pattern.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Optimized to use batch loading instead of per-table queries.
	 *
	 * @return array Array of optimization results for each table.
	 */
	public function optimize_all_tables(): array {
		$tables = $this->get_wordpress_tables();
		if ( empty( $tables ) ) {
			return array();
		}

		// Batch load all table info before optimization to avoid N+1 queries.
		$tables_info_before = $this->get_tables_info( $tables );

		$results = array();

		// Execute optimization for each table.
		foreach ( $tables as $table ) {
			if ( ! isset( $tables_info_before[ $table ] ) ) {
				continue;
			}

			$table_info = $tables_info_before[ $table ];
			$size_before = $table_info['size'];
			$engine      = $table_info['engine'];

			// Determine optimization command based on engine.
			$command = $this->get_optimization_command( $engine );

			// Execute optimization.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql used.
			$query  = "$command TABLE `" . esc_sql( $table ) . '`';
			$result = $this->connection->query( $query );

			// Track that optimization was attempted.
			$results[ $table ] = array(
				'table'       => $table,
				'engine'      => $engine,
				'size_before' => $size_before,
				'command'     => $command,
				'optimized'   => false !== $result,
			);
		}

		// Batch load all table info after optimization to avoid N+1 queries.
		$tables_info_after = $this->get_tables_info( $tables );

		// Merge after-sizes into results.
		foreach ( $results as $table => &$result ) {
			if ( isset( $tables_info_after[ $table ] ) ) {
				$result['size_after']   = $tables_info_after[ $table ]['size'];
				$result['size_reduced'] = $result['size_before'] - $result['size_after'];
			} else {
				$result['size_after']   = $result['size_before'];
				$result['size_reduced'] = 0;
			}
		}
		unset( $result );

		return array_values( $results );
	}

	/**
	 * Repair a specific table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name The name of the table to repair.
	 * @return array Array with success status and message.
	 */
	public function repair_table( string $table_name ): array {
		// Ensure table belongs to WordPress.
		if ( ! $this->is_wordpress_table( $table_name ) ) {
			return array(
				'success' => false,
				'message' => 'Table does not belong to WordPress installation.',
			);
		}

		// Execute repair.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql used.
		$query  = 'REPAIR TABLE `' . esc_sql( $table_name ) . '`';
		$result = $this->connection->query( $query );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => 'Repair operation failed.',
			);
		}

		return array(
			'success' => true,
			'table'   => $table_name,
			'message' => 'Table repaired successfully.',
		);
	}

	/**
	 * Get information about multiple tables in a single query.
	 *
	 * Batch loading method to avoid N+1 query pattern.
	 *
	 * @since 1.3.0
	 *
	 * @param array $tables Array of table names.
	 * @return array Array of table_name => info pairs.
	 */
	private function get_tables_info( array $tables ): array {
		if ( empty( $tables ) ) {
			return array();
		}

		// Validate all table names to prevent SQL injection.
		$tables = $this->filter_valid_table_names( $tables );
		if ( empty( $tables ) ) {
			return array();
		}

		// Build placeholders for IN clause.
		$placeholders = implode( ',', array_fill( 0, count( $tables ), '%s' ) );

		$query = $this->connection->prepare(
			"SELECT
			table_name as 'table',
			engine,
			(data_length + index_length) as size,
			data_free as overhead
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name IN ({$placeholders})",
			DB_NAME,
			...$tables
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query );

		$info = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$info[ $row->table ] = array(
					'table'    => $row->table,
					'engine'   => $row->engine,
					'size'     => absint( $row->size ),
					'overhead' => absint( $row->overhead ),
				);
			}
		}

		return $info;
	}

	/**
	 * Get information about a table.
	 *
	 * @param string $table_name The name of the table.
	 * @return array|false Array with table info, or false if not found.
	 */
	private function get_table_info( $table_name ) {
		$query = $this->connection->prepare(
			"SELECT
			table_name as 'table',
			engine,
			(data_length + index_length) as size,
			data_free as overhead
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name = %s",
			DB_NAME,
			$table_name
		);

		if ( null === $query ) {
			return false;
		}

		$result = $this->connection->get_row( $query, 'ARRAY_A' );

		if ( ! $result ) {
			return false;
		}

		return array(
			'table'    => $result['table'],
			'engine'   => $result['engine'],
			'size'     => absint( $result['size'] ),
			'overhead' => absint( $result['overhead'] ),
		);
	}

	/**
	 * Get the appropriate optimization command for the table engine.
	 *
	 * @param string $engine The database engine (e.g., InnoDB, MyISAM, Aria).
	 * @return string The optimization command to use.
	 */
	private function get_optimization_command( $engine ) {
		$engine = strtoupper( $engine );

		// InnoDB uses ANALYZE TABLE for optimization.
		if ( 'INNODB' === $engine ) {
			return 'ANALYZE';
		}

		// MyISAM and Aria use OPTIMIZE TABLE.
		return 'OPTIMIZE';
	}

	/**
	 * Filter array to only include valid table names.
	 *
	 * Validates table name format to prevent SQL injection via maliciously crafted table names.
	 * Only allows alphanumeric characters and underscores.
	 *
	 * @since 1.3.0
	 *
	 * @param array $tables Array of table names.
	 * @return array Array of valid table names.
	 */
	private function filter_valid_table_names( array $tables ): array {
		return array_filter( $tables, array( $this, 'is_valid_table_name' ) );
	}

	/**
	 * Check if a table name has a valid format.
	 *
	 * Only allows alphanumeric characters and underscores to prevent SQL injection.
	 *
	 * @since 1.3.0
	 *
	 * @param string $table_name The table name to validate.
	 * @return bool True if table name is valid, false otherwise.
	 */
	private function is_valid_table_name( string $table_name ): bool {
		return 1 === preg_match( '/^[a-zA-Z0-9_]+$/', $table_name );
	}

	/**
	 * Check if a table belongs to the WordPress installation.
	 *
	 * @param string $table_name The name of the table.
	 * @return bool True if table belongs to WordPress, false otherwise.
	 */
	private function is_wordpress_table( $table_name ) {
		// Validate table name format (only alphanumeric and underscores allowed).
		// This prevents SQL injection via maliciously crafted table names.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return false;
		}

		// Check if table starts with WordPress prefix.
		return 0 === strpos( $table_name, $this->connection->get_prefix() );
	}

	/**
	 * Get all WordPress tables.
	 *
	 * @return array Array of WordPress table names.
	 */
	private function get_wordpress_tables() {
		$prefix = $this->connection->get_prefix();
		$query  = $this->connection->prepare(
			'SELECT table_name
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name LIKE %s
			ORDER BY table_name',
			DB_NAME,
			$this->connection->esc_like( $prefix ) . '%'
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_col( $query );

		return $results ? $results : array();
	}
}
