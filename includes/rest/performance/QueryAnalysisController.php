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

		$slow_queries = array();
		$query_count  = $connection->get_num_queries();

		// Get slow query log if available.
		$query_log = $connection->get_query_log();
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $query_log ) ) {
			foreach ( $query_log as $query_data ) {
				if ( $query_data[1] > 0.05 ) { // Queries slower than 50ms.
					$slow_queries[] = array(
						'query'  => $query_data[0],
						'time'   => (float) $query_data[1],
						'caller' => $query_data[2],
					);
				}
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
			'savequeries'   => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Query analysis retrieved successfully.', 'wp-admin-health-suite' )
		);
	}
}
