<?php
/**
 * Get Plugin Impact Use Case
 *
 * Application service for plugin profiling data.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetPluginImpact
 *
 * @since 1.7.0
 */
class GetPluginImpact {

	private SettingsInterface $settings;

	private PluginProfilerInterface $plugin_profiler;

	/**
	 * @since 1.7.0
	 */
	public function __construct( SettingsInterface $settings, PluginProfilerInterface $plugin_profiler ) {
		$this->settings        = $settings;
		$this->plugin_profiler = $plugin_profiler;
	}

	/**
	 * Execute plugin impact analysis.
	 *
	 * @since 1.7.0
	 *
	 * @return array
	 */
	public function execute(): array {
		if ( empty( $this->settings->get_setting( 'plugin_profiling_enabled', false ) ) ) {
			return array(
				'plugins' => array(),
				'note'    => __( 'Plugin profiling is disabled in settings.', 'wp-admin-health-suite' ),
			);
		}

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

		$plugin_data = array();
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

		usort(
			$plugin_data,
			static function ( $a, $b ): int {
				return $b['load_time'] <=> $a['load_time'];
			}
		);

		return array_merge( array( 'plugins' => $plugin_data ), $meta );
	}
}
