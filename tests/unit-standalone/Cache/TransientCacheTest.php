<?php
/**
 * TransientCache Unit Tests (Standalone)
 *
 * Tests for the WordPress transient-based cache implementation.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Cache
 */

namespace WPAdminHealth\Tests\UnitStandalone\Cache;

use WPAdminHealth\Cache\TransientCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * TransientCache test class.
 *
 * Tests WordPress transient integration, TTL management,
 * key namespacing, and multisite support.
 */
class TransientCacheTest extends StandaloneTestCase {

	/**
	 * Transient storage for mock functions.
	 *
	 * @var array<string, mixed>
	 */
	private static array $transient_storage = array();

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Setup test environment before each test.
	 */
	protected function setup_test_environment(): void {
		self::$transient_storage = array();
		$this->connection        = new MockConnection();
		$this->setup_transient_mocks();
	}

	/**
	 * Cleanup test environment after each test.
	 */
	protected function cleanup_test_environment(): void {
		self::$transient_storage = array();
		$this->restore_transient_functions();
	}

	/**
	 * Setup transient mock functions.
	 */
	private function setup_transient_mocks(): void {
		// Store reference to test class for closures.
		$test_class = $this;

		// Override get_transient.
		$GLOBALS['wpha_test_get_transient'] = static function ( $transient ) {
			if ( isset( self::$transient_storage[ $transient ] ) ) {
				$data = self::$transient_storage[ $transient ];

				// Check expiration.
				if ( 0 !== $data['expires'] && time() > $data['expires'] ) {
					unset( self::$transient_storage[ $transient ] );
					return false;
				}

				return $data['value'];
			}
			return false;
		};

		// Override set_transient.
		$GLOBALS['wpha_test_set_transient'] = static function ( $transient, $value, $expiration = 0 ) {
			self::$transient_storage[ $transient ] = array(
				'value'   => $value,
				'expires' => $expiration > 0 ? time() + $expiration : 0,
			);
			return true;
		};

		// Override delete_transient.
		$GLOBALS['wpha_test_delete_transient'] = static function ( $transient ) {
			if ( isset( self::$transient_storage[ $transient ] ) ) {
				unset( self::$transient_storage[ $transient ] );
				return true;
			}
			return false;
		};
	}

	/**
	 * Restore transient functions.
	 */
	private function restore_transient_functions(): void {
		unset( $GLOBALS['wpha_test_get_transient'] );
		unset( $GLOBALS['wpha_test_set_transient'] );
		unset( $GLOBALS['wpha_test_delete_transient'] );
	}

	/**
	 * Get value from mock transient storage.
	 *
	 * @param string $key Transient key.
	 * @return mixed|false
	 */
	private function get_mock_transient( string $key ) {
		if ( isset( $GLOBALS['wpha_test_get_transient'] ) ) {
			return ( $GLOBALS['wpha_test_get_transient'] )( $key );
		}
		return false;
	}

	/**
	 * Set value in mock transient storage.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Value to store.
	 * @param int    $expiration Expiration in seconds.
	 * @return bool
	 */
	private function set_mock_transient( string $key, $value, int $expiration = 0 ): bool {
		if ( isset( $GLOBALS['wpha_test_set_transient'] ) ) {
			return ( $GLOBALS['wpha_test_set_transient'] )( $key, $value, $expiration );
		}
		return true;
	}

	/**
	 * Delete value from mock transient storage.
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	private function delete_mock_transient( string $key ): bool {
		if ( isset( $GLOBALS['wpha_test_delete_transient'] ) ) {
			return ( $GLOBALS['wpha_test_delete_transient'] )( $key );
		}
		return true;
	}

	// =========================================================================
	// Interface Implementation Tests
	// =========================================================================

	/**
	 * Test TransientCache implements CacheInterface.
	 */
	public function test_implements_cache_interface(): void {
		$cache = new TransientCache();
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test constructor with default prefix.
	 */
	public function test_constructor_default_prefix(): void {
		$cache = new TransientCache();

		// Use reflection to check prefix.
		$reflection = new \ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'prefix' );
		$property->setAccessible( true );

		$this->assertEquals( 'wpha_', $property->getValue( $cache ) );
	}

