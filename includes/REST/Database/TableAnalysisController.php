<?php
/**
 * Table Analysis REST Controller
 *
 * Handles database analysis and read-only endpoints.
 *
 * @package WPAdminHealth\REST\Database
 */

namespace WPAdminHealth\REST\Database;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for database analysis endpoints.
 *
 * Provides read-only endpoints for database statistics, revisions,
 * transients, and orphaned data analysis.
 *
 * @since 1.3.0
 */
class TableAnalysisController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'database/analysis';

	/**
	 * Analyzer instance.
	 *
	 * @var AnalyzerInterface
	 */
	private AnalyzerInterface $analyzer;

	/**
	 * Revisions manager instance.
	 *
	 * @var RevisionsManagerInterface
	 */
	private RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner instance.
	 *
	 * @var TransientsCleanerInterface
	 */
	private TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner instance.
	 *
	 * @var OrphanedCleanerInterface
	 */
	private OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface          $settings           Settings instance.
	 * @param ConnectionInterface        $connection         Database connection instance.
	 * @param AnalyzerInterface          $analyzer           Analyzer instance.
	 * @param RevisionsManagerInterface  $revisions_manager  Revisions manager instance.
	 * @param TransientsCleanerInterface $transients_cleaner Transients cleaner instance.
	 * @param OrphanedCleanerInterface   $orphaned_cleaner   Orphaned cleaner instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		AnalyzerInterface $analyzer,
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner
	) {
		parent::__construct( $settings, $connection );
		$this->analyzer           = $analyzer;
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/database/analysis/stats - Get all analyzer stats.
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

		// GET /wpha/v1/database/analysis/revisions - Get revision details.
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

		// GET /wpha/v1/database/analysis/transients - Get transient list.
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

		// GET /wpha/v1/database/analysis/orphaned - Get orphaned data summary.
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
	}

	/**
	 * Get all analyzer statistics.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		$stats = array(
			'database_size'            => $this->analyzer->get_database_size(),
			'table_sizes'              => $this->analyzer->get_table_sizes(),
			'revisions_count'          => $this->analyzer->get_revisions_count(),
			'auto_drafts_count'        => $this->analyzer->get_auto_drafts_count(),
			'trashed_posts_count'      => $this->analyzer->get_trashed_posts_count(),
			'spam_comments_count'      => $this->analyzer->get_spam_comments_count(),
			'trashed_comments_count'   => $this->analyzer->get_trashed_comments_count(),
			'expired_transients_count' => $this->analyzer->get_expired_transients_count(),
			'orphaned_postmeta_count'  => $this->analyzer->get_orphaned_postmeta_count(),
			'orphaned_commentmeta_count' => $this->analyzer->get_orphaned_commentmeta_count(),
			'orphaned_termmeta_count'  => $this->analyzer->get_orphaned_termmeta_count(),
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
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_revisions( $request ) {
		$limit = $request->get_param( 'limit' );

		$data = array(
			'total_count'              => $this->revisions_manager->get_all_revisions_count(),
			'size_estimate'            => $this->revisions_manager->get_revisions_size_estimate(),
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
	 * @since 1.3.0
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
					'total_count'         => 0,
					'size_estimate'       => 0,
					'expired_transients'  => array(),
					'using_external_cache' => true,
					'message'             => __( 'External object cache is in use. Transient data is not stored in the database.', 'wp-admin-health-suite' ),
				),
				__( 'Transient information retrieved successfully.', 'wp-admin-health-suite' )
			);
		}

		$data = array(
			'total_count'         => $this->transients_cleaner->count_transients(),
			'size_estimate'       => $this->transients_cleaner->get_transients_size(),
			'expired_transients'  => $this->transients_cleaner->get_expired_transients(),
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
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_orphaned( $request ) {
		$data = array(
			'orphaned_postmeta'     => array(
				'count' => $this->orphaned_cleaner->count_orphaned_postmeta(),
			),
			'orphaned_commentmeta'  => array(
				'count' => $this->orphaned_cleaner->count_orphaned_commentmeta(),
			),
			'orphaned_termmeta'     => array(
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
}
