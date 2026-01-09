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
 *
 * @since 1.2.0
 */
interface QueryMonitorInterface {

	/**
	 * Capture slow queries based on a threshold.
	 *
	 * @param float $threshold_ms Threshold in milliseconds.
	 * @return array Array of slow queries.
	 */
	public function capture_slow_queries( float $threshold_ms ): array;

	/**
	 * Get query summary statistics.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Summary statistics.
	 */
	public function get_query_summary( int $days = 7 ): array;

	/**
	 * Get queries grouped by caller.
	 *
	 * @param int $limit Number of results to return.
	 * @param int $days  Number of days to look back.
	 * @return array Queries grouped by caller.
	 */
	public function get_queries_by_caller( int $limit = 20, int $days = 7 ): array;

	/**
	 * Export query log to CSV or JSON format.
	 *
	 * @param int    $days   Number of days to export.
	 * @param string $format Export format: 'csv' or 'json'.
	 * @return string|array Exported data.
	 */
	public function export_query_log( int $days = 7, string $format = 'csv' );

	/**
	 * Prune old query logs based on TTL.
	 *
	 * @return int Number of rows deleted.
	 */
	public function prune_old_logs(): int;

	/**
	 * Check if Query Monitor plugin is active.
	 *
	 * @return bool True if Query Monitor is active.
	 */
	public function is_query_monitor_active(): bool;

	/**
	 * Get current monitoring status.
	 *
	 * @return array Status information.
	 */
	public function get_monitoring_status(): array;
}
