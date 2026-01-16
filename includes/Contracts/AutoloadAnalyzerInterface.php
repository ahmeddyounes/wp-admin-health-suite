<?php
/**
 * Autoload Analyzer Interface
 *
 * Defines the contract for analyzing WordPress autoloaded options.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface AutoloadAnalyzerInterface
 *
 * Contract for autoload options analysis operations.
 * Analyzes WordPress options that are automatically loaded on every page request.
 *
 * @since 1.2.0
 */
interface AutoloadAnalyzerInterface {

	/**
	 * Get all autoloaded options from the database.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{name: string, size: int, autoload: string}> Array of autoloaded options with details.
	 */
	public function get_autoloaded_options(): array;

	/**
	 * Get the total size of all autoloaded options.
	 *
	 * @since 1.2.0
	 *
	 * @return array{total_size: int, count: int, formatted_size: string} Size statistics.
	 */
	public function get_autoload_size(): array;

	/**
	 * Find large autoloaded options exceeding a threshold.
	 *
	 * @since 1.2.0
	 *
	 * @param int|null $threshold Size threshold in bytes (default: 10KB).
	 * @return array<array{name: string, size: int, formatted_size: string}> Array of large autoloaded options.
	 */
	public function find_large_autoloads( ?int $threshold = null ): array;

	/**
	 * Recommend autoload changes based on analysis.
	 *
	 * Identifies options that could safely have autoload disabled.
	 *
	 * @since 1.2.0
	 *
	 * @return array<array{name: string, size: int, reason: string, action: string}> Recommendations.
	 */
	public function recommend_autoload_changes(): array;

	/**
	 * Change the autoload status of an option.
	 *
	 * @since 1.2.0
	 *
	 * @param string $option_name  The name of the option to change.
	 * @param string $new_autoload New autoload value ('yes' or 'no').
	 * @return array{success: bool, message: string} Result with success status.
	 */
	public function change_autoload_status( string $option_name, string $new_autoload ): array;
}
