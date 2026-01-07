<?php
/**
 * Duplicate File Detector Class
 *
 * Detects duplicate media files in the WordPress media library.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Duplicate Detector class for finding duplicate media files.
 */
class Duplicate_Detector {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Find all duplicates using multiple detection methods.
	 *
	 * Combines exact file hash matching, filename pattern matching,
	 * and dimension/size similarity detection.
	 *
	 * @return array Array of duplicate groups.
	 */
	public function find_duplicates() {
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
	 * @return array Array of duplicate groups with original and copies.
	 */
	public function get_duplicate_groups() {
		$duplicates = $this->find_duplicates();
		$groups = array();

		foreach ( $duplicates as $hash => $attachment_ids ) {
			if ( count( $attachment_ids ) < 2 ) {
				continue;
			}

			// Determine the original (oldest by date or first uploaded).
			$original_id = $this->determine_original( $attachment_ids );
			$copies = array_values( array_diff( $attachment_ids, array( $original_id ) ) );

			$groups[] = array(
				'hash' => $hash,
				'original' => $original_id,
				'copies' => $copies,
				'count' => count( $attachment_ids ),
			);
		}

		return $groups;
	}

	/**
	 * Calculate potential storage savings if duplicates are removed.
	 *
	 * @return array Array with total savings in bytes and formatted size.
	 */
	public function get_potential_savings() {
		$groups = $this->get_duplicate_groups();
		$total_savings = 0;

		foreach ( $groups as $group ) {
			// Calculate savings from copies only.
			foreach ( $group['copies'] as $copy_id ) {
				$file_path = get_attached_file( $copy_id );
				if ( $file_path && file_exists( $file_path ) ) {
					$file_size = filesize( $file_path );
					$total_savings += $file_size;

					// Include thumbnail sizes.
					$metadata = wp_get_attachment_metadata( $copy_id );
					if ( ! empty( $metadata['sizes'] ) ) {
						$base_dir = trailingslashit( dirname( $file_path ) );
						foreach ( $metadata['sizes'] as $size_data ) {
							if ( ! empty( $size_data['file'] ) ) {
								$thumb_path = $base_dir . $size_data['file'];
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
			'bytes' => $total_savings,
			'formatted' => size_format( $total_savings ),
			'groups_count' => count( $groups ),
		);
	}

	/**
	 * Find duplicates by exact file hash (MD5).
	 *
	 * @return array Array of duplicate groups keyed by hash.
	 */
	private function find_duplicates_by_hash() {
		global $wpdb;

		$duplicates = array();
		$file_hashes = array();
		$batch_offset = 0;

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				LIMIT %d OFFSET %d",
				'attachment',
				$this->batch_size,
				$batch_offset
			);

			$attachments = $wpdb->get_col( $query );

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					continue;
				}

				$file_hash = md5_file( $file_path );
				if ( ! $file_hash ) {
					continue;
				}

				if ( isset( $file_hashes[ $file_hash ] ) ) {
					if ( ! isset( $duplicates[ $file_hash ] ) ) {
						$duplicates[ $file_hash ] = array( $file_hashes[ $file_hash ] );
					}
					$duplicates[ $file_hash ][] = $attachment_id;
				} else {
					$file_hashes[ $file_hash ] = $attachment_id;
				}
			}

			$batch_offset += $this->batch_size;
		}

		return $duplicates;
	}

	/**
	 * Find duplicates by filename pattern (e.g., image-1.jpg, image-2.jpg).
	 *
	 * @return array Array of duplicate groups keyed by base filename.
	 */
	private function find_duplicates_by_pattern() {
		global $wpdb;

		$duplicates = array();
		$filename_groups = array();
		$batch_offset = 0;

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				LIMIT %d OFFSET %d",
				'attachment',
				$this->batch_size,
				$batch_offset
			);

			$attachments = $wpdb->get_col( $query );

			if ( empty( $attachments ) ) {
				break;
			}

			foreach ( $attachments as $attachment_id ) {
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

			$batch_offset += $this->batch_size;
		}

		// Only keep groups with 2+ items and exclude thumbnails.
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
	 * @return array Array of duplicate groups keyed by dimension signature.
	 */
	private function find_duplicates_by_dimensions() {
		global $wpdb;

		$duplicates = array();
		$dimension_groups = array();
		$batch_offset = 0;

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
				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					continue;
				}

				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( empty( $metadata['width'] ) || empty( $metadata['height'] ) ) {
					continue;
				}

				$width = $metadata['width'];
				$height = $metadata['height'];
				$file_size = filesize( $file_path );

				// Create a dimension signature (width x height + size range).
				// Group by 5% size tolerance to catch re-compressed images.
				$size_bucket = floor( $file_size / ( $file_size * 0.05 ) );
				$signature = $width . 'x' . $height . '_' . $size_bucket;

				if ( ! isset( $dimension_groups[ $signature ] ) ) {
					$dimension_groups[ $signature ] = array();
				}
				$dimension_groups[ $signature ][] = $attachment_id;
			}

			$batch_offset += $this->batch_size;
		}

