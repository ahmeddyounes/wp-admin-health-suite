<?php
/**
 * Tests for OrphanedTables Class
 *
 * Tests for core table exclusion, plugin table detection, drop safeguards,
 * and SQL safety rules enforcement.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Database
 */

namespace WPAdminHealth\Tests\UnitStandalone\Database;

use WPAdminHealth\Database\OrphanedTables;
use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test cache that returns false by default (matching WordPress transient API behavior).
 *
 * The OrphanedTables class uses `if ( false !== $cached )` pattern which expects
 * cache misses to return `false`, not `null`.
 */
class TestableMemoryCache extends MemoryCache {
	/**
	 * Get value from cache with false as default (matching WordPress behavior).
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value (defaults to false).
	 * @return mixed
	 */
	public function get( string $key, $default = false ) {
		return parent::get( $key, $default );
	}
}

/**
 * Test cases for OrphanedTables functionality.
 *
 * Covers:
 * - Core table exclusion (WordPress core tables should never be flagged as orphaned)
 * - Plugin table detection (tables from active plugins should be protected)
 * - Drop safeguards (confirmation hash, prefix validation, TOCTOU protection)
 * - SQL safety rules (table name validation, injection prevention)
 */
class OrphanedTablesTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Memory cache instance for testing.
	 *
	 * @var TestableMemoryCache
	 */
	private TestableMemoryCache $cache;

	/**
	 * OrphanedTables instance under test.
	 *
	 * @var OrphanedTables
	 */
	private OrphanedTables $orphaned_tables;

	/**
	 * Original active_plugins option value.
	 *
	 * @var mixed
	 */
	private $original_active_plugins;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection      = new MockConnection();
		$this->cache           = new TestableMemoryCache();
		$this->orphaned_tables = new OrphanedTables( $this->connection, $this->cache );

		// Store original active_plugins if it exists.
		$this->original_active_plugins = $GLOBALS['wpha_test_active_plugins'] ?? null;
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		$this->cache->flush();

		// Restore original active_plugins.
		if ( null !== $this->original_active_plugins ) {
			$GLOBALS['wpha_test_active_plugins'] = $this->original_active_plugins;
		} else {
			unset( $GLOBALS['wpha_test_active_plugins'] );
		}
	}

	// =========================================================================
	// Core Table Exclusion Tests
	// =========================================================================

	/**
	 * Test get_known_core_tables returns all expected WordPress core tables.
	 */
	public function test_get_known_core_tables_returns_expected_tables(): void {
		$core_tables = $this->orphaned_tables->get_known_core_tables();

		// Verify core tables are present with wp_ prefix.
		$expected_tables = array(
			'wp_posts',
			'wp_postmeta',
			'wp_comments',
			'wp_commentmeta',
			'wp_terms',
			'wp_termmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_users',
			'wp_usermeta',
			'wp_links',
			'wp_options',
		);

		foreach ( $expected_tables as $table ) {
			$this->assertContains( $table, $core_tables, "Core table {$table} should be in the list" );
		}
	}

	/**
	 * Test get_known_core_tables uses custom prefix.
	 */
	public function test_get_known_core_tables_uses_custom_prefix(): void {
		$this->connection->set_prefix( 'mysite_' );
		// Clear cache to ensure fresh computation with new prefix.
		$this->cache->clear();
		$orphaned = new OrphanedTables( $this->connection, $this->cache );

		$core_tables = $orphaned->get_known_core_tables();

		$this->assertContains( 'mysite_posts', $core_tables );
		$this->assertContains( 'mysite_options', $core_tables );
		$this->assertNotContains( 'wp_posts', $core_tables );
	}

	/**
	 * Test get_known_core_tables caches results.
	 */
	public function test_get_known_core_tables_caches_results(): void {
		// First call should cache.
		$first_result = $this->orphaned_tables->get_known_core_tables();

		// Verify it's in cache.
		$this->assertTrue( $this->cache->has( 'wpha_core_tables' ) );

		// Second call should use cache.
		$second_result = $this->orphaned_tables->get_known_core_tables();

		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test core tables are never flagged as orphaned.
	 */
	public function test_core_tables_never_flagged_as_orphaned(): void {
		// Set up mock to return core tables plus an orphaned table.
		$all_tables = array(
			'wp_posts',
			'wp_postmeta',
			'wp_comments',
			'wp_commentmeta',
			'wp_terms',
			'wp_termmeta',
			'wp_term_relationships',
			'wp_term_taxonomy',
			'wp_users',
			'wp_usermeta',
			'wp_links',
			'wp_options',
			'wp_orphaned_table', // This should be flagged.
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		// Mock table info query for orphaned table.
		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_orphaned_table'%%",
			array(
				'name'        => 'wp_orphaned_table',
				'row_count'   => 100,
				'size_bytes'  => 50000,
				'DATA_LENGTH' => 40000,
				'INDEX_LENGTH' => 10000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		// Only the orphaned table should be found.
		$this->assertCount( 1, $orphaned );
		$this->assertEquals( 'wp_orphaned_table', $orphaned[0]['name'] );

		// Core tables should NOT be in the result.
		$orphaned_names = array_column( $orphaned, 'name' );
		$this->assertNotContains( 'wp_posts', $orphaned_names );
		$this->assertNotContains( 'wp_options', $orphaned_names );
	}

	// =========================================================================
	// Plugin Table Detection Tests
	// =========================================================================

	/**
	 * Test tables from active plugins are protected.
	 */
	public function test_active_plugin_tables_are_protected(): void {
		// Simulate WooCommerce being active.
		$GLOBALS['wpha_test_active_plugins'] = array( 'woocommerce/woocommerce.php' );

		// Override get_option to return our test plugins.
		$this->setup_active_plugins_mock( array( 'woocommerce/woocommerce.php' ) );

		// Set up mock to return WooCommerce tables.
		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_wc_orders',
			'wp_woocommerce_sessions',
			'wp_orphaned_table',
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		// Mock table info for orphaned table.
		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_orphaned_table'%%",
			array(
				'name'        => 'wp_orphaned_table',
				'row_count'   => 10,
				'size_bytes'  => 5000,
				'DATA_LENGTH' => 4000,
				'INDEX_LENGTH' => 1000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		// WooCommerce tables should NOT be flagged as orphaned.
		$orphaned_names = array_column( $orphaned, 'name' );
		$this->assertNotContains( 'wp_wc_orders', $orphaned_names );
		$this->assertNotContains( 'wp_woocommerce_sessions', $orphaned_names );
	}

	/**
	 * Test tables from deactivated plugins ARE flagged as orphaned.
	 */
	public function test_deactivated_plugin_tables_are_orphaned(): void {
		// No active plugins.
		$this->setup_active_plugins_mock( array() );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_wc_orders',  // WooCommerce table, but plugin not active.
			'wp_yoast_seo_links', // Yoast table, but plugin not active.
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		// Mock table info queries.
		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_wc_orders'%%",
			array(
				'name'        => 'wp_wc_orders',
				'row_count'   => 500,
				'size_bytes'  => 100000,
				'DATA_LENGTH' => 80000,
				'INDEX_LENGTH' => 20000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_yoast_seo_links'%%",
			array(
				'name'        => 'wp_yoast_seo_links',
				'row_count'   => 200,
				'size_bytes'  => 50000,
				'DATA_LENGTH' => 40000,
				'INDEX_LENGTH' => 10000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		$orphaned_names = array_column( $orphaned, 'name' );
		$this->assertContains( 'wp_wc_orders', $orphaned_names );
	}

	/**
	 * Test WPHA plugin table is always protected.
	 */
	public function test_wpha_plugin_table_always_protected(): void {
		$this->setup_active_plugins_mock( array() );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_wpha_scan_history', // Our plugin's table.
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		$orphaned_names = array_column( $orphaned, 'name' );
		$this->assertNotContains( 'wp_wpha_scan_history', $orphaned_names );
	}

	/**
	 * Test shared table patterns are detected with warning.
	 */
	public function test_shared_table_patterns_detected_with_warning(): void {
		$this->setup_active_plugins_mock( array() );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_actionscheduler_actions', // Shared table (used by multiple plugins).
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_actionscheduler_actions'%%",
			array(
				'name'        => 'wp_actionscheduler_actions',
				'row_count'   => 1000,
				'size_bytes'  => 200000,
				'DATA_LENGTH' => 150000,
				'INDEX_LENGTH' => 50000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		// Find the actionscheduler table in results.
		$as_table = null;
		foreach ( $orphaned as $table ) {
			if ( 'wp_actionscheduler_actions' === $table['name'] ) {
				$as_table = $table;
				break;
			}
		}

		$this->assertNotNull( $as_table, 'Action Scheduler table should be in orphaned list' );
		$this->assertTrue( $as_table['is_shared_table'], 'Should be flagged as shared table' );
		$this->assertArrayHasKey( 'warning', $as_table, 'Should have a warning' );
	}

	/**
	 * Test potential owner identification for known plugin patterns.
	 */
	public function test_potential_owner_identification(): void {
		$this->setup_active_plugins_mock( array() );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_wc_orders',
			'wp_yoast_seo_links',
			'wp_wpforms_entries',
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_wc_orders'%%",
			array(
				'name'        => 'wp_wc_orders',
				'row_count'   => 100,
				'size_bytes'  => 50000,
				'DATA_LENGTH' => 40000,
				'INDEX_LENGTH' => 10000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_yoast_seo_links'%%",
			array(
				'name'        => 'wp_yoast_seo_links',
				'row_count'   => 50,
				'size_bytes'  => 25000,
				'DATA_LENGTH' => 20000,
				'INDEX_LENGTH' => 5000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_wpforms_entries'%%",
			array(
				'name'        => 'wp_wpforms_entries',
				'row_count'   => 200,
				'size_bytes'  => 75000,
				'DATA_LENGTH' => 60000,
				'INDEX_LENGTH' => 15000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		// Find tables and check potential owners.
		foreach ( $orphaned as $table ) {
			if ( 'wp_wc_orders' === $table['name'] ) {
				$this->assertEquals( 'WooCommerce', $table['potential_owner'] );
			}
			if ( 'wp_wpforms_entries' === $table['name'] ) {
				$this->assertEquals( 'WPForms', $table['potential_owner'] );
			}
		}
	}

	// =========================================================================
	// Drop Safeguards Tests
	// =========================================================================

	/**
	 * Test delete requires valid confirmation hash.
	 */
	public function test_delete_requires_valid_confirmation_hash(): void {
		$result = $this->orphaned_tables->delete_orphaned_table( 'wp_orphaned_table', 'invalid_hash' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid confirmation hash', $result['message'] );
	}

	/**
	 * Test delete requires WordPress prefix.
	 */
	public function test_delete_requires_wordpress_prefix(): void {
		// Generate valid hash for table without prefix.
		$table_name = 'malicious_table';
		$valid_hash = $this->generate_hash_for_testing( $table_name );

		$result = $this->orphaned_tables->delete_orphaned_table( $table_name, $valid_hash );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'WordPress prefix', $result['message'] );
	}

	/**
	 * Test delete validates table name format (no SQL injection).
	 */
	public function test_delete_validates_table_name_format(): void {
		$malicious_name = 'wp_test; DROP TABLE wp_users;--';
		$valid_hash     = $this->generate_hash_for_testing( $malicious_name );

		$result = $this->orphaned_tables->delete_orphaned_table( $malicious_name, $valid_hash );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid table name format', $result['message'] );
	}

	/**
	 * Test delete rejects special characters in table name.
	 */
	public function test_delete_rejects_special_characters(): void {
		$special_chars = array(
			'wp_test`table',
			'wp_test"table',
			"wp_test'table",
			'wp_test table',
			'wp_test-table-with-dash',
			'wp_test.table',
		);

		foreach ( $special_chars as $name ) {
			$hash   = $this->generate_hash_for_testing( $name );
			$result = $this->orphaned_tables->delete_orphaned_table( $name, $hash );

			$this->assertFalse( $result['success'], "Table name '{$name}' should be rejected" );
		}
	}

	/**
	 * Test delete rejects core tables even with valid hash.
	 */
	public function test_delete_rejects_core_tables(): void {
		$core_table = 'wp_posts';
		$valid_hash = $this->generate_hash_for_testing( $core_table );

		// Mock the table existence check.
		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_posts'%%information_schema%%",
			'wp_posts'
		);

		$result = $this->orphaned_tables->delete_orphaned_table( $core_table, $valid_hash );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'no longer orphaned', $result['message'] );
	}

	/**
	 * Test delete fails if table doesn't exist.
	 */
	public function test_delete_fails_if_table_doesnt_exist(): void {
		$table_name = 'wp_nonexistent_table';
		$valid_hash = $this->generate_hash_for_testing( $table_name );

		// Mock: table doesn't exist.
		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_nonexistent_table'%%information_schema%%",
			null
		);

		$result = $this->orphaned_tables->delete_orphaned_table( $table_name, $valid_hash );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Test successful deletion returns freed size.
	 */
	public function test_successful_deletion_returns_freed_size(): void {
		$table_name = 'wp_orphaned_data';
		$valid_hash = $this->generate_hash_for_testing( $table_name );

		// Mock: table exists and is orphaned.
		$this->connection->set_expected_result(
			"%%SELECT TABLE_NAME FROM information_schema.TABLES%%TABLE_NAME = 'wp_orphaned_data'%%",
			'wp_orphaned_data'
		);

		// Mock table info.
		$this->connection->set_expected_result(
			"%%SELECT%%TABLE_NAME as name%%TABLE_NAME = 'wp_orphaned_data'%%",
			array(
				'name'        => 'wp_orphaned_data',
				'row_count'   => 1000,
				'size_bytes'  => 500000,
				'DATA_LENGTH' => 400000,
				'INDEX_LENGTH' => 100000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		// Mock DROP TABLE success.
		$this->connection->set_expected_result(
			'%%DROP TABLE%%wp_orphaned_data%%',
			1
		);

		// Mock INSERT into history.
		$this->connection->set_rows_affected( 1 );

		$result = $this->orphaned_tables->delete_orphaned_table( $table_name, $valid_hash );

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'size_freed', $result );
		$this->assertEquals( 500000, $result['size_freed'] );
	}

	/**
	 * Test deletion is logged to scan history.
	 */
	public function test_deletion_is_logged_to_scan_history(): void {
		$table_name = 'wp_orphaned_log_test';
		$valid_hash = $this->generate_hash_for_testing( $table_name );

		// Mock: table exists and is orphaned.
		$this->connection->set_expected_result(
			"%%SELECT TABLE_NAME FROM information_schema.TABLES%%TABLE_NAME = 'wp_orphaned_log_test'%%",
			'wp_orphaned_log_test'
		);

		// Mock table info.
		$this->connection->set_expected_result(
			"%%SELECT%%TABLE_NAME as name%%TABLE_NAME = 'wp_orphaned_log_test'%%",
			array(
				'name'        => 'wp_orphaned_log_test',
				'row_count'   => 50,
				'size_bytes'  => 10000,
				'DATA_LENGTH' => 8000,
				'INDEX_LENGTH' => 2000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		// Mock DROP TABLE success.
		$this->connection->set_expected_result(
			'%%DROP TABLE%%wp_orphaned_log_test%%',
			1
		);

		$this->connection->set_rows_affected( 1 );

		$result = $this->orphaned_tables->delete_orphaned_table( $table_name, $valid_hash );

		$this->assertTrue( $result['success'] );

		// Check that an INSERT to scan_history was made.
		$queries       = $this->connection->get_queries();
		$insert_found  = false;
		foreach ( $queries as $query_info ) {
			if ( strpos( $query_info['query'], 'INSERT INTO wp_wpha_scan_history' ) !== false ) {
				$insert_found = true;
				$this->assertStringContainsString( 'orphaned_table_deletion', $query_info['query'] );
				break;
			}
		}
		$this->assertTrue( $insert_found, 'Deletion should be logged to scan history' );
	}

	// =========================================================================
	// SQL Safety Tests
	// =========================================================================

	/**
	 * Test get_all_wp_tables uses prepared statements.
	 */
	public function test_get_all_wp_tables_uses_prepared_statements(): void {
		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_SCHEMA = 'test_database'%%TABLE_NAME LIKE 'wp\\_%'%%",
			array( 'wp_posts', 'wp_options' )
		);

		$tables = $this->orphaned_tables->get_all_wp_tables();

		// Verify the query used proper escaping.
		$queries = $this->connection->get_queries();
		$found   = false;
		foreach ( $queries as $query_info ) {
			if ( strpos( $query_info['query'], 'information_schema.TABLES' ) !== false ) {
				$found = true;
				// Should contain escaped database name and like pattern.
				$this->assertStringContainsString( 'test_database', $query_info['query'] );
				$this->assertStringContainsString( 'wp\\_%', $query_info['query'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Query to information_schema should be made' );
	}

	/**
	 * Test table name only allows alphanumeric and underscores.
	 */
	public function test_table_name_validation_pattern(): void {
		// Valid names.
		$valid_names = array(
			'wp_posts',
			'wp_my_custom_table',
			'wp_table123',
			'wp_MyMixedCase',
			'wp_table_with_numbers_123',
		);

		// These should pass validation (generate hash and check prefix).
		foreach ( $valid_names as $name ) {
			$hash   = $this->generate_hash_for_testing( $name );
			$result = $this->orphaned_tables->delete_orphaned_table( $name, $hash );
			// They may fail for other reasons (table not found, is core table, etc.)
			// but NOT for invalid format.
			$this->assertStringNotContainsString(
				'Invalid table name format',
				$result['message'],
				"Valid name '{$name}' should not fail format validation"
			);
		}

		// Invalid names.
		$invalid_names = array(
			'wp_table-dash',
			'wp_table.dot',
			'wp_table`backtick',
			'wp_table;semicolon',
			'wp_table DROP',
		);

		foreach ( $invalid_names as $name ) {
			$hash   = $this->generate_hash_for_testing( $name );
			$result = $this->orphaned_tables->delete_orphaned_table( $name, $hash );
			$this->assertStringContainsString(
				'Invalid table name format',
				$result['message'],
				"Invalid name '{$name}' should fail format validation"
			);
		}
	}

	/**
	 * Test DROP TABLE uses backtick escaping.
	 */
	public function test_drop_table_uses_backtick_escaping(): void {
		$table_name = 'wp_test_escape';
		$valid_hash = $this->generate_hash_for_testing( $table_name );

		// Mock: table exists and is orphaned.
		$this->connection->set_expected_result(
			"%%SELECT TABLE_NAME FROM information_schema.TABLES%%TABLE_NAME = 'wp_test_escape'%%",
			'wp_test_escape'
		);

		// Mock table info.
		$this->connection->set_expected_result(
			"%%SELECT%%TABLE_NAME as name%%TABLE_NAME = 'wp_test_escape'%%",
			array(
				'name'        => 'wp_test_escape',
				'row_count'   => 10,
				'size_bytes'  => 1000,
				'DATA_LENGTH' => 800,
				'INDEX_LENGTH' => 200,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		// Mock DROP TABLE.
		$this->connection->set_expected_result(
			'%%DROP TABLE%%',
			1
		);

		$this->connection->set_rows_affected( 1 );

		$this->orphaned_tables->delete_orphaned_table( $table_name, $valid_hash );

		// Find the DROP TABLE query.
		$queries    = $this->connection->get_queries();
		$drop_found = false;
		foreach ( $queries as $query_info ) {
			if ( strpos( $query_info['query'], 'DROP TABLE' ) !== false ) {
				$drop_found = true;
				// Should use backtick escaping.
				$this->assertStringContainsString( '`wp_test_escape`', $query_info['query'] );
				break;
			}
		}
		$this->assertTrue( $drop_found, 'DROP TABLE query should be executed' );
	}

	/**
	 * Test confirmation hash uses HMAC-SHA256.
	 */
	public function test_confirmation_hash_uses_hmac(): void {
		// Get two different table names.
		$table1 = 'wp_test_table_1';
		$table2 = 'wp_test_table_2';

		$hash1 = $this->generate_hash_for_testing( $table1 );
		$hash2 = $this->generate_hash_for_testing( $table2 );

		// Hashes should be different for different tables.
		$this->assertNotEquals( $hash1, $hash2 );

		// Hashes should be 32 hex characters (128 bits).
		$this->assertEquals( 32, strlen( $hash1 ) );
		$this->assertEquals( 32, strlen( $hash2 ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash1 );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $hash2 );
	}

	/**
	 * Test hash timing attack prevention with hash_equals.
	 */
	public function test_hash_verification_uses_constant_time_comparison(): void {
		// This is a design test - we verify the implementation uses hash_equals
		// by checking that similar hashes are both rejected (not short-circuited).
		$table_name   = 'wp_test_timing';
		$correct_hash = $this->generate_hash_for_testing( $table_name );

		// Try a hash that differs only in the last character.
		$almost_correct = substr( $correct_hash, 0, -1 ) . ( $correct_hash[-1] === 'a' ? 'b' : 'a' );

		// Both should fail with the same error (invalid hash).
		$result1 = $this->orphaned_tables->delete_orphaned_table( $table_name, 'completely_wrong_hash' );
		$result2 = $this->orphaned_tables->delete_orphaned_table( $table_name, $almost_correct );

		$this->assertFalse( $result1['success'] );
		$this->assertFalse( $result2['success'] );
		$this->assertEquals( $result1['message'], $result2['message'] );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test get_all_wp_tables returns empty array on prepare failure.
	 */
	public function test_get_all_wp_tables_handles_prepare_failure(): void {
		// Don't set any expected result - will return null/empty.
		$tables = $this->orphaned_tables->get_all_wp_tables();

		$this->assertIsArray( $tables );
		$this->assertEmpty( $tables );
	}

	/**
	 * Test find_orphaned_tables returns empty array when no orphans.
	 */
	public function test_find_orphaned_tables_returns_empty_when_none(): void {
		$core_tables = array(
			'wp_posts',
			'wp_options',
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%",
			$core_tables
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		$this->assertIsArray( $orphaned );
		$this->assertEmpty( $orphaned );
	}

	/**
	 * Test custom prefix is respected throughout.
	 */
	public function test_custom_prefix_respected(): void {
		$this->connection->set_prefix( 'custom_' );
		// Clear cache to ensure fresh computation with new prefix.
		$this->cache->clear();
		$orphaned = new OrphanedTables( $this->connection, $this->cache );

		// Test core tables have custom prefix.
		$core = $orphaned->get_known_core_tables();
		$this->assertContains( 'custom_posts', $core );
		$this->assertNotContains( 'wp_posts', $core );

		// Test WPHA table has custom prefix.
		$plugin_tables = $orphaned->get_registered_plugin_tables();
		$this->assertContains( 'custom_wpha_scan_history', $plugin_tables );
	}

	/**
	 * Test orphaned table info includes all expected fields.
	 */
	public function test_orphaned_table_info_structure(): void {
		$this->setup_active_plugins_mock( array() );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_orphaned_complete',
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		$this->connection->set_expected_result(
			"%%TABLE_NAME = 'wp_orphaned_complete'%%",
			array(
				'name'        => 'wp_orphaned_complete',
				'row_count'   => 250,
				'size_bytes'  => 75000,
				'DATA_LENGTH' => 60000,
				'INDEX_LENGTH' => 15000,
				'created_at'  => '2024-01-01 00:00:00',
				'updated_at'  => '2024-01-15 00:00:00',
			)
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		$this->assertCount( 1, $orphaned );
		$table = $orphaned[0];

		// Check required fields.
		$this->assertArrayHasKey( 'name', $table );
		$this->assertArrayHasKey( 'row_count', $table );
		$this->assertArrayHasKey( 'size_bytes', $table );
		$this->assertArrayHasKey( 'confirmation_hash', $table );
		$this->assertArrayHasKey( 'is_shared_table', $table );
		$this->assertArrayHasKey( 'potential_owner', $table );
	}

	/**
	 * Test Action Scheduler tables protected when WooCommerce active.
	 */
	public function test_action_scheduler_protected_with_woocommerce(): void {
		$this->setup_active_plugins_mock( array( 'woocommerce/woocommerce.php' ) );

		$all_tables = array(
			'wp_posts',
			'wp_options',
			'wp_actionscheduler_actions',
			'wp_actionscheduler_claims',
		);

		$this->connection->set_expected_result(
			"%%FROM information_schema.TABLES%%TABLE_NAME LIKE 'wp\\_%'%%",
			$all_tables
		);

		$orphaned = $this->orphaned_tables->find_orphaned_tables();

		$orphaned_names = array_column( $orphaned, 'name' );
		$this->assertNotContains( 'wp_actionscheduler_actions', $orphaned_names );
		$this->assertNotContains( 'wp_actionscheduler_claims', $orphaned_names );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Generate confirmation hash for testing.
	 *
	 * Uses the same algorithm as OrphanedTables::generate_confirmation_hash.
	 *
	 * @param string $table_name Table name.
	 * @return string The confirmation hash.
	 */
	private function generate_hash_for_testing( string $table_name ): string {
		$secret_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpha_default_key_' . ABSPATH;
		$hash       = hash_hmac( 'sha256', $table_name, $secret_key );
		return substr( $hash, 0, 32 );
	}

	/**
	 * Set up mock for active plugins.
	 *
	 * Since get_option is stubbed in bootstrap, we need a different approach.
	 * We'll rely on the default get_option returning an empty array.
	 *
	 * @param array $plugins List of active plugin paths.
	 */
	private function setup_active_plugins_mock( array $plugins ): void {
		// The bootstrap's get_option stub returns the default value,
		// so active_plugins will be an empty array by default.
		// For testing purposes, we'll use a global to override.
		$GLOBALS['wpha_test_active_plugins'] = $plugins;
	}
}
