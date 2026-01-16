<?php
/**
 * Media Analysis REST Controller
 *
 * Handles media analysis and reporting operations.
 *
 * @package WPAdminHealth\REST\Media
 */

namespace WPAdminHealth\REST\Media;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media analysis endpoints.
 *
 * Provides endpoints for media statistics, unused media detection,
 * duplicate finding, and large files analysis.
 *
 * @since 1.3.0
 */
class MediaAnalysisController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'media';

	/**
	 * Items per page for pagination.
	 *
	 * @var int
	 */
	private int $per_page = 50;

	/**
	 * Scanner instance.
	 *
	 * @var ScannerInterface
	 */
	private ScannerInterface $scanner;

	/**
	 * Duplicate detector instance.
	 *
	 * @var DuplicateDetectorInterface
	 */
	private DuplicateDetectorInterface $duplicate_detector;

	/**
	 * Large files detector instance.
	 *
	 * @var LargeFilesInterface
	 */
	private LargeFilesInterface $large_files;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface          $settings           Settings instance.
	 * @param ConnectionInterface        $connection         Database connection instance.
	 * @param ScannerInterface           $scanner            Scanner instance.
	 * @param DuplicateDetectorInterface $duplicate_detector Duplicate detector instance.
	 * @param LargeFilesInterface        $large_files        Large files detector instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files
	) {
		parent::__construct( $settings, $connection );
		$this->scanner            = $scanner;
		$this->duplicate_detector = $duplicate_detector;
		$this->large_files        = $large_files;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/media/stats - Get media overview statistics.
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

		// GET /wpha/v1/media/unused - Get paginated list of unused media.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unused',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_unused' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);

		// GET /wpha/v1/media/duplicates - Get duplicate file groups.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/duplicates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_duplicates' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);

		// GET /wpha/v1/media/large - Get large files list.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/large',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_large' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array_merge(
						$this->get_pagination_params(),
						array(
							'threshold' => array(
								'description'       => __( 'Minimum file size in KB.', 'wp-admin-health-suite' ),
								'type'              => 'integer',
								'default'           => 500,
								'minimum'           => 1,
								'sanitize_callback' => 'absint',
								'validate_callback' => 'rest_validate_request_arg',
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Get media overview statistics.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAnalysisController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		// Get basic counts.
		$total_count = $this->scanner->get_total_media_count();
		$total_size  = $this->scanner->get_total_media_size();

		// Get unused media count via batched scan.
		$scan_result  = $this->scanner->scan_unused_media( 1000, 0 );
		$unused_count = count( $scan_result['unused'] );

		// Get duplicate groups.
		$duplicate_groups = $this->duplicate_detector->get_duplicate_groups();
		$duplicate_count  = 0;
		foreach ( $duplicate_groups as $group ) {
			$duplicate_count += count( $group['copies'] );
		}

		// Get large files count (>500KB).
		$large_files_list  = $this->large_files->find_large_files( 500 );
		$large_files_count = count( $large_files_list );

		// Calculate potential savings from duplicates.
		$duplicate_savings = $this->duplicate_detector->get_potential_savings();

		$last_results = get_transient( 'wp_admin_health_media_scan_results' );
		$last_scan    = is_array( $last_results ) && ! empty( $last_results['scanned_at'] )
			? $last_results['scanned_at']
			: null;

		$stats = array(
			'total_count'          => $total_count,
			'total_size'           => $total_size,
			'total_size_formatted' => size_format( $total_size ),
			'unused_count'         => $unused_count,
			'duplicate_count'      => $duplicate_count,
			'duplicate_groups'     => count( $duplicate_groups ),
			'large_files_count'    => $large_files_count,
			'potential_savings'    => array(
				'bytes'     => $duplicate_savings['bytes'],
				'formatted' => $duplicate_savings['formatted'],
			),
			'last_scan'            => $last_scan,
		);

		return $this->format_response(
			true,
			$stats,
			__( 'Media statistics retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get paginated list of unused media.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAnalysisController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_unused( $request ) {
		$cursor   = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		// Use interface method with pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$scan_result = $this->scanner->scan_unused_media( $per_page, $start_index );
		$page_items  = $scan_result['unused'];

		// Enrich with details and thumbnail URLs.
		$items = array();
		foreach ( $page_items as $attachment_id ) {
			$items[] = MediaHelper::get_attachment_details( $attachment_id );
		}

		$has_more    = $scan_result['has_more'];
		$next_cursor = $has_more ? $scan_result['scanned'] : null;

		return $this->format_response(
			true,
			array(
				'items'    => $items,
				'total'    => $scan_result['total'],
				'cursor'   => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Unused media retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get duplicate file groups.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAnalysisController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_duplicates( $request ) {
		$cursor   = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		$all_groups = $this->duplicate_detector->get_duplicate_groups();

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$page_groups = array_slice( $all_groups, $start_index, $per_page );

		// Enrich with details and thumbnail URLs.
		$groups = array();
		foreach ( $page_groups as $group ) {
			$original_details = MediaHelper::get_attachment_details( $group['original'] );
			$copies_details = array();
			foreach ( $group['copies'] as $copy_id ) {
				$copies_details[] = MediaHelper::get_attachment_details( $copy_id );
			}

			$groups[] = array(
				'hash'     => $group['hash'],
				'count'    => $group['count'],
				'original' => $original_details,
				'copies'   => $copies_details,
			);
		}

		$has_more    = ( $start_index + $per_page ) < count( $all_groups );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'groups'   => $groups,
				'total'    => count( $all_groups ),
				'cursor'   => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Duplicate groups retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get large files list.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAnalysisController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_large( $request ) {
		$cursor    = $request->get_param( 'cursor' );
		$per_page  = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;
		$threshold = $request->get_param( 'threshold' );

		$all_large_files = $this->large_files->find_large_files( $threshold );

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$page_items  = array_slice( $all_large_files, $start_index, $per_page );

		$items = array();
		foreach ( $page_items as $file_data ) {
			$attachment_id = $file_data['id'];
			$details       = MediaHelper::get_attachment_details( $attachment_id );

			$dimensions = $file_data['dimensions'] ?? null;
			$width      = is_array( $dimensions ) && isset( $dimensions['width'] ) ? absint( $dimensions['width'] ) : null;
			$height     = is_array( $dimensions ) && isset( $dimensions['height'] ) ? absint( $dimensions['height'] ) : null;

			$size = $file_data['current_size'] ?? 0;

			$items[] = array_merge(
				$file_data,
				$details,
				array(
					// UI-friendly keys.
					'size'   => $size,
					'width'  => $width,
					'height' => $height,
				)
			);
		}

		$has_more    = ( $start_index + $per_page ) < count( $all_large_files );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items'    => $items,
				'total'    => count( $all_large_files ),
				'cursor'   => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Large files retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get pagination parameters.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAnalysisController.
	 *
	 * @return array Pagination parameters.
	 */
	private function get_pagination_params(): array {
		return array(
			'cursor' => array(
				'description'       => __( 'Cursor for pagination (offset).', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Number of items per page.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => $this->per_page,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
