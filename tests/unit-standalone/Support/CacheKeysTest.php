<?php
/**
 * CacheKeys Unit Tests (Standalone)
 *
 * Tests for the centralized cache key definitions.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Support
 */

namespace WPAdminHealth\Tests\UnitStandalone\Support;

use WPAdminHealth\Support\CacheKeys;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * CacheKeys test class.
 *
 * Tests cache key constants, TTL values, and utility methods.
 */
class CacheKeysTest extends StandaloneTestCase {

	// =========================================================================
	// Constants Tests
	// =========================================================================

	/**
	 * Test TTL constants are defined with correct values.
	 */
	public function test_ttl_constants_defined(): void {
		$this->assertEquals( 300, CacheKeys::DEFAULT_TTL );
		$this->assertEquals( 60, CacheKeys::SHORT_TTL );
		$this->assertEquals( 3600, CacheKeys::LONG_TTL );
	}

	/**
	 * Test database analyzer cache key constants are defined.
	 */
	public function test_db_analyzer_keys_defined(): void {
		$this->assertEquals( 'db_analyzer_database_size', CacheKeys::DB_ANALYZER_DATABASE_SIZE );
		$this->assertEquals( 'db_analyzer_table_sizes', CacheKeys::DB_ANALYZER_TABLE_SIZES );
		$this->assertEquals( 'db_analyzer_total_overhead', CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD );
	}

	/**
	 * Test database orphaned tables cache key constant is defined.
	 */
	public function test_db_orphaned_tables_key_defined(): void {
		$this->assertEquals( 'db_orphaned_tables', CacheKeys::DB_ORPHANED_TABLES );
	}

	/**
	 * Test performance cache key constants are defined.
	 */
	public function test_performance_keys_defined(): void {
		$this->assertEquals( 'perf_autoload_size', CacheKeys::PERF_AUTOLOAD_SIZE );
		$this->assertEquals( 'perf_autoload_analysis', CacheKeys::PERF_AUTOLOAD_ANALYSIS );
		$this->assertEquals( 'perf_health_check', CacheKeys::PERF_HEALTH_CHECK );
		$this->assertEquals( 'perf_plugin_profile', CacheKeys::PERF_PLUGIN_PROFILE );
	}

	/**
	 * Test media cache key constants are defined.
	 */
	public function test_media_keys_defined(): void {
		$this->assertEquals( 'media_scan_results', CacheKeys::MEDIA_SCAN_RESULTS );
		$this->assertEquals( 'media_duplicate_hashes', CacheKeys::MEDIA_DUPLICATE_HASHES );
	}

	/**
	 * Test health score cache key constant is defined.
	 */
	public function test_health_score_key_defined(): void {
		$this->assertEquals( 'health_score', CacheKeys::HEALTH_SCORE );
	}

	/**
	 * Test all cache keys follow naming convention.
	 */
	public function test_cache_keys_naming_convention(): void {
		$all_keys = CacheKeys::get_all_keys();

		foreach ( $all_keys as $name => $key ) {
			// Keys should be lowercase with underscores.
			$this->assertMatchesRegularExpression(
				'/^[a-z][a-z0-9_]*$/',
				$key,
				"Key '{$key}' should follow snake_case convention"
			);

			// Keys should start with a domain prefix (or be health_score).
			$this->assertMatchesRegularExpression(
				'/^(db_|perf_|media_|health_)/',
				$key,
				"Key '{$key}' should start with a domain prefix"
			);
		}
	}

	/**
	 * Test all cache keys are unique.
	 */
	public function test_cache_keys_are_unique(): void {
		$all_keys   = CacheKeys::get_all_keys();
		$values     = array_values( $all_keys );
		$unique     = array_unique( $values );

		$this->assertCount(
			count( $values ),
			$unique,
			'All cache key values should be unique'
		);
	}

	// =========================================================================
	// get_all_keys() Tests
	// =========================================================================

	/**
	 * Test get_all_keys returns array.
	 */
	public function test_get_all_keys_returns_array(): void {
		$keys = CacheKeys::get_all_keys();
		$this->assertIsArray( $keys );
	}

