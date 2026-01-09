<?php
/**
 * Transients Cleaner Interface
 *
 * Defines the contract for managing WordPress transients.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface TransientsCleanerInterface
 *
 * Contract for transient cleanup operations.
 *
 * @since 1.2.0
 */
interface TransientsCleanerInterface {

	/**
	 * Count all transients.
	 *
	 * @return int Total transient count.
	 */
	public function count_transients(): int;

	/**
	 * Get all expired transients.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude.
	 * @return array Array of expired transient data.
	 */
	public function get_expired_transients( array $exclude_patterns = array() ): array;

	/**
	 * Count expired transients.
	 *
	 * @return int Expired transient count.
	 */
	public function count_expired_transients(): int;

	/**
	 * Get the total size of all transients.
	 *
	 * @return int Size in bytes.
	 */
	public function get_transients_size(): int;

	/**
	 * Delete all expired transients.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude.
	 * @return array Array with 'deleted' count and 'bytes_freed'.
	 */
	public function delete_expired_transients( array $exclude_patterns = array() ): array;

	/**
	 * Delete all transients.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude.
	 * @return array Array with 'deleted' count and 'bytes_freed'.
	 */
	public function delete_all_transients( array $exclude_patterns = array() ): array;

	/**
	 * Get transients by prefix.
	 *
	 * @param string $prefix Transient prefix to search for.
	 * @return array Array of matching transients.
	 */
	public function get_transient_by_prefix( string $prefix ): array;
}
