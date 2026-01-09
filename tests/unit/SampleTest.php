<?php
/**
 * Sample test to verify PHPUnit setup
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests\Unit;

use WPAdminHealth\Tests\TestCase;

/**
 * Test case for verifying test environment setup
 */
class SampleTest extends TestCase {

	/**
	 * Test that WordPress is loaded
	 */
	public function test_wordpress_loaded() {
		$this->assertTrue( function_exists( 'do_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
	}

	/**
	 * Test that plugin constants are defined
	 */
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'WP_ADMIN_HEALTH_VERSION' ) );
		$this->assertTrue( defined( 'WP_ADMIN_HEALTH_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'WP_ADMIN_HEALTH_PLUGIN_FILE' ) );
	}

	/**
	 * Test creating a post using factory
	 */
	public function test_create_post() {
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'Factory Test Post',
			)
		);

		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( 'Factory Test Post', $post->post_title );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * Test creating an attachment using factory
	 */
	public function test_create_attachment() {
		$attachment_id = $this->create_test_attachment(
			array(
				'post_title' => 'Factory Test Attachment',
			)
		);

		$this->assertGreaterThan( 0, $attachment_id );

		$attachment = get_post( $attachment_id );
		$this->assertEquals( 'attachment', $attachment->post_type );
		$this->assertEquals( 'Factory Test Attachment', $attachment->post_title );
	}

	/**
	 * Test creating a comment using factory
	 */
	public function test_create_comment() {
		$post_id = $this->create_test_post();
		$comment_id = $this->create_test_comment(
			array(
				'comment_content' => 'Factory Test Comment',
			),
			$post_id
		);

		$this->assertGreaterThan( 0, $comment_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 'Factory Test Comment', $comment->comment_content );
		$this->assertEquals( $post_id, $comment->comment_post_ID );
	}

	/**
	 * Test custom assertion for options
	 */
	public function test_option_assertion() {
		$option_name = 'test_option_' . time();
		$option_value = 'test_value';

		update_option( $option_name, $option_value );

		$this->assertOptionEquals( $option_name, $option_value );
	}

	/**
	 * Test custom assertion for post meta
	 */
	public function test_post_meta_assertion() {
		$post_id = $this->create_test_post();
		$meta_key = 'test_meta_key';
		$meta_value = 'test_meta_value';

		update_post_meta( $post_id, $meta_key, $meta_value );

		$this->assertPostMetaEquals( $post_id, $meta_key, $meta_value );
	}
}
