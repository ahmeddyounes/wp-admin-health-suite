<?php
/**
 * Alt Text Checker Class
 *
 * Identifies images missing alt text and provides suggestions.
 * Supports decorative image handling and multilingual content via WPML/Polylang.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Integrations\Multilingual;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Alt Text Checker class for identifying images without alt text.
 *
 * Provides accessibility compliance checking for images including:
 * - Detection of missing alt text
 * - Handling of decorative images
 * - Multilingual support (WPML/Polylang)
 * - Coverage statistics and compliance reporting
 *
 * @since 1.0.0
 * @since 1.2.0 Implements AltTextCheckerInterface.
 * @since 1.4.0 Added decorative image handling and multilingual support.
 */
class AltTextChecker implements AltTextCheckerInterface {

	/**
	 * Meta key for marking images as decorative.
	 *
	 * @var string
	 */
	const META_DECORATIVE = '_wpha_decorative_image';

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
	 * Multilingual integration instance.
	 *
	 * @var Multilingual|null
	 */
	private ?Multilingual $multilingual = null;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Added ConnectionInterface dependency injection.
	 * @since 1.4.0 Added optional Multilingual integration.
	 *
	 * @param ConnectionInterface $connection   Database connection.
	 * @param ExclusionsInterface $exclusions   Exclusions manager.
	 * @param Multilingual|null   $multilingual Optional multilingual integration.
	 */
	public function __construct(
		ConnectionInterface $connection,
		ExclusionsInterface $exclusions,
		?Multilingual $multilingual = null
	) {
		$this->connection   = $connection;
		$this->exclusions   = $exclusions;
		$this->multilingual = $multilingual;
	}

	/**
	 * Find all images missing alt text.
	 *
	 * Returns list with: ID, filename, thumbnail URL, edit link.
	 * Excludes decorative images and considers multilingual alt text.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added decorative image exclusion and multilingual support.
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
				$attachment_id = absint( $attachment_id );

				// Skip excluded items.
				if ( $this->exclusions->is_excluded( $attachment_id ) ) {
					continue;
				}

				// Skip decorative images.
				if ( $this->is_decorative( $attachment_id ) ) {
					continue;
				}

				// Check alt text, including multilingual support.
				$alt_text = $this->get_alt_text( $attachment_id );

				// Check if alt text is missing or empty.
				if ( empty( $alt_text ) ) {
					$file_path = get_attached_file( $attachment_id );
					$filename  = $file_path ? basename( $file_path ) : '';

					// Get thumbnail URL.
					$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
					if ( ! $thumbnail_url ) {
						$thumbnail_url = wp_get_attachment_url( $attachment_id );
					}

					// Generate edit link.
					$edit_link = admin_url( 'post.php?post=' . $attachment_id . '&action=edit' );

					// Get post title for additional context.
					$post_title = get_the_title( $attachment_id );

					$attachment_url = wp_get_attachment_url( $attachment_id );

					$missing_alt[] = array(
						'id'            => $attachment_id,
						'title'         => $post_title,
						'url'           => $attachment_url ? $attachment_url : '',
						'filename'      => $filename,
						'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
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
	 * Check if an image is marked as decorative.
	 *
	 * Decorative images are intentionally exempt from alt text requirements
	 * as per WCAG 2.1 guidelines. They should use empty alt="" or
	 * role="presentation" / aria-hidden="true".
	 *
	 * @since 1.4.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if the image is marked as decorative.
	 */
	public function is_decorative( int $attachment_id ): bool {
		$is_decorative = get_post_meta( $attachment_id, self::META_DECORATIVE, true );
		return ! empty( $is_decorative );
	}

	/**
	 * Mark an image as decorative.
	 *
	 * @since 1.4.0
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $decorative    Whether the image is decorative.
	 * @return bool True on success, false on failure.
	 */
	public function set_decorative( int $attachment_id, bool $decorative ): bool {
		if ( $decorative ) {
			return (bool) update_post_meta( $attachment_id, self::META_DECORATIVE, '1' );
		}

		return delete_post_meta( $attachment_id, self::META_DECORATIVE );
	}

	/**
	 * Get alt text for an attachment, with multilingual support.
	 *
	 * If WPML or Polylang is active, checks all translations for alt text.
	 *
	 * @since 1.4.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return string The alt text, or empty string if none found.
	 */
	public function get_alt_text( int $attachment_id ): string {
		// Get the alt text for the requested attachment.
		$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( ! empty( $alt_text ) ) {
			return $alt_text;
		}

		// If multilingual is active, check translations for alt text.
		if ( null !== $this->multilingual && $this->multilingual->is_available() ) {
			$translations = $this->multilingual->get_translations( $attachment_id );

			foreach ( $translations as $translation_id ) {
				if ( $translation_id === $attachment_id ) {
					continue;
				}

				$translated_alt = get_post_meta( $translation_id, '_wp_attachment_image_alt', true );

				if ( ! empty( $translated_alt ) ) {
					return $translated_alt;
				}
			}
		}

		return '';
	}

