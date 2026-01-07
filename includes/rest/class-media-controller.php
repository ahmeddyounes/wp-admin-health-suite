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
use WPAdminHealth\Media\Scanner;
use WPAdminHealth\Media\Reference_Finder;
use WPAdminHealth\Media\Duplicate_Detector;
use WPAdminHealth\Media\Large_Files;
use WPAdminHealth\Media\Alt_Text_Checker;
use WPAdminHealth\Media\Safe_Delete;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media audit endpoints.
 *
 * Handles media statistics, unused media detection, duplicate finding,
 * large files analysis, alt text checking, and safe deletion operations.
 */
class Media_Controller extends REST_Controller {

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
	 * Register routes for the controller.
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
	}

	/**
	 * Get media overview statistics.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_stats( $request ) {
		$scanner = new Scanner();
		$duplicate_detector = new Duplicate_Detector();
		$large_files = new Large_Files();
		$alt_text_checker = new Alt_Text_Checker();
		$reference_finder = new Reference_Finder();

		// Get basic counts.
		$total_count = $scanner->get_media_count();
		$total_size = $scanner->get_media_total_size();

		// Get unused media count.
		$unused = $scanner->find_unused_media();
		$unused_count = count( $unused );

		// Get duplicate groups.
		$duplicate_groups = $duplicate_detector->get_duplicate_groups();
		$duplicate_count = 0;
		foreach ( $duplicate_groups as $group ) {
			$duplicate_count += count( $group['copies'] );
		}

		// Get large files count (>500KB).
		$large_files_list = $large_files->find_large_files( 500 );
		$large_files_count = count( $large_files_list );

		// Get missing alt text count.
		$missing_alt = $alt_text_checker->find_missing_alt_text();
		$missing_alt_count = count( $missing_alt );

		// Calculate potential savings from duplicates.
		$duplicate_savings = $duplicate_detector->get_potential_savings();

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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_unused( $request ) {
		$cursor = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		$scanner = new Scanner();
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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_duplicates( $request ) {
		$cursor = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		$duplicate_detector = new Duplicate_Detector();
		$all_groups = $duplicate_detector->get_duplicate_groups();

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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_large( $request ) {
		$cursor = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;
		$threshold = $request->get_param( 'threshold' );

		$large_files = new Large_Files();
		$all_large_files = $large_files->find_large_files( $threshold );

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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_missing_alt_text( $request ) {
		$cursor = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		$alt_text_checker = new Alt_Text_Checker();
		$all_missing = $alt_text_checker->find_missing_alt_text();

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
		$scanner = new Scanner();
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

		$safe_delete = new Safe_Delete();
		$result = $safe_delete->prepare_deletion( $ids );

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
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function restore_media( $request ) {
		$deletion_id = $request->get_param( 'deletion_id' );

		$safe_delete = new Safe_Delete();
		$result = $safe_delete->restore_deleted( $deletion_id );

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
}
