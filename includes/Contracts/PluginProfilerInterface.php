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
 * Measures and reports on the performance impact of installed plugins.
 *
 * @since 1.2.0
 */
interface PluginProfilerInterface {

	/**
	 * Measure the impact of plugins on site performance.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{load_time: float, memory: int, queries: int, impact_score: float}> Results of the profiling operation.
	 */
	public function measure_plugin_impact(): array;

	/**
	 * Get the slowest plugins based on impact score.
	 *
	 * @since 1.2.0
	 *
	 * @param int $limit Number of plugins to return.
	 * @return array<array{plugin: string, name: string, impact_score: float, load_time: float}> Array of slowest plugins.
	 */
	public function get_slowest_plugins( int $limit = 10 ): array;

	/**
	 * Get memory usage by plugin.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{name: string, memory: int, formatted: string}> Array of plugins with their memory usage.
	 */
	public function get_plugin_memory_usage(): array;

	/**
	 * Get database query counts by plugin.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{name: string, count: int, total_time: float}> Array of plugins with their query counts.
	 */
	public function get_plugin_query_counts(): array;

	/**
	 * Get asset counts by plugin.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{name: string, scripts: int, styles: int}> Array of plugins with their asset counts.
	 */
	public function get_asset_counts_by_plugin(): array;

	/**
	 * Clear cached profiler results.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache(): bool;
}
