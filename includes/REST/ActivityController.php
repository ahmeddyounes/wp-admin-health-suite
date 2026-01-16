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

		$limit = $request->get_param( 'limit' );
		if ( null === $limit ) {
			$limit = 10;
		}

		// Sanitize limit parameter.
		$limit = absint( $limit );
		if ( $limit < 1 || $limit > 100 ) {
			$limit = 10;
		}

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return $this->format_response(
				true,
				array(),
				__( 'No activities found. Database table does not exist yet.', 'wp-admin-health-suite' )
			);
		}

		// Fetch activities from database.
		$query = $connection->prepare(
			"SELECT id, scan_type, items_found, items_cleaned, bytes_freed, created_at
			FROM {$table_name}
			ORDER BY created_at DESC
			LIMIT %d",
			$limit
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

		if ( empty( $activities ) ) {
			return $this->format_response(
				true,
				array(),
				__( 'No activities found.', 'wp-admin-health-suite' )
			);
		}

		// Format the activities data.
		$formatted_activities = array_map(
			function ( $activity ) {
				return array(
					'id'            => (int) $activity['id'],
					'scan_type'     => sanitize_text_field( $activity['scan_type'] ),
					'items_found'   => (int) $activity['items_found'],
					'items_cleaned' => (int) $activity['items_cleaned'],
					'bytes_freed'   => (int) $activity['bytes_freed'],
					'created_at'    => $activity['created_at'],
				);
			},
			$activities
		);

		return $this->format_response(
			true,
			$formatted_activities,
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
		return array(
			'limit' => array(
				'description'       => __( 'Maximum number of activities to return.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
