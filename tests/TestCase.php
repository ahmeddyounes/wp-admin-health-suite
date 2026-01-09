<?php
/**
 * Base test case for WP Admin Health Suite
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames;

/**
 * Base test case class that all plugin tests should extend
 */
abstract class TestCase extends WP_UnitTestCase {
	use AssertionRenames;

	/**
	 * Set up test environment before each test
	 */
	protected function set_up() {
		parent::set_up();
		$this->setup_test_environment();
	}

	/**
	 * Clean up test environment after each test
	 */
	protected function tear_down() {
		$this->cleanup_test_environment();
		parent::tear_down();
	}

	/**
	 * Setup test-specific environment
	 *
	 * Override this method in child classes for custom setup
	 */
	protected function setup_test_environment() {
		// Override in child classes
	}

	/**
	 * Cleanup test-specific environment
	 *
	 * Override this method in child classes for custom cleanup
	 */
	protected function cleanup_test_environment() {
		// Override in child classes
	}

	/**
	 * Create a test post with default or custom attributes
	 *
	 * @param array $args Post arguments to override defaults
	 * @return int Post ID
	 */
	protected function create_test_post( $args = array() ) {
		$defaults = array(
			'post_title'   => 'Test Post',
			'post_content' => 'Test post content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		);

		$args = wp_parse_args( $args, $defaults );
		return $this->factory()->post->create( $args );
	}

	/**
	 * Create a test attachment with default or custom attributes
	 *
	 * @param array $args Attachment arguments to override defaults
	 * @param int   $parent_post_id Optional parent post ID
	 * @return int Attachment ID
	 */
	protected function create_test_attachment( $args = array(), $parent_post_id = 0 ) {
		$defaults = array(
			'post_title'     => 'Test Attachment',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		);

		if ( $parent_post_id > 0 ) {
			$defaults['post_parent'] = $parent_post_id;
		}

		$args = wp_parse_args( $args, $defaults );
		return $this->factory()->attachment->create( $args );
	}

	/**
	 * Create a test comment with default or custom attributes
	 *
	 * @param array $args Comment arguments to override defaults
	 * @param int   $post_id Optional post ID to attach comment to
	 * @return int Comment ID
	 */
	protected function create_test_comment( $args = array(), $post_id = 0 ) {
		if ( $post_id === 0 ) {
			$post_id = $this->create_test_post();
		}

		$defaults = array(
			'comment_post_ID'  => $post_id,
			'comment_content'  => 'Test comment content',
			'comment_approved' => 1,
		);

		$args = wp_parse_args( $args, $defaults );
		return $this->factory()->comment->create( $args );
	}

	/**
	 * Assert that a WordPress option exists and has expected value
	 *
	 * @param string $option Option name
	 * @param mixed  $expected_value Expected value
	 * @param string $message Optional message
	 */
	protected function assertOptionEquals( $option, $expected_value, $message = '' ) {
		$actual_value = get_option( $option );
		$this->assertEquals( $expected_value, $actual_value, $message );
	}

	/**
	 * Assert that a post meta exists and has expected value
	 *
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $expected_value Expected value
	 * @param string $message Optional message
	 */
	protected function assertPostMetaEquals( $post_id, $meta_key, $expected_value, $message = '' ) {
		$actual_value = get_post_meta( $post_id, $meta_key, true );
		$this->assertEquals( $expected_value, $actual_value, $message );
	}

	/**
	 * Assert that a hook has a callback registered
	 *
	 * @param string $hook Hook name
	 * @param string $callback Callback function/method name
	 * @param string $message Optional message
	 */
	protected function assertHookHasCallback( $hook, $callback, $message = '' ) {
		global $wp_filter;

		if ( empty( $message ) ) {
			$message = sprintf( 'Hook "%s" should have callback "%s" registered', $hook, $callback );
		}

		$this->assertTrue(
			isset( $wp_filter[ $hook ] ),
			sprintf( 'Hook "%s" is not registered', $hook )
		);

		$has_callback = false;
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $registered_callback ) {
				if ( is_string( $registered_callback['function'] ) && $registered_callback['function'] === $callback ) {
					$has_callback = true;
					break 2;
				}
			}
		}

		$this->assertTrue( $has_callback, $message );
	}
}
