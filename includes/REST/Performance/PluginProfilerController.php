<?php
/**
 * Plugin Profiler REST Controller
 *
 * Handles plugin impact analysis and profiling.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for plugin profiling endpoints.
 *
 * Provides endpoints for analyzing plugin impact on performance.
 *
 * @since 1.3.0
 */
class PluginProfilerController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/plugins';

	/**
	 * Plugin profiler instance.
	 *
	 * @var PluginProfilerInterface
	 */
	private PluginProfilerInterface $plugin_profiler;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface       $settings       Settings instance.
	 * @param ConnectionInterface     $connection     Database connection instance.
	 * @param PluginProfilerInterface $plugin_profiler Plugin profiler instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		PluginProfilerInterface $plugin_profiler
	) {
		parent::__construct( $settings, $connection );
		$this->plugin_profiler = $plugin_profiler;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/plugins.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plugin_impact' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Get plugin impact analysis.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PluginProfilerController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_plugin_impact( $request ) {
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();

		$results      = $this->plugin_profiler->measure_plugin_impact();
		$measurements = array();
		$meta         = array();

		if ( isset( $results['status'] ) && 'success' === $results['status'] ) {
			$measurements = isset( $results['measurements'] ) && is_array( $results['measurements'] ) ? $results['measurements'] : array();

			if ( isset( $results['measured_at'] ) ) {
				$meta['measured_at'] = (string) $results['measured_at'];
			}

			if ( isset( $results['note'] ) ) {
				$meta['note'] = (string) $results['note'];
			}
		} elseif ( isset( $results['message'] ) ) {
			$meta['error'] = (string) $results['message'];
		}
		$plugin_data  = array();

		foreach ( $measurements as $measurement ) {
			$plugin_file = isset( $measurement['file'] ) ? (string) $measurement['file'] : '';
			$name        = isset( $measurement['name'] ) ? (string) $measurement['name'] : '';

			$version = '';
			if ( $plugin_file && isset( $all_plugins[ $plugin_file ]['Version'] ) ) {
				$version = (string) $all_plugins[ $plugin_file ]['Version'];
			}

			$load_time_ms = isset( $measurement['time'] ) ? round( (float) $measurement['time'] * 1000, 1 ) : 0.0;
			$memory_kb    = isset( $measurement['memory'] ) ? (int) round( (float) $measurement['memory'] / 1024 ) : 0;
			$queries      = isset( $measurement['queries'] ) ? absint( $measurement['queries'] ) : 0;

			$plugin_data[] = array(
				'name'         => $name,
				'file'         => $plugin_file,
				'version'      => $version,
				'load_time'    => $load_time_ms,
				'memory'       => $memory_kb,
				'queries'      => $queries,
				'impact_score' => isset( $measurement['impact_score'] ) ? (float) $measurement['impact_score'] : 0.0,
			);
		}

		// Sort by load time impact.
		usort(
			$plugin_data,
			function ( $a, $b ): int {
				return $b['load_time'] <=> $a['load_time'];
			}
		);

		return $this->format_response(
			true,
			array_merge(
				array( 'plugins' => $plugin_data ),
				$meta
			),
			__( 'Plugin impact data retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Estimate plugin load time.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PluginProfilerController.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return float Estimated load time in milliseconds.
	 */
	private function estimate_plugin_load_time( string $plugin_file ): float {
		$plugin_path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$file_count  = 0;

		if ( is_dir( $plugin_path ) ) {
			$iterator   = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);
			$file_count = iterator_count( $iterator );
		}

		// Estimate: more files = potentially more load time.
		return min( 100, max( 5, $file_count * 0.5 ) );
	}

	/**
	 * Estimate plugin memory usage.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PluginProfilerController.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return int Estimated memory in KB.
	 */
	private function estimate_plugin_memory( string $plugin_file ): int {
		$plugin_path = WP_PLUGIN_DIR . '/' . dirname( $plugin_file );
		$total_size  = 0;

		if ( is_dir( $plugin_path ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $plugin_path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'php' ) {
					$total_size += $file->getSize();
				}
			}
		}

		// Convert to KB.
		return (int) ( $total_size / 1024 );
	}

	/**
	 * Estimate plugin query count.
	 *
	 * Estimates based on plugin-specific options in the database.
	 * This is a heuristic approach - actual profiling would be more accurate.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to PluginProfilerController.
	 *
	 * @param string $plugin_file Plugin file.
	 * @return int Estimated query count.
	 */
	private function estimate_plugin_queries( string $plugin_file ): int {
		$connection    = $this->get_connection();
		$options_table = $connection->get_options_table();

		$plugin_slug = dirname( $plugin_file );
		if ( '.' === $plugin_slug ) {
			$plugin_slug = basename( $plugin_file, '.php' );
		}

		// Count plugin-specific options (indicates database usage).
		$query = $connection->prepare(
			"SELECT COUNT(*) FROM {$options_table} WHERE option_name LIKE %s",
			$connection->esc_like( $plugin_slug ) . '%'
		);
		$option_count = $query ? $connection->get_var( $query ) : 0;

		// Rough estimate: each option might mean 1-2 queries on load.
		return absint( $option_count ) * 2;
	}
}
