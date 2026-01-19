<?php
/**
 * Analyzer Cache Behavior Tests (Standalone)
 *
 * Tests for Database Analyzer caching using CacheInterface and CacheKeys.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Database
 */

namespace WPAdminHealth\Tests\UnitStandalone\Database;

use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Support\CacheKeys;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Analyzer cache behavior test class.
 *
 * Tests cache hit/miss behavior and key naming.
 */
class AnalyzerCacheTest extends StandaloneTestCase {

	/**
	 * Mock connection.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Memory cache.
	 *
	 * @var MemoryCache
	 */
	private MemoryCache $cache;

	/**
	 * Analyzer under test.
	 *
	 * @var Analyzer
	 */
	private Analyzer $analyzer;

	/**
	 * Setup test environment before each test.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->cache      = new MemoryCache();
		$this->analyzer   = new Analyzer( $this->connection, $this->cache );

		// Set up default query results for database size queries.
		$this->connection->set_expected_result(
			'%%SUM(data_length + index_length)%%information_schema%%',
			12345678
		);
		$this->connection->set_expected_result(
			'%%SUM(data_free)%%information_schema%%',
			1000
		);
	}

	/**
	 * Cleanup test environment after each test.
	 */
	protected function cleanup_test_environment(): void {
		$this->cache->flush();
		$this->connection->reset();
	}

	// =========================================================================
	// Cache Key Tests
	// =========================================================================

