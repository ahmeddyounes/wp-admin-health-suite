<?php
/**
 * Get Performance Recommendations Use Case
 *
 * Application service for performance recommendations.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetRecommendations
 *
 * @since 1.7.0
 */
class GetRecommendations {

	private RunHealthCheck $health_check;

	/**
	 * @since 1.7.0
	 */
	public function __construct( RunHealthCheck $health_check ) {
		$this->health_check = $health_check;
	}

	/**
	 * Execute recommendation generation.
	 *
	 * @since 1.7.0
	 *
	 * @return array
	 */
	public function execute(): array {
		$autoload_results = $this->health_check->check_autoload();
		$cache_results    = $this->health_check->check_cache();

		$recommendations = array();

		$plugin_count = count( get_option( 'active_plugins', array() ) );
		if ( $plugin_count > 20 ) {
			$recommendations[] = array(
				'type'        => 'warning',
				'title'       => __( 'Too Many Plugins', 'wp-admin-health-suite' ),
				'description' => sprintf(
					/* translators: %d: number of active plugins */
					__( 'You have %d active plugins. Consider deactivating unused plugins to improve performance.', 'wp-admin-health-suite' ),
					$plugin_count
				),
				'action'      => 'review_plugins',
			);
		}

		$autoload_size = $autoload_results['total_size'] ?? 0;
		$autoload_mb   = $autoload_size / 1024 / 1024;
		if ( $autoload_mb > 0.8 ) {
			$recommendations[] = array(
				'type'        => 'warning',
				'title'       => __( 'Large Autoload Data', 'wp-admin-health-suite' ),
				'description' => sprintf(
					/* translators: %s: autoload size in MB */
					__( 'Your autoload data is %.2f MB. Consider cleaning up unused options.', 'wp-admin-health-suite' ),
					$autoload_mb
				),
				'action'      => 'optimize_autoload',
			);
		}

		if ( empty( $cache_results['object_cache_enabled'] ) ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable Object Caching', 'wp-admin-health-suite' ),
				'description' => __( 'Consider implementing an object cache (Redis, Memcached) to improve database performance.', 'wp-admin-health-suite' ),
				'action'      => 'enable_object_cache',
			);
		}

		if ( empty( $cache_results['opcache_enabled'] ) ) {
			$recommendations[] = array(
				'type'        => 'info',
				'title'       => __( 'Enable OPcache', 'wp-admin-health-suite' ),
				'description' => __( 'OPcache can significantly improve PHP performance by caching compiled scripts.', 'wp-admin-health-suite' ),
				'action'      => 'enable_opcache',
			);
		}

		if ( ! empty( $cache_results['recommendations'] ) ) {
			foreach ( $cache_results['recommendations'] as $rec ) {
				$recommendations[] = array(
					'type'        => $rec['type'] ?? 'info',
					'title'       => $rec['title'] ?? '',
					'description' => $rec['message'] ?? '',
					'action'      => $rec['action'] ?? '',
					'priority'    => $rec['priority'] ?? 'medium',
				);
			}
		}

		return array( 'recommendations' => $recommendations );
	}
}
