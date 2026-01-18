<?php
/**
 * Performance Stats REST Controller
 *
 * Handles performance score calculation and recommendations.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Application\Performance\RunHealthCheck;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for performance stats and recommendations.
 *
 * Provides endpoints for:
 * - Performance score calculation
 * - Performance recommendations
 *
 * @since 1.3.0
 * @since 1.4.0 Updated to use RunHealthCheck application service.
 */
class PerformanceStatsController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/stats';

	/**
	 * Health check application service.
	 *
	 * @since 1.4.0
	 * @var RunHealthCheck
	 */
	protected RunHealthCheck $health_check;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Added RunHealthCheck dependency.
	 *
	 * @param SettingsInterface   $settings     Settings instance.
	 * @param ConnectionInterface $connection   Database connection instance.
	 * @param RunHealthCheck      $health_check Health check application service.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		RunHealthCheck $health_check
	) {
		parent::__construct( $settings, $connection );
		$this->health_check = $health_check;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/stats.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_performance_stats' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/score (legacy alias for /performance/stats).
		register_rest_route(
			$this->namespace,
			'/performance/score',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_performance_stats' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/recommendations.
		register_rest_route(
			$this->namespace,
			'/performance/recommendations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_recommendations' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get performance stats overview.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PerformanceStatsController.
	 * @since 1.4.0 Delegates to RunHealthCheck application service.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_performance_stats( $request ) {
		// Use the application service to get a quick performance score.
		$quick_score = $this->health_check->get_quick_score();

		$response_data = array(
			'score'         => $quick_score['score'],
			'grade'         => $quick_score['grade'],
			'plugin_count'  => $quick_score['plugin_count'],
			'autoload_size' => $quick_score['autoload_size'],
			'query_count'   => $quick_score['query_count'],
			'object_cache'  => $quick_score['object_cache'],
			'timestamp'     => time(),
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Performance score retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get performance recommendations.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PerformanceStatsController.
	 * @since 1.4.0 Delegates to RunHealthCheck application service.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_recommendations( $request ) {
		// Run checks to get recommendations from the application service.
		$autoload_results = $this->health_check->check_autoload();
		$cache_results    = $this->health_check->check_cache();

		$recommendations = array();

		// Check plugin count.
		$plugin_count = count( get_option( 'active_plugins', array() ) );
		if ( $plugin_count > 20 ) {
			$recommendations[] = array(
				'type'        => 'warning',
				'title'       => __( 'Too Many Plugins', 'wp-admin-health-suite' ),
				'description' => sprintf(
					/* translators: %d: number of active plugins */
					__( 'You have %d active plugins. Consider deactivating unused plugins to improve performance.', 'wp-admin-health-suite' ),
					$plugin_count
				),
				'action'      => 'review_plugins',
			);
		}

		// Check autoload size using results from application service.
		$autoload_size = $autoload_results['total_size'] ?? 0;
		$autoload_mb   = $autoload_size / 1024 / 1024;
		if ( $autoload_mb > 0.8 ) {
			$recommendations[] = array(
				'type'        => 'warning',
				'title'       => __( 'Large Autoload Data', 'wp-admin-health-suite' ),
				'description' => sprintf(
					/* translators: %s: autoload size in MB */
					__( 'Your autoload data is %.2f MB. Consider cleaning up unused options.', 'wp-admin-health-suite' ),
					$autoload_mb
				),
				'action'      => 'optimize_autoload',
			);
		}

		// Check object cache using results from application service.
		if ( empty( $cache_results['object_cache_enabled'] ) ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable Object Caching', 'wp-admin-health-suite' ),
				'description' => __( 'Consider implementing an object cache (Redis, Memcached) to improve database performance.', 'wp-admin-health-suite' ),
				'action'      => 'enable_object_cache',
			);
		}

		// Check OPcache using results from application service.
		if ( empty( $cache_results['opcache_enabled'] ) ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable OPcache', 'wp-admin-health-suite' ),
				'description' => __( 'OPcache can significantly improve PHP performance by caching compiled scripts.', 'wp-admin-health-suite' ),
				'action'      => 'enable_opcache',
			);
		}

		// Add cache recommendations from the application service.
		if ( ! empty( $cache_results['recommendations'] ) ) {
			foreach ( $cache_results['recommendations'] as $rec ) {
				$recommendations[] = array(
					'type'        => $rec['type'] ?? 'info',
					'title'       => $rec['title'] ?? '',
					'description' => $rec['message'] ?? '',
					'action'      => $rec['action'] ?? '',
					'priority'    => $rec['priority'] ?? 'medium',
				);
			}
		}

		return $this->format_response(
			true,
			array( 'recommendations' => $recommendations ),
			__( 'Recommendations retrieved successfully.', 'wp-admin-health-suite' )
		);
	}
}
