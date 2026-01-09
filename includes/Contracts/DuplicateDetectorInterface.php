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
 *
 * @since 1.2.0
 */
interface DuplicateDetectorInterface {

	/**
	 * Find duplicate media files.
	 *
	 * @param array $options Detection options (method, threshold, etc.).
	 * @return array Array of duplicate groups.
	 */
	public function find_duplicates( array $options = array() ): array;

	/**
	 * Get duplicate groups with details.
	 *
	 * @return array Array of duplicate groups with file details.
	 */
	public function get_duplicate_groups(): array;

	/**
	 * Get potential storage savings from removing duplicates.
	 *
	 * @return array Array with 'bytes', 'formatted', and 'groups_count'.
	 */
	public function get_potential_savings(): array;
}
