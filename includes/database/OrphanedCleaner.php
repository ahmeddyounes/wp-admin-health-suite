<?php
/**
 * Orphaned Data Cleaner Class
 *
 * Identifies and removes orphaned metadata and relationships from WordPress database.
 * Handles postmeta, commentmeta, termmeta, and term relationships with no valid parent records.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\OrphanedCleanerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Orphaned Cleaner class for managing orphaned database records.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements OrphanedCleanerInterface.
 */
class OrphanedCleaner implements OrphanedCleanerInterface {

	/**
	 * Batch size for processing orphaned data.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 1000;

	/**
	 * Count orphaned postmeta records.
	 *
	 * Returns the count of orphaned postmeta without loading all IDs into memory.
	 * Use this for displaying counts in UI before deciding to delete.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned postmeta records.
	 */
	public function count_orphaned_postmeta(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(pm.meta_id)
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);

		return absint( $count );
	}

	/**
	 * Count orphaned commentmeta records.
	 *
	 * Returns the count of orphaned commentmeta without loading all IDs into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned commentmeta records.
	 */
	public function count_orphaned_commentmeta(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(cm.meta_id)
			FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL"
		);

		return absint( $count );
	}

	/**
	 * Count orphaned termmeta records.
	 *
	 * Returns the count of orphaned termmeta without loading all IDs into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned termmeta records.
	 */
	public function count_orphaned_termmeta(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			"SELECT COUNT(tm.meta_id)
			FROM {$wpdb->termmeta} tm
			LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL"
		);

		return absint( $count );
	}

	/**
	 * Count orphaned term relationships.
	 *
	 * Returns the count of orphaned term relationships without loading all data.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned term relationships.
	 */
	public function count_orphaned_relationships(): int {
		global $wpdb;

		// Count relationships where the post doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphaned_posts = $wpdb->get_var(
			"SELECT COUNT(tr.object_id)
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			WHERE p.ID IS NULL"
		);

		// Count relationships where the term_taxonomy doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphaned_terms = $wpdb->get_var(
			"SELECT COUNT(tr.object_id)
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_taxonomy_id IS NULL"
		);

		return absint( $orphaned_posts ) + absint( $orphaned_terms );
	}

	/**
	 * Find orphaned postmeta records.
	 *
	 * Identifies postmeta rows where the post_id does not exist in the posts table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_postmeta(): array {
		global $wpdb;

		$query = "SELECT pm.meta_id
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			ORDER BY pm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned commentmeta records.
	 *
	 * Identifies commentmeta rows where the comment_id does not exist in the comments table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_commentmeta(): array {
		global $wpdb;

		$query = "SELECT cm.meta_id
			FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL
			ORDER BY cm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned termmeta records.
	 *
	 * Identifies termmeta rows where the term_id does not exist in the terms table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_termmeta(): array {
		global $wpdb;

		$query = "SELECT tm.meta_id
			FROM {$wpdb->termmeta} tm
			LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL
			ORDER BY tm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned term relationships.
	 *
	 * Identifies term_relationships rows where the object_id does not exist in the posts table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of orphaned relationship data (object_id and term_taxonomy_id pairs).
	 */
	public function find_orphaned_relationships(): array {
		global $wpdb;

		$query = "SELECT tr.object_id, tr.term_taxonomy_id
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			WHERE p.ID IS NULL
			ORDER BY tr.object_id ASC, tr.term_taxonomy_id ASC";

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete orphaned postmeta records.
	 *
	 * Uses atomic DELETE with JOIN to prevent race conditions where
	 * a post could be created between finding orphans and deleting them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Changed to atomic DELETE query to prevent race conditions.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_postmeta(): int {
		global $wpdb;

		$deleted_count = 0;
		$batch_limit   = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		// Process in batches to avoid long-running queries.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE pm FROM {$wpdb->postmeta} pm
					LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.ID IS NULL
					LIMIT %d",
					$batch_limit
				)
			);

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;

			// Continue until no more orphans or we hit a reasonable limit.
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}

	/**
	 * Delete orphaned commentmeta records.
	 *
	 * Uses atomic DELETE with JOIN to prevent race conditions where
	 * a comment could be created between finding orphans and deleting them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Changed to atomic DELETE query to prevent race conditions.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_commentmeta(): int {
		global $wpdb;

		$deleted_count = 0;
		$batch_limit   = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE cm FROM {$wpdb->commentmeta} cm
					LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
					WHERE c.comment_ID IS NULL
					LIMIT %d",
					$batch_limit
				)
			);

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}

	/**
	 * Delete orphaned termmeta records.
	 *
	 * Uses atomic DELETE with JOIN to prevent race conditions where
	 * a term could be created between finding orphans and deleting them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Changed to atomic DELETE query to prevent race conditions.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_termmeta(): int {
		global $wpdb;

		$deleted_count = 0;
		$batch_limit   = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE tm FROM {$wpdb->termmeta} tm
					LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
					WHERE t.term_id IS NULL
					LIMIT %d",
					$batch_limit
				)
			);

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}

	/**
	 * Delete orphaned term relationships.
	 *
	 * Uses atomic DELETE with JOINs to prevent race conditions where
	 * a post or term_taxonomy could be created between finding orphans and deleting them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Changed to atomic DELETE query to prevent race conditions.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_relationships(): int {
		global $wpdb;

		$deleted_count = 0;
		$batch_limit   = self::BATCH_SIZE;

		// Delete relationships where the post doesn't exist.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE tr FROM {$wpdb->term_relationships} tr
					LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
					WHERE p.ID IS NULL
					LIMIT %d",
					$batch_limit
				)
			);

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		// Delete relationships where the term_taxonomy doesn't exist.
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE tr FROM {$wpdb->term_relationships} tr
					LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_taxonomy_id IS NULL
					LIMIT %d",
					$batch_limit
				)
			);

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}
}
