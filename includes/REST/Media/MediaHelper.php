<?php
/**
 * Media Helper
 *
 * Provides shared utility methods for media controllers.
 *
 * @package WPAdminHealth\REST\Media
 */

namespace WPAdminHealth\REST\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * MediaHelper class for shared media operations.
 *
 * @since 1.3.0
 */
class MediaHelper {

	/**
	 * Cached uploads base directory path.
	 *
	 * @since 1.6.0
	 * @var string|null
	 */
	private static ?string $uploads_basedir = null;

	/**
	 * Get the uploads base directory.
	 *
	 * @since 1.6.0
	 *
	 * @return string The uploads base directory path.
	 */
	private static function get_uploads_basedir(): string {
		if ( null === self::$uploads_basedir ) {
			$upload_dir = wp_upload_dir();
			$basedir    = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';

			self::$uploads_basedir = $basedir ? ( realpath( $basedir ) ?: $basedir ) : '';
		}

		return self::$uploads_basedir;
	}

	/**
	 * Validate that a file path is within the uploads directory.
	 *
	 * Security: Prevents path traversal and local file probing via
	 * tampered attachment metadata.
	 *
	 * @since 1.6.0
	 *
	 * @param string $file_path The file path to validate.
	 * @return bool True if path is valid and within uploads, false otherwise.
	 */
	private static function is_valid_upload_path( string $file_path ): bool {
		$file_path = trim( $file_path );

		if ( '' === $file_path ) {
			return false;
		}

		$uploads_basedir = self::get_uploads_basedir();
		if ( '' === $uploads_basedir ) {
			return false;
		}

		// Resolve the real path (resolves symlinks and ../ sequences).
		$real_path = realpath( $file_path );

		// If realpath fails (file doesn't exist), validate the directory portion.
		if ( false === $real_path ) {
			$dir_path = realpath( dirname( $file_path ) );
			if ( false === $dir_path ) {
				return false;
			}
			$real_path = $dir_path . DIRECTORY_SEPARATOR . basename( $file_path );
		}

		// Ensure the real path starts with the uploads directory.
		// Use strict comparison to prevent partial directory name matches.
		if ( 0 !== strpos( $real_path, $uploads_basedir . DIRECTORY_SEPARATOR ) && $real_path !== $uploads_basedir ) {
			return false;
		}

		return true;
	}

	/**
	 * Get attachment details with thumbnail URL.
	 *
	 * Consolidated method to avoid code duplication across media controllers.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Attachment details.
	 */
	public static function get_attachment_details( int $attachment_id ): array {
		$file_path = get_attached_file( $attachment_id );

		$url = wp_get_attachment_url( $attachment_id );
		$url = $url ? esc_url_raw( $url ) : '';

		$filename  = '';
		$file_size = 0;

		if ( $file_path && self::is_valid_upload_path( $file_path ) && file_exists( $file_path ) && is_file( $file_path ) ) {
			$filename = basename( $file_path );
			$size     = filesize( $file_path );
			$file_size = false === $size ? 0 : (int) $size;
		} elseif ( $url ) {
			$filename = basename( $url );
		}

		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $thumbnail_url ) {
			$thumbnail_url = $url;
		}
		$thumbnail_url = $thumbnail_url ? esc_url_raw( $thumbnail_url ) : '';

		$post = get_post( $attachment_id );

		$title = $post ? sanitize_text_field( $post->post_title ) : '';

		$date = $post ? sanitize_text_field( $post->post_date ) : '';
		if ( '' !== $date && function_exists( 'mysql_to_rfc3339' ) ) {
			$rfc3339 = mysql_to_rfc3339( $date );
			if ( false !== $rfc3339 ) {
				$date = $rfc3339;
			}
		}

		$mime_type = get_post_mime_type( $attachment_id );
		$mime_type = $mime_type ? sanitize_mime_type( (string) $mime_type ) : '';

		$type = self::get_media_type( $mime_type );

		return array(
			'id'                  => $attachment_id,
			'title'               => $title,
			'filename'            => sanitize_text_field( $filename ),
			'file_size'           => $file_size,
			'file_size_formatted' => size_format( $file_size ),
			'mime_type'           => $mime_type,
			'thumbnail_url'       => $thumbnail_url,
			'edit_link'           => esc_url_raw( admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ) ),

			// UI-friendly aliases.
			'size'      => $file_size,
			'thumbnail' => $thumbnail_url,
			'url'       => $url,
			'date'      => $date,
			'type'      => sanitize_key( $type ),
		);
	}

	/**
	 * Get attachment details for multiple IDs in a batch.
	 *
	 * Uses WordPress internal caching for efficient bulk loading.
	 *
	 * @since 1.3.0
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Array of attachment_id => details pairs.
	 */
	public static function get_attachment_details_batch( array $attachment_ids ): array {
		if ( empty( $attachment_ids ) ) {
			return array();
		}

		// Prime the post cache and meta cache in one query.
		_prime_post_caches( $attachment_ids, false, true );
		update_meta_cache( 'post', $attachment_ids );

		$details = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$details[ $attachment_id ] = self::get_attachment_details( $attachment_id );
		}

		return $details;
	}

	/**
	 * Determine media type from MIME type.
	 *
	 * @since 1.3.0
	 *
	 * @param string $mime_type MIME type.
	 * @return string Media type (image, video, audio, document).
	 */
	public static function get_media_type( string $mime_type ): string {
		if ( empty( $mime_type ) ) {
			return 'document';
		}

		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return 'image';
		}

		if ( strpos( $mime_type, 'video/' ) === 0 ) {
			return 'video';
		}

		if ( strpos( $mime_type, 'audio/' ) === 0 ) {
			return 'audio';
		}

		return 'document';
	}
}
