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
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;

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
	 *
	 * @param SettingsInterface          $settings           Settings instance.
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
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files,
		AltTextCheckerInterface $alt_text_checker,
		ReferenceFinderInterface $reference_finder,
		SafeDeleteInterface $safe_delete,
		ExclusionsInterface $exclusions
	) {
		parent::__construct( $settings );
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
		$total_count = $scanner->get_media_count();
		$total_size  = $scanner->get_media_total_size();

		// Get unused media count.
		$unused       = $scanner->find_unused_media();
		$unused_count = count( $unused );

		// Get duplicate groups.
		$duplicate_groups = $this->duplicate_detector->get_duplicate_groups();
		$duplicate_count  = 0;
		foreach ( $duplicate_groups as $group ) {
			$duplicate_count += count( $group['copies'] );
		}

		// Get large files count (>500KB).
		$large_files_list  = $this->large_files->find_large_files( 500 );
		$large_files_count = count( $large_files_list );

		// Get missing alt text count.
		$missing_alt       = $this->alt_text_checker->find_missing_alt_text();
		$missing_alt_count = count( $missing_alt );

		// Calculate potential savings from duplicates.
		$duplicate_savings = $this->duplicate_detector->get_potential_savings();

		$stats = array(
			'total_count' => $total_count,
			'total_size' => $total_size,
			'total_size_formatted' => size_format( $total_size ),
			'unused_count' => $unused_count,
			'duplicate_count' => $duplicate_count,
			'duplicate_groups' => count( $duplicate_groups ),
			'large_files_count' => $large_files_count,
			'missing_alt_count' => $missing_alt_count,
			'potential_savings' => array(
				'bytes' => $duplicate_savings['bytes'],
				'formatted' => $duplicate_savings['formatted'],
			),
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
		$all_unused = $scanner->find_unused_media();

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$page_items = array_slice( $all_unused, $start_index, $per_page );

		// Enrich with details and thumbnail URLs.
		$items = array();
		foreach ( $page_items as $attachment_id ) {
			$items[] = $this->get_attachment_details( $attachment_id );
		}

		$has_more = ( $start_index + $per_page ) < count( $all_unused );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items' => $items,
				'total' => count( $all_unused ),
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
		$page_items = array_slice( $all_large_files, $start_index, $per_page );

		// Enrich with thumbnail URLs.
		$items = array();
		foreach ( $page_items as $file_data ) {
			$attachment_id = $file_data['id'];
			$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
			if ( ! $thumbnail_url ) {
				$thumbnail_url = wp_get_attachment_url( $attachment_id );
			}

			$items[] = array_merge(
				$file_data,
				array(
					'thumbnail_url' => $thumbnail_url,
					'edit_link' => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
				)
			);
		}

		$has_more = ( $start_index + $per_page ) < count( $all_large_files );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items' => $items,
				'total' => count( $all_large_files ),
				'cursor' => $next_cursor,
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

		$all_missing = $this->alt_text_checker->find_missing_alt_text();

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;
		$page_items = array_slice( $all_missing, $start_index, $per_page );

		$has_more = ( $start_index + $per_page ) < count( $all_missing );
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items' => $page_items,
				'total' => count( $all_missing ),
				'cursor' => $next_cursor,
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
		$results = $scanner->scan_all_media();

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

		$result = $this->safe_delete->prepare_deletion( $ids );

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
		$file_path = get_attached_file( $attachment_id );
		$filename = $file_path ? basename( $file_path ) : '';
		$file_size = $file_path && file_exists( $file_path ) ? filesize( $file_path ) : 0;

		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $thumbnail_url ) {
			$thumbnail_url = wp_get_attachment_url( $attachment_id );
		}

		$post = get_post( $attachment_id );
		$title = $post ? $post->post_title : '';
		$mime_type = get_post_mime_type( $attachment_id );

		return array(
			'id' => $attachment_id,
			'title' => $title,
			'filename' => $filename,
			'file_size' => $file_size,
			'file_size_formatted' => size_format( $file_size ),
			'mime_type' => $mime_type,
			'thumbnail_url' => $thumbnail_url,
			'edit_link' => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
		);
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

		return array_map( 'absint', $value );
	}

	/**
	 * Log activity to scan history.
	 *
	 * @param string $type   The operation type.
	 * @param array  $result The result data.
	 * @return void
	 */
	private function log_activity( $type, $result ) {
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

		$wpdb->insert(
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
			$attachment_id = $exclusion['attachment_id'];
			$details = $this->get_attachment_details( $attachment_id );

			$user = get_user_by( 'id', $exclusion['excluded_by'] );
			$excluded_by_name = $user ? $user->display_name : __( 'Unknown', 'wp-admin-health-suite' );

			$enriched_exclusions[] = array_merge(
				$exclusion,
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
