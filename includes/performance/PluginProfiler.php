<?php
/**
 * Plugin Profiler Class
 *
 * Provides approximate performance profiling for WordPress plugins.
 * Measures plugin impact on queries, memory, load time, and assets.
 *
 * Note: This provides approximate estimates, not exact profiling data.
 * Results are indicative and help identify potentially problematic plugins.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

use WPAdminHealth\Contracts\PluginProfilerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Plugin Profiler class for measuring plugin performance impact.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements PluginProfilerInterface.
 */
class PluginProfiler implements PluginProfilerInterface {

	/**
	 * Transient key for storing profiler results.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'wpahs_plugin_profiler_results';

	/**
	 * Transient expiration time (24 hours).
	 *
	 * @var int
	 */
	const TRANSIENT_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Maximum number of files to process per plugin.
	 *
	 * Prevents resource exhaustion from plugins with excessive files.
	 *
	 * @var int
	 */
	const MAX_FILES_PER_PLUGIN = 500;

	/**
	 * Baseline measurements (no plugins).
	 *
	 * @var array|null
	 */
	private $baseline = null;

	/**
	 * Plugin measurements.
	 *
	 * @var array|null
	 */
	private $measurements = null;

	/**
	 * Measure the impact of plugins on site performance.
	 *
	 * This method performs approximate profiling by measuring:
	 * - Database query count
	 * - Memory usage
	 * - Load time
	 * - Enqueued assets
	 *
	 * Results are stored in a transient for later retrieval.
	 * This is an approximation and should be used as a guide only.
	 *
 * @since 1.0.0
 *
	 * @return array Results of the profiling operation.
	 */
	public function measure_plugin_impact() {
		// Check if we have cached results.
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		// Get all active plugins.
		$active_plugins = get_option( 'active_plugins', array() );
		if ( empty( $active_plugins ) ) {
			return array(
				'status'  => 'error',
				'message' => 'No active plugins found.',
			);
		}

		// Initialize measurements array.
		$measurements = array();

		// Measure each plugin.
		foreach ( $active_plugins as $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$plugin_slug = dirname( $plugin );
			if ( '.' === $plugin_slug ) {
				$plugin_slug = basename( $plugin, '.php' );
			}

			// Perform measurement for this plugin.
			$measurement = $this->measure_single_plugin( $plugin, $plugin_data['Name'] );

			$measurements[ $plugin_slug ] = array(
				'name'           => $plugin_data['Name'],
				'file'           => $plugin,
				'queries'        => $measurement['queries'],
				'memory'         => $measurement['memory'],
				'time'           => $measurement['time'],
				'assets'         => $measurement['assets'],
				'impact_score'   => $this->calculate_impact_score( $measurement ),
			);
		}

		// Sort by impact score (highest first).
		uasort(
			$measurements,
			function ( $a, $b ) {
				return $b['impact_score'] <=> $a['impact_score'];
			}
		);

		$results = array(
			'status'       => 'success',
			'measured_at'  => current_time( 'mysql' ),
			'measurements' => $measurements,
			'note'         => 'These are approximate measurements and should be used as a general guide only.',
		);

		// Store in transient.
		set_transient( self::TRANSIENT_KEY, $results, self::TRANSIENT_EXPIRATION );

		return $results;
	}

