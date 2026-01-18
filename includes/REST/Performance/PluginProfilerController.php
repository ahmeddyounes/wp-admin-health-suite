<?php
/**
 * Plugin Profiler REST Controller
 *
 * Handles plugin impact analysis and profiling.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Application\Performance\GetPluginImpact;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for plugin profiling endpoints.
 *
 * Provides endpoints for analyzing plugin impact on performance.
 *
 * @since 1.3.0
 */
class PluginProfilerController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/plugins';

	/**
	 * Plugin impact use-case.
	 *
	 * @since 1.7.0
	 * @var GetPluginImpact
	 */
	private GetPluginImpact $get_plugin_impact;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface   $settings          Settings instance.
	 * @param ConnectionInterface $connection        Database connection instance.
	 * @param GetPluginImpact     $get_plugin_impact Plugin impact use-case.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		GetPluginImpact $get_plugin_impact
	) {
		parent::__construct( $settings, $connection );
		$this->get_plugin_impact = $get_plugin_impact;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/plugins.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugin_impact' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get plugin impact analysis.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PluginProfilerController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_plugin_impact( $request ) {
		$data = $this->get_plugin_impact->execute();

		return $this->format_response(
			true,
			$data,
			__( 'Plugin impact data retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

}
