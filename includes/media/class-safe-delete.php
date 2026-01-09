<?php
/**
 * Safe Delete Class
 *
 * Implements two-step deletion for media files with recovery capability.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Safe_Delete class for managing two-step media deletion with recovery.
 *
 * @since 1.0.0
 */
class Safe_Delete {

	/**
	 * Trash directory name.
	 *
	 * @var string
	 */
	private $trash_dir = 'wpha-trash';

	/**
	 * Trash retention period in days.
	 *
	 * @var int
	 */
	private $retention_days = 30;

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wpha_deleted_media';
	}

	/**
	 * Normalize a file path by resolving . and .. segments without requiring the file to exist.
	 *
	 * Unlike realpath(), this function works on non-existent paths and doesn't follow symlinks,
	 * making it safe for security validation.
	 *
	 * @since 1.1.0
	 *
	 * @param string $path The path to normalize.
	 * @return string|false Normalized path or false if path is invalid.
	 */
	private function normalize_path( string $path ) {
		// Reject paths with null bytes (potential security bypass).
		if ( strpos( $path, "\0" ) !== false ) {
			return false;
		}

		// Normalize directory separators.
		$path = str_replace( '\\', '/', $path );

		// Must be absolute path.
		if ( empty( $path ) || $path[0] !== '/' ) {
			return false;
		}

		$parts     = array();
		$segments  = explode( '/', $path );

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				if ( empty( $parts ) ) {
					return false; // Attempting to go above root.
				}
				array_pop( $parts );
			} else {
				$parts[] = $segment;
			}
		}

		return '/' . implode( '/', $parts );
	}

	/**
	 * Validate that a path is safely within a base directory.
	 *
	 * @since 1.1.0
	 *
	 * @param string $path     The path to validate.
	 * @param string $base_dir The base directory that must contain the path.
	 * @return bool True if path is safely within base_dir, false otherwise.
	 */
	private function is_path_within( string $path, string $base_dir ): bool {
		$normalized_path = $this->normalize_path( $path );
		$normalized_base = $this->normalize_path( $base_dir );

		if ( false === $normalized_path || false === $normalized_base ) {
			return false;
		}

		// Ensure base_dir ends with / for proper prefix matching.
		$normalized_base = rtrim( $normalized_base, '/' ) . '/';

		// Check if path starts with base_dir.
		return strpos( $normalized_path . '/', $normalized_base ) === 0;
	}

	/**
	 * Get the WordPress uploads directory path.
	 *
	 * @since 1.1.0
	 *
	 * @return string|false The uploads base directory or false on failure.
	 */
	private function get_uploads_directory() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}
		return $upload_dir['basedir'];
	}

	/**
	 * Generate a unique filename for trash storage to avoid collisions.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $original_name The original filename.
	 * @param string $prefix        Optional prefix for the filename.
	 * @return string Unique filename.
	 */
	private function generate_unique_trash_name( int $attachment_id, string $original_name, string $prefix = '' ): string {
		// Use microtime for higher precision and random bytes for uniqueness.
		$unique_id = sprintf(
			'%d_%s_%s',
			$attachment_id,
			substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 ),
			microtime( true )
		);

		if ( '' !== $prefix ) {
			$unique_id .= '_' . $prefix;
		}

		return $unique_id . '_' . sanitize_file_name( $original_name );
	}

	/**
	 * Prepare deletion by moving files to trash and storing metadata.
	 *
 * @since 1.0.0
 *
	 * @param array $attachment_ids Array of attachment IDs to prepare for deletion.
	 * @return array Result with success status and deletion ID.
	 */
	public function prepare_deletion( $attachment_ids ) {
		global $wpdb;

		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return array(
				'success' => false,
				'message' => 'No valid attachment IDs provided.',
			);
		}

		$prepared_items = array();
		$errors = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );

			// Verify attachment exists.
			$post = get_post( $attachment_id );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				$errors[] = sprintf( 'Attachment %d does not exist.', $attachment_id );
				continue;
			}

			// Get attachment file path.
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$errors[] = sprintf( 'File for attachment %d does not exist.', $attachment_id );
				continue;
			}

			// Prepare metadata to store.
			$metadata = array(
				'post_title' => $post->post_title,
				'post_excerpt' => $post->post_excerpt,
				'post_content' => $post->post_content,
				'post_mime_type' => $post->post_mime_type,
				'post_parent' => $post->post_parent,
				'guid' => $post->guid,
				'attachment_metadata' => wp_get_attachment_metadata( $attachment_id ),
				'alt_text' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'file_path' => $file_path,
			);

			// Create trash directory if it doesn't exist.
			$trash_base_dir = $this->get_trash_directory();
			if ( ! $trash_base_dir ) {
				$errors[] = 'Failed to create trash directory.';
				continue;
			}

			// Move main file to trash.
			$trash_file_path = $this->move_file_to_trash( $file_path, $attachment_id );
			if ( ! $trash_file_path ) {
				$errors[] = sprintf( 'Failed to move file for attachment %d to trash.', $attachment_id );
				continue;
			}

			// Move thumbnail files to trash.
			$thumbnails = $this->move_thumbnails_to_trash( $attachment_id, $metadata['attachment_metadata'] );
			$metadata['thumbnails_in_trash'] = $thumbnails;

			// Insert record into database.
			$inserted = $wpdb->insert(
				$this->table_name,
				array(
					'attachment_id' => $attachment_id,
					'file_path' => $trash_file_path,
					'metadata' => wp_json_encode( $metadata ),
					'deleted_at' => current_time( 'mysql' ),
					'permanent_at' => null,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				$deletion_id = $wpdb->insert_id;

				// Delete the WordPress attachment post (soft delete).
				wp_delete_attachment( $attachment_id, false );

				$prepared_items[] = array(
					'attachment_id' => $attachment_id,
					'deletion_id' => $deletion_id,
					'file_path' => $trash_file_path,
				);
			} else {
				$errors[] = sprintf( 'Failed to save deletion record for attachment %d.', $attachment_id );
			}
		}

		return array(
			'success' => ! empty( $prepared_items ),
			'prepared_items' => $prepared_items,
			'errors' => $errors,
			'message' => sprintf(
				'Prepared %d item(s) for deletion. %d error(s) occurred.',
				count( $prepared_items ),
				count( $errors )
			),
		);
	}

	/**
	 * Execute permanent deletion of items in trash.
	 *
 * @since 1.0.0
 *
	 * @param int $deletion_id Deletion ID to permanently remove.
	 * @return array Result with success status.
	 */
	public function execute_deletion( $deletion_id ) {
		global $wpdb;

		$deletion_id = absint( $deletion_id );

		// Get deletion record.
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$deletion_id
			),
			ARRAY_A
		);

		if ( ! $record ) {
			return array(
				'success' => false,
				'message' => 'Deletion record not found.',
			);
		}

		// Delete the file from trash.
		$file_path = $record['file_path'];
		$trash_dir = $this->get_trash_directory();

		if ( file_exists( $file_path ) ) {
			// Validate file is within trash directory for security.
			// Uses normalize_path() instead of realpath() to avoid TOCTOU and non-existent file issues.
			if ( ! $trash_dir || ! $this->is_path_within( $file_path, $trash_dir ) ) {
				return array(
					'success' => false,
					'message' => 'Invalid file path for deletion.',
				);
			}

			if ( ! unlink( $file_path ) ) {
				return array(
					'success' => false,
					'message' => 'Failed to delete file from trash.',
				);
			}
		}

		// Delete thumbnails from trash.
		$metadata = json_decode( $record['metadata'], true );
		if ( ! empty( $metadata['thumbnails_in_trash'] ) ) {
			foreach ( $metadata['thumbnails_in_trash'] as $thumb_path ) {
				if ( file_exists( $thumb_path ) ) {
					// Validate thumbnail is within trash directory for security.
					if ( $trash_dir && $this->is_path_within( $thumb_path, $trash_dir ) ) {
						unlink( $thumb_path );
					}
				}
			}
		}

		// Update record to mark as permanently deleted.
		$updated = $wpdb->update(
			$this->table_name,
			array(
				'permanent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $deletion_id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'success' => $updated !== false,
			'message' => $updated !== false
				? 'Item permanently deleted.'
				: 'Failed to update deletion record.',
		);
	}

	/**
	 * Restore a deleted attachment.
	 *
 * @since 1.0.0
 *
	 * @param int $deletion_id Deletion ID to restore.
	 * @return array Result with success status and restored attachment ID.
	 */
	public function restore_deleted( $deletion_id ) {
		global $wpdb;

		$deletion_id = absint( $deletion_id );

		// Get deletion record.
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d AND permanent_at IS NULL",
				$deletion_id
			),
			ARRAY_A
		);

		if ( ! $record ) {
			return array(
				'success' => false,
				'message' => 'Deletion record not found or already permanently deleted.',
			);
		}

		$metadata           = json_decode( $record['metadata'], true );
		$trash_file_path    = $record['file_path'];
		$original_file_path = $metadata['file_path'] ?? '';

		// Security: Validate paths to prevent directory traversal attacks.
		$trash_dir   = $this->get_trash_directory();
		$uploads_dir = $this->get_uploads_directory();

		if ( ! $trash_dir || ! $uploads_dir ) {
			return array(
				'success' => false,
				'message' => 'Unable to verify directory paths.',
			);
		}

		// Validate trash file is within trash directory.
		if ( ! $this->is_path_within( $trash_file_path, $trash_dir ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid trash file path.',
			);
		}

		// Validate original path is within uploads directory.
		if ( ! $this->is_path_within( $original_file_path, $uploads_dir ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid restore destination path.',
			);
		}

		// Restore main file.
		if ( ! file_exists( $trash_file_path ) ) {
			return array(
				'success' => false,
				'message' => 'File no longer exists in trash.',
			);
		}

		// Create directory if it doesn't exist.
		$original_dir = dirname( $original_file_path );
		if ( ! is_dir( $original_dir ) ) {
			wp_mkdir_p( $original_dir );
		}

		// Move file back to original location.
		if ( ! rename( $trash_file_path, $original_file_path ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to restore file from trash.',
			);
		}

		// Restore thumbnails.
		if ( ! empty( $metadata['thumbnails_in_trash'] ) ) {
			foreach ( $metadata['thumbnails_in_trash'] as $trash_thumb => $original_thumb ) {
				// Security: Validate thumbnail paths before restore.
				if ( ! $this->is_path_within( $trash_thumb, $trash_dir ) ) {
					continue; // Skip invalid trash paths.
				}
				if ( ! $this->is_path_within( $original_thumb, $uploads_dir ) ) {
					continue; // Skip invalid destination paths.
				}

				if ( file_exists( $trash_thumb ) ) {
					$original_thumb_dir = dirname( $original_thumb );
					if ( ! is_dir( $original_thumb_dir ) ) {
						wp_mkdir_p( $original_thumb_dir );
					}
					rename( $trash_thumb, $original_thumb );
				}
			}
		}

		// Recreate the attachment post.
		$attachment_data = array(
			'post_title' => $metadata['post_title'],
			'post_content' => $metadata['post_content'],
			'post_excerpt' => $metadata['post_excerpt'],
			'post_mime_type' => $metadata['post_mime_type'],
			'post_parent' => $metadata['post_parent'],
			'guid' => $metadata['guid'],
			'post_type' => 'attachment',
			'post_status' => 'inherit',
		);

		$attachment_id = wp_insert_post( $attachment_data );

		if ( is_wp_error( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to recreate attachment post.',
			);
		}

		// Update attachment metadata.
		update_attached_file( $attachment_id, $original_file_path );

		if ( ! empty( $metadata['attachment_metadata'] ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata['attachment_metadata'] );
		}

		if ( ! empty( $metadata['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
		}

		// Remove deletion record.
		$wpdb->delete(
			$this->table_name,
			array( 'id' => $deletion_id ),
			array( '%d' )
		);

		return array(
			'success' => true,
			'attachment_id' => $attachment_id,
			'message' => 'Attachment successfully restored.',
		);
	}

	/**
	 * Get queue of items pending permanent deletion.
	 *
 * @since 1.0.0
 *
	 * @return array List of items in deletion queue.
	 */
	public function get_deletion_queue() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$this->table_name}
			WHERE permanent_at IS NULL
			ORDER BY deleted_at DESC",
			ARRAY_A
		);

		$queue = array();
		foreach ( $results as $record ) {
			$metadata = json_decode( $record['metadata'], true );

			$deleted_at = strtotime( $record['deleted_at'] );
			$expiry_at = $deleted_at + ( $this->retention_days * DAY_IN_SECONDS );
			$days_remaining = max( 0, ceil( ( $expiry_at - time() ) / DAY_IN_SECONDS ) );

			$queue[] = array(
				'id' => $record['id'],
				'attachment_id' => $record['attachment_id'],
				'file_name' => basename( $metadata['file_path'] ),
				'mime_type' => $metadata['post_mime_type'] ?? 'unknown',
				'deleted_at' => $record['deleted_at'],
				'days_remaining' => $days_remaining,
				'can_restore' => $days_remaining > 0,
			);
		}

		return $queue;
	}

	/**
	 * Get history of permanently deleted items.
	 *
 * @since 1.0.0
 *
	 * @param int $limit Number of records to retrieve.
	 * @return array List of permanently deleted items.
	 */
	public function get_deleted_history( $limit = 100 ) {
		global $wpdb;

		$limit = absint( $limit );
		if ( $limit <= 0 ) {
			$limit = 100;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE permanent_at IS NOT NULL
				ORDER BY permanent_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$history = array();
		foreach ( $results as $record ) {
			$metadata = json_decode( $record['metadata'], true );

			$history[] = array(
				'id' => $record['id'],
				'attachment_id' => $record['attachment_id'],
				'file_name' => basename( $metadata['file_path'] ),
				'mime_type' => $metadata['post_mime_type'] ?? 'unknown',
				'deleted_at' => $record['deleted_at'],
				'permanent_at' => $record['permanent_at'],
			);
		}

		return $history;
	}

	/**
	 * Auto-purge items older than retention period.
	 *
 * @since 1.0.0
 *
	 * @return array Result with count of purged items.
	 */
	public function auto_purge_expired() {
		global $wpdb;

		$expiry_date = gmdate( 'Y-m-d H:i:s', time() - ( $this->retention_days * DAY_IN_SECONDS ) );

		// Get expired items.
		$expired_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name}
				WHERE permanent_at IS NULL
				AND deleted_at < %s",
				$expiry_date
			),
			ARRAY_A
		);

		$purged_count = 0;
		foreach ( $expired_items as $item ) {
			$result = $this->execute_deletion( $item['id'] );
			if ( $result['success'] ) {
				$purged_count++;
			}
		}

		return array(
			'success' => true,
			'purged_count' => $purged_count,
			'message' => sprintf( 'Auto-purged %d expired item(s).', $purged_count ),
		);
	}

	/**
	 * Get the trash directory path.
	 *
	 * @return string|false Trash directory path or false on failure.
	 */
	private function get_trash_directory() {
		$upload_dir = wp_upload_dir();
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$trash_dir = trailingslashit( $content_dir ) . $this->trash_dir;

		if ( ! is_dir( $trash_dir ) ) {
			if ( ! wp_mkdir_p( $trash_dir ) ) {
				return false;
			}

			// Add index.php for security.
			$index_file = $trash_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden.' );
			}
		}

		return $trash_dir;
	}

	/**
	 * Move a file to the trash directory.
	 *
	 * @param string $file_path Original file path.
	 * @param int    $attachment_id Attachment ID for unique naming.
	 * @return string|false New file path in trash or false on failure.
	 */
	private function move_file_to_trash( $file_path, $attachment_id ) {
		$trash_dir = $this->get_trash_directory();
		if ( ! $trash_dir ) {
			return false;
		}

		$file_name       = basename( $file_path );
		$unique_name     = $this->generate_unique_trash_name( (int) $attachment_id, $file_name );
		$trash_file_path = $trash_dir . '/' . $unique_name;

		if ( ! rename( $file_path, $trash_file_path ) ) {
			return false;
		}

		return $trash_file_path;
	}

	/**
	 * Move thumbnail files to trash.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $attachment_metadata Attachment metadata with sizes.
	 * @return array Array mapping trash paths to original paths.
	 */
	private function move_thumbnails_to_trash( $attachment_id, $attachment_metadata ) {
		$thumbnails = array();

		if ( empty( $attachment_metadata['sizes'] ) ) {
			return $thumbnails;
		}

		$trash_dir = $this->get_trash_directory();
		if ( ! $trash_dir ) {
			return $thumbnails;
		}

		$original_file = get_attached_file( $attachment_id );
		$base_dir = trailingslashit( dirname( $original_file ) );

		foreach ( $attachment_metadata['sizes'] as $size => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			$thumb_path = $base_dir . $size_data['file'];
			if ( ! file_exists( $thumb_path ) ) {
				continue;
			}

			$thumb_name        = basename( $thumb_path );
			$unique_thumb_name = $this->generate_unique_trash_name( (int) $attachment_id, $thumb_name, $size );
			$trash_thumb_path  = $trash_dir . '/' . $unique_thumb_name;

			if ( rename( $thumb_path, $trash_thumb_path ) ) {
				$thumbnails[ $trash_thumb_path ] = $thumb_path;
			}
		}

		return $thumbnails;
	}
}
