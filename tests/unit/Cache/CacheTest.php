<?php
/**
 * Cache Unit Tests
 *
 * Tests for the cache abstraction layer.
 *
 * @package WPAdminHealth\Tests\Unit\Cache
 */

namespace WPAdminHealth\Tests\Unit\Cache;

use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Cache\NullCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\TestCase;

/**
 * Cache test class.
 */
class CacheTest extends TestCase {

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
}
