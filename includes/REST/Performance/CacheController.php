<?php
/**
 * Cache REST Controller
 *
 * Handles object cache status and information.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Application\Performance\GetCacheStatus;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for cache status endpoints.
 *
 * Provides endpoints for checking object cache and OPcache status.
 *
 * @since 1.3.0
 */
class CacheController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/cache';

	/**
	 * Cache status use-case.
	 *
	 * @since 1.7.0
	 * @var GetCacheStatus
	 */
	private GetCacheStatus $get_cache_status;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface   $settings         Settings instance.
	 * @param ConnectionInterface $connection       Database connection instance.
	 * @param GetCacheStatus      $get_cache_status Cache status use-case.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		GetCacheStatus $get_cache_status
	) {
		parent::__construct( $settings, $connection );
		$this->get_cache_status = $get_cache_status;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/cache.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cache_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get object cache status.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to CacheController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_cache_status( $request ) {
		$cache_info = $this->get_cache_status->execute();

		return $this->format_response(
			true,
			$cache_info,
			__( 'Cache status retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

}
