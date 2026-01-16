<?php
/**
 * Large Files Interface
 *
 * Defines the contract for detecting and managing large media files.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface LargeFilesInterface
 *
 * Contract for large file detection and analysis operations.
 * Identifies media files that may benefit from optimization or compression.
 *
 * @since 1.2.0
 */
interface LargeFilesInterface {

	/**
	 * Find large media files exceeding a threshold.
	 *
	 * @since 1.2.0
	 *
	 * @param int|null $threshold_kb Size threshold in kilobytes. Null for default (500KB).
	 * @return array<array{id: int, filename: string, size: int, formatted_size: string, mime_type: string}> Large files with details.
	 */
	public function find_large_files( ?int $threshold_kb = null ): array;

	/**
	 * Get optimization suggestions for all large media files.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{id: int, filename: string, suggestion: string, potential_savings: int}> Optimization suggestions.
	 */
	public function get_optimization_suggestions(): array;

	/**
	 * Get file size distribution statistics.
	 *
	 * Groups files by size ranges for analysis.
	 *
	 * @since 1.2.0
	 *
	 * @return array{ranges: array<array{min: int, max: int, count: int, total_size: int}>, total_files: int, total_size: int} Size distribution data.
	 */
	public function get_size_distribution(): array;
}
