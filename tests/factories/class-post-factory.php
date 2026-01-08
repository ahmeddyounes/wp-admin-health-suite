<?php
/**
 * Custom Post Factory for tests
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests\Factories;

use WP_UnitTest_Factory_For_Post;

/**
 * Extended Post Factory with custom methods for testing
 */
class Post_Factory extends WP_UnitTest_Factory_For_Post {

	/**
	 * Create a post with revisions
	 *
	 * @param int   $revision_count Number of revisions to create
	 * @param array $args Post arguments
	 * @return int Post ID
	 */
	public function create_with_revisions( $revision_count = 3, $args = array() ) {
		$post_id = $this->create( $args );

		// Enable revisions temporarily
		add_filter( 'wp_revisions_to_keep', '__return_true' );

		for ( $i = 0; $i < $revision_count; $i++ ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => 'Revision ' . ( $i + 1 ) . ' content',
				)
			);
		}

		remove_filter( 'wp_revisions_to_keep', '__return_true' );

		return $post_id;
	}

	/**
	 * Create a post in trash
	 *
	 * @param array $args Post arguments
	 * @return int Post ID
	 */
	public function create_trashed( $args = array() ) {
		$args['post_status'] = 'trash';
		return $this->create( $args );
	}

	/**
	 * Create multiple posts in bulk
	 *
	 * @param int   $count Number of posts to create
	 * @param array $args Post arguments
	 * @return array Array of post IDs
	 */
	public function create_many_posts( $count, $args = array() ) {
		$post_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$post_args = array_merge(
				$args,
				array(
					'post_title' => isset( $args['post_title'] ) ? $args['post_title'] . ' ' . ( $i + 1 ) : 'Post ' . ( $i + 1 ),
				)
			);
			$post_ids[] = $this->create( $post_args );
		}

		return $post_ids;
	}
}
