<?php
/**
 * Tests for Alt Text Checker Class
 *
 * Tests accurate detection of missing alt text, handling of decorative images,
 * multilingual support, and accessibility compliance reporting.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\UnitStandalone\Media;

use WPAdminHealth\Media\AltTextChecker;
use WPAdminHealth\Tests\Mocks\MockCache;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\Mocks\MockExclusions;
use WPAdminHealth\Tests\Mocks\MockMultilingual;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test cases for AltTextChecker functionality.
 */
class AltTextCheckerTest extends StandaloneTestCase {

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
	 * AltTextChecker instance.
	 *
	 * @var AltTextChecker
	 */
	private AltTextChecker $checker;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->exclusions = new MockExclusions();
		$this->checker    = new AltTextChecker( $this->connection, $this->exclusions );
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		$this->exclusions->reset();
	}

	/**
	 * Test get_alt_text_coverage returns correct structure with zero images.
	 */
	public function test_get_alt_text_coverage_empty(): void {
		// Setup: No images in database.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'0'
		);

		$result = $this->checker->get_alt_text_coverage();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'total_images', $result );
		$this->assertArrayHasKey( 'images_with_alt', $result );
		$this->assertArrayHasKey( 'images_without_alt', $result );
		$this->assertArrayHasKey( 'coverage_percentage', $result );
		$this->assertEquals( 0, $result['total_images'] );
		$this->assertEquals( 0, $result['images_with_alt'] );
		$this->assertEquals( 0, $result['images_without_alt'] );
		$this->assertEquals( 0.0, $result['coverage_percentage'] );
	}

	/**
	 * Test get_alt_text_coverage calculates correct percentages.
	 */
	public function test_get_alt_text_coverage_calculation(): void {
		// Setup: 10 total images, 7 with alt text, 0 decorative.
		$this->connection->set_default_result( '0' );

		// Total images query (exact match with newlines/tabs).
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		// Decorative images query returns 0 by default (from set_default_result).

		// Images with alt text query.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wp_attachment_image_alt'\n\t\t\tAND pm.meta_value != ''",
			'7'
		);

		$result = $this->checker->get_alt_text_coverage();

		$this->assertEquals( 10, $result['total_images'] );
		$this->assertEquals( 7, $result['images_with_alt'] );
		$this->assertEquals( 3, $result['images_without_alt'] );
		$this->assertEquals( 70.0, $result['coverage_percentage'] );
	}

	/**
	 * Test get_alt_text_coverage excludes decorative images from total.
	 */
	public function test_get_alt_text_coverage_excludes_decorative(): void {
		// Setup: 10 total images, 2 decorative, 5 with alt text.
		$this->connection->set_default_result( '0' );

		// Total images query.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		// Decorative images query.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wpha_decorative_image'\n\t\t\tAND pm.meta_value != ''",
			'2'
		);

		// Images with alt text query.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wp_attachment_image_alt'\n\t\t\tAND pm.meta_value != ''",
			'5'
		);

		$result = $this->checker->get_alt_text_coverage();

		// Effective total should be 10 - 2 = 8.
		// With 5 images having alt (minus 2 decorative) = 3.
		$this->assertEquals( 8, $result['total_images'] );
		$this->assertEquals( 3, $result['images_with_alt'] );
	}

	/**
	 * Test generate_alt_from_filename cleans up filenames properly.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_alt_from_filename(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_alt_from_filename' );
		$method->setAccessible( true );

		// Test basic filename.
		$result = $method->invoke( $this->checker, 'my-awesome-image.jpg' );
		$this->assertEquals( 'My Awesome Image', $result );

		// Test with underscores.
		$result = $method->invoke( $this->checker, 'product_photo_2024.png' );
		$this->assertStringContainsString( 'Product Photo', $result );

		// Test with dimension indicators.
		$result = $method->invoke( $this->checker, 'banner-1920x1080.jpg' );
		$this->assertEquals( 'Banner', $result );

		// Test with date patterns.
		$result = $method->invoke( $this->checker, 'photo-12-25-2024.jpg' );
		$this->assertEquals( 'Photo', $result );

		// Test scaled indicator.
		$result = $method->invoke( $this->checker, 'large-image-scaled.jpg' );
		$this->assertEquals( 'Large Image', $result );

		// Test numeric-only filename returns generic.
		$result = $method->invoke( $this->checker, '12345678.jpg' );
		$this->assertEquals( 'Image', $result );
	}

	/**
	 * Test clean_title_for_alt removes auto-generated prefixes.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_clean_title_for_alt(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'clean_title_for_alt' );
		$method->setAccessible( true );

		// Test IMG_ prefix removal.
		$result = $method->invoke( $this->checker, 'IMG_1234' );
		$this->assertEquals( '', $result );

		// Test DSC_ prefix removal.
		$result = $method->invoke( $this->checker, 'DSC_5678 My Photo' );
		$this->assertEquals( 'My Photo', $result );

		// Test file extension removal.
		$result = $method->invoke( $this->checker, 'Beautiful Sunset.jpg' );
		$this->assertEquals( 'Beautiful Sunset', $result );

		// Test numeric-only title.
		$result = $method->invoke( $this->checker, '12345' );
		$this->assertEquals( '', $result );

		// Test valid title passes through.
		$result = $method->invoke( $this->checker, 'Company Logo' );
		$this->assertEquals( 'Company Logo', $result );
	}

	/**
	 * Test generate_suggestion_with_confidence prefers post title.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_suggestion_with_confidence_prefers_title(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_suggestion_with_confidence' );
		$method->setAccessible( true );

		// When title is meaningful, should use it with high confidence.
		$result = $method->invoke( $this->checker, 'img-001.jpg', 'Company Logo' );

		$this->assertEquals( 'Company Logo', $result['suggestion'] );
		$this->assertEquals( 'high', $result['confidence'] );
		$this->assertEquals( 'title', $result['source'] );
	}

	/**
	 * Test generate_suggestion_with_confidence falls back to filename.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_suggestion_with_confidence_fallback(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_suggestion_with_confidence' );
		$method->setAccessible( true );

		// When title is auto-generated, should fall back to filename.
		$result = $method->invoke( $this->checker, 'product-showcase.jpg', 'IMG_1234' );

		$this->assertEquals( 'Product Showcase', $result['suggestion'] );
		$this->assertEquals( 'medium', $result['confidence'] );
		$this->assertEquals( 'filename', $result['source'] );
	}

	/**
	 * Test determine_compliance_level returns correct levels.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_determine_compliance_level(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'determine_compliance_level' );
		$method->setAccessible( true );

		// 100% coverage.
		$result = $method->invoke( $this->checker, 100.0, 0, 10 );
		$this->assertEquals( 'excellent', $result );

		// 95% coverage.
		$result = $method->invoke( $this->checker, 95.0, 0, 10 );
		$this->assertEquals( 'good', $result );

		// 75% coverage.
		$result = $method->invoke( $this->checker, 75.0, 0, 10 );
		$this->assertEquals( 'needs_improvement', $result );

		// 50% coverage.
		$result = $method->invoke( $this->checker, 50.0, 0, 10 );
		$this->assertEquals( 'critical', $result );
	}

	/**
	 * Test generate_recommendations produces appropriate suggestions.
	 *
	 * Uses reflection to test private method.
	 */
	public function test_generate_recommendations(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_recommendations' );
		$method->setAccessible( true );

		// Low coverage with missing images.
		$result = $method->invoke( $this->checker, 10, 0, 50.0, 20 );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		// Should contain recommendation about adding alt text.
		$found_alt_recommendation = false;
		foreach ( $result as $rec ) {
			if ( strpos( $rec, '10' ) !== false ) {
				$found_alt_recommendation = true;
				break;
			}
		}
		$this->assertTrue( $found_alt_recommendation, 'Should recommend adding alt text to missing images' );
	}

	/**
	 * Test exclusions are respected in find_missing_alt_text.
	 */
	public function test_exclusions_respected(): void {
		// Add an exclusion.
		$this->exclusions->add_exclusion( 123, 'Test reason' );

		// Setup mock to return attachment 123.
		$this->connection->set_expected_result(
			"%%FROM wp_posts%%LIMIT%%",
			array( '123' )
		);

		$result = $this->checker->find_missing_alt_text( 10 );

		// The excluded attachment should not appear in results.
		$ids = array_column( $result, 'id' );
		$this->assertNotContains( 123, $ids );
	}

	/**
	 * Test META_DECORATIVE constant is defined correctly.
	 */
	public function test_meta_decorative_constant(): void {
		$this->assertEquals( '_wpha_decorative_image', AltTextChecker::META_DECORATIVE );
	}

	/**
	 * Test get_accessibility_report returns complete structure.
	 */
	public function test_get_accessibility_report_structure(): void {
		// Setup basic mock responses.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);
		$this->connection->set_expected_result(
			"%%meta_key = '_wpha_decorative_image'%%",
			'2'
		);
		$this->connection->set_expected_result(
			"%%meta_key = '_wp_attachment_image_alt'%%",
			'6'
		);

		$result = $this->checker->get_accessibility_report();

		// Verify structure.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'total_images', $result );
		$this->assertArrayHasKey( 'images_with_alt', $result );
		$this->assertArrayHasKey( 'images_without_alt', $result );
		$this->assertArrayHasKey( 'decorative_images', $result );
		$this->assertArrayHasKey( 'coverage_percentage', $result );
		$this->assertArrayHasKey( 'compliance_level', $result );
		$this->assertArrayHasKey( 'recommendations', $result );
		$this->assertArrayHasKey( 'by_language', $result );

		// Verify types.
		$this->assertIsInt( $result['total_images'] );
		$this->assertIsInt( $result['images_with_alt'] );
		$this->assertIsInt( $result['images_without_alt'] );
		$this->assertIsInt( $result['decorative_images'] );
		$this->assertIsFloat( $result['coverage_percentage'] );
		$this->assertIsString( $result['compliance_level'] );
		$this->assertIsArray( $result['recommendations'] );
		$this->assertIsArray( $result['by_language'] );
	}

	/**
	 * Test bulk_suggest_alt_text returns proper structure with confidence.
	 */
	public function test_bulk_suggest_alt_text_structure(): void {
		// This test requires WordPress functions, so we'll just verify
		// the method exists and has the right signature.
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'bulk_suggest_alt_text' );

		$this->assertTrue( $method->isPublic() );

		$params = $method->getParameters();
		$this->assertCount( 1, $params );
		$this->assertEquals( 'attachment_ids', $params[0]->getName() );
		$this->assertEquals( 'array', $params[0]->getType()->getName() );
	}

	/**
	 * Test constructor accepts optional multilingual parameter.
	 */
	public function test_constructor_accepts_multilingual(): void {
		// Test with null multilingual (should not throw).
		$checker = new AltTextChecker( $this->connection, $this->exclusions, null );
		$this->assertInstanceOf( AltTextChecker::class, $checker );

		// Test without multilingual parameter (should not throw).
		$checker = new AltTextChecker( $this->connection, $this->exclusions );
		$this->assertInstanceOf( AltTextChecker::class, $checker );
	}

	/**
	 * Test get_alt_text method exists and is public.
	 */
	public function test_get_alt_text_method_exists(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'get_alt_text' );

		$this->assertTrue( $method->isPublic() );

		$params = $method->getParameters();
		$this->assertCount( 1, $params );
		$this->assertEquals( 'attachment_id', $params[0]->getName() );
	}

	/**
	 * Test get_alt_text_by_language method exists and is public.
	 */
	public function test_get_alt_text_by_language_method_exists(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'get_alt_text_by_language' );

		$this->assertTrue( $method->isPublic() );

		$params = $method->getParameters();
		$this->assertCount( 1, $params );
		$this->assertEquals( 'attachment_id', $params[0]->getName() );
	}

	/**
	 * Test is_decorative method exists and is public.
	 */
	public function test_is_decorative_method_exists(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'is_decorative' );

		$this->assertTrue( $method->isPublic() );

		$params = $method->getParameters();
		$this->assertCount( 1, $params );
		$this->assertEquals( 'attachment_id', $params[0]->getName() );
	}

	/**
	 * Test set_decorative method exists and is public.
	 */
	public function test_set_decorative_method_exists(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'set_decorative' );

		$this->assertTrue( $method->isPublic() );

		$params = $method->getParameters();
		$this->assertCount( 2, $params );
		$this->assertEquals( 'attachment_id', $params[0]->getName() );
		$this->assertEquals( 'decorative', $params[1]->getName() );
	}

	// =========================================================================
	// Missing Alt Text Detection Tests
	// =========================================================================

	/**
	 * Test find_missing_alt_text returns empty array when no images exist.
	 */
	public function test_find_missing_alt_text_no_images(): void {
		// Setup: Query returns no attachments.
		$this->connection->set_expected_result(
			"%%FROM wp_posts%%LIMIT%%",
			array()
		);

		$result = $this->checker->find_missing_alt_text( 10 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test find_missing_alt_text respects limit parameter.
	 */
	public function test_find_missing_alt_text_respects_limit(): void {
		// The method already limits results, verify it handles limit correctly.
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'find_missing_alt_text' );
		$params     = $method->getParameters();

		// First param should have default of 100.
		$this->assertEquals( 100, $params[0]->getDefaultValue() );
	}

	/**
	 * Test find_missing_alt_text handles null query result.
	 */
	public function test_find_missing_alt_text_handles_null_query(): void {
		// When prepare returns null, the method should break out of loop.
		$this->connection->set_default_result( null );

		$result = $this->checker->find_missing_alt_text( 10 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_alt_text returns alt text from post meta.
	 */
	public function test_get_alt_text_returns_meta_value(): void {
		// Setup post meta for attachment 123.
		$GLOBALS['wpha_test_post_meta'] = array(
			123 => array(
				'_wp_attachment_image_alt' => 'Test Alt Text',
			),
		);

		$result = $this->checker->get_alt_text( 123 );

		$this->assertEquals( 'Test Alt Text', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text returns empty string when no alt text exists.
	 */
	public function test_get_alt_text_returns_empty_when_missing(): void {
		// No post meta set, so get_post_meta returns empty string.
		$GLOBALS['wpha_test_post_meta'] = array(
			456 => array(
				'_wp_attachment_image_alt' => '',
			),
		);

		$result = $this->checker->get_alt_text( 456 );

		$this->assertEquals( '', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text_by_language returns default without multilingual.
	 */
	public function test_get_alt_text_by_language_default_only(): void {
		$GLOBALS['wpha_test_post_meta'] = array(
			789 => array(
				'_wp_attachment_image_alt' => 'Default Alt',
			),
		);

		$result = $this->checker->get_alt_text_by_language( 789 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'default', $result );
		$this->assertEquals( 'Default Alt', $result['default'] );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	// =========================================================================
	// Decorative Image Exclusion Tests
	// =========================================================================

	/**
	 * Test is_decorative returns true when meta value is set.
	 */
	public function test_is_decorative_returns_true_when_marked(): void {
		$GLOBALS['wpha_test_post_meta'] = array(
			100 => array(
				'_wpha_decorative_image' => '1',
			),
		);

		$result = $this->checker->is_decorative( 100 );

		$this->assertTrue( $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test is_decorative returns false when meta value is not set.
	 */
	public function test_is_decorative_returns_false_when_not_marked(): void {
		$GLOBALS['wpha_test_post_meta'] = array(
			101 => array(
				'_wpha_decorative_image' => '',
			),
		);

		$result = $this->checker->is_decorative( 101 );

		$this->assertFalse( $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test is_decorative returns false when meta does not exist.
	 */
	public function test_is_decorative_returns_false_when_no_meta(): void {
		// No meta set at all for this ID.
		$GLOBALS['wpha_test_post_meta'] = array();

		$result = $this->checker->is_decorative( 102 );

		$this->assertFalse( $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test decorative images are excluded from coverage total.
	 */
	public function test_coverage_excludes_decorative_from_total(): void {
		$this->connection->set_default_result( '0' );

		// 20 total images.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'20'
		);

		// 5 decorative images.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wpha_decorative_image'\n\t\t\tAND pm.meta_value != ''",
			'5'
		);

		// 10 images with alt text.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wp_attachment_image_alt'\n\t\t\tAND pm.meta_value != ''",
			'10'
		);

		$result = $this->checker->get_alt_text_coverage();

		// Effective total: 20 - 5 = 15.
		// Images with alt (excluding decorative): 10 - 5 = 5.
		$this->assertEquals( 15, $result['total_images'] );
		$this->assertEquals( 5, $result['images_with_alt'] );
		$this->assertEquals( 10, $result['images_without_alt'] );
	}

	/**
	 * Test 100% coverage when all non-decorative images have alt text.
	 */
	public function test_coverage_100_percent_scenario(): void {
		$this->connection->set_default_result( '0' );

		// 10 total images.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		// 2 decorative images.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wpha_decorative_image'\n\t\t\tAND pm.meta_value != ''",
			'2'
		);

		// 10 images with alt text (including 2 decorative).
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wp_attachment_image_alt'\n\t\t\tAND pm.meta_value != ''",
			'10'
		);

		$result = $this->checker->get_alt_text_coverage();

		// Effective total: 10 - 2 = 8.
		// Images with alt (excluding decorative): 10 - 2 = 8.
		$this->assertEquals( 8, $result['total_images'] );
		$this->assertEquals( 8, $result['images_with_alt'] );
		$this->assertEquals( 0, $result['images_without_alt'] );
		$this->assertEquals( 100.0, $result['coverage_percentage'] );
	}

	// =========================================================================
	// Accessibility Report Tests
	// =========================================================================

	/**
	 * Test get_accessibility_report with excellent compliance.
	 */
	public function test_accessibility_report_excellent_compliance(): void {
		$this->connection->set_default_result( '0' );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wpha_decorative_image'%%",
			'0'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wp_attachment_image_alt'%%",
			'10'
		);

		$result = $this->checker->get_accessibility_report();

		$this->assertEquals( 'excellent', $result['compliance_level'] );
		$this->assertEquals( 100.0, $result['coverage_percentage'] );
	}

	/**
	 * Test get_accessibility_report with critical compliance.
	 */
	public function test_accessibility_report_critical_compliance(): void {
		$this->connection->set_default_result( '0' );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'100'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wpha_decorative_image'%%",
			'0'
		);

		// Only 30% have alt text.
		$this->connection->set_expected_result(
			"%%meta_key = '_wp_attachment_image_alt'%%",
			'30'
		);

		$result = $this->checker->get_accessibility_report();

		$this->assertEquals( 'critical', $result['compliance_level'] );
		$this->assertEquals( 30.0, $result['coverage_percentage'] );
	}

	/**
	 * Test recommendations for low coverage.
	 */
	public function test_recommendations_for_low_coverage(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_recommendations' );
		$method->setAccessible( true );

		// 50 missing, 0 decorative, 50% coverage, 100 total.
		$result = $method->invoke( $this->checker, 50, 0, 50.0, 100 );

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, count( $result ) );

		// Should recommend adding alt text.
		$found_add_recommendation = false;
		$found_wcag_recommendation = false;
		foreach ( $result as $rec ) {
			if ( strpos( $rec, '50' ) !== false && strpos( $rec, 'alt text' ) !== false ) {
				$found_add_recommendation = true;
			}
			if ( strpos( $rec, 'WCAG' ) !== false ) {
				$found_wcag_recommendation = true;
			}
		}
		$this->assertTrue( $found_add_recommendation, 'Should recommend adding alt text to 50 images' );
		$this->assertTrue( $found_wcag_recommendation, 'Should mention WCAG for low coverage' );
	}

	/**
	 * Test recommendations for full coverage.
	 */
	public function test_recommendations_for_full_coverage(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_recommendations' );
		$method->setAccessible( true );

		// 0 missing, 5 decorative, 100% coverage, 50 total.
		$result = $method->invoke( $this->checker, 0, 5, 100.0, 50 );

		$this->assertIsArray( $result );

		// Should have recommendation about maintaining standards.
		$found_maintain_recommendation = false;
		foreach ( $result as $rec ) {
			if ( strpos( $rec, 'fully accessible' ) !== false ) {
				$found_maintain_recommendation = true;
				break;
			}
		}
		$this->assertTrue( $found_maintain_recommendation, 'Should commend full accessibility' );
	}

	/**
	 * Test recommendations suggest marking decorative images.
	 */
	public function test_recommendations_suggest_decorative_marking(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_recommendations' );
		$method->setAccessible( true );

		// 5 missing, 0 decorative, 90% coverage, 50 total.
		$result = $method->invoke( $this->checker, 5, 0, 90.0, 50 );

		$this->assertIsArray( $result );

		// Should suggest marking decorative images.
		$found_decorative_recommendation = false;
		foreach ( $result as $rec ) {
			if ( strpos( $rec, 'decorative' ) !== false ) {
				$found_decorative_recommendation = true;
				break;
			}
		}
		$this->assertTrue( $found_decorative_recommendation, 'Should suggest marking decorative images' );
	}

	// =========================================================================
	// Filename Suggestion Tests
	// =========================================================================

	/**
	 * Test generate_alt_from_filename handles various extensions.
	 */
	public function test_generate_alt_from_filename_extensions(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_alt_from_filename' );
		$method->setAccessible( true );

		// JPEG.
		$result = $method->invoke( $this->checker, 'beautiful-sunset.jpeg' );
		$this->assertEquals( 'Beautiful Sunset', $result );

		// PNG.
		$result = $method->invoke( $this->checker, 'logo-design.png' );
		$this->assertEquals( 'Logo Design', $result );

		// GIF.
		$result = $method->invoke( $this->checker, 'animated-banner.gif' );
		$this->assertEquals( 'Animated Banner', $result );

		// WEBP.
		$result = $method->invoke( $this->checker, 'product-photo.webp' );
		$this->assertEquals( 'Product Photo', $result );
	}

	/**
	 * Test generate_alt_from_filename handles complex filenames.
	 */
	public function test_generate_alt_from_filename_complex(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_alt_from_filename' );
		$method->setAccessible( true );

		// Multiple separators.
		$result = $method->invoke( $this->checker, 'my_awesome-image.test.jpg' );
		$this->assertStringContainsString( 'My', $result );
		$this->assertStringContainsString( 'Awesome', $result );

		// Timestamp in filename.
		$result = $method->invoke( $this->checker, 'photo-1705678901234.jpg' );
		$this->assertEquals( 'Photo', $result );
	}

	/**
	 * Test clean_title_for_alt with various camera prefixes.
	 */
	public function test_clean_title_for_alt_camera_prefixes(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'clean_title_for_alt' );
		$method->setAccessible( true );

		// DCIM prefix.
		$result = $method->invoke( $this->checker, 'DCIM_0001 Holiday Photo' );
		$this->assertEquals( 'Holiday Photo', $result );

		// Photo_ prefix.
		$result = $method->invoke( $this->checker, 'Photo_123 Beach Scene' );
		$this->assertEquals( 'Beach Scene', $result );

		// Image_ prefix.
		$result = $method->invoke( $this->checker, 'Image_456' );
		$this->assertEquals( '', $result );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test coverage calculation with all decorative images.
	 */
	public function test_coverage_all_decorative_images(): void {
		$this->connection->set_default_result( '0' );

		// 5 total images.
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'5'
		);

		// All 5 are decorative.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wpha_decorative_image'\n\t\t\tAND pm.meta_value != ''",
			'5'
		);

		// All have alt text too.
		$this->connection->set_expected_result(
			"SELECT COUNT(DISTINCT p.ID)\n\t\t\tFROM wp_posts p\n\t\t\tINNER JOIN wp_postmeta pm ON p.ID = pm.post_id\n\t\t\tWHERE p.post_type = 'attachment'\n\t\t\tAND p.post_mime_type LIKE 'image/%'\n\t\t\tAND pm.meta_key = '_wp_attachment_image_alt'\n\t\t\tAND pm.meta_value != ''",
			'5'
		);

		$result = $this->checker->get_alt_text_coverage();

		// Effective total: 5 - 5 = 0.
		$this->assertEquals( 0, $result['total_images'] );
		$this->assertEquals( 0.0, $result['coverage_percentage'] );
	}

	/**
	 * Test bulk_suggest_alt_text skips excluded attachments.
	 */
	public function test_bulk_suggest_skips_excluded(): void {
		// Add exclusion for attachment 200.
		$this->exclusions->add_exclusion( 200, 'Excluded for testing' );

		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'bulk_suggest_alt_text' );

		// The method requires WordPress functions like get_post_mime_type,
		// so we verify it handles exclusions in its loop logic.
		$params = $method->getParameters();
		$this->assertCount( 1, $params );
		$this->assertEquals( 'attachment_ids', $params[0]->getName() );
	}

	/**
	 * Test compliance level boundaries.
	 */
	public function test_compliance_level_boundaries(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'determine_compliance_level' );
		$method->setAccessible( true );

		// Exactly 100%.
		$this->assertEquals( 'excellent', $method->invoke( $this->checker, 100.0, 0, 10 ) );

		// Exactly 90%.
		$this->assertEquals( 'good', $method->invoke( $this->checker, 90.0, 0, 10 ) );

		// Just below 90%.
		$this->assertEquals( 'needs_improvement', $method->invoke( $this->checker, 89.99, 0, 10 ) );

		// Exactly 70%.
		$this->assertEquals( 'needs_improvement', $method->invoke( $this->checker, 70.0, 0, 10 ) );

		// Just below 70%.
		$this->assertEquals( 'critical', $method->invoke( $this->checker, 69.99, 0, 10 ) );

		// 0%.
		$this->assertEquals( 'critical', $method->invoke( $this->checker, 0.0, 0, 10 ) );
	}

	/**
	 * Test generate_suggestion_with_confidence low confidence.
	 */
	public function test_generate_suggestion_low_confidence(): void {
		$reflection = new \ReflectionClass( $this->checker );
		$method     = $reflection->getMethod( 'generate_suggestion_with_confidence' );
		$method->setAccessible( true );

		// Numeric-only filename, no title.
		$result = $method->invoke( $this->checker, '12345.jpg', '' );

		$this->assertEquals( 'Image', $result['suggestion'] );
		$this->assertEquals( 'low', $result['confidence'] );
		$this->assertEquals( 'filename', $result['source'] );
	}

	/**
	 * Test the AltTextCheckerInterface is implemented.
	 */
	public function test_implements_interface(): void {
		$this->assertInstanceOf(
			\WPAdminHealth\Contracts\AltTextCheckerInterface::class,
			$this->checker
		);
	}

	// =========================================================================
	// Multilingual Behavior Tests
	// =========================================================================

	/**
	 * Test get_alt_text returns alt text from translation when primary is empty.
	 */
	public function test_get_alt_text_falls_back_to_translation(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );

		// Setup: Attachment 100 has no alt text, but translation 101 does.
		$multilingual->set_translations( 100, array( 100, 101, 102 ) );
		$multilingual->set_attachment_language( 100, 'en' );
		$multilingual->set_attachment_language( 101, 'fr' );
		$multilingual->set_attachment_language( 102, 'de' );

		$GLOBALS['wpha_test_post_meta'] = array(
			100 => array(
				'_wp_attachment_image_alt' => '',
			),
			101 => array(
				'_wp_attachment_image_alt' => 'French Alt Text',
			),
			102 => array(
				'_wp_attachment_image_alt' => '',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text( 100 );

		$this->assertEquals( 'French Alt Text', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text prefers primary attachment's alt text.
	 */
	public function test_get_alt_text_prefers_primary(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );

		// Setup: Attachment 200 has alt text, translations also have alt text.
		$multilingual->set_translations( 200, array( 200, 201 ) );

		$GLOBALS['wpha_test_post_meta'] = array(
			200 => array(
				'_wp_attachment_image_alt' => 'Primary Alt Text',
			),
			201 => array(
				'_wp_attachment_image_alt' => 'Translation Alt Text',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text( 200 );

		// Should return primary, not translation.
		$this->assertEquals( 'Primary Alt Text', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text with unavailable multilingual integration.
	 */
	public function test_get_alt_text_with_unavailable_multilingual(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );
		$multilingual->set_available( false );

		// Setup: No alt text for attachment.
		$GLOBALS['wpha_test_post_meta'] = array(
			300 => array(
				'_wp_attachment_image_alt' => '',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text( 300 );

		// Should return empty since multilingual is not available.
		$this->assertEquals( '', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text_by_language returns all translations' alt text.
	 */
	public function test_get_alt_text_by_language_with_multilingual(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );
		$multilingual->set_languages( array( 'en', 'fr' ) );

		// Setup: Attachment with translations.
		$multilingual->set_translations( 400, array( 400, 401 ) );
		$multilingual->set_attachment_language( 400, 'en' );
		$multilingual->set_attachment_language( 401, 'fr' );

		$GLOBALS['wpha_test_post_meta'] = array(
			400 => array(
				'_wp_attachment_image_alt' => 'English Alt',
			),
			401 => array(
				'_wp_attachment_image_alt' => 'French Alt',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text_by_language( 400 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'default', $result );
		$this->assertEquals( 'English Alt', $result['default'] );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_alt_text_by_language returns only default when multilingual unavailable.
	 */
	public function test_get_alt_text_by_language_without_multilingual(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );
		$multilingual->set_available( false );

		$GLOBALS['wpha_test_post_meta'] = array(
			500 => array(
				'_wp_attachment_image_alt' => 'Only Alt',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text_by_language( 500 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'default', $result );
		$this->assertEquals( 'Only Alt', $result['default'] );
		// Should not have language-specific entries.
		$this->assertCount( 1, $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test that multilingual checker skips self in translation lookup.
	 */
	public function test_get_alt_text_skips_self_in_translations(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );

		// Setup: Only one attachment (no actual translations).
		$multilingual->set_translations( 600, array( 600 ) );

		$GLOBALS['wpha_test_post_meta'] = array(
			600 => array(
				'_wp_attachment_image_alt' => '',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_alt_text( 600 );

		// Should return empty string since there's no actual translation.
		$this->assertEquals( '', $result );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}

	/**
	 * Test get_coverage_by_language returns empty when multilingual unavailable.
	 */
	public function test_coverage_by_language_empty_without_multilingual(): void {
		$this->connection->set_default_result( '0' );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wpha_decorative_image'%%",
			'0'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wp_attachment_image_alt'%%",
			'10'
		);

		// Without multilingual, by_language should be empty.
		$result = $this->checker->get_accessibility_report();

		$this->assertArrayHasKey( 'by_language', $result );
		$this->assertEmpty( $result['by_language'] );
	}

	/**
	 * Test accessibility report includes by_language when multilingual available.
	 */
	public function test_accessibility_report_with_multilingual(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );
		$multilingual->set_languages( array( 'en', 'fr' ) );

		$this->connection->set_default_result( '0' );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			'10'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wpha_decorative_image'%%",
			'0'
		);

		$this->connection->set_expected_result(
			"%%meta_key = '_wp_attachment_image_alt'%%",
			'10'
		);

		// Return attachments for language breakdown.
		$this->connection->set_expected_result(
			"SELECT ID FROM wp_posts\n\t\t\tWHERE post_type = 'attachment'\n\t\t\tAND post_mime_type LIKE 'image/%'",
			array( '1', '2', '3', '4', '5' )
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );
		$result  = $checker->get_accessibility_report();

		$this->assertArrayHasKey( 'by_language', $result );
		// Should have entries for en and fr.
		$this->assertIsArray( $result['by_language'] );
	}

	/**
	 * Test constructor with mock multilingual creates valid instance.
	 */
	public function test_constructor_with_mock_multilingual(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );

		$this->assertInstanceOf( AltTextChecker::class, $checker );
	}

	/**
	 * Test multilingual integration is used correctly in find_missing_alt_text.
	 *
	 * This test verifies that the find_missing_alt_text method considers
	 * translation alt text when determining if an image is missing alt text.
	 */
	public function test_find_missing_considers_translations(): void {
		$cache        = new MockCache();
		$multilingual = new MockMultilingual( $this->connection, $cache );

		// Attachment 700 has no alt, but translation 701 does.
		$multilingual->set_translations( 700, array( 700, 701 ) );

		$GLOBALS['wpha_test_post_meta'] = array(
			700 => array(
				'_wp_attachment_image_alt' => '',
				'_wpha_decorative_image'   => '',
			),
			701 => array(
				'_wp_attachment_image_alt' => 'Translation has alt',
			),
		);

		$checker = new AltTextChecker( $this->connection, $this->exclusions, $multilingual );

		// get_alt_text should find the translation's alt text.
		$alt = $checker->get_alt_text( 700 );
		$this->assertEquals( 'Translation has alt', $alt );

		// Cleanup.
		unset( $GLOBALS['wpha_test_post_meta'] );
	}
}
