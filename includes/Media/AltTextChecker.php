<?php
/**
 * Alt Text Checker Class
 *
 * Identifies images missing alt text and provides suggestions.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Alt Text Checker class for identifying images without alt text.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements AltTextCheckerInterface.
 */
class AltTextChecker implements AltTextCheckerInterface {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Exclusions manager instance.
	 *
	 * @var ExclusionsInterface
	 */
	private ExclusionsInterface $exclusions;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Added ConnectionInterface dependency injection.
	 *
	 * @param ConnectionInterface $connection Database connection.
	 * @param ExclusionsInterface $exclusions Exclusions manager.
	 */
	public function __construct( ConnectionInterface $connection, ExclusionsInterface $exclusions ) {
		$this->connection = $connection;
		$this->exclusions = $exclusions;
	}

	/**
	 * Find all images missing alt text.
	 *
	 * Returns list with: ID, filename, thumbnail URL, edit link.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of results.
	 * @return array Array of images missing alt text with details.
	 */
	public function find_missing_alt_text( int $limit = 100 ): array {
		$missing_alt   = array();
		$missing_count = 0;
		$batch_offset  = 0;

		$posts_table = $this->connection->get_posts_table();

		while ( $missing_count < $limit ) {
			$query = $this->connection->prepare(
				"SELECT ID FROM {$posts_table}
				WHERE post_type = %s
				AND post_mime_type LIKE %s
				LIMIT %d OFFSET %d",
				'attachment',
				'image/%',
				$this->batch_size,
				$batch_offset
			);

			if ( null === $query ) {
				break;
			}

			$attachments = $this->connection->get_col( $query );

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				// Skip excluded items.
				if ( $this->exclusions->is_excluded( $attachment_id ) ) {
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
						'id'            => $attachment_id,
						'filename'      => $filename,
						'thumbnail_url' => $thumbnail_url,
						'edit_link'     => $edit_link,
					);
					++$missing_count;

					// Check if we've reached the limit.
					if ( $missing_count >= $limit ) {
						break;
					}
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
	 * @since 1.0.0
	 *
	 * @return array Array with coverage statistics.
	 */
	public function get_alt_text_coverage(): array {
		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		// Count all image attachments.
		$total_images_query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_type = %s
			AND post_mime_type LIKE %s",
			'attachment',
			'image/%'
		);

		if ( null === $total_images_query ) {
			return array(
				'total_images'        => 0,
				'images_with_alt'     => 0,
				'images_without_alt'  => 0,
				'coverage_percentage' => 0,
			);
		}

		$total_images = absint( $this->connection->get_var( $total_images_query ) );

		if ( 0 === $total_images ) {
			return array(
				'total_images'        => 0,
				'images_with_alt'     => 0,
				'images_without_alt'  => 0,
				'coverage_percentage' => 0,
			);
		}

		// Count images with alt text.
		// We need to check the postmeta table for non-empty alt text.
		$images_with_alt_query = $this->connection->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$posts_table} p
			INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND pm.meta_key = %s
			AND pm.meta_value != ''",
			'attachment',
			'image/%',
			'_wp_attachment_image_alt'
		);

		$images_with_alt = 0;
		if ( null !== $images_with_alt_query ) {
			$images_with_alt = absint( $this->connection->get_var( $images_with_alt_query ) );
		}
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
	 *
	 * @since 1.0.0
	 *
	 * @param array $attachment_ids Array of attachment IDs to suggest alt text for.
	 * @return array Array of suggestions per attachment.
	 */
	public function bulk_suggest_alt_text( array $attachment_ids ): array {
		$suggestions = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id ) {
				continue;
			}

			// Skip excluded items.
			if ( $this->exclusions->is_excluded( $attachment_id ) ) {
				continue;
			}

			// Verify it's an image attachment.
			$mime_type = get_post_mime_type( $attachment_id );
			if ( ! $mime_type || strpos( $mime_type, 'image/' ) !== 0 ) {
				continue;
			}

			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path ) {
				continue;
			}

			$filename = basename( $file_path );
			$suggested_alt = $this->generate_alt_from_filename( $filename );
			$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			$suggestions[] = array(
				'id'            => $attachment_id,
				'filename'      => $filename,
				'current_alt'   => $current_alt ?: '',
				'suggested_alt' => $suggested_alt,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
				'edit_link'     => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
			);
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
