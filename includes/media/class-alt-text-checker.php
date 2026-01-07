<?php
/**
 * Alt Text Checker Class
 *
 * Identifies images missing alt text and provides suggestions.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Alt Text Checker class for identifying images without alt text.
 */
class Alt_Text_Checker {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Find all images missing alt text.
	 *
	 * Returns list with: ID, filename, thumbnail URL, edit link.
	 *
	 * @return array Array of images missing alt text with details.
	 */
	public function find_missing_alt_text() {
		global $wpdb;

		$missing_alt = array();
		$batch_offset = 0;
		$exclusions = new Exclusions();

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_mime_type LIKE %s
				LIMIT %d OFFSET %d",
				'attachment',
				'image/%',
				$this->batch_size,
				$batch_offset
			);

			$attachments = $wpdb->get_col( $query );

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				// Skip excluded items.
				if ( $exclusions->is_excluded( $attachment_id ) ) {
					continue;
				}
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

				// Check if alt text is missing or empty.
				if ( empty( $alt_text ) ) {
					$file_path = get_attached_file( $attachment_id );
					$filename = $file_path ? basename( $file_path ) : '';

					// Get thumbnail URL.
					$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
					if ( ! $thumbnail_url ) {
						$thumbnail_url = wp_get_attachment_url( $attachment_id );
					}

					// Generate edit link.
					$edit_link = admin_url( 'post.php?post=' . $attachment_id . '&action=edit' );

					$missing_alt[] = array(
						'id' => $attachment_id,
						'filename' => $filename,
						'thumbnail_url' => $thumbnail_url,
						'edit_link' => $edit_link,
					);
				}
			}

			$batch_offset += $this->batch_size;
		}

		return $missing_alt;
	}

	/**
	 * Get alt text coverage percentage.
	 *
	 * Returns the percentage of images with alt text.
	 *
	 * @return array Array with coverage statistics.
	 */
	public function get_alt_text_coverage() {
		global $wpdb;

		// Count all image attachments.
		$total_images_query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_mime_type LIKE %s",
			'attachment',
			'image/%'
		);

		$total_images = absint( $wpdb->get_var( $total_images_query ) );

		if ( 0 === $total_images ) {
			return array(
				'total_images' => 0,
				'images_with_alt' => 0,
				'images_without_alt' => 0,
				'coverage_percentage' => 0,
			);
		}

		// Count images with alt text.
		// We need to check the postmeta table for non-empty alt text.
		$images_with_alt_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND pm.meta_key = %s
			AND pm.meta_value != ''",
			'attachment',
			'image/%',
			'_wp_attachment_image_alt'
		);

		$images_with_alt = absint( $wpdb->get_var( $images_with_alt_query ) );
		$images_without_alt = $total_images - $images_with_alt;
		$coverage_percentage = ( $total_images > 0 ) ? round( ( $images_with_alt / $total_images ) * 100, 2 ) : 0;

		return array(
			'total_images' => $total_images,
			'images_with_alt' => $images_with_alt,
			'images_without_alt' => $images_without_alt,
			'coverage_percentage' => $coverage_percentage,
		);
	}

	/**
	 * Bulk suggest alt text for images.
	 *
	 * Generates alt text suggestions from filename (clean up slugs).
	 * AI service integration is a placeholder for future feature.
	 *
	 * @param bool $use_ai Whether to use AI service for suggestions (future feature, currently a placeholder).
	 * @return array Array of suggestions with attachment ID and suggested alt text.
	 */
	public function bulk_suggest_alt_text( $use_ai = false ) {
		global $wpdb;

		$suggestions = array();
		$batch_offset = 0;

		// AI feature is placeholder for now.
		if ( $use_ai ) {
			// Future feature: Integrate with AI service for better alt text suggestions.
			// For now, fall back to filename-based suggestions.
		}

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_mime_type LIKE %s
				LIMIT %d OFFSET %d",
				'attachment',
				'image/%',
				$this->batch_size,
				$batch_offset
			);

			$attachments = $wpdb->get_col( $query );

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

				// Only suggest for images without alt text.
				if ( empty( $alt_text ) ) {
					$file_path = get_attached_file( $attachment_id );
					if ( ! $file_path ) {
						continue;
					}

					$filename = basename( $file_path );
					$suggested_alt = $this->generate_alt_from_filename( $filename );

					$suggestions[] = array(
						'id' => $attachment_id,
						'filename' => $filename,
						'suggested_alt' => $suggested_alt,
						'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
						'edit_link' => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
					);
				}
			}

			$batch_offset += $this->batch_size;
		}

		return $suggestions;
	}

	/**
	 * Generate alt text suggestion from filename.
	 *
	 * Cleans up filename slugs to create readable alt text.
	 *
	 * @param string $filename The filename to process.
	 * @return string Suggested alt text.
	 */
	private function generate_alt_from_filename( $filename ) {
		// Remove file extension.
		$name = preg_replace( '/\.[^.]+$/', '', $filename );

		// Replace common separators with spaces.
		$name = str_replace( array( '-', '_', '.' ), ' ', $name );

		// Remove numbers that are likely date stamps or IDs.
		$name = preg_replace( '/\b\d{4,}\b/', '', $name );
		$name = preg_replace( '/\b\d{1,2}-\d{1,2}-\d{2,4}\b/', '', $name );

		// Remove common size indicators (e.g., 1920x1080, 800x600).
		$name = preg_replace( '/\b\d+x\d+\b/i', '', $name );

		// Remove scaled indicators added by WordPress.
		$name = preg_replace( '/-scaled$/', '', $name );

		// Clean up multiple spaces.
		$name = preg_replace( '/\s+/', ' ', $name );

		// Trim and capitalize.
		$name = trim( $name );
		$name = ucwords( strtolower( $name ) );

		// If the result is empty or too short, provide a generic suggestion.
		if ( empty( $name ) || strlen( $name ) < 3 ) {
			$name = 'Image';
		}

		return $name;
	}
}
