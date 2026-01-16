<?php
/**
 * Duplicate File Detector Class
 *
 * Detects duplicate media files in the WordPress media library.
 * Uses multiple detection strategies: exact hash matching, filename pattern matching,
 * and dimension/size similarity detection for comprehensive duplicate identification.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use Generator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Duplicate Detector class for finding duplicate media files.
 *
 * Implements a multi-strategy approach to duplicate detection:
 * 1. Hash-based: Exact file content matching using MD5 (most accurate)
 * 2. Pattern-based: WordPress filename patterns (image-1.jpg, image-scaled.jpg)
 * 3. Dimension-based: Same dimensions + similar file size (catches re-compressed images)
 *
 * Performance optimizations for large media libraries:
 * - Two-pass size/hash approach reduces I/O by only hashing same-size files
 * - Generator-based iteration prevents memory exhaustion
 * - Memory monitoring with automatic cleanup
 * - Batch processing with configurable batch sizes
 *
 * @since 1.0.0
 * @since 1.2.0 Implements DuplicateDetectorInterface.
 * @since 1.5.0 Added two-pass hash optimization and memory monitoring.
 */
class DuplicateDetector implements DuplicateDetectorInterface {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private int $batch_size = 50;

	/**
	 * Memory limit threshold percentage (stop at 80% usage).
	 *
	 * @var float
	 */
	private float $memory_threshold = 0.8;

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
	 * Check if memory usage is approaching the limit.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True if memory is running low, false otherwise.
	 */
	private function is_memory_low(): bool {
		$memory_limit = $this->get_memory_limit_bytes();
		if ( $memory_limit <= 0 ) {
			return false; // No limit or unable to determine.
		}

		$current_usage = memory_get_usage( true );
		return ( $current_usage / $memory_limit ) >= $this->memory_threshold;
	}

	/**
	 * Get the PHP memory limit in bytes.
	 *
	 * @since 1.5.0
	 *
	 * @return int Memory limit in bytes, or -1 if unlimited.
	 */
	private function get_memory_limit_bytes(): int {
		$memory_limit = ini_get( 'memory_limit' );

		if ( '-1' === $memory_limit ) {
			return -1; // Unlimited.
		}

		$value = (int) $memory_limit;
		$unit  = strtolower( substr( $memory_limit, -1 ) );

		switch ( $unit ) {
			case 'g':
				$value *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$value *= 1024 * 1024;
				break;
			case 'k':
				$value *= 1024;
				break;
		}

		return $value;
	}

	/**
	 * Clear internal caches to free memory.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	private function clear_caches(): void {
		// Clear WordPress object cache for attachments if available.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'posts' );
			wp_cache_flush_group( 'post_meta' );
		}
	}

	/**
	 * Generator to iterate over attachment IDs in batches.
	 *
	 * Uses database-level pagination to avoid loading all IDs into memory.
	 *
	 * @since 1.5.0
	 *
	 * @param string|null $mime_type_filter Optional MIME type filter (e.g., 'image/%').
	 * @return Generator<int> Yields attachment IDs one at a time.
	 */
	private function get_attachment_ids_generator( ?string $mime_type_filter = null ): Generator {
		$posts_table  = $this->connection->get_posts_table();
		$batch_offset = 0;

		while ( true ) {
			// Check memory before each batch.
			if ( $this->is_memory_low() ) {
				$this->clear_caches();

				// If still low after clearing, stop iteration.
				if ( $this->is_memory_low() ) {
					break;
				}
			}

			if ( null !== $mime_type_filter ) {
				$query = $this->connection->prepare(
					"SELECT ID FROM {$posts_table}
					WHERE post_type = %s
					AND post_mime_type LIKE %s
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'attachment',
					$mime_type_filter,
					$this->batch_size,
					$batch_offset
				);
			} else {
				$query = $this->connection->prepare(
					"SELECT ID FROM {$posts_table}
					WHERE post_type = %s
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'attachment',
					$this->batch_size,
					$batch_offset
				);
			}

			if ( null === $query ) {
				break;
			}

			$attachment_ids = $this->connection->get_col( $query );

			if ( empty( $attachment_ids ) ) {
				break;
			}

			foreach ( $attachment_ids as $attachment_id ) {
				yield (int) $attachment_id;
			}

			$batch_offset += $this->batch_size;
		}
	}

