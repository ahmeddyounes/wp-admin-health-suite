<?php
/**
 * Mock Cache for Unit Testing
 *
 * Provides a testable implementation of CacheInterface
 * that stores data in memory.
 *
 * @package WPAdminHealth\Tests\Mocks
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Contracts\CacheInterface;

/**
 * Mock cache for testing.
 *
 * In-memory implementation of CacheInterface.
 */
class MockCache implements CacheInterface {

	/**
	 * Cached data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		$this->data[ $key ] = $value;
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $key ): bool {
		unset( $this->data[ $key ] );
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear( string $prefix = '' ): bool {
		if ( empty( $prefix ) ) {
			$this->data = array();
			return true;
		}

		foreach ( array_keys( $this->data ) as $key ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				unset( $this->data[ $key ] );
			}
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ) {
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
		if ( ! $this->has( $key ) ) {
			return false;
		}

		$current = $this->get( $key );
		if ( ! is_numeric( $current ) ) {
			return false;
		}

		$new_value = (int) $current + $value;
		$this->set( $key, $new_value );
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
		$result = array();
		foreach ( $keys as $key ) {
			$result[ $key ] = $this->get( $key, $default );
		}
		return $result;
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
	 * Reset mock state.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->data = array();
	}
}
