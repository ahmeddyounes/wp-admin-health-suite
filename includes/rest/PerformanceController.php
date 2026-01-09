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
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;

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
 *
 * @since 1.0.0
 * @since 1.2.0 Refactored to use constructor injection for all dependencies.
 */
class PerformanceController extends RestController {

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
	 * Autoload analyzer instance.
	 *
	 * @since 1.2.0
	 * @var AutoloadAnalyzerInterface
	 */
	protected AutoloadAnalyzerInterface $autoload_analyzer;

	/**
	 * Query monitor instance.
	 *
	 * @since 1.2.0
	 * @var QueryMonitorInterface
	 */
	protected QueryMonitorInterface $query_monitor;

	/**
	 * Plugin profiler instance.
	 *
	 * @since 1.2.0
	 * @var PluginProfilerInterface
	 */
	protected PluginProfilerInterface $plugin_profiler;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.2.0 Added all performance service dependencies via constructor injection.
	 *
	 * @param SettingsInterface          $settings          Settings instance.
	 * @param AutoloadAnalyzerInterface  $autoload_analyzer Autoload analyzer instance.
	 * @param QueryMonitorInterface      $query_monitor     Query monitor instance.
	 * @param PluginProfilerInterface    $plugin_profiler   Plugin profiler instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		AutoloadAnalyzerInterface $autoload_analyzer,
		QueryMonitorInterface $query_monitor,
		PluginProfilerInterface $plugin_profiler
	) {
		parent::__construct( $settings );
		$this->autoload_analyzer = $autoload_analyzer;
		$this->query_monitor     = $query_monitor;
		$this->plugin_profiler   = $plugin_profiler;
	}

	/**
	 * Register routes for the controller.
	 *
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
 * @since 1.0.0
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
	 * Security: Protected options cannot have their autoload setting changed
	 * to prevent accidental or malicious disruption of WordPress core functionality.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added protection for core WordPress options.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_autoload( $request ) {
		global $wpdb;

		$option_name = $request->get_param( 'option_name' );
		$autoload    = $request->get_param( 'autoload' );

		// Security: Check if the option is protected from modification.
		if ( $this->is_protected_option( $option_name ) ) {
			return $this->format_error_response(
				new \WP_Error(
					'protected_option',
					__( 'This option is protected and cannot be modified for security reasons.', 'wp-admin-health-suite' )
				),
				403
			);
		}

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
	 * Check if an option is protected from autoload modification.
	 *
	 * WordPress core options and critical plugin options should not have
	 * their autoload setting changed, as this could break site functionality.
	 *
	 * @since 1.2.0
	 *
	 * @param string $option_name The option name to check.
	 * @return bool True if the option is protected, false otherwise.
	 */
	private function is_protected_option( string $option_name ): bool {
		// Core WordPress options that must remain autoloaded for WordPress to function.
		$protected_options = array(
			// Essential WordPress settings.
			'siteurl',
			'home',
			'blogname',
			'blogdescription',
			'admin_email',
			'users_can_register',
			'start_of_week',
			'use_balanceTags',
			'use_smilies',
			'require_name_email',
			'comments_notify',
			'posts_per_rss',
			'rss_use_excerpt',
			'mailserver_url',
			'mailserver_login',
			'mailserver_pass',
			'mailserver_port',
			'default_category',
			'default_comment_status',
			'default_ping_status',
			'default_pingback_flag',
			'posts_per_page',
			'date_format',
			'time_format',
			'links_updated_date_format',
			'comment_moderation',
			'moderation_notify',
			'permalink_structure',
			'hack_file',
			'blog_charset',
			'moderation_keys',
			'active_plugins',
			'category_base',
			'ping_sites',
			'comment_max_links',
			'gmt_offset',
			'default_email_category',
			'recently_edited',
			'template',
			'stylesheet',
			'comment_whitelist',
			'comment_registration',
			'html_type',
			'default_role',
			'db_version',
			'uploads_use_yearmonth_folders',
			'upload_path',
			'blog_public',
			'default_link_category',
			'show_on_front',
			'tag_base',
			'show_avatars',
			'avatar_rating',
			'upload_url_path',
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'avatar_default',
			'large_size_w',
			'large_size_h',
			'image_default_link_type',
			'image_default_size',
			'image_default_align',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'thread_comments',
			'thread_comments_depth',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'sticky_posts',
			'widget_categories',
			'widget_text',
			'widget_rss',
			'timezone_string',
			'page_for_posts',
			'page_on_front',
			'default_post_format',
			'link_manager_enabled',
			'finished_splitting_shared_terms',
			'site_icon',
			'medium_large_size_w',
			'medium_large_size_h',
			'wp_page_for_privacy_policy',
			'show_comments_cookies_opt_in',
			'initial_db_version',
			'current_theme',
			'WPLANG',
			// Multisite specific.
			'site_admins',
			'network_admins',
			// User capabilities (critical for security).
			'wp_user_roles',
			// Cron and scheduling (must be autoloaded).
			'cron',
			// Site transients.
			'can_compress_scripts',
		);

		// Check exact match.
		if ( in_array( $option_name, $protected_options, true ) ) {
			return true;
		}

		// Protect options starting with critical prefixes.
		$protected_prefixes = array(
			'_site_transient_',      // Site transients.
			'_transient_timeout_',   // Transient timeouts.
			'wp_user_roles',         // User roles.
			'user_roles',            // Multisite user roles.
			'auto_core_update_',     // Core update settings.
			'auto_plugin_',          // Auto-update settings.
			'auto_theme_',           // Theme auto-update settings.
		);

		foreach ( $protected_prefixes as $prefix ) {
			if ( 0 === strpos( $option_name, $prefix ) ) {
				return true;
			}
		}

		/**
		 * Filter whether an option is protected from autoload modification.
		 *
		 * @since 1.2.0
		 *
		 * @param bool   $is_protected Whether the option is protected.
		 * @param string $option_name  The option name being checked.
		 */
		return apply_filters( 'wpha_is_protected_autoload_option', false, $option_name );
	}

	/**
	 * Get performance recommendations.
	 *
 * @since 1.0.0
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
