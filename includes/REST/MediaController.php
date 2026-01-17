<?php
/**
 * Media REST Controller
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\REST\Media\MediaHelper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media audit endpoints.
 *
 * Handles media statistics, unused media detection, duplicate finding,
 * large files analysis, alt text checking, and safe deletion operations.
 *
 * @since 1.0.0
 * @since 1.2.0 Refactored to use constructor injection for all dependencies.
 */
class MediaController extends RestController {

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
	private $per_page = 50;

	/**
	 * Scanner instance.
	 *
	 * @since 1.1.0
	 * @var ScannerInterface
	 */
	protected ScannerInterface $scanner;

	/**
	 * Duplicate detector instance.
	 *
	 * @since 1.2.0
	 * @var DuplicateDetectorInterface
	 */
	protected DuplicateDetectorInterface $duplicate_detector;

	/**
	 * Large files detector instance.
	 *
	 * @since 1.2.0
	 * @var LargeFilesInterface
	 */
	protected LargeFilesInterface $large_files;

	/**
	 * Alt text checker instance.
	 *
	 * @since 1.2.0
	 * @var AltTextCheckerInterface
	 */
	protected AltTextCheckerInterface $alt_text_checker;

	/**
	 * Reference finder instance.
	 *
	 * @since 1.2.0
	 * @var ReferenceFinderInterface
	 */
	protected ReferenceFinderInterface $reference_finder;

	/**
	 * Safe delete instance.
	 *
	 * @since 1.2.0
	 * @var SafeDeleteInterface
	 */
	protected SafeDeleteInterface $safe_delete;

