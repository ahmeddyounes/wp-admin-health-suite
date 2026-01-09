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
 *
 * @since 1.2.0
 */
interface AutoloadAnalyzerInterface {

	/**
	 * Get all autoloaded options from the database.
	 *
	 * @return array Array of autoloaded options with details.
	 */
	public function get_autoloaded_options(): array;

	/**
	 * Get the total size of all autoloaded options.
	 *
	 * @return array Array with total size and count statistics.
	 */
	public function get_autoload_size(): array;

	/**
	 * Find large autoloaded options exceeding a threshold.
	 *
	 * @param int|null $threshold Size threshold in bytes (default: 10KB).
	 * @return array Array of large autoloaded options.
	 */
	public function find_large_autoloads( ?int $threshold = null ): array;

	/**
	 * Recommend autoload changes based on analysis.
	 *
	 * @return array Array of recommendations with option details and suggested actions.
	 */
	public function recommend_autoload_changes(): array;

	/**
	 * Change the autoload status of an option.
	 *
	 * @param string $option_name The name of the option to change.
	 * @param string $new_autoload New autoload value ('yes' or 'no').
	 * @return array Result array with success status and message.
	 */
	public function change_autoload_status( string $option_name, string $new_autoload ): array;
}