	/**
	 * Get alt text for all languages of an attachment.
	 *
	 * @since 1.4.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<string, string> Associative array of language code => alt text.
	 */
	public function get_alt_text_by_language( int $attachment_id ): array {
		$result = array();

		// Default language alt text.
		$alt_text          = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$result['default'] = $alt_text ? $alt_text : '';

		// If multilingual is active, get translations.
		if ( null !== $this->multilingual && $this->multilingual->is_available() ) {
			$languages    = $this->multilingual->get_languages();
			$translations = $this->multilingual->get_translations( $attachment_id );

			foreach ( $translations as $translation_id ) {
				$lang = '';

				// Get the language code for this translation.
				foreach ( $languages as $language_code ) {
					// We need to check if this translation belongs to this language.
					// The Multilingual class uses private methods, so we check via the filter_attachments_by_language.
					$filtered = $this->multilingual->filter_attachments_by_language( array( $translation_id ), $language_code );
					if ( ! empty( $filtered ) ) {
						$lang = $language_code;
						break;
					}
				}

				if ( ! empty( $lang ) ) {
					$translated_alt  = get_post_meta( $translation_id, '_wp_attachment_image_alt', true );
					$result[ $lang ] = $translated_alt ? $translated_alt : '';
				}
			}
		}

		return $result;
	}

	/**
	 * Get alt text coverage percentage.
	 *
	 * Returns the percentage of images with alt text.
	 * Decorative images are excluded from the total count.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added decorative image exclusion from total count.
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
				'coverage_percentage' => 0.0,
			);
		}

		$total_images = absint( $this->connection->get_var( $total_images_query ) );

		if ( 0 === $total_images ) {
			return array(
				'total_images'        => 0,
				'images_with_alt'     => 0,
				'images_without_alt'  => 0,
				'coverage_percentage' => 0.0,
			);
		}

		// Count decorative images (these are intentionally exempt from alt text).
		$decorative_images_query = $this->connection->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$posts_table} p
			INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND pm.meta_key = %s
			AND pm.meta_value != ''",
			'attachment',
			'image/%',
			self::META_DECORATIVE
		);

		$decorative_images = 0;
		if ( null !== $decorative_images_query ) {
			$decorative_images = absint( $this->connection->get_var( $decorative_images_query ) );
		}

		// Effective total excludes decorative images.
		$effective_total = $total_images - $decorative_images;

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

		// Subtract decorative images that may also have alt text from the with_alt count.
		// (Decorative images should not contribute to coverage percentage).
		$images_with_alt     = max( 0, $images_with_alt - $decorative_images );
		$images_without_alt  = max( 0, $effective_total - $images_with_alt );
		$coverage_percentage = ( $effective_total > 0 )
			? round( ( $images_with_alt / $effective_total ) * 100, 2 )
			: 0.0;

		return array(
			'total_images'        => $effective_total,
			'images_with_alt'     => $images_with_alt,
			'images_without_alt'  => $images_without_alt,
			'coverage_percentage' => $coverage_percentage,
		);
	}

	/**
	 * Get detailed accessibility compliance report.
	 *
	 * Provides comprehensive statistics about image accessibility
	 * including WCAG compliance indicators.
	 *
	 * @since 1.4.0
	 *
	 * @return array{
	 *     total_images: int,
	 *     images_with_alt: int,
	 *     images_without_alt: int,
	 *     decorative_images: int,
	 *     coverage_percentage: float,
	 *     compliance_level: string,
	 *     recommendations: array<string>,
	 *     by_language: array<string, array{total: int, with_alt: int, without_alt: int}>
	 * } Comprehensive accessibility report.
	 */
	public function get_accessibility_report(): array {
		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		// Get raw image counts.
		$total_images_query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_type = %s
			AND post_mime_type LIKE %s",
			'attachment',
			'image/%'
		);

		$total_images = 0;
		if ( null !== $total_images_query ) {
			$total_images = absint( $this->connection->get_var( $total_images_query ) );
		}

