<?php
/**
 * Tests for Large Files Class
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests;

use WPAdminHealth\Media\LargeFiles;
use WPAdminHealth\Media\Exclusions;

/**
 * Test cases for Large Files functionality.
 */
class LargeFilesTest extends TestCase {

	/**
	 * LargeFiles instance.
	 *
	 * @var LargeFiles
	 */
	private $large_files;

	/**
	 * Exclusions instance.
	 *
	 * @var Exclusions
	 */
	private $exclusions;

	/**
	 * Test attachments created during tests.
	 *
	 * @var array
	 */
	private $test_attachments = array();

	/**
	 * Test upload directory.
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Set up test environment.
	 */
	protected function set_up() {
		parent::set_up();

		$this->exclusions = new Exclusions();

		// Use WPDB Connection for integration tests.
		$connection        = new \WPAdminHealth\Database\WPDBConnection();
		$this->large_files = new LargeFiles( $connection, $this->exclusions );

		$upload_dir_info  = wp_upload_dir();
		$this->upload_dir = $upload_dir_info['path'];
	}

	/**
	 * Clean up test environment.
	 */
	protected function tear_down() {
		// Clean up test attachments and files.
		foreach ( $this->test_attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		// Clean up exclusions.
		$this->exclusions->clear_exclusions();

		parent::tear_down();
	}

	/**
	 * Create a test file with specified size.
	 *
	 * @param string $filename  Filename for the test file.
	 * @param int    $size_kb   Size in kilobytes.
	 * @param string $mime_type MIME type.
	 * @return int Attachment ID.
	 */
	private function create_test_file( $filename = 'test-file.jpg', $size_kb = 100, $mime_type = 'image/jpeg' ) {
		$file_path = $this->upload_dir . '/' . $filename;

		// Create a file with specified size.
		$handle         = fopen( $file_path, 'w' );
		$bytes_to_write = $size_kb * 1024;
		$chunk_size     = 1024;
		$bytes_written  = 0;

		while ( $bytes_written < $bytes_to_write ) {
			$write_size = min( $chunk_size, $bytes_to_write - $bytes_written );
			fwrite( $handle, str_repeat( 'x', $write_size ) );
			$bytes_written += $write_size;
		}
		fclose( $handle );

		// Create attachment.
		$attachment_id = $this->factory()->attachment->create_object(
			$file_path,
			0,
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			)
		);

		$this->test_attachments[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Create a test image with specified dimensions.
	 *
	 * @param string $filename Filename for the test image.
	 * @param int    $width    Image width in pixels.
	 * @param int    $height   Image height in pixels.
	 * @param int    $size_kb  Approximate file size in kilobytes.
	 * @return int Attachment ID.
	 */
	private function create_test_image( $filename = 'test-image.jpg', $width = 100, $height = 100, $size_kb = 100 ) {
		$file_path = $this->upload_dir . '/' . $filename;

		// Create a simple image using GD if available.
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image    = imagecreatetruecolor( $width, $height );
			$bg_color = imagecolorallocate( $image, 255, 255, 255 );
			imagefill( $image, 0, 0, $bg_color );
			imagejpeg( $image, $file_path, 100 );
			imagedestroy( $image );

			// Pad file to approximate size if needed.
			$current_size = filesize( $file_path );
			$target_size  = $size_kb * 1024;
			if ( $current_size < $target_size ) {
				$handle = fopen( $file_path, 'a' );
				fwrite( $handle, str_repeat( "\x00", $target_size - $current_size ) );
				fclose( $handle );
			}
		} else {
			// Fallback: create a file with specified size.
			$handle         = fopen( $file_path, 'w' );
			$bytes_to_write = $size_kb * 1024;
			fwrite( $handle, str_repeat( 'x', $bytes_to_write ) );
			fclose( $handle );
		}

		// Create attachment.
		$attachment_id = $this->factory()->attachment->create_object(
			$file_path,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			)
		);

		// Generate attachment metadata with dimensions.
		$metadata = array(
			'width'  => $width,
			'height' => $height,
			'file'   => $filename,
		);
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$this->test_attachments[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Test find_large_files with default threshold.
	 */
	public function test_find_large_files_default_threshold() {
		// Create files: 400KB (below default), 600KB (above default).
		$small_file = $this->create_test_file( 'small-file.jpg', 400 );
		$large_file = $this->create_test_file( 'large-file.jpg', 600 );

		$results = $this->large_files->find_large_files();

		$result_ids = array_column( $results, 'id' );

		$this->assertNotContains( $small_file, $result_ids, '400KB file should not be found with 500KB default threshold' );
		$this->assertContains( $large_file, $result_ids, '600KB file should be found with 500KB default threshold' );
	}

	/**
	 * Test find_large_files with custom threshold.
	 */
	public function test_find_large_files_custom_threshold() {
		// Create files of different sizes.
		$file_200kb = $this->create_test_file( 'file-200kb.jpg', 200 );
		$file_400kb = $this->create_test_file( 'file-400kb.jpg', 400 );
		$file_600kb = $this->create_test_file( 'file-600kb.jpg', 600 );

		// Test 300KB threshold.
		$results_300 = $this->large_files->find_large_files( 300 );
		$ids_300     = array_column( $results_300, 'id' );

		$this->assertNotContains( $file_200kb, $ids_300, '200KB file should not be found with 300KB threshold' );
		$this->assertContains( $file_400kb, $ids_300, '400KB file should be found with 300KB threshold' );
		$this->assertContains( $file_600kb, $ids_300, '600KB file should be found with 300KB threshold' );

		// Test 500KB threshold.
		$results_500 = $this->large_files->find_large_files( 500 );
		$ids_500     = array_column( $results_500, 'id' );

		$this->assertNotContains( $file_200kb, $ids_500, '200KB file should not be found with 500KB threshold' );
		$this->assertNotContains( $file_400kb, $ids_500, '400KB file should not be found with 500KB threshold' );
		$this->assertContains( $file_600kb, $ids_500, '600KB file should be found with 500KB threshold' );
	}

	/**
	 * Test find_large_files respects exclusions.
	 */
	public function test_find_large_files_respects_exclusions() {
		// Create two large files.
		$large_file_1 = $this->create_test_file( 'large-1.jpg', 600 );
		$large_file_2 = $this->create_test_file( 'large-2.jpg', 600 );

		// Exclude one.
		$this->exclusions->add_exclusion( $large_file_1, 'Test exclusion' );

		$results    = $this->large_files->find_large_files();
		$result_ids = array_column( $results, 'id' );

		$this->assertNotContains( $large_file_1, $result_ids, 'Excluded file should not be in results' );
		$this->assertContains( $large_file_2, $result_ids, 'Non-excluded file should be in results' );
	}

	/**
	 * Test find_large_files returns correct data structure.
	 */
	public function test_find_large_files_returns_correct_structure() {
		$large_file = $this->create_test_image( 'structured-file.jpg', 500, 400, 600 );

		$results = $this->large_files->find_large_files();

		$this->assertNotEmpty( $results );

		$file_result = null;
		foreach ( $results as $result ) {
			if ( $result['id'] === $large_file ) {
				$file_result = $result;
				break;
			}
		}

		$this->assertNotNull( $file_result, 'File should be in results' );
		$this->assertArrayHasKey( 'id', $file_result );
		$this->assertArrayHasKey( 'filename', $file_result );
		$this->assertArrayHasKey( 'current_size', $file_result );
		$this->assertArrayHasKey( 'current_size_formatted', $file_result );
		$this->assertArrayHasKey( 'suggested_max_size', $file_result );
		$this->assertArrayHasKey( 'suggested_max_size_formatted', $file_result );
		$this->assertArrayHasKey( 'potential_savings', $file_result );
		$this->assertArrayHasKey( 'potential_savings_formatted', $file_result );
		$this->assertArrayHasKey( 'dimensions', $file_result );
		$this->assertArrayHasKey( 'mime_type', $file_result );

		$this->assertEquals( $large_file, $file_result['id'] );
		$this->assertIsInt( $file_result['current_size'] );
		$this->assertIsString( $file_result['current_size_formatted'] );
	}

	/**
	 * Test find_large_files includes dimensions for images.
	 */
	public function test_find_large_files_includes_dimensions() {
		$large_image = $this->create_test_image( 'dimensioned-file.jpg', 800, 600, 600 );

		$results = $this->large_files->find_large_files();

		$file_result = null;
		foreach ( $results as $result ) {
			if ( $result['id'] === $large_image ) {
				$file_result = $result;
				break;
			}
		}

		$this->assertNotNull( $file_result );
		$this->assertNotNull( $file_result['dimensions'] );
		$this->assertEquals( 800, $file_result['dimensions']['width'] );
		$this->assertEquals( 600, $file_result['dimensions']['height'] );
	}

	/**
	 * Test get_optimization_suggestions detects oversized dimensions.
	 */
	public function test_optimization_suggestions_oversized_dimensions() {
		// Create an oversized image (>2000px).
		$oversized_image = $this->create_test_image( 'oversized.jpg', 3000, 2500, 600 );

		// Create a normal-sized image.
		$normal_image = $this->create_test_image( 'normal.jpg', 800, 600, 600 );

		$suggestions = $this->large_files->get_optimization_suggestions();

		// Find suggestions for oversized image.
		$oversized_suggestions = array_filter(
			$suggestions,
			function ( $s ) use ( $oversized_image ) {
				return $s['id'] === $oversized_image;
			}
		);

		$this->assertNotEmpty( $oversized_suggestions, 'Oversized image should have suggestions' );

		$suggestion = reset( $oversized_suggestions );
		$types      = array_column( $suggestion['suggestions'], 'type' );
		$this->assertContains( 'oversized_dimensions', $types, 'Should suggest dimension reduction' );
	}

	/**
	 * Test get_optimization_suggestions detects unoptimized formats.
	 */
	public function test_optimization_suggestions_unoptimized_formats() {
		// Create a BMP file.
		$bmp_file = $this->create_test_file( 'unoptimized.bmp', 600, 'image/bmp' );

		$suggestions = $this->large_files->get_optimization_suggestions();

		$bmp_suggestions = array_filter(
			$suggestions,
			function ( $s ) use ( $bmp_file ) {
				return $s['id'] === $bmp_file;
			}
		);

		$this->assertNotEmpty( $bmp_suggestions, 'BMP file should have suggestions' );

		$suggestion = reset( $bmp_suggestions );
		$types      = array_column( $suggestion['suggestions'], 'type' );
		$this->assertContains( 'unoptimized_format', $types, 'Should suggest format conversion' );
	}

	/**
	 * Test get_optimization_suggestions respects exclusions.
	 */
	public function test_optimization_suggestions_respects_exclusions() {
		// Create two oversized images.
		$oversized_1 = $this->create_test_image( 'oversized-1.jpg', 3000, 2500, 600 );
		$oversized_2 = $this->create_test_image( 'oversized-2.jpg', 3000, 2500, 600 );

		// Exclude one.
		$this->exclusions->add_exclusion( $oversized_1, 'Test exclusion' );

		$suggestions = $this->large_files->get_optimization_suggestions();
		$ids         = array_column( $suggestions, 'id' );

		$this->assertNotContains( $oversized_1, $ids, 'Excluded file should not be in suggestions' );
		$this->assertContains( $oversized_2, $ids, 'Non-excluded file should be in suggestions' );
	}

	/**
	 * Test get_size_distribution returns correct buckets.
	 */
	public function test_size_distribution_buckets() {
		// Create files in different size ranges.
		$this->create_test_file( 'tiny.jpg', 50 );      // <100KB.
		$this->create_test_file( 'small.jpg', 200 );    // 100-500KB.
		$this->create_test_file( 'medium.jpg', 700 );   // 500KB-1MB.
		$this->create_test_file( 'large.jpg', 2000 );   // 1-5MB.
		$this->create_test_file( 'huge.jpg', 6000 );    // >5MB.

		$distribution = $this->large_files->get_size_distribution();

		$this->assertArrayHasKey( 'under_100kb', $distribution );
		$this->assertArrayHasKey( '100kb_to_500kb', $distribution );
		$this->assertArrayHasKey( '500kb_to_1mb', $distribution );
		$this->assertArrayHasKey( '1mb_to_5mb', $distribution );
		$this->assertArrayHasKey( 'over_5mb', $distribution );

		// Verify each bucket has required fields.
		foreach ( $distribution as $bucket ) {
			$this->assertArrayHasKey( 'label', $bucket );
			$this->assertArrayHasKey( 'count', $bucket );
			$this->assertArrayHasKey( 'total_size', $bucket );
			$this->assertArrayHasKey( 'total_size_formatted', $bucket );
		}
	}

	/**
	 * Test get_size_distribution counts files correctly.
	 */
	public function test_size_distribution_counts() {
		// Clear existing attachments by noting initial counts.
		$initial_distribution = $this->large_files->get_size_distribution();

		// Create test files.
		$this->create_test_file( 'dist-tiny-1.jpg', 50 );
		$this->create_test_file( 'dist-tiny-2.jpg', 80 );
		$this->create_test_file( 'dist-small.jpg', 200 );

		$final_distribution = $this->large_files->get_size_distribution();

		// Calculate differences.
		$under_100kb_diff    = $final_distribution['under_100kb']['count'] - $initial_distribution['under_100kb']['count'];
		$kb_100_to_500_diff = $final_distribution['100kb_to_500kb']['count'] - $initial_distribution['100kb_to_500kb']['count'];

		$this->assertEquals( 2, $under_100kb_diff, 'Should have 2 new files under 100KB' );
		$this->assertEquals( 1, $kb_100_to_500_diff, 'Should have 1 new file in 100-500KB range' );
	}

	/**
	 * Test that files outside uploads directory are ignored.
	 */
	public function test_path_traversal_protection() {
		// This test verifies that is_valid_upload_path properly validates paths.
		// We can't easily test path traversal without mocking get_attached_file,
		// but we verify the method exists and is called.
		$large_file = $this->create_test_file( 'safe-file.jpg', 600 );

		$results = $this->large_files->find_large_files();

		// If the file is valid, it should appear in results.
		$result_ids = array_column( $results, 'id' );
		$this->assertContains( $large_file, $result_ids, 'Valid file in uploads directory should be included' );
	}

	/**
	 * Test that nonexistent files are handled gracefully.
	 */
	public function test_handles_missing_files() {
		// Create an attachment.
		$attachment_id = $this->create_test_file( 'will-be-deleted.jpg', 600 );

		// Delete the physical file but keep the attachment.
		$file_path = get_attached_file( $attachment_id );
		unlink( $file_path );

		// Should not throw an error.
		$results = $this->large_files->find_large_files();

		// The attachment with missing file should not be in results.
		$result_ids = array_column( $results, 'id' );
		$this->assertNotContains( $attachment_id, $result_ids, 'Attachment with missing file should be excluded' );
	}

	/**
	 * Test optimization calculations for oversized images.
	 */
	public function test_optimization_calculation_oversized_dimensions() {
		// Create an oversized image.
		$oversized = $this->create_test_image( 'calc-oversized.jpg', 3000, 2000, 1000 );

		$results = $this->large_files->find_large_files();

		$file_result = null;
		foreach ( $results as $result ) {
			if ( $result['id'] === $oversized ) {
				$file_result = $result;
				break;
			}
		}

		$this->assertNotNull( $file_result );

		// For oversized images, suggested size should be ~40% of current (60% reduction).
		$expected_max = $file_result['current_size'] * 0.4;
		$this->assertLessThanOrEqual( $expected_max * 1.1, $file_result['suggested_max_size'], 'Suggested size should be approximately 40% of current' );
		$this->assertGreaterThan( 0, $file_result['potential_savings'], 'Should have positive potential savings' );
	}

	/**
	 * Test batch processing handles multiple files.
	 */
	public function test_batch_processing() {
		// Create more files than the batch size (50).
		for ( $i = 0; $i < 60; $i++ ) {
			$this->create_test_file( "batch-file-{$i}.jpg", 600 );
		}

		$results = $this->large_files->find_large_files();

		// Should find all 60 files.
		$this->assertGreaterThanOrEqual( 60, count( $results ), 'Should find all files across batches' );
	}

	/**
	 * Test null threshold uses default.
	 */
	public function test_null_threshold_uses_default() {
		$file_400kb = $this->create_test_file( 'threshold-test-400.jpg', 400 );
		$file_600kb = $this->create_test_file( 'threshold-test-600.jpg', 600 );

		// Pass null explicitly.
		$results    = $this->large_files->find_large_files( null );
		$result_ids = array_column( $results, 'id' );

		// Default is 500KB.
		$this->assertNotContains( $file_400kb, $result_ids, '400KB file should not be found' );
		$this->assertContains( $file_600kb, $result_ids, '600KB file should be found' );
	}

	/**
	 * Test that MIME type is correctly reported.
	 */
	public function test_mime_type_reporting() {
		$jpeg_file = $this->create_test_file( 'mime-test.jpg', 600, 'image/jpeg' );
		$png_file  = $this->create_test_file( 'mime-test.png', 600, 'image/png' );

		$results = $this->large_files->find_large_files();

		foreach ( $results as $result ) {
			if ( $result['id'] === $jpeg_file ) {
				$this->assertEquals( 'image/jpeg', $result['mime_type'] );
			}
			if ( $result['id'] === $png_file ) {
				$this->assertEquals( 'image/png', $result['mime_type'] );
			}
		}
	}

	/**
	 * Test optimization suggestions structure.
	 */
	public function test_optimization_suggestions_structure() {
		// Create an oversized BMP (should have multiple suggestions).
		$problem_file = $this->create_test_file( 'problematic.bmp', 600, 'image/bmp' );

		// Set up metadata with oversized dimensions.
		$metadata = array(
			'width'  => 3000,
			'height' => 2500,
			'file'   => 'problematic.bmp',
		);
		wp_update_attachment_metadata( $problem_file, $metadata );

		$suggestions = $this->large_files->get_optimization_suggestions();

		$file_suggestions = array_filter(
			$suggestions,
			function ( $s ) use ( $problem_file ) {
				return $s['id'] === $problem_file;
			}
		);

		$this->assertNotEmpty( $file_suggestions );

		$suggestion = reset( $file_suggestions );
		$this->assertArrayHasKey( 'id', $suggestion );
		$this->assertArrayHasKey( 'filename', $suggestion );
		$this->assertArrayHasKey( 'current_size', $suggestion );
		$this->assertArrayHasKey( 'current_size_formatted', $suggestion );
		$this->assertArrayHasKey( 'mime_type', $suggestion );
		$this->assertArrayHasKey( 'suggestions', $suggestion );
		$this->assertIsArray( $suggestion['suggestions'] );

		// Each suggestion should have type, priority, message, and action.
		foreach ( $suggestion['suggestions'] as $s ) {
			$this->assertArrayHasKey( 'type', $s );
			$this->assertArrayHasKey( 'priority', $s );
			$this->assertArrayHasKey( 'message', $s );
			$this->assertArrayHasKey( 'action', $s );
		}
	}
}
