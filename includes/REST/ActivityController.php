<?php
/**
 * Activity REST Controller
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for activity timeline.
 *
 * Handles fetching recent activities from the scan history table.
 *
 * @since 1.0.0
 */
class ActivityController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'activity';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Added optional connection parameter.
	 *
	 * @param SettingsInterface|null   $settings   Optional settings instance for dependency injection.
	 * @param ConnectionInterface|null $connection Optional database connection for dependency injection.
	 */
	public function __construct( ?SettingsInterface $settings = null, ?ConnectionInterface $connection = null ) {
		parent::__construct( $settings, $connection );
	}

	/**
	 * Register routes for the controller.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_activities' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Get recent activities from scan history.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activities( $request ) {
		$connection = $this->get_connection();

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		// Back-compat: Support legacy `limit` param as an alias for per_page.
		// `per_page` wins if both are provided.
		$limit = $request->get_param( 'limit' );
		if ( null !== $limit && ! $request->has_param( 'per_page' ) ) {
			$per_page = $limit;
		}

		// Calculate offset.
		$offset = ( $page - 1 ) * $per_page;

		$scan_type        = $request->get_param( 'scan_type' );
		$scan_type_prefix = $request->get_param( 'scan_type_prefix' );

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return $this->format_response(
				true,
				array(
					'items'        => array(),
					'total'        => 0,
					'total_pages'  => 0,
					'current_page' => $page,
					'per_page'     => $per_page,
				),
				__( 'No activities found. Database table does not exist yet.', 'wp-admin-health-suite' )
			);
		}

		$where_clause = '';
		$where_args   = array();

		if ( ! empty( $scan_type ) ) {
			$where_clause = 'WHERE scan_type = %s';
			$where_args[] = $scan_type;
		} elseif ( ! empty( $scan_type_prefix ) ) {
			$where_clause = 'WHERE scan_type LIKE %s';
			$where_args[] = $connection->esc_like( $scan_type_prefix ) . '%';
		}

		// Get total count for pagination.
		$total_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
		if ( ! empty( $where_args ) ) {
			$total_query = $connection->prepare( $total_query, ...$where_args );
		}

		if ( null === $total_query ) {
			return $this->format_error_response(
				new WP_Error(
					'database_error',
					__( 'Database error occurred while preparing total count query.', 'wp-admin-health-suite' )
				),
				500
			);
		}

		$total = (int) $connection->get_var( $total_query );

		// Fetch activities from database.
		$query_args = array_merge( $where_args, array( $per_page, $offset ) );
		$query      = $connection->prepare(
			"SELECT id, scan_type, items_found, items_cleaned, bytes_freed, created_at
			FROM {$table_name}
			{$where_clause}
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			...$query_args
		);

		if ( null === $query ) {
			return $this->format_error_response(
				new WP_Error(
					'database_error',
					__( 'Database error occurred while preparing query.', 'wp-admin-health-suite' )
				),
				500
			);
		}

		$activities = $connection->get_results( $query, 'ARRAY_A' );

		// Format the activities data.
		$formatted_activities = array_map(
			function ( $activity ) {
				$created_at = isset( $activity['created_at'] ) ? sanitize_text_field( $activity['created_at'] ) : '';
				if ( '' !== $created_at && function_exists( 'mysql_to_rfc3339' ) ) {
					$rfc3339 = mysql_to_rfc3339( $created_at );
					if ( false !== $rfc3339 ) {
						$created_at = $rfc3339;
					}
				}

				return array(
					'id'            => (int) $activity['id'],
					'scan_type'     => sanitize_text_field( $activity['scan_type'] ),
					'items_found'   => isset( $activity['items_found'] ) ? absint( $activity['items_found'] ) : 0,
					'items_cleaned' => isset( $activity['items_cleaned'] ) ? absint( $activity['items_cleaned'] ) : 0,
					'bytes_freed'   => isset( $activity['bytes_freed'] ) ? absint( $activity['bytes_freed'] ) : 0,
					'created_at'    => $created_at,
				);
			},
			$activities
		);

		$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

		return $this->format_response(
			true,
			array(
				'items'        => $formatted_activities,
				'total'        => $total,
				'total_pages'  => $total_pages,
				'current_page' => $page,
				'per_page'     => $per_page,
			),
			__( 'Activities retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get collection parameters.
	 *
 * @since 1.0.0
 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['limit'] = array(
			'description'       => __( 'Deprecated. Use per_page. Maximum number of activities to return.', 'wp-admin-health-suite' ),
			'type'              => 'integer',
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['scan_type'] = array(
			'description'       => __( 'Limit results to a specific scan type.', 'wp-admin-health-suite' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['scan_type_prefix'] = array(
			'description'       => __( 'Limit results to scan types starting with this prefix.', 'wp-admin-health-suite' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
