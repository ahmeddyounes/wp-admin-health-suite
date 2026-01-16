<?php
/**
 * Ajax Monitor Class
 *
 * Provides AJAX monitoring and analysis functionality.
 * Tracks admin AJAX requests including execution time, memory usage,
 * user role, and request frequency.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Ajax Monitor class for tracking and analyzing AJAX requests.
 *
 * @since 1.0.0
 */
class AjaxMonitor {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Table name for AJAX logs.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * TTL for AJAX logs in seconds (7 days).
	 *
	 * @var int
	 */
	const LOG_TTL = 604800;

	/**
	 * Request start time in microseconds.
	 *
	 * @var float|null
	 */
	private ?float $request_start_time = null;

	/**
	 * Request start memory in bytes.
	 *
	 * @var int|null
	 */
	private ?int $request_start_memory = null;

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
		$this->table_name = $this->connection->get_prefix() . 'wpha_ajax_log';
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for AJAX monitoring.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Hook early to capture all admin AJAX requests.
		add_action( 'admin_init', array( $this, 'start_ajax_monitoring' ), 1 );
		add_action( 'shutdown', array( $this, 'finish_ajax_monitoring' ), 999 );

		// Schedule daily cleanup.
		if ( ! wp_next_scheduled( 'wpha_ajax_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpha_ajax_log_cleanup' );
		}

