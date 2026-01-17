<?php
/**
 * Memory Cache Implementation
 *
 * In-memory cache implementation for request lifecycle caching and testing.
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
 * Class MemoryCache
 *
 * Implements caching using in-memory array storage.
 * Ideal for unit testing where cache state needs to be inspectable,
 * or for request-scoped caching where persistence is not needed.
 *
 * Features:
 * - Configurable maximum item limit to prevent memory exhaustion
 * - Automatic LRU eviction when limit is reached
 * - Expired entry cleanup on access and via explicit garbage collection
 * - Statistics tracking for monitoring cache behavior
 *
 * @since 1.1.0
 */
class MemoryCache implements CacheInterface {

	/**
	 * Default maximum items in cache.
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_ITEMS = 1000;

	/**
	 * In-memory storage.
	 *
	 * @var array<string, array{value: mixed, expires: int, accessed: int}>
	 */
	private array $storage = array();

	/**
	 * Maximum number of items allowed in cache.
	 *
	 * @var int
	 */
	private int $max_items;

	/**
	 * Track cache statistics.
	 *
	 * @var array{hits: int, misses: int, writes: int, deletes: int, evictions: int}
	 */
	private array $stats = array(
		'hits'      => 0,
		'misses'    => 0,
		'writes'    => 0,
		'deletes'   => 0,
		'evictions' => 0,
	);

	/**
	 * Time provider callable for testing.
	 *
	 * @var callable|null
	 */
	private $time_provider = null;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param int $max_items Maximum number of items to store (0 = unlimited).
	 */
	public function __construct( int $max_items = self::DEFAULT_MAX_ITEMS ) {
		$this->max_items = $max_items;
	}

	/**
	 * Get the current timestamp.
	 *
	 * Uses the injected time provider if available, otherwise uses time().
	 *
	 * @return int Current timestamp.
	 */
	private function get_current_time(): int {
		if ( null !== $this->time_provider ) {
			return call_user_func( $this->time_provider );
		}
		return time();
	}

	/**
	 * Set a custom time provider for testing.
	 *
	 * @since 1.1.0
	 *
	 * @param callable|null $provider Callable that returns a timestamp, or null to reset.
	 * @return void
	 */
	public function set_time_provider( ?callable $provider ): void {
		$this->time_provider = $provider;
	}

	/**
	 * Check if a key has expired.
	 *
	 * Note: This method does not have side effects. Use remove_if_expired()
	 * if you need to clean up expired entries.
	 *
	 * @param string $key Cache key.
	 * @return bool True if expired or not found.
	 */
	private function is_expired( string $key ): bool {
		if ( ! isset( $this->storage[ $key ] ) ) {
			return true;
		}

		$expires = $this->storage[ $key ]['expires'];

		if ( 0 === $expires ) {
			return false; // No expiration.
		}

		return $this->get_current_time() >= $expires;
	}

