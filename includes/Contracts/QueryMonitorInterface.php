<?php
/**
 * Query Monitor Interface
 *
 * Defines the contract for monitoring and analyzing database queries.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface QueryMonitorInterface
 *
 * Contract for query monitoring operations.
 * Provides methods to capture, analyze, and export database query data.
 *
 * @since 1.2.0
 */
interface QueryMonitorInterface {

	/**
	 * Capture slow queries based on a threshold.
	 *
	 * @since 1.2.0
	 *
	 * @param float $threshold_ms Threshold in milliseconds.
	 * @return array<array{query: string, time: float, caller: string}> Array of slow queries.
	 */
	public function capture_slow_queries( float $threshold_ms ): array;

	/**
	 * Get query summary statistics.
	 *
	 * @since 1.2.0
	 *
	 * @param int $days Number of days to look back.
	 * @return array{total_queries: int, slow_queries: int, avg_time: float, total_time: float} Summary statistics.
	 */
	public function get_query_summary( int $days = 7 ): array;

	/**
	 * Get queries grouped by caller.
	 *
	 * @since 1.2.0
	 *
	 * @param int $limit Number of results to return.
	 * @param int $days  Number of days to look back.
	 * @return array<array{caller: string, count: int, avg_time: float, total_time: float}> Queries grouped by caller.
	 */
	public function get_queries_by_caller( int $limit = 20, int $days = 7 ): array;

	/**
	 * Export query log to CSV or JSON format.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $days   Number of days to export.
	 * @param string $format Export format: 'csv' or 'json'.
	 * @return string|array<array{query: string, time: float, caller: string, timestamp: string}> Exported data.
	 */
	public function export_query_log( int $days = 7, string $format = 'csv' );

	/**
	 * Prune old query logs based on TTL.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_old_logs(): int;

	/**
	 * Check if Query Monitor plugin is active.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True if Query Monitor is active.
	 */
	public function is_query_monitor_active(): bool;

	/**
	 * Get current monitoring status.
	 *
	 * @since 1.2.0
	 *
	 * @return array{enabled: bool, threshold_ms: float, log_count: int, table_exists: bool} Status information.
	 */
	public function get_monitoring_status(): array;
}
