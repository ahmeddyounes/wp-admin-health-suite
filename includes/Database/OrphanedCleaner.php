<?php
/**
 * Orphaned Data Cleaner Class
 *
 * Identifies and removes orphaned metadata and relationships from WordPress database.
 * Handles postmeta, commentmeta, termmeta, usermeta, and term relationships with no valid parent records.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Orphaned Cleaner class for managing orphaned database records.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements OrphanedCleanerInterface.
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 * @since 1.4.0 Added usermeta support and fixed find_orphaned_relationships to include missing term_taxonomy.
 */
class OrphanedCleaner implements OrphanedCleanerInterface {

	/**
	 * Batch size for processing orphaned data.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 1000;

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
	}

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
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $this->connection->get_var(
			"SELECT COUNT(pm.meta_id)
			FROM {$postmeta_table} pm
			LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
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
		$commentmeta_table = $this->connection->get_commentmeta_table();
		$comments_table    = $this->connection->get_comments_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $this->connection->get_var(
			"SELECT COUNT(cm.meta_id)
			FROM {$commentmeta_table} cm
			LEFT JOIN {$comments_table} c ON cm.comment_id = c.comment_ID
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
		$termmeta_table = $this->connection->get_termmeta_table();
		$terms_table    = $this->connection->get_terms_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $this->connection->get_var(
			"SELECT COUNT(tm.meta_id)
			FROM {$termmeta_table} tm
			LEFT JOIN {$terms_table} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL"
		);

		return absint( $count );
	}

	/**
	 * Count orphaned usermeta records.
	 *
	 * Returns the count of orphaned usermeta without loading all IDs into memory.
	 * Note: In multisite, usermeta is a global table shared across all sites.
	 *
	 * @since 1.4.0
	 *
	 * @return int Number of orphaned usermeta records.
	 */
	public function count_orphaned_usermeta(): int {
		$usermeta_table = $this->connection->get_usermeta_table();
		$users_table    = $this->connection->get_users_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $this->connection->get_var(
			"SELECT COUNT(um.umeta_id)
			FROM {$usermeta_table} um
			LEFT JOIN {$users_table} u ON um.user_id = u.ID
			WHERE u.ID IS NULL"
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
		$prefix                    = $this->connection->get_prefix();
		$term_relationships_table  = $prefix . 'term_relationships';
		$term_taxonomy_table       = $prefix . 'term_taxonomy';
		$posts_table               = $this->connection->get_posts_table();

		// Count relationships where the post doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphaned_posts = $this->connection->get_var(
			"SELECT COUNT(tr.object_id)
			FROM {$term_relationships_table} tr
			LEFT JOIN {$posts_table} p ON tr.object_id = p.ID
			WHERE p.ID IS NULL"
		);

		// Count relationships where the term_taxonomy doesn't exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphaned_terms = $this->connection->get_var(
			"SELECT COUNT(tr.object_id)
			FROM {$term_relationships_table} tr
			LEFT JOIN {$term_taxonomy_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
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
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		$query = "SELECT pm.meta_id
			FROM {$postmeta_table} pm
			LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			ORDER BY pm.meta_id ASC";

		return $this->connection->get_col( $query );
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
		$commentmeta_table = $this->connection->get_commentmeta_table();
		$comments_table    = $this->connection->get_comments_table();

		$query = "SELECT cm.meta_id
			FROM {$commentmeta_table} cm
			LEFT JOIN {$comments_table} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL
			ORDER BY cm.meta_id ASC";

		return $this->connection->get_col( $query );
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
		$termmeta_table = $this->connection->get_termmeta_table();
		$terms_table    = $this->connection->get_terms_table();

		$query = "SELECT tm.meta_id
			FROM {$termmeta_table} tm
			LEFT JOIN {$terms_table} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL
			ORDER BY tm.meta_id ASC";

		return $this->connection->get_col( $query );
	}

	/**
	 * Find orphaned usermeta records.
	 *
	 * Identifies usermeta rows where the user_id does not exist in the users table.
	 * Note: In multisite, usermeta is a global table shared across all sites.
	 *
	 * @since 1.4.0
	 *
	 * @return array Array of orphaned umeta_ids.
	 */
	public function find_orphaned_usermeta(): array {
		$usermeta_table = $this->connection->get_usermeta_table();
		$users_table    = $this->connection->get_users_table();

		$query = "SELECT um.umeta_id
			FROM {$usermeta_table} um
			LEFT JOIN {$users_table} u ON um.user_id = u.ID
			WHERE u.ID IS NULL
			ORDER BY um.umeta_id ASC";

		return $this->connection->get_col( $query );
	}

	/**
	 * Find orphaned term relationships.
	 *
	 * Identifies term_relationships rows where the object_id does not exist in the posts table
	 * OR where the term_taxonomy_id does not exist in the term_taxonomy table.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Also includes relationships with missing term_taxonomy.
	 *
	 * @return array Array of orphaned relationship data (object_id and term_taxonomy_id pairs).
	 */
	public function find_orphaned_relationships(): array {
		$prefix                   = $this->connection->get_prefix();
		$term_relationships_table = $prefix . 'term_relationships';
		$term_taxonomy_table      = $prefix . 'term_taxonomy';
		$posts_table              = $this->connection->get_posts_table();

		// Find relationships where the post doesn't exist.
		$orphaned_posts_query = "SELECT tr.object_id, tr.term_taxonomy_id
			FROM {$term_relationships_table} tr
			LEFT JOIN {$posts_table} p ON tr.object_id = p.ID
			WHERE p.ID IS NULL";

		// Find relationships where the term_taxonomy doesn't exist.
		$orphaned_taxonomy_query = "SELECT tr.object_id, tr.term_taxonomy_id
			FROM {$term_relationships_table} tr
			LEFT JOIN {$term_taxonomy_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_taxonomy_id IS NULL";

		// Combine with UNION to avoid duplicates and order results.
		$query = "({$orphaned_posts_query}) UNION ({$orphaned_taxonomy_query})
			ORDER BY object_id ASC, term_taxonomy_id ASC";

		return $this->connection->get_results( $query, 'ARRAY_A' );
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
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();
		$deleted_count  = 0;
		$batch_limit    = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		// Process in batches to avoid long-running queries.
		do {
			$query = $this->connection->prepare(
				"DELETE pm FROM {$postmeta_table} pm
				LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
				WHERE p.ID IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

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
		$commentmeta_table = $this->connection->get_commentmeta_table();
		$comments_table    = $this->connection->get_comments_table();
		$deleted_count     = 0;
		$batch_limit       = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		do {
			$query = $this->connection->prepare(
				"DELETE cm FROM {$commentmeta_table} cm
				LEFT JOIN {$comments_table} c ON cm.comment_id = c.comment_ID
				WHERE c.comment_ID IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

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
		$termmeta_table = $this->connection->get_termmeta_table();
		$terms_table    = $this->connection->get_terms_table();
		$deleted_count  = 0;
		$batch_limit    = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		do {
			$query = $this->connection->prepare(
				"DELETE tm FROM {$termmeta_table} tm
				LEFT JOIN {$terms_table} t ON tm.term_id = t.term_id
				WHERE t.term_id IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}

	/**
	 * Delete orphaned usermeta records.
	 *
	 * Uses atomic DELETE with JOIN to prevent race conditions where
	 * a user could be created between finding orphans and deleting them.
	 * Note: In multisite, usermeta is a global table shared across all sites.
	 * Use caution as this affects all sites in the network.
	 *
	 * @since 1.4.0
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_usermeta(): int {
		$usermeta_table = $this->connection->get_usermeta_table();
		$users_table    = $this->connection->get_users_table();
		$deleted_count  = 0;
		$batch_limit    = self::BATCH_SIZE;

		// Use atomic DELETE with JOIN - finds and deletes in one operation.
		do {
			$query = $this->connection->prepare(
				"DELETE um FROM {$usermeta_table} um
				LEFT JOIN {$users_table} u ON um.user_id = u.ID
				WHERE u.ID IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

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
		$prefix                   = $this->connection->get_prefix();
		$term_relationships_table = $prefix . 'term_relationships';
		$term_taxonomy_table      = $prefix . 'term_taxonomy';
		$posts_table              = $this->connection->get_posts_table();
		$deleted_count            = 0;
		$batch_limit              = self::BATCH_SIZE;

		// Delete relationships where the post doesn't exist.
		do {
			$query = $this->connection->prepare(
				"DELETE tr FROM {$term_relationships_table} tr
				LEFT JOIN {$posts_table} p ON tr.object_id = p.ID
				WHERE p.ID IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		// Delete relationships where the term_taxonomy doesn't exist.
		do {
			$query = $this->connection->prepare(
				"DELETE tr FROM {$term_relationships_table} tr
				LEFT JOIN {$term_taxonomy_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.term_taxonomy_id IS NULL
				LIMIT %d",
				$batch_limit
			);

			if ( null === $query ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $this->connection->query( $query );

			if ( false === $result ) {
				break;
			}

			$deleted_count += $result;
		} while ( $result > 0 && $deleted_count < 10000 );

		return $deleted_count;
	}
}
