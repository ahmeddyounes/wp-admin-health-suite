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
 */
class PerformanceStatsController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/stats';

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
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_performance_stats( $request ) {
		$connection = $this->get_connection();

		// Calculate performance factors.
		$plugin_count   = count( get_option( 'active_plugins', array() ) );
		$autoload_size  = $this->get_autoload_size();
		$db_query_count = $connection->get_num_queries();
		$object_cache   = wp_using_ext_object_cache();

		// Calculate score (0-100).
		$score = 100;

		// Deduct points for high plugin count.
		if ( $plugin_count > 30 ) {
			$score -= 20;
		} elseif ( $plugin_count > 20 ) {
			$score -= 10;
		} elseif ( $plugin_count > 10 ) {
			$score -= 5;
		}

		// Deduct points for large autoload size.
		$autoload_mb = $autoload_size / 1024 / 1024;
		if ( $autoload_mb > 1 ) {
			$score -= 15;
		} elseif ( $autoload_mb > 0.5 ) {
			$score -= 10;
		}

		// Add points for object cache.
		if ( ! $object_cache ) {
			$score -= 15;
		}

		// Deduct points for high query count.
		if ( $db_query_count > 100 ) {
			$score -= 10;
		} elseif ( $db_query_count > 50 ) {
			$score -= 5;
		}

		$score = max( 0, min( 100, $score ) );

		// Determine grade.
		if ( $score >= 90 ) {
			$grade = 'A';
		} elseif ( $score >= 80 ) {
			$grade = 'B';
		} elseif ( $score >= 70 ) {
			$grade = 'C';
		} elseif ( $score >= 60 ) {
			$grade = 'D';
		} else {
			$grade = 'F';
		}

		$response_data = array(
			'score'         => $score,
			'grade'         => $grade,
			'plugin_count'  => $plugin_count,
			'autoload_size' => $autoload_size,
			'query_count'   => $db_query_count,
			'object_cache'  => $object_cache,
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
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_recommendations( $request ) {
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

		// Check autoload size.
		$autoload_size = $this->get_autoload_size();
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

		// Check object cache.
		if ( ! wp_using_ext_object_cache() ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable Object Caching', 'wp-admin-health-suite' ),
				'description' => __( 'Consider implementing an object cache (Redis, Memcached) to improve database performance.', 'wp-admin-health-suite' ),
				'action'      => 'enable_object_cache',
			);
		}

		// Check OPcache.
		if ( ! function_exists( 'opcache_get_status' ) || ! opcache_get_status() ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable OPcache', 'wp-admin-health-suite' ),
				'description' => __( 'OPcache can significantly improve PHP performance by caching compiled scripts.', 'wp-admin-health-suite' ),
				'action'      => 'enable_opcache',
			);
		}

		return $this->format_response(
			true,
			array( 'recommendations' => $recommendations ),
			__( 'Recommendations retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get total autoload size.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PerformanceStatsController.
	 *
	 * @return int Autoload size in bytes.
	 */
	private function get_autoload_size(): int {
		$connection    = $this->get_connection();
		$options_table = $connection->get_options_table();

		$result = $connection->get_var(
			"SELECT SUM(LENGTH(option_value))
			FROM {$options_table}
			WHERE autoload = 'yes'"
		);

		return $result ? (int) $result : 0;
	}
}
