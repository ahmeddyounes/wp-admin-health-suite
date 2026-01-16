<?php
/**
 * Media Scanner Class
 *
 * Scans and analyzes WordPress media library for optimization opportunities.
 * Uses generator-based batch processing for memory efficiency with large libraries.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use Generator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Media Scanner class for analyzing media library health and statistics.
 *
 * @since 1.0.0
 * @since 1.3.0 Added ConnectionInterface dependency injection.
 * @since 1.4.0 Added generator-based batch processing for memory efficiency.
 */
class Scanner implements ScannerInterface {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Transient key prefix for scan results.
	 *
	 * @var string
	 */
	private $transient_prefix = 'wp_admin_health_media_scan_';

	/**
	 * Exclusions manager instance.
	 *
	 * @var ExclusionsInterface
	 */
	private ExclusionsInterface $exclusions;

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Cached media count to avoid repeated queries.
	 *
	 * @var int|null
	 */
	private ?int $cached_media_count = null;

	/**
	 * Memory limit threshold percentage (stop at 80% usage).
	 *
	 * @var float
	 */
	private float $memory_threshold = 0.8;

	/**
	 * Progress update interval (update every N items).
	 *
	 * @var int
	 */
	private int $progress_update_interval = 100;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Added ConnectionInterface parameter.
	 *
	 * @param ExclusionsInterface $exclusions Exclusions manager.
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ExclusionsInterface $exclusions, ConnectionInterface $connection ) {
		$this->exclusions = $exclusions;
		$this->connection = $connection;
	}

	/**
	 * Check if memory usage is approaching the limit.
	 *
	 * @since 1.4.0
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
	 * @since 1.4.0
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
	 * @since 1.4.0
	 *
	 * @return void
	 */
	private function clear_caches(): void {
		$this->cached_media_count = null;

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
	 * @since 1.4.0
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
	 * Update scan progress in transient.
	 *
	 * Only updates at defined intervals to reduce database writes.
	 *
	 * @since 1.4.0
	 *
	 * @param int $processed Number of items processed.
	 * @param int $total     Total number of items.
	 * @return void
	 */
	private function update_progress( int $processed, int $total ): void {
		// Only update at intervals to reduce database writes.
		if ( 0 !== $processed % $this->progress_update_interval && $processed !== $total ) {
			return;
		}

		if ( $total > 0 ) {
			$progress = min( 100, (int) ( ( $processed / $total ) * 100 ) );
			set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Scan all media attachments in batches.
	 *
	 * Uses single-pass scanning for efficiency, collecting all statistics
	 * in one iteration through the media library.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Optimized with single-pass scanning and generator usage.
	 *
	 * @return array {
	 *     Scan results with counts and statistics.
	 *
	 *     @type int    $total_count        Total number of media files.
	 *     @type int    $total_size         Total size in bytes.
	 *     @type int    $unused_count       Number of unused files.
	 *     @type int    $duplicate_count    Number of duplicate files.
	 *     @type int    $large_files_count  Number of large files.
	 *     @type int    $missing_alt_count  Number of files missing alt text.
	 *     @type string $scanned_at         Timestamp of scan.
	 *     @type bool   $memory_limited     True if scan was cut short due to memory.
	 * }
	 *
	 * @example
	 * // Scan all media and get results
	 * $scanner = new Media\Scanner();
	 * $results = $scanner->scan_all_media();
	 * echo "Found " . $results['total_count'] . " media files";
	 * echo "Unused files: " . $results['unused_count'];
	 */
	public function scan_all_media() {
		// Start scan.
		set_transient( $this->transient_prefix . 'progress', 0, HOUR_IN_SECONDS );

		// Get total count once and cache it.
		$total_count = $this->get_media_count();

		// Single-pass scan: collect all statistics in one iteration.
		$total_size        = 0;
		$unused_count      = 0;
		$large_files_count = 0;
		$missing_alt_count = 0;
		$file_hashes       = array();
		$duplicate_count   = 0;
		$processed         = 0;
		$memory_limited    = false;

		// Large file threshold: 1MB in bytes.
		$large_threshold = 1024 * 1024;

		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			++$processed;

			// Update progress periodically.
			$this->update_progress( $processed, $total_count );

			// Get file info.
			$file_path = get_attached_file( $attachment_id );
			$file_size = 0;

			if ( $file_path && file_exists( $file_path ) ) {
				$file_size   = filesize( $file_path );
				$total_size += $file_size;

				// Add thumbnail sizes.
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'] ) ) {
					$base_dir = trailingslashit( dirname( $file_path ) );

					foreach ( $metadata['sizes'] as $size_data ) {
						if ( ! empty( $size_data['file'] ) ) {
							// Security: Use basename() to prevent path traversal attacks.
							$thumb_filename = basename( $size_data['file'] );
							$thumb_path     = $base_dir . $thumb_filename;
							if ( file_exists( $thumb_path ) ) {
								$total_size += filesize( $thumb_path );
							}
						}
					}
				}

				// Check for large files.
				if ( $file_size >= $large_threshold ) {
					++$large_files_count;
				}

				// Check for duplicates using hash.
				// Use smaller chunk for initial comparison to optimize I/O.
				$file_hash = md5_file( $file_path );
				if ( isset( $file_hashes[ $file_hash ] ) ) {
					++$duplicate_count;
				} else {
					$file_hashes[ $file_hash ] = $attachment_id;
				}
			}

			// Check for unused media (skip exclusions).
			if ( ! $this->exclusions->is_excluded( $attachment_id ) ) {
				if ( ! $this->is_attachment_used( $attachment_id ) ) {
					++$unused_count;
				}
			}

			// Check for missing alt text (images only).
			$post = get_post( $attachment_id );
			if ( $post && 0 === strpos( $post->post_mime_type, 'image/' ) ) {
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				if ( empty( $alt_text ) ) {
					++$missing_alt_count;
				}
			}

			// Check memory and potentially break early.
			if ( $this->is_memory_low() ) {
				$this->clear_caches();
				if ( $this->is_memory_low() ) {
					$memory_limited = true;
					break;
				}
			}
		}

		$results = array(
			'total_count'       => $total_count,
			'total_size'        => $total_size,
			'unused_count'      => $unused_count,
			'duplicate_count'   => $duplicate_count,
			'large_files_count' => $large_files_count,
			'missing_alt_count' => $missing_alt_count,
			'scanned_at'        => current_time( 'mysql' ),
			'memory_limited'    => $memory_limited,
		);

		// Store results in transient.
		set_transient( $this->transient_prefix . 'results', $results, DAY_IN_SECONDS );
		set_transient( $this->transient_prefix . 'progress', 100, DAY_IN_SECONDS );

		// Clear cached count after scan.
		$this->cached_media_count = null;

		return $results;
	}

	/**
	 * Get the total count of media attachments.
	 *
	 * Uses internal caching to avoid repeated database queries during a scan.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Added internal caching.
	 *
	 * @return int Total number of media attachments.
	 */
	public function get_media_count() {
		if ( null !== $this->cached_media_count ) {
			return $this->cached_media_count;
		}

		$posts_table = $this->connection->get_posts_table();

		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = %s",
			'attachment'
		);

		if ( null === $query ) {
			return 0;
		}

		$result                   = $this->connection->get_var( $query );
		$this->cached_media_count = absint( $result );

		return $this->cached_media_count;
	}

	/**
	 * Get the total size of all media attachments.
	 *
	 * Uses generator-based iteration for memory efficiency.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Uses generator-based iteration with memory awareness.
	 *
	 * @return int Total size in bytes.
	 */
	public function get_media_total_size() {
		$total_size  = 0;
		$total_count = $this->get_media_count();
		$processed   = 0;

		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			++$processed;

			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$total_size += filesize( $file_path );

				// Add sizes of thumbnails.
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'] ) ) {
					$base_dir = trailingslashit( dirname( $file_path ) );

					foreach ( $metadata['sizes'] as $size_data ) {
						if ( ! empty( $size_data['file'] ) ) {
							// Security: Use basename() to prevent path traversal attacks.
							// Metadata could be tampered with via database compromise.
							$thumb_filename = basename( $size_data['file'] );
							$thumb_path     = $base_dir . $thumb_filename;
							if ( file_exists( $thumb_path ) ) {
								$total_size += filesize( $thumb_path );
							}
						}
					}
				}
			}

			// Update progress periodically.
			$this->update_progress( $processed, $total_count );
		}

		return $total_size;
	}

	/**
	 * Find unused media attachments.
	 *
	 * Uses generator-based iteration for memory efficiency with large libraries.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Uses generator-based iteration with memory awareness.
	 *
	 * @return array Array of unused attachment IDs.
	 */
	public function find_unused_media() {
		$unused      = array();
		$total_count = $this->get_media_count();
		$processed   = 0;

		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			++$processed;

			// Skip excluded items.
			if ( $this->exclusions->is_excluded( $attachment_id ) ) {
				continue;
			}

			if ( ! $this->is_attachment_used( $attachment_id ) ) {
				$unused[] = $attachment_id;
			}

			// Update progress periodically.
			$this->update_progress( $processed, $total_count );
		}

		return $unused;
	}

	/**
	 * Check if a specific attachment is in use.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if in use, false otherwise.
	 */
	public function is_attachment_in_use( int $attachment_id ): bool {
		return $this->is_attachment_used( $attachment_id );
	}

	/**
	 * Check if an attachment is used anywhere.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param int $attachment_id Attachment ID to check.
	 * @return bool True if used, false otherwise.
	 */
	private function is_attachment_used( $attachment_id ): bool {
		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();
		$options_table  = $this->connection->get_options_table();

		// Check if it's a featured image.
		$featured_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$postmeta_table}
			WHERE meta_key = %s AND meta_value = %d",
			'_thumbnail_id',
			$attachment_id
		);

		if ( null !== $featured_check && $this->connection->get_var( $featured_check ) > 0 ) {
			return true;
		}

		// Get attachment URL.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url ) {
			return false;
		}

		// Parse URL to get relative path.
		$upload_dir = wp_upload_dir();
		$attachment_path = str_replace( $upload_dir['baseurl'], '', $attachment_url );
		$attachment_filename = basename( $attachment_url );

		// Check in post content.
		$content_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND (post_content LIKE %s OR post_content LIKE %s)",
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%'
		);

		if ( null !== $content_check && $this->connection->get_var( $content_check ) > 0 ) {
			return true;
		}

		// Check in postmeta (for galleries, ACF fields, etc.).
		$postmeta_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$postmeta_table}
			WHERE (meta_value LIKE %s OR meta_value LIKE %s OR meta_value = %d)",
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id
		);

		if ( null !== $postmeta_check && $this->connection->get_var( $postmeta_check ) > 0 ) {
			return true;
		}

		// Check in options (for widgets, customizer, site logo, etc.).
		$options_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table}
			WHERE option_name NOT LIKE %s
			AND (option_value LIKE %s OR option_value LIKE %s OR option_value = %d)",
			'%' . $this->connection->esc_like( '_transient_' ) . '%',
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id
		);

		if ( null !== $options_check && $this->connection->get_var( $options_check ) > 0 ) {
			return true;
		}

		// Check for WooCommerce product galleries.
		$woo_gallery_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$postmeta_table}
			WHERE meta_key = %s AND meta_value LIKE %s",
			'_product_image_gallery',
			'%' . $this->connection->esc_like( (string) $attachment_id ) . '%'
		);

		if ( null !== $woo_gallery_check && $this->connection->get_var( $woo_gallery_check ) > 0 ) {
			return true;
		}

		// Check for Elementor data.
		$elementor_check = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$postmeta_table}
			WHERE meta_key = %s AND meta_value LIKE %s",
			'_elementor_data',
			'%' . $this->connection->esc_like( (string) $attachment_id ) . '%'
		);

		if ( null !== $elementor_check && $this->connection->get_var( $elementor_check ) > 0 ) {
			return true;
		}

		// Check if attached to a post (parent post).
		$post = get_post( $attachment_id );
		if ( $post && $post->post_parent > 0 ) {
			$parent_post = get_post( $post->post_parent );
			if ( $parent_post && 'trash' !== $parent_post->post_status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find duplicate files based on file hash.
	 *
	 * Uses a two-pass approach for efficiency:
	 * 1. First pass: Group files by size (fast, no I/O)
	 * 2. Second pass: Hash only files with matching sizes (expensive, but targeted)
	 *
	 * This optimization significantly reduces I/O for large libraries where
	 * most files have unique sizes.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Uses two-pass size/hash approach for efficiency.
	 *
	 * @return array Array of duplicate file groups keyed by hash.
	 */
	public function find_duplicate_files() {
		$total_count    = $this->get_media_count();
		$processed      = 0;
		$files_by_size  = array();

		// First pass: Group files by size (very fast).
		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			++$processed;

			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );

				if ( ! isset( $files_by_size[ $file_size ] ) ) {
					$files_by_size[ $file_size ] = array();
				}
				$files_by_size[ $file_size ][] = array(
					'id'   => $attachment_id,
					'path' => $file_path,
				);
			}

			// Update progress (first pass is ~50% of work).
			$this->update_progress( $processed, $total_count * 2 );
		}

		// Second pass: Only hash files that share the same size.
		$duplicates  = array();
		$file_hashes = array();

		foreach ( $files_by_size as $size => $files ) {
			// Skip unique-sized files (no possible duplicates).
			if ( count( $files ) < 2 ) {
				++$processed;
				continue;
			}

			// Hash each file with this size.
			foreach ( $files as $file_info ) {
				++$processed;
				$this->update_progress( $processed, $total_count * 2 );

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
	 * Find large files above a specified size.
	 *
	 * Uses generator-based iteration for memory efficiency.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Uses generator-based iteration with memory awareness.
	 *
	 * @param int $min_size_mb Minimum file size in MB.
	 * @return array Array of attachment IDs with their sizes.
	 */
	public function find_large_files( $min_size_mb ) {
		$min_size_bytes = $min_size_mb * 1024 * 1024;
		$large_files    = array();
		$total_count    = $this->get_media_count();
		$processed      = 0;

		foreach ( $this->get_attachment_ids_generator() as $attachment_id ) {
			++$processed;

			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				if ( $file_size >= $min_size_bytes ) {
					$large_files[] = array(
						'id'   => $attachment_id,
						'size' => $file_size,
						'path' => $file_path,
					);
				}
			}

			// Update progress periodically.
			$this->update_progress( $processed, $total_count );
		}

		return $large_files;
	}

	/**
	 * Find images missing alt text.
	 *
	 * Uses generator-based iteration for memory efficiency.
	 * Only scans image attachments (post_mime_type LIKE 'image/%').
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Uses generator-based iteration with memory awareness.
	 *
	 * @return array Array of attachment IDs missing alt text.
	 */
	public function find_missing_alt_text() {
		$missing_alt = array();
		$total_count = $this->get_image_count();
		$processed   = 0;

		// Use filtered generator for images only.
		foreach ( $this->get_attachment_ids_generator( 'image/%' ) as $attachment_id ) {
			++$processed;

			$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( empty( $alt_text ) ) {
				$missing_alt[] = $attachment_id;
			}

			// Update progress periodically.
			$this->update_progress( $processed, $total_count );
		}

		return $missing_alt;
	}

	/**
	 * Get the total count of image attachments.
	 *
	 * @since 1.4.0
	 *
	 * @return int Total number of image attachments.
	 */
	private function get_image_count(): int {
		$posts_table = $this->connection->get_posts_table();

		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_type = %s AND post_mime_type LIKE %s",
			'attachment',
			'image/%'
		);

		if ( null === $query ) {
			return 0;
		}

		return absint( $this->connection->get_var( $query ) );
	}

	/**
	 * Get the current scan progress.
	 *
	 * @since 1.0.0
	 *
	 * @return int Progress percentage (0-100).
	 */
	public function get_scan_progress() {
		$progress = get_transient( $this->transient_prefix . 'progress' );
		return false !== $progress ? absint( $progress ) : 0;
	}

	/**
	 * Scan for unused media files.
	 *
	 * Provides paginated scanning for client-side progress tracking.
	 * Uses ORDER BY ID ASC for consistent pagination.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.4.0 Added memory awareness.
	 *
	 * @param int $batch_size Number of attachments to scan per batch.
	 * @param int $offset     Starting offset for pagination.
	 * @return array{
	 *     unused: array<int>,
	 *     scanned: int,
	 *     total: int,
	 *     has_more: bool,
	 *     memory_limited: bool
	 * } Scan results.
	 */
	public function scan_unused_media( int $batch_size = 100, int $offset = 0 ): array {
		$posts_table = $this->connection->get_posts_table();

		$total  = $this->get_media_count();
		$unused = array();

		$query = $this->connection->prepare(
			"SELECT ID FROM {$posts_table}
			WHERE post_type = %s
			ORDER BY ID ASC
			LIMIT %d OFFSET %d",
			'attachment',
			$batch_size,
			$offset
		);

		if ( null === $query ) {
			return array(
				'unused'         => array(),
				'scanned'        => 0,
				'total'          => $total,
				'has_more'       => false,
				'memory_limited' => false,
			);
		}

		$attachments    = $this->connection->get_col( $query );
		$scanned        = count( $attachments );
		$memory_limited = false;

		foreach ( $attachments as $attachment_id ) {
			// Check memory before processing each item.
			if ( $this->is_memory_low() ) {
				$this->clear_caches();
				if ( $this->is_memory_low() ) {
					$memory_limited = true;
					break;
				}
			}

			// Skip excluded items.
			if ( $this->exclusions->is_excluded( $attachment_id ) ) {
				continue;
			}

			if ( ! $this->is_attachment_used( $attachment_id ) ) {
				$unused[] = (int) $attachment_id;
			}
		}

		return array(
			'unused'         => $unused,
			'scanned'        => $offset + $scanned,
			'total'          => $total,
			'has_more'       => ( $offset + $scanned ) < $total,
			'memory_limited' => $memory_limited,
		);
	}

	/**
	 * Scan for duplicate media files.
	 *
	 * @since 1.1.0
	 *
	 * @param string $method Detection method: 'hash', 'filename', or 'both'.
	 * @return array<string, array<int>> Array of hash/name => attachment IDs.
	 */
	public function scan_duplicate_media( string $method = 'hash' ): array {
		// For now, use the existing hash-based implementation.
		// The method parameter is reserved for future extension.
		return $this->find_duplicate_files();
	}

	/**
	 * Scan for large media files.
	 *
	 * @since 1.1.0
	 *
	 * @param int $threshold_kb Size threshold in kilobytes.
	 * @return array<int, array{id: int, size: int, file: string}> Large file data.
	 */
	public function scan_large_media( int $threshold_kb = 1000 ): array {
		// Convert KB to MB for the existing method.
		$threshold_mb = $threshold_kb / 1024;
		$large_files = $this->find_large_files( $threshold_mb );

		// Transform to match interface return type.
		$result = array();
		foreach ( $large_files as $file ) {
			$result[] = array(
				'id'   => (int) $file['id'],
				'size' => (int) $file['size'],
				'file' => $file['path'],
			);
		}

		return $result;
	}

	/**
	 * Get the total count of media files.
	 *
	 * @since 1.1.0
	 *
	 * @return int Total number of attachments.
	 */
	public function get_total_media_count(): int {
		return $this->get_media_count();
	}

	/**
	 * Get the total size of all media files.
	 *
	 * @since 1.1.0
	 *
	 * @return int Total size in bytes.
	 */
	public function get_total_media_size(): int {
		return $this->get_media_total_size();
	}

	/**
	 * Get usage locations for an attachment.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<array{type: string, id: int, title: string}> Usage locations.
	 */
	public function get_attachment_usage( int $attachment_id ): array {
		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		$usages = array();

		// Check featured image usage.
		$featured_query = $this->connection->prepare(
			"SELECT post_id FROM {$postmeta_table}
			WHERE meta_key = %s AND meta_value = %d",
			'_thumbnail_id',
			$attachment_id
		);

		if ( null !== $featured_query ) {
			$featured_posts = $this->connection->get_col( $featured_query );

			foreach ( $featured_posts as $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$usages[] = array(
						'type'  => 'featured_image',
						'id'    => (int) $post_id,
						'title' => $post->post_title,
					);
				}
			}
		}

		// Check post content usage.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( $attachment_url ) {
			$upload_dir = wp_upload_dir();
			$attachment_path = str_replace( $upload_dir['baseurl'], '', $attachment_url );

			$content_query = $this->connection->prepare(
				"SELECT ID FROM {$posts_table}
				WHERE post_status NOT IN ('trash', 'auto-draft')
				AND post_content LIKE %s
				LIMIT 20",
				'%' . $this->connection->esc_like( $attachment_path ) . '%'
			);

			if ( null !== $content_query ) {
				$content_posts = $this->connection->get_col( $content_query );

				foreach ( $content_posts as $post_id ) {
					$post = get_post( $post_id );
					if ( $post ) {
						$usages[] = array(
							'type'  => 'content',
							'id'    => (int) $post_id,
							'title' => $post->post_title,
						);
					}
				}
			}
		}

		// Check parent attachment.
		$post = get_post( $attachment_id );
		if ( $post && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );
			if ( $parent && 'trash' !== $parent->post_status ) {
				$usages[] = array(
					'type'  => 'parent',
					'id'    => (int) $parent->ID,
					'title' => $parent->post_title,
				);
			}
		}

		return $usages;
	}

	/**
	 * Get a summary of the media library.
	 *
	 * @since 1.1.0
	 *
	 * @return array{
	 *     total_count: int,
	 *     total_size: int,
	 *     unused_count: int,
	 *     unused_size: int,
	 *     duplicate_count: int,
	 *     large_count: int
	 * } Media summary statistics.
	 */
	public function get_media_summary(): array {
		$total_count = $this->get_media_count();
		$total_size = $this->get_media_total_size();

		// Get unused media.
		$unused = $this->find_unused_media();
		$unused_count = count( $unused );

		// Calculate unused size.
		$unused_size = 0;
		foreach ( $unused as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$unused_size += filesize( $file_path );
			}
		}

		// Get duplicate count.
		$duplicates = $this->find_duplicate_files();
		$duplicate_count = 0;
		foreach ( $duplicates as $ids ) {
			// Count duplicates (excluding original).
			$duplicate_count += count( $ids ) - 1;
		}

		// Get large files count (files > 1MB).
		$large_files = $this->find_large_files( 1 );
		$large_count = count( $large_files );

		return array(
			'total_count'     => $total_count,
			'total_size'      => $total_size,
			'unused_count'    => $unused_count,
			'unused_size'     => $unused_size,
			'duplicate_count' => $duplicate_count,
			'large_count'     => $large_count,
		);
	}
}
