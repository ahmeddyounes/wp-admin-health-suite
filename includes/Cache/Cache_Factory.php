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
 * Class Cache_Factory
 *
 * Factory class for creating cache instances based on environment.
 * Automatically selects the best available cache backend.
 *
 * @since 1.1.0
 */
class Cache_Factory {

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
		if ( Object_Cache::is_available() ) {
			return new Object_Cache( $prefix );
		}

		// Fallback to transients.
		return new Transient_Cache( $prefix );
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
	 * Create an object cache instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $group Cache group name.
	 * @return Object_Cache Object cache instance.
	 */
	public static function create_object_cache( string $group = 'wpha' ): Object_Cache {
		return new Object_Cache( $group );
	}

	/**
	 * Create a transient cache instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prefix Transient key prefix.
	 * @return Transient_Cache Transient cache instance.
	 */
	public static function create_transient_cache( string $prefix = 'wpha_' ): Transient_Cache {
		return new Transient_Cache( $prefix );
	}

	/**
	 * Create an in-memory cache instance.
	 *
	 * Useful for testing.
	 *
	 * @since 1.1.0
	 *
	 * @return Memory_Cache Memory cache instance.
	 */
	public static function create_memory_cache(): Memory_Cache {
		return new Memory_Cache();
	}

	/**
	 * Create a null cache instance.
	 *
	 * Useful for testing scenarios where caching should be disabled.
	 *
	 * @since 1.1.0
	 *
	 * @return Null_Cache Null cache instance.
	 */
	public static function create_null_cache(): Null_Cache {
		return new Null_Cache();
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
	 * Check which cache backend is being used.
	 *
	 * @since 1.1.0
	 *
	 * @return string Cache backend name: 'object', 'transient', 'memory', or 'null'.
	 */
	public static function get_backend_type(): string {
		$cache = self::get_instance();

		if ( $cache instanceof Object_Cache ) {
			return 'object';
		}

		if ( $cache instanceof Transient_Cache ) {
			return 'transient';
		}

		if ( $cache instanceof Memory_Cache ) {
			return 'memory';
		}

		if ( $cache instanceof Null_Cache ) {
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
		return Object_Cache::is_available();
	}
}
