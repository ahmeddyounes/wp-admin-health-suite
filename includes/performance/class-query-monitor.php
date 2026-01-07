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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Query Monitor class for tracking and analyzing database queries.
 *
 * @since 1.0.0
 */
class Query_Monitor {

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
	 * Constructor.
 * @since 1.0.0
 *
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wpha_query_log';
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
	public function capture_slow_queries( $threshold_ms = self::DEFAULT_THRESHOLD ) {
		global $wpdb;

		$slow_queries = array();

		// Check if Query Monitor plugin is active.
		if ( class_exists( 'QM_Collectors' ) && function_exists( 'QM_Collectors' ) ) {
			$slow_queries = $this->capture_from_query_monitor( $threshold_ms );
		} elseif ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ) {
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
		global $wpdb;

		$slow_queries = array();
		$query_hashes = array();

		if ( ! isset( $wpdb->queries ) || ! is_array( $wpdb->queries ) ) {
			return $slow_queries;
		}

		foreach ( $wpdb->queries as $query_data ) {
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
		global $wpdb;

		// Only check SELECT queries.
		if ( stripos( trim( $sql ), 'SELECT' ) !== 0 ) {
			return false;
		}

		// Suppress errors for EXPLAIN queries.
		$wpdb->hide_errors();

		// Run EXPLAIN on the query.
		$explain = $wpdb->get_results( "EXPLAIN $sql", ARRAY_A );

		$wpdb->show_errors();

		if ( empty( $explain ) ) {
			return false;
		}

		// Check for common indicators of missing indexes.
		foreach ( $explain as $row ) {
			// Full table scan without index.
			if ( isset( $row['type'] ) && $row['type'] === 'ALL' ) {
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
	 * Log queries to the database.
	 *
	 * @param array $queries Array of query data.
	 * @return void
	 */
	private function log_queries( $queries ) {
		global $wpdb;

		foreach ( $queries as $query ) {
			$wpdb->insert(
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
	}

	/**
	 * Get query summary statistics.
	 *
 * @since 1.0.0
 *
	 * @param int $limit Number of days to look back (default: 7).
	 * @return array Summary statistics.
	 */
	public function get_query_summary( $limit = 7 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$limit} days" ) );

		// Get total queries logged.
		$total_queries = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
				$since
			)
		);

		// Get average query time.
		$avg_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(time_ms) FROM {$this->table_name} WHERE created_at >= %s",
				$since
			)
		);

		// Get count of duplicate queries.
		$duplicate_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE is_duplicate = 1 AND created_at >= %s",
				$since
			)
		);

		// Get count of queries needing indexes.
		$needs_index_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE needs_index = 1 AND created_at >= %s",
				$since
			)
		);

		// Get slowest query.
		$slowest_query = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT sql, time_ms, caller FROM {$this->table_name} WHERE created_at >= %s ORDER BY time_ms DESC LIMIT 1",
				$since
			),
			ARRAY_A
		);

		// Get queries by component.
		$by_component = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT component, COUNT(*) as count, AVG(time_ms) as avg_time
				FROM {$this->table_name}
				WHERE created_at >= %s
				GROUP BY component
				ORDER BY count DESC",
				$since
			),
			ARRAY_A
		);

		return array(
			'period_days'       => $limit,
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
	public function get_queries_by_caller( $limit = 20, $days = 7 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
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
			),
			ARRAY_A
		);

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
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$queries = $wpdb->get_results(
			$wpdb->prepare(
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
			),
			ARRAY_A
		);

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
	public function prune_old_logs() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::LOG_TTL );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE created_at < %s",
				$cutoff
			)
		);

		return absint( $deleted );
	}

	/**
	 * Check if Query Monitor plugin is active.
	 *
 * @since 1.0.0
 *
	 * @return bool True if Query Monitor is active.
	 */
	public function is_query_monitor_active() {
		return class_exists( 'QueryMonitor' ) || class_exists( 'QM_Collectors' );
	}

	/**
	 * Get current monitoring status.
	 *
 * @since 1.0.0
 *
	 * @return array Status information.
	 */
	public function get_monitoring_status() {
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
