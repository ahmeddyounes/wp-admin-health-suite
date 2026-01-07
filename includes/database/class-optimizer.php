<?php
/**
 * Database Optimizer Class
 *
 * Optimizes and repairs WordPress database tables.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database Optimizer class for optimizing and repairing database tables.
 *
 * @since 1.0.0
 */
class Optimizer {

	/**
	 * Get tables that need optimization based on overhead.
	 *
 * @since 1.0.0
 *
	 * @return array Array of table names with overhead.
	 */
	public function get_tables_needing_optimization() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT table_name as 'table',
			data_free as overhead,
			engine
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name LIKE %s
			AND data_free > 0
			ORDER BY data_free DESC",
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		);

		$results = $wpdb->get_results( $query );

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
	public function get_table_overhead( $table_name ) {
		global $wpdb;

		// Ensure table belongs to WordPress.
		if ( ! $this->is_wordpress_table( $table_name ) ) {
			return false;
		}

		$query = $wpdb->prepare(
			"SELECT data_free as overhead
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name = %s",
			DB_NAME,
			$table_name
		);

		$result = $wpdb->get_var( $query );

		return $result !== null ? absint( $result ) : false;
	}

	/**
	 * Optimize a specific table.
	 *
 * @since 1.0.0
 *
	 * @param string $table_name The name of the table to optimize.
	 * @return array|false Array with optimization results, or false on failure.
	 */
	public function optimize_table( $table_name ) {
		global $wpdb;

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
		$query  = "$command TABLE `$table_name`";
		$result = $wpdb->query( $query );

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
 * @since 1.0.0
 *
	 * @return array Array of optimization results for each table.
	 */
	public function optimize_all_tables() {
		$tables = $this->get_wordpress_tables();
		$results = array();

		foreach ( $tables as $table ) {
			$result = $this->optimize_table( $table );
			if ( $result ) {
				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * Repair a specific table.
	 *
 * @since 1.0.0
 *
	 * @param string $table_name The name of the table to repair.
	 * @return bool|array True on success, array with error details on failure.
	 */
	public function repair_table( $table_name ) {
		global $wpdb;

		// Ensure table belongs to WordPress.
		if ( ! $this->is_wordpress_table( $table_name ) ) {
			return array(
				'success' => false,
				'message' => 'Table does not belong to WordPress installation.',
			);
		}

		// Execute repair.
		$query  = "REPAIR TABLE `$table_name`";
		$result = $wpdb->query( $query );

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
	 * Get information about a table.
	 *
	 * @param string $table_name The name of the table.
	 * @return array|false Array with table info, or false if not found.
	 */
	private function get_table_info( $table_name ) {
		global $wpdb;

		$query = $wpdb->prepare(
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

		$result = $wpdb->get_row( $query, ARRAY_A );

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
	 * Check if a table belongs to the WordPress installation.
	 *
	 * @param string $table_name The name of the table.
	 * @return bool True if table belongs to WordPress, false otherwise.
	 */
	private function is_wordpress_table( $table_name ) {
		global $wpdb;

		// Check if table starts with WordPress prefix.
		return 0 === strpos( $table_name, $wpdb->prefix );
	}

	/**
	 * Get all WordPress tables.
	 *
	 * @return array Array of WordPress table names.
	 */
	private function get_wordpress_tables() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT table_name
			FROM information_schema.TABLES
			WHERE table_schema = %s
			AND table_name LIKE %s
			ORDER BY table_name",
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		);

		$results = $wpdb->get_col( $query );

		return $results ? $results : array();
	}
}
