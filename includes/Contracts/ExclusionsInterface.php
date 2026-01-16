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
 * Manages a list of media attachments that should be excluded from cleanup operations.
 *
 * @since 1.2.0
 */
interface ExclusionsInterface {

	/**
	 * Add an attachment to the exclusion list.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        Optional reason for exclusion.
	 * @return bool True on success, false on failure.
	 */
	public function add_exclusion( int $attachment_id, string $reason = '' ): bool;

	/**
	 * Remove an attachment from the exclusion list.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public function remove_exclusion( int $attachment_id ): bool;

	/**
	 * Get all excluded attachments.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{id: int, reason: string, added_at: string}> Array of excluded attachment data.
	 */
	public function get_exclusions(): array;

	/**
	 * Check if an attachment is excluded.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_excluded( int $attachment_id ): bool;

	/**
	 * Bulk add attachments to the exclusion list.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @param string     $reason         Optional reason for exclusion.
	 * @return array{success: int, failed: array<int>} Count of successes and array of failed IDs.
	 */
	public function bulk_add_exclusions( array $attachment_ids, string $reason = '' ): array;

	/**
	 * Clear all exclusions.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_exclusions(): bool;

	/**
	 * Filter out excluded attachments from a list.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<int> Filtered array without excluded IDs.
	 */
	public function filter_excluded( array $attachment_ids ): array;
}
