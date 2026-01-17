<?php
/**
 * Cache Factory
 *
 * Factory for creating cache instances.
 *
 * @package WPAdminHealth\Cache
 */

namespace WPAdminHealth\Cache;

use WPAdminHealth\Contracts\CacheInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CacheFactory
 *
 * Factory class for creating cache instances based on environment.
 * Automatically selects the best available cache backend.
 *
 * @since 1.1.0
 */
class CacheFactory {

	/**
	 * Default cache group/prefix.
	 *
	 * @var string
	 */
	private static string $default_prefix = 'wpha_';

	/**
	 * Cached instance for singleton-like usage.
	 *
	 * @var CacheInterface|null
	 */
	private static ?CacheInterface $instance = null;

	/**
	 * Create a cache instance based on environment.
	 *
	 * Automatically detects the best available cache backend:
	 * 1. Object cache if persistent object caching is available
	 * 2. Transient cache as fallback
	 *
	 * @since 1.1.0
	 *
	 * @param string|null $prefix Optional custom prefix/group.
	 * @return CacheInterface Cache instance.
	 */
	public static function create( ?string $prefix = null ): CacheInterface {
		$prefix = $prefix ?? self::$default_prefix;

		// Use object cache if persistent caching is available.
		if ( ObjectCache::is_available() ) {
			return new ObjectCache( $prefix );
		}

		// Fallback to transients.
		return new TransientCache( $prefix );
	}

	/**
	 * Get or create a singleton cache instance.
	 *
	 * @since 1.1.0
	 *
	 * @return CacheInterface Cache instance.
	 */
	public static function get_instance(): CacheInterface {
		if ( null === self::$instance ) {
			self::$instance = self::create();
		}

		return self::$instance;
	}

	/**
	 * Set the singleton instance.
	 *
	 * Useful for testing to inject a mock cache.
	 *
	 * @since 1.1.0
	 *
	 * @param CacheInterface|null $instance Cache instance or null to reset.
	 * @return void
	 */
	public static function set_instance( ?CacheInterface $instance ): void {
		self::$instance = $instance;
	}

	/**
	 * Reset the singleton instance.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Reset all static state to defaults.
	 *
	 * Useful for testing to ensure clean state between tests.
	 * Resets both the singleton instance and the default prefix.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function reset_all(): void {
		self::$instance       = null;
		self::$default_prefix = 'wpha_';
	}

	/**
	 * Create an object cache instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $group Cache group name.
	 * @return ObjectCache Object cache instance.
	 */
	public static function create_object_cache( string $group = 'wpha_' ): ObjectCache {
		return new ObjectCache( $group );
	}

	/**
	 * Create a transient cache instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prefix Transient key prefix.
	 * @return TransientCache Transient cache instance.
	 */
	public static function create_transient_cache( string $prefix = 'wpha_' ): TransientCache {
		return new TransientCache( $prefix );
	}

	/**
	 * Create an in-memory cache instance.
	 *
	 * Useful for testing.
	 *
	 * @since 1.1.0
	 *
	 * @return MemoryCache Memory cache instance.
	 */
	public static function create_memory_cache(): MemoryCache {
		return new MemoryCache();
	}

	/**
	 * Create a null cache instance.
	 *
	 * Useful for testing scenarios where caching should be disabled.
	 *
	 * @since 1.1.0
	 *
	 * @return NullCache Null cache instance.
	 */
	public static function create_null_cache(): NullCache {
		return new NullCache();
	}

	/**
	 * Set the default prefix for new cache instances.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prefix New default prefix.
	 * @return void
	 */
	public static function set_default_prefix( string $prefix ): void {
		self::$default_prefix = $prefix;
	}

	/**
	 * Get the current default prefix.
	 *
	 * @since 1.1.0
	 *
	 * @return string Current default prefix.
	 */
	public static function get_default_prefix(): string {
		return self::$default_prefix;
	}

	/**
	 * Check which cache backend is being used by the singleton.
	 *
	 * Note: This method returns 'none' if no singleton has been created yet,
	 * avoiding the side effect of creating one. Use get_instance() first if
	 * you need to ensure a cache exists.
	 *
	 * @since 1.1.0
	 *
	 * @return string Cache backend name: 'object', 'transient', 'memory', 'null', or 'none'.
	 */
	public static function get_backend_type(): string {
		// Return 'none' if no instance exists to avoid side effects.
		if ( null === self::$instance ) {
			return 'none';
		}

		if ( self::$instance instanceof ObjectCache ) {
			return 'object';
		}

		if ( self::$instance instanceof TransientCache ) {
			return 'transient';
		}

		if ( self::$instance instanceof MemoryCache ) {
			return 'memory';
		}

		if ( self::$instance instanceof NullCache ) {
			return 'null';
		}

		return 'unknown';
	}

	/**
	 * Check if persistent object cache is available.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if persistent object cache is available.
	 */
	public static function has_persistent_cache(): bool {
		return ObjectCache::is_available();
	}
}
