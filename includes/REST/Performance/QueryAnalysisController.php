<?php
/**
 * Query Analysis REST Controller
 *
 * Handles database query monitoring and analysis.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for query analysis endpoints.
 *
 * Provides endpoints for monitoring and analyzing database queries.
 *
 * @since 1.3.0
 */
class QueryAnalysisController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/queries';

	/**
	 * Query monitor instance.
	 *
	 * @var QueryMonitorInterface
	 */
	private QueryMonitorInterface $query_monitor;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface      $settings      Settings instance.
	 * @param ConnectionInterface    $connection    Database connection instance.
	 * @param QueryMonitorInterface  $query_monitor Query monitor instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		QueryMonitorInterface $query_monitor
	) {
		parent::__construct( $settings, $connection );
		$this->query_monitor = $query_monitor;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/queries.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_query_analysis' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get query analysis.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to QueryAnalysisController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_query_analysis( $request ) {
		$connection = $this->get_connection();

		$query_count = $connection->get_num_queries();

		$threshold_ms = 50.0;
		$status       = $this->query_monitor->get_monitoring_status();
		$can_capture  = isset( $status['monitoring_enabled'] ) ? (bool) $status['monitoring_enabled'] : ( defined( 'SAVEQUERIES' ) && SAVEQUERIES );

		// Only show raw SQL when explicitly enabled (WPHA_DEBUG) and debug mode is on.
		// Otherwise, return a redacted query string to reduce accidental data exposure.
		$include_raw_sql = $this->is_debug_mode_enabled() && $this->can_view_detailed_debug();

		$slow_queries = array();
		if ( $can_capture ) {
			$captured = $this->query_monitor->capture_slow_queries( $threshold_ms );
			foreach ( $captured as $query ) {
				$sql     = isset( $query['sql'] ) ? $query['sql'] : ( $query['query'] ?? '' );
				$time_ms = isset( $query['time'] ) ? (float) $query['time'] : 0.0;

				$sql_string = is_string( $sql ) ? $sql : '';
				$query_hash = '' !== $sql_string ? substr( md5( $sql_string ), 0, 12 ) : '';

				$item = array(
					'query'      => $this->sanitize_sql_for_output( $sql_string, ! $include_raw_sql ),
					'query_hash' => $query_hash,
					'query_type' => $this->get_query_type( $sql_string ),
					'time'       => round( $time_ms / 1000, 5 ),
					'caller'     => $this->sanitize_caller_for_output( isset( $query['caller'] ) ? (string) $query['caller'] : '' ),
				);

				if ( isset( $query['component'] ) ) {
					$item['component'] = sanitize_text_field( (string) $query['component'] );
				}

				if ( isset( $query['is_duplicate'] ) ) {
					$item['is_duplicate'] = (bool) $query['is_duplicate'];
				}

				if ( isset( $query['needs_index'] ) ) {
					$item['needs_index'] = (bool) $query['needs_index'];
				}

				$slow_queries[] = $item;
			}
		}

		// Sort by time descending.
		usort(
			$slow_queries,
			function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);

		$response_data = array(
			'total_queries' => $query_count,
			'slow_queries'  => array_slice( $slow_queries, 0, 20 ), // Top 20 slow queries.
			'savequeries'   => $can_capture,
			'threshold_ms'  => $threshold_ms,
			'monitoring'    => $status,
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Query analysis retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Sanitize SQL for REST output.
	 *
	 * Collapses whitespace, optionally redacts literals, and enforces a length limit.
	 * This is for display purposes only and is never executed.
	 *
	 * @since 1.6.1
	 *
	 * @param string $sql           SQL query string.
	 * @param bool   $redact_values Whether to redact string/numeric literals.
	 * @return string Sanitized SQL string for output.
	 */
	private function sanitize_sql_for_output( string $sql, bool $redact_values ): string {
		$sql = trim( $sql );
		if ( '' === $sql ) {
			return '';
		}

		// Normalize whitespace for readability and to reduce accidental leakage of formatting.
		$sql = preg_replace( '/\\s+/', ' ', $sql );
		$sql = is_string( $sql ) ? $sql : '';

		if ( $redact_values ) {
			// Redact common literal formats to reduce exposure of user/content data.
			// Replace quoted strings with '?' placeholders.
			$sql = preg_replace( "/'(?:\\\\\\\\.|[^'\\\\\\\\])*'/", "'?'", $sql );
			$sql = is_string( $sql ) ? $sql : '';
			$sql = preg_replace( '/"(?:\\\\\\\\.|[^"\\\\\\\\])*"/', '"?"', $sql );
			$sql = is_string( $sql ) ? $sql : '';

			// Replace numeric literals (including decimals) with '?'.
			$sql = preg_replace( '/\\b\\d+(?:\\.\\d+)?\\b/', '?', $sql );
			$sql = is_string( $sql ) ? $sql : '';
		}

		// Enforce a max length to avoid huge payloads.
		$max_len = 500;
		if ( strlen( $sql ) > $max_len ) {
			$sql = substr( $sql, 0, $max_len ) . '…';
		}

		return sanitize_text_field( $sql );
	}

	/**
	 * Sanitize caller information for REST output.
	 *
	 * Removes absolute path prefixes where possible and enforces a length limit.
	 *
	 * @since 1.6.1
	 *
	 * @param string $caller Caller string.
	 * @return string Sanitized caller string.
	 */
	private function sanitize_caller_for_output( string $caller ): string {
		$caller = sanitize_text_field( $caller );

		if ( defined( 'ABSPATH' ) && is_string( ABSPATH ) && '' !== ABSPATH ) {
			$caller = str_replace( ABSPATH, '', $caller );
		}

		$caller = trim( $caller );
		if ( '' === $caller ) {
			return 'unknown';
		}

		$max_len = 200;
		if ( strlen( $caller ) > $max_len ) {
			$caller = substr( $caller, 0, $max_len ) . '…';
		}

		return $caller;
	}
}
