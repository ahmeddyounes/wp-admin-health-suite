<?php
/**
 * Cache Unit Tests (Standalone)
 *
 * Tests for the cache abstraction layer.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Cache
 */

namespace WPAdminHealth\Tests\UnitStandalone\Cache;

use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Cache\NullCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Cache test class.
 */
class CacheTest extends StandaloneTestCase {

	/**
	 * Test MemoryCache implements CacheInterface.
	 */
	public function test_memory_cache_implements_interface(): void {
		$cache = new MemoryCache();
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test NullCache implements CacheInterface.
	 */
	public function test_null_cache_implements_interface(): void {
		$cache = new NullCache();
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test MemoryCache get and set.
	 */
	public function test_memory_cache_get_set(): void {
		$cache = new MemoryCache();

		$cache->set( 'test_key', 'test_value' );

		$this->assertTrue( $cache->has( 'test_key' ) );
		$this->assertEquals( 'test_value', $cache->get( 'test_key' ) );
	}

	/**
	 * Test MemoryCache returns default for missing key.
	 */
	public function test_memory_cache_default_value(): void {
		$cache = new MemoryCache();

		$this->assertNull( $cache->get( 'nonexistent' ) );
		$this->assertEquals( 'default', $cache->get( 'nonexistent', 'default' ) );
	}

	/**
	 * Test MemoryCache delete.
	 */
	public function test_memory_cache_delete(): void {
		$cache = new MemoryCache();

		$cache->set( 'to_delete', 'value' );
		$this->assertTrue( $cache->has( 'to_delete' ) );

		$cache->delete( 'to_delete' );
		$this->assertFalse( $cache->has( 'to_delete' ) );
	}

	/**
	 * Test MemoryCache clear.
	 */
	public function test_memory_cache_clear(): void {
		$cache = new MemoryCache();

		$cache->set( 'key1', 'value1' );
		$cache->set( 'key2', 'value2' );
		$cache->set( 'key3', 'value3' );

		$cache->clear();

		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
		$this->assertFalse( $cache->has( 'key3' ) );
	}

	/**
	 * Test MemoryCache clear with prefix.
	 */
	public function test_memory_cache_clear_with_prefix(): void {
		$cache = new MemoryCache();

		$cache->set( 'prefix_key1', 'value1' );
		$cache->set( 'prefix_key2', 'value2' );
		$cache->set( 'other_key', 'value3' );

		$cache->clear( 'prefix_' );

		$this->assertFalse( $cache->has( 'prefix_key1' ) );
		$this->assertFalse( $cache->has( 'prefix_key2' ) );
		$this->assertTrue( $cache->has( 'other_key' ) );
	}

	/**
	 * Test MemoryCache stores complex data.
	 */
	public function test_memory_cache_complex_data(): void {
		$cache = new MemoryCache();

		$array_data = array( 'nested' => array( 'deep' => 'value' ) );
		$cache->set( 'array', $array_data );
		$this->assertEquals( $array_data, $cache->get( 'array' ) );

		$object_data       = new \stdClass();
		$object_data->prop = 'value';
		$cache->set( 'object', $object_data );
		$this->assertEquals( $object_data, $cache->get( 'object' ) );

		$cache->set( 'integer', 42 );
		$this->assertSame( 42, $cache->get( 'integer' ) );

		$cache->set( 'boolean', false );
		$this->assertFalse( $cache->get( 'boolean' ) );
	}

	/**
	 * Test NullCache always misses.
	 */
	public function test_null_cache_always_misses(): void {
		$cache = new NullCache();

		// Set returns true but value is not stored.
		$result = $cache->set( 'key', 'value' );
		$this->assertTrue( $result );

		// Has always returns false.
		$this->assertFalse( $cache->has( 'key' ) );

		// Get always returns default.
		$this->assertNull( $cache->get( 'key' ) );
		$this->assertEquals( 'default', $cache->get( 'key', 'default' ) );
	}

	/**
	 * Test NullCache delete always succeeds.
	 */
	public function test_null_cache_delete(): void {
		$cache = new NullCache();

		$this->assertTrue( $cache->delete( 'any_key' ) );
	}

	/**
	 * Test NullCache clear always succeeds.
	 */
	public function test_null_cache_clear(): void {
		$cache = new NullCache();

		$this->assertTrue( $cache->clear() );
		$this->assertTrue( $cache->clear( 'prefix' ) );
	}

	/**
	 * Test MemoryCache remembers values.
	 */
	public function test_memory_cache_remember(): void {
		$cache = new MemoryCache();

		$counter = 0;
		$callback = function() use ( &$counter ) {
			return ++$counter;
		};

		// First call executes callback.
		$result1 = $cache->remember( 'remember_key', $callback );
		$this->assertEquals( 1, $result1 );

		// Second call returns cached value.
		$result2 = $cache->remember( 'remember_key', $callback );
		$this->assertEquals( 1, $result2 );

		// Counter should only have been incremented once.
		$this->assertEquals( 1, $counter );
	}

	/**
	 * Test MemoryCache remember with TTL.
	 */
	public function test_memory_cache_remember_with_ttl(): void {
		$cache = new MemoryCache();

		$result = $cache->remember( 'ttl_key', fn() => 'value', 3600 );

		$this->assertEquals( 'value', $result );
		$this->assertEquals( 'value', $cache->get( 'ttl_key' ) );
	}

	/**
	 * Test MemoryCache get multiple keys.
	 */
	public function test_memory_cache_get_multiple(): void {
		$cache = new MemoryCache();

		$cache->set( 'multi_1', 'value1' );
		$cache->set( 'multi_2', 'value2' );

		$results = $cache->get_multiple( array( 'multi_1', 'multi_2', 'multi_3' ) );

		$this->assertEquals(
			array(
				'multi_1' => 'value1',
				'multi_2' => 'value2',
				'multi_3' => null,
			),
			$results
		);
	}

	/**
	 * Test MemoryCache set multiple keys.
	 */
	public function test_memory_cache_set_multiple(): void {
		$cache = new MemoryCache();

		$values = array(
			'batch_1' => 'value1',
			'batch_2' => 'value2',
			'batch_3' => 'value3',
		);

		$cache->set_multiple( $values );

		$this->assertEquals( 'value1', $cache->get( 'batch_1' ) );
		$this->assertEquals( 'value2', $cache->get( 'batch_2' ) );
		$this->assertEquals( 'value3', $cache->get( 'batch_3' ) );
	}

	/**
	 * Test MemoryCache delete multiple keys.
	 */
	public function test_memory_cache_delete_multiple(): void {
		$cache = new MemoryCache();

		$cache->set( 'del_1', 'value1' );
		$cache->set( 'del_2', 'value2' );
		$cache->set( 'del_3', 'value3' );

		$cache->delete_multiple( array( 'del_1', 'del_2' ) );

		$this->assertFalse( $cache->has( 'del_1' ) );
		$this->assertFalse( $cache->has( 'del_2' ) );
		$this->assertTrue( $cache->has( 'del_3' ) );
	}

	/**
	 * Test MemoryCache increment on non-existent key.
	 */
	public function test_memory_cache_increment_nonexistent(): void {
		$cache = new MemoryCache();

		$result = $cache->increment( 'counter' );

		$this->assertEquals( 1, $result );
		$this->assertEquals( 1, $cache->get( 'counter' ) );
	}

	/**
	 * Test MemoryCache increment on existing key.
	 */
	public function test_memory_cache_increment_existing(): void {
		$cache = new MemoryCache();

		$cache->set( 'counter', 10 );
		$result = $cache->increment( 'counter' );

		$this->assertEquals( 11, $result );
		$this->assertEquals( 11, $cache->get( 'counter' ) );
	}

	/**
	 * Test MemoryCache increment with custom value.
	 */
	public function test_memory_cache_increment_by_value(): void {
		$cache = new MemoryCache();

		$cache->set( 'counter', 5 );
		$result = $cache->increment( 'counter', 10 );

		$this->assertEquals( 15, $result );
	}

	/**
	 * Test MemoryCache increment fails on non-numeric value.
	 */
	public function test_memory_cache_increment_fails_on_non_numeric(): void {
		$cache = new MemoryCache();

		$cache->set( 'string_key', 'not_a_number' );
		$result = $cache->increment( 'string_key' );

		$this->assertFalse( $result );
	}

	/**
	 * Test MemoryCache decrement on non-existent key.
	 */
	public function test_memory_cache_decrement_nonexistent(): void {
		$cache = new MemoryCache();

		$result = $cache->decrement( 'counter' );

		$this->assertEquals( -1, $result );
		$this->assertEquals( -1, $cache->get( 'counter' ) );
	}

	/**
	 * Test MemoryCache decrement on existing key.
	 */
	public function test_memory_cache_decrement_existing(): void {
		$cache = new MemoryCache();

		$cache->set( 'counter', 10 );
		$result = $cache->decrement( 'counter' );

		$this->assertEquals( 9, $result );
		$this->assertEquals( 9, $cache->get( 'counter' ) );
	}

	/**
	 * Test MemoryCache decrement with custom value.
	 */
	public function test_memory_cache_decrement_by_value(): void {
		$cache = new MemoryCache();

		$cache->set( 'counter', 20 );
		$result = $cache->decrement( 'counter', 5 );

		$this->assertEquals( 15, $result );
	}

	/**
	 * Test MemoryCache decrement can go negative.
	 */
	public function test_memory_cache_decrement_goes_negative(): void {
		$cache = new MemoryCache();

		$cache->set( 'counter', 5 );
		$result = $cache->decrement( 'counter', 10 );

		$this->assertEquals( -5, $result );
	}

	/**
	 * Test NullCache increment behavior.
	 */
	public function test_null_cache_increment(): void {
		$cache = new NullCache();

		// Null cache starts from 0 conceptually.
		$result = $cache->increment( 'counter' );
		$this->assertEquals( 1, $result );

		$result = $cache->increment( 'counter', 5 );
		$this->assertEquals( 5, $result );
	}

	/**
	 * Test NullCache decrement behavior.
	 */
	public function test_null_cache_decrement(): void {
		$cache = new NullCache();

		// Null cache starts from 0 conceptually.
		$result = $cache->decrement( 'counter' );
		$this->assertEquals( -1, $result );

		$result = $cache->decrement( 'counter', 5 );
		$this->assertEquals( -5, $result );
	}

	/**
	 * Test NullCache remember always executes callback.
	 */
	public function test_null_cache_remember_always_executes(): void {
		$cache = new NullCache();

		$counter = 0;
		$callback = function() use ( &$counter ) {
			return ++$counter;
		};

		$result1 = $cache->remember( 'key', $callback );
		$result2 = $cache->remember( 'key', $callback );
		$result3 = $cache->remember( 'key', $callback );

		// Each call should execute the callback.
		$this->assertEquals( 1, $result1 );
		$this->assertEquals( 2, $result2 );
		$this->assertEquals( 3, $result3 );
		$this->assertEquals( 3, $counter );
	}

	/**
	 * Test NullCache get_multiple returns defaults.
	 */
	public function test_null_cache_get_multiple(): void {
		$cache = new NullCache();

		$cache->set( 'key1', 'value1' );
		$cache->set( 'key2', 'value2' );

		$results = $cache->get_multiple( array( 'key1', 'key2', 'key3' ), 'default' );

		// All values should be defaults since nothing is cached.
		$this->assertEquals(
			array(
				'key1' => 'default',
				'key2' => 'default',
				'key3' => 'default',
			),
			$results
		);
	}

	/**
	 * Test NullCache set_multiple succeeds.
	 */
	public function test_null_cache_set_multiple(): void {
		$cache = new NullCache();

		$result = $cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			)
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test NullCache delete_multiple succeeds.
	 */
	public function test_null_cache_delete_multiple(): void {
		$cache = new NullCache();

		$result = $cache->delete_multiple( array( 'key1', 'key2' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test MemoryCache statistics tracking.
	 */
	public function test_memory_cache_stats(): void {
		$cache = new MemoryCache();

		// Initial stats should be zero.
		$stats = $cache->get_stats();
		$this->assertEquals( 0, $stats['hits'] );
		$this->assertEquals( 0, $stats['misses'] );
		$this->assertEquals( 0, $stats['writes'] );
		$this->assertEquals( 0, $stats['deletes'] );

		// Set increments writes.
		$cache->set( 'key', 'value' );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['writes'] );

		// Get on existing key increments hits.
		$cache->get( 'key' );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['hits'] );

		// Get on missing key increments misses.
		$cache->get( 'nonexistent' );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['misses'] );

		// Delete increments deletes.
		$cache->delete( 'key' );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['deletes'] );
	}

	/**
	 * Test MemoryCache reset stats.
	 */
	public function test_memory_cache_reset_stats(): void {
		$cache = new MemoryCache();

		$cache->set( 'key', 'value' );
		$cache->get( 'key' );
		$cache->get( 'miss' );
		$cache->delete( 'key' );

		$cache->reset_stats();

		$stats = $cache->get_stats();
		$this->assertEquals( 0, $stats['hits'] );
		$this->assertEquals( 0, $stats['misses'] );
		$this->assertEquals( 0, $stats['writes'] );
		$this->assertEquals( 0, $stats['deletes'] );
	}

	/**
	 * Test MemoryCache get_keys helper.
	 */
	public function test_memory_cache_get_keys(): void {
		$cache = new MemoryCache();

		$cache->set( 'key1', 'value1' );
		$cache->set( 'key2', 'value2' );

		$keys = $cache->get_keys();

		$this->assertCount( 2, $keys );
		$this->assertContains( 'key1', $keys );
		$this->assertContains( 'key2', $keys );
	}

	/**
	 * Test MemoryCache count helper.
	 */
	public function test_memory_cache_count(): void {
		$cache = new MemoryCache();

		$this->assertEquals( 0, $cache->count() );

		$cache->set( 'key1', 'value1' );
		$this->assertEquals( 1, $cache->count() );

		$cache->set( 'key2', 'value2' );
		$this->assertEquals( 2, $cache->count() );

		$cache->delete( 'key1' );
		$this->assertEquals( 1, $cache->count() );
	}

	/**
	 * Test MemoryCache get_storage returns raw storage.
	 */
	public function test_memory_cache_get_storage(): void {
		$cache = new MemoryCache();

		$cache->set( 'key1', 'value1', 3600 );
		$cache->set( 'key2', 'value2' ); // No TTL.

		$storage = $cache->get_storage();

		$this->assertCount( 2, $storage );
		$this->assertArrayHasKey( 'key1', $storage );
		$this->assertArrayHasKey( 'key2', $storage );
		$this->assertEquals( 'value1', $storage['key1']['value'] );
		$this->assertEquals( 'value2', $storage['key2']['value'] );
		$this->assertGreaterThan( 0, $storage['key1']['expires'] ); // Has expiration.
		$this->assertEquals( 0, $storage['key2']['expires'] ); // No expiration.
	}

	/**
	 * Test MemoryCache TTL expiration.
	 *
	 * Uses time injection to verify expiration without sleep.
	 */
	public function test_memory_cache_ttl_expiration(): void {
		$cache = new MemoryCache();
		$current_time = 1000;

		// Inject time provider.
		$cache->set_time_provider( function() use ( &$current_time ) {
			return $current_time;
		} );

		// Set with 10-second TTL.
		$cache->set( 'expires_key', 'expires_value', 10 );

		// Should exist immediately.
		$this->assertTrue( $cache->has( 'expires_key' ) );
		$this->assertEquals( 'expires_value', $cache->get( 'expires_key' ) );

		// Advance time past expiration.
		$current_time = 1011;

		// Should be expired now.
		$this->assertFalse( $cache->has( 'expires_key' ) );
		$this->assertNull( $cache->get( 'expires_key' ) );
		$this->assertEquals( 'default', $cache->get( 'expires_key', 'default' ) );
	}

	/**
	 * Test MemoryCache remember with expired key re-executes callback.
	 */
	public function test_memory_cache_remember_expired_reexecutes(): void {
		$cache = new MemoryCache();
		$current_time = 1000;

		// Inject time provider.
		$cache->set_time_provider( function() use ( &$current_time ) {
			return $current_time;
		} );

		$counter  = 0;
		$callback = function() use ( &$counter ) {
			return ++$counter;
		};

		// First call with 10-second TTL.
		$result1 = $cache->remember( 'remember_expires', $callback, 10 );
		$this->assertEquals( 1, $result1 );

		// Second call should return cached value.
		$result2 = $cache->remember( 'remember_expires', $callback, 10 );
		$this->assertEquals( 1, $result2 );
		$this->assertEquals( 1, $counter );

		// Advance time past expiration.
		$current_time = 1011;

		// Third call should re-execute callback.
		$result3 = $cache->remember( 'remember_expires', $callback, 10 );
		$this->assertEquals( 2, $result3 );
		$this->assertEquals( 2, $counter );
	}

	/**
	 * Test MemoryCache increment on expired key.
	 */
	public function test_memory_cache_increment_expired_key(): void {
		$cache = new MemoryCache();
		$current_time = 1000;

		// Inject time provider.
		$cache->set_time_provider( function() use ( &$current_time ) {
			return $current_time;
		} );

		$cache->set( 'counter', 10, 10 );

		// Should increment existing value.
		$this->assertEquals( 11, $cache->increment( 'counter' ) );

		// Advance time past expiration.
		$current_time = 1011;

		// Should start fresh from the increment value.
		$result = $cache->increment( 'counter', 5 );
		$this->assertEquals( 5, $result );
	}

	/**
	 * Test MemoryCache stats track remember hits/misses correctly.
	 */
	public function test_memory_cache_remember_stats(): void {
		$cache = new MemoryCache();

		$callback = fn() => 'value';

		// First call is a miss + write.
		$cache->remember( 'stats_key', $callback );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['misses'] );
		$this->assertEquals( 1, $stats['writes'] );
		$this->assertEquals( 0, $stats['hits'] );

		// Second call is a hit.
		$cache->remember( 'stats_key', $callback );
		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['misses'] );
		$this->assertEquals( 1, $stats['writes'] );
		$this->assertEquals( 1, $stats['hits'] );
	}

	/**
	 * Test MemoryCache can store and retrieve false values.
	 *
	 * This is a tricky edge case - false could be confused with cache miss.
	 */
	public function test_memory_cache_stores_false_value(): void {
		$cache = new MemoryCache();

		$cache->set( 'false_key', false );

		$this->assertTrue( $cache->has( 'false_key' ) );
		$this->assertFalse( $cache->get( 'false_key' ) );
		$this->assertSame( false, $cache->get( 'false_key', 'default' ) );
	}

	/**
	 * Test MemoryCache can store and retrieve null values.
	 *
	 * Null could be confused with the default return value.
	 */
	public function test_memory_cache_stores_null_value(): void {
		$cache = new MemoryCache();

		$cache->set( 'null_key', null );

		$this->assertTrue( $cache->has( 'null_key' ) );
		$this->assertNull( $cache->get( 'null_key' ) );
		// has() distinguishes stored null from missing key.
		$this->assertFalse( $cache->has( 'missing_key' ) );
	}

	/**
	 * Test MemoryCache can store and retrieve empty string.
	 */
	public function test_memory_cache_stores_empty_string(): void {
		$cache = new MemoryCache();

		$cache->set( 'empty_string_key', '' );

		$this->assertTrue( $cache->has( 'empty_string_key' ) );
		$this->assertSame( '', $cache->get( 'empty_string_key' ) );
	}

	/**
	 * Test MemoryCache can store and retrieve empty array.
	 */
	public function test_memory_cache_stores_empty_array(): void {
		$cache = new MemoryCache();

		$cache->set( 'empty_array_key', array() );

		$this->assertTrue( $cache->has( 'empty_array_key' ) );
		$this->assertSame( array(), $cache->get( 'empty_array_key' ) );
	}

	/**
	 * Test MemoryCache can store and retrieve objects.
	 */
	public function test_memory_cache_stores_objects(): void {
		$cache = new MemoryCache();

		$obj = new \stdClass();
		$obj->property = 'value';

		$cache->set( 'object_key', $obj );

		$this->assertTrue( $cache->has( 'object_key' ) );
		$retrieved = $cache->get( 'object_key' );
		$this->assertSame( $obj, $retrieved );
		$this->assertEquals( 'value', $retrieved->property );
	}

	/**
	 * Test MemoryCache handles empty string as key.
	 */
	public function test_memory_cache_empty_string_key(): void {
		$cache = new MemoryCache();

		$cache->set( '', 'empty_key_value' );

		$this->assertTrue( $cache->has( '' ) );
		$this->assertEquals( 'empty_key_value', $cache->get( '' ) );
	}

	/**
	 * Test MemoryCache handles keys with special characters.
	 */
	public function test_memory_cache_special_character_keys(): void {
		$cache = new MemoryCache();

		$special_keys = array(
			'key:with:colons'    => 'value1',
			'key.with.dots'      => 'value2',
			'key/with/slashes'   => 'value3',
			'key with spaces'    => 'value4',
			'key_with_unicode_ü' => 'value5',
		);

		foreach ( $special_keys as $key => $value ) {
			$cache->set( $key, $value );
		}

		foreach ( $special_keys as $key => $expected ) {
			$this->assertTrue( $cache->has( $key ), "Key '{$key}' should exist" );
			$this->assertEquals( $expected, $cache->get( $key ), "Key '{$key}' should return '{$expected}'" );
		}
	}

	/**
	 * Test MemoryCache zero TTL means no expiration.
	 */
	public function test_memory_cache_zero_ttl_no_expiration(): void {
		$cache = new MemoryCache();

		$cache->set( 'no_expire', 'value', 0 );

		$storage = $cache->get_storage();
		$this->assertEquals( 0, $storage['no_expire']['expires'] );

		// Key should still be available (not expired).
		$this->assertTrue( $cache->has( 'no_expire' ) );
	}

	/**
	 * Test MemoryCache delete returns false for non-existent key.
	 */
	public function test_memory_cache_delete_nonexistent_returns_false(): void {
		$cache = new MemoryCache();

		$this->assertFalse( $cache->delete( 'nonexistent_key' ) );
	}

	/**
	 * Test MemoryCache clear with prefix only clears matching keys.
	 */
	public function test_memory_cache_clear_with_prefix_preserves_others(): void {
		$cache = new MemoryCache();

		$cache->set( 'prefix_key1', 'value1' );
		$cache->set( 'prefix_key2', 'value2' );
		$cache->set( 'other_key', 'value3' );

		$cache->clear( 'prefix_' );

		$this->assertFalse( $cache->has( 'prefix_key1' ) );
		$this->assertFalse( $cache->has( 'prefix_key2' ) );
		$this->assertTrue( $cache->has( 'other_key' ) );
		$this->assertEquals( 'value3', $cache->get( 'other_key' ) );
	}

	/**
	 * Test MemoryCache increment on string numeric value.
	 */
	public function test_memory_cache_increment_string_numeric(): void {
		$cache = new MemoryCache();

		$cache->set( 'string_num', '10' );

		$result = $cache->increment( 'string_num', 5 );

		$this->assertEquals( 15, $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Test MemoryCache increment on float value.
	 */
	public function test_memory_cache_increment_float(): void {
		$cache = new MemoryCache();

		$cache->set( 'float_num', 10.5 );

		$result = $cache->increment( 'float_num', 5 );

		// is_numeric returns true for floats, so increment works.
		// Result is cast to int.
		$this->assertEquals( 15, $result );
	}
}
