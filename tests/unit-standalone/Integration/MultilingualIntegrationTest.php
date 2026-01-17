<?php
/**
 * Multilingual Integration Tests (Standalone)
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Integration
 */

namespace {
	// Minimal WPML function stubs for standalone testing.
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		define( 'ICL_SITEPRESS_VERSION', '4.6.0' );
	}

	if ( ! function_exists( 'icl_get_languages' ) ) {
		function icl_get_languages( $args = '' ) {
			return $GLOBALS['wpha_test_wpml_languages'] ?? array(
				'en' => array( 'code' => 'en' ),
				'de' => array( 'code' => 'de' ),
			);
		}
	}

	if ( ! function_exists( 'icl_get_current_language' ) ) {
		function icl_get_current_language() {
			return $GLOBALS['wpha_test_wpml_current_language'] ?? 'en';
		}
	}
}

namespace WPAdminHealth\Tests\UnitStandalone\Integration {

	use WPAdminHealth\Cache\MemoryCache;
	use WPAdminHealth\Integrations\Multilingual;
	use WPAdminHealth\Tests\Mocks\MockConnection;
	use WPAdminHealth\Tests\StandaloneTestCase;

	class MultilingualIntegrationTest extends StandaloneTestCase {

		protected function cleanup_test_environment(): void {
			unset( $GLOBALS['wpha_test_wpml_languages'] );
			unset( $GLOBALS['wpha_test_wpml_current_language'] );
			unset( $GLOBALS['wpha_test_posts'] );
			unset( $GLOBALS['wpha_test_post_types'] );
			unset( $GLOBALS['wpha_test_attachment_urls'] );
		}

		public function test_get_id_returns_multilingual(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$this->assertEquals( 'multilingual', $integration->get_id() );
		}

		public function test_get_name_returns_wpml_when_active(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			// WPML is active due to ICL_SITEPRESS_VERSION constant.
			$this->assertEquals( 'WPML', $integration->get_name() );
		}

		public function test_is_available_returns_true_with_wpml(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$this->assertTrue( $integration->is_available() );
		}

		public function test_get_active_plugin_returns_wpml(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$this->assertEquals( 'wpml', $integration->get_active_plugin() );
		}

		public function test_get_languages_returns_wpml_languages(): void {
			$GLOBALS['wpha_test_wpml_languages'] = array(
				'en' => array( 'code' => 'en' ),
				'de' => array( 'code' => 'de' ),
				'fr' => array( 'code' => 'fr' ),
			);

			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$languages = $integration->get_languages();

			$this->assertEquals( array( 'en', 'de', 'fr' ), $languages );
		}

		public function test_get_current_language_returns_wpml_current(): void {
			$GLOBALS['wpha_test_wpml_current_language'] = 'de';

			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$this->assertEquals( 'de', $integration->get_current_language() );
		}

		public function test_get_translations_returns_post_id_when_no_translations_table(): void {
			$connection = new MockConnection();
			// Simulate missing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", false );

			$integration = new Multilingual( $connection, new MemoryCache() );

			// Set up post type for the post.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'post' );

			$translations = $integration->get_translations( 123 );

			$this->assertEquals( array( 123 ), $translations );
		}

		public function test_get_translations_returns_all_translation_ids(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post type for the post.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'post' );

			// Return translation IDs as default result since pattern matching
			// doesn't work well with multi-line queries.
			$connection->set_default_result( array( '123', '456', '789' ) );

			$integration = new Multilingual( $connection, new MemoryCache() );

			$translations = $integration->get_translations( 123 );

			$this->assertContains( 123, $translations );
			$this->assertContains( 456, $translations );
			$this->assertContains( 789, $translations );
		}

		public function test_get_translations_caches_results(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post type for the post.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'post' );

			// Return translation IDs.
			$connection->set_expected_result(
				"%%FROM wp_icl_translations%%element_id = 123%%",
				array( '123', '456' )
			);

			$integration = new Multilingual( $connection, new MemoryCache() );

			// First call.
			$translations1 = $integration->get_translations( 123 );
			$query_count_1 = count( $connection->get_queries() );

			// Second call should use cache.
			$translations2 = $integration->get_translations( 123 );
			$query_count_2 = count( $connection->get_queries() );

