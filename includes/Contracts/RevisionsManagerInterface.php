<?php
/**
 * Revisions Manager Interface
 *
 * Defines the contract for managing WordPress post revisions.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface RevisionsManagerInterface
 *
 * Contract for post revision management operations.
 *
 * @since 1.2.0
 */
interface RevisionsManagerInterface {

	/**
	 * Get revisions for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of revision data.
	 */
	public function get_revisions_by_post( int $post_id ): array;

	/**
	 * Get total count of all post revisions.
	 *
	 * @return int Total revision count.
	 */
	public function get_all_revisions_count(): int;

	/**
	 * Get estimated size of all revisions.
	 *
	 * @return int Size in bytes.
	 */
	public function get_revisions_size_estimate(): int;

	/**
	 * Delete revisions for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $keep    Number of revisions to keep (default: 0).
	 * @return array Array with 'deleted' count and 'bytes_freed'.
	 */
	public function delete_revisions_for_post( int $post_id, int $keep = 0 ): array;

	/**
	 * Delete all revisions across all posts.
	 *
	 * @param int $keep Number of revisions to keep per post (default: 0).
	 * @return array Array with 'deleted' count and 'bytes_freed'.
	 */
	public function delete_all_revisions( int $keep = 0 ): array;

	/**
	 * Get posts with the most revisions.
	 *
	 * @param int $limit Number of posts to return (default: 10).
	 * @return array Array of posts with revision counts.
	 */
	public function get_posts_with_most_revisions( int $limit = 10 ): array;
}
