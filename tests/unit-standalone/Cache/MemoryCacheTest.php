<?php
/**
 * MemoryCache Unit Tests (Standalone)
 *
 * Tests for the in-memory cache implementation including
 * memory limits, LRU eviction, and proper cleanup.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Cache
 */

namespace WPAdminHealth\Tests\UnitStandalone\Cache;

use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * MemoryCache test class.
 *
 * Tests in-memory caching, memory limits, LRU eviction,
 * TTL expiration, and cleanup mechanisms.
 */
class MemoryCacheTest extends StandaloneTestCase {

	/**
	 * Mock time for testing expiration.
	 *
	 * @var int
	 */
	private int $mock_time = 1000000;

	/**
	 * Cache instance for testing.
	 *
	 * @var MemoryCache
	 */
	private MemoryCache $cache;

	/**
	 * Setup test environment before each test.
	 */
	protected function setup_test_environment(): void {
		$this->mock_time = 1000000;
		$this->cache     = new MemoryCache();
		$this->cache->set_time_provider( fn() => $this->mock_time );
	}

	/**
	 * Cleanup test environment after each test.
	 */
	protected function cleanup_test_environment(): void {
		$this->cache->flush();
	}

	// =========================================================================
	// Interface Implementation Tests
	// =========================================================================

	/**
	 * Test MemoryCache implements CacheInterface.
	 */
	public function test_implements_cache_interface(): void {
		$this->assertInstanceOf( CacheInterface::class, $this->cache );
	}

	/**
	 * Test default constructor uses DEFAULT_MAX_ITEMS.
	 */
	public function test_constructor_default_max_items(): void {
		$cache = new MemoryCache();
		$this->assertEquals( MemoryCache::DEFAULT_MAX_ITEMS, $cache->get_max_items() );
	}

	/**
	 * Test constructor with custom max items.
	 */
	public function test_constructor_custom_max_items(): void {
		$cache = new MemoryCache( 500 );
		$this->assertEquals( 500, $cache->get_max_items() );
	}

	/**
	 * Test constructor with unlimited items.
	 */
	public function test_constructor_unlimited_items(): void {
		$cache = new MemoryCache( 0 );
		$this->assertEquals( 0, $cache->get_max_items() );
	}

	// =========================================================================
	// Basic Get/Set/Delete/Has Tests
	// =========================================================================

	/**
	 * Test set and get basic value.
	 */
	public function test_set_and_get_value(): void {
		$this->assertTrue( $this->cache->set( 'key', 'value' ) );
		$this->assertEquals( 'value', $this->cache->get( 'key' ) );
	}

	/**
	 * Test get returns default for missing key.
	 */
	public function test_get_returns_default_for_missing_key(): void {
		$this->assertNull( $this->cache->get( 'nonexistent' ) );
		$this->assertEquals( 'default', $this->cache->get( 'nonexistent', 'default' ) );
	}

	/**
	 * Test has returns true for existing key.
	 */
	public function test_has_returns_true_for_existing_key(): void {
		$this->cache->set( 'key', 'value' );
		$this->assertTrue( $this->cache->has( 'key' ) );
	}

	/**
	 * Test has returns false for missing key.
	 */
	public function test_has_returns_false_for_missing_key(): void {
		$this->assertFalse( $this->cache->has( 'nonexistent' ) );
	}

	/**
	 * Test delete removes existing key.
	 */
	public function test_delete_removes_existing_key(): void {
		$this->cache->set( 'key', 'value' );
		$this->assertTrue( $this->cache->delete( 'key' ) );
		$this->assertFalse( $this->cache->has( 'key' ) );
	}

	/**
	 * Test delete returns false for nonexistent key.
	 */
	public function test_delete_returns_false_for_nonexistent_key(): void {
		$this->assertFalse( $this->cache->delete( 'nonexistent' ) );
	}

	/**
	 * Test set overwrites existing value.
	 */
	public function test_set_overwrites_existing_value(): void {
		$this->cache->set( 'key', 'value1' );
		$this->cache->set( 'key', 'value2' );
		$this->assertEquals( 'value2', $this->cache->get( 'key' ) );
	}

	// =========================================================================
	// TTL/Expiration Tests
	// =========================================================================

