<?php
/**
 * Database REST Controller
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for database health endpoints.
 *
 * Handles database statistics, cleanup operations, and optimization.
 *
 * @since 1.0.0
 * @since 1.2.0 Refactored to use constructor injection for all dependencies.
 */
class DatabaseController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'database';

	/**
	 * Analyzer instance.
	 *
	 * @since 1.1.0
	 * @var AnalyzerInterface
	 */
	protected AnalyzerInterface $analyzer;

	/**
	 * Revisions manager instance.
	 *
	 * @since 1.2.0
	 * @var RevisionsManagerInterface
	 */
	protected RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner instance.
	 *
	 * @since 1.2.0
	 * @var TransientsCleanerInterface
	 */
	protected TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner instance.
	 *
	 * @since 1.2.0
	 * @var OrphanedCleanerInterface
	 */
	protected OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Trash cleaner instance.
	 *
	 * @since 1.2.0
	 * @var TrashCleanerInterface
	 */
	protected TrashCleanerInterface $trash_cleaner;

	/**
	 * Optimizer instance.
	 *
	 * @since 1.2.0
	 * @var OptimizerInterface
	 */
	protected OptimizerInterface $optimizer;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.2.0 Added all database service dependencies via constructor injection.
	 * @since 1.3.0 Added ConnectionInterface parameter.
	 *
	 * @param SettingsInterface          $settings           Settings instance.
	 * @param ConnectionInterface        $connection         Database connection instance.
	 * @param AnalyzerInterface          $analyzer           Analyzer instance.
	 * @param RevisionsManagerInterface  $revisions_manager  Revisions manager instance.
	 * @param TransientsCleanerInterface $transients_cleaner Transients cleaner instance.
	 * @param OrphanedCleanerInterface   $orphaned_cleaner   Orphaned cleaner instance.
	 * @param TrashCleanerInterface      $trash_cleaner      Trash cleaner instance.
	 * @param OptimizerInterface         $optimizer          Optimizer instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		AnalyzerInterface $analyzer,
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		OptimizerInterface $optimizer
	) {
		parent::__construct( $settings, $connection );
		$this->analyzer           = $analyzer;
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->optimizer          = $optimizer;
	}

	/**
	 * Get the analyzer instance.
	 *
	 * @since 1.1.0
	 *
	 * @return AnalyzerInterface The analyzer instance.
	 */
	protected function get_analyzer(): AnalyzerInterface {
		return $this->analyzer;
	}

	/**
	 * Register routes for the controller.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function register_routes() {
		// GET /wpha/v1/database/stats - Get all analyzer stats.
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

		// GET /wpha/v1/database/revisions - Get revision details.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revisions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_revisions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'limit' => array(
							'description'       => __( 'Number of posts to return with most revisions.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 50,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// GET /wpha/v1/database/transients - Get transient list.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/transients',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_transients' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// GET /wpha/v1/database/orphaned - Get orphaned data summary.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/orphaned',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_orphaned' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/database/clean - Execute cleanup by type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clean',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'type'    => array(
							'description'       => __( 'Type of cleanup to perform.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'revisions', 'transients', 'spam', 'trash', 'orphaned' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'options' => array(
							'description'       => __( 'Additional options for cleanup.', 'wp-admin-health-suite' ),
							'type'              => 'object',
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_options' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/database/optimize - Run optimization.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/optimize',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'optimize' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'tables' => array(
							'description'       => __( 'Specific tables to optimize. If empty, optimizes all tables.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_table_names' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get all analyzer statistics.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		$analyzer = $this->get_analyzer();

		$stats = array(
			'database_size'           => $analyzer->get_database_size(),
			'table_sizes'             => $analyzer->get_table_sizes(),
			'revisions_count'         => $analyzer->get_revisions_count(),
			'auto_drafts_count'       => $analyzer->get_auto_drafts_count(),
			'trashed_posts_count'     => $analyzer->get_trashed_posts_count(),
			'spam_comments_count'     => $analyzer->get_spam_comments_count(),
			'trashed_comments_count'  => $analyzer->get_trashed_comments_count(),
			'expired_transients_count' => $analyzer->get_expired_transients_count(),
			'orphaned_postmeta_count' => $analyzer->get_orphaned_postmeta_count(),
			'orphaned_commentmeta_count' => $analyzer->get_orphaned_commentmeta_count(),
			'orphaned_termmeta_count' => $analyzer->get_orphaned_termmeta_count(),
		);

		return $this->format_response(
			true,
			$stats,
			__( 'Database statistics retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get revision details.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_revisions( $request ) {
		$limit = $request->get_param( 'limit' );

		$data = array(
			'total_count' => $this->revisions_manager->get_all_revisions_count(),
			'size_estimate' => $this->revisions_manager->get_revisions_size_estimate(),
			'posts_with_most_revisions' => $this->revisions_manager->get_posts_with_most_revisions( $limit ),
		);

		return $this->format_response(
			true,
			$data,
			__( 'Revision details retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get transient list.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_transients( $request ) {
		// Check if using external object cache.
		if ( wp_using_ext_object_cache() ) {
			return $this->format_response(
				true,
				array(
					'total_count' => 0,
					'size_estimate' => 0,
					'expired_transients' => array(),
					'using_external_cache' => true,
					'message' => __( 'External object cache is in use. Transient data is not stored in the database.', 'wp-admin-health-suite' ),
				),
				__( 'Transient information retrieved successfully.', 'wp-admin-health-suite' )
			);
		}

		$data = array(
			'total_count' => $this->transients_cleaner->count_transients(),
			'size_estimate' => $this->transients_cleaner->get_transients_size(),
			'expired_transients' => $this->transients_cleaner->get_expired_transients(),
			'using_external_cache' => false,
		);

		return $this->format_response(
			true,
			$data,
			__( 'Transient list retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get orphaned data summary.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_orphaned( $request ) {
		$data = array(
			'orphaned_postmeta' => array(
				'count' => $this->orphaned_cleaner->count_orphaned_postmeta(),
			),
			'orphaned_commentmeta' => array(
				'count' => $this->orphaned_cleaner->count_orphaned_commentmeta(),
			),
			'orphaned_termmeta' => array(
				'count' => $this->orphaned_cleaner->count_orphaned_termmeta(),
			),
			'orphaned_relationships' => array(
				'count' => $this->orphaned_cleaner->count_orphaned_relationships(),
			),
		);

		return $this->format_response(
			true,
			$data,
			__( 'Orphaned data summary retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Execute cleanup by type.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean( $request ) {
		$type    = $request->get_param( 'type' );
		$options = $request->get_param( 'options' );

		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = array();
		}

		// Check if safe mode is enabled.
		$safe_mode = $this->is_safe_mode_enabled();

		$result = null;

		switch ( $type ) {
			case 'revisions':
				$result = $this->clean_revisions( $options, $safe_mode );
				break;

			case 'transients':
				$result = $this->clean_transients( $options, $safe_mode );
				break;

			case 'spam':
				$result = $this->clean_spam( $options, $safe_mode );
				break;

			case 'trash':
				$result = $this->clean_trash( $options, $safe_mode );
				break;

			case 'orphaned':
				$result = $this->clean_orphaned( $options, $safe_mode );
				break;

			default:
				return $this->format_error_response(
					new WP_Error(
						'invalid_type',
						__( 'Invalid cleanup type specified.', 'wp-admin-health-suite' )
					),
					400
				);
		}

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		// Add safe mode indicator to result.
		if ( $safe_mode ) {
			$result['safe_mode'] = true;
			$result['preview_only'] = true;
		}

		// Log to activity.
		$this->log_activity( $type, $result );

		return $this->format_response(
			true,
			$result,
			sprintf(
				/* translators: %s: cleanup type */
				__( '%s cleanup completed successfully.', 'wp-admin-health-suite' ),
				ucfirst( $type )
			)
		);
	}

	/**
	 * Run database optimization.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function optimize( $request ) {
		$tables  = $request->get_param( 'tables' );
		$results = array();

		if ( empty( $tables ) ) {
			// Optimize all tables.
			$results = $this->optimizer->optimize_all_tables();
		} else {
			// Optimize specific tables.
			foreach ( $tables as $table ) {
				$result = $this->optimizer->optimize_table( $table );
				if ( $result ) {
					$results[] = $result;
				}
			}
		}

		if ( empty( $results ) ) {
			return $this->format_error_response(
				new WP_Error(
					'optimization_failed',
					__( 'No tables were optimized. Please check the table names.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		// Calculate total bytes freed.
		$total_bytes_freed = 0;
		foreach ( $results as $result ) {
			$total_bytes_freed += isset( $result['size_reduced'] ) ? $result['size_reduced'] : 0;
		}

		// Log to activity.
		$this->log_activity(
			'optimization',
			array(
				'tables_optimized' => count( $results ),
				'bytes_freed'      => $total_bytes_freed,
			)
		);

		return $this->format_response(
			true,
			array(
				'results'           => $results,
				'tables_optimized'  => count( $results ),
				'total_bytes_freed' => $total_bytes_freed,
			),
			__( 'Database optimization completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clean revisions.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array|WP_Error Cleanup results or error.
	 */
	private function clean_revisions( $options, $safe_mode = false ) {
		// Use settings as fallback if not provided in options.
		$settings      = $this->get_settings();
		$keep_per_post = isset( $options['keep_per_post'] ) ? absint( $options['keep_per_post'] ) : absint( $settings->get_setting( 'revisions_to_keep', 0 ) );

		if ( $safe_mode ) {
			// In safe mode, only return preview data without actually deleting.
			$total_revisions = $this->revisions_manager->get_all_revisions_count();
			$size_estimate   = $this->revisions_manager->get_revisions_size_estimate();

			return array(
				'type'          => 'revisions',
				'deleted'       => 0,
				'would_delete'  => $total_revisions,
				'bytes_freed'   => 0,
				'would_free'    => $size_estimate,
				'keep_per_post' => $keep_per_post,
			);
		}

		$result = $this->revisions_manager->delete_all_revisions( $keep_per_post );

		return array(
			'type'         => 'revisions',
			'deleted'      => $result['deleted'],
			'bytes_freed'  => $result['bytes_freed'],
			'keep_per_post' => $keep_per_post,
		);
	}

	/**
	 * Clean transients.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array|WP_Error Cleanup results or error.
	 */
	private function clean_transients( $options, $safe_mode = false ) {
		// Use settings as fallback if not provided in options.
		$settings     = $this->get_settings();
		$expired_only = isset( $options['expired_only'] ) ? (bool) $options['expired_only'] : true;

		// Get exclude patterns from settings if not provided in options.
		if ( ! isset( $options['exclude_patterns'] ) || ! is_array( $options['exclude_patterns'] ) ) {
			$excluded_prefixes = $settings->get_setting( 'excluded_transient_prefixes', '' );
			// Split by newlines and filter empty lines.
			$exclude_patterns = array_filter( array_map( 'trim', explode( "\n", $excluded_prefixes ) ) );
		} else {
			$exclude_patterns = $options['exclude_patterns'];
		}

		if ( $safe_mode ) {
			// In safe mode, only return preview data without actually deleting.
			$total_count = $this->transients_cleaner->count_transients();
			$size        = $this->transients_cleaner->get_transients_size();

			return array(
				'type'             => 'transients',
				'deleted'          => 0,
				'would_delete'     => $total_count,
				'bytes_freed'      => 0,
				'would_free'       => $size,
				'expired_only'     => $expired_only,
				'exclude_patterns' => $exclude_patterns,
			);
		}

		if ( $expired_only ) {
			$result = $this->transients_cleaner->delete_expired_transients( $exclude_patterns );
		} else {
			$result = $this->transients_cleaner->delete_all_transients( $exclude_patterns );
		}

		return array(
			'type'            => 'transients',
			'deleted'         => $result['deleted'],
			'bytes_freed'     => $result['bytes_freed'],
			'expired_only'    => $expired_only,
			'exclude_patterns' => $exclude_patterns,
		);
	}

	/**
	 * Clean spam comments.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array|WP_Error Cleanup results or error.
	 */
	private function clean_spam( $options, $safe_mode = false ) {
		// Use settings as fallback if not provided in options.
		$settings        = $this->get_settings();
		$older_than_days = isset( $options['older_than_days'] ) ? absint( $options['older_than_days'] ) : absint( $settings->get_setting( 'auto_clean_spam_days', 0 ) );

		if ( $safe_mode ) {
			// In safe mode, only return preview data.
			$analyzer = $this->get_analyzer();
			$count    = $analyzer->get_spam_comments_count();

			return array(
				'type'            => 'spam',
				'deleted'         => 0,
				'would_delete'    => $count,
				'errors'          => array(),
				'older_than_days' => $older_than_days,
			);
		}

		$result = $this->trash_cleaner->delete_spam_comments( $older_than_days );

		return array(
			'type'            => 'spam',
			'deleted'         => $result['deleted'],
			'errors'          => $result['errors'],
			'older_than_days' => $older_than_days,
		);
	}

	/**
	 * Clean trash (posts and comments).
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array|WP_Error Cleanup results or error.
	 */
	private function clean_trash( $options, $safe_mode = false ) {
		// Use settings as fallback if not provided in options.
		$settings        = $this->get_settings();
		$older_than_days = isset( $options['older_than_days'] ) ? absint( $options['older_than_days'] ) : absint( $settings->get_setting( 'auto_clean_trash_days', 0 ) );
		$post_types      = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : array();

		if ( $safe_mode ) {
			// In safe mode, only return preview data.
			$analyzer       = $this->get_analyzer();
			$posts_count    = $analyzer->get_trashed_posts_count();
			$comments_count = $analyzer->get_trashed_comments_count();

			return array(
				'type'                 => 'trash',
				'posts_deleted'        => 0,
				'posts_would_delete'   => $posts_count,
				'posts_errors'         => array(),
				'comments_deleted'     => 0,
				'comments_would_delete' => $comments_count,
				'comments_errors'      => array(),
				'older_than_days'      => $older_than_days,
				'post_types'           => $post_types,
			);
		}

		// Delete trashed posts.
		$posts_result = $this->trash_cleaner->delete_trashed_posts( $post_types, $older_than_days );

		// Delete trashed comments.
		$comments_result = $this->trash_cleaner->delete_trashed_comments( $older_than_days );

		return array(
			'type'              => 'trash',
			'posts_deleted'     => $posts_result['deleted'],
			'posts_errors'      => $posts_result['errors'],
			'comments_deleted'  => $comments_result['deleted'],
			'comments_errors'   => $comments_result['errors'],
			'older_than_days'   => $older_than_days,
			'post_types'        => $post_types,
		);
	}

	/**
	 * Clean orphaned data.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array|WP_Error Cleanup results or error.
	 */
	private function clean_orphaned( $options, $safe_mode = false ) {
		$types = isset( $options['types'] ) && is_array( $options['types'] ) ? $options['types'] : array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' );

		$results = array(
			'type' => 'orphaned',
		);

		if ( $safe_mode ) {
			// In safe mode, only return preview counts.
			if ( in_array( 'postmeta', $types, true ) ) {
				$results['postmeta_deleted']      = 0;
				$results['postmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_postmeta() );
			}

			if ( in_array( 'commentmeta', $types, true ) ) {
				$results['commentmeta_deleted']      = 0;
				$results['commentmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_commentmeta() );
			}

			if ( in_array( 'termmeta', $types, true ) ) {
				$results['termmeta_deleted']      = 0;
				$results['termmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_termmeta() );
			}

			if ( in_array( 'relationships', $types, true ) ) {
				$results['relationships_deleted']      = 0;
				$results['relationships_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_relationships() );
			}

			return $results;
		}

		if ( in_array( 'postmeta', $types, true ) ) {
			$results['postmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_postmeta();
		}

		if ( in_array( 'commentmeta', $types, true ) ) {
			$results['commentmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_commentmeta();
		}

		if ( in_array( 'termmeta', $types, true ) ) {
			$results['termmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_termmeta();
		}

		if ( in_array( 'relationships', $types, true ) ) {
			$results['relationships_deleted'] = $this->orphaned_cleaner->delete_orphaned_relationships();
		}

		return $results;
	}

	/**
	 * Log activity to scan history.
	 *
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $type   The cleanup/operation type.
	 * @param array  $result The result data.
	 * @return void
	 */
	private function log_activity( $type, $result ) {
		$connection = $this->get_connection();

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return;
		}

		// Determine items found and cleaned.
		$items_found   = 0;
		$items_cleaned = 0;
		$bytes_freed   = 0;

		switch ( $type ) {
			case 'revisions':
				$items_found   = isset( $result['deleted'] ) ? $result['deleted'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? $result['bytes_freed'] : 0;
				break;

			case 'transients':
				$items_found   = isset( $result['deleted'] ) ? $result['deleted'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? $result['bytes_freed'] : 0;
				break;

			case 'spam':
				$items_found   = isset( $result['deleted'] ) ? $result['deleted'] : 0;
				$items_cleaned = $items_found;
				break;

			case 'trash':
				$items_found   = ( isset( $result['posts_deleted'] ) ? $result['posts_deleted'] : 0 ) + ( isset( $result['comments_deleted'] ) ? $result['comments_deleted'] : 0 );
				$items_cleaned = $items_found;
				break;

			case 'orphaned':
				$items_found   = ( isset( $result['postmeta_deleted'] ) ? $result['postmeta_deleted'] : 0 ) +
								( isset( $result['commentmeta_deleted'] ) ? $result['commentmeta_deleted'] : 0 ) +
								( isset( $result['termmeta_deleted'] ) ? $result['termmeta_deleted'] : 0 ) +
								( isset( $result['relationships_deleted'] ) ? $result['relationships_deleted'] : 0 );
				$items_cleaned = $items_found;
				break;

			case 'optimization':
				$items_found   = isset( $result['tables_optimized'] ) ? $result['tables_optimized'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? $result['bytes_freed'] : 0;
				break;
		}

		$scan_type = 'database_' . $type;

		$connection->insert(
			$table_name,
			array(
				'scan_type'     => sanitize_text_field( $scan_type ),
				'items_found'   => absint( $items_found ),
				'items_cleaned' => absint( $items_cleaned ),
				'bytes_freed'   => absint( $bytes_freed ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Sanitize options parameter.
	 *
 * @since 1.0.0
 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized options array.
	 */
	public function sanitize_options( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $value as $key => $val ) {
			$key = sanitize_key( $key );

			if ( is_array( $val ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $val );
			} elseif ( is_bool( $val ) ) {
				$sanitized[ $key ] = (bool) $val;
			} elseif ( is_numeric( $val ) ) {
				$sanitized[ $key ] = absint( $val );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $val );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize table names array.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized table names array.
	 */
	public function sanitize_table_names( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$connection = $this->get_connection();
		$prefix     = $connection->get_prefix();

		$sanitized = array();

		foreach ( $value as $table_name ) {
			$table_name = sanitize_text_field( $table_name );

			// Only allow tables with WordPress prefix.
			if ( 0 === strpos( $table_name, $prefix ) ) {
				$sanitized[] = $table_name;
			}
		}

		return $sanitized;
	}
}