	/**
	 * Find all duplicates using multiple detection methods.
	 *
	 * Combines exact file hash matching, filename pattern matching,
	 * and dimension/size similarity detection.
	 *
	 * @since 1.0.0
	 *
	 * @param array $options Detection options (method, threshold, etc.).
	 * @return array Array of duplicate groups.
	 */
	public function find_duplicates( array $options = array() ): array {
		$duplicates = array();

		// Method 1: Exact file hash (md5_file).
		$hash_duplicates = $this->find_duplicates_by_hash();

		// Method 2: Filename pattern matching (image-1.jpg, image-2.jpg).
		$pattern_duplicates = $this->find_duplicates_by_pattern();

		// Method 3: Same dimensions + similar size.
		$dimension_duplicates = $this->find_duplicates_by_dimensions();

		// Merge all duplicate groups, avoiding overlaps.
		$duplicates = $this->merge_duplicate_groups(
			array(
				$hash_duplicates,
				$pattern_duplicates,
				$dimension_duplicates,
			)
		);

		return $duplicates;
	}

	/**
	 * Get duplicate groups organized by original and copies.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of duplicate groups with original and copies.
	 */
	public function get_duplicate_groups(): array {
		$duplicates = $this->find_duplicates();
		$groups     = array();

		foreach ( $duplicates as $hash => $attachment_ids ) {
			if ( count( $attachment_ids ) < 2 ) {
				continue;
			}

			// Filter out excluded items.
			$attachment_ids = $this->exclusions->filter_excluded( $attachment_ids );

			// Re-check count after filtering.
			if ( count( $attachment_ids ) < 2 ) {
				continue;
			}

			// Determine the original (oldest by date or first uploaded).
			$original_id = $this->determine_original( $attachment_ids );
			$copies      = array_values( array_diff( $attachment_ids, array( $original_id ) ) );

			$groups[] = array(
				'hash'     => $hash,
				'original' => $original_id,
				'copies'   => $copies,
				'count'    => count( $attachment_ids ),
			);
		}

		return $groups;
	}

	/**
	 * Calculate potential storage savings if duplicates are removed.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array with total savings in bytes and formatted size.
	 */
	public function get_potential_savings(): array {
		$groups        = $this->get_duplicate_groups();
		$total_savings = 0;

		foreach ( $groups as $group ) {
			// Calculate savings from copies only.
			foreach ( $group['copies'] as $copy_id ) {
				$file_path = get_attached_file( $copy_id );
				if ( $file_path && file_exists( $file_path ) ) {
					$file_size      = filesize( $file_path );
					$total_savings += $file_size;

					// Include thumbnail sizes.
					$metadata = wp_get_attachment_metadata( $copy_id );
					if ( ! empty( $metadata['sizes'] ) ) {
						$base_dir = trailingslashit( dirname( $file_path ) );
						foreach ( $metadata['sizes'] as $size_data ) {
							if ( ! empty( $size_data['file'] ) ) {
								// Security: Use basename() to prevent path traversal attacks.
								// Metadata could be tampered with via database compromise.
								$thumb_filename = basename( $size_data['file'] );
								$thumb_path     = $base_dir . $thumb_filename;
								if ( file_exists( $thumb_path ) ) {
									$total_savings += filesize( $thumb_path );
								}
							}
						}
					}
				}
			}
		}

		return array(
			'bytes'        => $total_savings,
			'formatted'    => size_format( $total_savings ),
			'groups_count' => count( $groups ),
		);
	}