	/**
	 * Test set with TTL.
	 */
	public function test_set_with_ttl(): void {
		$this->cache->set( 'key', 'value', 3600 );
		$this->assertEquals( 'value', $this->cache->get( 'key' ) );
	}

	/**
	 * Test value expires after TTL.
	 */
	public function test_value_expires_after_ttl(): void {
		$this->cache->set( 'key', 'value', 3600 );

		// Advance time past expiration.
		$this->mock_time += 3601;

		$this->assertNull( $this->cache->get( 'key' ) );
		$this->assertFalse( $this->cache->has( 'key' ) );
	}

	/**
	 * Test value does not expire before TTL.
	 */
	public function test_value_does_not_expire_before_ttl(): void {
		$this->cache->set( 'key', 'value', 3600 );

		// Advance time just before expiration.
		$this->mock_time += 3599;

		$this->assertEquals( 'value', $this->cache->get( 'key' ) );
	}

	/**
	 * Test zero TTL means no expiration.
	 */
	public function test_zero_ttl_no_expiration(): void {
		$this->cache->set( 'key', 'value', 0 );

		// Advance time significantly.
		$this->mock_time += 1000000;

		$this->assertEquals( 'value', $this->cache->get( 'key' ) );
	}

	/**
	 * Test expired items are cleaned up on access.
	 */
	public function test_expired_items_cleaned_on_access(): void {
		$this->cache->set( 'key', 'value', 100 );

		// Advance past expiration.
		$this->mock_time += 101;

		// Access triggers cleanup.
		$this->cache->get( 'key' );

		// Verify item is removed from storage.
		$this->assertEquals( 0, $this->cache->count() );
	}

	// =========================================================================
	// Memory Limits Tests
	// =========================================================================

	/**
	 * Test max items limit is enforced.
	 */
	public function test_max_items_limit_enforced(): void {
		$cache = new MemoryCache( 5 );
		$cache->set_time_provider( fn() => $this->mock_time );

		// Add 5 items.
		for ( $i = 1; $i <= 5; $i++ ) {
			$cache->set( "key{$i}", "value{$i}" );
		}

		$this->assertEquals( 5, $cache->count() );

		// Add 6th item should evict one.
		$cache->set( 'key6', 'value6' );
		$this->assertEquals( 5, $cache->count() );
	}

