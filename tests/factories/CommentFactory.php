<?php
/**
 * Custom Comment Factory for tests
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests\Factories;

use WP_UnitTest_Factory_For_Comment;

/**
 * Extended Comment Factory with custom methods for testing
 */
class CommentFactory extends WP_UnitTest_Factory_For_Comment {

	/**
	 * Create a spam comment
	 *
	 * @param int   $post_id Post ID to attach comment to
	 * @param array $args Comment arguments
	 * @return int Comment ID
	 */
	public function create_spam( $post_id, $args = array() ) {
		$args['comment_post_ID'] = $post_id;
		$args['comment_approved'] = 'spam';
		return $this->create( $args );
	}

	/**
	 * Create a trashed comment
	 *
	 * @param int   $post_id Post ID to attach comment to
	 * @param array $args Comment arguments
	 * @return int Comment ID
	 */
	public function create_trashed( $post_id, $args = array() ) {
		$args['comment_post_ID'] = $post_id;
		$args['comment_approved'] = 'trash';
		return $this->create( $args );
	}

	/**
	 * Create multiple comments for a post
	 *
	 * @param int   $post_id Post ID to attach comments to
	 * @param int   $count Number of comments to create
	 * @param array $args Comment arguments
	 * @return array Array of comment IDs
	 */
	public function create_many_for_post( $post_id, $count, $args = array() ) {
		$comment_ids = array();
		$args['comment_post_ID'] = $post_id;

		for ( $i = 0; $i < $count; $i++ ) {
			$comment_args = array_merge(
				$args,
				array(
					'comment_content' => isset( $args['comment_content'] ) ? $args['comment_content'] . ' ' . ( $i + 1 ) : 'Comment ' . ( $i + 1 ),
				)
			);
			$comment_ids[] = $this->create( $comment_args );
		}

		return $comment_ids;
	}

	/**
	 * Create a comment with a specific author
	 *
	 * @param int   $post_id Post ID to attach comment to
	 * @param int   $user_id User ID of comment author
	 * @param array $args Comment arguments
	 * @return int Comment ID
	 */
	public function create_with_author( $post_id, $user_id, $args = array() ) {
		$args['comment_post_ID'] = $post_id;
		$args['user_id'] = $user_id;
		return $this->create( $args );
	}

	/**
	 * Create a comment thread (parent and replies)
	 *
	 * @param int   $post_id Post ID to attach comments to
	 * @param int   $reply_count Number of replies to create
	 * @param array $args Comment arguments
	 * @return array Array with 'parent' and 'replies' keys
	 */
	public function create_thread( $post_id, $reply_count = 3, $args = array() ) {
		$args['comment_post_ID'] = $post_id;
		$parent_id = $this->create( $args );

		$replies = array();
		for ( $i = 0; $i < $reply_count; $i++ ) {
			$reply_args = array_merge(
				$args,
				array(
					'comment_parent' => $parent_id,
					'comment_content' => 'Reply ' . ( $i + 1 ),
				)
			);
			$replies[] = $this->create( $reply_args );
		}

		return array(
			'parent'  => $parent_id,
			'replies' => $replies,
		);
	}
}
