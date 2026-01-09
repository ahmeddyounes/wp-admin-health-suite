<?php
/**
 * Cleaner Interface
 *
 * Contract for database cleanup operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface CleanerInterface
 *
 * Defines the contract for database cleanup operations.
 * Provides methods to clean various types of database bloat.
 *
 * @since 1.1.0
 */
interface CleanerInterface {

	/**
	 * Clean post revisions.
	 *
	 * @since 1.1.0
	 *
	 * @param int  $keep     Number of revisions to keep per post. 0 = delete all.
	 * @param bool $dry_run  If true, returns count without deleting.
	 * @return int Number of revisions deleted (or would be deleted).
	 */
	public function clean_revisions( int $keep = 0, bool $dry_run = false ): int;

	/**
	 * Clean auto-draft posts.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $dry_run If true, returns count without deleting.
	 * @return int Number of auto-drafts deleted (or would be deleted).
	 */
	public function clean_auto_drafts( bool $dry_run = false ): int;

	/**
	 * Clean trashed posts.
	 *
	 * @since 1.1.0
	 *
	 * @param int  $older_than_days Only delete trash older than X days. 0 = all.
	 * @param bool $dry_run         If true, returns count without deleting.
	 * @return int Number of trashed posts deleted (or would be deleted).
	 */
	public function clean_trashed_posts( int $older_than_days = 0, bool $dry_run = false ): int;

	/**
	 * Clean spam comments.
	 *
	 * @since 1.1.0
	 *
	 * @param int  $older_than_days Only delete spam older than X days. 0 = all.
	 * @param bool $dry_run         If true, returns count without deleting.
	 * @return int Number of spam comments deleted (or would be deleted).
	 */
	public function clean_spam_comments( int $older_than_days = 0, bool $dry_run = false ): int;

	/**
	 * Clean trashed comments.
	 *
	 * @since 1.1.0
	 *
	 * @param int  $older_than_days Only delete trash older than X days. 0 = all.
	 * @param bool $dry_run         If true, returns count without deleting.
	 * @return int Number of trashed comments deleted (or would be deleted).
	 */
	public function clean_trashed_comments( int $older_than_days = 0, bool $dry_run = false ): int;

	/**
	 * Clean expired transients.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $exclude_prefixes Transient prefixes to exclude.
	 * @param bool          $dry_run          If true, returns count without deleting.
	 * @return int Number of expired transients deleted (or would be deleted).
	 */
	public function clean_expired_transients( array $exclude_prefixes = array(), bool $dry_run = false ): int;

	/**
	 * Clean orphaned postmeta.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $dry_run If true, returns count without deleting.
	 * @return int Number of orphaned postmeta deleted (or would be deleted).
	 */
	public function clean_orphaned_postmeta( bool $dry_run = false ): int;

	/**
	 * Clean orphaned commentmeta.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $dry_run If true, returns count without deleting.
	 * @return int Number of orphaned commentmeta deleted (or would be deleted).
	 */
	public function clean_orphaned_commentmeta( bool $dry_run = false ): int;

	/**
	 * Clean orphaned termmeta.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $dry_run If true, returns count without deleting.
	 * @return int Number of orphaned termmeta deleted (or would be deleted).
	 */
	public function clean_orphaned_termmeta( bool $dry_run = false ): int;

	/**
	 * Run all cleanup operations.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $options Cleanup options.
	 * @param bool                 $dry_run If true, returns counts without deleting.
	 * @return array<string, int> Array of cleanup type => items deleted.
	 */
	public function clean_all( array $options = array(), bool $dry_run = false ): array;

	/**
	 * Optimize database tables.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string> $tables Tables to optimize. Empty = all WordPress tables.
	 * @return array<string, bool> Array of table => success status.
	 */
	public function optimize_tables( array $tables = array() ): array;
}
