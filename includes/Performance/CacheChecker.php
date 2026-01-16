<?php
/**
 * Cache Checker Class
 *
 * Provides object cache detection, performance testing, and recommendations.
 * Identifies cache backend type (Redis, Memcached, APCu, file-based, or none)
 * and provides actionable recommendations based on hosting environment.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Cache Checker class for object cache analysis and recommendations.
 *
 * @since 1.0.0
 */
class CacheChecker {

	/**
	 * Number of items to test in performance benchmark.
	 *
	 * @var int
	 */
	const BENCHMARK_ITEMS = 100;

	/**
	 * Cache key prefix for testing.
	 *
	 * @var string
	 */
	const TEST_KEY_PREFIX = 'wpha_cache_test_';

	/**
	 * Check if persistent cache is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if persistent cache is available.
	 */
	public function is_persistent_cache_available() {
		global $_wp_using_ext_object_cache;

		// Check if external object cache is in use.
		if ( ! empty( $_wp_using_ext_object_cache ) ) {
			return true;
		}

		// Additional checks for drop-in plugins.
		if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get comprehensive cache status information.
	 *
	 * @since 1.0.0
	 *
	 * @return array Cache status details.
	 */
	public function get_cache_status() {
		$status = array(
			'persistent_cache_available' => $this->is_persistent_cache_available(),
			'cache_type'                  => $this->detect_cache_type(),
			'cache_backend'               => $this->detect_cache_backend(),
			'object_cache_dropin'         => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
			'extensions_available'        => $this->get_available_extensions(),
			'hit_rate'                    => $this->get_cache_hit_rate(),
			'cache_info'                  => $this->get_cache_info(),
			'object_cache'                => $this->is_persistent_cache_available(),
			'page_cache'                  => $this->detect_page_cache(),
			'browser_cache'               => $this->detect_browser_cache_config(),
			'caching_plugins'             => $this->detect_caching_plugins(),
		);

		return $status;
	}

	/**
	 * Test cache performance with benchmark.
	 *
	 * @since 1.0.0
	 *
	 * @return array Performance test results.
	 */
	public function test_cache_performance() {
		$results = array(
			'test_items'       => self::BENCHMARK_ITEMS,
			'set_operations'   => array(),
			'get_operations'   => array(),
			'total_time_ms'    => 0,
			'avg_set_time_ms'  => 0,
			'avg_get_time_ms'  => 0,
			'operations_per_sec' => 0,
		);

		// Prepare test data.
		$test_data = array();
		for ( $i = 0; $i < self::BENCHMARK_ITEMS; $i++ ) {
			$test_data[] = array(
				'key'   => self::TEST_KEY_PREFIX . $i,
				'value' => 'test_value_' . wp_generate_password( 20, false ),
			);
		}

		// Measure SET operations.
		$set_start = microtime( true );
		foreach ( $test_data as $item ) {
			$item_start = microtime( true );
			wp_cache_set( $item['key'], $item['value'], 'wpha_benchmark', 300 );
			$item_time = ( microtime( true ) - $item_start ) * 1000;
			$results['set_operations'][] = $item_time;
		}
		$set_total = ( microtime( true ) - $set_start ) * 1000;

		// Measure GET operations.
		$get_start = microtime( true );
		$hits = 0;
		foreach ( $test_data as $item ) {
			$item_start = microtime( true );
			$value = wp_cache_get( $item['key'], 'wpha_benchmark' );
			$item_time = ( microtime( true ) - $item_start ) * 1000;
			$results['get_operations'][] = $item_time;

			if ( $item['value'] === $value ) {
				$hits++;
			}
		}
		$get_total = ( microtime( true ) - $get_start ) * 1000;

		// Clean up test data.
		foreach ( $test_data as $item ) {
			wp_cache_delete( $item['key'], 'wpha_benchmark' );
		}

		// Calculate statistics.
		$results['total_time_ms']      = round( $set_total + $get_total, 2 );
		$results['avg_set_time_ms']    = round( $set_total / self::BENCHMARK_ITEMS, 4 );
		$results['avg_get_time_ms']    = round( $get_total / self::BENCHMARK_ITEMS, 4 );
		$results['operations_per_sec'] = round( ( self::BENCHMARK_ITEMS * 2 ) / ( ( $set_total + $get_total ) / 1000 ), 2 );
		$results['hit_rate']           = round( ( $hits / self::BENCHMARK_ITEMS ) * 100, 2 );
		$results['cache_effective']    = self::BENCHMARK_ITEMS === $hits;

		return $results;
	}

	/**
	 * Get cache recommendations based on hosting environment.
	 *
	 * @since 1.0.0
	 *
	 * @return array Recommendations array.
	 */
	public function get_cache_recommendations() {
		$recommendations = array();
		$cache_status = $this->get_cache_status();
		$hosting_env = $this->detect_hosting_environment();

		// Check if persistent cache is already active.
		if ( $cache_status['persistent_cache_available'] ) {
			$recommendations[] = array(
				'type'     => 'success',
				'title'    => 'Persistent Object Cache Active',
				'message'  => sprintf(
					'Your site is using %s for object caching. This is optimal for performance.',
					$cache_status['cache_type']
				),
				'priority' => 'low',
			);

			// Add hit rate recommendation if available.
			if ( ! empty( $cache_status['hit_rate'] ) ) {
				if ( $cache_status['hit_rate'] < 70 ) {
					$recommendations[] = array(
						'type'     => 'warning',
						'title'    => 'Low Cache Hit Rate',
						'message'  => sprintf(
							'Cache hit rate is %.2f%%. Consider reviewing cache configuration and TTL settings.',
							$cache_status['hit_rate']
						),
						'priority' => 'medium',
					);
				}
			}
		} else {
			// No persistent cache - provide environment-specific recommendations.
			$recommendations[] = array(
				'type'     => 'warning',
				'title'    => 'No Persistent Object Cache',
				'message'  => 'Your site is not using persistent object caching, which can significantly impact performance.',
				'priority' => 'high',
			);

			// Add hosting-specific recommendations.
			$hosting_recommendations = $this->get_hosting_specific_recommendations( $hosting_env );
			$recommendations = array_merge( $recommendations, $hosting_recommendations );
		}

		// Check available extensions and suggest installation if needed.
		if ( ! $cache_status['persistent_cache_available'] ) {
			$available_extensions = $cache_status['extensions_available'];

			if ( ! empty( $available_extensions ) ) {
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Available Cache Extensions',
					'message'  => sprintf(
						'The following cache extensions are available: %s. Consider using a cache plugin to utilize them.',
						implode( ', ', $available_extensions )
					),
					'priority' => 'medium',
				);
			} else {
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'No Cache Extensions Available',
					'message'  => 'No cache extensions (Redis, Memcached, APCu) are installed. Contact your hosting provider to enable caching.',
					'priority' => 'medium',
				);
			}
		}

