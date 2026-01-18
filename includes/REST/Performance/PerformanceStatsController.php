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
use WPAdminHealth\Application\Performance\GetRecommendations;
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
	 * Recommendations use-case.
	 *
	 * @since 1.7.0
	 * @var GetRecommendations
	 */
	protected GetRecommendations $get_recommendations;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Added RunHealthCheck dependency.
	 *
	 * @param SettingsInterface   $settings            Settings instance.
	 * @param ConnectionInterface $connection          Database connection instance.
	 * @param RunHealthCheck      $health_check        Health check application service.
	 * @param GetRecommendations  $get_recommendations Recommendations use-case.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		RunHealthCheck $health_check,
		GetRecommendations $get_recommendations
	) {
		parent::__construct( $settings, $connection );
		$this->health_check        = $health_check;
		$this->get_recommendations = $get_recommendations;
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
		$data = $this->get_recommendations->execute();

		return $this->format_response(
			true,
			$data,
			__( 'Recommendations retrieved successfully.', 'wp-admin-health-suite' )
		);
	}
}
