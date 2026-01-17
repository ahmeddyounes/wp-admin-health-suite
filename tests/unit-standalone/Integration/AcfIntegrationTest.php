<?php
/**
 * ACF Integration Tests (Standalone)
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Integration
 */

namespace {
	// Minimal ACF function stubs for standalone testing.
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		function acf_get_field_groups() {
			return $GLOBALS['wpha_test_acf_field_groups'] ?? array();
		}
	}

	if ( ! function_exists( 'acf_get_fields' ) ) {
		function acf_get_fields( $field_group_key ) {
			if ( isset( $GLOBALS['wpha_test_acf_fields'] ) && is_array( $GLOBALS['wpha_test_acf_fields'] ) ) {
				return $GLOBALS['wpha_test_acf_fields'][ $field_group_key ] ?? array();
			}
			return array();
		}
	}

	if ( ! function_exists( 'acf_get_field' ) ) {
		function acf_get_field( $field_key ) {
			if ( isset( $GLOBALS['wpha_test_acf_field_map'] ) && is_array( $GLOBALS['wpha_test_acf_field_map'] ) ) {
				return $GLOBALS['wpha_test_acf_field_map'][ $field_key ] ?? false;
			}
			return false;
		}
	}

	if ( ! defined( 'ACF_VERSION' ) ) {
		define( 'ACF_VERSION', '6.0.0' );
	}
}

namespace WPAdminHealth\Tests\UnitStandalone\Integration {

	use WPAdminHealth\Cache\MemoryCache;
	use WPAdminHealth\Integrations\ACF;
	use WPAdminHealth\Tests\Mocks\MockConnection;
	use WPAdminHealth\Tests\StandaloneTestCase;

	class AcfIntegrationTest extends StandaloneTestCase {

		protected function cleanup_test_environment(): void {
			unset( $GLOBALS['wpha_test_acf_field_groups'] );
			unset( $GLOBALS['wpha_test_acf_fields'] );
			unset( $GLOBALS['wpha_test_acf_field_map'] );
			unset( $GLOBALS['wpha_test_post_meta'] );
		}

		private function set_acf_field_definitions(): void {
			$GLOBALS['wpha_test_acf_field_groups'] = array(
				array(
					'key'   => 'group_1',
				'title' => 'Test Group',
			),
		);

		$GLOBALS['wpha_test_acf_fields'] = array(
			'group_1' => array(
				array(
					'type'       => 'repeater',
					'key'        => 'field_repeater',
					'sub_fields' => array(
						array(
							'type' => 'gallery',
							'key'  => 'field_gallery',
						),
					),
				),
				array(
					'type'       => 'group',
					'key'        => 'field_group',
					'sub_fields' => array(
						array(
							'type' => 'file',
							'key'  => 'field_file',
						),
					),
				),
				array(
					'type'    => 'flexible_content',
					'key'     => 'field_flex',
					'layouts' => array(
						array(
							'sub_fields' => array(
								array(
									'type' => 'image',
									'key'  => 'field_flex_image',
								),
							),
						),
					),
				),
			),
		);

		$GLOBALS['wpha_test_acf_field_map'] = array(
			'field_gallery'    => array( 'label' => 'Gallery Label', 'type' => 'gallery' ),
			'field_file'       => array( 'label' => 'File Label', 'type' => 'file' ),
			'field_flex_image' => array( 'label' => 'Flex Image Label', 'type' => 'image' ),
		);
	}

	public function test_get_media_field_keys_includes_nested_fields(): void {
		$this->set_acf_field_definitions();

		$integration = new ACF( new MockConnection(), new MemoryCache() );
		$keys        = $integration->get_media_field_keys();
		sort( $keys );

		$this->assertEquals(
			array( 'field_file', 'field_flex_image', 'field_gallery' ),
			$keys
		);
	}

	public function test_check_acf_image_usage_detects_gallery_ids(): void {
		$this->set_acf_field_definitions();

		$connection = new MockConnection();

		$attachment_id = 123;
		$connection->set_default_result( '1' );

		$integration = new ACF( $connection, new MemoryCache() );

		$this->assertTrue( $integration->check_acf_image_usage( false, $attachment_id ) );

		$last_query = $connection->get_last_query();
		$this->assertIsArray( $last_query );
		$this->assertStringContainsString( 'SELECT 1', $last_query['query'] );
		$this->assertStringContainsString( 'INNER JOIN wp_postmeta fk', $last_query['query'] );
		$this->assertStringContainsString( 'i:123;', $last_query['query'] );
	}

	public function test_get_used_attachments_scans_acf_media_fields(): void {
		$this->set_acf_field_definitions();

		$connection = new MockConnection();

		$results = array(
			serialize( array( 123, 456 ) ), // gallery field.
			'789', // direct ID field.
			'[123,999]', // JSON array.
		);

		$connection->set_default_result( $results );

		$integration = new ACF( $connection, new MemoryCache() );

		$ids = $integration->get_used_attachments( 10 );
		sort( $ids );

		$this->assertEquals( array( 123, 456, 789, 999 ), $ids );

		$last_query = $connection->get_last_query();
		$this->assertIsArray( $last_query );
		$this->assertStringContainsString( 'INNER JOIN wp_postmeta fk', $last_query['query'] );
	}

	public function test_get_attachment_usage_includes_field_label_context(): void {
		$this->set_acf_field_definitions();

		$post_id  = 42;
		$meta_key = 'my_gallery';

		$GLOBALS['wpha_test_post_meta'] = array(
			$post_id => array(
				'_' . $meta_key => 'field_gallery',
			),
		);

		$connection = new MockConnection();

		$attachment_id = 123;

		$row             = new \stdClass();
		$row->post_id    = $post_id;
		$row->meta_key   = $meta_key;
		$row->meta_value = serialize( array( $attachment_id ) );
		$row->post_title = 'Test Post';

		$connection->set_default_result( array( $row ) );

		$integration = new ACF( $connection, new MemoryCache() );
		$usages      = $integration->get_attachment_usage( $attachment_id, 1 );

		$this->assertCount( 1, $usages );
		$this->assertEquals( $post_id, $usages[0]['post_id'] );
		$this->assertEquals( 'Test Post', $usages[0]['post_title'] );
		$this->assertEquals( 'ACF gallery field: Gallery Label', $usages[0]['context'] );

		$last_query = $connection->get_last_query();
		$this->assertIsArray( $last_query );
		$this->assertStringContainsString( 'INNER JOIN wp_postmeta fk', $last_query['query'] );
	}
	}
}
