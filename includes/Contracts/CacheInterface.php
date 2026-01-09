<?php
/**
 * Cache Interface
 *
 * Contract for caching operations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface CacheInterface
 *
 * Defines the contract for caching operations. Implementations can use
 * WordPress transients, object cache, or in-memory storage for testing.
 *
 * @since 1.1.0
 */
interface CacheInterface {

	/**
	 * Retrieve a value from the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key     The cache key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed The cached value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Store a value in the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 * @param int    $ttl   Time to live in seconds. 0 means no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool;

	/**
	 * Delete a value from the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key The cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool;

	/**
	 * Check if a key exists in the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key The cache key.
	 * @return bool True if key exists, false otherwise.
	 */
	public function has( string $key ): bool;

	/**
	 * Clear all values from the cache, optionally filtered by prefix.
	 *
	 * @since 1.1.0
	 *
	 * @param string $prefix Optional prefix to filter keys.
	 * @return bool True on success, false on failure.
	 */
	public function clear( string $prefix = '' ): bool;

	/**
	 * Get or set a cached value.
	 *
	 * If the key exists in cache, return it. Otherwise, execute the callback,
	 * cache the result, and return it.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $key      The cache key.
	 * @param callable $callback Callback to generate the value if not cached.
	 * @param int      $ttl      Time to live in seconds.
	 * @return mixed The cached or generated value.
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 );

	/**
	 * Increment a numeric cached value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key   The cache key.
	 * @param int    $value Amount to increment by.
	 * @return int|false New value on success, false on failure.
	 */
	public function increment( string $key, int $value = 1 );

	/**
	 * Decrement a numeric cached value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key   The cache key.
	 * @param int    $value Amount to decrement by.
	 * @return int|false New value on success, false on failure.
	 */
	public function decrement( string $key, int $value = 1 );

	/**
	 * Get multiple values from the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param array $keys    Array of cache keys.
	 * @param mixed $default Default value for missing keys.
	 * @return array Associative array of key => value pairs.
	 */
	public function get_multiple( array $keys, $default = null ): array;

	/**
	 * Set multiple values in the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param array $values Associative array of key => value pairs.
	 * @param int   $ttl    Time to live in seconds.
	 * @return bool True if all values were set, false otherwise.
	 */
	public function set_multiple( array $values, int $ttl = 0 ): bool;

	/**
	 * Delete multiple values from the cache.
	 *
	 * @since 1.1.0
	 *
	 * @param array $keys Array of cache keys.
	 * @return bool True if all keys were deleted, false otherwise.
	 */
	public function delete_multiple( array $keys ): bool;
}
