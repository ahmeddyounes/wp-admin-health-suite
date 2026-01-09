<?php
/**
 * Object Cache Implementation
 *
 * Cache implementation using WordPress object cache.
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
 * Class Object_Cache
 *
 * Implements caching using WordPress object cache API.
 * Best for sites with persistent object caching (Redis, Memcached).
 *
 * @since 1.1.0
 */
class Object_Cache implements CacheInterface {

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * Constructor.
	 *
	 * @param string $group Cache group name.
	 */
	public function __construct( string $group = 'wpha' ) {
		$this->group = $group;
	}

	/**
	 * Check if persistent object cache is available.
	 *
	 * @return bool True if persistent object cache is available.
	 */
	public static function is_available(): bool {
		return wp_using_ext_object_cache();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		$found = false;
		$value = wp_cache_get( $key, $this->group, false, $found );

		if ( ! $found ) {
			return $default;
		}

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return wp_cache_set( $key, $value, $this->group, $ttl );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $key ): bool {
		return wp_cache_delete( $key, $this->group );
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		$found = false;
		wp_cache_get( $key, $this->group, false, $found );
		return $found;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear( string $prefix = '' ): bool {
		if ( empty( $prefix ) ) {
			// Clear entire group if no prefix specified.
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				return wp_cache_flush_group( $this->group );
			}
			// Fallback: flush entire cache (not ideal but works).
			return wp_cache_flush();
		}

		// Object cache doesn't support prefix-based deletion easily.
		// This is a limitation - for prefix clearing, use transient cache.
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ) {
		// Use has() to properly detect cache hits, including cached null values.
		if ( $this->has( $key ) ) {
			return $this->get( $key );
		}

		$value = $callback();
		$this->set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function increment( string $key, int $value = 1 ) {
		$result = wp_cache_incr( $key, $value, $this->group );
		return false === $result ? false : $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function decrement( string $key, int $value = 1 ) {
		$result = wp_cache_decr( $key, $value, $this->group );
		return false === $result ? false : $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_multiple( array $keys, $default = null ): array {
		// Use wp_cache_get_multiple if available (WP 5.5+).
		if ( function_exists( 'wp_cache_get_multiple' ) ) {
			$values = wp_cache_get_multiple( $keys, $this->group );
			$results = array();

			foreach ( $keys as $key ) {
				$results[ $key ] = isset( $values[ $key ] ) && false !== $values[ $key ]
					? $values[ $key ]
					: $default;
			}

			return $results;
		}

		// Fallback to individual gets.
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
		// Use wp_cache_set_multiple if available (WP 6.0+).
		if ( function_exists( 'wp_cache_set_multiple' ) ) {
			$results = wp_cache_set_multiple( $values, $this->group, $ttl );
			return ! in_array( false, $results, true );
		}

		// Fallback to individual sets.
		$success = true;
		foreach ( $values as $key => $value ) {
			if ( ! $this->set( $key, $value, $ttl ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_multiple( array $keys ): bool {
		// Use wp_cache_delete_multiple if available (WP 6.0+).
		if ( function_exists( 'wp_cache_delete_multiple' ) ) {
			$results = wp_cache_delete_multiple( $keys, $this->group );
			return ! in_array( false, $results, true );
		}

		// Fallback to individual deletes.
		$success = true;
		foreach ( $keys as $key ) {
			if ( ! $this->delete( $key ) ) {
				$success = false;
			}
		}

		return $success;
	}
}
