<?php
/**
 * Large File Identifier Class
 *
 * Identifies large media files and provides optimization suggestions.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Large Files class for identifying and analyzing large media files.
 */
class Large_Files {

	/**
	 * Batch size for processing attachments.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Default threshold in KB.
	 *
	 * @var int
	 */
	private $default_threshold_kb = 500;

	/**
	 * Find large files above a specified threshold.
	 *
	 * @param int $threshold_kb Minimum file size in KB. Default is 500KB.
	 * @return array Array of large files with details.
	 */
	public function find_large_files( $threshold_kb = null ) {
		global $wpdb;

		if ( null === $threshold_kb ) {
			$threshold_kb = $this->default_threshold_kb;
		}

		$threshold_bytes = $threshold_kb * 1024;
		$large_files = array();
		$batch_offset = 0;
		$exclusions = new Exclusions();

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
				// Skip excluded items.
				if ( $exclusions->is_excluded( $attachment_id ) ) {
					continue;
				}
				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					continue;
				}

				$file_size = filesize( $file_path );
				if ( $file_size < $threshold_bytes ) {
					continue;
				}

				$metadata = wp_get_attachment_metadata( $attachment_id );
				$mime_type = get_post_mime_type( $attachment_id );
				$filename = basename( $file_path );

				// Determine dimensions for images.
				$dimensions = null;
				if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
					$dimensions = array(
						'width' => $metadata['width'],
						'height' => $metadata['height'],
					);
				}

				// Calculate suggested max size and potential savings.
				$optimization = $this->calculate_optimization( $file_size, $dimensions, $mime_type );

				$large_files[] = array(
					'id' => $attachment_id,
					'filename' => $filename,
					'current_size' => $file_size,
					'current_size_formatted' => size_format( $file_size ),
					'suggested_max_size' => $optimization['suggested_max_size'],
					'suggested_max_size_formatted' => size_format( $optimization['suggested_max_size'] ),
					'potential_savings' => $optimization['potential_savings'],
					'potential_savings_formatted' => size_format( $optimization['potential_savings'] ),
					'dimensions' => $dimensions,
					'mime_type' => $mime_type,
				);
			}

			$batch_offset += $this->batch_size;
		}

		return $large_files;
	}

	/**
	 * Get optimization suggestions for large files.
	 *
	 * @return array Array of optimization suggestions with actionable recommendations.
	 */
	public function get_optimization_suggestions() {
		global $wpdb;

		$suggestions = array();
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

				$metadata = wp_get_attachment_metadata( $attachment_id );
				$mime_type = get_post_mime_type( $attachment_id );
				$file_size = filesize( $file_path );
				$filename = basename( $file_path );

				$file_suggestions = array();

				// Check for oversized dimensions (images over 2000px on either dimension).
				if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
					if ( $metadata['width'] > 2000 || $metadata['height'] > 2000 ) {
						$file_suggestions[] = array(
							'type' => 'oversized_dimensions',
							'priority' => 'high',
							'message' => sprintf(
								'Image dimensions (%dx%d) exceed recommended maximum of 2000px. Consider resizing to reduce file size.',
								$metadata['width'],
								$metadata['height']
							),
							'action' => 'Resize image to maximum 2000px on longest side',
						);
					}
				}

				// Check for unoptimized formats (BMP, TIFF).
				if ( in_array( $mime_type, array( 'image/bmp', 'image/x-ms-bmp', 'image/tiff' ), true ) ) {
					$file_suggestions[] = array(
						'type' => 'unoptimized_format',
						'priority' => 'high',
						'message' => sprintf(
							'File is in unoptimized format (%s). Convert to JPG or PNG for better web performance.',
							$mime_type
						),
						'action' => 'Convert to JPG (for photos) or PNG (for graphics with transparency)',
					);
				}

				// Check for PNGs that should be JPGs.
				// PNGs without transparency and over 100KB are typically better as JPGs.
				if ( 'image/png' === $mime_type && $file_size > 102400 ) {
					$has_transparency = $this->check_png_transparency( $file_path );
					if ( ! $has_transparency ) {
						$estimated_jpg_size = $file_size * 0.3; // JPGs are typically 30% of PNG size for photos.
						$potential_savings = $file_size - $estimated_jpg_size;

						$file_suggestions[] = array(
							'type' => 'png_to_jpg',
							'priority' => 'medium',
							'message' => sprintf(
								'PNG file without transparency could be converted to JPG. Estimated savings: %s.',
								size_format( $potential_savings )
							),
							'action' => 'Convert to JPG format',
						);
					}
				}

				// Only add files with suggestions.
				if ( ! empty( $file_suggestions ) ) {
					$suggestions[] = array(
						'id' => $attachment_id,
						'filename' => $filename,
						'current_size' => $file_size,
						'current_size_formatted' => size_format( $file_size ),
						'mime_type' => $mime_type,
						'dimensions' => ! empty( $metadata['width'] ) && ! empty( $metadata['height'] )
							? array( 'width' => $metadata['width'], 'height' => $metadata['height'] )
							: null,
						'suggestions' => $file_suggestions,
					);
				}
			}

			$batch_offset += $this->batch_size;
		}

		return $suggestions;
	}

	/**
	 * Get size distribution of media library.
	 *
	 * Returns counts for different size buckets to understand library composition.
	 *
	 * @return array Array of size buckets with counts.
	 */
	public function get_size_distribution() {
		global $wpdb;

		$distribution = array(
			'under_100kb' => array(
				'label' => '<100KB',
				'count' => 0,
				'total_size' => 0,
			),
			'100kb_to_500kb' => array(
				'label' => '100-500KB',
				'count' => 0,
				'total_size' => 0,
			),
			'500kb_to_1mb' => array(
				'label' => '500KB-1MB',
				'count' => 0,
				'total_size' => 0,
			),
			'1mb_to_5mb' => array(
				'label' => '1-5MB',
				'count' => 0,
				'total_size' => 0,
			),
			'over_5mb' => array(
				'label' => '>5MB',
				'count' => 0,
				'total_size' => 0,
			),
		);

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

				$file_size = filesize( $file_path );

				// Categorize into buckets.
				if ( $file_size < 102400 ) { // <100KB.
					$distribution['under_100kb']['count']++;
					$distribution['under_100kb']['total_size'] += $file_size;
				} elseif ( $file_size < 512000 ) { // 100-500KB.
					$distribution['100kb_to_500kb']['count']++;
					$distribution['100kb_to_500kb']['total_size'] += $file_size;
				} elseif ( $file_size < 1048576 ) { // 500KB-1MB.
					$distribution['500kb_to_1mb']['count']++;
					$distribution['500kb_to_1mb']['total_size'] += $file_size;
				} elseif ( $file_size < 5242880 ) { // 1-5MB.
					$distribution['1mb_to_5mb']['count']++;
					$distribution['1mb_to_5mb']['total_size'] += $file_size;
				} else { // >5MB.
					$distribution['over_5mb']['count']++;
					$distribution['over_5mb']['total_size'] += $file_size;
				}
			}

			$batch_offset += $this->batch_size;
		}

		// Add formatted sizes.
		foreach ( $distribution as $key => $bucket ) {
			$distribution[ $key ]['total_size_formatted'] = size_format( $bucket['total_size'] );
		}

		return $distribution;
	}

	/**
	 * Calculate optimization for a file.
	 *
	 * @param int        $file_size Current file size in bytes.
	 * @param array|null $dimensions Image dimensions (width, height) or null.
	 * @param string     $mime_type File MIME type.
	 * @return array Array with suggested_max_size and potential_savings.
	 */
	private function calculate_optimization( $file_size, $dimensions, $mime_type ) {
		$suggested_max_size = $file_size;
		$potential_savings = 0;

		// For images with oversized dimensions, estimate size reduction.
		if ( $dimensions && ( $dimensions['width'] > 2000 || $dimensions['height'] > 2000 ) ) {
			// Estimate that resizing to 2000px will reduce size by ~60%.
			$suggested_max_size = $file_size * 0.4;
			$potential_savings = $file_size - $suggested_max_size;
		}

		// For unoptimized formats, estimate conversion savings.
		if ( in_array( $mime_type, array( 'image/bmp', 'image/x-ms-bmp', 'image/tiff' ), true ) ) {
			// BMP/TIFF to JPG typically saves ~80%.
			$suggested_max_size = min( $suggested_max_size, $file_size * 0.2 );
			$potential_savings = $file_size - $suggested_max_size;
		}

		// For large PNGs without transparency, suggest JPG conversion.
		if ( 'image/png' === $mime_type && $file_size > 102400 ) {
			// Assume 70% savings by converting to JPG.
			$suggested_max_size = min( $suggested_max_size, $file_size * 0.3 );
			$potential_savings = $file_size - $suggested_max_size;
		}

		return array(
			'suggested_max_size' => (int) $suggested_max_size,
			'potential_savings' => (int) $potential_savings,
		);
	}

	/**
	 * Check if a PNG file has transparency.
	 *
	 * @param string $file_path Path to the PNG file.
	 * @return bool True if PNG has transparency, false otherwise.
	 */
	private function check_png_transparency( $file_path ) {
		// Use GD or ImageMagick if available to check for alpha channel.
		if ( function_exists( 'imagecreatefrompng' ) ) {
			$image = @imagecreatefrompng( $file_path );
			if ( ! $image ) {
				return false;
			}

			// Check if image has alpha channel.
			$width = imagesx( $image );
			$height = imagesy( $image );

			// Sample pixels to check for transparency (check corners and center).
			$sample_points = array(
				array( 0, 0 ), // Top-left.
				array( $width - 1, 0 ), // Top-right.
				array( 0, $height - 1 ), // Bottom-left.
				array( $width - 1, $height - 1 ), // Bottom-right.
				array( (int) ( $width / 2 ), (int) ( $height / 2 ) ), // Center.
			);

			foreach ( $sample_points as $point ) {
				$color = imagecolorat( $image, $point[0], $point[1] );
				$alpha = ( $color >> 24 ) & 0x7F;
				if ( $alpha > 0 ) {
					imagedestroy( $image );
					return true;
				}
			}

			imagedestroy( $image );
			return false;
		}

		// If GD is not available, assume no transparency for safety.
		return false;
	}
}
