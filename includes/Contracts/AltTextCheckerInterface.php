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
	 * @since 1.2.0
	 *
	 * @param int $limit Maximum number of results.
	 * @return array<int, array{id: int, title: string, url: string, filename: string}> Array of attachments missing alt text.
	 */
	public function find_missing_alt_text( int $limit = 100 ): array;

	/**
	 * Get alt text coverage statistics.
	 *
	 * @since 1.2.0
	 *
	 * @return array{total: int, with_alt: int, without_alt: int, coverage_percentage: float} Coverage statistics.
	 */
	public function get_alt_text_coverage(): array;

	/**
	 * Bulk suggest alt text for images.
	 *
	 * Generates alt text suggestions based on filename analysis.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<int, array{id: int, suggestion: string, confidence: string}> Array of suggestions per attachment.
	 */
	public function bulk_suggest_alt_text( array $attachment_ids ): array;
}
