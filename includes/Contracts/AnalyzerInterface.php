<?php
/**
 * Analyzer Interface
 *
 * Contract for database analysis operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface AnalyzerInterface
 *
 * Defines the contract for database analysis operations.
 * Provides methods to analyze database size, bloat, and optimization opportunities.
 *
 * @since 1.1.0
 */
interface AnalyzerInterface {

	/**
	 * Get the total database size in bytes.
	 *
	 * @since 1.1.0
	 *
	 * @return int Total database size in bytes.
	 */
	public function get_database_size(): int;

	/**
	 * Get sizes of all database tables.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> Array of table names and their sizes in bytes.
	 */
	public function get_table_sizes(): array;

	/**
	 * Get the count of post revisions.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of post revisions.
	 */
	public function get_revisions_count(): int;

	/**
	 * Get the count of auto-draft posts.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of auto-draft posts.
	 */
	public function get_auto_drafts_count(): int;

	/**
	 * Get the count of trashed posts.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of trashed posts.
	 */
	public function get_trashed_posts_count(): int;

	/**
	 * Get the count of spam comments.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of spam comments.
	 */
	public function get_spam_comments_count(): int;

	/**
	 * Get the count of trashed comments.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of trashed comments.
	 */
	public function get_trashed_comments_count(): int;

	/**
	 * Get the count of expired transients.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of expired transients.
	 */
	public function get_expired_transients_count(): int;

	/**
	 * Get the count of orphaned postmeta.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of orphaned postmeta records.
	 */
	public function get_orphaned_postmeta_count(): int;

	/**
	 * Get the count of orphaned commentmeta.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of orphaned commentmeta records.
	 */
	public function get_orphaned_commentmeta_count(): int;

	/**
	 * Get the count of orphaned termmeta.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of orphaned termmeta records.
	 */
	public function get_orphaned_termmeta_count(): int;

	/**
	 * Get a summary of all bloat counts.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> Array of bloat type => count pairs.
	 */
	public function get_bloat_summary(): array;

	/**
	 * Get the estimated reclaimable space in bytes.
	 *
	 * @since 1.1.0
	 *
	 * @return int Estimated reclaimable space in bytes.
	 */
	public function get_estimated_reclaimable_space(): int;
}
