<?php
/**
 * Safe Delete Interface
 *
 * Defines the contract for safe media deletion with recovery.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SafeDeleteInterface
 *
 * Contract for safe deletion operations with recovery capability.
 * Implements a soft-delete pattern allowing media to be recovered before permanent removal.
 *
 * @since 1.2.0
 */
interface SafeDeleteInterface {

	/**
	 * Prepare attachments for deletion (soft delete).
	 *
	 * Moves attachments to a deletion queue for a grace period before permanent deletion.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs to prepare for deletion.
	 * @return array{success: bool, prepared_items: array<int>, errors: array<string>, message: string} Preparation result.
	 */
	public function prepare_deletion( array $attachment_ids ): array;

	/**
	 * Execute permanent deletion of a prepared item.
	 *
	 * @since 1.2.0
	 *
	 * @param int $deletion_id Deletion ID from the deletion queue.
	 * @return array{success: bool, message: string} Deletion result.
	 */
	public function execute_deletion( int $deletion_id ): array;

	/**
	 * Restore a soft-deleted attachment.
	 *
	 * @since 1.2.0
	 *
	 * @param int $deletion_id Deletion ID from the deletion queue.
	 * @return array{success: bool, attachment_id: int, message: string} Restoration result.
	 */
	public function restore_deleted( int $deletion_id ): array;

	/**
	 * Get attachments in the deletion queue.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{id: int, attachment_id: int, filename: string, queued_at: string, expires_at: string}> Queued attachments.
	 */
	public function get_deletion_queue(): array;

	/**
	 * Get history of deleted attachments.
	 *
	 * @since 1.2.0
	 *
	 * @param int $limit Maximum number of results.
	 * @return array<array{id: int, filename: string, deleted_at: string, deleted_by: int}> Deletion history records.
	 */
	public function get_deleted_history( int $limit = 50 ): array;

	/**
	 * Auto-purge expired soft-deleted attachments.
	 *
	 * Permanently deletes attachments that have passed their grace period.
	 *
	 * @since 1.2.0
	 *
	 * @return array{success: bool, purged_count: int, message: string} Purge result.
	 */
	public function auto_purge_expired(): array;
}
