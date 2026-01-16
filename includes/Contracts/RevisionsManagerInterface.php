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
 * Handles querying, counting, and deleting WordPress post revisions.
 *
 * @since 1.2.0
 */
interface RevisionsManagerInterface {

	/**
	 * Get revisions for a specific post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<array{id: int, post_title: string, post_date: string, post_author: int}> Array of revision data.
	 */
	public function get_revisions_by_post( int $post_id ): array;

	/**
	 * Get total count of all post revisions.
	 *
	 * @since 1.2.0
	 *
	 * @return int Total revision count.
	 */
	public function get_all_revisions_count(): int;

	/**
	 * Get estimated size of all revisions.
	 *
	 * @since 1.2.0
	 *
	 * @return int Size in bytes.
	 */
	public function get_revisions_size_estimate(): int;

	/**
	 * Delete revisions for a specific post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $keep    Number of revisions to keep (default: 0).
	 * @return array{deleted: int, bytes_freed: int} Deletion result.
	 */
	public function delete_revisions_for_post( int $post_id, int $keep = 0 ): array;

	/**
	 * Delete all revisions across all posts.
	 *
	 * @since 1.2.0
	 *
	 * @param int $keep Number of revisions to keep per post (default: 0).
	 * @return array{deleted: int, bytes_freed: int} Deletion result.
	 */
	public function delete_all_revisions( int $keep = 0 ): array;

	/**
	 * Get posts with the most revisions.
	 *
	 * @since 1.2.0
	 *
	 * @param int $limit Number of posts to return (default: 10).
	 * @return array<array{id: int, title: string, revision_count: int, type: string}> Posts with revision counts.
	 */
	public function get_posts_with_most_revisions( int $limit = 10 ): array;
}