	/**
	 * Measure a single plugin's impact.
	 *
	 * This is a simplified simulation since we can't easily disable
	 * individual plugins during runtime. We track changes during admin_init.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $plugin_name Plugin name.
	 * @return array Measurement data.
	 */
	private function measure_single_plugin( $plugin_file, $plugin_name ) {
		global $wpdb;

		// Get baseline query count.
		$queries_before = $wpdb->num_queries;
		$memory_before  = memory_get_usage();
		$time_before    = microtime( true );

		// Simulate plugin-specific hook execution if possible.
		// Note: This is approximate - we can't truly isolate plugins at runtime.
		// We're using a heuristic approach by checking what hooks fire.

		// Get current enqueued assets that might be from this plugin.
		$assets = $this->get_plugin_assets( $plugin_file );

		// Approximate query impact by checking for plugin-specific tables or options.
		$queries_after = $wpdb->num_queries;
		$memory_after  = memory_get_usage();
		$time_after    = microtime( true );

		// Calculate differences (these are rough estimates).
		$query_delta  = max( 0, $queries_after - $queries_before );
		$memory_delta = max( 0, $memory_after - $memory_before );
		$time_delta   = max( 0, $time_after - $time_before );

		// For more accurate measurements, we'd look at plugin-specific data.
		// This is a simplified version that provides estimates.
		$estimated_queries = $this->estimate_plugin_queries( $plugin_file );
		$estimated_memory  = $this->estimate_plugin_memory( $plugin_file );

		return array(
			'queries' => max( $query_delta, $estimated_queries ),
			'memory'  => max( $memory_delta, $estimated_memory ),
			'time'    => $time_delta,
			'assets'  => $assets,
		);
	}