	/**
	 * Remove an entry if it has expired.
	 *
	 * @param string $key Cache key.
	 * @return bool True if the entry was removed (was expired), false otherwise.
	 */
	private function remove_if_expired( string $key ): bool {
		if ( $this->is_expired( $key ) && isset( $this->storage[ $key ] ) ) {
			unset( $this->storage[ $key ] );
			return true;
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		// Clean up expired entry if needed.
		$this->remove_if_expired( $key );

		if ( ! isset( $this->storage[ $key ] ) ) {
			++$this->stats['misses'];
			return $default;
		}

		++$this->stats['hits'];

		// Update access time for LRU tracking.
		$this->storage[ $key ]['accessed'] = $this->get_current_time();

		return $this->storage[ $key ]['value'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		$current_time = $this->get_current_time();

		// If key already exists, just update it (no eviction needed).
		if ( isset( $this->storage[ $key ] ) ) {
			$this->storage[ $key ] = array(
				'value'    => $value,
				'expires'  => $ttl > 0 ? $current_time + $ttl : 0,
				'accessed' => $current_time,
			);
			++$this->stats['writes'];
			return true;
		}

		// Check if we need to evict items before adding new one.
		$this->evict_if_needed();

		$this->storage[ $key ] = array(
			'value'    => $value,
			'expires'  => $ttl > 0 ? $current_time + $ttl : 0,
			'accessed' => $current_time,
		);

		++$this->stats['writes'];
		return true;
	}

	/**
	 * Evict items if the cache is at capacity.
	 *
	 * Uses LRU (Least Recently Used) eviction strategy.
	 * First removes expired items, then evicts least recently accessed items.
	 *
	 * @return void
	 */
	private function evict_if_needed(): void {
		// No limit set, skip eviction.
		if ( 0 === $this->max_items ) {
			return;
		}

		// Cache is not at capacity.
		if ( count( $this->storage ) < $this->max_items ) {
			return;
		}

		// First, try to free space by removing expired items.
		$this->gc();

		// If still at capacity, evict LRU items.
		while ( count( $this->storage ) >= $this->max_items ) {
			$this->evict_lru();
		}
	}

	/**
	 * Evict the least recently used item.
	 *
	 * @return bool True if an item was evicted, false if cache is empty.
	 */
	private function evict_lru(): bool {
		if ( empty( $this->storage ) ) {
			return false;
		}

		$oldest_key  = null;
		$oldest_time = PHP_INT_MAX;

		foreach ( $this->storage as $key => $entry ) {
			$accessed = $entry['accessed'] ?? 0;
			if ( $accessed < $oldest_time ) {
				$oldest_time = $accessed;
				$oldest_key  = $key;
			}
		}

		if ( null !== $oldest_key ) {
			unset( $this->storage[ $oldest_key ] );
			++$this->stats['evictions'];
			return true;
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $key ): bool {
		if ( isset( $this->storage[ $key ] ) ) {
			unset( $this->storage[ $key ] );
			++$this->stats['deletes'];
			return true;
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		// Clean up expired entry if needed and return false.
		if ( $this->remove_if_expired( $key ) ) {
			return false;
		}
		return isset( $this->storage[ $key ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear( string $prefix = '' ): bool {
		if ( empty( $prefix ) ) {
			$this->storage = array();
			return true;
		}

		foreach ( array_keys( $this->storage ) as $key ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				unset( $this->storage[ $key ] );
			}
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ) {
		// Clean up expired entry if needed.
		$this->remove_if_expired( $key );

		if ( isset( $this->storage[ $key ] ) ) {
			++$this->stats['hits'];

			// Update access time for LRU tracking.
			$this->storage[ $key ]['accessed'] = $this->get_current_time();

			return $this->storage[ $key ]['value'];
		}

		++$this->stats['misses'];
		$value = $callback();
		$this->set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function increment( string $key, int $value = 1 ) {
		// Clean up expired entry if needed.
		$this->remove_if_expired( $key );

		if ( ! isset( $this->storage[ $key ] ) ) {
			$this->set( $key, $value );
			return $value;
		}

		$current = $this->storage[ $key ]['value'];

		if ( ! is_numeric( $current ) ) {
			return false;
		}

		$new_value                        = (int) $current + $value;
		$this->storage[ $key ]['value']   = $new_value;
		$this->storage[ $key ]['accessed'] = $this->get_current_time();

		return $new_value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function decrement( string $key, int $value = 1 ) {
		return $this->increment( $key, -$value );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_multiple( array $keys, $default = null ): array {
		$results = array();

		foreach ( $keys as $key ) {
			$results[ $key ] = $this->get( $key, $default );
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_multiple( array $values, int $ttl = 0 ): bool {
		foreach ( $values as $key => $value ) {
			$this->set( $key, $value, $ttl );
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_multiple( array $keys ): bool {
		foreach ( $keys as $key ) {
			$this->delete( $key );
		}

		return true;
	}

	/**
	 * Get cache statistics.
	 *
	 * Useful for testing to verify cache behavior.
	 *
	 * @return array{hits: int, misses: int, writes: int, deletes: int, evictions: int} Cache stats.
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Reset cache statistics.
	 *
	 * @return void
	 */
	public function reset_stats(): void {
		$this->stats = array(
			'hits'      => 0,
			'misses'    => 0,
			'writes'    => 0,
			'deletes'   => 0,
			'evictions' => 0,
		);
	}

	/**
	 * Get all stored keys.
	 *
	 * Useful for testing to inspect cache state.
	 *
	 * @return array<string> Array of cache keys.
	 */
	public function get_keys(): array {
		return array_keys( $this->storage );
	}

	/**
	 * Get the number of items in cache.
	 *
	 * @return int Number of cached items.
	 */
	public function count(): int {
		return count( $this->storage );
	}

	/**
	 * Get raw storage for debugging.
	 *
	 * @return array<string, array{value: mixed, expires: int, accessed: int}> Raw storage.
	 */
	public function get_storage(): array {
		return $this->storage;
	}

	/**
	 * Perform garbage collection to remove all expired entries.
	 *
	 * This method proactively removes expired entries from the cache,
	 * freeing up memory without waiting for access-based cleanup.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of expired items removed.
	 */
	public function gc(): int {
		$removed      = 0;
		$current_time = $this->get_current_time();

		foreach ( $this->storage as $key => $entry ) {
			// Skip entries with no expiration.
			if ( 0 === $entry['expires'] ) {
				continue;
			}

			// Remove expired entries.
			if ( $current_time >= $entry['expires'] ) {
				unset( $this->storage[ $key ] );
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Completely flush the cache including statistics.
	 *
	 * Unlike clear(), this also resets all statistics to provide
	 * a complete reset of the cache state.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->storage = array();
		$this->reset_stats();
	}

	/**
	 * Get the maximum items limit.
	 *
	 * @since 1.1.0
	 *
	 * @return int Maximum items (0 = unlimited).
	 */
	public function get_max_items(): int {
		return $this->max_items;
	}

	/**
	 * Set the maximum items limit.
	 *
	 * If reducing the limit below the current count, excess items
	 * will be evicted using the LRU strategy.
	 *
	 * @since 1.1.0
	 *
	 * @param int $max_items Maximum items (0 = unlimited).
	 * @return void
	 */
	public function set_max_items( int $max_items ): void {
		$this->max_items = $max_items;

		// Evict items if new limit is exceeded.
		if ( $max_items > 0 ) {
			while ( count( $this->storage ) > $max_items ) {
				$this->evict_lru();
			}
		}
	}
}
