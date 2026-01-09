<?php
/**
 * Scanner Interface
 *
 * Contract for media scanning operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ScannerInterface
 *
 * Defines the contract for media scanning operations.
 * Provides methods to detect unused, duplicate, and large media files.
 *
 * @since 1.1.0
 */
interface ScannerInterface {

	/**
	 * Scan for unused media files.
	 *
	 * @since 1.1.0
	 *
	 * @param int $batch_size Number of attachments to scan per batch.
	 * @param int $offset     Starting offset for pagination.
	 * @return array{
	 *     unused: array<int>,
	 *     scanned: int,
	 *     total: int,
	 *     has_more: bool
	 * } Scan results.
	 */
	public function scan_unused_media( int $batch_size = 100, int $offset = 0 ): array;

	/**
	 * Scan for duplicate media files.
	 *
	 * @since 1.1.0
	 *
	 * @param string $method Detection method: 'hash', 'filename', or 'both'.
	 * @return array<string, array<int>> Array of hash/name => attachment IDs.
	 */
	public function scan_duplicate_media( string $method = 'hash' ): array;

	/**
	 * Scan for large media files.
	 *
	 * @since 1.1.0
	 *
	 * @param int $threshold_kb Size threshold in kilobytes.
	 * @return array<int, array{id: int, size: int, file: string}> Large file data.
	 */
	public function scan_large_media( int $threshold_kb = 1000 ): array;

	/**
	 * Get the total count of media files.
	 *
	 * @since 1.1.0
	 *
	 * @return int Total number of attachments.
	 */
	public function get_total_media_count(): int;

	/**
	 * Get the total size of all media files.
	 *
	 * @since 1.1.0
	 *
	 * @return int Total size in bytes.
	 */
	public function get_total_media_size(): int;

	/**
	 * Check if a specific attachment is in use.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if in use, false otherwise.
	 */
	public function is_attachment_in_use( int $attachment_id ): bool;

	/**
	 * Get usage locations for an attachment.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<array{type: string, id: int, title: string}> Usage locations.
	 */
	public function get_attachment_usage( int $attachment_id ): array;

	/**
	 * Get a summary of the media library.
	 *
	 * @since 1.1.0
	 *
	 * @return array{
	 *     total_count: int,
	 *     total_size: int,
	 *     unused_count: int,
	 *     unused_size: int,
	 *     duplicate_count: int,
	 *     large_count: int
	 * } Media summary statistics.
	 */
	public function get_media_summary(): array;
}