		// Count decorative images.
		$decorative_query = $this->connection->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$posts_table} p
			INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND pm.meta_key = %s
			AND pm.meta_value != ''",
			'attachment',
			'image/%',
			self::META_DECORATIVE
		);

		$decorative_images = 0;
		if ( null !== $decorative_query ) {
			$decorative_images = absint( $this->connection->get_var( $decorative_query ) );
		}

		// Count images with alt text.
		$with_alt_query = $this->connection->prepare(
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
		if ( null !== $with_alt_query ) {
			$images_with_alt = absint( $this->connection->get_var( $with_alt_query ) );
		}

		// Calculate effective totals.
		$effective_total    = max( 0, $total_images - $decorative_images );
		$images_without_alt = max( 0, $effective_total - $images_with_alt );
		$coverage           = ( $effective_total > 0 )
			? round( ( $images_with_alt / $effective_total ) * 100, 2 )
			: 100.0;

		// Determine compliance level.
		$compliance_level = $this->determine_compliance_level( $coverage, $decorative_images, $total_images );

		// Generate recommendations.
		$recommendations = $this->generate_recommendations(
			$images_without_alt,
			$decorative_images,
			$coverage,
			$total_images
		);

		// Get multilingual breakdown if available.
		$by_language = $this->get_coverage_by_language();

		return array(
			'total_images'        => $effective_total,
			'images_with_alt'     => $images_with_alt,
			'images_without_alt'  => $images_without_alt,
			'decorative_images'   => $decorative_images,
			'coverage_percentage' => $coverage,
			'compliance_level'    => $compliance_level,
			'recommendations'     => $recommendations,
			'by_language'         => $by_language,
		);
	}

	/**
	 * Determine WCAG compliance level based on coverage.
	 *
	 * @since 1.4.0
	 *
	 * @param float $coverage          Coverage percentage.
	 * @param int   $decorative_count  Number of decorative images.
	 * @param int   $total_images      Total number of images.
	 * @return string Compliance level: 'excellent', 'good', 'needs_improvement', or 'critical'.
	 */
	private function determine_compliance_level( float $coverage, int $decorative_count, int $total_images ): string {
		// 100% coverage is excellent.
		if ( $coverage >= 100.0 ) {
			return 'excellent';
		}

		// 90%+ is good.
		if ( $coverage >= 90.0 ) {
			return 'good';
		}

		// 70-90% needs improvement.
		if ( $coverage >= 70.0 ) {
			return 'needs_improvement';
		}

		// Below 70% is critical.
		return 'critical';
	}

	/**
	 * Generate accessibility recommendations.
	 *
	 * @since 1.4.0
	 *
	 * @param int   $missing_alt      Number of images without alt text.
	 * @param int   $decorative_count Number of decorative images.
	 * @param float $coverage         Coverage percentage.
	 * @param int   $total_images     Total number of images.
	 * @return array<string> Array of recommendation strings.
	 */
	private function generate_recommendations(
		int $missing_alt,
		int $decorative_count,
		float $coverage,
		int $total_images
	): array {
		$recommendations = array();

		if ( $missing_alt > 0 ) {
			$recommendations[] = sprintf(
				/* translators: %d: number of images */
				_n(
					'Add alt text to %d image to improve accessibility.',
					'Add alt text to %d images to improve accessibility.',
					$missing_alt,
					'wp-admin-health-suite'
				),
				$missing_alt
			);
		}

		if ( 0 === $decorative_count && $total_images > 10 ) {
			$recommendations[] = __(
				'Consider marking purely decorative images (icons, separators, backgrounds) as decorative to improve compliance reporting accuracy.',
				'wp-admin-health-suite'
			);
		}

		if ( $coverage < 70.0 ) {
			$recommendations[] = __(
				'Your alt text coverage is below WCAG recommended levels. Consider using bulk suggestion to quickly add alt text to images.',
				'wp-admin-health-suite'
			);
		}

		if ( $coverage >= 100.0 && $total_images > 0 ) {
			$recommendations[] = __(
				'Your images are fully accessible. Consider periodic reviews to ensure new uploads maintain this standard.',
				'wp-admin-health-suite'
			);
		}

		return $recommendations;
	}

	/**
	 * Get alt text coverage broken down by language.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, array{total: int, with_alt: int, without_alt: int}> Coverage by language.
	 */
	private function get_coverage_by_language(): array {
		$result = array();

		if ( null === $this->multilingual || ! $this->multilingual->is_available() ) {
			return $result;
		}

		$languages = $this->multilingual->get_languages();

		if ( empty( $languages ) ) {
			return $result;
		}

		// Get all image attachments.
		$posts_table = $this->connection->get_posts_table();
		$query       = $this->connection->prepare(
			"SELECT ID FROM {$posts_table}
			WHERE post_type = %s
			AND post_mime_type LIKE %s",
			'attachment',
			'image/%'
		);

		if ( null === $query ) {
			return $result;
		}

		$all_attachments = $this->connection->get_col( $query );

		foreach ( $languages as $lang ) {
			$lang_attachments = $this->multilingual->filter_attachments_by_language( $all_attachments, $lang );

			$with_alt = 0;
			foreach ( $lang_attachments as $attachment_id ) {
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				if ( ! empty( $alt_text ) ) {
					++$with_alt;
				}
			}

			$total = count( $lang_attachments );

			$result[ $lang ] = array(
				'total'       => $total,
				'with_alt'    => $with_alt,
				'without_alt' => $total - $with_alt,
			);
		}

		return $result;
	}

	/**
	 * Bulk suggest alt text for images.
	 *
	 * Generates alt text suggestions from filename (clean up slugs).
	 * Also considers the post title as an alternative source.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added confidence level and improved suggestion sources.
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

			// Skip decorative images.
			if ( $this->is_decorative( $attachment_id ) ) {
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

			$filename    = basename( $file_path );
			$post_title  = get_the_title( $attachment_id );
			$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			// Generate suggestion and determine confidence.
			$suggestion_result = $this->generate_suggestion_with_confidence( $filename, $post_title );

			$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

			$suggestions[] = array(
				'id'            => $attachment_id,
				'filename'      => $filename,
				'current_alt'   => $current_alt ? $current_alt : '',
				'suggested_alt' => $suggestion_result['suggestion'],
				'suggestion'    => $suggestion_result['suggestion'],
				'confidence'    => $suggestion_result['confidence'],
				'source'        => $suggestion_result['source'],
				'thumbnail_url' => $thumbnail_url ? $thumbnail_url : '',
				'edit_link'     => admin_url( 'post.php?post=' . $attachment_id . '&action=edit' ),
			);
		}

		return $suggestions;
	}

	/**
	 * Generate alt text suggestion with confidence level.
	 *
	 * @since 1.4.0
	 *
	 * @param string $filename   The filename to process.
	 * @param string $post_title The post title.
	 * @return array{suggestion: string, confidence: string, source: string} Suggestion with metadata.
	 */
	private function generate_suggestion_with_confidence( string $filename, string $post_title ): array {
		// First, try using the post title if it's meaningful.
		$cleaned_title = $this->clean_title_for_alt( $post_title );
		if ( ! empty( $cleaned_title ) && strlen( $cleaned_title ) >= 3 ) {
			return array(
				'suggestion' => $cleaned_title,
				'confidence' => 'high',
				'source'     => 'title',
			);
		}

		// Fall back to filename-based suggestion.
		$from_filename = $this->generate_alt_from_filename( $filename );

		// Determine confidence based on suggestion quality.
		$confidence = 'low';
		if ( 'Image' !== $from_filename && strlen( $from_filename ) > 5 ) {
			$confidence = 'medium';

			// Higher confidence if the filename contains real words.
			if ( preg_match( '/[a-z]{3,}/i', $from_filename ) ) {
				$confidence = 'medium';
			}
		}

		return array(
			'suggestion' => $from_filename,
			'confidence' => $confidence,
			'source'     => 'filename',
		);
	}

	/**
	 * Clean a post title for use as alt text.
	 *
	 * @since 1.4.0
	 *
	 * @param string $title The post title.
	 * @return string Cleaned title suitable for alt text.
	 */
	private function clean_title_for_alt( string $title ): string {
		// Remove common auto-generated prefixes.
		$title = preg_replace( '/^(IMG_|DSC_|DCIM_|Photo_|Image_)\d*/i', '', $title );

		// Remove file extensions if present.
		$title = preg_replace( '/\.(jpg|jpeg|png|gif|webp|svg|bmp)$/i', '', $title );

		// If title is just numbers or very short, return empty.
		if ( preg_match( '/^\d+$/', $title ) || strlen( trim( $title ) ) < 3 ) {
			return '';
		}

		return trim( $title );
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

		// Remove scaled indicators added by WordPress (before separator replacement).
		$name = preg_replace( '/-scaled$/i', '', $name );

		// Remove common size indicators before separator replacement (e.g., 1920x1080).
		$name = preg_replace( '/[-_]?\d+x\d+[-_]?/i', '', $name );

		// Remove date patterns before separator replacement (e.g., 12-25-2024).
		$name = preg_replace( '/[-_]?\d{1,2}-\d{1,2}-\d{2,4}[-_]?/', '', $name );

		// Replace common separators with spaces.
		$name = str_replace( array( '-', '_', '.' ), ' ', $name );

		// Remove numbers that are likely timestamps or IDs (4+ digits).
		$name = preg_replace( '/\b\d{4,}\b/', '', $name );

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
