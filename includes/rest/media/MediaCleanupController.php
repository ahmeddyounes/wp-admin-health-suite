<?php
/**
 * Media Cleanup REST Controller
 *
 * Handles media cleanup operations including deletion, restoration, and exclusions.
 *
 * @package WPAdminHealth\REST\Media
 */

namespace WPAdminHealth\REST\Media;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media cleanup endpoints.
 *
 * Provides endpoints for safe deletion, restoration, and exclusion management.
 *
 * @since 1.3.0
 */
class MediaCleanupController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'media';

	/**
	 * Safe delete instance.
	 *
	 * @var SafeDeleteInterface
	 */
	private SafeDeleteInterface $safe_delete;

	/**
	 * Exclusions manager instance.
	 *
	 * @var ExclusionsInterface
	 */
	private ExclusionsInterface $exclusions;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface   $settings   Settings instance.
	 * @param ConnectionInterface $connection Database connection instance.
	 * @param SafeDeleteInterface $safe_delete Safe delete instance.
	 * @param ExclusionsInterface $exclusions Exclusions manager instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		SafeDeleteInterface $safe_delete,
		ExclusionsInterface $exclusions
	) {
		parent::__construct( $settings, $connection );
		$this->safe_delete = $safe_delete;
		$this->exclusions  = $exclusions;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
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
	 * Safe delete selected media items.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaCleanupController.
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
	 * @since 1.3.0 Moved to MediaCleanupController.
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
	 * Get all exclusions.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaCleanupController.
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
			$details = MediaHelper::get_attachment_details( $attachment_id );

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
				'total'      => count( $enriched_exclusions ),
			),
			__( 'Exclusions retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Add exclusions.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaCleanupController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function add_exclusions( $request ) {
		$ids    = $request->get_param( 'ids' );
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
	 * @since 1.3.0 Moved to MediaCleanupController.
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
	 * @since 1.3.0 Moved to MediaCleanupController.
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

	/**
	 * Sanitize IDs array.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaCleanupController.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized IDs array.
	 */
	public function sanitize_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'absint', $value );
	}

	/**
	 * Log activity to scan history.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaCleanupController. Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $type   The operation type.
	 * @param array  $result The result data.
	 * @return void
	 */
	private function log_activity( string $type, array $result ): void {
		$connection = $this->get_connection();
		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return;
		}

		$items_found   = 0;
		$items_cleaned = 0;

		switch ( $type ) {
			case 'media_delete':
				$items_found   = isset( $result['prepared_items'] ) ? count( $result['prepared_items'] ) : 0;
				$items_cleaned = $items_found;
				break;

			case 'media_restore':
				$items_found   = 1;
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
}
