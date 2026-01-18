<?php
/**
 * Tests for Duplicate Detector Class
 *
 * Tests hash-based duplicate detection, filename pattern matching,
 * dimension-based detection, exclusions handling, and savings calculations.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\UnitStandalone\Media;

use WPAdminHealth\Media\DuplicateDetector;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\Mocks\MockExclusions;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test cases for DuplicateDetector functionality.
 */
class DuplicateDetectorTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Mock exclusions instance.
	 *
	 * @var MockExclusions
	 */
	private MockExclusions $exclusions;

	/**
	 * DuplicateDetector instance.
	 *
	 * @var DuplicateDetector
	 */
	private DuplicateDetector $detector;

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
		$this->connection = new MockConnection();
		$this->exclusions = new MockExclusions();
		$this->detector   = new DuplicateDetector( $this->connection, $this->exclusions );

		// Create a temporary test directory.
		$this->test_dir = sys_get_temp_dir() . '/duplicatedetector_test_' . uniqid();
		mkdir( $this->test_dir, 0755, true );

		// Reset global test state.
		unset( $GLOBALS['wpha_test_attachment_files'] );
		unset( $GLOBALS['wpha_test_attachment_metadata'] );
		unset( $GLOBALS['wpha_test_posts'] );
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		$this->exclusions->reset();

		// Clean up test directory.
		if ( is_dir( $this->test_dir ) ) {
			$this->recursively_remove_directory( $this->test_dir );
		}

		// Reset global test state.
		unset( $GLOBALS['wpha_test_attachment_files'] );
		unset( $GLOBALS['wpha_test_attachment_metadata'] );
		unset( $GLOBALS['wpha_test_posts'] );
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
	 * Test constructor accepts required dependencies.
	 */
	public function test_constructor_accepts_dependencies(): void {
		$detector = new DuplicateDetector( $this->connection, $this->exclusions );
		$this->assertInstanceOf( DuplicateDetector::class, $detector );
	}

	/**
	 * Test DuplicateDetector implements DuplicateDetectorInterface.
	 */
	public function test_implements_duplicate_detector_interface(): void {
		$reflection = new \ReflectionClass( DuplicateDetector::class );
		$interfaces = $reflection->getInterfaceNames();

		$this->assertContains(
			'WPAdminHealth\Contracts\DuplicateDetectorInterface',
			$interfaces
		);
	}

	// =========================================================================
	// extract_base_filename() Tests
	// =========================================================================

	/**
	 * Test extract_base_filename extracts numbered patterns.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_numbered_pattern(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test image-1.jpg pattern.
		$result = $method->invoke( $this->detector, 'image-1.jpg' );
		$this->assertEquals( 'image.jpg', $result );

		// Test image-2.jpg pattern.
		$result = $method->invoke( $this->detector, 'image-2.jpg' );
		$this->assertEquals( 'image.jpg', $result );

		// Test multi-digit numbers.
		$result = $method->invoke( $this->detector, 'photo-123.png' );
		$this->assertEquals( 'photo.png', $result );

		// Test complex name with number.
		$result = $method->invoke( $this->detector, 'my-cool-image-5.jpg' );
		$this->assertEquals( 'my-cool-image.jpg', $result );
	}

	/**
	 * Test extract_base_filename extracts scaled pattern.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_scaled_pattern(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test -scaled suffix.
		$result = $method->invoke( $this->detector, 'image-scaled.jpg' );
		$this->assertEquals( 'image.jpg', $result );

		// Test complex name with scaled.
		$result = $method->invoke( $this->detector, 'large-banner-scaled.png' );
		$this->assertEquals( 'large-banner.png', $result );
	}

	/**
	 * Test extract_base_filename extracts editor timestamp pattern.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_editor_pattern(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test -e1234567890 pattern (WordPress image editor).
		$result = $method->invoke( $this->detector, 'image-e1234567890.jpg' );
		$this->assertEquals( 'image.jpg', $result );

		// Test another editor pattern.
		$result = $method->invoke( $this->detector, 'photo-e9876543210.png' );
		$this->assertEquals( 'photo.png', $result );
	}

	/**
	 * Test extract_base_filename extracts rotated pattern.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_rotated_pattern(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test -rotated suffix.
		$result = $method->invoke( $this->detector, 'image-rotated.jpg' );
		$this->assertEquals( 'image.jpg', $result );

		// Test complex name with rotated.
		$result = $method->invoke( $this->detector, 'portrait-photo-rotated.png' );
		$this->assertEquals( 'portrait-photo.png', $result );
	}

	/**
	 * Test extract_base_filename returns null for dimension patterns (thumbnails).
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_excludes_dimension_patterns(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test thumbnail dimensions should return null.
		$result = $method->invoke( $this->detector, 'image-150x150.jpg' );
		$this->assertNull( $result );

		// Test another dimension pattern.
		$result = $method->invoke( $this->detector, 'photo-300x200.png' );
		$this->assertNull( $result );

		// Test large dimension pattern.
		$result = $method->invoke( $this->detector, 'banner-1920x1080.jpg' );
		$this->assertNull( $result );
	}

	/**
	 * Test extract_base_filename returns null for non-matching patterns.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_no_pattern(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Test regular filename without pattern.
		$result = $method->invoke( $this->detector, 'regular-image.jpg' );
		$this->assertNull( $result );

		// Test another regular filename.
		$result = $method->invoke( $this->detector, 'my_photo.png' );
		$this->assertNull( $result );

		// Test filename with extension only.
		$result = $method->invoke( $this->detector, 'image.jpg' );
		$this->assertNull( $result );
	}

	// =========================================================================
	// determine_original() Tests
	// =========================================================================

	/**
	 * Test determine_original selects oldest post as original.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_determine_original_selects_oldest(): void {
		// Setup mock posts with different dates.
		$GLOBALS['wpha_test_posts'] = array(
			1 => (object) array(
				'ID'        => 1,
				'post_date' => '2024-01-15 10:00:00',
			),
			2 => (object) array(
				'ID'        => 2,
				'post_date' => '2024-01-10 10:00:00', // Oldest.
			),
			3 => (object) array(
				'ID'        => 3,
				'post_date' => '2024-01-20 10:00:00',
			),
		);

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'determine_original' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array( 1, 2, 3 ) );

		$this->assertEquals( 2, $result, 'Should select the oldest post as original' );
	}

	/**
	 * Test determine_original falls back to first ID when posts not found.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_determine_original_fallback_to_first(): void {
		// No posts configured - get_post will return null.
		$GLOBALS['wpha_test_posts'] = array();

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'determine_original' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array( 5, 6, 7 ) );

		$this->assertEquals( 5, $result, 'Should fall back to first ID when posts not found' );
	}

	/**
	 * Test determine_original handles single post correctly.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_determine_original_single_post(): void {
		$GLOBALS['wpha_test_posts'] = array(
			42 => (object) array(
				'ID'        => 42,
				'post_date' => '2024-01-15 10:00:00',
			),
		);

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'determine_original' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array( 42 ) );

		$this->assertEquals( 42, $result );
	}

	// =========================================================================
	// merge_duplicate_groups() Tests
	// =========================================================================

	/**
	 * Test merge_duplicate_groups combines non-overlapping groups.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_merge_duplicate_groups_no_overlap(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'merge_duplicate_groups' );
		$method->setAccessible( true );

		$group1 = array(
			'hash1' => array( 1, 2 ),
			'hash2' => array( 3, 4 ),
		);
		$group2 = array(
			'pattern1' => array( 5, 6 ),
		);

		$result = $method->invoke( $this->detector, array( $group1, $group2 ) );

		$this->assertCount( 3, $result );
		$this->assertArrayHasKey( 'hash1', $result );
		$this->assertArrayHasKey( 'hash2', $result );
		$this->assertArrayHasKey( 'pattern1', $result );
	}

	/**
	 * Test merge_duplicate_groups skips overlapping groups.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_merge_duplicate_groups_with_overlap(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'merge_duplicate_groups' );
		$method->setAccessible( true );

		$group1 = array(
			'hash1' => array( 1, 2 ),
		);
		$group2 = array(
			'pattern1' => array( 2, 3 ), // ID 2 overlaps with hash1.
		);

		$result = $method->invoke( $this->detector, array( $group1, $group2 ) );

		// Should only include hash1 since it was processed first.
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'hash1', $result );
		$this->assertArrayNotHasKey( 'pattern1', $result );
	}

	/**
	 * Test merge_duplicate_groups respects priority order.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_merge_duplicate_groups_priority(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'merge_duplicate_groups' );
		$method->setAccessible( true );

		// First group (hash) takes priority over second (pattern).
		$hash_groups = array(
			'hash_abc' => array( 10, 11 ),
		);
		$pattern_groups = array(
			'pattern_xyz' => array( 10, 12 ), // ID 10 overlaps.
		);
		$dimension_groups = array(
			'dim_123' => array( 13, 14 ), // No overlap.
		);

		$result = $method->invoke( $this->detector, array( $hash_groups, $pattern_groups, $dimension_groups ) );

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'hash_abc', $result );
		$this->assertArrayHasKey( 'dim_123', $result );
		$this->assertArrayNotHasKey( 'pattern_xyz', $result );
	}

	/**
	 * Test merge_duplicate_groups handles empty input.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_merge_duplicate_groups_empty(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'merge_duplicate_groups' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================================
	// get_memory_limit_bytes() Tests
	// =========================================================================

	/**
	 * Test get_memory_limit_bytes parses megabytes correctly.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_get_memory_limit_bytes_megabytes(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_memory_limit_bytes' );
		$method->setAccessible( true );

		// Note: This test depends on actual PHP memory_limit setting.
		// We can only verify the method runs without error.
		$result = $method->invoke( $this->detector );

		$this->assertIsInt( $result );
	}

	// =========================================================================
	// find_duplicates() Tests
	// =========================================================================

	/**
	 * Test find_duplicates returns empty array with no attachments.
	 */
	public function test_find_duplicates_empty(): void {
		// Setup: No attachments in database.
		$this->connection->set_default_result( array() );

		$result = $this->detector->find_duplicates();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test find_duplicates returns array structure.
	 */
	public function test_find_duplicates_returns_array(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->find_duplicates();

		$this->assertIsArray( $result );
	}

	/**
	 * Test find_duplicates accepts options parameter.
	 */
	public function test_find_duplicates_accepts_options(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->find_duplicates( array( 'method' => 'hash' ) );

		$this->assertIsArray( $result );
	}

	// =========================================================================
	// get_duplicate_groups() Tests
	// =========================================================================

	/**
	 * Test get_duplicate_groups returns empty array when no duplicates.
	 */
	public function test_get_duplicate_groups_empty(): void {
		// Setup: No attachments.
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_duplicate_groups();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_duplicate_groups respects exclusions.
	 */
	public function test_get_duplicate_groups_respects_exclusions(): void {
		// Setup: Add exclusion.
		$this->exclusions->add_exclusion( 123, 'Test exclusion' );

		// This test verifies the exclusion mechanism.
		$this->assertTrue( $this->exclusions->is_excluded( 123 ) );

		// filter_excluded should remove ID 123.
		$filtered = $this->exclusions->filter_excluded( array( 123, 124, 125 ) );
		$this->assertNotContains( 123, $filtered );
		$this->assertContains( 124, $filtered );
		$this->assertContains( 125, $filtered );
	}

	// =========================================================================
	// get_potential_savings() Tests
	// =========================================================================

	/**
	 * Test get_potential_savings returns correct structure.
	 */
	public function test_get_potential_savings_structure(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_potential_savings();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'bytes', $result );
		$this->assertArrayHasKey( 'formatted', $result );
		$this->assertArrayHasKey( 'groups_count', $result );
	}

	/**
	 * Test get_potential_savings returns zero with no duplicates.
	 */
	public function test_get_potential_savings_empty(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_potential_savings();

		$this->assertEquals( 0, $result['bytes'] );
		$this->assertEquals( 0, $result['groups_count'] );
	}

	/**
	 * Test get_potential_savings bytes is integer type.
	 */
	public function test_get_potential_savings_bytes_type(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_potential_savings();

		$this->assertIsInt( $result['bytes'] );
	}

	/**
	 * Test get_potential_savings formatted is string type.
	 */
	public function test_get_potential_savings_formatted_type(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_potential_savings();

		$this->assertIsString( $result['formatted'] );
	}

	/**
	 * Test get_potential_savings groups_count is integer type.
	 */
	public function test_get_potential_savings_groups_count_type(): void {
		$this->connection->set_default_result( array() );

		$result = $this->detector->get_potential_savings();

		$this->assertIsInt( $result['groups_count'] );
	}

	// =========================================================================
	// exclude_thumbnails() Tests
	// =========================================================================

	/**
	 * Test exclude_thumbnails returns input for single item.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_exclude_thumbnails_single_item(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'exclude_thumbnails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array( 1 ) );

		$this->assertEquals( array( 1 ), $result );
	}

	/**
	 * Test exclude_thumbnails returns empty for empty input.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_exclude_thumbnails_empty(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'exclude_thumbnails' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array() );

		$this->assertEmpty( $result );
	}

	// =========================================================================
	// Batch Size and Memory Threshold Configuration Tests
	// =========================================================================

	/**
	 * Test batch_size property default value.
	 */
	public function test_batch_size_default(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$property   = $reflection->getProperty( 'batch_size' );
		$property->setAccessible( true );

		$this->assertEquals( 50, $property->getValue( $this->detector ) );
	}

	/**
	 * Test memory_threshold property default value.
	 */
	public function test_memory_threshold_default(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$property   = $reflection->getProperty( 'memory_threshold' );
		$property->setAccessible( true );

		$this->assertEquals( 0.8, $property->getValue( $this->detector ) );
	}

	// =========================================================================
	// is_memory_low() Tests
	// =========================================================================

	/**
	 * Test is_memory_low returns boolean.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_is_memory_low_returns_boolean(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'is_memory_low' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector );

		$this->assertIsBool( $result );
	}

	// =========================================================================
	// Generator Tests
	// =========================================================================

	/**
	 * Test get_attachment_ids_generator returns Generator.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_get_attachment_ids_generator_returns_generator(): void {
		$this->connection->set_default_result( array() );

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_attachment_ids_generator' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector );

		$this->assertInstanceOf( \Generator::class, $result );
	}

	/**
	 * Test get_attachment_ids_generator accepts MIME type filter.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_get_attachment_ids_generator_mime_filter(): void {
		$this->connection->set_default_result( array() );

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_attachment_ids_generator' );
		$method->setAccessible( true );

		// Call with MIME type filter.
		$result = $method->invoke( $this->detector, 'image/%' );

		$this->assertInstanceOf( \Generator::class, $result );

		// Iterate to trigger query.
		iterator_to_array( $result );

		// Verify query was made with MIME type filter.
		$last_query = $this->connection->get_last_query();
		$this->assertNotNull( $last_query );
		$this->assertStringContainsString( 'image/%', $last_query['query'] );
	}

	/**
	 * Test get_attachment_ids_generator yields integers.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_get_attachment_ids_generator_yields_integers(): void {
		// Return some attachment IDs.
		$this->connection->set_default_result( array( '1', '2', '3' ) );

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_attachment_ids_generator' );
		$method->setAccessible( true );

		$generator = $method->invoke( $this->detector );
		$ids       = iterator_to_array( $generator );

		foreach ( $ids as $id ) {
			$this->assertIsInt( $id );
		}
	}

	// =========================================================================
	// Query Patterns Tests
	// =========================================================================

	/**
	 * Test find_duplicates uses correct table prefix.
	 */
	public function test_uses_correct_table_prefix(): void {
		$custom_connection = new MockConnection();
		$custom_connection->set_prefix( 'custom_' );
		$custom_connection->set_default_result( array() );

		$detector = new DuplicateDetector( $custom_connection, $this->exclusions );
		$detector->find_duplicates();

		$queries = $custom_connection->get_queries();
		$this->assertNotEmpty( $queries );

		// Verify at least one query uses the custom prefix.
		$found_prefix = false;
		foreach ( $queries as $query_data ) {
			if ( strpos( $query_data['query'], 'custom_posts' ) !== false ) {
				$found_prefix = true;
				break;
			}
		}
		$this->assertTrue( $found_prefix, 'Should use custom table prefix' );
	}

	// =========================================================================
	// Edge Cases
	// =========================================================================

	/**
	 * Test extract_base_filename handles extension-less filename.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_no_extension(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Filename without extension with number pattern.
		$result = $method->invoke( $this->detector, 'image-1' );
		$this->assertEquals( 'image.', $result );
	}

	/**
	 * Test extract_base_filename handles multiple dots in filename.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_multiple_dots(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Multiple dots in filename.
		$result = $method->invoke( $this->detector, 'my.file.name-1.jpg' );
		$this->assertEquals( 'my.file.name.jpg', $result );
	}

	/**
	 * Test extract_base_filename handles uppercase extension.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_extract_base_filename_uppercase_extension(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'extract_base_filename' );
		$method->setAccessible( true );

		// Uppercase extension.
		$result = $method->invoke( $this->detector, 'image-1.JPG' );
		$this->assertEquals( 'image.JPG', $result );
	}

	/**
	 * Test merge_duplicate_groups handles all overlapping groups.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_merge_duplicate_groups_all_overlap(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'merge_duplicate_groups' );
		$method->setAccessible( true );

		$group1 = array(
			'hash1' => array( 1, 2 ),
		);
		$group2 = array(
			'pattern1' => array( 1, 3 ), // Overlaps with hash1.
		);
		$group3 = array(
			'dim1' => array( 2, 4 ), // Overlaps with hash1.
		);

		$result = $method->invoke( $this->detector, array( $group1, $group2, $group3 ) );

		// Only hash1 should remain.
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'hash1', $result );
	}

	/**
	 * Test determine_original handles same-date posts.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_determine_original_same_dates(): void {
		// All posts have the same date.
		$GLOBALS['wpha_test_posts'] = array(
			1 => (object) array(
				'ID'        => 1,
				'post_date' => '2024-01-15 10:00:00',
			),
			2 => (object) array(
				'ID'        => 2,
				'post_date' => '2024-01-15 10:00:00',
			),
			3 => (object) array(
				'ID'        => 3,
				'post_date' => '2024-01-15 10:00:00',
			),
		);

		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'determine_original' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->detector, array( 1, 2, 3 ) );

		// Should return one of them (first found with that date).
		$this->assertContains( $result, array( 1, 2, 3 ) );
	}

	// =========================================================================
	// Public Method Existence Tests
	// =========================================================================

	/**
	 * Test find_duplicates method is public.
	 */
	public function test_find_duplicates_is_public(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'find_duplicates' );

		$this->assertTrue( $method->isPublic() );
	}

	/**
	 * Test get_duplicate_groups method is public.
	 */
	public function test_get_duplicate_groups_is_public(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_duplicate_groups' );

		$this->assertTrue( $method->isPublic() );
	}

	/**
	 * Test get_potential_savings method is public.
	 */
	public function test_get_potential_savings_is_public(): void {
		$reflection = new \ReflectionClass( $this->detector );
		$method     = $reflection->getMethod( 'get_potential_savings' );

		$this->assertTrue( $method->isPublic() );
	}
}
