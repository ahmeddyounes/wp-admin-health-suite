<?php
/**
 * Safe Delete Class
 *
 * Implements two-step deletion for media files with recovery capability.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * SafeDelete class for managing two-step media deletion with recovery.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements SafeDeleteInterface.
 */
class SafeDelete implements SafeDeleteInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Settings instance (optional).
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings = null;

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
	private int $retention_days = 30;

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
	 * @since 1.3.0 Added ConnectionInterface dependency injection.
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection, ?SettingsInterface $settings = null ) {
		$this->connection = $connection;
		$this->settings   = $settings;
		$this->table_name = $this->connection->get_prefix() . 'wpha_deleted_media';
		$this->retention_days = $this->get_configured_retention_days();
	}

	/**
	 * Set settings instance.
	 *
	 * @since 1.6.0
	 *
	 * @param SettingsInterface|null $settings Settings instance.
	 * @return void
	 */
	public function set_settings( ?SettingsInterface $settings ): void {
		$this->settings        = $settings;
		$this->retention_days  = $this->get_configured_retention_days();
	}

	/**
	 * Get configured trash retention days.
	 *
	 * Falls back to defaults if settings are unavailable.
	 *
	 * @return int Retention days.
	 */
	private function get_configured_retention_days(): int {
		$days = 30;

		if ( $this->settings ) {
			$days = absint( $this->settings->get_setting( 'media_trash_retention_days', 30 ) );
		} else {
			$raw_settings = get_option( 'wpha_settings', array() );
			if ( is_array( $raw_settings ) ) {
				if ( isset( $raw_settings['media_trash_retention_days'] ) ) {
					$days = absint( $raw_settings['media_trash_retention_days'] );
				} elseif ( isset( $raw_settings['media_retention_days'] ) ) {
					$days = absint( $raw_settings['media_retention_days'] );
				}
			}
		}

		// Enforce sane bounds even if legacy/un-sanitized data exists.
		if ( $days < 7 ) {
			$days = 7;
		}
		if ( $days > 365 ) {
			$days = 365;
		}

		return $days;
	}

	/**
	 * Safely delete a file with TOCTOU protection.
	 *
	 * Performs final path validation immediately before deletion to prevent
	 * race conditions where a file could be replaced with a symlink.
	 *
	 * @since 1.2.0
	 *
	 * @param string $file_path The file to delete.
	 * @param string $base_dir  The directory the file must be within.
	 * @return bool True if file was deleted or doesn't exist, false on error.
	 */
	private function safe_unlink( string $file_path, string $base_dir ): bool {
		// Final validation: Check path is still within allowed directory.
		if ( ! $this->is_path_within( $file_path, $base_dir ) ) {
			return false;
		}

		// Security: Refuse to delete symlinks to prevent symlink attacks.
		// An attacker could replace a file with a symlink between validation and deletion.
		if ( is_link( $file_path ) ) {
			return false;
		}

		// If file doesn't exist, consider it a success.
		if ( ! file_exists( $file_path ) ) {
			return true;
		}

		// Final check: Verify real path is still within base directory.
		// realpath() resolves the actual path on disk at this exact moment.
		$real_path = realpath( $file_path );
		$real_base = realpath( $base_dir );

		if ( false === $real_path || false === $real_base ) {
			return false;
		}

		// Ensure the resolved path is still within the base directory.
		if ( strpos( $real_path . '/', rtrim( $real_base, '/' ) . '/' ) !== 0 ) {
			return false;
		}

		return @unlink( $file_path );
	}

	/**
	 * Safely rename/move a file with TOCTOU protection.
	 *
	 * Performs final path validation immediately before operation.
	 *
	 * @since 1.2.0
	 *
	 * @param string $source      Source file path.
	 * @param string $dest        Destination file path.
	 * @param string $source_base Directory the source must be within.
	 * @param string $dest_base   Directory the destination must be within.
	 * @return bool True on success, false on failure.
	 */
	private function safe_rename( string $source, string $dest, string $source_base, string $dest_base ): bool {
		// Final validation: Verify paths are within allowed directories.
		if ( ! $this->is_path_within( $source, $source_base ) ) {
			return false;
		}
		if ( ! $this->is_path_within( $dest, $dest_base ) ) {
			return false;
		}

		// Security: Refuse to operate on symlinks.
		if ( is_link( $source ) ) {
			return false;
		}

		// Verify source file exists.
		if ( ! file_exists( $source ) ) {
			return false;
		}

		// Final check: Verify real source path is within base directory.
		$real_source = realpath( $source );
		$real_base   = realpath( $source_base );

		if ( false === $real_source || false === $real_base ) {
			return false;
		}

		if ( strpos( $real_source . '/', rtrim( $real_base, '/' ) . '/' ) !== 0 ) {
			return false;
		}

		return @rename( $source, $dest );
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
		if ( empty( $path ) || '/' !== $path[0] ) {
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
	public function prepare_deletion( array $attachment_ids ): array {
		if ( empty( $attachment_ids ) ) {
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
			// Let the DB default permanent_at to NULL.
			$inserted = $this->connection->insert(
				$this->table_name,
				array(
					'attachment_id' => $attachment_id,
					'file_path'     => $trash_file_path,
					'metadata'      => wp_json_encode( $metadata ),
					'deleted_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				$deletion_id = $this->connection->get_insert_id();

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
	public function execute_deletion( int $deletion_id ): array {
		$deletion_id = absint( $deletion_id );

		// Get deletion record.
		$query = $this->connection->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$deletion_id
		);

		if ( null === $query ) {
			return array(
				'success' => false,
				'message' => 'Failed to prepare deletion query.',
			);
		}

		$record = $this->connection->get_row( $query, ARRAY_A );

		if ( ! $record ) {
			return array(
				'success' => false,
				'message' => 'Deletion record not found.',
			);
		}

		// Delete the file from trash.
		$file_path = $record['file_path'];
		$trash_dir = $this->get_trash_directory();

		if ( ! $trash_dir ) {
			return array(
				'success' => false,
				'message' => 'Unable to verify trash directory.',
			);
		}

		// Use safe_unlink() to prevent TOCTOU race conditions.
		// This performs final path validation and symlink checks immediately before deletion.
		if ( ! $this->safe_unlink( $file_path, $trash_dir ) ) {
			// safe_unlink returns true if file doesn't exist, so this is a real error.
			return array(
				'success' => false,
				'message' => 'Failed to delete file from trash (invalid path or security check failed).',
			);
		}

		// Delete thumbnails from trash.
		$metadata = json_decode( $record['metadata'], true );
		if ( ! empty( $metadata['thumbnails_in_trash'] ) ) {
			foreach ( $metadata['thumbnails_in_trash'] as $thumb_path ) {
				// Use safe_unlink() for TOCTOU protection on thumbnails too.
				$this->safe_unlink( $thumb_path, $trash_dir );
			}
		}

		// Update record to mark as permanently deleted.
		$updated = $this->connection->update(
			$this->table_name,
			array(
				'permanent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $deletion_id ),
			array( '%s' ),
			array( '%d' )
		);

		return array(
			'success' => false !== $updated,
			'message' => false !== $updated
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
	public function restore_deleted( int $deletion_id ): array {
		$deletion_id = absint( $deletion_id );

		// Get deletion record.
		$query = $this->connection->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d AND permanent_at IS NULL",
			$deletion_id
		);

		if ( null === $query ) {
			return array(
				'success' => false,
				'message' => 'Failed to prepare restore query.',
			);
		}

		$record = $this->connection->get_row( $query, ARRAY_A );

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

		// Security: Refuse to restore symlinks (potential TOCTOU attack).
		if ( is_link( $trash_file_path ) ) {
			return array(
				'success' => false,
				'message' => 'Cannot restore: file appears to be a symlink.',
			);
		}

		// Create directory if it doesn't exist.
		$original_dir = dirname( $original_file_path );
		if ( ! is_dir( $original_dir ) ) {
			wp_mkdir_p( $original_dir );
		}

		// Move file back to original location using safe_rename() for TOCTOU protection.
		if ( ! $this->safe_rename( $trash_file_path, $original_file_path, $trash_dir, $uploads_dir ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to restore file from trash (security check failed).',
			);
		}

		// Restore thumbnails.
		if ( ! empty( $metadata['thumbnails_in_trash'] ) ) {
			foreach ( $metadata['thumbnails_in_trash'] as $trash_thumb => $original_thumb ) {
				// Create directory for thumbnail if needed.
				$original_thumb_dir = dirname( $original_thumb );
				if ( ! is_dir( $original_thumb_dir ) ) {
					wp_mkdir_p( $original_thumb_dir );
				}

				// Use safe_rename() for TOCTOU protection on thumbnails.
				$this->safe_rename( $trash_thumb, $original_thumb, $trash_dir, $uploads_dir );
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
		$this->connection->delete(
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
	public function get_deletion_queue(): array {
		$results = $this->connection->get_results(
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
	public function get_deleted_history( int $limit = 50 ): array {
		$limit = absint( $limit );
		if ( $limit <= 0 ) {
			$limit = 100;
		}

		$query = $this->connection->prepare(
			"SELECT * FROM {$this->table_name}
			WHERE permanent_at IS NOT NULL
			ORDER BY permanent_at DESC
			LIMIT %d",
			$limit
		);

		if ( null === $query ) {
			return array();
		}

		$results = $this->connection->get_results( $query, ARRAY_A );

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
	public function auto_purge_expired(): array {
		$expiry_date = gmdate( 'Y-m-d H:i:s', time() - ( $this->retention_days * DAY_IN_SECONDS ) );

		// Get expired items.
		$query = $this->connection->prepare(
			"SELECT id FROM {$this->table_name}
			WHERE permanent_at IS NULL
			AND deleted_at < %s",
			$expiry_date
		);

		if ( null === $query ) {
			return array(
				'success' => false,
				'purged_count' => 0,
				'message' => 'Failed to prepare purge query.',
			);
		}

		$expired_items = $this->connection->get_results( $query, ARRAY_A );

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

		// Security: Validate source file is within uploads directory.
		$uploads_dir = $this->get_uploads_directory();
		if ( ! $uploads_dir || ! $this->is_path_within( $file_path, $uploads_dir ) ) {
			return false;
		}

		$file_name       = basename( $file_path );
		$unique_name     = $this->generate_unique_trash_name( (int) $attachment_id, $file_name );
		$trash_file_path = $trash_dir . '/' . $unique_name;

		// Use safe_rename() for TOCTOU protection.
		if ( ! $this->safe_rename( $file_path, $trash_file_path, $uploads_dir, $trash_dir ) ) {
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

		// Security: Get and validate uploads directory.
		$uploads_dir = $this->get_uploads_directory();
		if ( ! $uploads_dir ) {
			return $thumbnails;
		}

		$original_file = get_attached_file( $attachment_id );
		$base_dir = trailingslashit( dirname( $original_file ) );

		foreach ( $attachment_metadata['sizes'] as $size => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			// Security: Use basename() to prevent path traversal attacks.
			// Metadata could be tampered with via database compromise.
			$thumb_filename = basename( $size_data['file'] );
			$thumb_path     = $base_dir . $thumb_filename;

			// Validate thumbnail path is within uploads directory.
			if ( ! $this->is_path_within( $thumb_path, $uploads_dir ) ) {
				continue;
			}

			if ( ! file_exists( $thumb_path ) ) {
				continue;
			}

			$unique_thumb_name = $this->generate_unique_trash_name( (int) $attachment_id, $thumb_filename, $size );
			$trash_thumb_path  = $trash_dir . '/' . $unique_thumb_name;

			// Use safe_rename() for TOCTOU protection.
			if ( $this->safe_rename( $thumb_path, $trash_thumb_path, $uploads_dir, $trash_dir ) ) {
				$thumbnails[ $trash_thumb_path ] = $thumb_path;
			}
		}

		return $thumbnails;
	}
}
