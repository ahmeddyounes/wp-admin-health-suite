<?php
/**
 * Plugin Profiler Interface
 *
 * Defines the contract for profiling WordPress plugin performance.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface PluginProfilerInterface
 *
 * Contract for plugin performance profiling operations.
 *
 * @since 1.2.0
 */
interface PluginProfilerInterface {

	/**
	 * Measure the impact of plugins on site performance.
	 *
	 * @return array Results of the profiling operation.
	 */
	public function measure_plugin_impact(): array;

	/**
	 * Get the slowest plugins based on impact score.
	 *
	 * @param int $limit Number of plugins to return.
	 * @return array Array of slowest plugins.
	 */
	public function get_slowest_plugins( int $limit = 10 ): array;

	/**
	 * Get memory usage by plugin.
	 *
	 * @return array Array of plugins with their memory usage.
	 */
	public function get_plugin_memory_usage(): array;

	/**
	 * Get database query counts by plugin.
	 *
	 * @return array Array of plugins with their query counts.
	 */
	public function get_plugin_query_counts(): array;

	/**
	 * Get asset counts by plugin.
	 *
	 * @return array Array of plugins with their asset counts.
	 */
	public function get_asset_counts_by_plugin(): array;

	/**
	 * Clear cached profiler results.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache(): bool;
}
