<?php
/**
 * Duplicate Detector Interface
 *
 * Defines the contract for detecting duplicate media files.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface DuplicateDetectorInterface
 *
 * Contract for duplicate media detection operations.
 * Detects duplicate media files by hash or filename comparison.
 *
 * @since 1.2.0
 */
interface DuplicateDetectorInterface {

	/**
	 * Find duplicate media files.
	 *
	 * @since 1.2.0
	 *
	 * @param array{method?: string, threshold?: int} $options Detection options (method: 'hash'|'filename'|'both', threshold: similarity threshold).
	 * @return array<string, array<int>> Array of duplicate groups keyed by hash/filename.
	 */
	public function find_duplicates( array $options = array() ): array;

	/**
	 * Get duplicate groups with details.
	 *
	 * Returns full attachment details for each duplicate group.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{hash: string, files: array<array{id: int, filename: string, size: int, url: string}>}> Duplicate groups with file details.
	 */
	public function get_duplicate_groups(): array;

	/**
	 * Get potential storage savings from removing duplicates.
	 *
	 * Calculates how much space could be saved by keeping only one copy per group.
	 *
	 * @since 1.2.0
	 *
	 * @return array{bytes: int, formatted: string, groups_count: int} Potential savings information.
	 */
	public function get_potential_savings(): array;
}