	/**
	 * Find duplicates by exact file hash (MD5).
	 *
	 * Uses a two-pass approach for performance optimization:
	 * 1. First pass: Group files by size (fast, no I/O except stat calls)
	 * 2. Second pass: Hash only files with matching sizes (expensive, but targeted)
	 *
	 * This approach significantly reduces I/O operations for large media libraries
	 * where most files have unique sizes, as hashing is only performed on files
	 * that could potentially be duplicates.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Optimized with two-pass size/hash approach.
	 *
	 * @return array Array of duplicate groups keyed by hash.
	 */
	private function find_duplicates_by_hash(): array {
		$files_by_size = array();

		// First pass: Group files by size (very fast - only stat calls).
		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$file_size = filesize( $file_path );
			if ( false === $file_size ) {
				continue;
			}

			if ( ! isset( $files_by_size[ $file_size ] ) ) {
				$files_by_size[ $file_size ] = array();
			}
			$files_by_size[ $file_size ][] = array(
				'id'   => $attachment_id,
				'path' => $file_path,
			);
		}

		// Second pass: Only hash files that share the same size.
		$duplicates  = array();
		$file_hashes = array();

		foreach ( $files_by_size as $size => $files ) {
			// Skip unique-sized files (no possible duplicates).
			if ( count( $files ) < 2 ) {
				continue;
			}

			// Hash each file with this size.
			foreach ( $files as $file_info ) {
				$file_hash = md5_file( $file_info['path'] );
				if ( false === $file_hash ) {
					continue;
				}

				if ( isset( $file_hashes[ $file_hash ] ) ) {
					if ( ! isset( $duplicates[ $file_hash ] ) ) {
						$duplicates[ $file_hash ] = array( $file_hashes[ $file_hash ] );
					}
					$duplicates[ $file_hash ][] = $file_info['id'];
				} else {
					$file_hashes[ $file_hash ] = $file_info['id'];
				}

				// Check memory during intensive hashing.
				if ( $this->is_memory_low() ) {
					$this->clear_caches();
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Find duplicates by filename pattern (e.g., image-1.jpg, image-2.jpg).
	 *
	 * Detects WordPress-style filename patterns that indicate potential duplicates:
	 * - Numbered uploads: image-1.jpg, image-2.jpg (WordPress auto-numbering)
	 * - Scaled images: image-scaled.jpg (big image handling)
	 * - Edited images: image-e1234567890.jpg (timestamp-based edits)
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Uses generator-based iteration.
	 *
	 * @return array Array of duplicate groups keyed by base filename.
	 */
	private function find_duplicates_by_pattern(): array {
		$filename_groups = array();

		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$filename = basename( $file_path );

			// Check for WordPress scaled/numbered patterns.
			// Patterns: image-1.jpg, image-2.jpg or image-scaled.jpg.
			$base_name = $this->extract_base_filename( $filename );

			if ( $base_name ) {
				if ( ! isset( $filename_groups[ $base_name ] ) ) {
					$filename_groups[ $base_name ] = array();
				}
				$filename_groups[ $base_name ][] = $attachment_id;
			}
		}

		// Only keep groups with 2+ items and exclude thumbnails.
		$duplicates = array();
		foreach ( $filename_groups as $base_name => $attachment_ids ) {
			if ( count( $attachment_ids ) >= 2 ) {
				// Filter out thumbnails (different sizes of same image).
				$filtered_ids = $this->exclude_thumbnails( $attachment_ids );
				if ( count( $filtered_ids ) >= 2 ) {
					$duplicates[ 'pattern_' . $base_name ] = $filtered_ids;
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Find duplicates by same dimensions and similar size.
	 *
	 * This method catches duplicates that may have been re-compressed or saved
	 * with slightly different quality settings. Files are grouped by:
	 * - Exact width and height dimensions
	 * - Size bucket (~5% tolerance using logarithmic scaling)
	 *
	 * The 5% tolerance accounts for:
	 * - Different JPEG quality levels
	 * - Re-saves through different image editors
	 * - Minor metadata differences
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Uses generator-based iteration.
	 *
	 * @return array Array of duplicate groups keyed by dimension signature.
	 */
	private function find_duplicates_by_dimensions(): array {
		$dimension_groups = array();

		// Only process images for dimension-based detection.
		foreach ( $this->get_attachment_ids_generator( 'image/%' ) as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
				continue;
			}

			$width     = (int) $metadata['width'];
			$height    = (int) $metadata['height'];
			$file_size = filesize( $file_path );

			if ( false === $file_size || 0 === $file_size ) {
				continue;
			}

			// Create a dimension signature (width x height + size range).
			// Group by ~5% size tolerance buckets to catch re-compressed images.
			// Using log scale: floor(log(size) / log(1.05)) groups files within ~5% of each other.
			$size_bucket = (int) floor( log( $file_size ) / log( 1.05 ) );
			$signature   = $width . 'x' . $height . '_' . $size_bucket;

			if ( ! isset( $dimension_groups[ $signature ] ) ) {
				$dimension_groups[ $signature ] = array();
			}
			$dimension_groups[ $signature ][] = $attachment_id;
		}

		// Only keep groups with 2+ items and exclude thumbnails.
		$duplicates = array();
		foreach ( $dimension_groups as $signature => $attachment_ids ) {
			if ( count( $attachment_ids ) >= 2 ) {
				// Filter out thumbnails.
				$filtered_ids = $this->exclude_thumbnails( $attachment_ids );
				if ( count( $filtered_ids ) >= 2 ) {
					$duplicates[ 'dimension_' . $signature ] = $filtered_ids;
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Extract base filename from numbered patterns.
	 *
	 * Handles WordPress-style patterns:
	 * - image-1.jpg -> image.jpg (WordPress auto-numbering for same-name uploads)
	 * - image-2.jpg -> image.jpg
	 * - image-scaled.jpg -> image.jpg (WordPress big image threshold scaling)
	 * - image-e1234567890.jpg -> image.jpg (WordPress image editor timestamp)
	 * - image-150x150.jpg -> null (This is a thumbnail size, handled separately)
	 *
	 * Note: Returns null for files that don't match WordPress patterns.
	 * This ensures only actual WordPress-generated variants are grouped together.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Added dimension pattern exclusion to avoid false positives with thumbnails.
	 *
	 * @param string $filename The filename to parse.
	 * @return string|null Base filename or null if no pattern detected.
	 */
	private function extract_base_filename( string $filename ): ?string {
		// Remove extension.
		$info      = pathinfo( $filename );
		$name      = $info['filename'];
		$extension = isset( $info['extension'] ) ? $info['extension'] : '';

		// Skip dimension patterns (thumbnails like image-150x150.jpg).
		// These are legitimate different sizes, not duplicates.
		if ( preg_match( '/^(.+)-\d+x\d+$/', $name ) ) {
			return null;
		}

		// Pattern 1: image-1, image-2, etc. (WordPress auto-numbering).
		if ( preg_match( '/^(.+)-(\d+)$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		// Pattern 2: image-scaled (WordPress big image handling since 5.3).
		if ( preg_match( '/^(.+)-scaled$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		// Pattern 3: image-e1234567890 (WordPress image editor timestamp).
		if ( preg_match( '/^(.+)-e\d+$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		// Pattern 4: image-rotated (WordPress image rotation).
		if ( preg_match( '/^(.+)-rotated$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		return null;
	}

	/**
	 * Exclude thumbnails from a list of attachment IDs.
	 *
	 * Thumbnails are different sizes of the same original image generated
	 * by WordPress. We identify them by checking if a file appears in
	 * another attachment's sizes metadata array.
	 *
	 * This prevents false positives where WordPress-generated thumbnails
	 * might be incorrectly flagged as duplicate files.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Optimized to pre-cache all filenames and metadata.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<int> Filtered array excluding thumbnails.
	 */
	private function exclude_thumbnails( array $attachment_ids ): array {
		if ( count( $attachment_ids ) < 2 ) {
			return $attachment_ids;
		}

		// Pre-cache all filenames and thumbnail files for efficiency.
		$attachment_files = array();
		$all_thumb_files  = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path ) {
				continue;
			}

			$filename                           = basename( $file_path );
			$attachment_files[ $attachment_id ] = $filename;

			// Collect all thumbnail filenames from this attachment.
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_data ) {
					if ( ! empty( $size_data['file'] ) ) {
						$all_thumb_files[ $size_data['file'] ] = $attachment_id;
					}
				}
			}
		}

		// Filter out files that appear as thumbnails of other attachments.
		$filtered = array();
		foreach ( $attachment_ids as $attachment_id ) {
			if ( ! isset( $attachment_files[ $attachment_id ] ) ) {
				continue;
			}

			$filename = $attachment_files[ $attachment_id ];

			// Check if this file is a thumbnail of another attachment in the group.
			if ( isset( $all_thumb_files[ $filename ] ) && $all_thumb_files[ $filename ] !== $attachment_id ) {
				// This file is a thumbnail of another attachment, skip it.
				continue;
			}

			$filtered[] = $attachment_id;
		}

		return $filtered;
	}

	/**
	 * Determine which attachment is the original from a group.
	 *
	 * Uses upload date as the primary criterion (oldest = original).
	 * This heuristic assumes the first uploaded file is the "source"
	 * and subsequent uploads are copies or variations.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return int Original attachment ID.
	 */
	private function determine_original( array $attachment_ids ): int {
		$oldest_id   = null;
		$oldest_date = null;

		foreach ( $attachment_ids as $attachment_id ) {
			$post = get_post( $attachment_id );
			if ( ! $post ) {
				continue;
			}

			$post_date = strtotime( $post->post_date );

			if ( null === $oldest_date || $post_date < $oldest_date ) {
				$oldest_date = $post_date;
				$oldest_id   = $attachment_id;
			}
		}

		return $oldest_id ?? $attachment_ids[0];
	}

	/**
	 * Merge multiple duplicate group arrays, avoiding overlaps.
	 *
	 * When the same attachment appears in multiple detection method results
	 * (e.g., both hash-based and pattern-based), priority is given to hash-based
	 * detection as it's the most accurate (exact content match).
	 *
	 * Groups are processed in order:
	 * 1. Hash duplicates (highest confidence - exact content match)
	 * 2. Pattern duplicates (medium confidence - WordPress naming convention)
	 * 3. Dimension duplicates (lower confidence - similar properties)
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Uses array key lookup for O(1) overlap detection.
	 *
	 * @param array<array<string, array<int>>> $group_arrays Array of duplicate group arrays.
	 * @return array<string, array<int>> Merged duplicate groups.
	 */
	private function merge_duplicate_groups( array $group_arrays ): array {
		$merged        = array();
		$processed_ids = array(); // Use keys for O(1) lookup.

		foreach ( $group_arrays as $groups ) {
			foreach ( $groups as $key => $attachment_ids ) {
				// Skip if any ID in this group has been processed.
				$has_overlap = false;
				foreach ( $attachment_ids as $id ) {
					if ( isset( $processed_ids[ $id ] ) ) {
						$has_overlap = true;
						break;
					}
				}

				if ( ! $has_overlap ) {
					$merged[ $key ] = $attachment_ids;
					// Mark all IDs as processed.
					foreach ( $attachment_ids as $id ) {
						$processed_ids[ $id ] = true;
					}
				}
			}
		}

		return $merged;
	}
}
