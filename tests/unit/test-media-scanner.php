<?php
/**
 * Tests for Media Scanner Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WPAdminHealth\Media\Scanner;
use WPAdminHealth\Media\Exclusions;
use WPAdminHealth\Media\Safe_Delete;

/**
 * Test cases for Media Scanner functionality
 */
class Test_Media_Scanner extends Test_Case {

	/**
	 * Scanner instance
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Exclusions instance
	 *
	 * @var Exclusions
	 */
	private $exclusions;

	/**
	 * Safe_Delete instance
	 *
	 * @var Safe_Delete
	 */
	private $safe_delete;

	/**
	 * Test attachments created during tests
	 *
	 * @var array
	 */
	private $test_attachments = array();

	/**
	 * Test upload directory
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Set up test environment
	 */
	protected function set_up() {
		parent::set_up();

		$this->scanner = new Scanner();
		$this->exclusions = new Exclusions();
		$this->safe_delete = new Safe_Delete();

		$upload_dir_info = wp_upload_dir();
		$this->upload_dir = $upload_dir_info['path'];

		// Create the database table for safe delete
		$this->create_safe_delete_table();
	}

	/**
	 * Clean up test environment
	 */
	protected function tear_down() {
		// Clean up test attachments and files
		foreach ( $this->test_attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		// Clean up exclusions
		$this->exclusions->clear_exclusions();

		// Clean up database
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpha_deleted_media';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		parent::tear_down();
	}

	/**
	 * Create the safe delete database table
	 */
	private function create_safe_delete_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpha_deleted_media';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			file_path varchar(500) NOT NULL,
			metadata longtext,
			deleted_at datetime NOT NULL,
			permanent_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create a test image file attachment
	 *
	 * @param string $filename Filename for the test image
	 * @param int    $width Image width in pixels
	 * @param int    $height Image height in pixels
	 * @param int    $parent_post_id Optional parent post ID
	 * @return int Attachment ID
	 */
	private function create_test_image( $filename = 'test-image.jpg', $width = 100, $height = 100, $parent_post_id = 0 ) {
		// Create a simple test image file
		$file_path = $this->upload_dir . '/' . $filename;

		// Create a simple image using GD if available
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = imagecreatetruecolor( $width, $height );
			$bg_color = imagecolorallocate( $image, 255, 255, 255 );
			imagefill( $image, 0, 0, $bg_color );
			imagejpeg( $image, $file_path );
			imagedestroy( $image );
		} else {
			// Fallback: create a minimal valid JPEG file
			$jpeg_data = base64_decode( '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=' );
			file_put_contents( $file_path, $jpeg_data );
		}

		// Create attachment
		$attachment_id = $this->factory()->attachment->create_object(
			$file_path,
			$parent_post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		$this->test_attachments[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Create a large test file
	 *
	 * @param string $filename Filename for the test file
	 * @param int    $size_mb Size in megabytes
	 * @return int Attachment ID
	 */
	private function create_large_test_file( $filename = 'large-file.jpg', $size_mb = 5 ) {
		$file_path = $this->upload_dir . '/' . $filename;

		// Create a file with specified size
		$handle = fopen( $file_path, 'w' );
		$bytes_to_write = $size_mb * 1024 * 1024;
		$chunk_size = 1024;
		$bytes_written = 0;

		while ( $bytes_written < $bytes_to_write ) {
			$write_size = min( $chunk_size, $bytes_to_write - $bytes_written );
			fwrite( $handle, str_repeat( 'x', $write_size ) );
			$bytes_written += $write_size;
		}
		fclose( $handle );

		// Create attachment
		$attachment_id = $this->factory()->attachment->create_object(
			$file_path,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Large Test File',
			)
		);

		$this->test_attachments[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Test unused media detection with post content reference
	 */
	public function test_unused_media_detection_post_content() {
		// Create a test post
		$post_id = $this->create_test_post();

		// Create an attachment used in post content
		$used_attachment = $this->create_test_image( 'used-in-content.jpg' );
		$used_url = wp_get_attachment_url( $used_attachment );

		// Update post content to include the attachment URL
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<p>Image: <img src="' . $used_url . '" /></p>',
			)
		);

		// Create an unused attachment
		$unused_attachment = $this->create_test_image( 'unused.jpg' );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert the used attachment is not in unused list
		$this->assertNotContains( $used_attachment, $unused );

		// Assert the unused attachment is in unused list
		$this->assertContains( $unused_attachment, $unused );
	}

	/**
	 * Test unused media detection with featured image
	 */
	public function test_unused_media_detection_featured_image() {
		// Create a test post
		$post_id = $this->create_test_post();

		// Create an attachment and set it as featured image
		$featured_attachment = $this->create_test_image( 'featured.jpg' );
		set_post_thumbnail( $post_id, $featured_attachment );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert featured image is not in unused list
		$this->assertNotContains( $featured_attachment, $unused );
	}

	/**
	 * Test unused media detection with post parent
	 */
	public function test_unused_media_detection_post_parent() {
		// Create a test post
		$post_id = $this->create_test_post();

		// Create an attachment with post parent
		$attached_image = $this->create_test_image( 'attached.jpg', 100, 100, $post_id );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert attachment with parent is not in unused list
		$this->assertNotContains( $attached_image, $unused );
	}

	/**
	 * Test unused media detection with postmeta (ACF, galleries)
	 */
	public function test_unused_media_detection_postmeta() {
		// Create a test post
		$post_id = $this->create_test_post();

		// Create an attachment and add to postmeta (simulating ACF or gallery)
		$gallery_attachment = $this->create_test_image( 'gallery.jpg' );
		add_post_meta( $post_id, 'gallery_images', $gallery_attachment );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert gallery attachment is not in unused list
		$this->assertNotContains( $gallery_attachment, $unused );
	}

	/**
	 * Test unused media detection with Elementor data
	 */
	public function test_unused_media_detection_elementor() {
		// Create a test post
		$post_id = $this->create_test_post();

		// Create an attachment
		$elementor_attachment = $this->create_test_image( 'elementor.jpg' );

		// Simulate Elementor data with attachment ID
		$elementor_data = array(
			array(
				'elements' => array(
					array(
						'settings' => array(
							'image' => array(
								'id' => $elementor_attachment,
							),
						),
					),
				),
			),
		);

		add_post_meta( $post_id, '_elementor_data', wp_json_encode( $elementor_data ) );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert Elementor attachment is not in unused list
		$this->assertNotContains( $elementor_attachment, $unused );
	}

	/**
	 * Test unused media detection with options table
	 */
	public function test_unused_media_detection_options() {
		// Create an attachment
		$option_attachment = $this->create_test_image( 'site-logo.jpg' );

		// Add to options (simulating site logo or similar)
		update_option( 'site_logo', $option_attachment );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert option attachment is not in unused list
		$this->assertNotContains( $option_attachment, $unused );

		// Cleanup
		delete_option( 'site_logo' );
	}

	/**
	 * Test duplicate file detection accuracy
	 */
	public function test_duplicate_detection() {
		// Create first image
		$attachment1 = $this->create_test_image( 'original.jpg' );
		$file1 = get_attached_file( $attachment1 );

		// Create duplicate by copying the file
		$file2 = $this->upload_dir . '/duplicate.jpg';
		copy( $file1, $file2 );

		$attachment2 = $this->factory()->attachment->create_object(
			$file2,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Duplicate Image',
			)
		);
		$this->test_attachments[] = $attachment2;

		// Create a unique image (different content)
		$attachment3 = $this->create_test_image( 'unique.jpg', 200, 200 );

		// Find duplicates
		$duplicates = $this->scanner->find_duplicate_files();

		// Should find at least one duplicate group
		$this->assertNotEmpty( $duplicates );

		// Check that attachment1 and attachment2 are in the same duplicate group
		$found_duplicate_group = false;
		foreach ( $duplicates as $hash => $group ) {
			if ( in_array( $attachment1, $group, true ) && in_array( $attachment2, $group, true ) ) {
				$found_duplicate_group = true;
				break;
			}
		}

		$this->assertTrue( $found_duplicate_group, 'Duplicate images should be grouped together' );

		// Verify unique image is not in any duplicate group
		foreach ( $duplicates as $hash => $group ) {
			$this->assertNotContains( $attachment3, $group, 'Unique image should not be in duplicate groups' );
		}
	}

	/**
	 * Test large file threshold detection
	 */
	public function test_large_file_threshold() {
		// Create a 2MB file
		$small_file = $this->create_large_test_file( 'small.jpg', 2 );

		// Create a 6MB file
		$large_file = $this->create_large_test_file( 'large.jpg', 6 );

		// Find files larger than 5MB
		$large_files = $this->scanner->find_large_files( 5 );

		// Extract IDs from results
		$large_file_ids = array_column( $large_files, 'id' );

		// Assert large file is found
		$this->assertContains( $large_file, $large_file_ids, 'Large file (6MB) should be detected' );

		// Assert small file is not found
		$this->assertNotContains( $small_file, $large_file_ids, 'Small file (2MB) should not be detected' );

		// Verify the size is correctly reported
		foreach ( $large_files as $file_info ) {
			if ( $file_info['id'] === $large_file ) {
				$size_mb = $file_info['size'] / ( 1024 * 1024 );
				$this->assertGreaterThan( 5, $size_mb, 'Reported size should be greater than 5MB' );
			}
		}
	}

	/**
	 * Test that different file size thresholds work correctly
	 */
	public function test_large_file_various_thresholds() {
		// Create files of different sizes
		$file_1mb = $this->create_large_test_file( '1mb.jpg', 1 );
		$file_3mb = $this->create_large_test_file( '3mb.jpg', 3 );
		$file_5mb = $this->create_large_test_file( '5mb.jpg', 5 );

		// Test 2MB threshold
		$files_2mb = $this->scanner->find_large_files( 2 );
		$ids_2mb = array_column( $files_2mb, 'id' );

		$this->assertNotContains( $file_1mb, $ids_2mb, '1MB file should not be found with 2MB threshold' );
		$this->assertContains( $file_3mb, $ids_2mb, '3MB file should be found with 2MB threshold' );
		$this->assertContains( $file_5mb, $ids_2mb, '5MB file should be found with 2MB threshold' );

		// Test 4MB threshold
		$files_4mb = $this->scanner->find_large_files( 4 );
		$ids_4mb = array_column( $files_4mb, 'id' );

		$this->assertNotContains( $file_1mb, $ids_4mb, '1MB file should not be found with 4MB threshold' );
		$this->assertNotContains( $file_3mb, $ids_4mb, '3MB file should not be found with 4MB threshold' );
		$this->assertContains( $file_5mb, $ids_4mb, '5MB file should be found with 4MB threshold' );
	}

	/**
	 * Test exclusions are respected in unused media detection
	 */
	public function test_exclusions_respected() {
		// Create two unused attachments
		$unused1 = $this->create_test_image( 'unused1.jpg' );
		$unused2 = $this->create_test_image( 'unused2.jpg' );

		// Add one to exclusions
		$this->exclusions->add_exclusion( $unused1, 'Test exclusion' );

		// Find unused media
		$unused = $this->scanner->find_unused_media();

		// Assert excluded attachment is not in results
		$this->assertNotContains( $unused1, $unused, 'Excluded attachment should not be in unused results' );

		// Assert non-excluded unused attachment is in results
		$this->assertContains( $unused2, $unused, 'Non-excluded unused attachment should be in results' );
	}

	/**
	 * Test exclusion add and remove
	 */
	public function test_exclusion_management() {
		$attachment = $this->create_test_image( 'test-exclusion.jpg' );

		// Test adding exclusion
		$result = $this->exclusions->add_exclusion( $attachment, 'Test reason' );
		$this->assertTrue( $result, 'Adding exclusion should succeed' );

		// Test checking if excluded
		$this->assertTrue( $this->exclusions->is_excluded( $attachment ), 'Attachment should be excluded' );

		// Test getting exclusions
		$all_exclusions = $this->exclusions->get_exclusions();
		$this->assertNotEmpty( $all_exclusions, 'Should have at least one exclusion' );

		// Test removing exclusion
		$result = $this->exclusions->remove_exclusion( $attachment );
		$this->assertTrue( $result, 'Removing exclusion should succeed' );

		// Test checking after removal
		$this->assertFalse( $this->exclusions->is_excluded( $attachment ), 'Attachment should not be excluded after removal' );
	}

	/**
	 * Test bulk exclusion operations
	 */
	public function test_bulk_exclusions() {
		$attachment1 = $this->create_test_image( 'bulk1.jpg' );
		$attachment2 = $this->create_test_image( 'bulk2.jpg' );
		$attachment3 = $this->create_test_image( 'bulk3.jpg' );

		$ids = array( $attachment1, $attachment2, $attachment3 );

		// Test bulk add
		$result = $this->exclusions->bulk_add_exclusions( $ids, 'Bulk test' );
		$this->assertEquals( 3, $result['success'], 'Should successfully add 3 exclusions' );
		$this->assertEmpty( $result['failed'], 'Should have no failed exclusions' );

		// Verify all are excluded
		foreach ( $ids as $id ) {
			$this->assertTrue( $this->exclusions->is_excluded( $id ), "Attachment {$id} should be excluded" );
		}
	}

	/**
	 * Test safe delete prepare deletion
	 */
	public function test_safe_delete_prepare() {
		$attachment = $this->create_test_image( 'to-delete.jpg' );
		$file_path = get_attached_file( $attachment );

		// Verify file exists before deletion
		$this->assertFileExists( $file_path );

		// Prepare deletion
		$result = $this->safe_delete->prepare_deletion( array( $attachment ) );

		$this->assertTrue( $result['success'], 'Prepare deletion should succeed' );
		$this->assertNotEmpty( $result['prepared_items'], 'Should have prepared items' );

		// Verify file was moved to trash
		$this->assertFileDoesNotExist( $file_path, 'Original file should be moved' );

		// Verify attachment post was soft-deleted
		$post = get_post( $attachment );
		$this->assertNull( $post, 'Attachment post should be deleted' );

		// Verify deletion queue
		$queue = $this->safe_delete->get_deletion_queue();
		$this->assertNotEmpty( $queue, 'Deletion queue should not be empty' );
	}

	/**
	 * Test safe delete restore functionality
	 */
	public function test_safe_delete_restore() {
		$attachment = $this->create_test_image( 'to-restore.jpg' );
		$original_file_path = get_attached_file( $attachment );
		$original_title = get_the_title( $attachment );

		// Prepare deletion
		$delete_result = $this->safe_delete->prepare_deletion( array( $attachment ) );
		$this->assertTrue( $delete_result['success'], 'Prepare deletion should succeed' );

		$deletion_id = $delete_result['prepared_items'][0]['deletion_id'];

		// Restore the file
		$restore_result = $this->safe_delete->restore_deleted( $deletion_id );

		$this->assertTrue( $restore_result['success'], 'Restore should succeed' );
		$this->assertArrayHasKey( 'attachment_id', $restore_result, 'Should return new attachment ID' );

		$new_attachment_id = $restore_result['attachment_id'];

		// Verify file was restored
		$restored_file = get_attached_file( $new_attachment_id );
		$this->assertFileExists( $restored_file, 'Restored file should exist' );

		// Verify metadata was restored
		$restored_title = get_the_title( $new_attachment_id );
		$this->assertEquals( $original_title, $restored_title, 'Title should be restored' );

		// Add to test attachments for cleanup
		$this->test_attachments[] = $new_attachment_id;
	}

	/**
	 * Test safe delete permanent deletion
	 */
	public function test_safe_delete_permanent() {
		$attachment = $this->create_test_image( 'to-permanently-delete.jpg' );

		// Prepare deletion
		$delete_result = $this->safe_delete->prepare_deletion( array( $attachment ) );
		$this->assertTrue( $delete_result['success'], 'Prepare deletion should succeed' );

		$deletion_id = $delete_result['prepared_items'][0]['deletion_id'];
		$trash_file_path = $delete_result['prepared_items'][0]['file_path'];

		// Verify file exists in trash
		$this->assertFileExists( $trash_file_path, 'File should exist in trash' );

		// Execute permanent deletion
		$result = $this->safe_delete->execute_deletion( $deletion_id );

		$this->assertTrue( $result['success'], 'Permanent deletion should succeed' );

		// Verify file was removed from trash
		$this->assertFileDoesNotExist( $trash_file_path, 'File should be removed from trash' );

		// Verify it appears in history
		$history = $this->safe_delete->get_deleted_history();
		$found_in_history = false;
		foreach ( $history as $item ) {
			if ( $item['id'] === $deletion_id ) {
				$found_in_history = true;
				$this->assertNotNull( $item['permanent_at'], 'Should have permanent_at timestamp' );
				break;
			}
		}
		$this->assertTrue( $found_in_history, 'Deletion should appear in history' );
	}

	/**
	 * Test that restore fails after permanent deletion
	 */
	public function test_safe_delete_restore_after_permanent_fails() {
		$attachment = $this->create_test_image( 'permanent-then-restore.jpg' );

		// Prepare deletion
		$delete_result = $this->safe_delete->prepare_deletion( array( $attachment ) );
		$deletion_id = $delete_result['prepared_items'][0]['deletion_id'];

		// Execute permanent deletion
		$this->safe_delete->execute_deletion( $deletion_id );

		// Try to restore
		$restore_result = $this->safe_delete->restore_deleted( $deletion_id );

		$this->assertFalse( $restore_result['success'], 'Restore should fail after permanent deletion' );
	}

	/**
	 * Test missing alt text detection
	 */
	public function test_missing_alt_text_detection() {
		// Create image with alt text
		$with_alt = $this->create_test_image( 'with-alt.jpg' );
		update_post_meta( $with_alt, '_wp_attachment_image_alt', 'This is alt text' );

		// Create image without alt text
		$without_alt = $this->create_test_image( 'without-alt.jpg' );

		// Find missing alt text
		$missing_alt = $this->scanner->find_missing_alt_text();

		// Assert image with alt text is not in results
		$this->assertNotContains( $with_alt, $missing_alt, 'Image with alt text should not be in results' );

		// Assert image without alt text is in results
		$this->assertContains( $without_alt, $missing_alt, 'Image without alt text should be in results' );
	}

	/**
	 * Test scan_all_media aggregates counts correctly
	 */
	public function test_scan_all_media() {
		// Create some test attachments
		$this->create_test_image( 'test1.jpg' );
		$this->create_test_image( 'test2.jpg' );
		$this->create_test_image( 'test3.jpg' );

		// Run full scan
		$results = $this->scanner->scan_all_media();

		// Verify results structure
		$this->assertArrayHasKey( 'total_count', $results );
		$this->assertArrayHasKey( 'total_size', $results );
		$this->assertArrayHasKey( 'scanned_at', $results );

		// Verify counts are positive
		$this->assertGreaterThan( 0, $results['total_count'], 'Should have attachments' );
		$this->assertGreaterThan( 0, $results['total_size'], 'Should have total size' );
	}

	/**
	 * Test media count accuracy
	 */
	public function test_media_count() {
		$initial_count = $this->scanner->get_media_count();

		// Create 3 new attachments
		$this->create_test_image( 'count1.jpg' );
		$this->create_test_image( 'count2.jpg' );
		$this->create_test_image( 'count3.jpg' );

		$new_count = $this->scanner->get_media_count();

		$this->assertEquals( $initial_count + 3, $new_count, 'Media count should increase by 3' );
	}

	/**
	 * Test no false positives for used media in complex scenarios
	 */
	public function test_no_false_positives_complex() {
		// Create post with multiple media references
		$post_id = $this->create_test_post();

		// Featured image
		$featured = $this->create_test_image( 'featured-complex.jpg' );
		set_post_thumbnail( $post_id, $featured );

		// In content
		$in_content = $this->create_test_image( 'in-content-complex.jpg' );
		$url = wp_get_attachment_url( $in_content );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<img src="' . $url . '" />',
			)
		);

		// In meta (gallery)
		$in_gallery = $this->create_test_image( 'in-gallery-complex.jpg' );
		add_post_meta( $post_id, 'gallery', $in_gallery );

		// Find unused
		$unused = $this->scanner->find_unused_media();

		// None of these should be marked as unused
		$this->assertNotContains( $featured, $unused, 'Featured image should not be unused' );
		$this->assertNotContains( $in_content, $unused, 'Image in content should not be unused' );
		$this->assertNotContains( $in_gallery, $unused, 'Image in gallery should not be unused' );
	}
}