		return $recommendations;
	}

	/**
	 * Detect the type of cache in use.
	 *
	 * @return string Cache type name.
	 */
	private function detect_cache_type() {
		if ( ! $this->is_persistent_cache_available() ) {
			return 'No persistent cache';
		}

		$backend = $this->detect_cache_backend();

		switch ( $backend ) {
			case 'redis':
				return 'Redis';
			case 'memcached':
				return 'Memcached';
			case 'apcu':
				return 'APCu';
			case 'file':
				return 'File-based cache';
			default:
				return 'Unknown persistent cache';
		}
	}

	/**
	 * Detect the cache backend being used.
	 *
	 * @return string Backend identifier (redis, memcached, apcu, file, none).
	 */
	private function detect_cache_backend() {
		global $wp_object_cache;

		if ( ! is_object( $wp_object_cache ) ) {
			return $this->is_persistent_cache_available() ? 'file' : 'none';
		}

		$class_name = get_class( $wp_object_cache );

		// Check for Redis by class name.
		if ( stripos( $class_name, 'redis' ) !== false ) {
			return 'redis';
		}

		// Check for Redis by object properties (common in Redis Object Cache plugin).
		if ( property_exists( $wp_object_cache, 'redis' ) || property_exists( $wp_object_cache, 'redis_client' ) ) {
			return 'redis';
		}

		// Check for WP Redis plugin constants.
		if ( defined( 'WP_REDIS_VERSION' ) || defined( 'WP_REDIS_CLIENT' ) ) {
			return 'redis';
		}

		// Check for Memcached by class name.
		if ( stripos( $class_name, 'memcache' ) !== false ) {
			return 'memcached';
		}

		// Check for Memcached by object properties.
		if ( property_exists( $wp_object_cache, 'mc' ) || property_exists( $wp_object_cache, 'memcache' ) ) {
			return 'memcached';
		}

		// Check for APCu by class name.
		if ( stripos( $class_name, 'apcu' ) !== false || stripos( $class_name, 'apc' ) !== false ) {
			return 'apcu';
		}

		// Check for APCu by testing if it's using apcu functions.
		if ( function_exists( 'apcu_fetch' ) && method_exists( $wp_object_cache, '_exists' ) ) {
			// Some APCu implementations don't have clear class naming.
			// Check the object-cache.php drop-in file content if available.
			$dropin_path = WP_CONTENT_DIR . '/object-cache.php';
			if ( file_exists( $dropin_path ) && is_readable( $dropin_path ) ) {
				$content = file_get_contents( $dropin_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== $content && preg_match( '/apcu_/i', $content ) && ! preg_match( '/redis|memcache/i', $content ) ) {
					return 'apcu';
				}
			}
		}

		// If persistent cache is available but we couldn't detect the type, it's likely file-based.
		if ( $this->is_persistent_cache_available() ) {
			return 'file';
		}

		return 'none';
	}

	/**
	 * Get available cache extensions.
	 *
	 * @return array List of available extensions.
	 */
	private function get_available_extensions() {
		$extensions = array();

		if ( class_exists( 'Redis' ) || class_exists( 'Predis\Client' ) ) {
			$extensions[] = 'Redis';
		}

		if ( class_exists( 'Memcached' ) ) {
			$extensions[] = 'Memcached';
		}

		if ( class_exists( 'Memcache' ) ) {
			$extensions[] = 'Memcache';
		}

		if ( function_exists( 'apcu_fetch' ) && function_exists( 'apcu_store' ) ) {
			$extensions[] = 'APCu';
		}

		return $extensions;
	}

	/**
	 * Get cache hit rate if available.
	 *
	 * @return float|null Hit rate percentage or null if not available.
	 */
	private function get_cache_hit_rate() {
		global $wp_object_cache;

		// Try to get hit rate from cache object if available.
		if ( is_object( $wp_object_cache ) ) {
			// Redis Object Cache plugin.
			if ( method_exists( $wp_object_cache, 'info' ) ) {
				$info = $wp_object_cache->info();
				if ( isset( $info['hits'] ) && isset( $info['misses'] ) ) {
					$total = $info['hits'] + $info['misses'];
					if ( $total > 0 ) {
						return round( ( $info['hits'] / $total ) * 100, 2 );
					}
				}
			}

			// Check for cache_hits and cache_misses properties.
			if ( isset( $wp_object_cache->cache_hits ) && isset( $wp_object_cache->cache_misses ) ) {
				$total = $wp_object_cache->cache_hits + $wp_object_cache->cache_misses;
				if ( $total > 0 ) {
					return round( ( $wp_object_cache->cache_hits / $total ) * 100, 2 );
				}
			}
		}

		return null;
	}

	/**
	 * Get additional cache information.
	 *
	 * @return array Cache information.
	 */
	private function get_cache_info() {
		global $wp_object_cache;

		$info = array(
			'class' => is_object( $wp_object_cache ) ? get_class( $wp_object_cache ) : 'N/A',
		);

		// Try to get additional info from Redis Object Cache.
		if ( is_object( $wp_object_cache ) && method_exists( $wp_object_cache, 'info' ) ) {
			$cache_info = $wp_object_cache->info();
			if ( is_array( $cache_info ) ) {
				$info = array_merge( $info, $cache_info );
			}
		}

		return $info;
	}

	/**
	 * Detect hosting environment.
	 *
	 * @return string Hosting environment identifier.
	 */
	private function detect_hosting_environment() {
		// Check for common hosting providers via constants and server variables.
		if ( defined( 'WPE_APIKEY' ) ) {
			return 'wpengine';
		}

		if ( defined( 'KINSTA_CACHE_ZONE' ) ) {
			return 'kinsta';
		}

		if ( defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
			return 'flywheel';
		}

		if ( defined( 'PANTHEON_ENVIRONMENT' ) ) {
			return 'pantheon';
		}

		if ( defined( 'VIP_GO_ENV' ) ) {
			return 'wordpress_vip';
		}

		if ( isset( $_SERVER['PRESSABLE_PROXIED_REQUEST'] ) ) {
			return 'pressable';
		}

		if ( defined( 'GD_SYSTEM_PLUGIN_DIR' ) ) {
			return 'godaddy';
		}

		if ( defined( 'IS_ATOMIC' ) && IS_ATOMIC ) {
			return 'wordpress_com';
		}

		// Check for SiteGround.
		if ( class_exists( 'SiteGround_Optimizer\Loader' ) ) {
			return 'siteground';
		}

		// Check for Cloudways.
		if ( defined( 'CLOUDWAYS_APP_ID' ) || isset( $_SERVER['cw_allowed_ip'] ) ) {
			return 'cloudways';
		}

		return 'generic';
	}

	/**
	 * Get hosting-specific cache recommendations.
	 *
	 * @param string $hosting_env Hosting environment identifier.
	 * @return array Recommendations.
	 */
	private function get_hosting_specific_recommendations( $hosting_env ) {
		$recommendations = array();

		switch ( $hosting_env ) {
			case 'wpengine':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'WP Engine Hosting',
					'message'  => 'WP Engine provides built-in object caching. Contact support to enable Redis for your site.',
					'priority' => 'medium',
					'action'   => 'Contact WP Engine support to enable Redis object caching.',
				);
				break;

			case 'kinsta':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Kinsta Hosting',
					'message'  => 'Kinsta includes Redis object caching on all plans. Enable it from your MyKinsta dashboard.',
					'priority' => 'medium',
					'action'   => 'Go to MyKinsta > Sites > [Your Site] > Tools > Redis and click "Enable".',
				);
				break;

			case 'flywheel':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Flywheel Hosting',
					'message'  => 'Flywheel provides built-in caching. Contact support for advanced object caching options.',
					'priority' => 'medium',
					'action'   => 'Contact Flywheel support for Redis object caching availability.',
				);
				break;

			case 'pantheon':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Pantheon Hosting',
					'message'  => 'Pantheon includes Redis object caching. Install the WP Redis plugin to use it.',
					'priority' => 'medium',
					'action'   => 'Install and activate the WP Redis plugin from the WordPress repository.',
				);
				break;

			case 'wordpress_vip':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'WordPress VIP',
					'message'  => 'WordPress VIP includes built-in object caching that should be active by default.',
					'priority' => 'medium',
					'action'   => 'Contact VIP support if object caching is not working correctly.',
				);
				break;

			case 'pressable':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Pressable Hosting',
					'message'  => 'Pressable includes Redis object caching. Contact support to enable it for your site.',
					'priority' => 'medium',
					'action'   => 'Contact Pressable support to enable Redis object caching.',
				);
				break;

			case 'siteground':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'SiteGround Hosting',
					'message'  => 'SiteGround offers Memcached object caching. Enable it via the SG Optimizer plugin.',
					'priority' => 'medium',
					'action'   => 'Install SG Optimizer plugin and enable Memcached from the plugin settings.',
				);
				break;

			case 'cloudways':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Cloudways Hosting',
					'message'  => 'Cloudways includes Redis and Memcached. Enable object caching from the Cloudways dashboard.',
					'priority' => 'medium',
					'action'   => 'Enable Redis from Application Management > Application Settings > Advanced.',
				);
				break;

			case 'godaddy':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'GoDaddy Hosting',
					'message'  => 'GoDaddy managed WordPress hosting includes built-in caching. Upgrade to higher plans for better performance.',
					'priority' => 'low',
					'action'   => 'Consider upgrading to a plan with more advanced caching features.',
				);
				break;

			case 'wordpress_com':
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'WordPress.com Hosting',
					'message'  => 'WordPress.com includes built-in object caching on Business and eCommerce plans.',
					'priority' => 'low',
					'action'   => 'Object caching should be automatically enabled. No action needed.',
				);
				break;

			default:
				$recommendations[] = array(
					'type'     => 'info',
					'title'    => 'Generic Hosting Recommendation',
					'message'  => 'Install a Redis or Memcached object cache plugin to improve performance.',
					'priority' => 'medium',
					'action'   => 'Popular options: Redis Object Cache, W3 Total Cache, or WP Rocket.',
				);

				// Suggest based on available extensions.
				$available = $this->get_available_extensions();
				if ( in_array( 'Redis', $available, true ) ) {
					$recommendations[] = array(
						'type'     => 'info',
						'title'    => 'Redis Available',
						'message'  => 'Redis extension is installed. Install "Redis Object Cache" plugin to use it.',
						'priority' => 'medium',
						'action'   => 'Install and activate the Redis Object Cache plugin from WordPress.org.',
					);
				} elseif ( in_array( 'Memcached', $available, true ) ) {
					$recommendations[] = array(
						'type'     => 'info',
						'title'    => 'Memcached Available',
						'message'  => 'Memcached extension is installed. Install a compatible object cache plugin.',
						'priority' => 'medium',
						'action'   => 'Install W3 Total Cache or similar plugin that supports Memcached.',
					);
				} elseif ( in_array( 'APCu', $available, true ) ) {
					$recommendations[] = array(
						'type'     => 'info',
						'title'    => 'APCu Available',
						'message'  => 'APCu is available but limited to single-server setups. Consider Redis for better scalability.',
						'priority' => 'low',
						'action'   => 'Install an APCu-compatible object cache plugin or request Redis from your host.',
					);
				}
				break;
		}

		return $recommendations;
	}

	/**
	 * Detect if page caching is enabled.
	 *
	 * Checks for common page caching configurations and plugins.
	 *
	 * @since 1.0.0
	 *
	 * @return array Page cache detection results.
	 */
	private function detect_page_cache() {
		$result = array(
			'enabled' => false,
			'type'    => 'none',
			'plugin'  => null,
		);

		// Check for WP_CACHE constant (used by most page cache plugins).
		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			$result['enabled'] = true;
		}

		// Check for advanced-cache.php drop-in.
		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			$result['enabled'] = true;
			$result['type']    = 'drop-in';
		}

		// Detect specific page cache plugins.
		$page_cache_plugins = $this->detect_page_cache_plugins();
		if ( ! empty( $page_cache_plugins ) ) {
			$result['enabled'] = true;
			$result['plugin']  = $page_cache_plugins[0]; // Primary detected plugin.
			$result['type']    = 'plugin';
		}

		// Check for server-level caching indicators.
		$server_cache = $this->detect_server_level_cache();
		if ( $server_cache ) {
			$result['enabled']      = true;
			$result['type']         = 'server';
			$result['server_cache'] = $server_cache;
		}

		return $result;
	}

	/**
	 * Detect page cache plugins.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of detected page cache plugins.
	 */
	private function detect_page_cache_plugins() {
		$plugins = array();

		// WP Super Cache.
		if ( defined( 'WPCACHEHOME' ) || function_exists( 'wp_cache_serve_cache_file' ) ) {
			$plugins[] = 'WP Super Cache';
		}

		// W3 Total Cache.
		if ( defined( 'W3TC' ) || class_exists( 'W3_Plugin_TotalCache' ) || class_exists( 'W3TC\Util_Environment' ) ) {
			$plugins[] = 'W3 Total Cache';
		}

		// WP Rocket.
		if ( defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_init' ) ) {
			$plugins[] = 'WP Rocket';
		}

		// LiteSpeed Cache.
		if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed_Cache' ) || class_exists( 'LiteSpeed\Core' ) ) {
			$plugins[] = 'LiteSpeed Cache';
		}

		// WP Fastest Cache.
		if ( class_exists( 'WpFastestCache' ) || defined( 'WPFC_MAIN_PATH' ) ) {
			$plugins[] = 'WP Fastest Cache';
		}

		// Cache Enabler.
		if ( class_exists( 'Cache_Enabler' ) || defined( 'CACHE_ENABLER_VERSION' ) ) {
			$plugins[] = 'Cache Enabler';
		}

		// Autoptimize (has page cache feature).
		if ( class_exists( 'autoptimizeCache' ) && defined( 'AUTOPTIMIZE_CACHE_DIR' ) ) {
			$plugins[] = 'Autoptimize';
		}

		// Breeze (Cloudways).
		if ( defined( 'BREEZE_VERSION' ) || class_exists( 'Breeze_Configuration' ) ) {
			$plugins[] = 'Breeze';
		}

		// Hummingbird.
		if ( class_exists( 'WP_Hummingbird' ) || defined( 'WPHB_VERSION' ) ) {
			$plugins[] = 'Hummingbird';
		}

		// Swift Performance.
		if ( class_exists( 'Swift_Performance' ) || defined( 'STARTER_VERSION' ) ) {
			$plugins[] = 'Swift Performance';
		}

		// Comet Cache.
		if ( class_exists( 'WebSharks\\CometCache\\Classes\\Plugin' ) || defined( 'COMET_CACHE_VERSION' ) ) {
			$plugins[] = 'Comet Cache';
		}

		// SG Optimizer.
		if ( class_exists( 'SiteGround_Optimizer\\Loader\\Loader' ) || defined( 'SiteGround_Optimizer\\VERSION' ) ) {
			$plugins[] = 'SG Optimizer';
		}

		// Powered Cache.
		if ( defined( 'POWERED_CACHE_VERSION' ) || class_exists( 'PoweredCache\\Core' ) ) {
			$plugins[] = 'Powered Cache';
		}

		// NitroPack.
		if ( defined( 'NITROPACK_VERSION' ) || class_exists( 'NitroPack\\SDK\\NitroPack' ) ) {
			$plugins[] = 'NitroPack';
		}

		return $plugins;
	}

	/**
	 * Detect server-level caching.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null Server cache type or null if not detected.
	 */
	private function detect_server_level_cache() {
		// Check for Varnish.
		if ( isset( $_SERVER['HTTP_X_VARNISH'] ) ) {
			return 'Varnish';
		}

		// Check for Varnish via HTTP_VIA header.
		if ( isset( $_SERVER['HTTP_VIA'] ) ) {
			$http_via = sanitize_text_field( wp_unslash( $_SERVER['HTTP_VIA'] ) );
			if ( false !== strpos( $http_via, 'varnish' ) ) {
				return 'Varnish';
			}
		}

		// Check for nginx FastCGI cache.
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_software = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
			if ( false !== strpos( $server_software, 'nginx' ) ) {
				// Check for common nginx cache headers in response.
				if ( isset( $_SERVER['HTTP_X_FASTCGI_CACHE'] ) || isset( $_SERVER['HTTP_X_CACHE_STATUS'] ) ) {
					return 'nginx FastCGI';
				}
			}

			// Check for LiteSpeed server.
			if ( false !== strpos( $server_software, 'LiteSpeed' ) ) {
				return 'LiteSpeed';
			}
		}

		// Check for Cloudflare.
		if ( isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return 'Cloudflare';
		}

		// Check for Sucuri.
		if ( isset( $_SERVER['HTTP_X_SUCURI_ID'] ) || isset( $_SERVER['HTTP_X_SUCURI_CACHE'] ) ) {
			return 'Sucuri';
		}

		return null;
	}

	/**
	 * Detect browser cache configuration.
	 *
	 * Checks for common browser caching configurations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Browser cache configuration status.
	 */
	private function detect_browser_cache_config() {
		$result = array(
			'configured'           => false,
			'htaccess_rules'       => false,
			'web_config_rules'     => false,
			'plugin_managed'       => false,
			'cache_control_header' => null,
			'expires_header'       => null,
		);

		// Check .htaccess for browser caching rules (Apache).
		$htaccess_path = ABSPATH . '.htaccess';
		if ( file_exists( $htaccess_path ) && is_readable( $htaccess_path ) ) {
			$htaccess_content = file_get_contents( $htaccess_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $htaccess_content ) {
				// Check for mod_expires rules.
				if ( preg_match( '/ExpiresActive\s+On/i', $htaccess_content ) ) {
					$result['htaccess_rules'] = true;
					$result['configured']     = true;
				}

				// Check for Cache-Control headers.
				if ( preg_match( '/Header\s+set\s+Cache-Control/i', $htaccess_content ) ) {
					$result['htaccess_rules'] = true;
					$result['configured']     = true;
				}

				// Check for mod_headers Expires.
				if ( preg_match( '/Header\s+set\s+Expires/i', $htaccess_content ) ) {
					$result['htaccess_rules'] = true;
					$result['configured']     = true;
				}
			}
		}

		// Check web.config for IIS browser caching rules.
		$web_config_path = ABSPATH . 'web.config';
		if ( file_exists( $web_config_path ) && is_readable( $web_config_path ) ) {
			$web_config_content = file_get_contents( $web_config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $web_config_content ) {
				// Check for clientCache element.
				if ( preg_match( '/<clientCache/i', $web_config_content ) ) {
					$result['web_config_rules'] = true;
					$result['configured']       = true;
				}

				// Check for custom headers.
				if ( preg_match( '/<customHeaders/i', $web_config_content ) && preg_match( '/Cache-Control|Expires/i', $web_config_content ) ) {
					$result['web_config_rules'] = true;
					$result['configured']       = true;
				}
			}
		}

		// Check if a caching plugin is likely managing browser cache.
		$caching_plugins = $this->detect_caching_plugins();
		if ( ! empty( $caching_plugins ) ) {
			// Most caching plugins handle browser caching.
			$plugins_with_browser_cache = array(
				'WP Rocket',
				'W3 Total Cache',
				'LiteSpeed Cache',
				'WP Super Cache',
				'WP Fastest Cache',
				'Autoptimize',
				'Breeze',
				'Hummingbird',
				'SG Optimizer',
			);

			foreach ( $caching_plugins as $plugin ) {
				if ( in_array( $plugin, $plugins_with_browser_cache, true ) ) {
					$result['plugin_managed'] = true;
					$result['configured']     = true;
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Detect installed and active caching plugins.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of detected caching plugins with their capabilities.
	 */
	private function detect_caching_plugins() {
		$plugins = array();

		// Redis Object Cache.
		if ( class_exists( 'Rhubarb\\RedisCache\\Plugin' ) || defined( 'WP_REDIS_VERSION' ) ) {
			$plugins[] = 'Redis Object Cache';
		}

		// Object Cache Pro.
		if ( class_exists( 'ObjectCachePro\\Plugin' ) || defined( 'RedisCachePro\\Version' ) ) {
			$plugins[] = 'Object Cache Pro';
		}

		// Include page cache plugins.
		$page_cache_plugins = $this->detect_page_cache_plugins();
		$plugins            = array_merge( $plugins, $page_cache_plugins );

		return array_unique( $plugins );
	}
}
