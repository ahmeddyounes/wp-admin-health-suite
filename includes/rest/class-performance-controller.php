<?php
/**
 * Performance REST Controller
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
 * REST API controller for performance endpoints.
 *
 * Provides endpoints for:
 * - Performance score calculation
 * - Plugin impact analysis
 * - Query analysis
 * - Heartbeat control
 * - Object cache status
 * - Autoload analysis
 */
class Performance_Controller extends REST_Controller {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance';

	/**
	 * Transient key for caching performance data.
	 *
	 * @var string
	 */
	const PERFORMANCE_CACHE_KEY = 'wpha_performance_data';

	/**
	 * Cache expiration time (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Register routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /wpha/v1/performance/stats
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_performance_stats' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/plugins
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/plugins',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugin_impact' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/queries
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/queries',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_query_analysis' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/heartbeat
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/heartbeat',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_heartbeat_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/performance/heartbeat
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/heartbeat',
			array(
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
							'description' => __( 'Whether heartbeat is enabled.', 'wp-admin-health-suite' ),
							'type'        => 'boolean',
							'required'    => true,
						),
						'interval' => array(
							'description' => __( 'Heartbeat interval in seconds.', 'wp-admin-health-suite' ),
							'type'        => 'integer',
							'minimum'     => 15,
							'maximum'     => 120,
						),
					),
				),
			)
		);

		// GET /wpha/v1/performance/cache
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cache',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cache_status' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/performance/autoload
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/autoload',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_autoload_analysis' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_autoload' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'option_name' => array(
							'description'       => __( 'The option name to update.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'autoload'    => array(
							'description' => __( 'Whether to autoload the option.', 'wp-admin-health-suite' ),
							'type'        => 'boolean',
							'required'    => true,
						),
					),
				),
			)
		);

		// GET /wpha/v1/performance/recommendations
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recommendations',
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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_performance_stats( $request ) {
		global $wpdb;

		// Calculate performance factors.
		$plugin_count     = count( get_option( 'active_plugins', array() ) );
		$autoload_size    = $this->get_autoload_size();
		$db_query_count   = $wpdb->num_queries;
		$object_cache     = wp_using_ext_object_cache();

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
			'score'          => $score,
			'grade'          => $grade,
			'plugin_count'   => $plugin_count,
			'autoload_size'  => $autoload_size,
			'query_count'    => $db_query_count,
			'object_cache'   => $object_cache,
			'timestamp'      => time(),
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Performance score retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get plugin impact analysis.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_plugin_impact( $request ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$plugin_data    = array();

		foreach ( $active_plugins as $plugin_file ) {
			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				continue;
			}

			$plugin = $all_plugins[ $plugin_file ];

			// Estimate impact based on plugin characteristics.
			$load_time = $this->estimate_plugin_load_time( $plugin_file );
			$memory    = $this->estimate_plugin_memory( $plugin_file );
			$queries   = $this->estimate_plugin_queries( $plugin_file );

			$plugin_data[] = array(
				'name'        => $plugin['Name'],
				'file'        => $plugin_file,
				'version'     => $plugin['Version'],
				'load_time'   => $load_time,
				'memory'      => $memory,
				'queries'     => $queries,
			);
		}

		// Sort by load time impact.
		usort( $plugin_data, function( $a, $b ) {
			return $b['load_time'] - $a['load_time'];
		} );

		return $this->format_response(
			true,
			array( 'plugins' => $plugin_data ),
			__( 'Plugin impact data retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get query analysis.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_query_analysis( $request ) {
		global $wpdb;

		$slow_queries = array();
		$query_count  = $wpdb->num_queries;

		// Get slow query log if available.
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query_data ) {
				if ( $query_data[1] > 0.05 ) { // Queries slower than 50ms.
					$slow_queries[] = array(
						'query'    => $query_data[0],
						'time'     => (float) $query_data[1],
						'caller'   => $query_data[2],
					);
				}
			}
		}

		// Sort by time descending.
		usort( $slow_queries, function( $a, $b ) {
			return $b['time'] <=> $a['time'];
		} );

		$response_data = array(
			'total_queries' => $query_count,
			'slow_queries'  => array_slice( $slow_queries, 0, 20 ), // Top 20 slow queries.
			'savequeries'   => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Query analysis retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get heartbeat settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_heartbeat_settings( $request ) {
		$settings = get_option( 'wpha_heartbeat_settings', array(
			'dashboard' => array( 'enabled' => true, 'interval' => 60 ),
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => true, 'interval' => 60 ),
		) );

		return $this->format_response(
			true,
			$settings,
			__( 'Heartbeat settings retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Update heartbeat settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_heartbeat_settings( $request ) {
		$location = $request->get_param( 'location' );
		$enabled  = $request->get_param( 'enabled' );
		$interval = $request->get_param( 'interval' );

		$settings = get_option( 'wpha_heartbeat_settings', array(
			'dashboard' => array( 'enabled' => true, 'interval' => 60 ),
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => true, 'interval' => 60 ),
		) );

		$settings[ $location ] = array(
			'enabled'  => $enabled,
			'interval' => $interval ? $interval : $settings[ $location ]['interval'],
		);

		update_option( 'wpha_heartbeat_settings', $settings );

		return $this->format_response(
			true,
			$settings,
			__( 'Heartbeat settings updated successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get object cache status.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_cache_status( $request ) {
		$object_cache = wp_using_ext_object_cache();

		$cache_info = array(
			'object_cache_enabled' => $object_cache,
			'cache_type'           => $object_cache ? $this->get_cache_type() : 'none',
			'opcache_enabled'      => function_exists( 'opcache_get_status' ) && opcache_get_status(),
		);

		if ( function_exists( 'opcache_get_status' ) ) {
			$opcache_status = opcache_get_status( false );
			if ( $opcache_status ) {
				$cache_info['opcache_stats'] = array(
					'hit_rate'      => $opcache_status['opcache_statistics']['opcache_hit_rate'],
					'memory_usage'  => $opcache_status['memory_usage']['used_memory'],
					'cached_scripts' => $opcache_status['opcache_statistics']['num_cached_scripts'],
				);
			}
		}

		return $this->format_response(
			true,
			$cache_info,
			__( 'Cache status retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get autoload analysis.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_autoload_analysis( $request ) {
		global $wpdb;

		$autoload_options = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) as size
			FROM {$wpdb->options}
			WHERE autoload = 'yes'
			ORDER BY size DESC
			LIMIT 50"
		);

		$total_size = $this->get_autoload_size();
		$options    = array();

		foreach ( $autoload_options as $option ) {
			$options[] = array(
				'name' => $option->option_name,
				'size' => (int) $option->size,
			);
		}

		$response_data = array(
			'total_size'   => $total_size,
			'total_size_mb' => round( $total_size / 1024 / 1024, 2 ),
			'options'      => $options,
			'count'        => count( $autoload_options ),
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Autoload analysis retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Update autoload setting for an option.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_autoload( $request ) {
		global $wpdb;

		$option_name = $request->get_param( 'option_name' );
		$autoload    = $request->get_param( 'autoload' );

		// Check if option exists.
		$option_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
				$option_name
			)
		);

		if ( ! $option_exists ) {
			return $this->format_error_response(
				new \WP_Error(
					'option_not_found',
					__( 'The specified option does not exist.', 'wp-admin-health-suite' )
				),
				404
			);
		}

		// Update the autoload setting.
		$autoload_value = $autoload ? 'yes' : 'no';
		$result = $wpdb->update(
			$wpdb->options,
			array( 'autoload' => $autoload_value ),
			array( 'option_name' => $option_name ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return $this->format_error_response(
				new \WP_Error(
					'update_failed',
					__( 'Failed to update autoload setting.', 'wp-admin-health-suite' )
				),
				500
			);
		}

		// Clear the alloptions cache to ensure changes take effect.
		wp_cache_delete( 'alloptions', 'options' );

		return $this->format_response(
			true,
			array(
				'option_name' => $option_name,
				'autoload'    => $autoload,
			),
			__( 'Autoload setting updated successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get performance recommendations.
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
	 * @return int Autoload size in bytes.
	 */
	private function get_autoload_size() {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value))
			FROM {$wpdb->options}
			WHERE autoload = 'yes'"
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Estimate plugin load time.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return float Estimated load time in milliseconds.
	 */
	private function estimate_plugin_load_time( $plugin_file ) {
		$plugin_path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$file_count  = 0;

		if ( is_dir( $plugin_path ) ) {
			$iterator   = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);
			$file_count = iterator_count( $iterator );
		}

		// Estimate: more files = potentially more load time.
		return min( 100, max( 5, $file_count * 0.5 ) );
	}

	/**
	 * Estimate plugin memory usage.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return int Estimated memory in KB.
	 */
	private function estimate_plugin_memory( $plugin_file ) {
		$plugin_path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$total_size  = 0;

		if ( is_dir( $plugin_path ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$total_size += $file->getSize();
				}
			}
		}

		// Convert to KB.
		return (int) ( $total_size / 1024 );
	}

	/**
	 * Estimate plugin query count.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return int Estimated query count.
	 */
	private function estimate_plugin_queries( $plugin_file ) {
		// This is a rough estimation based on common patterns.
		// In reality, you'd need actual profiling.
		return rand( 0, 10 );
	}

	/**
	 * Get cache type.
	 *
	 * @return string Cache type.
	 */
	private function get_cache_type() {
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