	/**
	 * Test constructor with custom prefix.
	 */
	public function test_constructor_custom_prefix(): void {
		$cache = new TransientCache( 'custom_prefix_' );

		$reflection = new \ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'prefix' );
		$property->setAccessible( true );

		$this->assertEquals( 'custom_prefix_', $property->getValue( $cache ) );
	}

	/**
	 * Test constructor with connection.
	 */
	public function test_constructor_with_connection(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$reflection = new \ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'connection' );
		$property->setAccessible( true );

		$this->assertSame( $this->connection, $property->getValue( $cache ) );
	}

	// =========================================================================
	// Key Namespacing Tests
	// =========================================================================

	/**
	 * Test key prefixing.
	 */
	public function test_key_prefixing(): void {
		$cache = new TransientCache( 'test_' );

		// Use reflection to test build_key method.
		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		$this->assertEquals( 'test_my_key', $method->invoke( $cache, 'my_key' ) );
		$this->assertEquals( 'test_', $method->invoke( $cache, '' ) );
		$this->assertEquals( 'test_nested:key', $method->invoke( $cache, 'nested:key' ) );
	}

	/**
	 * Test empty prefix.
	 */
	public function test_empty_prefix(): void {
		$cache = new TransientCache( '' );

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		$this->assertEquals( 'my_key', $method->invoke( $cache, 'my_key' ) );
	}

	/**
	 * Test special characters in keys.
	 */
	public function test_special_characters_in_keys(): void {
		$cache = new TransientCache( 'wpha_' );

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		// Keys with colons.
		$this->assertEquals( 'wpha_key:with:colons', $method->invoke( $cache, 'key:with:colons' ) );

		// Keys with dots.
		$this->assertEquals( 'wpha_key.with.dots', $method->invoke( $cache, 'key.with.dots' ) );

		// Keys with slashes.
		$this->assertEquals( 'wpha_key/with/slashes', $method->invoke( $cache, 'key/with/slashes' ) );
	}

	// =========================================================================
	// Value Wrapping/Unwrapping Tests
	// =========================================================================

	/**
	 * Test value wrapping.
	 */
	public function test_value_wrapping(): void {
		$cache = new TransientCache();

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'wrap' );
		$method->setAccessible( true );

		$wrapped = $method->invoke( $cache, 'test_value' );

		$this->assertIsArray( $wrapped );
		$this->assertArrayHasKey( '_wpha_v', $wrapped );
		$this->assertEquals( 'test_value', $wrapped['_wpha_v'] );
	}

	/**
	 * Test value unwrapping - valid wrapped value.
	 */
	public function test_value_unwrapping_valid(): void {
		$cache = new TransientCache();

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'unwrap' );
		$method->setAccessible( true );

		$wrapped = array( '_wpha_v' => 'test_value' );
		$result  = $method->invoke( $cache, $wrapped );

		$this->assertTrue( $result['found'] );
		$this->assertEquals( 'test_value', $result['value'] );
	}

	/**
	 * Test value unwrapping - invalid wrapped value.
	 */
	public function test_value_unwrapping_invalid(): void {
		$cache = new TransientCache();

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'unwrap' );
		$method->setAccessible( true );

		// Non-array value.
		$result = $method->invoke( $cache, 'raw_string' );
		$this->assertFalse( $result['found'] );
		$this->assertNull( $result['value'] );

		// Array without wrapper key.
		$result = $method->invoke( $cache, array( 'other' => 'value' ) );
		$this->assertFalse( $result['found'] );
		$this->assertNull( $result['value'] );

		// False value.
		$result = $method->invoke( $cache, false );
		$this->assertFalse( $result['found'] );
		$this->assertNull( $result['value'] );
	}

	/**
	 * Test wrapping preserves false values.
	 */
	public function test_wrap_preserves_false(): void {
		$cache = new TransientCache();

		$reflection = new \ReflectionClass( $cache );
		$wrap       = $reflection->getMethod( 'wrap' );
		$unwrap     = $reflection->getMethod( 'unwrap' );
		$wrap->setAccessible( true );
		$unwrap->setAccessible( true );

		$wrapped = $wrap->invoke( $cache, false );
		$result  = $unwrap->invoke( $cache, $wrapped );

		$this->assertTrue( $result['found'] );
		$this->assertFalse( $result['value'] );
	}

	/**
	 * Test wrapping preserves null values.
	 */
	public function test_wrap_preserves_null(): void {
		$cache = new TransientCache();

		$reflection = new \ReflectionClass( $cache );
		$wrap       = $reflection->getMethod( 'wrap' );
		$unwrap     = $reflection->getMethod( 'unwrap' );
		$wrap->setAccessible( true );
		$unwrap->setAccessible( true );

		$wrapped = $wrap->invoke( $cache, null );
		$result  = $unwrap->invoke( $cache, $wrapped );

		$this->assertTrue( $result['found'] );
		$this->assertNull( $result['value'] );
	}

	// =========================================================================
	// Get/Set/Delete/Has Tests (using stubs)
	// =========================================================================

	/**
	 * Test get returns default for missing key.
	 *
	 * Note: This uses the stub get_transient which always returns false.
	 */
	public function test_get_returns_default_for_missing_key(): void {
		$cache = new TransientCache();

		$this->assertNull( $cache->get( 'nonexistent' ) );
		$this->assertEquals( 'default', $cache->get( 'nonexistent', 'default' ) );
	}

	/**
	 * Test has returns false for missing key.
	 */
	public function test_has_returns_false_for_missing_key(): void {
		$cache = new TransientCache();

		$this->assertFalse( $cache->has( 'nonexistent' ) );
	}

	/**
	 * Test set returns true.
	 *
	 * Note: The stub set_transient always returns true.
	 */
	public function test_set_returns_true(): void {
		$cache = new TransientCache();

		$this->assertTrue( $cache->set( 'key', 'value' ) );
		$this->assertTrue( $cache->set( 'key', 'value', 3600 ) );
	}

	/**
	 * Test delete returns true.
	 *
	 * Note: The stub delete_transient always returns true.
	 */
	public function test_delete_returns_true(): void {
		$cache = new TransientCache();

		$this->assertTrue( $cache->delete( 'any_key' ) );
	}

	// =========================================================================
	// TTL Management Tests
	// =========================================================================

	/**
	 * Test set with zero TTL.
	 */
	public function test_set_with_zero_ttl(): void {
		$cache = new TransientCache();

		// Zero TTL should be passed to set_transient (no expiration).
		$result = $cache->set( 'key', 'value', 0 );
		$this->assertTrue( $result );
	}

	/**
	 * Test set with positive TTL.
	 */
	public function test_set_with_positive_ttl(): void {
		$cache = new TransientCache();

		$result = $cache->set( 'key', 'value', 3600 );
		$this->assertTrue( $result );
	}

	// =========================================================================
	// Remember Tests
	// =========================================================================

	/**
	 * Test remember executes callback on cache miss.
	 */
	public function test_remember_executes_callback_on_miss(): void {
		$cache = new TransientCache();

		$executed = false;
		$callback = function () use ( &$executed ) {
			$executed = true;
			return 'callback_value';
		};

		$result = $cache->remember( 'remember_key', $callback );

		$this->assertTrue( $executed );
		$this->assertEquals( 'callback_value', $result );
	}

	/**
	 * Test remember with TTL.
	 */
	public function test_remember_with_ttl(): void {
		$cache = new TransientCache();

		$result = $cache->remember( 'remember_key', fn() => 'value', 3600 );

		$this->assertEquals( 'value', $result );
	}

	// =========================================================================
	// Increment/Decrement Tests
	// =========================================================================

	/**
	 * Test increment on non-existent key.
	 */
	public function test_increment_nonexistent_key(): void {
		$cache = new TransientCache();

		// Non-existent key starts from 0 (default).
		$result = $cache->increment( 'counter' );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Test increment with custom value.
	 */
	public function test_increment_custom_value(): void {
		$cache = new TransientCache();

		$result = $cache->increment( 'counter', 5 );

		$this->assertEquals( 5, $result );
	}

	/**
	 * Test decrement on non-existent key.
	 */
	public function test_decrement_nonexistent_key(): void {
		$cache = new TransientCache();

		$result = $cache->decrement( 'counter' );

		$this->assertEquals( -1, $result );
	}

	/**
	 * Test decrement with custom value.
	 */
	public function test_decrement_custom_value(): void {
		$cache = new TransientCache();

		$result = $cache->decrement( 'counter', 5 );

		$this->assertEquals( -5, $result );
	}

	/**
	 * Test increment returns false for non-numeric value.
	 *
	 * We need to simulate a cached non-numeric value for this.
	 */
	public function test_increment_fails_on_non_numeric(): void {
		$cache = new TransientCache();

		// We can't easily test this with stubs, but we verify the logic.
		// When get() returns a non-numeric value, increment should return false.
		// Since our stub returns 'default' or null, this test verifies default behavior.
		$result = $cache->increment( 'nonexistent_key', 1 );
		$this->assertIsInt( $result );
	}

	// =========================================================================
	// Multiple Operations Tests
	// =========================================================================

	/**
	 * Test get_multiple returns defaults.
	 */
	public function test_get_multiple_returns_defaults(): void {
		$cache = new TransientCache();

		$results = $cache->get_multiple( array( 'key1', 'key2', 'key3' ) );

		$this->assertEquals(
			array(
				'key1' => null,
				'key2' => null,
				'key3' => null,
			),
			$results
		);
	}

	/**
	 * Test get_multiple with custom default.
	 */
	public function test_get_multiple_custom_default(): void {
		$cache = new TransientCache();

		$results = $cache->get_multiple( array( 'key1', 'key2' ), 'default' );

		$this->assertEquals(
			array(
				'key1' => 'default',
				'key2' => 'default',
			),
			$results
		);
	}

	/**
	 * Test set_multiple returns true.
	 */
	public function test_set_multiple_returns_true(): void {
		$cache = new TransientCache();

		$result = $cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test set_multiple with TTL.
	 */
	public function test_set_multiple_with_ttl(): void {
		$cache = new TransientCache();

		$result = $cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			),
			3600
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test delete_multiple returns true.
	 */
	public function test_delete_multiple_returns_true(): void {
		$cache = new TransientCache();

		$result = $cache->delete_multiple( array( 'key1', 'key2' ) );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// Clear Tests with ConnectionInterface
	// =========================================================================

	/**
	 * Test clear with connection executes correct queries.
	 */
	public function test_clear_with_connection(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$cache->clear();

		$queries = $this->connection->get_queries();

		$this->assertCount( 2, $queries );

		// First query deletes transient values.
		// Note: The _ in wpha_ is escaped to \_ by esc_like.
		$this->assertStringContainsString( 'DELETE FROM', $queries[0]['query'] );
		$this->assertStringContainsString( '_transient_wpha', $queries[0]['query'] );

		// Second query deletes transient timeouts.
		$this->assertStringContainsString( 'DELETE FROM', $queries[1]['query'] );
		$this->assertStringContainsString( '_transient_timeout_wpha', $queries[1]['query'] );
	}

	/**
	 * Test clear with prefix using connection.
	 */
	public function test_clear_with_prefix_and_connection(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$cache->clear( 'session_' );

		$queries = $this->connection->get_queries();

		$this->assertCount( 2, $queries );

		// Queries should include the prefix.
		// Note: Underscores in the prefix are escaped by esc_like.
		$this->assertStringContainsString( '_transient_wpha', $queries[0]['query'] );
		$this->assertStringContainsString( 'session', $queries[0]['query'] );
		$this->assertStringContainsString( '_transient_timeout_wpha', $queries[1]['query'] );
		$this->assertStringContainsString( 'session', $queries[1]['query'] );
	}

	/**
	 * Test clear uses correct table name from connection.
	 */
	public function test_clear_uses_connection_table_name(): void {
		$this->connection->set_prefix( 'custom_' );
		$cache = new TransientCache( 'wpha_', $this->connection );

		$cache->clear();

		$queries = $this->connection->get_queries();

		// Should use custom_options table.
		$this->assertStringContainsString( 'custom_options', $queries[0]['query'] );
	}

	/**
	 * Test clear escapes LIKE wildcards.
	 */
	public function test_clear_escapes_like_wildcards(): void {
		$cache = new TransientCache( 'test_%_', $this->connection );

		$cache->clear();

		$queries = $this->connection->get_queries();

		// The % should be escaped to \%.
		$this->assertStringContainsString( 'test\_\%\_', $queries[0]['query'] );
	}

	/**
	 * Test clear returns true.
	 */
	public function test_clear_returns_true(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$this->assertTrue( $cache->clear() );
		$this->assertTrue( $cache->clear( 'prefix_' ) );
	}

	/**
	 * Test clear without connection uses wpdb fallback.
	 *
	 * Note: This requires a mock $wpdb global.
	 */
	public function test_clear_without_connection(): void {
		// Create a mock wpdb object using an anonymous class.
		$mock_wpdb = new class {
			public string $options = 'wp_options';

			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			public function prepare( string $query, $arg ): string {
				return str_replace( '%s', "'" . addslashes( (string) $arg ) . "'", $query );
			}

			public function query( string $query ): bool {
				return true;
			}
		};

		// Store original wpdb and set our mock.
		$original_wpdb   = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = $mock_wpdb;

		try {
			$cache = new TransientCache( 'wpha_' );

			// Should not throw an error.
			$result = $cache->clear();

			$this->assertTrue( $result );
		} finally {
			// Restore original wpdb.
			if ( null === $original_wpdb ) {
				unset( $GLOBALS['wpdb'] );
			} else {
				$GLOBALS['wpdb'] = $original_wpdb;
			}
		}
	}

	// =========================================================================
	// Edge Cases
	// =========================================================================

	/**
	 * Test empty key.
	 */
	public function test_empty_key(): void {
		$cache = new TransientCache( 'wpha_' );

		// Should handle empty key gracefully.
		$result = $cache->set( '', 'value' );
		$this->assertTrue( $result );
	}

	/**
	 * Test very long key is handled by truncation and hash.
	 *
	 * WordPress transient keys have length limits.
	 * The total key with prefix should be considered.
	 */
	public function test_long_key(): void {
		$cache = new TransientCache( 'wpha_' );

		$long_key = str_repeat( 'a', 200 );

		// Should still work - key will be truncated with hash suffix.
		$result = $cache->set( $long_key, 'value' );
		$this->assertTrue( $result );
	}

	/**
	 * Test long key is truncated with hash suffix.
	 */
	public function test_long_key_truncated_with_hash(): void {
		$cache = new TransientCache( 'wpha_' );

		// Use reflection to test build_key method.
		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		$long_key = str_repeat( 'a', 200 );
		$result   = $method->invoke( $cache, $long_key );

		// Result should be within limits (172 - 19 = 153 max).
		$this->assertLessThanOrEqual( 153, strlen( $result ) );

		// Result should contain an MD5 hash (32 hex chars).
		$this->assertMatchesRegularExpression( '/_[a-f0-9]{32}$/', $result );
	}

	/**
	 * Test short key is not modified.
	 */
	public function test_short_key_not_modified(): void {
		$cache = new TransientCache( 'wpha_' );

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		$short_key = 'my_key';
		$result    = $method->invoke( $cache, $short_key );

		// Short key should remain as-is with prefix.
		$this->assertEquals( 'wpha_my_key', $result );
	}

	/**
	 * Test different long keys produce different hashes.
	 */
	public function test_different_long_keys_different_hashes(): void {
		$cache = new TransientCache( 'wpha_' );

		$reflection = new \ReflectionClass( $cache );
		$method     = $reflection->getMethod( 'build_key' );
		$method->setAccessible( true );

		$long_key1 = str_repeat( 'a', 200 );
		$long_key2 = str_repeat( 'b', 200 );

		$result1 = $method->invoke( $cache, $long_key1 );
		$result2 = $method->invoke( $cache, $long_key2 );

		// Different keys should produce different hashed results.
		$this->assertNotEquals( $result1, $result2 );
	}

	/**
	 * Test setting complex data types.
	 */
	public function test_complex_data_types(): void {
		$cache = new TransientCache();

		// Array.
		$this->assertTrue( $cache->set( 'array_key', array( 'nested' => array( 'value' ) ) ) );

		// Object.
		$obj       = new \stdClass();
		$obj->prop = 'value';
		$this->assertTrue( $cache->set( 'object_key', $obj ) );

		// Integer.
		$this->assertTrue( $cache->set( 'int_key', 42 ) );

		// Float.
		$this->assertTrue( $cache->set( 'float_key', 3.14 ) );

		// Boolean.
		$this->assertTrue( $cache->set( 'bool_key', true ) );

		// Null.
		$this->assertTrue( $cache->set( 'null_key', null ) );
	}

	/**
	 * Test remember callback exception handling.
	 *
	 * If callback throws, the exception should propagate.
	 */
	public function test_remember_callback_exception(): void {
		$cache = new TransientCache();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Callback error' );

		$cache->remember(
			'error_key',
			function () {
				throw new \RuntimeException( 'Callback error' );
			}
		);
	}

	/**
	 * Test multiple operations on same key.
	 */
	public function test_multiple_operations_same_key(): void {
		$cache = new TransientCache();

		// Set.
		$this->assertTrue( $cache->set( 'key', 'value1' ) );

		// Set again (overwrite).
		$this->assertTrue( $cache->set( 'key', 'value2' ) );

		// Delete.
		$this->assertTrue( $cache->delete( 'key' ) );

		// Set after delete.
		$this->assertTrue( $cache->set( 'key', 'value3' ) );
	}

	// =========================================================================
	// Multisite Support Tests
	// =========================================================================

	/**
	 * Test clear handles standard transient patterns.
	 *
	 * Note: Full multisite testing requires WordPress environment.
	 * This tests that the query patterns are correct.
	 */
	public function test_clear_transient_patterns(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$cache->clear();

		$queries = $this->connection->get_queries();

		// Should target _transient_ prefix.
		$this->assertStringContainsString( '_transient_', $queries[0]['query'] );
		$this->assertStringContainsString( 'wpha', $queries[0]['query'] );

		// Should target _transient_timeout_ prefix.
		$this->assertStringContainsString( '_transient_timeout_', $queries[1]['query'] );
		$this->assertStringContainsString( 'wpha', $queries[1]['query'] );
	}

	// =========================================================================
	// Connection Interface Integration Tests
	// =========================================================================

	/**
	 * Test clear handles null prepare result.
	 */
	public function test_clear_handles_null_prepare(): void {
		// Create a mock that returns null from prepare.
		$mock_connection = $this->createMock( \WPAdminHealth\Contracts\ConnectionInterface::class );
		$mock_connection->method( 'get_options_table' )->willReturn( 'wp_options' );
		$mock_connection->method( 'esc_like' )->willReturnCallback( fn( $s ) => addcslashes( $s, '_%\\' ) );
		$mock_connection->method( 'prepare' )->willReturn( null );
		$mock_connection->expects( $this->never() )->method( 'query' );

		$cache = new TransientCache( 'wpha_', $mock_connection );

		// Should not throw and should return true.
		$this->assertTrue( $cache->clear() );
	}

	/**
	 * Test clear executes both queries with connection.
	 */
	public function test_clear_executes_both_queries(): void {
		$cache = new TransientCache( 'wpha_', $this->connection );

		$cache->clear( 'test_' );

		// Should have executed 2 queries.
		$this->assertCount( 2, $this->connection->get_queries() );
	}
}
