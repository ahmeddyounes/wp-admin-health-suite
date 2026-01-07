<?php
/**
 * REST Controller Base Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Base REST API controller class.
 *
 * Provides common functionality for REST API endpoints including:
 * - Authentication and permission checks
 * - Nonce verification
 * - Rate limiting
 * - Standard response formatting
 * - Error handling
 */
class REST_Controller extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wpha/v1';

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Rate limit: maximum requests per minute.
	 *
	 * @var int
	 */
	protected $rate_limit = 60;

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the object.', 'wp-admin-health-suite' ),
							'type'        => 'integer',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the object.', 'wp-admin-health-suite' ),
							'type'        => 'integer',
						),
					),
				),
			)
		);
	}

	/**
	 * Get a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		return $this->format_response( true, array(), __( 'Items retrieved successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Get a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$id = $request->get_param( 'id' );
		return $this->format_response( true, array( 'id' => $id ), __( 'Item retrieved successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Create a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		return $this->format_response( true, array(), __( 'Item created successfully.', 'wp-admin-health-suite' ), 201 );
	}

	/**
	 * Delete a single item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$id = $request->get_param( 'id' );
		return $this->format_response( true, array( 'id' => $id ), __( 'Item deleted successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Check permissions for the request.
	 *
	 * Verifies:
	 * - REST API is enabled
	 * - User authentication
	 * - manage_options capability
	 * - Nonce verification via X-WP-Nonce header
	 * - Rate limiting
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
	 */
	public function check_permissions( $request ) {
		// Check if REST API is enabled.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		if ( ! $settings->is_rest_api_enabled() ) {
			return new WP_Error(
				'rest_api_disabled',
				__( 'REST API is currently disabled.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You are not currently logged in.', 'wp-admin-health-suite' ),
				array( 'status' => 401 )
			);
		}

		// Check if user has manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Check rate limiting.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		return true;
	}

	/**
	 * Verify nonce from X-WP-Nonce header.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if nonce is valid, WP_Error otherwise.
	 */
	protected function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			return new WP_Error(
				'rest_missing_nonce',
				__( 'Missing security nonce.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid security nonce.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check rate limiting for the current user.
	 *
	 * Limits requests per minute per user using transients.
	 * Rate limit is configurable via settings.
	 *
	 * @return bool|WP_Error True if within rate limit, WP_Error otherwise.
	 */
	protected function check_rate_limit() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return true;
		}

		// Get rate limit from settings.
		$settings   = \WPAdminHealth\Plugin::get_instance()->get_settings();
		$rate_limit = $settings->get_rest_api_rate_limit();

		$transient_key = 'wpha_rate_limit_' . $user_id;
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			// First request in this minute.
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		if ( $requests >= $rate_limit ) {
			return new WP_Error(
				'rest_rate_limit_exceeded',
				sprintf(
					/* translators: %d: rate limit */
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'wp-admin-health-suite' ),
					$rate_limit
				),
				array( 'status' => 429 )
			);
		}

		// Increment the request counter.
		set_transient( $transient_key, $requests + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Format response in standard format.
	 *
	 * @param bool   $success Whether the request was successful.
	 * @param mixed  $data    The response data.
	 * @param string $message The response message.
	 * @param int    $status  HTTP status code (default: 200).
	 * @return WP_REST_Response The formatted response.
	 */
	protected function format_response( $success, $data = null, $message = '', $status = 200 ) {
		$response = array(
			'success' => $success,
			'data'    => $data,
			'message' => $message,
		);

		// Add debug information if debug mode is enabled.
		if ( $this->is_debug_mode_enabled() ) {
			global $wpdb;

			$response['debug'] = array(
				'queries'       => $wpdb->num_queries,
				'memory_usage'  => size_format( memory_get_usage() ),
				'memory_peak'   => size_format( memory_get_peak_usage() ),
				'time_elapsed'  => timer_stop( 0, 3 ),
			);

			// Include query log if available.
			if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wpdb->queries ) ) {
				$response['debug']['query_log'] = array_map(
					function( $query ) {
						return array(
							'query' => $query[0],
							'time'  => $query[1] . 's',
							'stack' => $query[2],
						);
					},
					array_slice( $wpdb->queries, -10 ) // Last 10 queries
				);
			}
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Format error response.
	 *
	 * @param WP_Error $error   The error object.
	 * @param int      $status  HTTP status code (default: 400).
	 * @return WP_REST_Response The formatted error response.
	 */
	protected function format_error_response( $error, $status = 400 ) {
		$response = array(
			'success' => false,
			'data'    => null,
			'message' => $error->get_error_message(),
		);

		// Include error code if available.
		if ( $error->get_error_code() ) {
			$response['code'] = $error->get_error_code();
		}

		// Include error data if available.
		if ( $error->get_error_data() ) {
			$response['error_data'] = $error->get_error_data();
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * When safe mode is enabled, all destructive operations should return
	 * preview data only without actually modifying anything.
	 *
	 * @return bool True if safe mode is enabled, false otherwise.
	 */
	protected function is_safe_mode_enabled() {
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		return $settings->is_safe_mode_enabled();
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * When debug mode is enabled, extra logging and query time information
	 * should be included in responses.
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	protected function is_debug_mode_enabled() {
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		return $settings->is_debug_mode_enabled();
	}
}
