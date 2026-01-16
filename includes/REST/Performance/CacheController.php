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
		$object_cache = wp_using_ext_object_cache();

		$opcache_status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;

		$cache_info = array(
			'object_cache_enabled' => $object_cache,
			'cache_type'           => $object_cache ? $this->get_cache_type() : 'none',
			'opcache_enabled'      => (bool) $opcache_status,
		);

		if ( $opcache_status ) {
			$cache_info['opcache_stats'] = array(
				'hit_rate'       => $opcache_status['opcache_statistics']['opcache_hit_rate'],
				'memory_usage'   => $opcache_status['memory_usage']['used_memory'],
				'cached_scripts' => $opcache_status['opcache_statistics']['num_cached_scripts'],
			);
		}

		return $this->format_response(
			true,
			$cache_info,
			__( 'Cache status retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get cache type.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to CacheController.
	 *
	 * @return string Cache type.
	 */
	private function get_cache_type(): string {
		global $wp_object_cache;

		if ( isset( $wp_object_cache ) && is_object( $wp_object_cache ) ) {
			$class = get_class( $wp_object_cache );

			if ( strpos( $class, 'Redis' ) !== false ) {
				return 'Redis';
			} elseif ( strpos( $class, 'Memcached' ) !== false ) {
				return 'Memcached';
			} elseif ( strpos( $class, 'APCu' ) !== false ) {
				return 'APCu';
			}
		}

		return 'Unknown';
	}
}
