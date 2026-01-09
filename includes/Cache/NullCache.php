<?php
/**
 * Null Cache Implementation
 *
 * No-op cache implementation for testing.
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
 * Class NullCache
 *
 * Implements a no-op cache that never stores anything.
 * Useful for testing scenarios where you want to ensure
 * cache misses and verify non-cached behavior.
 *
 * @since 1.1.0
 */
class NullCache implements CacheInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		return $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return true; // Pretend success, but store nothing.
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $key ): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear( string $prefix = '' ): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ) {
		// Always execute the callback since nothing is cached.
		return $callback();
	}

	/**
	 * {@inheritdoc}
	 */
	public function increment( string $key, int $value = 1 ) {
		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function decrement( string $key, int $value = 1 ) {
		return -$value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_multiple( array $keys, $default = null ): array {
		$results = array();

		foreach ( $keys as $key ) {
			$results[ $key ] = $default;
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_multiple( array $values, int $ttl = 0 ): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete_multiple( array $keys ): bool {
		return true;
	}
}
