<?php
/**
 * Media Scanner Class
 *
 * Scans and analyzes WordPress media library for optimization opportunities.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Media Scanner class for analyzing media library health and statistics.
 */
class Scanner {

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
	 * Scan all media attachments in batches.
	 *
	 * @return array Scan results with counts and statistics.
	 */
	public function scan_all_media() {
		global $wpdb;

		$total_count = $this->get_media_count();
		$total_size = $this->get_media_total_size();

		$results = array(
			'total_count' => $total_count,
			'total_size' => $total_size,
			'unused_count' => 0,
			'duplicate_count' => 0,
			'large_files_count' => 0,
			'missing_alt_count' => 0,
			'scanned_at' => current_time( 'mysql' ),
		);

		// Store results in transient.
		set_transient( $this->transient_prefix . 'results', $results, DAY_IN_SECONDS );
		set_transient( $this->transient_prefix . 'progress', 100, DAY_IN_SECONDS );

		return $results;
	}

	/**
	 * Get the total count of media attachments.
	 *
	 * @return int Total number of media attachments.
	 */
	public function get_media_count() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'attachment'
		);

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get the total size of all media attachments.
	 *
	 * @return int Total size in bytes.
	 */
	public function get_media_total_size() {
		global $wpdb;

		$total_size = 0;
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
				if ( $file_path && file_exists( $file_path ) ) {
					$total_size += filesize( $file_path );

					// Add sizes of thumbnails.
					$metadata = wp_get_attachment_metadata( $attachment_id );
					if ( ! empty( $metadata['sizes'] ) ) {
						$upload_dir = wp_upload_dir();
						$base_dir = trailingslashit( dirname( $file_path ) );

						foreach ( $metadata['sizes'] as $size_data ) {
							if ( ! empty( $size_data['file'] ) ) {
								$thumb_path = $base_dir . $size_data['file'];
								if ( file_exists( $thumb_path ) ) {
									$total_size += filesize( $thumb_path );
								}
							}
						}
					}
				}
			}

			$batch_offset += $this->batch_size;

			// Update progress.
			$total_count = $this->get_media_count();
			if ( $total_count > 0 ) {
				$progress = min( 100, ( $batch_offset / $total_count ) * 100 );
				set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
			}
		}

		return $total_size;
	}

	/**
	 * Find unused media attachments.
	 *
	 * @return array Array of unused attachment IDs.
	 */
	public function find_unused_media() {
		global $wpdb;

		$unused = array();
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
				if ( ! $this->is_attachment_used( $attachment_id ) ) {
					$unused[] = $attachment_id;
				}
			}

			$batch_offset += $this->batch_size;

			// Update progress.
			$total_count = $this->get_media_count();
			if ( $total_count > 0 ) {
				$progress = min( 100, ( $batch_offset / $total_count ) * 100 );
				set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
			}
		}

		return $unused;
	}

	/**
	 * Check if an attachment is used anywhere.
	 *
	 * @param int $attachment_id Attachment ID to check.
	 * @return bool True if used, false otherwise.
	 */
	private function is_attachment_used( $attachment_id ) {
		global $wpdb;

		// Check if it's a featured image.
		$featured_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value = %d",
			'_thumbnail_id',
			$attachment_id
		);

		if ( $wpdb->get_var( $featured_check ) > 0 ) {
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
		$content_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND (post_content LIKE %s OR post_content LIKE %s)",
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%'
		);

		if ( $wpdb->get_var( $content_check ) > 0 ) {
			return true;
		}

		// Check in postmeta (for galleries, ACF fields, etc.).
		$postmeta_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE (meta_value LIKE %s OR meta_value LIKE %s OR meta_value = %d)",
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%',
			$attachment_id
		);

		if ( $wpdb->get_var( $postmeta_check ) > 0 ) {
			return true;
		}

		// Check in options (for widgets, customizer, site logo, etc.).
		$options_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name NOT LIKE %s
			AND (option_value LIKE %s OR option_value LIKE %s OR option_value = %d)",
			'%' . $wpdb->esc_like( '_transient_' ) . '%',
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%',
			$attachment_id
		);

		if ( $wpdb->get_var( $options_check ) > 0 ) {
			return true;
		}

		// Check for WooCommerce product galleries.
		$woo_gallery_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value LIKE %s",
			'_product_image_gallery',
			'%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
		);

		if ( $wpdb->get_var( $woo_gallery_check ) > 0 ) {
			return true;
		}

		// Check for Elementor data.
		$elementor_check = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value LIKE %s",
			'_elementor_data',
			'%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
		);

		if ( $wpdb->get_var( $elementor_check ) > 0 ) {
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
	 * @return array Array of duplicate file groups.
	 */
	public function find_duplicate_files() {
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
				if ( $file_path && file_exists( $file_path ) ) {
					$file_hash = md5_file( $file_path );
					if ( isset( $file_hashes[ $file_hash ] ) ) {
						if ( ! isset( $duplicates[ $file_hash ] ) ) {
							$duplicates[ $file_hash ] = array( $file_hashes[ $file_hash ] );
						}
						$duplicates[ $file_hash ][] = $attachment_id;
					} else {
						$file_hashes[ $file_hash ] = $attachment_id;
					}
				}
			}

			$batch_offset += $this->batch_size;

			// Update progress.
			$total_count = $this->get_media_count();
			if ( $total_count > 0 ) {
				$progress = min( 100, ( $batch_offset / $total_count ) * 100 );
				set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
			}
		}

		return $duplicates;
	}

	/**
	 * Find large files above a specified size.
	 *
	 * @param int $min_size_mb Minimum file size in MB.
	 * @return array Array of attachment IDs with their sizes.
	 */
	public function find_large_files( $min_size_mb ) {
		global $wpdb;

		$min_size_bytes = $min_size_mb * 1024 * 1024;
		$large_files = array();
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
				if ( $file_path && file_exists( $file_path ) ) {
					$file_size = filesize( $file_path );
					if ( $file_size >= $min_size_bytes ) {
						$large_files[] = array(
							'id' => $attachment_id,
							'size' => $file_size,
							'path' => $file_path,
						);
					}
				}
			}

			$batch_offset += $this->batch_size;

			// Update progress.
			$total_count = $this->get_media_count();
			if ( $total_count > 0 ) {
				$progress = min( 100, ( $batch_offset / $total_count ) * 100 );
				set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
			}
		}

		return $large_files;
	}

	/**
	 * Find images missing alt text.
	 *
	 * @return array Array of attachment IDs missing alt text.
	 */
	public function find_missing_alt_text() {
		global $wpdb;

		$missing_alt = array();
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
				$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				if ( empty( $alt_text ) ) {
					$missing_alt[] = $attachment_id;
				}
			}

			$batch_offset += $this->batch_size;

			// Update progress.
			$total_count = $this->get_media_count();
			if ( $total_count > 0 ) {
				$progress = min( 100, ( $batch_offset / $total_count ) * 100 );
				set_transient( $this->transient_prefix . 'progress', $progress, HOUR_IN_SECONDS );
			}
		}

		return $missing_alt;
	}

	/**
	 * Get the current scan progress.
	 *
	 * @return int Progress percentage (0-100).
	 */
	public function get_scan_progress() {
		$progress = get_transient( $this->transient_prefix . 'progress' );
		return $progress !== false ? absint( $progress ) : 0;
	}
}
