<?php
/**
 * Table Checker Unit Tests (Standalone)
 *
 * Tests for the TableChecker service including table validation,
 * existence caching, and missing table detection.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Services
 */

namespace WPAdminHealth\Tests\UnitStandalone\Services;

use WPAdminHealth\Services\TableChecker;
use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Table Checker test class.
 */
class TableCheckerTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	protected MockConnection $connection;

	/**
	 * TableChecker instance.
	 *
	 * @var TableChecker
	 */
	protected TableChecker $checker;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->checker    = new TableChecker( $this->connection );
	}

	/**
	 * Test TableChecker implements TableCheckerInterface.
	 */
	public function test_implements_table_checker_interface(): void {
		$this->assertInstanceOf( TableCheckerInterface::class, $this->checker );
	}

	/**
	 * Test exists method returns true for existing table.
	 */
	public function test_exists_returns_true_for_existing_table(): void {
		// MockConnection defaults to returning true for table_exists.
		$result = $this->checker->exists( 'wp_posts' );

		$this->assertTrue( $result );
	}

	/**
	 * Test exists method returns false for non-existing table.
	 */
	public function test_exists_returns_false_for_non_existing_table(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_nonexistent'",
			false
		);

		$result = $this->checker->exists( 'wp_nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Test exists method caches results.
	 */
	public function test_exists_caches_results(): void {
		// First call.
		$this->checker->exists( 'wp_posts' );

		// Reset queries to check if cache is used.
		$this->connection->reset_queries();

		// Second call should use cache.
		$result = $this->checker->exists( 'wp_posts' );

		$this->assertTrue( $result );

		// No new queries should have been made.
		$queries = $this->connection->get_queries();
		$this->assertEmpty( $queries );
	}

	/**
	 * Test exists method queries database when not cached.
	 */
	public function test_exists_queries_database_when_not_cached(): void {
		$this->checker->exists( 'wp_posts' );

		$queries = $this->connection->get_queries();
		$this->assertNotEmpty( $queries );

		// Should have made a SHOW TABLES query.
		$found_show_tables = false;
		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'SHOW TABLES' ) !== false ) {
				$found_show_tables = true;
				break;
			}
		}
		$this->assertTrue( $found_show_tables );
	}

	/**
	 * Test exists_multiple checks multiple tables.
	 */
	public function test_exists_multiple_checks_multiple_tables(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_missing'",
			false
		);

		$tables  = array( 'wp_posts', 'wp_options', 'wp_missing' );
		$results = $this->checker->exists_multiple( $tables );

		$this->assertIsArray( $results );
		$this->assertCount( 3, $results );
		$this->assertTrue( $results['wp_posts'] );
		$this->assertTrue( $results['wp_options'] );
		$this->assertFalse( $results['wp_missing'] );
	}

	/**
	 * Test exists_multiple returns associative array.
	 */
	public function test_exists_multiple_returns_associative_array(): void {
		$tables  = array( 'wp_posts', 'wp_users' );
		$results = $this->checker->exists_multiple( $tables );

		$this->assertArrayHasKey( 'wp_posts', $results );
		$this->assertArrayHasKey( 'wp_users', $results );
	}

	/**
	 * Test exists_multiple uses cache for already checked tables.
	 */
	public function test_exists_multiple_uses_cache(): void {
		// Check one table first.
		$this->checker->exists( 'wp_posts' );

		// Reset queries.
		$this->connection->reset_queries();

		// Check multiple including the already cached one.
		$this->checker->exists_multiple( array( 'wp_posts', 'wp_options' ) );

		// Should only query for wp_options, not wp_posts.
		$queries           = $this->connection->get_queries();
		$posts_query_count = 0;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'wp_posts' ) !== false ) {
				++$posts_query_count;
			}
		}

		$this->assertEquals( 0, $posts_query_count );
	}

	/**
	 * Test get_scan_history_table returns correct name.
	 */
	public function test_get_scan_history_table_returns_correct_name(): void {
		$table = $this->checker->get_scan_history_table();

		$this->assertEquals( 'wp_wpha_scan_history', $table );
	}

	/**
	 * Test get_query_log_table returns correct name.
	 */
	public function test_get_query_log_table_returns_correct_name(): void {
		$table = $this->checker->get_query_log_table();

		$this->assertEquals( 'wp_wpha_query_log', $table );
	}

	/**
	 * Test get_ajax_log_table returns correct name.
	 */
	public function test_get_ajax_log_table_returns_correct_name(): void {
		$table = $this->checker->get_ajax_log_table();

		$this->assertEquals( 'wp_wpha_ajax_log', $table );
	}

	/**
	 * Test table name methods use correct prefix.
	 */
	public function test_table_names_use_correct_prefix(): void {
		$this->connection->set_prefix( 'custom_prefix_' );
		$checker = new TableChecker( $this->connection );

		$this->assertEquals( 'custom_prefix_wpha_scan_history', $checker->get_scan_history_table() );
		$this->assertEquals( 'custom_prefix_wpha_query_log', $checker->get_query_log_table() );
		$this->assertEquals( 'custom_prefix_wpha_ajax_log', $checker->get_ajax_log_table() );
	}

	/**
	 * Test scan_history_exists delegates to exists.
	 */
	public function test_scan_history_exists_delegates_to_exists(): void {
		$result = $this->checker->scan_history_exists();

		$this->assertTrue( $result );

		$queries            = $this->connection->get_queries();
		$found_scan_history = false;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'wpha_scan_history' ) !== false ) {
				$found_scan_history = true;
				break;
			}
		}

		$this->assertTrue( $found_scan_history );
	}

	/**
	 * Test scan_history_exists returns false when table missing.
	 */
	public function test_scan_history_exists_returns_false_when_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$result = $this->checker->scan_history_exists();

		$this->assertFalse( $result );
	}

	/**
	 * Test query_log_exists delegates to exists.
	 */
	public function test_query_log_exists_delegates_to_exists(): void {
		$result = $this->checker->query_log_exists();

		$this->assertTrue( $result );
	}

	/**
	 * Test query_log_exists returns false when table missing.
	 */
	public function test_query_log_exists_returns_false_when_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_query_log'",
			false
		);

		$result = $this->checker->query_log_exists();

		$this->assertFalse( $result );
	}

	/**
	 * Test ajax_log_exists delegates to exists.
	 */
	public function test_ajax_log_exists_delegates_to_exists(): void {
		$result = $this->checker->ajax_log_exists();

		$this->assertTrue( $result );
	}

	/**
	 * Test ajax_log_exists returns false when table missing.
	 */
	public function test_ajax_log_exists_returns_false_when_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_ajax_log'",
			false
		);

		$result = $this->checker->ajax_log_exists();

		$this->assertFalse( $result );
	}

	/**
	 * Test clear_cache clears all cache when no table specified.
	 */
	public function test_clear_cache_clears_all_cache(): void {
		// Populate cache.
		$this->checker->exists( 'wp_posts' );
		$this->checker->exists( 'wp_options' );

		// Clear all cache.
		$this->checker->clear_cache();

		// Reset queries.
		$this->connection->reset_queries();

		// Should query again now.
		$this->checker->exists( 'wp_posts' );

		$queries = $this->connection->get_queries();
		$this->assertNotEmpty( $queries );
	}

	/**
	 * Test clear_cache clears specific table cache.
	 */
	public function test_clear_cache_clears_specific_table(): void {
		// Populate cache.
		$this->checker->exists( 'wp_posts' );
		$this->checker->exists( 'wp_options' );

		// Clear only wp_posts cache.
		$this->checker->clear_cache( 'wp_posts' );

		// Reset queries.
		$this->connection->reset_queries();

		// Should query for wp_posts again.
		$this->checker->exists( 'wp_posts' );

		$queries           = $this->connection->get_queries();
		$found_posts_query = false;
		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'wp_posts' ) !== false ) {
				$found_posts_query = true;
				break;
			}
		}
		$this->assertTrue( $found_posts_query );
	}

	/**
	 * Test clear_cache for specific table doesn't affect other cache.
	 */
	public function test_clear_cache_specific_table_preserves_other_cache(): void {
		// Populate cache.
		$this->checker->exists( 'wp_posts' );
		$this->checker->exists( 'wp_options' );

		// Clear only wp_posts cache.
		$this->checker->clear_cache( 'wp_posts' );

		// Reset queries.
		$this->connection->reset_queries();

		// Should still use cache for wp_options.
		$this->checker->exists( 'wp_options' );

		$queries             = $this->connection->get_queries();
		$found_options_query = false;
		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'wp_options' ) !== false ) {
				$found_options_query = true;
				break;
			}
		}
		$this->assertFalse( $found_options_query );
	}

	/**
	 * Test clear_cache handles non-existent cache key gracefully.
	 */
	public function test_clear_cache_handles_non_existent_key(): void {
		// This should not throw an error.
		$this->checker->clear_cache( 'non_existent_table' );

		// Test passes if no exception is thrown.
		$this->assertTrue( true );
	}

	/**
	 * Test exists with empty string table name.
	 */
	public function test_exists_with_empty_table_name(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE ''",
			false
		);

		$result = $this->checker->exists( '' );

		// Should handle gracefully, returning result from connection.
		$this->assertFalse( $result );
	}

	/**
	 * Test exists_multiple with empty array.
	 */
	public function test_exists_multiple_with_empty_array(): void {
		$results = $this->checker->exists_multiple( array() );

		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * Test table checker works with multisite prefix.
	 */
	public function test_works_with_multisite_prefix(): void {
		$this->connection->set_prefix( 'wp_2_' );
		$checker = new TableChecker( $this->connection );

		$this->assertEquals( 'wp_2_wpha_scan_history', $checker->get_scan_history_table() );
		$this->assertEquals( 'wp_2_wpha_query_log', $checker->get_query_log_table() );
		$this->assertEquals( 'wp_2_wpha_ajax_log', $checker->get_ajax_log_table() );
	}

	/**
	 * Test cache is per-instance.
	 */
	public function test_cache_is_per_instance(): void {
		// First checker caches result.
		$checker1 = new TableChecker( $this->connection );
		$checker1->exists( 'wp_posts' );

		// Reset queries.
		$this->connection->reset_queries();

		// Second checker should query independently.
		$checker2 = new TableChecker( $this->connection );
		$checker2->exists( 'wp_posts' );

		$queries = $this->connection->get_queries();
		$this->assertNotEmpty( $queries );
	}

	/**
	 * Test exists returns bool type.
	 */
	public function test_exists_returns_bool_type(): void {
		$result = $this->checker->exists( 'wp_posts' );
		$this->assertIsBool( $result );

		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_nonexistent'",
			false
		);
		$result = $this->checker->exists( 'wp_nonexistent' );
		$this->assertIsBool( $result );
	}

	/**
	 * Test plugin tables existence check workflow.
	 */
	public function test_plugin_tables_existence_workflow(): void {
		// Set up: scan_history exists, query_log missing, ajax_log exists.
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_query_log'",
			false
		);

		$scan_exists  = $this->checker->scan_history_exists();
		$query_exists = $this->checker->query_log_exists();
		$ajax_exists  = $this->checker->ajax_log_exists();

		$this->assertTrue( $scan_exists );
		$this->assertFalse( $query_exists );
		$this->assertTrue( $ajax_exists );
	}

	/**
	 * Test clear_cache after table creation scenario.
	 */
	public function test_clear_cache_after_table_creation(): void {
		// Initially table doesn't exist.
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$result1 = $this->checker->scan_history_exists();
		$this->assertFalse( $result1 );

		// "Create" the table - change the expected result.
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			true
		);

		// Without clearing cache, should still return cached false.
		$result2 = $this->checker->scan_history_exists();
		$this->assertFalse( $result2 );

		// Clear cache for specific table.
		$table_name = $this->checker->get_scan_history_table();
		$this->checker->clear_cache( $table_name );

		// Now should query again and return true.
		$result3 = $this->checker->scan_history_exists();
		$this->assertTrue( $result3 );
	}

	/**
	 * Test exists_multiple maintains result order.
	 */
	public function test_exists_multiple_maintains_order(): void {
		$tables  = array( 'wp_table_a', 'wp_table_b', 'wp_table_c' );
		$results = $this->checker->exists_multiple( $tables );

		$keys = array_keys( $results );
		$this->assertEquals( $tables, $keys );
	}

	/**
	 * Test constructor stores prefix correctly.
	 */
	public function test_constructor_stores_prefix(): void {
		$this->connection->set_prefix( 'test_' );
		$checker = new TableChecker( $this->connection );

		// Verify by checking generated table names.
		$this->assertStringStartsWith( 'test_', $checker->get_scan_history_table() );
	}

	/**
	 * Test multiple existence checks don't multiply queries.
	 */
	public function test_multiple_existence_checks_use_cache(): void {
		// Check same table multiple times.
		$this->checker->exists( 'wp_posts' );
		$this->checker->exists( 'wp_posts' );
		$this->checker->exists( 'wp_posts' );

		$queries = $this->connection->get_queries();

		// Should only have one SHOW TABLES query.
		$show_tables_count = 0;
		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'SHOW TABLES' ) !== false ) {
				++$show_tables_count;
			}
		}

		$this->assertEquals( 1, $show_tables_count );
	}
}