	/**
	 * Exclusions manager instance.
	 *
	 * @since 1.2.0
	 * @var ExclusionsInterface
	 */
	protected ExclusionsInterface $exclusions;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.2.0 Added all media service dependencies via constructor injection.
	 * @since 1.3.0 Added ConnectionInterface dependency.
	 *
	 * @param SettingsInterface          $settings           Settings instance.
	 * @param ConnectionInterface        $connection         Database connection instance.
	 * @param ScannerInterface           $scanner            Scanner instance.
	 * @param DuplicateDetectorInterface $duplicate_detector Duplicate detector instance.
	 * @param LargeFilesInterface        $large_files        Large files detector instance.
	 * @param AltTextCheckerInterface    $alt_text_checker   Alt text checker instance.
	 * @param ReferenceFinderInterface   $reference_finder   Reference finder instance.
	 * @param SafeDeleteInterface        $safe_delete        Safe delete instance.
	 * @param ExclusionsInterface        $exclusions         Exclusions manager instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files,
		AltTextCheckerInterface $alt_text_checker,
		ReferenceFinderInterface $reference_finder,
		SafeDeleteInterface $safe_delete,
		ExclusionsInterface $exclusions
	) {
		parent::__construct( $settings, $connection );
		$this->scanner            = $scanner;
		$this->duplicate_detector = $duplicate_detector;
		$this->large_files        = $large_files;
		$this->alt_text_checker   = $alt_text_checker;
		$this->reference_finder   = $reference_finder;
		$this->safe_delete        = $safe_delete;
		$this->exclusions         = $exclusions;
	}

	/**
	 * Get the scanner instance.
	 *
	 * @since 1.1.0
	 *
	 * @return ScannerInterface The scanner instance.
	 */
	protected function get_scanner(): ScannerInterface {
		return $this->scanner;
	}

	/**
	 * Register routes for the controller.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function register_routes() {
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

		// GET /wpha/v1/media/alt-text - Get images missing alt text.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alt-text',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_missing_alt_text' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);

		// GET /wpha/v1/media/missing-alt - Alias for missing alt text endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/missing-alt',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_missing_alt_text' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);

		// POST /wpha/v1/media/scan - Trigger full media scan.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/scan',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_scan' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/media/delete - Safe delete selected media items.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'safe_delete' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'ids' => array(
							'description'       => __( 'Array of attachment IDs to delete.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'required'          => true,
							'items'             => array( 'type' => 'integer' ),
							'sanitize_callback' => array( $this, 'sanitize_ids' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
						'confirm' => array(
							'description'       => __( 'Explicit confirmation flag required for deletion.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/media/restore - Restore media from trash.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/restore',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_media' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'deletion_id' => array(
							'description'       => __( 'Deletion record ID to restore.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// GET /wpha/v1/media/exclusions - Get all exclusions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exclusions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_exclusions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/media/exclusions - Add exclusion(s).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exclusions',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_exclusions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'ids' => array(
							'description'       => __( 'Array of attachment IDs to exclude.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'required'          => true,
							'items'             => array( 'type' => 'integer' ),
							'sanitize_callback' => array( $this, 'sanitize_ids' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
						'reason' => array(
							'description'       => __( 'Reason for exclusion.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// DELETE /wpha/v1/media/exclusions/{id} - Remove an exclusion.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exclusions/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_exclusion' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Attachment ID to remove from exclusions.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// DELETE /wpha/v1/media/exclusions - Clear all exclusions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exclusions',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_exclusions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get media overview statistics.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		$scanner = $this->get_scanner();

		// Get basic counts.
		$total_count = $scanner->get_total_media_count();
		$total_size  = $scanner->get_total_media_size();

		// Get unused media count via batched scan.
		$scan_result  = $scanner->scan_unused_media( 1000, 0 );
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

		// Get missing alt text count (use COUNT query to avoid 100-item cap).
		$alt_coverage      = $this->alt_text_checker->get_alt_text_coverage();
		$missing_alt_count = isset( $alt_coverage['images_without_alt'] ) ? absint( $alt_coverage['images_without_alt'] ) : 0;

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
			'missing_alt_count'    => $missing_alt_count,
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
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_unused( $request ) {
		$cursor = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		$scanner = $this->get_scanner();

		// Use interface method with pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$scan_result = $scanner->scan_unused_media( $per_page, $start_index );
		$page_items  = $scan_result['unused'];

		// Enrich with details and thumbnail URLs.
		$items = array();
		foreach ( $page_items as $attachment_id ) {
			$items[] = $this->get_attachment_details( $attachment_id );
		}

		$has_more    = $scan_result['has_more'];
		$next_cursor = $has_more ? $scan_result['scanned'] : null;

		return $this->format_response(
			true,
			array(
				'items' => $items,
				'total' => $scan_result['total'],
				'cursor' => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Unused media retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get duplicate file groups.
	 *
 * @since 1.0.0
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
			$original_details = $this->get_attachment_details( $group['original'] );
			$copies_details = array();
			foreach ( $group['copies'] as $copy_id ) {
				$copies_details[] = $this->get_attachment_details( $copy_id );
			}

			$groups[] = array(
				'hash' => $group['hash'],
				'count' => $group['count'],
				'original' => $original_details,
				'copies' => $copies_details,
			);
		}

		$has_more = ( $start_index + $per_page ) < count( $all_groups );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'groups' => $groups,
				'total' => count( $all_groups ),
				'cursor' => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Duplicate groups retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get large files list.
	 *
 * @since 1.0.0
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
			$details       = $this->get_attachment_details( $attachment_id );

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
	 * Get images missing alt text.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_missing_alt_text( $request ) {
		$cursor   = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;

		// AltTextChecker is limit-based; request enough items to serve this page.
		$all_missing = $this->alt_text_checker->find_missing_alt_text( $start_index + $per_page );
		$page_items  = array_slice( $all_missing, $start_index, $per_page );

		$alt_coverage = $this->alt_text_checker->get_alt_text_coverage();
		$total       = isset( $alt_coverage['images_without_alt'] ) ? absint( $alt_coverage['images_without_alt'] ) : count( $all_missing );

		$items = array();
		foreach ( $page_items as $item ) {
			$attachment_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $attachment_id ) {
				continue;
			}
			$items[] = array_merge( $this->get_attachment_details( $attachment_id ), $item );
		}

		$has_more    = ( $start_index + $per_page ) < $total;
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items'    => $items,
				'total'    => $total,
				'cursor'   => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Images missing alt text retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Trigger full media scan.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function trigger_scan( $request ) {
		// Schedule scan in background using action scheduler.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'wpha_media_scan', array(), 'wpha_media' );

			return $this->format_response(
				true,
				array(
					'status' => 'scheduled',
					'message' => __( 'Media scan has been scheduled to run in the background.', 'wp-admin-health-suite' ),
				),
				__( 'Media scan scheduled successfully.', 'wp-admin-health-suite' )
			);
		}

		// Fallback: run scan immediately if Action Scheduler is not available.
		$scanner = $this->get_scanner();
		$results = $scanner->get_media_summary();

		return $this->format_response(
			true,
			array(
				'status' => 'completed',
				'results' => $results,
			),
			__( 'Media scan completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Safe delete selected media items.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function safe_delete( $request ) {
		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $this->format_error_response(
				new WP_Error(
					'invalid_ids',
					__( 'No valid attachment IDs provided.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		$safe_mode = $this->is_safe_mode_enabled();

		$require_confirmation = (bool) $this->get_settings()->get_setting( 'require_media_delete_confirmation', true );
		$confirmed            = (bool) filter_var( $request->get_param( 'confirm' ), FILTER_VALIDATE_BOOLEAN );

		if ( $require_confirmation && ! $safe_mode && ! $confirmed ) {
			return $this->format_error_response(
				new WP_Error(
					'confirmation_required',
					__( 'Deletion confirmation is required.', 'wp-admin-health-suite' )
				),
				428
			);
		}

		// Filter out excluded attachments - they should not be deleted.
		$excluded_ids  = array_filter(
			$ids,
			function ( $id ) {
				return $this->exclusions->is_excluded( (int) $id );
			}
		);
		$ids_to_delete = $this->exclusions->filter_excluded( $ids );

		if ( empty( $ids_to_delete ) ) {
			return $this->format_error_response(
				new WP_Error(
					'all_excluded',
					__( 'All selected attachments are excluded from deletion.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		if ( $safe_mode ) {
			$preview = array(
				'success'      => true,
				'safe_mode'    => true,
				'preview_only' => true,
				'prepared_items' => array_map(
					static function ( $attachment_id ) {
						return array( 'attachment_id' => absint( $attachment_id ) );
					},
					$ids_to_delete
				),
				'message'      => __( 'Safe mode enabled. No files were moved to trash.', 'wp-admin-health-suite' ),
			);

			if ( ! empty( $excluded_ids ) ) {
				$preview['excluded_ids'] = array_values( $excluded_ids );
				$preview['message']     .= sprintf(
					' ' . _n(
						'%d item was skipped because it is excluded.',
						'%d items were skipped because they are excluded.',
						count( $excluded_ids ),
						'wp-admin-health-suite'
					),
					count( $excluded_ids )
				);
			}

			$this->log_activity( 'media_delete', $preview );

			return $this->format_response(
				true,
				$preview,
				$preview['message']
			);
		}

		$result = $this->safe_delete->prepare_deletion( $ids_to_delete );

		// Do not expose absolute file paths via REST responses.
		if ( isset( $result['prepared_items'] ) && is_array( $result['prepared_items'] ) ) {
			$result['prepared_items'] = array_map(
				static function ( $item ) {
					if ( ! is_array( $item ) ) {
						return $item;
					}

					unset( $item['file_path'] );
					return $item;
				},
				$result['prepared_items']
			);
		}

		// Include info about skipped excluded items in the result.
		if ( ! empty( $excluded_ids ) ) {
			$result['excluded_ids'] = array_values( $excluded_ids );
			$result['message']      = isset( $result['message'] ) ? (string) $result['message'] : '';
			$result['message']     .= sprintf(
				' ' . _n(
					'%d item was skipped because it is excluded.',
					'%d items were skipped because they are excluded.',
					count( $excluded_ids ),
					'wp-admin-health-suite'
				),
				count( $excluded_ids )
			);
		}

		if ( ! $result['success'] ) {
			return $this->format_error_response(
				new WP_Error(
					'deletion_failed',
					$result['message']
				),
				400
			);
		}

		// Log to activity.
		$this->log_activity( 'media_delete', $result );

		return $this->format_response(
			true,
			$result,
			$result['message']
		);
	}

	/**
	 * Restore media from trash.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function restore_media( $request ) {
		$deletion_id = $request->get_param( 'deletion_id' );

		$safe_mode = $this->is_safe_mode_enabled();
		if ( $safe_mode ) {
			$preview = array(
				'success'      => true,
				'safe_mode'    => true,
				'preview_only' => true,
				'deletion_id'  => absint( $deletion_id ),
				'message'      => __( 'Safe mode enabled. No files were restored from trash.', 'wp-admin-health-suite' ),
			);

			$this->log_activity( 'media_restore', $preview );

			return $this->format_response(
				true,
				$preview,
				$preview['message']
			);
		}

		$result = $this->safe_delete->restore_deleted( $deletion_id );

		if ( ! $result['success'] ) {
			return $this->format_error_response(
				new WP_Error(
					'restore_failed',
					$result['message']
				),
				400
			);
		}

		// Log to activity.
		$this->log_activity( 'media_restore', $result );

		return $this->format_response(
			true,
			$result,
			$result['message']
		);
	}

	/**
	 * Get attachment details with thumbnail URL.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Attachment details.
	 */
	private function get_attachment_details( $attachment_id ) {
		return MediaHelper::get_attachment_details( (int) $attachment_id );
	}

	/**
	 * Get pagination parameters.
	 *
	 * @return array Pagination parameters.
	 */
	private function get_pagination_params() {
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

	/**
	 * Sanitize IDs array.
	 *
 * @since 1.0.0
 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized IDs array.
	 */
	public function sanitize_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_filter( array_map( 'absint', $value ) );
		$ids = array_values( array_unique( $ids ) );

		return $ids;
	}

	/**
	 * Log activity to scan history.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $type   The operation type.
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

		$items_found = 0;
		$items_cleaned = 0;

		switch ( $type ) {
			case 'media_delete':
				$items_found = isset( $result['prepared_items'] ) ? count( $result['prepared_items'] ) : 0;
				$items_cleaned = $items_found;
				break;

			case 'media_restore':
				$items_found = 1;
				$items_cleaned = 1;
				break;
		}

		$scan_type = 'media_' . str_replace( 'media_', '', $type );

		$connection->insert(
			$table_name,
			array(
				'scan_type'     => sanitize_text_field( $scan_type ),
				'items_found'   => absint( $items_found ),
				'items_cleaned' => absint( $items_cleaned ),
				'bytes_freed'   => 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Get all exclusions.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_exclusions( $request ) {
		$exclusions = $this->exclusions->get_exclusions();

		// Enrich with attachment details.
		$enriched_exclusions = array();
		foreach ( $exclusions as $exclusion ) {
			$attachment_id = isset( $exclusion['attachment_id'] ) ? absint( $exclusion['attachment_id'] ) : 0;
			$details       = $this->get_attachment_details( $attachment_id );

			$excluded_by = isset( $exclusion['excluded_by'] ) ? absint( $exclusion['excluded_by'] ) : 0;
			$user        = $excluded_by ? get_user_by( 'id', $excluded_by ) : null;
			$excluded_by_name = $user ? sanitize_text_field( (string) $user->display_name ) : __( 'Unknown', 'wp-admin-health-suite' );

			$excluded_at = isset( $exclusion['excluded_at'] ) ? sanitize_text_field( (string) $exclusion['excluded_at'] ) : '';
			if ( '' !== $excluded_at && function_exists( 'mysql_to_rfc3339' ) ) {
				$rfc3339 = mysql_to_rfc3339( $excluded_at );
				if ( false !== $rfc3339 ) {
					$excluded_at = $rfc3339;
				}
			}

			$sanitized_exclusion = array(
				'attachment_id' => $attachment_id,
				'excluded_at'   => $excluded_at,
				'reason'        => isset( $exclusion['reason'] ) ? sanitize_text_field( (string) $exclusion['reason'] ) : '',
				'excluded_by'   => $excluded_by,
			);

			$enriched_exclusions[] = array_merge(
				$sanitized_exclusion,
				$details,
				array( 'excluded_by_name' => $excluded_by_name )
			);
		}

		return $this->format_response(
			true,
			array(
				'exclusions' => $enriched_exclusions,
				'total' => count( $enriched_exclusions ),
			),
			__( 'Exclusions retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Add exclusions.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function add_exclusions( $request ) {
		$ids = $request->get_param( 'ids' );
		$reason = $request->get_param( 'reason' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $this->format_error_response(
				new WP_Error(
					'invalid_ids',
					__( 'No valid attachment IDs provided.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		$result = $this->exclusions->bulk_add_exclusions( $ids, $reason );

		return $this->format_response(
			true,
			array(
				'added'  => $result['success'],
				'failed' => $result['failed'],
			),
			sprintf(
				// translators: %d is the number of items excluded.
				__( '%d item(s) excluded successfully.', 'wp-admin-health-suite' ),
				$result['success']
			)
		);
	}

	/**
	 * Remove an exclusion.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function remove_exclusion( $request ) {
		$attachment_id = $request->get_param( 'id' );

		$success = $this->exclusions->remove_exclusion( $attachment_id );

		if ( ! $success ) {
			return $this->format_error_response(
				new WP_Error(
					'removal_failed',
					__( 'Failed to remove exclusion. Attachment may not be excluded.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		return $this->format_response(
			true,
			array( 'removed' => $attachment_id ),
			__( 'Exclusion removed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clear all exclusions.
	 *
 * @since 1.0.0
 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clear_exclusions( $request ) {
		$success = $this->exclusions->clear_exclusions();

		if ( ! $success ) {
			return $this->format_error_response(
				new WP_Error(
					'clear_failed',
					__( 'Failed to clear exclusions.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		return $this->format_response(
			true,
			array(),
			__( 'All exclusions cleared successfully.', 'wp-admin-health-suite' )
		);
	}
}