	/**
	 * Test get_all_keys contains all expected keys.
	 */
	public function test_get_all_keys_contains_expected_keys(): void {
		$keys = CacheKeys::get_all_keys();

		$expected = array(
			'DB_ANALYZER_DATABASE_SIZE',
			'DB_ANALYZER_TABLE_SIZES',
			'DB_ANALYZER_TOTAL_OVERHEAD',
			'DB_ORPHANED_TABLES',
			'PERF_AUTOLOAD_SIZE',
			'PERF_AUTOLOAD_ANALYSIS',
			'PERF_HEALTH_CHECK',
			'PERF_PLUGIN_PROFILE',
			'HEALTH_SCORE',
			'MEDIA_SCAN_RESULTS',
			'MEDIA_DUPLICATE_HASHES',
		);

		foreach ( $expected as $key_name ) {
			$this->assertArrayHasKey( $key_name, $keys, "Missing key: {$key_name}" );
		}
	}

	// =========================================================================
	// get_keys_by_prefix() Tests
	// =========================================================================

	/**
	 * Test get_keys_by_prefix returns matching keys.
	 */
	public function test_get_keys_by_prefix_returns_matching(): void {
		$db_keys = CacheKeys::get_keys_by_prefix( 'db_' );

		$this->assertContains( CacheKeys::DB_ANALYZER_DATABASE_SIZE, $db_keys );
		$this->assertContains( CacheKeys::DB_ANALYZER_TABLE_SIZES, $db_keys );
		$this->assertContains( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD, $db_keys );
		$this->assertContains( CacheKeys::DB_ORPHANED_TABLES, $db_keys );
	}

	/**
	 * Test get_keys_by_prefix excludes non-matching keys.
	 */
	public function test_get_keys_by_prefix_excludes_non_matching(): void {
		$db_keys = CacheKeys::get_keys_by_prefix( 'db_' );

		$this->assertNotContains( CacheKeys::PERF_AUTOLOAD_SIZE, $db_keys );
		$this->assertNotContains( CacheKeys::MEDIA_SCAN_RESULTS, $db_keys );
	}

	/**
	 * Test get_keys_by_prefix with perf prefix.
	 */
	public function test_get_keys_by_prefix_performance(): void {
		$perf_keys = CacheKeys::get_keys_by_prefix( 'perf_' );

		$this->assertContains( CacheKeys::PERF_AUTOLOAD_SIZE, $perf_keys );
		$this->assertContains( CacheKeys::PERF_AUTOLOAD_ANALYSIS, $perf_keys );
		$this->assertContains( CacheKeys::PERF_HEALTH_CHECK, $perf_keys );
		$this->assertContains( CacheKeys::PERF_PLUGIN_PROFILE, $perf_keys );
		$this->assertCount( 4, $perf_keys );
	}

	/**
	 * Test get_keys_by_prefix with media prefix.
	 */
	public function test_get_keys_by_prefix_media(): void {
		$media_keys = CacheKeys::get_keys_by_prefix( 'media_' );

		$this->assertContains( CacheKeys::MEDIA_SCAN_RESULTS, $media_keys );
		$this->assertContains( CacheKeys::MEDIA_DUPLICATE_HASHES, $media_keys );
		$this->assertCount( 2, $media_keys );
	}

	/**
	 * Test get_keys_by_prefix with non-existent prefix.
	 */
	public function test_get_keys_by_prefix_no_match(): void {
		$keys = CacheKeys::get_keys_by_prefix( 'nonexistent_' );
		$this->assertEmpty( $keys );
	}

	// =========================================================================
	// get_ttl() Tests
	// =========================================================================

