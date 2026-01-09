<?php
/**
 * Reference Finder Interface
 *
 * Defines the contract for finding media references across the site.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ReferenceFinderInterface
 *
 * Contract for media reference detection operations.
 *
 * @since 1.2.0
 */
interface ReferenceFinderInterface {

	/**
	 * Find all references to a media attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found.
	 */
	public function find_references( int $attachment_id ): array;

	/**
	 * Check if a media attachment is used anywhere.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if media is used, false otherwise.
	 */
	public function is_media_used( int $attachment_id ): bool;

	/**
	 * Get detailed reference locations for a media attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array with location details (posts, meta, etc.).
	 */
	public function get_reference_locations( int $attachment_id ): array;
}
