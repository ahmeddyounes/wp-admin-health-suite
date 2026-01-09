<?php
/**
 * Orphaned Cleaner Interface
 *
 * Defines the contract for managing orphaned database records.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface OrphanedCleanerInterface
 *
 * Contract for orphaned data cleanup operations.
 *
 * @since 1.2.0
 */
interface OrphanedCleanerInterface {

	/**
	 * Find orphaned post meta records.
	 *
	 * @return array Array of orphaned post meta records.
	 */
	public function find_orphaned_postmeta(): array;

	/**
	 * Find orphaned comment meta records.
	 *
	 * @return array Array of orphaned comment meta records.
	 */
	public function find_orphaned_commentmeta(): array;

	/**
	 * Find orphaned term meta records.
	 *
	 * @return array Array of orphaned term meta records.
	 */
	public function find_orphaned_termmeta(): array;

	/**
	 * Find orphaned term relationships.
	 *
	 * @return array Array of orphaned term relationships.
	 */
	public function find_orphaned_relationships(): array;

	/**
	 * Count orphaned post meta records.
	 *
	 * Returns count without loading all IDs into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned post meta records.
	 */
	public function count_orphaned_postmeta(): int;

	/**
	 * Count orphaned comment meta records.
	 *
	 * Returns count without loading all IDs into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned comment meta records.
	 */
	public function count_orphaned_commentmeta(): int;

	/**
	 * Count orphaned term meta records.
	 *
	 * Returns count without loading all IDs into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned term meta records.
	 */
	public function count_orphaned_termmeta(): int;

	/**
	 * Count orphaned term relationships.
	 *
	 * Returns count without loading all data into memory.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of orphaned term relationships.
	 */
	public function count_orphaned_relationships(): int;

	/**
	 * Delete orphaned post meta records.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_postmeta(): int;

	/**
	 * Delete orphaned comment meta records.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_commentmeta(): int;

	/**
	 * Delete orphaned term meta records.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_termmeta(): int;

	/**
	 * Delete orphaned term relationships.
	 *
	 * @return int Number of records deleted.
	 */
	public function delete_orphaned_relationships(): int;
}