	/**
	 * Test LRU eviction removes least recently used item.
	 */
	public function test_lru_eviction_removes_oldest_accessed(): void {
		$cache = new MemoryCache( 3 );
		$cache->set_time_provider( fn() => $this->mock_time );

		// Add 3 items at different times.
		$cache->set( 'key1', 'value1' );
		$this->mock_time += 1;
		$cache->set( 'key2', 'value2' );
		$this->mock_time += 1;
		$cache->set( 'key3', 'value3' );

		// Access key1 to make it recently used.
		$this->mock_time += 1;
		$cache->get( 'key1' );

		// Add key4 - should evict key2 (least recently accessed).
		$this->mock_time += 1;
		$cache->set( 'key4', 'value4' );

		$this->assertTrue( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
		$this->assertTrue( $cache->has( 'key3' ) );
		$this->assertTrue( $cache->has( 'key4' ) );
	}

	/**
	 * Test eviction tracks statistics.
	 */
	public function test_eviction_tracks_statistics(): void {
		$cache = new MemoryCache( 2 );
		$cache->set_time_provider( fn() => $this->mock_time );

		$cache->set( 'key1', 'value1' );
		$cache->set( 'key2', 'value2' );
		$cache->set( 'key3', 'value3' ); // Evicts key1.

		$stats = $cache->get_stats();
		$this->assertEquals( 1, $stats['evictions'] );
	}

	/**
	 * Test unlimited cache has no evictions.
	 */
	public function test_unlimited_cache_no_evictions(): void {
		$cache = new MemoryCache( 0 );
		$cache->set_time_provider( fn() => $this->mock_time );

		// Add many items.
		for ( $i = 1; $i <= 100; $i++ ) {
			$cache->set( "key{$i}", "value{$i}" );
		}

		$this->assertEquals( 100, $cache->count() );
		$this->assertEquals( 0, $cache->get_stats()['evictions'] );
	}

	/**
	 * Test set_max_items evicts excess items.
	 */
	public function test_set_max_items_evicts_excess(): void {
		$cache = new MemoryCache( 10 );
		$cache->set_time_provider( fn() => $this->mock_time );

		// Add 10 items.
		for ( $i = 1; $i <= 10; $i++ ) {
			$cache->set( "key{$i}", "value{$i}" );
			$this->mock_time += 1;
		}

		$this->assertEquals( 10, $cache->count() );

		// Reduce limit.
		$cache->set_max_items( 5 );

		$this->assertEquals( 5, $cache->count() );
		$this->assertEquals( 5, $cache->get_max_items() );
	}

	/**
	 * Test eviction prefers expired items first.
	 */
	public function test_eviction_prefers_expired_items(): void {
		$cache = new MemoryCache( 3 );
		$cache->set_time_provider( fn() => $this->mock_time );

		// Add items with TTL.
		$cache->set( 'expiring', 'value', 100 );
		$this->mock_time += 1;
		$cache->set( 'permanent1', 'value' );
		$this->mock_time += 1;
		$cache->set( 'permanent2', 'value' );

		// Advance past expiration.
		$this->mock_time += 150;

		// Add new item - should clean expired first, no LRU eviction needed.
		$cache->set( 'new', 'value' );

		$this->assertFalse( $cache->has( 'expiring' ) );
		$this->assertTrue( $cache->has( 'permanent1' ) );
		$this->assertTrue( $cache->has( 'permanent2' ) );
		$this->assertTrue( $cache->has( 'new' ) );
	}

	// =========================================================================
	// Cleanup/GC Tests
	// =========================================================================

	/**
	 * Test gc removes expired items.
	 */
	public function test_gc_removes_expired_items(): void {
		$this->cache->set( 'expiring1', 'value', 100 );
		$this->cache->set( 'expiring2', 'value', 200 );
		$this->cache->set( 'permanent', 'value', 0 );

		// Advance past first expiration.
		$this->mock_time += 150;

		$removed = $this->cache->gc();

		$this->assertEquals( 1, $removed );
		$this->assertFalse( $this->cache->has( 'expiring1' ) );
		$this->assertTrue( $this->cache->has( 'expiring2' ) );
		$this->assertTrue( $this->cache->has( 'permanent' ) );
	}

	/**
	 * Test gc removes all expired items.
	 */
	public function test_gc_removes_all_expired_items(): void {
		$this->cache->set( 'expiring1', 'value', 100 );
		$this->cache->set( 'expiring2', 'value', 100 );
		$this->cache->set( 'permanent', 'value', 0 );

		// Advance past expiration.
		$this->mock_time += 150;

		$removed = $this->cache->gc();

		$this->assertEquals( 2, $removed );
		$this->assertEquals( 1, $this->cache->count() );
	}

	/**
	 * Test gc returns zero when nothing expired.
	 */
	public function test_gc_returns_zero_when_nothing_expired(): void {
		$this->cache->set( 'key1', 'value', 3600 );
		$this->cache->set( 'key2', 'value', 0 );

		$removed = $this->cache->gc();

		$this->assertEquals( 0, $removed );
		$this->assertEquals( 2, $this->cache->count() );
	}

	// =========================================================================
	// Clear/Flush Tests
	// =========================================================================

	/**
	 * Test clear removes all items.
	 */
	public function test_clear_removes_all_items(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$this->assertTrue( $this->cache->clear() );
		$this->assertEquals( 0, $this->cache->count() );
	}

	/**
	 * Test clear with prefix removes matching items only.
	 */
	public function test_clear_with_prefix(): void {
		$this->cache->set( 'session_user1', 'value' );
		$this->cache->set( 'session_user2', 'value' );
		$this->cache->set( 'other_key', 'value' );

		$this->cache->clear( 'session_' );

		$this->assertFalse( $this->cache->has( 'session_user1' ) );
		$this->assertFalse( $this->cache->has( 'session_user2' ) );
		$this->assertTrue( $this->cache->has( 'other_key' ) );
	}

	/**
	 * Test flush clears storage and resets stats.
	 */
	public function test_flush_clears_storage_and_stats(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );
		$this->cache->get( 'key1' ); // Generate stats.

		$this->cache->flush();

		$this->assertEquals( 0, $this->cache->count() );

		$stats = $this->cache->get_stats();
		$this->assertEquals( 0, $stats['hits'] );
		$this->assertEquals( 0, $stats['misses'] );
		$this->assertEquals( 0, $stats['writes'] );
		$this->assertEquals( 0, $stats['deletes'] );
		$this->assertEquals( 0, $stats['evictions'] );
	}

