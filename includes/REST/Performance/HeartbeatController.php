<?php
/**
 * Heartbeat REST Controller
 *
 * Handles WordPress Heartbeat API control and settings.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for heartbeat control endpoints.
 *
 * Provides endpoints for managing WordPress Heartbeat API settings.
 *
 * @since 1.3.0
 */
class HeartbeatController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/heartbeat';

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface   $settings   Settings instance.
	 * @param ConnectionInterface $connection Database connection instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection
	) {
		parent::__construct( $settings, $connection );
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/heartbeat.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_heartbeat_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_heartbeat_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'location' => array(
							'description'       => __( 'The location to update settings for.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'dashboard', 'editor', 'frontend' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'enabled'  => array(
							'description'       => __( 'Whether heartbeat is enabled.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'interval' => array(
							'description'       => __( 'Heartbeat interval in seconds.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'minimum'           => 15,
							'maximum'           => 120,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get heartbeat settings.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to HeartbeatController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_heartbeat_settings( $request ) {
		$settings = get_option(
			'wpha_heartbeat_settings',
			array(
				'dashboard' => array(
					'enabled'  => true,
					'interval' => 60,
				),
				'editor'    => array(
					'enabled'  => true,
					'interval' => 15,
				),
				'frontend'  => array(
					'enabled'  => true,
					'interval' => 60,
				),
			)
		);

		return $this->format_response(
			true,
			$settings,
			__( 'Heartbeat settings retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Update heartbeat settings.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to HeartbeatController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_heartbeat_settings( $request ) {
		$location = $request->get_param( 'location' );
		$enabled  = $request->get_param( 'enabled' );
		$interval = $request->get_param( 'interval' );

		$settings = get_option(
			'wpha_heartbeat_settings',
			array(
				'dashboard' => array(
					'enabled'  => true,
					'interval' => 60,
				),
				'editor'    => array(
					'enabled'  => true,
					'interval' => 15,
				),
				'frontend'  => array(
					'enabled'  => true,
					'interval' => 60,
				),
			)
		);

		$settings[ $location ] = array(
			'enabled'  => (bool) $enabled,
			'interval' => $interval ? absint( $interval ) : $settings[ $location ]['interval'],
		);

		update_option( 'wpha_heartbeat_settings', $settings );

		return $this->format_response(
			true,
			$settings,
			__( 'Heartbeat settings updated successfully.', 'wp-admin-health-suite' )
		);
	}
}
