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
use WPAdminHealth\Settings\SettingsRegistry;

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
							'validate_callback' => 'rest_validate_request_arg',
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
		$all_settings = $this->get_settings()->get_settings();
		$settings     = $this->build_heartbeat_settings_from_settings( $all_settings );

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

		$settings_service = $this->get_settings();
		$all_settings     = $settings_service->get_settings();

		$enabled_bool = (bool) $enabled;
		$interval_int = null === $interval ? null : absint( $interval );
		if ( null !== $interval_int ) {
			$interval_int = max( 15, min( 120, $interval_int ) );
		}

		switch ( $location ) {
			case 'dashboard':
				$all_settings['heartbeat_admin_enabled'] = $enabled_bool;
				if ( null !== $interval_int ) {
					$all_settings['heartbeat_admin_frequency'] = $interval_int;
				}
				break;
			case 'editor':
				$all_settings['heartbeat_editor_enabled'] = $enabled_bool;
				if ( null !== $interval_int ) {
					$all_settings['heartbeat_editor_frequency'] = $interval_int;
				}
				break;
			case 'frontend':
				$all_settings['heartbeat_frontend'] = $enabled_bool;
				if ( null !== $interval_int ) {
					$all_settings['heartbeat_frontend_frequency'] = $interval_int;
				}
				break;
		}

		update_option( SettingsRegistry::OPTION_NAME, $all_settings );

		if ( $settings_service instanceof SettingsRegistry ) {
			$settings_service->clear_cache();
		}

		$settings = $this->build_heartbeat_settings_from_settings( $all_settings );

		return $this->format_response(
			true,
			$settings,
			__( 'Heartbeat settings updated successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Build heartbeat settings response from the main settings array.
	 *
	 * @param array $settings Main settings array (wpha_settings).
	 * @return array<string, array{enabled: bool, interval: int}>
	 */
	private function build_heartbeat_settings_from_settings( array $settings ): array {
		$dashboard_interval = isset( $settings['heartbeat_admin_frequency'] ) ? absint( $settings['heartbeat_admin_frequency'] ) : 60;
		$editor_interval    = isset( $settings['heartbeat_editor_frequency'] ) ? absint( $settings['heartbeat_editor_frequency'] ) : 15;
		$frontend_interval  = isset( $settings['heartbeat_frontend_frequency'] ) ? absint( $settings['heartbeat_frontend_frequency'] ) : 60;

		$dashboard_interval = max( 15, min( 120, $dashboard_interval ) );
		$editor_interval    = max( 15, min( 120, $editor_interval ) );
		$frontend_interval  = max( 15, min( 120, $frontend_interval ) );

		return array(
			'dashboard' => array(
				'enabled'  => ! empty( $settings['heartbeat_admin_enabled'] ),
				'interval' => $dashboard_interval,
			),
			'editor'    => array(
				'enabled'  => ! empty( $settings['heartbeat_editor_enabled'] ),
				'interval' => $editor_interval,
			),
			'frontend'  => array(
				'enabled'  => ! empty( $settings['heartbeat_frontend'] ),
				'interval' => $frontend_interval,
			),
		);
	}
}
