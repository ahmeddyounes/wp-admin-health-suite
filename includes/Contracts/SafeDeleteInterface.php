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
 *
 * @since 1.2.0
 */
interface SafeDeleteInterface {

	/**
	 * Prepare attachments for deletion (soft delete).
	 *
	 * @param array $attachment_ids Array of attachment IDs to prepare for deletion.
	 * @return array Result with success status, prepared_items, errors, and message.
	 */
	public function prepare_deletion( array $attachment_ids ): array;

	/**
	 * Execute permanent deletion of a prepared item.
	 *
	 * @param int $deletion_id Deletion ID from the deletion queue.
	 * @return array Result with success status and message.
	 */
	public function execute_deletion( int $deletion_id ): array;

	/**
	 * Restore a soft-deleted attachment.
	 *
	 * @param int $deletion_id Deletion ID from the deletion queue.
	 * @return array Result with success status, attachment_id, and message.
	 */
	public function restore_deleted( int $deletion_id ): array;

	/**
	 * Get attachments in the deletion queue.
	 *
	 * @return array Array of queued attachments.
	 */
	public function get_deletion_queue(): array;

	/**
	 * Get history of deleted attachments.
	 *
	 * @param int $limit Maximum number of results.
	 * @return array Array of deletion history records.
	 */
	public function get_deleted_history( int $limit = 50 ): array;

	/**
	 * Auto-purge expired soft-deleted attachments.
	 *
	 * @return array Result with success status, purged_count, and message.
	 */
	public function auto_purge_expired(): array;
}