	/**
	 * Test get_ttl returns correct TTL for database analyzer keys.
	 */
	public function test_get_ttl_db_analyzer_keys(): void {
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_DATABASE_SIZE )
		);
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_TABLE_SIZES )
		);
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( CacheKeys::DB_ANALYZER_TOTAL_OVERHEAD )
		);
	}

	/**
	 * Test get_ttl returns long TTL for orphaned tables.
	 */
	public function test_get_ttl_orphaned_tables(): void {
		$this->assertEquals(
			CacheKeys::LONG_TTL,
			CacheKeys::get_ttl( CacheKeys::DB_ORPHANED_TABLES )
		);
	}

	/**
	 * Test get_ttl returns correct TTL for performance keys.
	 */
	public function test_get_ttl_performance_keys(): void {
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( CacheKeys::PERF_AUTOLOAD_SIZE )
		);
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( CacheKeys::PERF_AUTOLOAD_ANALYSIS )
		);
		$this->assertEquals(
			CacheKeys::LONG_TTL,
			CacheKeys::get_ttl( CacheKeys::PERF_PLUGIN_PROFILE )
		);
	}

	/**
	 * Test get_ttl returns long TTL for media keys.
	 */
	public function test_get_ttl_media_keys(): void {
		$this->assertEquals(
			CacheKeys::LONG_TTL,
			CacheKeys::get_ttl( CacheKeys::MEDIA_SCAN_RESULTS )
		);
		$this->assertEquals(
			CacheKeys::LONG_TTL,
			CacheKeys::get_ttl( CacheKeys::MEDIA_DUPLICATE_HASHES )
		);
	}

	/**
	 * Test get_ttl returns default TTL for unknown key.
	 */
	public function test_get_ttl_unknown_key(): void {
		$this->assertEquals(
			CacheKeys::DEFAULT_TTL,
			CacheKeys::get_ttl( 'unknown_key' )
		);
	}

	// =========================================================================
	// with_suffix() Tests
	// =========================================================================

	/**
	 * Test with_suffix appends suffix correctly.
	 */
	public function test_with_suffix_appends_suffix(): void {
		$result = CacheKeys::with_suffix( CacheKeys::DB_ANALYZER_DATABASE_SIZE, 'v2' );
		$this->assertEquals( 'db_analyzer_database_size_v2', $result );
	}

	/**
	 * Test with_suffix with numeric suffix.
	 */
	public function test_with_suffix_numeric(): void {
		$result = CacheKeys::with_suffix( CacheKeys::PERF_AUTOLOAD_SIZE, '123' );
		$this->assertEquals( 'perf_autoload_size_123', $result );
	}

	/**
	 * Test with_suffix with complex suffix.
	 */
	public function test_with_suffix_complex(): void {
		$result = CacheKeys::with_suffix( CacheKeys::MEDIA_SCAN_RESULTS, 'user_42_page_5' );
		$this->assertEquals( 'media_scan_results_user_42_page_5', $result );
	}

	// =========================================================================
	// is_valid_key() Tests
	// =========================================================================

	/**
	 * Test is_valid_key returns true for valid keys.
	 */
	public function test_is_valid_key_returns_true_for_valid(): void {
		$this->assertTrue( CacheKeys::is_valid_key( CacheKeys::DB_ANALYZER_DATABASE_SIZE ) );
		$this->assertTrue( CacheKeys::is_valid_key( CacheKeys::PERF_AUTOLOAD_SIZE ) );
		$this->assertTrue( CacheKeys::is_valid_key( CacheKeys::MEDIA_SCAN_RESULTS ) );
	}

	/**
	 * Test is_valid_key returns false for invalid keys.
	 */
	public function test_is_valid_key_returns_false_for_invalid(): void {
		$this->assertFalse( CacheKeys::is_valid_key( 'invalid_key' ) );
		$this->assertFalse( CacheKeys::is_valid_key( '' ) );
		$this->assertFalse( CacheKeys::is_valid_key( 'DB_ANALYZER_DATABASE_SIZE' ) ); // Constant name, not value.
	}

	/**
	 * Test is_valid_key is case sensitive.
	 */
	public function test_is_valid_key_case_sensitive(): void {
		$this->assertFalse( CacheKeys::is_valid_key( 'DB_ANALYZER_DATABASE_SIZE' ) );
		$this->assertFalse( CacheKeys::is_valid_key( 'Db_Analyzer_Database_Size' ) );
	}
}
