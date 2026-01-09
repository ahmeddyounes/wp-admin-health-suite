<?php
/**
 * Media Aware Integration Interface
 *
 * Contract for integrations that can detect media usage.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface MediaAwareIntegrationInterface
 *
 * Extends IntegrationInterface for integrations that can detect
 * media usage in their plugin's content (e.g., Elementor, WooCommerce).
 *
 * @since 1.1.0
 */
interface MediaAwareIntegrationInterface extends IntegrationInterface {

	/**
	 * Check if an attachment is used in this integration's content.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID to check.
	 * @return bool True if the attachment is used.
	 */
	public function is_attachment_used( int $attachment_id ): bool;

	/**
	 * Get all attachment IDs used by this integration.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int> Array of attachment IDs.
	 */
	public function get_used_attachments(): array;

	/**
	 * Get attachment usage locations.
	 *
	 * Returns information about where an attachment is used.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<array{post_id: int, post_title: string, context: string}> Usage locations.
	 */
	public function get_attachment_usage( int $attachment_id ): array;
}