	// =========================================================================
	// Remember Tests
	// =========================================================================

	/**
	 * Test remember returns cached value on hit.
	 */
	public function test_remember_returns_cached_value(): void {
		$this->cache->set( 'key', 'cached_value' );

		$executed = false;
		$result   = $this->cache->remember(
			'key',
			function () use ( &$executed ) {
				$executed = true;
				return 'callback_value';
			}
		);

		$this->assertFalse( $executed );
		$this->assertEquals( 'cached_value', $result );
	}

	/**
	 * Test remember executes callback on miss.
	 */
	public function test_remember_executes_callback_on_miss(): void {
		$executed = false;
		$result   = $this->cache->remember(
			'key',
			function () use ( &$executed ) {
				$executed = true;
				return 'callback_value';
			}
		);

		$this->assertTrue( $executed );
		$this->assertEquals( 'callback_value', $result );
	}

	/**
	 * Test remember caches callback result.
	 */
	public function test_remember_caches_callback_result(): void {
		$call_count = 0;
		$callback   = function () use ( &$call_count ) {
			++$call_count;
			return 'value';
		};

		$this->cache->remember( 'key', $callback );
		$this->cache->remember( 'key', $callback );

		$this->assertEquals( 1, $call_count );
	}

	/**
	 * Test remember with TTL.
	 */
	public function test_remember_with_ttl(): void {
		$result = $this->cache->remember( 'key', fn() => 'value', 3600 );

		$this->assertEquals( 'value', $result );

		// Advance past expiration.
		$this->mock_time += 3601;

		// Should execute callback again.
		$executed = false;
		$this->cache->remember(
			'key',
			function () use ( &$executed ) {
				$executed = true;
				return 'new_value';
			},
			3600
		);

		$this->assertTrue( $executed );
	}

	/**
	 * Test remember updates access time.
	 */
	public function test_remember_updates_access_time(): void {
		$this->cache->set( 'key', 'value' );

		$this->mock_time += 100;
		$this->cache->remember( 'key', fn() => 'unused' );

		$storage = $this->cache->get_storage();
		$this->assertEquals( $this->mock_time, $storage['key']['accessed'] );
	}

	// =========================================================================
	// Increment/Decrement Tests
	// =========================================================================

	/**
	 * Test increment creates new key.
	 */
	public function test_increment_creates_new_key(): void {
		$result = $this->cache->increment( 'counter' );
		$this->assertEquals( 1, $result );
		$this->assertEquals( 1, $this->cache->get( 'counter' ) );
	}

	/**
	 * Test increment with custom value.
	 */
	public function test_increment_with_custom_value(): void {
		$result = $this->cache->increment( 'counter', 5 );
		$this->assertEquals( 5, $result );
	}

	/**
	 * Test increment existing value.
	 */
	public function test_increment_existing_value(): void {
		$this->cache->set( 'counter', 10 );
		$result = $this->cache->increment( 'counter', 5 );
		$this->assertEquals( 15, $result );
	}

	/**
	 * Test increment fails on non-numeric value.
	 */
	public function test_increment_fails_on_non_numeric(): void {
		$this->cache->set( 'key', 'string_value' );
		$result = $this->cache->increment( 'key' );
		$this->assertFalse( $result );
	}

	/**
	 * Test decrement creates new key.
	 */
	public function test_decrement_creates_new_key(): void {
		$result = $this->cache->decrement( 'counter' );
		$this->assertEquals( -1, $result );
	}

	/**
	 * Test decrement existing value.
	 */
	public function test_decrement_existing_value(): void {
		$this->cache->set( 'counter', 10 );
		$result = $this->cache->decrement( 'counter', 3 );
		$this->assertEquals( 7, $result );
	}

	/**
	 * Test increment on expired key.
	 */
	public function test_increment_on_expired_key(): void {
		$this->cache->set( 'counter', 10, 100 );

		// Advance past expiration.
		$this->mock_time += 101;

		$result = $this->cache->increment( 'counter', 5 );
		$this->assertEquals( 5, $result ); // Starts fresh.
	}

	// =========================================================================
	// Multiple Operations Tests
	// =========================================================================