			$this->assertEquals( $translations1, $translations2 );
			// Query count should not increase significantly (table check may still happen).
			$this->assertLessThanOrEqual( $query_count_1 + 1, $query_count_2 );
		}

		public function test_check_translated_media_usage_returns_true_early_when_already_used(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			// If already marked as used, should return true immediately.
			$result = $integration->check_translated_media_usage( true, 123 );

			$this->assertTrue( $result );
		}

		public function test_check_translated_media_usage_returns_false_for_single_attachment(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post type for the attachment.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'attachment' );

			// Return only the original attachment (no translations).
			$connection->set_expected_result(
				"%%FROM wp_icl_translations%%element_id = 123%%",
				array( '123' )
			);

			$integration = new Multilingual( $connection, new MemoryCache() );

			$result = $integration->check_translated_media_usage( false, 123 );

			// Single attachment with no translations should not change usage status.
			$this->assertFalse( $result );
		}

		public function test_get_used_attachments_returns_empty_when_no_wpml_table(): void {
			$connection = new MockConnection();

			// Simulate missing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", false );

			$integration = new Multilingual( $connection, new MemoryCache() );

			$attachments = $integration->get_used_attachments();

			$this->assertEquals( array(), $attachments );
		}

		public function test_get_used_attachments_returns_attachment_ids_in_translation_groups(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Return attachment IDs as default result since pattern matching
			// doesn't work well with multi-line queries.
			$connection->set_default_result( array( '123', '456', '789' ) );

			$integration = new Multilingual( $connection, new MemoryCache() );

			$attachments = $integration->get_used_attachments();

			$this->assertEquals( array( 123, 456, 789 ), $attachments );
		}

		public function test_is_attachment_used_delegates_to_check_translated_media_usage(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post type for the attachment.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'attachment' );

			// Return only the original attachment.
			$connection->set_expected_result(
				"%%FROM wp_icl_translations%%element_id = 123%%",
				array( '123' )
			);

			$integration = new Multilingual( $connection, new MemoryCache() );

			// Single attachment should not be marked as used.
			$result = $integration->is_attachment_used( 123 );

			$this->assertFalse( $result );
		}

		public function test_get_statistics_returns_expected_structure(): void {
			$GLOBALS['wpha_test_wpml_languages']         = array(
				'en' => array( 'code' => 'en' ),
				'de' => array( 'code' => 'de' ),
			);
			$GLOBALS['wpha_test_wpml_current_language'] = 'en';

			$connection = new MockConnection();
			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Return post counts per language.
			$connection->set_expected_result( "%%language_code = 'en'%%", '10' );
			$connection->set_expected_result( "%%language_code = 'de'%%", '5' );

			$integration = new Multilingual( $connection, new MemoryCache() );

			$stats = $integration->get_statistics();

			$this->assertArrayHasKey( 'active_plugin', $stats );
			$this->assertArrayHasKey( 'languages', $stats );
			$this->assertArrayHasKey( 'posts_per_language', $stats );
			$this->assertArrayHasKey( 'current_language', $stats );

			$this->assertEquals( 'wpml', $stats['active_plugin'] );
			$this->assertEquals( array( 'en', 'de' ), $stats['languages'] );
			$this->assertEquals( 'en', $stats['current_language'] );
		}

		public function test_filter_attachments_by_language_returns_all_when_no_language(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			// Override the current language to empty.
			$GLOBALS['wpha_test_wpml_current_language'] = '';

			$attachments = array( 1, 2, 3 );
			$result      = $integration->filter_attachments_by_language( $attachments, '' );

			$this->assertEquals( $attachments, $result );
		}

		public function test_get_capabilities_includes_media_detection(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$capabilities = $integration->get_capabilities();

			$this->assertContains( 'media_detection', $capabilities );
		}

		public function test_get_current_version_returns_wpml_version(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$version = $integration->get_current_version();

			$this->assertEquals( ICL_SITEPRESS_VERSION, $version );
		}

		public function test_get_min_version_returns_wpml_min_version(): void {
			$connection  = new MockConnection();
			$integration = new Multilingual( $connection, new MemoryCache() );

			$min_version = $integration->get_min_version();

			$this->assertEquals( '4.0.0', $min_version );
		}

		public function test_get_attachment_usage_returns_empty_for_single_translation(): void {
			$connection = new MockConnection();

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post type for the attachment.
			$GLOBALS['wpha_test_post_types'] = array( 123 => 'attachment' );

			// Return only the original attachment (no translations).
			$connection->set_expected_result(
				"%%FROM wp_icl_translations%%element_id = 123%%",
				array( '123' )
			);

			$integration = new Multilingual( $connection, new MemoryCache() );

			$usages = $integration->get_attachment_usage( 123 );

			$this->assertEquals( array(), $usages );
		}

		public function test_get_attachment_usage_returns_translation_info(): void {
			// This test uses a custom mock that handles multi-line query matching.
			$connection = new class extends MockConnection {
				private array $query_results = array();

				public function set_query_result( string $key, $value ): void {
					$this->query_results[ $key ] = $value;
				}

				public function get_var( string $query, int $x = 0, int $y = 0 ) {
					// Handle language queries - return language code based on element_id.
					if ( strpos( $query, 'language_code' ) !== false ) {
						if ( strpos( $query, 'element_id = 123' ) !== false ) {
							return 'en';
						}
						if ( strpos( $query, 'element_id = 456' ) !== false ) {
							return 'de';
						}
						return '';
					}
					return parent::get_var( $query, $x, $y );
				}

				public function get_col( string $query, int $x = 0 ): array {
					// Handle translation queries - return translation IDs.
					if ( strpos( $query, 'trid' ) !== false && strpos( $query, 'icl_translations' ) !== false ) {
						return array( '123', '456' );
					}
					return parent::get_col( $query, $x );
				}
			};

			// Simulate existing WPML translations table.
			$connection->set_expected_result( "SHOW TABLES LIKE 'wp_icl_translations'", true );

			// Set up post types for the attachments.
			$GLOBALS['wpha_test_post_types'] = array(
				123 => 'attachment',
				456 => 'attachment',
			);

			// Set up mock posts.
			$post1             = new \stdClass();
			$post1->ID         = 123;
			$post1->post_title = 'Image EN';
			$post1->post_type  = 'attachment';

			$post2             = new \stdClass();
			$post2->ID         = 456;
			$post2->post_title = 'Image DE';
			$post2->post_type  = 'attachment';

			$GLOBALS['wpha_test_posts'] = array(
				123 => $post1,
				456 => $post2,
			);

			$integration = new Multilingual( $connection, new MemoryCache() );

			$usages = $integration->get_attachment_usage( 123 );

			$this->assertCount( 2, $usages );

			// Check first usage.
			$this->assertEquals( 123, $usages[0]['post_id'] );
			$this->assertEquals( 'Image EN', $usages[0]['post_title'] );

			// Check second usage.
			$this->assertEquals( 456, $usages[1]['post_id'] );
			$this->assertEquals( 'Image DE', $usages[1]['post_title'] );
		}
	}
}
