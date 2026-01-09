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
 *
 * @since 1.2.0
 */
interface LargeFilesInterface {

	/**
	 * Find large media files exceeding a threshold.
	 *
	 * @param int|null $threshold_kb Size threshold in kilobytes. Null for default (500KB).
	 * @return array Array of large files with details.
	 */
	public function find_large_files( ?int $threshold_kb = null ): array;

	/**
	 * Get optimization suggestions for all large media files.
	 *
	 * @return array Array of optimization suggestions with actionable recommendations.
	 */
	public function get_optimization_suggestions(): array;

	/**
	 * Get file size distribution statistics.
	 *
	 * @return array Array with size distribution data.
	 */
	public function get_size_distribution(): array;
}
