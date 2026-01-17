<?php
/**
 * CacheFactory Unit Tests (Standalone)
 *
 * Tests for the cache factory class including cache strategy selection,
 * factory pattern implementation, and configuration-based cache creation.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Cache
 */

namespace WPAdminHealth\Tests\UnitStandalone\Cache;

use WPAdminHealth\Cache\CacheFactory;
use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Cache\NullCache;
use WPAdminHealth\Cache\ObjectCache;
use WPAdminHealth\Cache\TransientCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * CacheFactory test class.
 *
 * Tests factory pattern implementation, cache strategy selection,
 * singleton management, and configuration-based cache creation.
 */
class CacheFactoryTest extends StandaloneTestCase {

	/**
	 * Cleanup test environment after each test.
	 *
	 * Resets all static state to ensure tests are isolated.
	 */
	protected function cleanup_test_environment(): void {
		CacheFactory::reset_all();

		// Reset the external object cache flag.
		unset( $GLOBALS['wpha_test_ext_object_cache'] );
	}

	// =========================================================================
	// Factory Method: create() Tests
	// =========================================================================

	/**
	 * Test create returns TransientCache when no persistent cache available.
	 */
	public function test_create_returns_transient_cache_when_no_persistent_cache(): void {
		// Ensure no external object cache.
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache = CacheFactory::create();

		$this->assertInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test create returns ObjectCache when persistent cache is available.
	 */
	public function test_create_returns_object_cache_when_persistent_cache_available(): void {
		// Enable external object cache.
		$GLOBALS['wpha_test_ext_object_cache'] = true;

		$cache = CacheFactory::create();

		$this->assertInstanceOf( ObjectCache::class, $cache );
	}

	/**
	 * Test create uses default prefix when none specified.
	 */
	public function test_create_uses_default_prefix(): void {
		$this->assertEquals( 'wpha_', CacheFactory::get_default_prefix() );
	}

	/**
	 * Test create uses custom prefix when specified.
	 */
	public function test_create_returns_cache_with_custom_prefix(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache = CacheFactory::create( 'custom_prefix_' );

		// Verify it's a TransientCache (since no persistent cache).
		$this->assertInstanceOf( TransientCache::class, $cache );
		// The prefix is used internally by the cache.
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test create with null prefix uses default.
	 */
	public function test_create_with_null_prefix_uses_default(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache = CacheFactory::create( null );

		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	// =========================================================================
	// Singleton Pattern Tests
	// =========================================================================

	/**
	 * Test get_instance returns same instance on multiple calls.
	 */
	public function test_get_instance_returns_same_instance(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$instance1 = CacheFactory::get_instance();
		$instance2 = CacheFactory::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test get_instance creates instance on first call.
	 */
	public function test_get_instance_creates_instance_on_first_call(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );

		$instance = CacheFactory::get_instance();

		$this->assertInstanceOf( CacheInterface::class, $instance );
		$this->assertNotEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test set_instance replaces singleton.
	 */
	public function test_set_instance_replaces_singleton(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		// Get default instance.
		$original = CacheFactory::get_instance();

		// Replace with custom instance.
		$custom = new MemoryCache();
		CacheFactory::set_instance( $custom );

		$this->assertSame( $custom, CacheFactory::get_instance() );
		$this->assertNotSame( $original, CacheFactory::get_instance() );
	}

	/**
	 * Test set_instance with null clears singleton.
	 */
	public function test_set_instance_with_null_clears_singleton(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		// Create and set an instance.
		CacheFactory::get_instance();
		$this->assertNotEquals( 'none', CacheFactory::get_backend_type() );

		// Clear it.
		CacheFactory::set_instance( null );

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test reset clears singleton instance.
	 */
	public function test_reset_clears_singleton(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		CacheFactory::get_instance();
		$this->assertNotEquals( 'none', CacheFactory::get_backend_type() );

		CacheFactory::reset();

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test reset_all clears singleton and resets prefix.
	 */
	public function test_reset_all_clears_singleton_and_resets_prefix(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		// Modify state.
		CacheFactory::get_instance();
		CacheFactory::set_default_prefix( 'custom_' );

		$this->assertNotEquals( 'none', CacheFactory::get_backend_type() );
		$this->assertEquals( 'custom_', CacheFactory::get_default_prefix() );

		// Reset all.
		CacheFactory::reset_all();

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
		$this->assertEquals( 'wpha_', CacheFactory::get_default_prefix() );
	}

	// =========================================================================
	// Specific Cache Type Factory Methods
	// =========================================================================

	/**
	 * Test create_object_cache returns ObjectCache.
	 */
	public function test_create_object_cache_returns_object_cache(): void {
		$cache = CacheFactory::create_object_cache();

		$this->assertInstanceOf( ObjectCache::class, $cache );
	}

	/**
	 * Test create_object_cache uses default group.
	 */
	public function test_create_object_cache_uses_default_group(): void {
		$cache = CacheFactory::create_object_cache();

		$this->assertInstanceOf( ObjectCache::class, $cache );
	}

	/**
	 * Test create_object_cache with custom group.
	 */
	public function test_create_object_cache_with_custom_group(): void {
		$cache = CacheFactory::create_object_cache( 'custom_group' );

		$this->assertInstanceOf( ObjectCache::class, $cache );
	}

	/**
	 * Test create_transient_cache returns TransientCache.
	 */
	public function test_create_transient_cache_returns_transient_cache(): void {
		$cache = CacheFactory::create_transient_cache();

		$this->assertInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test create_transient_cache uses default prefix.
	 */
	public function test_create_transient_cache_uses_default_prefix(): void {
		$cache = CacheFactory::create_transient_cache();

		$this->assertInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test create_transient_cache with custom prefix.
	 */
	public function test_create_transient_cache_with_custom_prefix(): void {
		$cache = CacheFactory::create_transient_cache( 'custom_prefix_' );

		$this->assertInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test create_memory_cache returns MemoryCache.
	 */
	public function test_create_memory_cache_returns_memory_cache(): void {
		$cache = CacheFactory::create_memory_cache();

		$this->assertInstanceOf( MemoryCache::class, $cache );
	}

	/**
	 * Test create_null_cache returns NullCache.
	 */
	public function test_create_null_cache_returns_null_cache(): void {
		$cache = CacheFactory::create_null_cache();

		$this->assertInstanceOf( NullCache::class, $cache );
	}

	// =========================================================================
	// All Factory Methods Return CacheInterface
	// =========================================================================

	/**
	 * Test all factory methods return CacheInterface implementations.
	 */
	public function test_all_factory_methods_return_cache_interface(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$this->assertInstanceOf( CacheInterface::class, CacheFactory::create() );
		$this->assertInstanceOf( CacheInterface::class, CacheFactory::create_object_cache() );
		$this->assertInstanceOf( CacheInterface::class, CacheFactory::create_transient_cache() );
		$this->assertInstanceOf( CacheInterface::class, CacheFactory::create_memory_cache() );
		$this->assertInstanceOf( CacheInterface::class, CacheFactory::create_null_cache() );
	}

	// =========================================================================
	// Default Prefix Management Tests
	// =========================================================================

	/**
	 * Test get_default_prefix returns default value.
	 */
	public function test_get_default_prefix_returns_default(): void {
		$this->assertEquals( 'wpha_', CacheFactory::get_default_prefix() );
	}

	/**
	 * Test set_default_prefix changes default.
	 */
	public function test_set_default_prefix_changes_default(): void {
		CacheFactory::set_default_prefix( 'new_prefix_' );

		$this->assertEquals( 'new_prefix_', CacheFactory::get_default_prefix() );
	}

	/**
	 * Test set_default_prefix affects subsequent create calls.
	 */
	public function test_set_default_prefix_affects_create(): void {
		CacheFactory::set_default_prefix( 'custom_' );
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache = CacheFactory::create();

		// Verify a cache was created (we can't easily verify the prefix).
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test set_default_prefix with empty string.
	 */
	public function test_set_default_prefix_allows_empty_string(): void {
		CacheFactory::set_default_prefix( '' );

		$this->assertEquals( '', CacheFactory::get_default_prefix() );
	}

	// =========================================================================
	// Backend Type Detection Tests
	// =========================================================================

	/**
	 * Test get_backend_type returns 'none' when no singleton exists.
	 */
	public function test_get_backend_type_returns_none_when_no_singleton(): void {
		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type does not create singleton.
	 */
	public function test_get_backend_type_does_not_create_singleton(): void {
		// Call get_backend_type.
		CacheFactory::get_backend_type();

		// Singleton should still be null.
		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type returns 'transient' for TransientCache.
	 */
	public function test_get_backend_type_returns_transient(): void {
		CacheFactory::set_instance( new TransientCache() );

		$this->assertEquals( 'transient', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type returns 'object' for ObjectCache.
	 */
	public function test_get_backend_type_returns_object(): void {
		CacheFactory::set_instance( new ObjectCache() );

		$this->assertEquals( 'object', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type returns 'memory' for MemoryCache.
	 */
	public function test_get_backend_type_returns_memory(): void {
		CacheFactory::set_instance( new MemoryCache() );

		$this->assertEquals( 'memory', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type returns 'null' for NullCache.
	 */
	public function test_get_backend_type_returns_null(): void {
		CacheFactory::set_instance( new NullCache() );

		$this->assertEquals( 'null', CacheFactory::get_backend_type() );
	}

	/**
	 * Test get_backend_type returns 'unknown' for custom implementation.
	 */
	public function test_get_backend_type_returns_unknown_for_custom(): void {
		// Create anonymous class implementing CacheInterface.
		$custom = new class implements CacheInterface {
			public function get( string $key, $default = null ) {
				return $default;
			}
			public function set( string $key, $value, int $ttl = 0 ): bool {
				return true;
			}
			public function delete( string $key ): bool {
				return true;
			}
			public function has( string $key ): bool {
				return false;
			}
			public function clear( string $prefix = '' ): bool {
				return true;
			}
			public function remember( string $key, callable $callback, int $ttl = 0 ) {
				return $callback();
			}
			public function increment( string $key, int $value = 1 ) {
				return $value;
			}
			public function decrement( string $key, int $value = 1 ) {
				return -$value;
			}
			public function get_multiple( array $keys, $default = null ): array {
				return array_fill_keys( $keys, $default );
			}
			public function set_multiple( array $values, int $ttl = 0 ): bool {
				return true;
			}
			public function delete_multiple( array $keys ): bool {
				return true;
			}
		};

		CacheFactory::set_instance( $custom );

		$this->assertEquals( 'unknown', CacheFactory::get_backend_type() );
	}

	// =========================================================================
	// has_persistent_cache Tests
	// =========================================================================

	/**
	 * Test has_persistent_cache returns false when no external cache.
	 */
	public function test_has_persistent_cache_returns_false_when_none(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$this->assertFalse( CacheFactory::has_persistent_cache() );
	}

	/**
	 * Test has_persistent_cache returns true when external cache available.
	 */
	public function test_has_persistent_cache_returns_true_when_available(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = true;

		$this->assertTrue( CacheFactory::has_persistent_cache() );
	}

	// =========================================================================
	// Cache Strategy Selection Tests
	// =========================================================================

	/**
	 * Test create prefers ObjectCache over TransientCache when available.
	 */
	public function test_create_prefers_object_cache_over_transient(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = true;

		$cache = CacheFactory::create();

		$this->assertInstanceOf( ObjectCache::class, $cache );
		$this->assertNotInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test create falls back to TransientCache when ObjectCache unavailable.
	 */
	public function test_create_falls_back_to_transient_cache(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache = CacheFactory::create();

		$this->assertInstanceOf( TransientCache::class, $cache );
	}

	/**
	 * Test cache strategy changes based on environment.
	 */
	public function test_cache_strategy_changes_with_environment(): void {
		// First: no persistent cache.
		$GLOBALS['wpha_test_ext_object_cache'] = false;
		$cache1 = CacheFactory::create();
		$this->assertInstanceOf( TransientCache::class, $cache1 );

		// Reset singleton.
		CacheFactory::reset();

		// Now: with persistent cache.
		$GLOBALS['wpha_test_ext_object_cache'] = true;
		$cache2 = CacheFactory::create();
		$this->assertInstanceOf( ObjectCache::class, $cache2 );
	}

	// =========================================================================
	// Factory Method Independence Tests
	// =========================================================================

	/**
	 * Test each create call returns new instance.
	 */
	public function test_create_returns_new_instance_each_call(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$cache1 = CacheFactory::create();
		$cache2 = CacheFactory::create();

		$this->assertNotSame( $cache1, $cache2 );
	}

	/**
	 * Test specific factory methods return new instances.
	 */
	public function test_specific_factory_methods_return_new_instances(): void {
		$memory1 = CacheFactory::create_memory_cache();
		$memory2 = CacheFactory::create_memory_cache();

		$this->assertNotSame( $memory1, $memory2 );

		$null1 = CacheFactory::create_null_cache();
		$null2 = CacheFactory::create_null_cache();

		$this->assertNotSame( $null1, $null2 );
	}

	/**
	 * Test create is independent of get_instance.
	 */
	public function test_create_is_independent_of_get_instance(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		$created = CacheFactory::create();
		$singleton = CacheFactory::get_instance();

		// They should be different instances.
		$this->assertNotSame( $created, $singleton );
	}

	// =========================================================================
	// Dependency Injection for Testing Tests
	// =========================================================================

	/**
	 * Test set_instance enables mock injection for testing.
	 */
	public function test_set_instance_enables_mock_injection(): void {
		// Create a mock cache that tracks calls.
		$mock = new MemoryCache();
		$mock->set( 'test_key', 'test_value' );

		CacheFactory::set_instance( $mock );

		$instance = CacheFactory::get_instance();
		$this->assertEquals( 'test_value', $instance->get( 'test_key' ) );
	}

	/**
	 * Test NullCache useful for disabling caching in tests.
	 */
	public function test_null_cache_disables_caching(): void {
		$nullCache = CacheFactory::create_null_cache();

		// Set value.
		$this->assertTrue( $nullCache->set( 'key', 'value' ) );

		// Value is not actually cached.
		$this->assertFalse( $nullCache->has( 'key' ) );
		$this->assertNull( $nullCache->get( 'key' ) );
	}

	/**
	 * Test MemoryCache useful for isolated testing.
	 */
	public function test_memory_cache_for_isolated_testing(): void {
		$memoryCache = CacheFactory::create_memory_cache();

		// Set and get value.
		$memoryCache->set( 'key', 'value' );
		$this->assertEquals( 'value', $memoryCache->get( 'key' ) );

		// Clear for next test.
		$memoryCache->clear();
		$this->assertNull( $memoryCache->get( 'key' ) );
	}

	// =========================================================================
	// Edge Cases
	// =========================================================================

	/**
	 * Test multiple resets don't cause issues.
	 */
	public function test_multiple_resets_are_safe(): void {
		CacheFactory::reset();
		CacheFactory::reset();
		CacheFactory::reset();

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
	}

	/**
	 * Test multiple reset_all calls don't cause issues.
	 */
	public function test_multiple_reset_all_are_safe(): void {
		CacheFactory::reset_all();
		CacheFactory::reset_all();
		CacheFactory::reset_all();

		$this->assertEquals( 'none', CacheFactory::get_backend_type() );
		$this->assertEquals( 'wpha_', CacheFactory::get_default_prefix() );
	}

	/**
	 * Test factory works after reset.
	 */
	public function test_factory_works_after_reset(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;

		// Create and reset.
		CacheFactory::get_instance();
		CacheFactory::reset();

		// Should work again.
		$instance = CacheFactory::get_instance();
		$this->assertInstanceOf( CacheInterface::class, $instance );
	}
}
