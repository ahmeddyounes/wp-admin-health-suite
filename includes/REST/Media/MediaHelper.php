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
		$filename  = $file_path ? basename( $file_path ) : '';
		$file_size = $file_path && file_exists( $file_path ) ? filesize( $file_path ) : 0;

		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( ! $thumbnail_url ) {
			$thumbnail_url = wp_get_attachment_url( $attachment_id );
		}

		$url  = wp_get_attachment_url( $attachment_id );
		$post = get_post( $attachment_id );

		$title     = $post ? $post->post_title : '';
		$date      = $post ? $post->post_date : '';
		$mime_type = get_post_mime_type( $attachment_id );

		$type = 'document';
		if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
			$type = 'image';
		} elseif ( $mime_type && strpos( $mime_type, 'video/' ) === 0 ) {
			$type = 'video';
		}

		return array(
			'id'                  => $attachment_id,
			'title'               => $title,
			'filename'            => $filename,
			'file_size'           => $file_size,
			'file_size_formatted' => size_format( $file_size ),
			'mime_type'           => $mime_type,
			'thumbnail_url'       => $thumbnail_url,
			'edit_link'           => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),

			// UI-friendly aliases.
			'size'      => $file_size,
			'thumbnail' => $thumbnail_url,
			'url'       => $url,
			'date'      => $date,
			'type'      => $type,
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