		add_action( 'wpha_ajax_log_cleanup', array( $this, 'handle_log_cleanup' ) );
	}

	/**
	 * Start monitoring AJAX request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function start_ajax_monitoring() {
		// Only monitor admin AJAX requests.
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$this->request_start_time   = microtime( true );
		$this->request_start_memory = memory_get_usage();
	}

	/**
	 * Finish monitoring and log AJAX request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function finish_ajax_monitoring() {
		// Only log admin AJAX requests.
		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) ) {
			return;
		}

		// Ensure monitoring was started.
		if ( null === $this->request_start_time || null === $this->request_start_memory ) {
			return;
		}

		// Calculate execution metrics.
		$execution_time = ( microtime( true ) - $this->request_start_time ) * 1000; // Convert to milliseconds.
		$memory_used    = memory_get_usage() - $this->request_start_memory;

		// Get request details.
		$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );

		// Get user role.
		$user_role = $this->get_current_user_role();

		// Log the request.
		$this->log_ajax_request( $action, $execution_time, $memory_used, $user_role );
	}

	/**
	 * Get current user role.
	 *
	 * @return string User role or 'guest' if not logged in.
	 */
	private function get_current_user_role() {
		if ( ! is_user_logged_in() ) {
			return 'guest';
		}

		$user = wp_get_current_user();
		if ( empty( $user->roles ) ) {
			return 'none';
		}

		return $user->roles[0];
	}

	/**
	 * Handle scheduled log cleanup action.
	 *
	 * Action callbacks should not return values, so this wraps prune_old_logs().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_log_cleanup(): void {
		$this->prune_old_logs();
	}

	/**
	 * Log AJAX request to database.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action         AJAX action name.
	 * @param float  $execution_time Execution time in milliseconds.
	 * @param int    $memory_used    Memory used in bytes.
	 * @param string $user_role      User role.
	 * @return bool True on success, false on failure.
	 */
	public function log_ajax_request( string $action, float $execution_time, int $memory_used, string $user_role ): bool {
		$result = $this->connection->insert(
			$this->table_name,
			array(
				'action'         => $action,
				'execution_time' => $execution_time,
				'memory_used'    => $memory_used,
				'user_role'      => $user_role,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%f', '%d', '%s', '%s' )
		);

		return (bool) $result;
	}

	/**
	 * Get AJAX request summary for a given period.
	 *
	 * @since 1.0.0
	 *
	 * @param string $period Period: '1hour', '24hours', '7days', '30days' (default: '24hours').
	 * @return array Summary statistics.
	 */
	public function get_ajax_summary( $period = '24hours' ) {
		$since = $this->get_period_start_time( $period );

		// Total requests.
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
			$since
		);
		$total_requests = $query ? $this->connection->get_var( $query ) : 0;

		// Average response time.
		$query = $this->connection->prepare(
			"SELECT AVG(execution_time) FROM {$this->table_name} WHERE created_at >= %s",
			$since
		);
		$avg_response_time = $query ? $this->connection->get_var( $query ) : 0;

		// Average memory used.
		$query = $this->connection->prepare(
			"SELECT AVG(memory_used) FROM {$this->table_name} WHERE created_at >= %s",
			$since
		);
		$avg_memory = $query ? $this->connection->get_var( $query ) : 0;

		// Requests per minute.
		$period_minutes  = $this->get_period_minutes( $period );
		$requests_per_min = $period_minutes > 0 ? $total_requests / $period_minutes : 0;

		// Slowest request.
		$query = $this->connection->prepare(
			"SELECT action, execution_time, created_at
			FROM {$this->table_name}
			WHERE created_at >= %s
			ORDER BY execution_time DESC
			LIMIT 1",
			$since
		);
		$slowest_request = $query ? $this->connection->get_row( $query, ARRAY_A ) : null;

		// Requests by user role.
		$query = $this->connection->prepare(
			"SELECT user_role, COUNT(*) as count
			FROM {$this->table_name}
			WHERE created_at >= %s
			GROUP BY user_role
			ORDER BY count DESC",
			$since
		);
		$by_role = $query ? $this->connection->get_results( $query, ARRAY_A ) : array();

		return array(
			'period'             => $period,
			'total_requests'     => absint( $total_requests ),
			'requests_per_min'   => round( $requests_per_min, 2 ),
			'avg_response_time'  => round( floatval( $avg_response_time ), 2 ),
			'avg_memory_bytes'   => absint( $avg_memory ),
			'avg_memory_human'   => size_format( absint( $avg_memory ) ),
			'slowest_request'    => $slowest_request,
			'by_role'            => $by_role,
		);
	}

	/**
	 * Get most frequent AJAX actions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $period Period: '1hour', '24hours', '7days', '30days' (default: '24hours').
	 * @param int    $limit  Number of results to return (default: 10).
	 * @return array Frequent AJAX actions with statistics.
	 */
	public function get_frequent_ajax_actions( $period = '24hours', $limit = 10 ) {
		$since = $this->get_period_start_time( $period );

		$query = $this->connection->prepare(
			"SELECT
				action,
				COUNT(*) as request_count,
				AVG(execution_time) as avg_time,
				MAX(execution_time) as max_time,
				MIN(execution_time) as min_time,
				AVG(memory_used) as avg_memory
			FROM {$this->table_name}
			WHERE created_at >= %s
			GROUP BY action
			ORDER BY request_count DESC
			LIMIT %d",
			$since,
			$limit
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

		// Format results.
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[] = array(
				'action'        => $row['action'],
				'request_count' => absint( $row['request_count'] ),
				'avg_time_ms'   => round( floatval( $row['avg_time'] ), 2 ),
				'max_time_ms'   => round( floatval( $row['max_time'] ), 2 ),
				'min_time_ms'   => round( floatval( $row['min_time'] ), 2 ),
				'avg_memory'    => size_format( absint( $row['avg_memory'] ) ),
			);
		}

		return $formatted;
	}

	/**
	 * Get slow AJAX actions above a threshold.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $threshold_ms Threshold in milliseconds (default: 1000ms).
	 * @param string $period       Period: '1hour', '24hours', '7days', '30days' (default: '24hours').
	 * @param int    $limit        Number of results to return (default: 10).
	 * @return array Slow AJAX actions.
	 */
	public function get_slow_ajax_actions( $threshold_ms = 1000.0, $period = '24hours', $limit = 10 ) {
		$since = $this->get_period_start_time( $period );

		$query = $this->connection->prepare(
			"SELECT
				action,
				COUNT(*) as slow_count,
				AVG(execution_time) as avg_time,
				MAX(execution_time) as max_time
			FROM {$this->table_name}
			WHERE created_at >= %s AND execution_time >= %f
			GROUP BY action
			ORDER BY avg_time DESC
			LIMIT %d",
			$since,
			$threshold_ms,
			$limit
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

		// Format results.
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[] = array(
				'action'      => $row['action'],
				'slow_count'  => absint( $row['slow_count'] ),
				'avg_time_ms' => round( floatval( $row['avg_time'] ), 2 ),
				'max_time_ms' => round( floatval( $row['max_time'] ), 2 ),
			);
		}

		return $formatted;
	}

	/**
	 * Identify potentially excessive polling by detecting high-frequency requests.
	 *
	 * @since 1.0.0
	 *
	 * @param string $period       Period: '1hour', '24hours', '7days', '30days' (default: '1hour').
	 * @param int    $min_requests Minimum requests to be considered excessive (default: 60).
	 * @return array Actions with excessive polling detected.
	 */
	public function get_excessive_polling( $period = '1hour', $min_requests = 60 ) {
		$since = $this->get_period_start_time( $period );
		$period_minutes = $this->get_period_minutes( $period );

		$query = $this->connection->prepare(
			"SELECT
				action,
				COUNT(*) as request_count,
				AVG(execution_time) as avg_time
			FROM {$this->table_name}
			WHERE created_at >= %s
			GROUP BY action
			HAVING request_count >= %d
			ORDER BY request_count DESC",
			$since,
			$min_requests
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

		// Format results with requests per minute.
		$formatted = array();
		foreach ( $results as $row ) {
			$request_count = absint( $row['request_count'] );
			$rpm = $period_minutes > 0 ? $request_count / $period_minutes : 0;

			$formatted[] = array(
				'action'             => $row['action'],
				'request_count'      => $request_count,
				'requests_per_min'   => round( $rpm, 2 ),
				'avg_time_ms'        => round( floatval( $row['avg_time'] ), 2 ),
				'optimization_note'  => $rpm > 1 ? 'High polling frequency detected' : 'Normal frequency',
			);
		}

		return $formatted;
	}

	/**
	 * Identify redundant requests (same action called multiple times in short period).
	 *
	 * @since 1.0.0
	 *
	 * @param int $timeframe_seconds Timeframe in seconds to check for redundancy (default: 60).
	 * @param int $min_occurrences   Minimum occurrences to be considered redundant (default: 3).
	 * @return array Redundant request patterns.
	 */
	public function get_redundant_requests( $timeframe_seconds = 60, $min_occurrences = 3 ) {
		// Get recent requests.
		$since = gmdate( 'Y-m-d H:i:s', time() - 3600 ); // Last hour.

		$query = $this->connection->prepare(
			"SELECT
				action,
				user_role,
				created_at,
				execution_time
			FROM {$this->table_name}
			WHERE created_at >= %s
			ORDER BY action, created_at",
			$since
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

		// Analyze for redundancy patterns.
		$redundant = array();
		$current_group = array();
		$last_action = null;
		$last_time = null;

		foreach ( $results as $row ) {
			$action = $row['action'];
			$timestamp = strtotime( $row['created_at'] );

			if ( $action !== $last_action || ( $last_time && ( $timestamp - $last_time ) > $timeframe_seconds ) ) {
				// Check if current group is redundant.
				if ( count( $current_group ) >= $min_occurrences ) {
					$redundant[] = array(
						'action'       => $last_action,
						'occurrences'  => count( $current_group ),
						'timeframe_sec' => $timeframe_seconds,
						'user_role'    => $current_group[0]['user_role'],
					);
				}

				// Start new group.
				$current_group = array( $row );
			} else {
				// Add to current group.
				$current_group[] = $row;
			}

			$last_action = $action;
			$last_time = $timestamp;
		}

		// Check final group.
		if ( count( $current_group ) >= $min_occurrences ) {
			$redundant[] = array(
				'action'        => $last_action,
				'occurrences'   => count( $current_group ),
				'timeframe_sec' => $timeframe_seconds,
				'user_role'     => $current_group[0]['user_role'],
			);
		}

		return $redundant;
	}

	/**
	 * Get period start time for queries.
	 *
	 * @param string $period Period identifier.
	 * @return string MySQL datetime string.
	 */
	private function get_period_start_time( $period ) {
		$time_map = array(
			'1hour'   => '-1 hour',
			'24hours' => '-24 hours',
			'7days'   => '-7 days',
			'30days'  => '-30 days',
		);

		$time_offset = isset( $time_map[ $period ] ) ? $time_map[ $period ] : '-24 hours';
		return gmdate( 'Y-m-d H:i:s', strtotime( $time_offset ) );
	}

	/**
	 * Get period duration in minutes.
	 *
	 * @param string $period Period identifier.
	 * @return int Minutes in the period.
	 */
	private function get_period_minutes( $period ) {
		$minutes_map = array(
			'1hour'   => 60,
			'24hours' => 1440,
			'7days'   => 10080,
			'30days'  => 43200,
		);

		return isset( $minutes_map[ $period ] ) ? $minutes_map[ $period ] : 1440;
	}

	/**
	 * Prune old AJAX logs based on TTL.
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

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Get monitoring status.
	 *
	 * @since 1.0.0
	 *
	 * @return array Status information.
	 */
	public function get_monitoring_status() {
		$total_logged = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$this->table_name}"
		);

		$oldest_log = $this->connection->get_var(
			"SELECT created_at FROM {$this->table_name} ORDER BY created_at ASC LIMIT 1"
		);

		return array(
			'monitoring_enabled'    => true,
			'total_requests_logged' => absint( $total_logged ),
			'oldest_log'            => $oldest_log,
			'log_ttl_days'          => self::LOG_TTL / DAY_IN_SECONDS,
		);
	}
}
