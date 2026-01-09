<?php
/**
 * Trash Cleaner Interface
 *
 * Defines the contract for managing trashed content and spam.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface TrashCleanerInterface
 *
 * Contract for trash and spam cleanup operations.
 *
 * @since 1.2.0
 */
interface TrashCleanerInterface {

	/**
	 * Get all trashed posts.
	 *
	 * @param array $post_types Array of post types to query. Empty for all.
	 * @return array Array of trashed posts.
	 */
	public function get_trashed_posts( array $post_types = array() ): array;

	/**
	 * Count trashed posts.
	 *
	 * @return int Number of trashed posts.
	 */
	public function count_trashed_posts(): int;

	/**
	 * Count spam comments.
	 *
	 * @return int Number of spam comments.
	 */
	public function count_spam_comments(): int;

	/**
	 * Count trashed comments.
	 *
	 * @return int Number of trashed comments.
	 */
	public function count_trashed_comments(): int;

	/**
	 * Delete all trashed posts.
	 *
	 * @param array $post_types       Array of post types to delete. Empty for all.
	 * @param int   $older_than_days  Only delete posts trashed more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_posts( array $post_types = array(), int $older_than_days = 0 ): array;

	/**
	 * Delete all spam comments.
	 *
	 * @param int $older_than_days Only delete comments marked as spam more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_spam_comments( int $older_than_days = 0 ): array;

	/**
	 * Delete all trashed comments.
	 *
	 * @param int $older_than_days Only delete comments in trash more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_comments( int $older_than_days = 0 ): array;

	/**
	 * Empty all trash (posts and comments).
	 *
	 * @return array Array with counts of deleted items.
	 */
	public function empty_all_trash(): array;
}
