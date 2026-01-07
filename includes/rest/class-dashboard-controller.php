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
use WPAdminHealth\Health_Calculator;

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
 */
class Dashboard_Controller extends REST_Controller {

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
	 * Register routes for the controller.
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /wpha/v1/dashboard/stats
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

		// GET /wpha/v1/dashboard/health-score
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

		// GET /wpha/v1/dashboard/activity
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

		// POST /wpha/v1/dashboard/quick-action
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

		global $wpdb;

		// Calculate total database size.
		$total_db_size = $this->calculate_database_size();

		// Calculate total media count.
		$total_media_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_health_score( $request ) {
		$health_calculator = new Health_Calculator();

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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activity( $request ) {
		global $wpdb;

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		// Calculate offset.
		$offset = ( $page - 1 ) * $per_page;

		$table_name = $wpdb->prefix . 'wpha_scan_history';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists !== $table_name ) {
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
		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name}"
		);

		// Fetch paginated activities.
		$activities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, scan_type, items_found, items_cleaned, bytes_freed, created_at
				FROM {$table_name}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

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
			function( $activity ) {
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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function execute_quick_action( $request ) {
		global $wpdb;

		$action_id = $request->get_param( 'action_id' );

		// Execute action based on ID.
		$result = $this->execute_action( $action_id );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		// Log the action to scan history.
		$table_name = $wpdb->prefix . 'wpha_scan_history';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'scan_type'      => $result['scan_type'],
				'items_found'    => $result['items_found'],
				'items_cleaned'  => $result['items_cleaned'],
				'bytes_freed'    => $result['bytes_freed'],
				'created_at'     => current_time( 'mysql' ),
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

		// Clear the stats cache since data has changed.
		delete_transient( self::STATS_CACHE_KEY );

		// Clear health score cache since data has changed.
		$health_calculator = new Health_Calculator();
		$health_calculator->clear_cache();

		return $this->format_response(
			true,
			array(
				'action_id'      => $action_id,
				'items_cleaned'  => $result['items_cleaned'],
				'bytes_freed'    => $result['bytes_freed'],
				'log_id'         => $wpdb->insert_id,
			),
			sprintf(
				/* translators: %d: number of items cleaned */
				__( 'Action executed successfully. %d items cleaned.', 'wp-admin-health-suite' ),
				$result['items_cleaned']
			)
		);
	}

	/**
	 * Execute action based on action ID.
	 *
	 * @param string $action_id The action ID to execute.
	 * @return array|WP_Error Result array or WP_Error on failure.
	 */
	private function execute_action( $action_id ) {
		global $wpdb;

		switch ( $action_id ) {
			case 'delete_trash':
				$items_found = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
				);

				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'"
				);

				return array(
					'scan_type'     => 'delete_trash',
					'items_found'   => (int) $items_found,
					'items_cleaned' => (int) $deleted,
					'bytes_freed'   => 0,
				);

			case 'delete_spam':
				$items_found = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
				);

				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
				);

				return array(
					'scan_type'     => 'delete_spam',
					'items_found'   => (int) $items_found,
					'items_cleaned' => (int) $deleted,
					'bytes_freed'   => 0,
				);

			case 'delete_auto_drafts':
				$items_found = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
				);

				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
				);

				return array(
					'scan_type'     => 'delete_auto_drafts',
					'items_found'   => (int) $items_found,
					'items_cleaned' => (int) $deleted,
					'bytes_freed'   => 0,
				);

			case 'clean_expired_transients':
				$items_found = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->options} t1
						INNER JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
						WHERE t1.option_name LIKE %s
						AND t1.option_value < %d",
						$wpdb->esc_like( '_transient_timeout_' ) . '%',
						time()
					)
				);

				// Delete expired transients.
				$deleted = $wpdb->query(
					$wpdb->prepare(
						"DELETE t1, t2 FROM {$wpdb->options} t1
						LEFT JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
						WHERE t1.option_name LIKE %s
						AND t1.option_value < %d",
						$wpdb->esc_like( '_transient_timeout_' ) . '%',
						time()
					)
				);

				return array(
					'scan_type'     => 'clean_expired_transients',
					'items_found'   => (int) $items_found,
					'items_cleaned' => (int) $deleted,
					'bytes_freed'   => 0,
				);

			case 'optimize_tables':
				// Get all tables.
				$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
				$optimized = 0;

				foreach ( $tables as $table ) {
					$table_name = $table[0];
					$wpdb->query( "OPTIMIZE TABLE {$table_name}" );
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
	 * @return int Database size in bytes.
	 */
	private function calculate_database_size() {
		global $wpdb;

		$database = DB_NAME;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(data_length + index_length) AS size
				FROM information_schema.TABLES
				WHERE table_schema = %s",
				$database
			)
		);

		return $result ? (int) $result->size : 0;
	}

	/**
	 * Calculate total number of cleanable items.
	 *
	 * @return int Total cleanable items count.
	 */
	private function calculate_cleanable_items() {
		global $wpdb;

		$cleanable = 0;

		// Trashed posts.
		$cleanable += $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"
		);

		// Spam comments.
		$cleanable += $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
		);

		// Auto-drafts.
		$cleanable += $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
		);

		// Expired transients.
		$cleanable += $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} t1
				INNER JOIN {$wpdb->options} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
				WHERE t1.option_name LIKE %s
				AND t1.option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		// Orphaned postmeta.
		$cleanable += $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);

		return (int) $cleanable;
	}

	/**
	 * Get the date of the last cleanup action.
	 *
	 * @return string|null Last cleanup date or null if none found.
	 */
	private function get_last_cleanup_date() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpha_scan_history';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $table_exists !== $table_name ) {
			return null;
		}

		$last_date = $wpdb->get_var(
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
