<?php
/**
 * Memory Cache Implementation
 *
 * In-memory cache implementation for testing.
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
 * Ideal for unit testing where cache state needs to be inspectable.
 *
 * @since 1.1.0
 */
class MemoryCache implements CacheInterface {

	/**
	 * In-memory storage.
	 *
	 * @var array<string, array{value: mixed, expires: int}>
	 */
	private array $storage = array();

	/**
	 * Track cache statistics.
	 *
	 * @var array{hits: int, misses: int, writes: int, deletes: int}
	 */
	private array $stats = array(
		'hits'    => 0,
		'misses'  => 0,
		'writes'  => 0,
		'deletes' => 0,
	);

	/**
	 * Time provider callable for testing.
	 *
	 * @var callable|null
	 */
	private $time_provider = null;

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

		$expired = $this->get_current_time() >= $expires;

		// Clean up expired entries to prevent memory leaks.
		if ( $expired ) {
			unset( $this->storage[ $key ] );
		}

		return $expired;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		if ( $this->is_expired( $key ) ) {
			++$this->stats['misses'];
			return $default;
		}

		++$this->stats['hits'];
		return $this->storage[ $key ]['value'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		$this->storage[ $key ] = array(
			'value'   => $value,
			'expires' => $ttl > 0 ? $this->get_current_time() + $ttl : 0,
		);

		++$this->stats['writes'];
		return true;
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
		return ! $this->is_expired( $key );
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
		if ( ! $this->is_expired( $key ) ) {
			++$this->stats['hits'];
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
		if ( $this->is_expired( $key ) ) {
			$this->set( $key, $value );
			return $value;
		}

		$current = $this->storage[ $key ]['value'];

		if ( ! is_numeric( $current ) ) {
			return false;
		}

		$new_value = (int) $current + $value;
		$this->storage[ $key ]['value'] = $new_value;

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
	 * @return array{hits: int, misses: int, writes: int, deletes: int} Cache stats.
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
			'hits'    => 0,
			'misses'  => 0,
			'writes'  => 0,
			'deletes' => 0,
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
	 * @return array<string, array{value: mixed, expires: int}> Raw storage.
	 */
	public function get_storage(): array {
		return $this->storage;
	}
}
