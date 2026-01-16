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
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\Mocks\MockExclusions;
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
}
