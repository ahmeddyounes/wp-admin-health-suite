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
 * @since 1.4.0 Added progress callback support and large table handling.
 */
class Optimizer implements OptimizerInterface {

	/**
	 * Size threshold (in bytes) above which a table is considered large.
	 * Large tables (>100MB) may take longer to optimize and cause locking.
	 *
	 * @since 1.4.0
	 * @var int
	 */
	public const LARGE_TABLE_THRESHOLD = 104857600; // 100MB

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Progress callback for reporting optimization progress.
	 *
	 * @since 1.4.0
	 * @var callable|null
	 */
	private $progress_callback = null;

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
	 * Set a callback function to report progress during optimization.
	 *
	 * The callback receives three parameters:
	 * - int    $current   Current table index (1-based).
	 * - int    $total     Total number of tables.
	 * - string $table     Current table name being processed.
	 *
	 * @since 1.4.0
	 *
	 * @param callable|null $callback Progress callback function.
	 * @return void
	 */
	public function set_progress_callback( ?callable $callback ): void {
		$this->progress_callback = $callback;
	}

	/**
	 * Get tables that need optimization based on overhead.
	 *
	 * Returns tables with overhead (fragmented space) that can be reclaimed.
	 * Each table includes a 'is_large' flag indicating if optimization may
	 * take longer due to table size exceeding LARGE_TABLE_THRESHOLD.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added data_size and is_large fields.
	 *
	 * @return array Array of table names with overhead.
	 */
	public function get_tables_needing_optimization(): array {
		$prefix = $this->connection->get_prefix();
		$query  = $this->connection->prepare(
			"SELECT table_name as 'table',
			data_free as overhead,
			(data_length + index_length) as data_size,
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
				$data_size = absint( $row->data_size );
				$tables[]  = array(
					'name'      => $row->table,
					'overhead'  => absint( $row->overhead ),
					'data_size' => $data_size,
					'engine'    => $row->engine,
					'is_large'  => $data_size > self::LARGE_TABLE_THRESHOLD,
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
	 * Note: OPTIMIZE TABLE acquires a table lock during execution.
	 * For MyISAM tables, this is a full lock (reads and writes blocked).
	 * For InnoDB tables, this performs an online rebuild with minimal blocking,
	 * but may still impact performance on very large tables.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added is_large flag and locking documentation.
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
		$is_large    = $size_before > self::LARGE_TABLE_THRESHOLD;

		// Execute optimization using OPTIMIZE TABLE for all engines.
		// OPTIMIZE TABLE works for both InnoDB and MyISAM:
		// - InnoDB: Performs an online table rebuild to reclaim fragmented space.
		// - MyISAM/Aria: Defragments data file and sorts indexes.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql used.
		$query  = 'OPTIMIZE TABLE `' . esc_sql( $table_name ) . '`';
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
			'is_large'     => $is_large,
		);
	}

	/**
	 * Optimize all WordPress tables.
	 *
	 * Uses batch queries to avoid N+1 query pattern. If a progress callback
	 * has been set via set_progress_callback(), it will be called before
	 * each table is optimized.
	 *
	 * Note: This operation may take significant time for large databases.
	 * Consider running during low-traffic periods as OPTIMIZE TABLE
	 * acquires locks during execution.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Optimized to use batch loading instead of per-table queries.
	 * @since 1.4.0 Added progress callback support and is_large flag.
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

		$results     = array();
		$total       = count( $tables );
		$current     = 0;

		// Execute optimization for each table.
		foreach ( $tables as $table ) {
			++$current;

			if ( ! isset( $tables_info_before[ $table ] ) ) {
				continue;
			}

			// Report progress if callback is set.
			if ( null !== $this->progress_callback ) {
				call_user_func( $this->progress_callback, $current, $total, $table );
			}

			$table_info  = $tables_info_before[ $table ];
			$size_before = $table_info['size'];
			$engine      = $table_info['engine'];
			$is_large    = $size_before > self::LARGE_TABLE_THRESHOLD;

			// Execute optimization using OPTIMIZE TABLE for all engines.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql used.
			$query  = 'OPTIMIZE TABLE `' . esc_sql( $table ) . '`';
			$result = $this->connection->query( $query );

			// Track that optimization was attempted.
			$results[ $table ] = array(
				'table'       => $table,
				'engine'      => $engine,
				'size_before' => $size_before,
				'is_large'    => $is_large,
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
	 * Note: REPAIR TABLE is only supported for MyISAM, Aria, ARCHIVE, and CSV
	 * storage engines. InnoDB tables do not support REPAIR TABLE. For InnoDB
	 * tables, use optimize_table() instead, which performs a table rebuild.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added engine compatibility check for InnoDB.
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

		// Get table info to check engine type.
		$table_info = $this->get_table_info( $table_name );
		if ( ! $table_info ) {
			return array(
				'success' => false,
				'message' => 'Could not retrieve table information.',
			);
		}

		// Check if engine supports REPAIR TABLE.
		// InnoDB does not support REPAIR TABLE - it has built-in crash recovery.
		$engine            = strtoupper( $table_info['engine'] );
		$supported_engines = array( 'MYISAM', 'ARIA', 'ARCHIVE', 'CSV' );

		if ( ! in_array( $engine, $supported_engines, true ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					'REPAIR TABLE is not supported for %s engine. Use optimize_table() for InnoDB tables.',
					$engine
				),
				'engine'  => $engine,
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
			'engine'  => $engine,
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
