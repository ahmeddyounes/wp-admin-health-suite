<?php
/**
 * Alt Text Checker Interface
 *
 * Defines the contract for checking image alt text coverage.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface AltTextCheckerInterface
 *
 * Contract for alt text checking operations.
 *
 * @since 1.2.0
 */
interface AltTextCheckerInterface {

	/**
	 * Find images missing alt text.
	 *
	 * @param int $limit Maximum number of results.
	 * @return array Array of attachments missing alt text.
	 */
	public function find_missing_alt_text( int $limit = 100 ): array;

	/**
	 * Get alt text coverage statistics.
	 *
	 * @return array Array with coverage percentage and counts.
	 */
	public function get_alt_text_coverage(): array;

	/**
	 * Bulk suggest alt text for images.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Array of suggestions per attachment.
	 */
	public function bulk_suggest_alt_text( array $attachment_ids ): array;
}