	/**
	 * Estimate plugin's database query count.
	 *
	 * This is a heuristic approach based on plugin options and known patterns.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return int Estimated query count.
	 */
	private function estimate_plugin_queries( $plugin_file ) {
		global $wpdb;

		$plugin_slug = dirname( $plugin_file );
		if ( '.' === $plugin_slug ) {
			$plugin_slug = basename( $plugin_file, '.php' );
		}

		// Count plugin-specific options (indicates database usage).
		$option_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $plugin_slug ) . '%'
			)
		);

		// Rough estimate: each option might mean 1-2 queries on load.
		return absint( $option_count ) * 2;
	}

	/**
	 * Estimate plugin's memory usage.
	 *
	 * This is a very rough estimate based on file count and size.
	 * Limited to MAX_FILES_PER_PLUGIN to prevent resource exhaustion.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return int Estimated memory usage in bytes.
	 */
	private function estimate_plugin_memory( $plugin_file ) {
		$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );

		// If it's a single-file plugin.
		if ( ! is_dir( $plugin_dir ) ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( file_exists( $plugin_path ) ) {
				return filesize( $plugin_path );
			}
			return 0;
		}

		// For directory-based plugins, estimate based on PHP file sizes.
		// Limited to prevent resource exhaustion from plugins with many files.
		$total_size  = 0;
		$file_count  = 0;
		$files       = glob( $plugin_dir . '/*.php' );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				// Resource limit: Stop after MAX_FILES_PER_PLUGIN files.
				if ( ++$file_count > self::MAX_FILES_PER_PLUGIN ) {
					break;
				}

				if ( file_exists( $file ) ) {
					$total_size += filesize( $file );
				}
			}
		}

		// Memory usage is roughly 2-3x file size when loaded.
		return $total_size * 2;
	}

	/**
	 * Get assets (scripts and styles) enqueued by a plugin.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return array Asset counts.
	 */
	private function get_plugin_assets( $plugin_file ) {
		global $wp_scripts, $wp_styles;

		$plugin_slug = dirname( $plugin_file );
		if ( '.' === $plugin_slug ) {
			$plugin_slug = basename( $plugin_file, '.php' );
		}

		$scripts = 0;
		$styles  = 0;

		// Count scripts that likely belong to this plugin.
		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( $wp_scripts->registered as $handle => $script ) {
				if ( false !== strpos( $script->src, $plugin_slug ) ) {
					++$scripts;
				}
			}
		}

		// Count styles that likely belong to this plugin.
		if ( $wp_styles instanceof \WP_Styles ) {
			foreach ( $wp_styles->registered as $handle => $style ) {
				if ( false !== strpos( $style->src, $plugin_slug ) ) {
					++$styles;
				}
			}
		}

		return array(
			'scripts' => $scripts,
			'styles'  => $styles,
			'total'   => $scripts + $styles,
		);
	}

	/**
	 * Calculate an impact score for a plugin.
	 *
	 * Higher scores indicate greater impact on performance.
	 * This is a weighted calculation of various metrics.
	 *
	 * @param array $measurement Measurement data.
	 * @return float Impact score.
	 */
	private function calculate_impact_score( $measurement ) {
		// Weights for different metrics.
		$query_weight = 1.0;
		$memory_weight = 0.000001; // Convert bytes to MB equivalent.
		$time_weight = 1000.0; // Convert seconds to ms equivalent.
		$asset_weight = 5.0;

		$score = 0;

		$score += $measurement['queries'] * $query_weight;
		$score += $measurement['memory'] * $memory_weight;
		$score += $measurement['time'] * $time_weight;
		$score += $measurement['assets']['total'] * $asset_weight;

		return round( $score, 2 );
	}

	/**
	 * Get the slowest plugins based on impact score.
	 *
 * @since 1.0.0
 *
	 * @param int $limit Number of plugins to return (default: 10).
	 * @return array Array of slowest plugins.
	 */
	public function get_slowest_plugins( $limit = 10 ) {
		$results = $this->measure_plugin_impact();

		if ( 'error' === $results['status'] || empty( $results['measurements'] ) ) {
			return array();
		}

		// Already sorted by impact score in measure_plugin_impact().
		$slowest = array_slice( $results['measurements'], 0, $limit, true );

		return $slowest;
	}

	/**
	 * Get memory usage by plugin.
	 *
 * @since 1.0.0
 *
	 * @return array Array of plugins with their memory usage.
	 */
	public function get_plugin_memory_usage() {
		$results = $this->measure_plugin_impact();

		if ( 'error' === $results['status'] || empty( $results['measurements'] ) ) {
			return array();
		}

		$memory_usage = array();
		foreach ( $results['measurements'] as $slug => $data ) {
			$memory_usage[ $slug ] = array(
				'name'   => $data['name'],
				'memory' => $data['memory'],
			);
		}

		// Sort by memory usage (highest first).
		uasort(
			$memory_usage,
			function ( $a, $b ) {
				return $b['memory'] <=> $a['memory'];
			}
		);

		return $memory_usage;
	}

	/**
	 * Get database query counts by plugin.
	 *
 * @since 1.0.0
 *
	 * @return array Array of plugins with their query counts.
	 */
	public function get_plugin_query_counts() {
		$results = $this->measure_plugin_impact();

		if ( 'error' === $results['status'] || empty( $results['measurements'] ) ) {
			return array();
		}

		$query_counts = array();
		foreach ( $results['measurements'] as $slug => $data ) {
			$query_counts[ $slug ] = array(
				'name'    => $data['name'],
				'queries' => $data['queries'],
			);
		}

		// Sort by query count (highest first).
		uasort(
			$query_counts,
			function ( $a, $b ) {
				return $b['queries'] <=> $a['queries'];
			}
		);

		return $query_counts;
	}

	/**
	 * Get asset counts by plugin.
	 *
 * @since 1.0.0
 *
	 * @return array Array of plugins with their asset counts.
	 */
	public function get_asset_counts_by_plugin() {
		$results = $this->measure_plugin_impact();

		if ( 'error' === $results['status'] || empty( $results['measurements'] ) ) {
			return array();
		}

		$asset_counts = array();
		foreach ( $results['measurements'] as $slug => $data ) {
			$asset_counts[ $slug ] = array(
				'name'    => $data['name'],
				'scripts' => $data['assets']['scripts'],
				'styles'  => $data['assets']['styles'],
				'total'   => $data['assets']['total'],
			);
		}

		// Sort by total assets (highest first).
		uasort(
			$asset_counts,
			function ( $a, $b ) {
				return $b['total'] <=> $a['total'];
			}
		);

		return $asset_counts;
	}

	/**
	 * Clear cached profiler results.
	 *
 * @since 1.0.0
 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache() {
		return delete_transient( self::TRANSIENT_KEY );
	}
}
