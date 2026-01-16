<?php
/**
 * Query Monitor Class
 *
 * Provides query monitoring and analysis functionality.
 * Integrates with Query Monitor plugin when available, otherwise provides
 * standalone monitoring using WordPress SAVEQUERIES.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Query Monitor class for tracking and analyzing database queries.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements QueryMonitorInterface.
 */
class QueryMonitor implements QueryMonitorInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Table name for query logs.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Default slow query threshold in milliseconds.
	 *
	 * @var float
	 */
	const DEFAULT_THRESHOLD = 50.0;

	/**
	 * TTL for query logs in seconds (7 days).
	 *
	 * @var int
	 */
	const LOG_TTL = 604800;

	/**
	 * Maximum number of rows to keep in the log table.
	 *
	 * @var int
	 */
	const MAX_LOG_ROWS = 10000;

	/**
	 * Transient key for tracking last prune time.
	 *
	 * @var string
	 */
	const PRUNE_TRANSIENT = 'wpha_query_log_last_prune';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Added ConnectionInterface parameter.
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
		$this->table_name = $this->connection->get_prefix() . 'wpha_query_log';
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for query monitoring.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Only hook if SAVEQUERIES is enabled or Query Monitor is active.
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			add_action( 'shutdown', array( $this, 'analyze_queries_on_shutdown' ), 999 );
		}
	}

	/**
	 * Analyze queries on shutdown.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function analyze_queries_on_shutdown() {
		// Capture slow queries with default threshold.
		$this->capture_slow_queries( self::DEFAULT_THRESHOLD );
	}

	/**
	 * Capture slow queries based on a threshold.
	 *
 * @since 1.0.0
 *
	 * @param float $threshold_ms Threshold in milliseconds (default: 50ms).
	 * @return array Array of slow queries.
	 */
	public function capture_slow_queries( float $threshold_ms = self::DEFAULT_THRESHOLD ): array {
		$slow_queries = array();
		$query_log    = $this->connection->get_query_log();

		// Check if Query Monitor plugin is active.
		if ( class_exists( 'QM_Collectors' ) && function_exists( 'QM_Collectors' ) ) {
			$slow_queries = $this->capture_from_query_monitor( $threshold_ms );
		} elseif ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $query_log ) ) {
			$slow_queries = $this->capture_from_savequeries( $threshold_ms );
		}

		// Log slow queries to database.
		if ( ! empty( $slow_queries ) ) {
			$this->log_queries( $slow_queries );
		}

		return $slow_queries;
	}

	/**
	 * Capture slow queries from Query Monitor plugin.
	 *
	 * @param float $threshold_ms Threshold in milliseconds.
	 * @return array Array of slow queries.
	 */
	private function capture_from_query_monitor( $threshold_ms ) {
		$slow_queries = array();

		if ( ! function_exists( 'QM_Collectors' ) ) {
			return $slow_queries;
		}

		$collectors = \QM_Collectors::init();
		if ( ! isset( $collectors['db_queries'] ) ) {
			return $slow_queries;
		}

		$db_collector = $collectors['db_queries'];
		$data = $db_collector->get_data();

		if ( empty( $data->dbs ) ) {
			return $slow_queries;
		}

		foreach ( $data->dbs as $db_name => $db_queries ) {
			if ( empty( $db_queries ) ) {
				continue;
			}

			foreach ( $db_queries as $query ) {
				$query_time_ms = $query['ltime'] * 1000;

				if ( $query_time_ms >= $threshold_ms ) {
					$slow_queries[] = array(
						'sql'           => $query['sql'],
						'time'          => $query_time_ms,
						'caller'        => isset( $query['trace'] ) ? $this->extract_caller( $query['trace'] ) : 'unknown',
						'component'     => isset( $query['component'] ) ? $query['component']->name : 'unknown',
						'is_duplicate'  => false, // Query Monitor tracks this separately.
						'needs_index'   => false, // Will be checked separately.
						'timestamp'     => current_time( 'mysql' ),
					);
				}
			}
		}

		return $slow_queries;
	}

	/**
	 * Capture slow queries from WordPress SAVEQUERIES.
	 *
	 * @param float $threshold_ms Threshold in milliseconds.
	 * @return array Array of slow queries.
	 */
	private function capture_from_savequeries( $threshold_ms ) {
		$slow_queries = array();
		$query_hashes = array();
		$query_log    = $this->connection->get_query_log();

		if ( empty( $query_log ) ) {
			return $slow_queries;
		}

		foreach ( $query_log as $query_data ) {
			if ( ! is_array( $query_data ) || count( $query_data ) < 3 ) {
				continue;
			}

			list( $sql, $time, $caller ) = $query_data;

			// Convert time to milliseconds.
			$time_ms = $time * 1000;

			if ( $time_ms >= $threshold_ms ) {
				// Check for duplicates.
				$query_hash = md5( $sql );
				$is_duplicate = isset( $query_hashes[ $query_hash ] );
				$query_hashes[ $query_hash ] = true;

				// Check if query might need an index.
				$needs_index = $this->check_needs_index( $sql );

				$slow_queries[] = array(
					'sql'          => $sql,
					'time'         => $time_ms,
					'caller'       => $caller,
					'component'    => $this->identify_component( $caller ),
					'is_duplicate' => $is_duplicate,
					'needs_index'  => $needs_index,
					'timestamp'    => current_time( 'mysql' ),
				);
			}
		}

		return $slow_queries;
	}

	/**
	 * Extract caller from Query Monitor trace.
	 *
	 * @param array $trace Stack trace from Query Monitor.
	 * @return string Caller string.
	 */
	private function extract_caller( $trace ) {
		if ( empty( $trace ) || ! is_array( $trace ) ) {
			return 'unknown';
		}

		// Get the first item in the trace.
		$first = reset( $trace );
		if ( isset( $first['display'] ) ) {
			return $first['display'];
		}

		return 'unknown';
	}

	/**
	 * Identify component from caller string.
	 *
	 * @param string $caller Caller string.
	 * @return string Component name.
	 */
	private function identify_component( $caller ) {
		if ( strpos( $caller, 'wp-content/plugins/' ) !== false ) {
			preg_match( '#wp-content/plugins/([^/]+)#', $caller, $matches );
			return isset( $matches[1] ) ? 'plugin:' . $matches[1] : 'plugin';
		}

		if ( strpos( $caller, 'wp-content/themes/' ) !== false ) {
			preg_match( '#wp-content/themes/([^/]+)#', $caller, $matches );
			return isset( $matches[1] ) ? 'theme:' . $matches[1] : 'theme';
		}

		if ( strpos( $caller, 'wp-includes/' ) !== false || strpos( $caller, 'wp-admin/' ) !== false ) {
			return 'core';
		}

		return 'unknown';
	}

	/**
	 * Check if a query might need an index using EXPLAIN.
	 *
	 * @param string $sql SQL query.
	 * @return bool True if query might need an index.
	 */
	private function check_needs_index( $sql ) {
		// Validate SQL query is safe for EXPLAIN.
		if ( ! $this->is_safe_for_explain( $sql ) ) {
			return false;
		}

		// Suppress errors for EXPLAIN queries.
		$this->connection->suppress_errors( true );

		// Run EXPLAIN on the query.
		// Note: $sql comes from query log which is WordPress internal logging.
		// Additional validation is performed by is_safe_for_explain() above.
		$explain = $this->connection->get_results( 'EXPLAIN ' . $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->connection->show_errors( true );

		if ( empty( $explain ) ) {
			return false;
		}

		// Check for common indicators of missing indexes.
		foreach ( $explain as $row ) {
			// Full table scan without index.
			if ( isset( $row['type'] ) && 'ALL' === $row['type'] ) {
				return true;
			}

			// High number of rows examined.
			if ( isset( $row['rows'] ) && $row['rows'] > 1000 ) {
				return true;
			}

			// Using filesort or temporary table.
			if ( isset( $row['Extra'] ) ) {
				if ( strpos( $row['Extra'], 'Using filesort' ) !== false ||
					 strpos( $row['Extra'], 'Using temporary' ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Validate that a SQL query is safe to use with EXPLAIN.
	 *
	 * This provides defense-in-depth even though $sql comes from $wpdb->queries
	 * which is populated by WordPress core internal logging.
	 *
	 * @since 1.2.0
	 *
	 * @param string $sql SQL query to validate.
	 * @return bool True if query is safe for EXPLAIN, false otherwise.
	 */
	private function is_safe_for_explain( string $sql ): bool {
		$sql = trim( $sql );

		// Must start with SELECT (case-insensitive) with no leading whitespace or comments.
		if ( stripos( $sql, 'SELECT' ) !== 0 ) {
			return false;
		}

		// Reject queries with multiple statements (semicolons outside quotes).
		// Simple check: reject any semicolons as EXPLAIN shouldn't need them.
		if ( strpos( $sql, ';' ) !== false ) {
			return false;
		}

		// Reject queries that look like they contain stacked/injected commands.
		// These patterns should never appear in legitimate WordPress SELECT queries.
		$dangerous_patterns = array(
			// UNION injection (both UNION ALL and plain UNION).
			'/\bUNION\b/i',
			// File operations.
			'/\bINTO\s+OUTFILE\b/i',
			'/\bINTO\s+DUMPFILE\b/i',
			'/\bLOAD_FILE\s*\(/i',
			// Time-based attacks.
			'/\bBENCHMARK\s*\(/i',
			'/\bSLEEP\s*\(/i',
			// SQL comments anywhere (not just end of line).
			'/--/',                              // Double dash comment.
			'/#/',                               // Hash comment.
			'/\/\*/',                            // Block comment start.
			'/\*\//',                            // Block comment end.
			// Additional dangerous functions.
			'/\bEXEC\s*\(/i',                    // Execute.
			'/\bEXECUTE\s*\(/i',                 // Execute.
			'/\bXP_/i',                          // Extended stored procs.
			'/\bSP_/i',                          // System stored procs.
		);

		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $sql ) ) {
				return false;
			}
		}

		// Query length sanity check (no legitimate WordPress query should be > 64KB).
		if ( strlen( $sql ) > 65536 ) {
			return false;
		}

		return true;
	}

	/**
	 * Log queries to the database.
	 *
	 * @param array $queries Array of query data.
	 * @return void
	 */
	private function log_queries( $queries ) {
		foreach ( $queries as $query ) {
			$this->connection->insert(
				$this->table_name,
				array(
					'sql'          => $query['sql'],
					'time_ms'      => $query['time'],
					'caller'       => $query['caller'],
					'component'    => $query['component'],
					'is_duplicate' => $query['is_duplicate'] ? 1 : 0,
					'needs_index'  => $query['needs_index'] ? 1 : 0,
					'created_at'   => $query['timestamp'],
				),
				array( '%s', '%f', '%s', '%s', '%d', '%d', '%s' )
			);
		}

		// Trigger auto-pruning (throttled to once per day).
		$this->maybe_auto_prune();
	}

	/**
	 * Automatically prune old logs if needed.
	 *
	 * Runs at most once per day to prevent excessive database operations.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function maybe_auto_prune() {
		// Check if we've pruned recently (within the last day).
		if ( false !== get_transient( self::PRUNE_TRANSIENT ) ) {
			return;
		}

		// Set transient to prevent running again for 24 hours.
		set_transient( self::PRUNE_TRANSIENT, time(), DAY_IN_SECONDS );

		// Prune old logs by TTL.
		$this->prune_old_logs();

		// Also enforce max rows limit to prevent unbounded growth.
		$this->enforce_max_rows();
	}

	/**
	 * Enforce maximum row limit by deleting oldest entries.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of rows deleted.
	 */
	private function enforce_max_rows() {
		// Get current row count.
		$count = $this->connection->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		if ( $count <= self::MAX_LOG_ROWS ) {
			return 0;
		}

		// Calculate how many rows to delete.
		$excess = $count - self::MAX_LOG_ROWS;

		// Delete oldest entries.
		$query = $this->connection->prepare(
			"DELETE FROM {$this->table_name}
			ORDER BY created_at ASC
			LIMIT %d",
			$excess
		);

		if ( null === $query ) {
			return 0;
		}

		$deleted = $this->connection->query( $query );

		return absint( $deleted );
	}

	/**
	 * Get query summary statistics.
	 *
 * @since 1.0.0
 *
	 * @param int $days Number of days to look back (default: 7).
	 * @return array Summary statistics.
	 */
	public function get_query_summary( int $days = 7 ): array {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get total queries logged.
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
			$since
		);
		$total_queries = $query ? $this->connection->get_var( $query ) : 0;

		// Get average query time.
		$query = $this->connection->prepare(
			"SELECT AVG(time_ms) FROM {$this->table_name} WHERE created_at >= %s",
			$since
		);
		$avg_time = $query ? $this->connection->get_var( $query ) : 0;

		// Get count of duplicate queries.
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE is_duplicate = 1 AND created_at >= %s",
			$since
		);
		$duplicate_count = $query ? $this->connection->get_var( $query ) : 0;

		// Get count of queries needing indexes.
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE needs_index = 1 AND created_at >= %s",
			$since
		);
		$needs_index_count = $query ? $this->connection->get_var( $query ) : 0;

		// Get slowest query.
		$query = $this->connection->prepare(
			"SELECT sql, time_ms, caller FROM {$this->table_name} WHERE created_at >= %s ORDER BY time_ms DESC LIMIT 1",
			$since
		);
		$slowest_query = $query ? $this->connection->get_row( $query, ARRAY_A ) : null;

		// Get queries by component.
		$query = $this->connection->prepare(
			"SELECT component, COUNT(*) as count, AVG(time_ms) as avg_time
			FROM {$this->table_name}
			WHERE created_at >= %s
			GROUP BY component
			ORDER BY count DESC",
			$since
		);
		$by_component = $query ? $this->connection->get_results( $query, ARRAY_A ) : array();

		return array(
			'period_days'       => $days,
			'total_queries'     => absint( $total_queries ),
			'avg_time_ms'       => round( floatval( $avg_time ), 2 ),
			'duplicate_count'   => absint( $duplicate_count ),
			'needs_index_count' => absint( $needs_index_count ),
			'slowest_query'     => $slowest_query,
			'by_component'      => $by_component,
		);
	}

	/**
	 * Get queries grouped by caller.
	 *
 * @since 1.0.0
 *
	 * @param int $limit Number of results to return (default: 20).
	 * @param int $days Number of days to look back (default: 7).
	 * @return array Queries grouped by caller.
	 */
	public function get_queries_by_caller( int $limit = 20, int $days = 7 ): array {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$query = $this->connection->prepare(
			"SELECT
				caller,
				component,
				COUNT(*) as query_count,
				AVG(time_ms) as avg_time,
				MAX(time_ms) as max_time,
				SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as duplicate_count,
				SUM(CASE WHEN needs_index = 1 THEN 1 ELSE 0 END) as needs_index_count
			FROM {$this->table_name}
			WHERE created_at >= %s
			GROUP BY caller, component
			ORDER BY query_count DESC
			LIMIT %d",
			$since,
			$limit
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

		// Format the results.
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[] = array(
				'caller'             => $row['caller'],
				'component'          => $row['component'],
				'query_count'        => absint( $row['query_count'] ),
				'avg_time_ms'        => round( floatval( $row['avg_time'] ), 2 ),
				'max_time_ms'        => round( floatval( $row['max_time'] ), 2 ),
				'duplicate_count'    => absint( $row['duplicate_count'] ),
				'needs_index_count'  => absint( $row['needs_index_count'] ),
			);
		}

		return $formatted;
	}

	/**
	 * Export query log to CSV format.
	 *
 * @since 1.0.0
 *
	 * @param int    $days Number of days to export (default: 7).
	 * @param string $format Export format: 'csv' or 'json' (default: 'csv').
	 * @return string|array Exported data.
	 */
	public function export_query_log( $days = 7, $format = 'csv' ) {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$query = $this->connection->prepare(
			"SELECT
				sql,
				time_ms,
				caller,
				component,
				is_duplicate,
				needs_index,
				created_at
			FROM {$this->table_name}
			WHERE created_at >= %s
			ORDER BY created_at DESC",
			$since
		);

		if ( null === $query ) {
			return 'csv' === $format ? '' : array();
		}

		$queries = $this->connection->get_results( $query, ARRAY_A );

		if ( 'json' === $format ) {
			return $queries;
		}

		// CSV format.
		$csv = array();
		$csv[] = array(
			'SQL',
			'Time (ms)',
			'Caller',
			'Component',
			'Is Duplicate',
			'Needs Index',
			'Created At',
		);

		foreach ( $queries as $query ) {
			$csv[] = array(
				$query['sql'],
				$query['time_ms'],
				$query['caller'],
				$query['component'],
				$query['is_duplicate'] ? 'Yes' : 'No',
				$query['needs_index'] ? 'Yes' : 'No',
				$query['created_at'],
			);
		}

		return $this->array_to_csv( $csv );
	}

	/**
	 * Convert array to CSV string.
	 *
	 * @param array $data Array data.
	 * @return string CSV string.
	 */
	private function array_to_csv( $data ) {
		$output = fopen( 'php://temp', 'r+' );

		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Prune old query logs based on TTL.
	 *
 * @since 1.0.0
 *
	 * @return int Number of rows deleted.
	 */
	public function prune_old_logs(): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::LOG_TTL );

		$query = $this->connection->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < %s",
			$cutoff
		);

		if ( null === $query ) {
			return 0;
		}

		$deleted = $this->connection->query( $query );

		return absint( $deleted );
	}

	/**
	 * Check if Query Monitor plugin is active.
	 *
 * @since 1.0.0
 *
	 * @return bool True if Query Monitor is active.
	 */
	public function is_query_monitor_active(): bool {
		return class_exists( 'QueryMonitor' ) || class_exists( 'QM_Collectors' );
	}

	/**
	 * Get current monitoring status.
	 *
 * @since 1.0.0
 *
	 * @return array Status information.
	 */
	public function get_monitoring_status(): array {
		return array(
			'query_monitor_active' => $this->is_query_monitor_active(),
			'savequeries_enabled'  => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
			'monitoring_enabled'   => $this->is_monitoring_enabled(),
			'default_threshold'    => self::DEFAULT_THRESHOLD,
			'log_ttl_days'         => self::LOG_TTL / DAY_IN_SECONDS,
		);
	}

	/**
	 * Check if monitoring is enabled.
	 *
	 * @return bool True if monitoring is enabled.
	 */
	private function is_monitoring_enabled() {
		return $this->is_query_monitor_active() || ( defined( 'SAVEQUERIES' ) && SAVEQUERIES );
	}
}
