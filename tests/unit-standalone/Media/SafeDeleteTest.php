<?php
/**
 * Tests for Safe Delete Class
 *
 * Tests two-step deletion mechanism, 30-day recovery window implementation,
 * proper file system cleanup, thumbnail handling, and recovery functionality.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\UnitStandalone\Media;

use WPAdminHealth\Media\SafeDelete;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test cases for SafeDelete functionality.
 */
class SafeDeleteTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * SafeDelete instance.
	 *
	 * @var SafeDelete
	 */
	private SafeDelete $safe_delete;

	/**
	 * Temporary test directory.
	 *
	 * @var string
	 */
	private string $test_dir;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection  = new MockConnection();
		$this->safe_delete = new SafeDelete( $this->connection );

		// Create a temporary test directory.
		$this->test_dir = sys_get_temp_dir() . '/safedelete_test_' . uniqid();
		mkdir( $this->test_dir, 0755, true );
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();

		// Clean up test directory.
		if ( is_dir( $this->test_dir ) ) {
			$this->recursively_remove_directory( $this->test_dir );
		}
	}

	/**
	 * Recursively remove a directory and its contents.
	 *
	 * @param string $dir Directory path.
	 */
	private function recursively_remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->recursively_remove_directory( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Test constructor initializes with ConnectionInterface.
	 */
	public function test_constructor_accepts_connection_interface(): void {
		$safe_delete = new SafeDelete( $this->connection );
		$this->assertInstanceOf( SafeDelete::class, $safe_delete );
	}

	/**
	 * Test constructor sets up correct table name with prefix.
	 */
	public function test_constructor_uses_prefix_for_table_name(): void {
		$custom_connection = new MockConnection();
		$custom_connection->set_prefix( 'custom_' );

		$safe_delete = new SafeDelete( $custom_connection );

		// Use reflection to check table_name.
		$reflection = new \ReflectionClass( $safe_delete );
		$property   = $reflection->getProperty( 'table_name' );
		$property->setAccessible( true );

		$this->assertEquals( 'custom_wpha_deleted_media', $property->getValue( $safe_delete ) );
	}

	/**
	 * Test prepare_deletion returns error for empty array.
	 */
	public function test_prepare_deletion_rejects_empty_array(): void {
		$result = $this->safe_delete->prepare_deletion( array() );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'No valid attachment IDs provided.', $result['message'] );
	}

	/**
	 * Test execute_deletion returns error for non-existent record.
	 */
	public function test_execute_deletion_non_existent_record(): void {
		// Mock: No record found.
		$this->connection->set_default_result( null );

		$result = $this->safe_delete->execute_deletion( 999 );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Deletion record not found.', $result['message'] );
	}

	/**
	 * Test restore_deleted returns error for non-existent or permanently deleted record.
	 */
	public function test_restore_deleted_non_existent_record(): void {
		// Mock: No record found (either doesn't exist or already permanently deleted).
		$this->connection->set_default_result( null );

		$result = $this->safe_delete->restore_deleted( 999 );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Deletion record not found or already permanently deleted.', $result['message'] );
	}

	/**
	 * Test get_deletion_queue returns empty array when no items.
	 */
	public function test_get_deletion_queue_empty(): void {
		// Mock: Empty result set.
		$this->connection->set_expected_result(
			"SELECT * FROM wp_wpha_deleted_media\n\t\t\tWHERE permanent_at IS NULL\n\t\t\tORDER BY deleted_at DESC",
			array()
		);

		$result = $this->safe_delete->get_deletion_queue();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_deletion_queue calculates days_remaining correctly.
	 */
	public function test_get_deletion_queue_calculates_days_remaining(): void {
		// Calculate a date 15 days ago.
		$deleted_at = gmdate( 'Y-m-d H:i:s', strtotime( '-15 days' ) );

		// Mock: One item in queue.
		$this->connection->set_expected_result(
			"SELECT * FROM wp_wpha_deleted_media\n\t\t\tWHERE permanent_at IS NULL\n\t\t\tORDER BY deleted_at DESC",
			array(
				array(
					'id'            => 1,
					'attachment_id' => 123,
					'file_path'     => '/tmp/trash/test.jpg',
					'metadata'      => wp_json_encode(
						array(
							'file_path'      => '/uploads/2024/01/test.jpg',
							'post_mime_type' => 'image/jpeg',
						)
					),
					'deleted_at'    => $deleted_at,
					'permanent_at'  => null,
				),
			)
		);

		$result = $this->safe_delete->get_deletion_queue();

		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]['id'] );
		$this->assertEquals( 123, $result[0]['attachment_id'] );

		// Should have approximately 15 days remaining (30 - 15 = 15).
		$this->assertGreaterThanOrEqual( 14, $result[0]['days_remaining'] );
		$this->assertLessThanOrEqual( 16, $result[0]['days_remaining'] );
		$this->assertTrue( $result[0]['can_restore'] );
	}

	/**
	 * Test get_deletion_queue marks expired items as non-restorable.
	 */
	public function test_get_deletion_queue_expired_item_not_restorable(): void {
		// Calculate a date 35 days ago (past 30-day retention).
		$deleted_at = gmdate( 'Y-m-d H:i:s', strtotime( '-35 days' ) );

		// Mock: One expired item in queue.
		$this->connection->set_expected_result(
			"SELECT * FROM wp_wpha_deleted_media\n\t\t\tWHERE permanent_at IS NULL\n\t\t\tORDER BY deleted_at DESC",
			array(
				array(
					'id'            => 1,
					'attachment_id' => 123,
					'file_path'     => '/tmp/trash/test.jpg',
					'metadata'      => wp_json_encode(
						array(
							'file_path'      => '/uploads/2024/01/test.jpg',
							'post_mime_type' => 'image/jpeg',
						)
					),
					'deleted_at'    => $deleted_at,
					'permanent_at'  => null,
				),
			)
		);

		$result = $this->safe_delete->get_deletion_queue();

		$this->assertCount( 1, $result );
		$this->assertEquals( 0, $result[0]['days_remaining'] );
		$this->assertFalse( $result[0]['can_restore'] );
	}

	/**
	 * Test get_deleted_history returns empty array when no history.
	 */
	public function test_get_deleted_history_empty(): void {
		// Mock: Empty result set.
		$this->connection->set_default_result( array() );

		$result = $this->safe_delete->get_deleted_history();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_deleted_history respects limit parameter.
	 */
	public function test_get_deleted_history_with_custom_limit(): void {
		// The method should prepare a query with the limit.
		$result = $this->safe_delete->get_deleted_history( 25 );

		// Get the last query and verify limit was used.
		$last_query = $this->connection->get_last_query();
		$this->assertNotNull( $last_query );
		$this->assertStringContainsString( 'LIMIT 25', $last_query['query'] );
	}

	/**
	 * Test get_deleted_history uses default limit for invalid values.
	 */
	public function test_get_deleted_history_default_limit_for_invalid(): void {
		$result = $this->safe_delete->get_deleted_history( 0 );

		// Get the last query and verify default limit was used.
		$last_query = $this->connection->get_last_query();
		$this->assertNotNull( $last_query );
		$this->assertStringContainsString( 'LIMIT 100', $last_query['query'] );
	}

	/**
	 * Test auto_purge_expired returns correct structure.
	 */
	public function test_auto_purge_expired_returns_structure(): void {
		// Mock: No expired items.
		$this->connection->set_default_result( array() );

		$result = $this->safe_delete->auto_purge_expired();

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'purged_count', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 0, $result['purged_count'] );
	}

	/**
	 * Test normalize_path method handles various path scenarios.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_normalize_path_handles_traversal(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'normalize_path' );
		$method->setAccessible( true );

		// Test normal path.
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/test.jpg' );
		$this->assertEquals( '/var/www/uploads/test.jpg', $result );

		// Test path with single dots.
		$result = $method->invoke( $this->safe_delete, '/var/./www/./uploads/test.jpg' );
		$this->assertEquals( '/var/www/uploads/test.jpg', $result );

		// Test path with double dots (traversal).
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/../images/test.jpg' );
		$this->assertEquals( '/var/www/images/test.jpg', $result );

		// Test path that tries to escape root.
		$result = $method->invoke( $this->safe_delete, '/var/../../../etc/passwd' );
		$this->assertFalse( $result );

		// Test path with null byte (security bypass attempt).
		$result = $method->invoke( $this->safe_delete, "/var/www/uploads/test\0.jpg" );
		$this->assertFalse( $result );

		// Test relative path (should fail).
		$result = $method->invoke( $this->safe_delete, 'var/www/uploads/test.jpg' );
		$this->assertFalse( $result );

		// Test empty path.
		$result = $method->invoke( $this->safe_delete, '' );
		$this->assertFalse( $result );
	}

	/**
	 * Test is_path_within correctly validates paths.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_is_path_within_validates_correctly(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'is_path_within' );
		$method->setAccessible( true );

		// Test valid path within base.
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/2024/test.jpg', '/var/www/uploads' );
		$this->assertTrue( $result );

		// Test path at base level.
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/test.jpg', '/var/www/uploads' );
		$this->assertTrue( $result );

		// Test path outside base.
		$result = $method->invoke( $this->safe_delete, '/var/www/other/test.jpg', '/var/www/uploads' );
		$this->assertFalse( $result );

		// Test traversal attempt.
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/../other/test.jpg', '/var/www/uploads' );
		$this->assertFalse( $result );

		// Test with trailing slashes.
		$result = $method->invoke( $this->safe_delete, '/var/www/uploads/test.jpg', '/var/www/uploads/' );
		$this->assertTrue( $result );
	}

	/**
	 * Test safe_unlink refuses to delete symlinks.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_safe_unlink_refuses_symlinks(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'safe_unlink' );
		$method->setAccessible( true );

		// Create a real file and a symlink to it.
		$real_file = $this->test_dir . '/real_file.txt';
		$symlink   = $this->test_dir . '/symlink.txt';

		file_put_contents( $real_file, 'test content' );
		symlink( $real_file, $symlink );

		// Attempting to delete the symlink should fail.
		$result = $method->invoke( $this->safe_delete, $symlink, $this->test_dir );
		$this->assertFalse( $result );

		// The symlink and real file should both still exist.
		$this->assertTrue( is_link( $symlink ) );
		$this->assertTrue( file_exists( $real_file ) );

		// Clean up.
		unlink( $symlink );
		unlink( $real_file );
	}

	/**
	 * Test safe_unlink returns true for non-existent file.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_safe_unlink_returns_true_for_nonexistent(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'safe_unlink' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->safe_delete,
			$this->test_dir . '/nonexistent.txt',
			$this->test_dir
		);
		$this->assertTrue( $result );
	}

	/**
	 * Test safe_unlink deletes valid file.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_safe_unlink_deletes_valid_file(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'safe_unlink' );
		$method->setAccessible( true );

		// Create a test file.
		$test_file = $this->test_dir . '/test_file.txt';
		file_put_contents( $test_file, 'test content' );

		$result = $method->invoke( $this->safe_delete, $test_file, $this->test_dir );
		$this->assertTrue( $result );
		$this->assertFalse( file_exists( $test_file ) );
	}

	/**
	 * Test safe_rename refuses to move symlinks.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_safe_rename_refuses_symlinks(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'safe_rename' );
		$method->setAccessible( true );

		// Create directories.
		$source_dir = $this->test_dir . '/source';
		$dest_dir   = $this->test_dir . '/dest';
		mkdir( $source_dir, 0755, true );
		mkdir( $dest_dir, 0755, true );

		// Create a real file and a symlink.
		$real_file = $source_dir . '/real_file.txt';
		$symlink   = $source_dir . '/symlink.txt';
		file_put_contents( $real_file, 'test content' );
		symlink( $real_file, $symlink );

		// Attempting to rename the symlink should fail.
		$result = $method->invoke(
			$this->safe_delete,
			$symlink,
			$dest_dir . '/moved_symlink.txt',
			$source_dir,
			$dest_dir
		);
		$this->assertFalse( $result );

		// Clean up.
		unlink( $symlink );
		unlink( $real_file );
	}

	/**
	 * Test safe_rename moves valid file.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_safe_rename_moves_valid_file(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'safe_rename' );
		$method->setAccessible( true );

		// Create directories.
		$source_dir = $this->test_dir . '/source';
		$dest_dir   = $this->test_dir . '/dest';
		mkdir( $source_dir, 0755, true );
		mkdir( $dest_dir, 0755, true );

		// Create a test file.
		$source_file = $source_dir . '/test_file.txt';
		$dest_file   = $dest_dir . '/moved_file.txt';
		file_put_contents( $source_file, 'test content' );

		$result = $method->invoke(
			$this->safe_delete,
			$source_file,
			$dest_file,
			$source_dir,
			$dest_dir
		);

		$this->assertTrue( $result );
		$this->assertFalse( file_exists( $source_file ) );
		$this->assertTrue( file_exists( $dest_file ) );
		$this->assertEquals( 'test content', file_get_contents( $dest_file ) );
	}

	/**
	 * Test generate_unique_trash_name produces unique names.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_unique_trash_name_is_unique(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'generate_unique_trash_name' );
		$method->setAccessible( true );

		$name1 = $method->invoke( $this->safe_delete, 1, 'test.jpg' );
		$name2 = $method->invoke( $this->safe_delete, 1, 'test.jpg' );

		// Names should be different even for same input.
		$this->assertNotEquals( $name1, $name2 );

		// Names should contain attachment ID.
		$this->assertStringStartsWith( '1_', $name1 );
		$this->assertStringStartsWith( '1_', $name2 );

		// Names should end with sanitized filename.
		$this->assertStringEndsWith( '_test.jpg', $name1 );
		$this->assertStringEndsWith( '_test.jpg', $name2 );
	}

	/**
	 * Test generate_unique_trash_name includes prefix when provided.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_unique_trash_name_with_prefix(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$method     = $reflection->getMethod( 'generate_unique_trash_name' );
		$method->setAccessible( true );

		$name = $method->invoke( $this->safe_delete, 123, 'image.jpg', 'thumbnail' );

		$this->assertStringContainsString( 'thumbnail', $name );
		$this->assertStringEndsWith( '_image.jpg', $name );
	}

	/**
	 * Test SafeDeleteInterface implementation.
	 */
	public function test_implements_safe_delete_interface(): void {
		$reflection = new \ReflectionClass( SafeDelete::class );
		$interfaces = $reflection->getInterfaceNames();

		$this->assertContains(
			'WPAdminHealth\Contracts\SafeDeleteInterface',
			$interfaces
		);
	}

	/**
	 * Test trash_dir constant value.
	 */
	public function test_trash_dir_value(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$property   = $reflection->getProperty( 'trash_dir' );
		$property->setAccessible( true );

		$this->assertEquals( 'wpha-trash', $property->getValue( $this->safe_delete ) );
	}

	/**
	 * Test retention_days is 30 days.
	 */
	public function test_retention_days_is_30(): void {
		$reflection = new \ReflectionClass( $this->safe_delete );
		$property   = $reflection->getProperty( 'retention_days' );
		$property->setAccessible( true );

		$this->assertEquals( 30, $property->getValue( $this->safe_delete ) );
	}

	/**
	 * Test prepare_deletion returns correct structure.
	 */
	public function test_prepare_deletion_result_structure(): void {
		// Mock WordPress functions would be needed for full test.
		// Instead, test with invalid IDs to verify structure.
		$result = $this->safe_delete->prepare_deletion( array( 1, 2 ) );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'prepared_items', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsArray( $result['prepared_items'] );
		$this->assertIsArray( $result['errors'] );
	}

	/**
	 * Test execute_deletion result structure.
	 */
	public function test_execute_deletion_result_structure(): void {
		// Mock: No record found.
		$this->connection->set_default_result( null );

		$result = $this->safe_delete->execute_deletion( 1 );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsBool( $result['success'] );
		$this->assertIsString( $result['message'] );
	}

	/**
	 * Test restore_deleted result structure.
	 */
	public function test_restore_deleted_result_structure(): void {
		// Mock: No record found.
		$this->connection->set_default_result( null );

		$result = $this->safe_delete->restore_deleted( 1 );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsBool( $result['success'] );
		$this->assertIsString( $result['message'] );
	}
}