	/**
	 * Test get_database_size uses correct cache key.
	 */
	public function test_get_database_size_uses_correct_cache_key(): void {
		$this->analyzer->get_database_size();

		$this->assertTrue(
			$this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ),
			'Database size should be cached with correct key'
		);
	}

	/**
	 * Test get_table_sizes uses correct cache key.
	 */
	public function test_get_table_sizes_uses_correct_cache_key(): void {
		// Set up expected results for table sizes query.
		$this->connection->set_expected_result(
			'%%table_name%%information_schema%%',
			array(
				(object) array(
					'table' => 'wp_posts',
					'size'  => 5000,
				),
			)
		);

		$this->analyzer->get_table_sizes();

		$this->assertTrue(
			$this->cache->has( CacheKeys::DB_ANALYZER_TABLE_SIZES ),
			'Table sizes should be cached with correct key'
		);
	}

	/**
	 * Test get_total_overhead uses correct cache key.
	 */
	public function test_get_total_overhead_uses_correct_cache_key(): void {
		$this->analyzer->get_total_overhead();

		$this->assertTrue(
			$this->cache->has( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD ),
			'Total overhead should be cached with correct key'
		);
	}

	// =========================================================================
	// Cache Hit Behavior Tests
	// =========================================================================

	/**
	 * Test get_database_size returns cached value on cache hit.
	 */
	public function test_get_database_size_cache_hit(): void {
		// Pre-populate cache.
		$cached_value = 99999999;
		$this->cache->set(
			CacheKeys::DB_ANALYZER_DATABASE_SIZE,
			$cached_value,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_DATABASE_SIZE )
		);

		// Reset query count.
		$this->connection->reset_queries();

		// Call should return cached value without database query.
		$result = $this->analyzer->get_database_size();

		$this->assertEquals( $cached_value, $result );
		$this->assertEmpty(
			$this->connection->get_queries(),
			'No database queries should be made on cache hit'
		);
	}

	/**
	 * Test get_table_sizes returns cached value on cache hit.
	 */
	public function test_get_table_sizes_cache_hit(): void {
		// Pre-populate cache.
		$cached_value = array( 'wp_posts' => 5000, 'wp_options' => 3000 );
		$this->cache->set(
			CacheKeys::DB_ANALYZER_TABLE_SIZES,
			$cached_value,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_TABLE_SIZES )
		);

		// Reset query count.
		$this->connection->reset_queries();

		// Call should return cached value without database query.
		$result = $this->analyzer->get_table_sizes();

		$this->assertEquals( $cached_value, $result );
		$this->assertEmpty(
			$this->connection->get_queries(),
			'No database queries should be made on cache hit'
		);
	}

	/**
	 * Test get_total_overhead returns cached value on cache hit.
	 */
	public function test_get_total_overhead_cache_hit(): void {
		// Pre-populate cache.
		$cached_value = 50000;
		$this->cache->set(
			CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD,
			$cached_value,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD )
		);

		// Reset query count.
		$this->connection->reset_queries();

		// Call should return cached value without database query.
		$result = $this->analyzer->get_total_overhead();

		$this->assertEquals( $cached_value, $result );
		$this->assertEmpty(
			$this->connection->get_queries(),
			'No database queries should be made on cache hit'
		);
	}

	// =========================================================================
	// Cache Miss Behavior Tests
	// =========================================================================

	/**
	 * Test get_database_size queries database on cache miss.
	 */
	public function test_get_database_size_cache_miss(): void {
		// Ensure cache is empty.
		$this->assertFalse( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );

		// Call should query database.
		$result = $this->analyzer->get_database_size();

		$this->assertEquals( 12345678, $result );
		$this->assertNotEmpty(
			$this->connection->get_queries(),
			'Database query should be made on cache miss'
		);
	}

	/**
	 * Test get_database_size caches result after query.
	 */
	public function test_get_database_size_caches_result(): void {
		// First call populates cache.
		$this->analyzer->get_database_size();

		// Verify value is now cached.
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
		$this->assertEquals( 12345678, $this->cache->get( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
	}

	// =========================================================================
	// In-Memory Cache Tests
	// =========================================================================

	/**
	 * Test in-memory cache prevents redundant persistent cache reads.
	 */
	public function test_in_memory_cache_prevents_redundant_reads(): void {
		// Set up table sizes query.
		$this->connection->set_expected_result(
			'%%table_name%%information_schema%%',
			array(
				(object) array(
					'table' => 'wp_posts',
					'size'  => 5000,
				),
			)
		);

		// First call queries database.
		$this->analyzer->get_database_size();
		$this->analyzer->get_table_sizes();

		$first_query_count = count( $this->connection->get_queries() );

		// Second calls should use in-memory cache.
		$this->analyzer->get_database_size();
		$this->analyzer->get_table_sizes();

		$second_query_count = count( $this->connection->get_queries() );

		$this->assertEquals(
			$first_query_count,
			$second_query_count,
			'In-memory cache should prevent additional queries'
		);
	}

	// =========================================================================
	// clear_cache() Tests
	// =========================================================================

	/**
	 * Test clear_cache removes all cached values.
	 */
	public function test_clear_cache_removes_cached_values(): void {
		// Populate caches.
		$this->analyzer->get_database_size();
		$this->analyzer->get_total_overhead();

		// Verify caches are populated.
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD ) );

		// Clear cache.
		$result = $this->analyzer->clear_cache();

		// Verify caches are cleared.
		$this->assertTrue( $result );
		$this->assertFalse( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
		$this->assertFalse( $this->cache->has( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD ) );
	}

	/**
	 * Test clear_cache resets in-memory cache.
	 */
	public function test_clear_cache_resets_in_memory_cache(): void {
		// Populate in-memory cache.
		$this->analyzer->get_database_size();

		// Clear cache.
		$this->analyzer->clear_cache();

		// Change expected result.
		$this->connection->set_expected_result(
			'%%SUM(data_length + index_length)%%information_schema%%',
			87654321
		);

		// Next call should query database again.
		$result = $this->analyzer->get_database_size();

		$this->assertEquals( 87654321, $result );
	}

	// =========================================================================
	// TTL Tests
	// =========================================================================

	/**
	 * Test cache uses correct TTL values.
	 */
	public function test_cache_uses_correct_ttl(): void {
		// Set up a time provider to control cache expiration.
		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		// Populate cache.
		$this->analyzer->get_database_size();

		// Advance time just before expiration.
		$current_time += CacheKeys::DEFAULT_TTL - 1;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should still be cached.
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );

		// Advance time past expiration.
		$current_time += 2;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should be expired.
		$this->assertFalse( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
	}

	// =========================================================================
	// Statistics Tests
	// =========================================================================

	/**
	 * Test cache statistics track hits and misses.
	 */
	public function test_cache_statistics(): void {
		$this->cache->reset_stats();

		// First call - cache miss.
		$this->analyzer->get_database_size();

		$stats = $this->cache->get_stats();
		$this->assertEquals( 1, $stats['misses'] );
		$this->assertEquals( 1, $stats['writes'] );

		// Clear in-memory cache to test persistent cache hit.
		$new_analyzer = new Analyzer( $this->connection, $this->cache );
		$this->cache->reset_stats();

		// Second call - cache hit.
		$new_analyzer->get_database_size();

		$stats = $this->cache->get_stats();
		$this->assertEquals( 1, $stats['hits'] );
		$this->assertEquals( 0, $stats['misses'] );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test handling of null query prepare result.
	 */
	public function test_handles_null_prepare_result(): void {
		// Create a connection that returns null for prepare.
		$mock = $this->createMock( MockConnection::class );
		$mock->method( 'prepare' )->willReturn( null );

		$analyzer = new Analyzer( $mock, $this->cache );

		// Should return 0 and cache it.
		$result = $analyzer->get_database_size();

		$this->assertEquals( 0, $result );
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
	}

	/**
	 * Test caching of empty table sizes array.
	 */
	public function test_caches_empty_table_sizes(): void {
		// Set up empty results.
		$this->connection->set_expected_result(
			'%%table_name%%information_schema%%',
			array()
		);

		$result = $this->analyzer->get_table_sizes();

		$this->assertEmpty( $result );
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_TABLE_SIZES ) );
		$this->assertEquals( array(), $this->cache->get( CacheKeys::DB_ANALYZER_TABLE_SIZES ) );
	}

	/**
	 * Test caching of zero database size.
	 */
	public function test_caches_zero_database_size(): void {
		$this->connection->set_expected_result(
			'%%SUM(data_length + index_length)%%information_schema%%',
			0
		);

		$result = $this->analyzer->get_database_size();

		$this->assertEquals( 0, $result );
		$this->assertTrue( $this->cache->has( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
		$this->assertEquals( 0, $this->cache->get( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
	}
}