	/**
	 * Test get_multiple returns all values.
	 */
	public function test_get_multiple_returns_all_values(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$results = $this->cache->get_multiple( array( 'key1', 'key2', 'key3' ) );

		$this->assertEquals(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => null,
			),
			$results
		);
	}

	/**
	 * Test get_multiple with custom default.
	 */
	public function test_get_multiple_custom_default(): void {
		$results = $this->cache->get_multiple( array( 'key1', 'key2' ), 'default' );

		$this->assertEquals(
			array(
				'key1' => 'default',
				'key2' => 'default',
			),
			$results
		);
	}

	/**
	 * Test set_multiple sets all values.
	 */
	public function test_set_multiple_sets_all_values(): void {
		$result = $this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			)
		);

		$this->assertTrue( $result );
		$this->assertEquals( 'value1', $this->cache->get( 'key1' ) );
		$this->assertEquals( 'value2', $this->cache->get( 'key2' ) );
	}

	/**
	 * Test set_multiple with TTL.
	 */
	public function test_set_multiple_with_ttl(): void {
		$this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			),
			100
		);

		// Advance past expiration.
		$this->mock_time += 101;

		$this->assertNull( $this->cache->get( 'key1' ) );
		$this->assertNull( $this->cache->get( 'key2' ) );
	}

	/**
	 * Test delete_multiple removes all keys.
	 */
	public function test_delete_multiple_removes_all_keys(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );
		$this->cache->set( 'key3', 'value3' );

		$result = $this->cache->delete_multiple( array( 'key1', 'key2' ) );

		$this->assertTrue( $result );
		$this->assertFalse( $this->cache->has( 'key1' ) );
		$this->assertFalse( $this->cache->has( 'key2' ) );
		$this->assertTrue( $this->cache->has( 'key3' ) );
	}

	// =========================================================================
	// Statistics Tests
	// =========================================================================

	/**
	 * Test statistics track hits.
	 */
	public function test_stats_track_hits(): void {
		$this->cache->set( 'key', 'value' );
		$this->cache->get( 'key' );
		$this->cache->get( 'key' );

		$stats = $this->cache->get_stats();
		$this->assertEquals( 2, $stats['hits'] );
	}

	/**
	 * Test statistics track misses.
	 */
	public function test_stats_track_misses(): void {
		$this->cache->get( 'nonexistent' );
		$this->cache->get( 'also_missing' );

		$stats = $this->cache->get_stats();
		$this->assertEquals( 2, $stats['misses'] );
	}

	/**
	 * Test statistics track writes.
	 */
	public function test_stats_track_writes(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$stats = $this->cache->get_stats();
		$this->assertEquals( 2, $stats['writes'] );
	}

	/**
	 * Test statistics track deletes.
	 */
	public function test_stats_track_deletes(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );
		$this->cache->delete( 'key1' );
		$this->cache->delete( 'nonexistent' ); // Should not count.

		$stats = $this->cache->get_stats();
		$this->assertEquals( 1, $stats['deletes'] );
	}

	/**
	 * Test reset_stats clears all statistics.
	 */
	public function test_reset_stats_clears_statistics(): void {
		$this->cache->set( 'key', 'value' );
		$this->cache->get( 'key' );
		$this->cache->delete( 'key' );

		$this->cache->reset_stats();

		$stats = $this->cache->get_stats();
		$this->assertEquals( 0, $stats['hits'] );
		$this->assertEquals( 0, $stats['misses'] );
		$this->assertEquals( 0, $stats['writes'] );
		$this->assertEquals( 0, $stats['deletes'] );
		$this->assertEquals( 0, $stats['evictions'] );
	}

	// =========================================================================
	// Access Time Tracking Tests
	// =========================================================================

	/**
	 * Test get updates access time.
	 */
	public function test_get_updates_access_time(): void {
		$this->cache->set( 'key', 'value' );

		$this->mock_time += 100;
		$this->cache->get( 'key' );

		$storage = $this->cache->get_storage();
		$this->assertEquals( $this->mock_time, $storage['key']['accessed'] );
	}

	/**
	 * Test set sets initial access time.
	 */
	public function test_set_sets_initial_access_time(): void {
		$this->cache->set( 'key', 'value' );

		$storage = $this->cache->get_storage();
		$this->assertEquals( $this->mock_time, $storage['key']['accessed'] );
	}

	/**
	 * Test increment updates access time.
	 */
	public function test_increment_updates_access_time(): void {
		$this->cache->set( 'counter', 10 );

		$this->mock_time += 100;
		$this->cache->increment( 'counter' );

		$storage = $this->cache->get_storage();
		$this->assertEquals( $this->mock_time, $storage['counter']['accessed'] );
	}

	// =========================================================================
	// Inspection Methods Tests
	// =========================================================================

	/**
	 * Test get_keys returns all keys.
	 */
	public function test_get_keys_returns_all_keys(): void {
		$this->cache->set( 'key1', 'value1' );
		$this->cache->set( 'key2', 'value2' );

		$keys = $this->cache->get_keys();

		$this->assertCount( 2, $keys );
		$this->assertContains( 'key1', $keys );
		$this->assertContains( 'key2', $keys );
	}

	/**
	 * Test count returns correct count.
	 */
	public function test_count_returns_correct_count(): void {
		$this->assertEquals( 0, $this->cache->count() );

		$this->cache->set( 'key1', 'value1' );
		$this->assertEquals( 1, $this->cache->count() );

		$this->cache->set( 'key2', 'value2' );
		$this->assertEquals( 2, $this->cache->count() );

		$this->cache->delete( 'key1' );
		$this->assertEquals( 1, $this->cache->count() );
	}

	/**
	 * Test get_storage returns raw storage.
	 */
	public function test_get_storage_returns_raw_storage(): void {
		$this->cache->set( 'key', 'value', 3600 );

		$storage = $this->cache->get_storage();

		$this->assertArrayHasKey( 'key', $storage );
		$this->assertEquals( 'value', $storage['key']['value'] );
		$this->assertEquals( $this->mock_time + 3600, $storage['key']['expires'] );
		$this->assertEquals( $this->mock_time, $storage['key']['accessed'] );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test storing false value.
	 */
	public function test_storing_false_value(): void {
		$this->cache->set( 'key', false );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertFalse( $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing null value.
	 */
	public function test_storing_null_value(): void {
		$this->cache->set( 'key', null );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing empty string.
	 */
	public function test_storing_empty_string(): void {
		$this->cache->set( 'key', '' );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertEquals( '', $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing zero.
	 */
	public function test_storing_zero(): void {
		$this->cache->set( 'key', 0 );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertEquals( 0, $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing array.
	 */
	public function test_storing_array(): void {
		$array = array( 'nested' => array( 'data' => 'value' ) );
		$this->cache->set( 'key', $array );

		$this->assertEquals( $array, $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing object.
	 */
	public function test_storing_object(): void {
		$obj       = new \stdClass();
		$obj->prop = 'value';

		$this->cache->set( 'key', $obj );

		$retrieved = $this->cache->get( 'key' );
		$this->assertEquals( 'value', $retrieved->prop );
	}

	/**
	 * Test empty key.
	 */
	public function test_empty_key(): void {
		$this->cache->set( '', 'value' );
		$this->assertEquals( 'value', $this->cache->get( '' ) );
	}

	/**
	 * Test special characters in key.
	 */
	public function test_special_characters_in_key(): void {
		$key = 'key:with/special\\chars!@#$%';
		$this->cache->set( $key, 'value' );
		$this->assertEquals( 'value', $this->cache->get( $key ) );
	}

	// =========================================================================
	// Time Provider Tests
	// =========================================================================

	/**
	 * Test set_time_provider changes time source.
	 */
	public function test_set_time_provider(): void {
		$custom_time = 9999999;
		$this->cache->set_time_provider( fn() => $custom_time );

		$this->cache->set( 'key', 'value', 100 );

		$storage = $this->cache->get_storage();
		$this->assertEquals( $custom_time + 100, $storage['key']['expires'] );
	}

	/**
	 * Test null time provider uses real time.
	 */
	public function test_null_time_provider_uses_real_time(): void {
		$cache = new MemoryCache();
		$cache->set( 'key', 'value', 100 );

		$storage = $cache->get_storage();
		$this->assertGreaterThan( time() - 1, $storage['key']['expires'] - 100 );
		$this->assertLessThanOrEqual( time() + 1, $storage['key']['expires'] - 100 );
	}
}
