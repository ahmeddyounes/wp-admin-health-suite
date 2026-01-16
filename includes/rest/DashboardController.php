<?php
/**
 * Dashboard REST Controller
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\HealthCalculator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for dashboard endpoints.
 *
 * Provides endpoints for:
 * - Dashboard statistics with caching
 * - Health score calculations
 * - Activity log retrieval with pagination
 * - Quick action execution
 *
 * @since 1.0.0
 */
class DashboardController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'dashboard';

	/**
	 * Transient key for caching dashboard stats.
	 *
	 * @var string
	 */
	const STATS_CACHE_KEY = 'wpha_dashboard_stats';

	/**
	 * Cache expiration time (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Health calculator instance.
	 *
	 * @since 1.1.0
	 * @since 1.2.0 Made non-nullable.
	 * @var HealthCalculator
	 */
	protected HealthCalculator $health_calculator;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.2.0 Made HealthCalculator dependency required.
	 * @since 1.3.0 Added ConnectionInterface dependency.
	 *
	 * @param SettingsInterface   $settings          Settings instance.
	 * @param ConnectionInterface $connection        Database connection instance.
	 * @param HealthCalculator    $health_calculator Health calculator instance.
	 */
	public function __construct( SettingsInterface $settings, ConnectionInterface $connection, HealthCalculator $health_calculator ) {
		parent::__construct( $settings, $connection );
		$this->health_calculator = $health_calculator;
	}

	/**
	 * Get the health calculator instance.
	 *
	 * @since 1.1.0
	 *
	 * @return HealthCalculator The health calculator instance.
	 */
	protected function get_health_calculator(): HealthCalculator {
		return $this->health_calculator;
	}

	/**
	 * Register routes for the controller.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function register_routes() {
		// GET /wpha/v1/dashboard/stats.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/dashboard/health-score.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health-score',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_health_score' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/dashboard/activity.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activity',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_activity' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_activity_params(),
				),
			)
		);

		// POST /wpha/v1/dashboard/quick-action.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/quick-action',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'execute_quick_action' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'action_id' => array(
							'description'       => __( 'The ID of the action to execute.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get dashboard statistics.
	 *
	 * Returns aggregated dashboard metrics including:
	 * - Total database size
	 * - Total media count
	 * - Cleanable items count
	 * - Last cleanup date
	 *
	 * Results are cached for 5 minutes to reduce database load.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		// Check cache first.
		$cached_stats = get_transient( self::STATS_CACHE_KEY );
		if ( false !== $cached_stats && is_array( $cached_stats ) ) {
			return $this->format_response(
				true,
				$cached_stats,
				__( 'Dashboard stats retrieved successfully (cached).', 'wp-admin-health-suite' )
			);
		}

		$connection = $this->get_connection();

		// Calculate total database size.
		$total_db_size = $this->calculate_database_size();

		// Calculate total media count.
		$posts_table       = $connection->get_posts_table();
		$total_media_count = $connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = 'attachment'"
		);

		// Calculate cleanable items.
		$cleanable_items = $this->calculate_cleanable_items();

		// Get last cleanup date.
		$last_cleanup_date = $this->get_last_cleanup_date();

		// Build stats array.
		$stats = array(
			'total_db_size'      => $total_db_size,
			'total_media_count'  => (int) $total_media_count,
			'cleanable_items'    => $cleanable_items,
			'last_cleanup_date'  => $last_cleanup_date,
			'cache_timestamp'    => time(),
		);

		// Cache the stats.
		set_transient( self::STATS_CACHE_KEY, $stats, self::CACHE_EXPIRATION );

		return $this->format_response(
			true,
			$stats,
			__( 'Dashboard stats retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get health score and factors.
	 *
	 * Returns the overall health score along with individual factor scores
	 * and recommendations.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_health_score( $request ) {
		$health_calculator = $this->get_health_calculator();

		// Get overall score and factors.
		$health_data = $health_calculator->calculate_overall_score();

		// Get recommendations.
		$recommendations = $health_calculator->get_recommendations();

		// Build response data.
		$response_data = array(
			'score'           => $health_data['score'],
			'grade'           => $health_data['grade'],
			'factors'         => $health_data['factor_scores'],
			'recommendations' => $recommendations,
			'timestamp'       => $health_data['timestamp'],
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Health score retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get paginated activity log.
	 *
	 * Returns activity log entries from the scan history table with pagination support.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activity( $request ) {
		$connection = $this->get_connection();

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		// Calculate offset.
		$offset = ( $page - 1 ) * $per_page;

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return $this->format_response(
				true,
				array(
					'items'       => array(),
					'total'       => 0,
					'total_pages' => 0,
					'current_page' => $page,
					'per_page'    => $per_page,
				),
				__( 'No activities found. Database table does not exist yet.', 'wp-admin-health-suite' )
			);
		}

		// Get total count for pagination.
		$total = $connection->get_var(
			"SELECT COUNT(*) FROM {$table_name}"
		);

		// Fetch paginated activities.
		$query = $connection->prepare(
			"SELECT id, scan_type, items_found, items_cleaned, bytes_freed, created_at
			FROM {$table_name}
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$activities = $query ? $connection->get_results( $query, 'ARRAY_A' ) : null;

		if ( null === $activities ) {
			return $this->format_error_response(
				new WP_Error(
					'database_error',
					__( 'Database error occurred while fetching activities.', 'wp-admin-health-suite' )
				),
				500
			);
		}

		// Format the activities data.
		$formatted_activities = array_map(
			function ( $activity ) {
				return array(
					'id'            => (int) $activity['id'],
					'scan_type'     => sanitize_text_field( $activity['scan_type'] ),
					'items_found'   => (int) $activity['items_found'],
					'items_cleaned' => (int) $activity['items_cleaned'],
					'bytes_freed'   => (int) $activity['bytes_freed'],
					'created_at'    => $activity['created_at'],
				);
			},
			$activities
		);

		// Calculate pagination info.
		$total_pages = ceil( $total / $per_page );

		$response_data = array(
			'items'        => $formatted_activities,
			'total'        => (int) $total,
			'total_pages'  => (int) $total_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Activities retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Execute a quick action.
	 *
	 * Executes a quick action by ID and logs the result to the scan history.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function execute_quick_action( $request ) {
		$connection = $this->get_connection();

		$action_id = $request->get_param( 'action_id' );

		// Execute action based on ID.
		$result = $this->execute_action( $action_id );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		// Log the action to scan history (non-fatal if the table doesn't exist yet).
		$table_name = $connection->get_prefix() . 'wpha_scan_history';
		$log_id     = null;
		$logged     = false;

		if ( $connection->table_exists( $table_name ) ) {
			$inserted = $connection->insert(
				$table_name,
				array(
					'scan_type'     => $result['scan_type'],
					'items_found'   => $result['items_found'],
					'items_cleaned' => $result['items_cleaned'],
					'bytes_freed'   => $result['bytes_freed'],
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%d', '%s' )
			);

			if ( false === $inserted ) {
				return $this->format_error_response(
					new WP_Error(
						'database_error',
						__( 'Failed to log action to database.', 'wp-admin-health-suite' )
					),
					500
				);
			}

			$log_id = $connection->get_insert_id();
			$logged = true;
		}

		// Clear the stats cache since data has changed.
		delete_transient( self::STATS_CACHE_KEY );

		// Clear health score cache since data has changed.
		$health_calculator = $this->get_health_calculator();
		$health_calculator->clear_cache();

		$message = sprintf(
			/* translators: %d: number of items cleaned */
			__( 'Action executed successfully. %d items cleaned.', 'wp-admin-health-suite' ),
			$result['items_cleaned']
		);

		if ( ! $logged ) {
			$message .= ' ' . __( 'Warning: activity log table not found, so this action was not logged.', 'wp-admin-health-suite' );
		}

		return $this->format_response(
			true,
			array(
				'action_id'     => $action_id,
				'items_cleaned' => $result['items_cleaned'],
				'bytes_freed'   => $result['bytes_freed'],
				'log_id'        => $log_id,
				'logged'        => $logged,
			),
			$message
		);
	}

	/**
	 * Execute action based on action ID.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $action_id The action ID to execute.
	 * @return array|WP_Error Result array or WP_Error on failure.
	 */
	private function execute_action( $action_id ) {
		$connection     = $this->get_connection();
		$posts_table    = $connection->get_posts_table();
		$comments_table = $connection->get_comments_table();
		$options_table  = $connection->get_options_table();

		switch ( $action_id ) {
			case 'delete_trash':
				$delete_limit = 200;
				$items_found  = $connection->get_var(
					"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'trash'"
				);
				$query    = $connection->prepare(
					"SELECT ID FROM {$posts_table} WHERE post_status = 'trash' LIMIT %d",
					$delete_limit
				);
				$post_ids = $query ? $connection->get_col( $query ) : array();

				$deleted = 0;
				foreach ( $post_ids as $post_id ) {
					if ( wp_delete_post( (int) $post_id, true ) ) {
						$deleted++;
					}
				}

				return array(
					'scan_type'     => 'delete_trash',
					'items_found'   => (int) $items_found,
					'items_cleaned' => $deleted,
					'bytes_freed'   => 0,
				);

			case 'delete_spam':
				$delete_limit = 200;
				$items_found  = $connection->get_var(
					"SELECT COUNT(*) FROM {$comments_table} WHERE comment_approved = 'spam'"
				);
				$query       = $connection->prepare(
					"SELECT comment_ID FROM {$comments_table} WHERE comment_approved = 'spam' LIMIT %d",
					$delete_limit
				);
				$comment_ids = $query ? $connection->get_col( $query ) : array();

				$deleted = 0;
				foreach ( $comment_ids as $comment_id ) {
					if ( wp_delete_comment( (int) $comment_id, true ) ) {
						$deleted++;
					}
				}

				return array(
					'scan_type'     => 'delete_spam',
					'items_found'   => (int) $items_found,
					'items_cleaned' => $deleted,
					'bytes_freed'   => 0,
				);

			case 'delete_auto_drafts':
				$delete_limit = 200;
				$items_found  = $connection->get_var(
					"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'auto-draft'"
				);
				$query    = $connection->prepare(
					"SELECT ID FROM {$posts_table} WHERE post_status = 'auto-draft' LIMIT %d",
					$delete_limit
				);
				$post_ids = $query ? $connection->get_col( $query ) : array();

				$deleted = 0;
				foreach ( $post_ids as $post_id ) {
					if ( wp_delete_post( (int) $post_id, true ) ) {
						$deleted++;
					}
				}

				return array(
					'scan_type'     => 'delete_auto_drafts',
					'items_found'   => (int) $items_found,
					'items_cleaned' => $deleted,
					'bytes_freed'   => 0,
				);

			case 'clean_expired_transients':
				$count_query = $connection->prepare(
					"SELECT COUNT(*) FROM {$options_table} t1
					INNER JOIN {$options_table} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
					WHERE t1.option_name LIKE %s
					AND t1.option_value < %d",
					$connection->esc_like( '_transient_timeout_' ) . '%',
					time()
				);
				$items_found = $count_query ? $connection->get_var( $count_query ) : 0;

				// Delete expired transients.
				$delete_query = $connection->prepare(
					"DELETE t1, t2 FROM {$options_table} t1
					LEFT JOIN {$options_table} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
					WHERE t1.option_name LIKE %s
					AND t1.option_value < %d",
					$connection->esc_like( '_transient_timeout_' ) . '%',
					time()
				);
				$deleted = $delete_query ? $connection->query( $delete_query ) : 0;

				return array(
					'scan_type'     => 'clean_expired_transients',
					'items_found'   => (int) $items_found,
					'items_cleaned' => (int) $deleted,
					'bytes_freed'   => 0,
				);

			case 'optimize_tables':
				// Get all tables using INFORMATION_SCHEMA for security.
				$query  = $connection->prepare(
					'SELECT table_name
					FROM information_schema.TABLES
					WHERE table_schema = %s
					AND table_name LIKE %s',
					DB_NAME,
					$connection->esc_like( $connection->get_prefix() ) . '%'
				);
				$tables = $query ? $connection->get_col( $query ) : array();

				$optimized = 0;

				foreach ( $tables as $table_name ) {
					// Validate table name format (only alphanumeric, underscores allowed).
					if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
						continue;
					}
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders, esc_sql and backticks used.
					$connection->query( 'OPTIMIZE TABLE `' . esc_sql( $table_name ) . '`' );
					$optimized++;
				}

				return array(
					'scan_type'     => 'optimize_tables',
					'items_found'   => count( $tables ),
					'items_cleaned' => $optimized,
					'bytes_freed'   => 0,
				);

			default:
				return new WP_Error(
					'invalid_action',
					sprintf(
						/* translators: %s: action ID */
						__( 'Invalid action ID: %s', 'wp-admin-health-suite' ),
						$action_id
					)
				);
		}
	}

	/**
	 * Calculate total database size in bytes.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return int Database size in bytes.
	 */
	private function calculate_database_size() {
		$connection = $this->get_connection();

		$database = DB_NAME;

		$query = $connection->prepare(
			'SELECT SUM(data_length + index_length) AS size
			FROM information_schema.TABLES
			WHERE table_schema = %s',
			$database
		);

		$result = $query ? $connection->get_row( $query, 'OBJECT' ) : null;

		return ( $result && is_object( $result ) && isset( $result->size ) ) ? (int) $result->size : 0;
	}

	/**
	 * Calculate total number of cleanable items.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return int Total cleanable items count.
	 */
	private function calculate_cleanable_items() {
		$connection      = $this->get_connection();
		$posts_table     = $connection->get_posts_table();
		$comments_table  = $connection->get_comments_table();
		$options_table   = $connection->get_options_table();
		$postmeta_table  = $connection->get_postmeta_table();

		$cleanable = 0;

		// Trashed posts.
		$cleanable += (int) $connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'trash'"
		);

		// Spam comments.
		$cleanable += (int) $connection->get_var(
			"SELECT COUNT(*) FROM {$comments_table} WHERE comment_approved = 'spam'"
		);

		// Auto-drafts.
		$cleanable += (int) $connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'auto-draft'"
		);

		// Expired transients.
		$transients_query = $connection->prepare(
			"SELECT COUNT(*) FROM {$options_table} t1
			INNER JOIN {$options_table} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
			WHERE t1.option_name LIKE %s
			AND t1.option_value < %d",
			$connection->esc_like( '_transient_timeout_' ) . '%',
			time()
		);
		$cleanable += $transients_query ? (int) $connection->get_var( $transients_query ) : 0;

		// Orphaned postmeta.
		$cleanable += (int) $connection->get_var(
			"SELECT COUNT(*) FROM {$postmeta_table} pm
			LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);

		return $cleanable;
	}

	/**
	 * Get the date of the last cleanup action.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return string|null Last cleanup date or null if none found.
	 */
	private function get_last_cleanup_date() {
		$connection = $this->get_connection();

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return null;
		}

		$last_date = $connection->get_var(
			"SELECT created_at FROM {$table_name}
			WHERE items_cleaned > 0
			ORDER BY created_at DESC
			LIMIT 1"
		);

		return $last_date;
	}

	/**
	 * Get activity endpoint parameters.
	 *
 * @since 1.0.0
 *
	 * @return array Collection parameters.
	 */
	public function get_activity_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
