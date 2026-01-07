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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for activity timeline.
 *
 * Handles fetching recent activities from the scan history table.
 */
class Activity_Controller extends REST_Controller {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'activity';

	/**
	 * Register routes for the controller.
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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activities( $request ) {
		global $wpdb;

		$limit = $request->get_param( 'limit' );
		if ( null === $limit ) {
			$limit = 10;
		}

		// Sanitize limit parameter.
		$limit = absint( $limit );
		if ( $limit < 1 || $limit > 100 ) {
			$limit = 10;
		}

		$table_name = $wpdb->prefix . 'wpha_scan_history';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists !== $table_name ) {
			return $this->format_response(
				true,
				array(),
				__( 'No activities found. Database table does not exist yet.', 'wp-admin-health-suite' )
			);
		}

		// Fetch activities from database.
		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, scan_type, items_found, items_cleaned, bytes_freed, created_at
				FROM {$table_name}
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( null === $activities ) {
			return $this->format_error_response(
				new WP_Error(
					'database_error',
					__( 'Database error occurred while fetching activities.', 'wp-admin-health-suite' )
				),
				500
			);
		}

		// Format the activities data.
		$formatted_activities = array_map(
			function( $activity ) {
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