		// Only keep groups with 2+ items and exclude thumbnails.
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
	 * Handles patterns like:
	 * - image-1.jpg -> image.jpg
	 * - image-2.jpg -> image.jpg
	 * - image-scaled.jpg -> image.jpg
	 *
	 * @param string $filename The filename to parse.
	 * @return string|null Base filename or null if no pattern detected.
	 */
	private function extract_base_filename( $filename ) {
		// Remove extension.
		$info = pathinfo( $filename );
		$name = $info['filename'];
		$extension = isset( $info['extension'] ) ? $info['extension'] : '';

		// Pattern 1: image-1, image-2, etc.
		if ( preg_match( '/^(.+)-(\d+)$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		// Pattern 2: image-scaled.
		if ( preg_match( '/^(.+)-scaled$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		// Pattern 3: image-e1234567890 (edited timestamp).
		if ( preg_match( '/^(.+)-e\d+$/', $name, $matches ) ) {
			return $matches[1] . '.' . $extension;
		}

		return null;
	}

	/**
	 * Exclude thumbnails from a list of attachment IDs.
	 *
	 * Thumbnails are different sizes of the same original image.
	 * We identify them by checking if they share metadata structure
	 * or if one is in the other's sizes array.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Filtered array excluding thumbnails.
	 */
	private function exclude_thumbnails( $attachment_ids ) {
		$filtered = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$is_thumbnail = false;

			// Check if this ID is in any other attachment's sizes metadata.
			foreach ( $attachment_ids as $check_id ) {
				if ( $check_id === $attachment_id ) {
					continue;
				}

				$metadata = wp_get_attachment_metadata( $check_id );
				if ( empty( $metadata['sizes'] ) ) {
					continue;
				}

				// Get current attachment filename.
				$current_file = basename( get_attached_file( $attachment_id ) );

				// Check if current file is in the sizes array.
				foreach ( $metadata['sizes'] as $size_data ) {
					if ( ! empty( $size_data['file'] ) && $size_data['file'] === $current_file ) {
						$is_thumbnail = true;
						break 2;
					}
				}
			}

			if ( ! $is_thumbnail ) {
				$filtered[] = $attachment_id;
			}
		}

		return $filtered;
	}

	/**
	 * Determine which attachment is the original from a group.
	 *
	 * Uses upload date as the primary criterion (oldest = original).
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return int Original attachment ID.
	 */
	private function determine_original( $attachment_ids ) {
		$oldest_id = null;
		$oldest_date = null;

		foreach ( $attachment_ids as $attachment_id ) {
			$post = get_post( $attachment_id );
			if ( ! $post ) {
				continue;
			}

			$post_date = strtotime( $post->post_date );

			if ( null === $oldest_date || $post_date < $oldest_date ) {
				$oldest_date = $post_date;
				$oldest_id = $attachment_id;
			}
		}

		return $oldest_id ? $oldest_id : $attachment_ids[0];
	}

	/**
	 * Merge multiple duplicate group arrays, avoiding overlaps.
	 *
	 * @param array $group_arrays Array of duplicate group arrays.
	 * @return array Merged duplicate groups.
	 */
	private function merge_duplicate_groups( $group_arrays ) {
		$merged = array();
		$processed_ids = array();

		foreach ( $group_arrays as $groups ) {
			foreach ( $groups as $key => $attachment_ids ) {
				// Skip if any ID in this group has been processed.
				$has_overlap = false;
				foreach ( $attachment_ids as $id ) {
					if ( in_array( $id, $processed_ids, true ) ) {
						$has_overlap = true;
						break;
					}
				}

				if ( ! $has_overlap ) {
					$merged[ $key ] = $attachment_ids;
					$processed_ids = array_merge( $processed_ids, $attachment_ids );
				}
			}
		}

		return $merged;
	}
}
