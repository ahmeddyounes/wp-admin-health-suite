<?php
/**
 * Get Cache Status Use Case
 *
 * Application service for cache status data.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetCacheStatus
 *
 * @since 1.7.0
 */
class GetCacheStatus {

	/**
	 * Execute cache status retrieval.
	 *
	 * @since 1.7.0
	 *
	 * @return array
	 */
	public function execute(): array {
		$object_cache = wp_using_ext_object_cache();
		$opcache_status = function_exists( 'opcache_get_status' ) ? opcache_get_status( false ) : false;

		$cache_info = array(
			'object_cache_enabled' => (bool) $object_cache,
			'cache_type'           => $object_cache ? $this->get_cache_type() : 'none',
			'opcache_enabled'      => (bool) $opcache_status,
		);

		if ( $opcache_status && isset( $opcache_status['opcache_statistics'], $opcache_status['memory_usage'] ) ) {
			$cache_info['opcache_stats'] = array(
				'hit_rate'       => $opcache_status['opcache_statistics']['opcache_hit_rate'],
				'memory_usage'   => $opcache_status['memory_usage']['used_memory'],
				'cached_scripts' => $opcache_status['opcache_statistics']['num_cached_scripts'],
			);
		}

		return $cache_info;
	}

	/**
	 * Get cache type (best-effort).
	 *
	 * @since 1.7.0
	 */
	private function get_cache_type(): string {
		global $wp_object_cache;

		if ( isset( $wp_object_cache ) && is_object( $wp_object_cache ) ) {
			$class = get_class( $wp_object_cache );

			if ( strpos( $class, 'Redis' ) !== false ) {
				return 'Redis';
			}

			if ( strpos( $class, 'Memcached' ) !== false ) {
				return 'Memcached';
			}

			if ( strpos( $class, 'APCu' ) !== false ) {
				return 'APCu';
			}
		}

		return 'Unknown';
	}
}
