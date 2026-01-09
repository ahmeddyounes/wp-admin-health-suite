<?php
/**
 * Exclusions Interface
 *
 * Defines the contract for managing media exclusions from cleanup.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ExclusionsInterface
 *
 * Contract for media exclusion management operations.
 *
 * @since 1.2.0
 */
interface ExclusionsInterface {

	/**
	 * Add an attachment to the exclusion list.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        Optional reason for exclusion.
	 * @return bool True on success, false on failure.
	 */
	public function add_exclusion( int $attachment_id, string $reason = '' ): bool;

	/**
	 * Remove an attachment from the exclusion list.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public function remove_exclusion( int $attachment_id ): bool;

	/**
	 * Get all excluded attachments.
	 *
	 * @return array Array of excluded attachment IDs with details.
	 */
	public function get_exclusions(): array;

	/**
	 * Check if an attachment is excluded.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_excluded( int $attachment_id ): bool;

	/**
	 * Bulk add attachments to the exclusion list.
	 *
	 * @param array  $attachment_ids Array of attachment IDs.
	 * @param string $reason         Optional reason for exclusion.
	 * @return array Array with 'success' count and 'failed' IDs.
	 */
	public function bulk_add_exclusions( array $attachment_ids, string $reason = '' ): array;

	/**
	 * Clear all exclusions.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_exclusions(): bool;

	/**
	 * Filter out excluded attachments from a list.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Filtered array without excluded IDs.
	 */
	public function filter_excluded( array $attachment_ids ): array;
}
