<?php
/**
 * Optimizer Interface
 *
 * Defines the contract for database optimization operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface OptimizerInterface
 *
 * Contract for database table optimization operations.
 *
 * @since 1.2.0
 */
interface OptimizerInterface {

	/**
	 * Get tables that need optimization.
	 *
	 * @return array Array of table names needing optimization.
	 */
	public function get_tables_needing_optimization(): array;

	/**
	 * Get overhead size for a specific table.
	 *
	 * @param string $table_name Table name.
	 * @return int|false Overhead size in bytes, or false if table not found.
	 */
	public function get_table_overhead( string $table_name );

	/**
	 * Optimize a specific table.
	 *
	 * @param string $table_name Table name to optimize.
	 * @return array|false Array with optimization results, or false on failure.
	 */
	public function optimize_table( string $table_name );

	/**
	 * Optimize all tables that need optimization.
	 *
	 * @return array Array with results for each table.
	 */
	public function optimize_all_tables(): array;

	/**
	 * Repair a specific table.
	 *
	 * @param string $table_name Table name to repair.
	 * @return array Array with success status and message.
	 */
	public function repair_table( string $table_name ): array;
}
