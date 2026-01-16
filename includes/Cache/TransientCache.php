<?php
/**
 * Transient Cache Implementation
 *
 * Cache implementation using WordPress transients.
 *
 * @package WPAdminHealth\Cache
 */

namespace WPAdminHealth\Cache;

use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class TransientCache
 *
 * Implements caching using WordPress transients.
 * Best for sites without persistent object caching.
 *
 * @since 1.1.0
 */
class TransientCache implements CacheInterface {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Database connection.
	 *
	 * @since 1.3.0
	 * @var ConnectionInterface|null
	 */
	private ?ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0 Added optional ConnectionInterface parameter.
	 *
	 * @param string                    $prefix     Cache key prefix.
	 * @param ConnectionInterface|null $connection Optional database connection.
	 */
	public function __construct( string $prefix = 'wpha_', ?ConnectionInterface $connection = null ) {
		$this->prefix     = $prefix;
		$this->connection = $connection;
	}

	/**
	 * Wrapper key used to distinguish stored values from missing keys.
	 *
	 * WordPress transients return false for both missing keys and stored false values.
	 * By wrapping values in an array with this key, we can distinguish between them.
	 *
	 * @var string
	 */
	private const WRAPPER_KEY = '_wpha_v';

	/**
	 * Build a prefixed cache key.
	 *
	 * @param string $key Cache key.
	 * @return string Prefixed key.
	 */
	private function build_key( string $key ): string {
		return $this->prefix . $key;
	}

	/**
	 * Wrap a value for storage.
	 *
	 * @param mixed $value Value to wrap.
	 * @return array Wrapped value.
	 */
	private function wrap( $value ): array {
		return array( self::WRAPPER_KEY => $value );
	}

	/**
	 * Unwrap a stored value.
	 *
	 * @param mixed $wrapped The wrapped value from storage.
	 * @return array{found: bool, value: mixed} Array with 'found' and 'value' keys.
	 */
	private function unwrap( $wrapped ): array {
		if ( is_array( $wrapped ) && array_key_exists( self::WRAPPER_KEY, $wrapped ) ) {
			return array(
				'found' => true,
				'value' => $wrapped[ self::WRAPPER_KEY ],
			);
		}

		return array(
			'found' => false,
			'value' => null,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		$wrapped = get_transient( $this->build_key( $key ) );
		$result  = $this->unwrap( $wrapped );

		if ( ! $result['found'] ) {
			return $default;
		}

		return $result['value'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return set_transient( $this->build_key( $key ), $this->wrap( $value ), $ttl );
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->build_key( $key ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		$wrapped = get_transient( $this->build_key( $key ) );
		$result  = $this->unwrap( $wrapped );

		return $result['found'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear( string $prefix = '' ): bool {
		$full_prefix = $this->build_key( $prefix );

		// Use ConnectionInterface if available.
		if ( $this->connection ) {
			$options_table = $this->connection->get_options_table();

			// Delete transient values.
			$query = $this->connection->prepare(
				"DELETE FROM {$options_table} WHERE option_name LIKE %s",
				'_transient_' . $this->connection->esc_like( $full_prefix ) . '%'
			);
			if ( $query ) {
				$this->connection->query( $query );
			}

			// Delete transient timeouts.
			$query = $this->connection->prepare(
				"DELETE FROM {$options_table} WHERE option_name LIKE %s",
				'_transient_timeout_' . $this->connection->esc_like( $full_prefix ) . '%'
			);
			if ( $query ) {
				$this->connection->query( $query );
			}
		} else {
			// Fallback to global $wpdb for backward compatibility.
			global $wpdb;

			// Delete transient values.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $wpdb->esc_like( $full_prefix ) . '%'
				)
			);

			// Delete transient timeouts.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_timeout_' . $wpdb->esc_like( $full_prefix ) . '%'
				)
			);
		}

		return true;
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
		$current = $this->get( $key, 0 );

		if ( ! is_numeric( $current ) ) {
			return false;
		}

		$new_value = (int) $current + $value;

		// We can't preserve TTL with transients, so use a default.
		if ( $this->set( $key, $new_value ) ) {
			return $new_value;
		}

		return false;
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
		$success = true;

		foreach ( $keys as $key ) {
			if ( ! $this->delete( $key ) ) {
				$success = false;
			}
		}

		return $success;
	}
}
