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
 * Provides methods to find where media attachments are used across the site.
 *
 * @since 1.2.0
 */
interface ReferenceFinderInterface {

	/**
	 * Find all references to a media attachment.
	 *
	 * Searches post content, post meta, and other locations for media usage.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<array{type: string, id: int, title: string, context: string}> Array of references found.
	 */
	public function find_references( int $attachment_id ): array;

	/**
	 * Check if a media attachment is used anywhere.
	 *
	 * Quick check without loading all reference details.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if media is used, false otherwise.
	 */
	public function is_media_used( int $attachment_id ): bool;

	/**
	 * Get detailed reference locations for a media attachment.
	 *
	 * Groups references by location type for display purposes.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{posts: array, meta: array, featured: array, integrations: array} Grouped reference locations.
	 */
	public function get_reference_locations( int $attachment_id ): array;
}
