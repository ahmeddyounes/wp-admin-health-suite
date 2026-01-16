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
 * Handles OPTIMIZE TABLE and REPAIR TABLE operations for MySQL/MariaDB.
 *
 * @since 1.2.0
 */
interface OptimizerInterface {

	/**
	 * Get tables that need optimization.
	 *
	 * Returns tables with significant overhead (fragmented space).
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{name: string, overhead: int, data_size: int}> Array of tables needing optimization.
	 */
	public function get_tables_needing_optimization(): array;

	/**
	 * Get overhead size for a specific table.
	 *
	 * @since 1.2.0
	 *
	 * @param string $table_name Table name.
	 * @return int|false Overhead size in bytes, or false if table not found.
	 */
	public function get_table_overhead( string $table_name );

	/**
	 * Optimize a specific table.
	 *
	 * @since 1.2.0
	 *
	 * @param string $table_name Table name to optimize.
	 * @return array{success: bool, message: string, overhead_reclaimed: int}|false Optimization results, or false on failure.
	 */
	public function optimize_table( string $table_name );

	/**
	 * Optimize all tables that need optimization.
	 *
	 * @since 1.2.0
	 *
	 * @return array{optimized: int, failed: int, overhead_reclaimed: int, results: array} Results for each table.
	 */
	public function optimize_all_tables(): array;

	/**
	 * Repair a specific table.
	 *
	 * @since 1.2.0
	 *
	 * @param string $table_name Table name to repair.
	 * @return array{success: bool, message: string} Repair result.
	 */
	public function repair_table( string $table_name ): array;
}
