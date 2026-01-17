<?php
/**
 * Activity Logger Unit Tests (Standalone)
 *
 * Tests for the ActivityLogger service including log rotation and retention.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Services
 */

namespace WPAdminHealth\Tests\UnitStandalone\Services;

use WPAdminHealth\Services\ActivityLogger;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\Mocks\MockSettings;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Activity Logger test class.
 */
class ActivityLoggerTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	protected MockConnection $connection;

	/**
	 * Mock settings instance.
	 *
	 * @var MockSettings
	 */
	protected MockSettings $settings;

	/**
	 * ActivityLogger instance.
	 *
	 * @var ActivityLogger
	 */
	protected ActivityLogger $logger;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->settings   = new MockSettings();
		$this->logger     = new ActivityLogger( $this->connection, $this->settings );
	}

	/**
	 * Test ActivityLogger implements ActivityLoggerInterface.
	 */
	public function test_implements_activity_logger_interface(): void {
		$this->assertInstanceOf( ActivityLoggerInterface::class, $this->logger );
	}

	/**
	 * Test log method records activity.
	 */
	public function test_log_records_activity(): void {
		$result = $this->logger->log( 'database_revisions', 10, 10, 5000 );

		$this->assertTrue( $result );

		$queries = $this->connection->get_queries();
		$insert_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'INSERT INTO' ) !== false ) {
				$insert_query = $query['query'];
				break;
			}
		}

		$this->assertNotNull( $insert_query );
		$this->assertStringContainsString( 'wpha_scan_history', $insert_query );
		$this->assertStringContainsString( 'database_revisions', $insert_query );
	}

	/**
	 * Test log method sanitizes scan type.
	 */
	public function test_log_sanitizes_scan_type(): void {
		$this->logger->log( '<script>alert("xss")</script>', 1 );

		$queries = $this->connection->get_queries();
		$insert_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'INSERT INTO' ) !== false ) {
				$insert_query = $query['query'];
				break;
			}
		}

		$this->assertNotNull( $insert_query );
		// Script tags should be sanitized out.
		$this->assertStringNotContainsString( '<script>', $insert_query );
	}

	/**
	 * Test log returns false when table does not exist.
	 */
	public function test_log_returns_false_when_table_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$result = $this->logger->log( 'test_scan', 5 );

		$this->assertFalse( $result );
	}

	/**
	 * Test log_database_cleanup logs revisions cleanup.
	 */
	public function test_log_database_cleanup_revisions(): void {
		$result = $this->logger->log_database_cleanup( 'revisions', array(
			'deleted'     => 25,
			'bytes_freed' => 50000,
		) );

		$this->assertTrue( $result );

		$queries = $this->connection->get_queries();
		$insert_query = $this->find_insert_query( $queries );

		$this->assertStringContainsString( 'database_revisions', $insert_query );
	}

	/**
	 * Test log_database_cleanup logs trash cleanup.
	 */
	public function test_log_database_cleanup_trash(): void {
		$result = $this->logger->log_database_cleanup( 'trash', array(
			'posts_deleted'    => 5,
			'comments_deleted' => 10,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test log_database_cleanup logs orphaned cleanup.
	 */
	public function test_log_database_cleanup_orphaned(): void {
		$result = $this->logger->log_database_cleanup( 'orphaned', array(
			'postmeta_deleted'      => 10,
			'commentmeta_deleted'   => 5,
			'termmeta_deleted'      => 3,
			'relationships_deleted' => 2,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test log_media_operation logs delete operation.
	 */
	public function test_log_media_operation_delete(): void {
		$result = $this->logger->log_media_operation( 'delete', array(
			'prepared_items' => array( 1, 2, 3 ),
			'bytes_freed'    => 1000000,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test log_media_operation logs scan operation.
	 */
	public function test_log_media_operation_scan(): void {
		$result = $this->logger->log_media_operation( 'scan', array(
			'total'  => 100,
			'unused' => 25,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test log_performance_check logs query analysis.
	 */
	public function test_log_performance_check_query_analysis(): void {
		$result = $this->logger->log_performance_check( 'query_analysis', array(
			'slow_queries' => 5,
		) );

		$this->assertTrue( $result );
	}

	/**
	 * Test get_recent returns empty array when table missing.
	 */
	public function test_get_recent_returns_empty_when_table_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$result = $this->logger->get_recent( 10 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_recent with limit.
	 */
	public function test_get_recent_with_limit(): void {
		$expected = array(
			array( 'id' => 1, 'scan_type' => 'test' ),
			array( 'id' => 2, 'scan_type' => 'test2' ),
		);

		$this->connection->set_expected_result( '%%ORDER BY created_at DESC LIMIT%%', $expected );

		$result = $this->logger->get_recent( 10 );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_recent with type filter.
	 */
	public function test_get_recent_with_type_filter(): void {
		$this->logger->get_recent( 10, 'database' );

		$queries = $this->connection->get_queries();
		$found_like = false;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'LIKE' ) !== false ) {
				$found_like = true;
				break;
			}
		}

		$this->assertTrue( $found_like );
	}

	/**
	 * Test get_recent clamps limit to max 100.
	 */
	public function test_get_recent_clamps_limit(): void {
		$this->logger->get_recent( 500 );

		$queries = $this->connection->get_queries();
		$found_limit = false;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'LIMIT 100' ) !== false ) {
				$found_limit = true;
				break;
			}
		}

		$this->assertTrue( $found_limit );
	}

	/**
	 * Test table_exists returns cached value.
	 */
	public function test_table_exists_caches_result(): void {
		// First call.
		$result1 = $this->logger->table_exists();

		// Second call should use cache, not query again.
		$result2 = $this->logger->table_exists();

		$this->assertEquals( $result1, $result2 );

		// Only one SHOW TABLES query should have been made.
		$queries = $this->connection->get_queries();
		$show_tables_count = 0;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'SHOW TABLES' ) !== false ) {
				++$show_tables_count;
			}
		}

		$this->assertEquals( 1, $show_tables_count );
	}

	/**
	 * Test prune_old_logs deletes old records.
	 */
	public function test_prune_old_logs_deletes_old_records(): void {
		// MockConnection.query() returns the expected result, not rows_affected.
		$this->connection->set_expected_result( 'DELETE FROM%%wpha_scan_history%%', 15 );

		$deleted = $this->logger->prune_old_logs();

		$this->assertEquals( 15, $deleted );

		$queries = $this->connection->get_queries();
		$delete_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'DELETE FROM' ) !== false ) {
				$delete_query = $query['query'];
				break;
			}
		}

		$this->assertNotNull( $delete_query );
		$this->assertStringContainsString( 'wpha_scan_history', $delete_query );
		$this->assertStringContainsString( 'created_at <', $delete_query );
	}

	/**
	 * Test prune_old_logs returns 0 when table missing.
	 */
	public function test_prune_old_logs_returns_zero_when_table_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$deleted = $this->logger->prune_old_logs();

		$this->assertEquals( 0, $deleted );
	}

	/**
	 * Test prune_old_logs uses settings retention days.
	 */
	public function test_prune_old_logs_uses_settings_retention(): void {
		$this->settings->set_setting( 'log_retention_days', 14 );

		// Create new logger with updated settings.
		$logger = new ActivityLogger( $this->connection, $this->settings );
		$logger->prune_old_logs();

		$queries = $this->connection->get_queries();
		$delete_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'DELETE FROM' ) !== false ) {
				$delete_query = $query['query'];
				break;
			}
		}

		$this->assertNotNull( $delete_query );
		// The cutoff date should be calculated based on 14 days.
		$expected_cutoff = gmdate( 'Y-m-d', time() - ( 14 * DAY_IN_SECONDS ) );
		$this->assertStringContainsString( $expected_cutoff, $delete_query );
	}

	/**
	 * Test get_log_count returns count.
	 */
	public function test_get_log_count_returns_count(): void {
		$this->connection->set_expected_result( 'SELECT COUNT(*) FROM%%', 42 );

		$count = $this->logger->get_log_count();

		$this->assertEquals( 42, $count );
	}

	/**
	 * Test get_log_count returns 0 when table missing.
	 */
	public function test_get_log_count_returns_zero_when_table_missing(): void {
		$this->connection->set_expected_result(
			"SHOW TABLES LIKE 'wp_wpha_scan_history'",
			false
		);

		$count = $this->logger->get_log_count();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test constructor works without settings.
	 */
	public function test_constructor_without_settings(): void {
		$logger = new ActivityLogger( $this->connection );

		$this->assertInstanceOf( ActivityLoggerInterface::class, $logger );
	}

	/**
	 * Test log with default values.
	 */
	public function test_log_with_default_values(): void {
		$result = $this->logger->log( 'test_scan', 5 );

		$this->assertTrue( $result );

		$queries = $this->connection->get_queries();
		$insert_query = $this->find_insert_query( $queries );

		// Default values: items_cleaned = 0, bytes_freed = 0.
		$this->assertStringContainsString( 'test_scan', $insert_query );
	}

	/**
	 * Test log clamps retention days to valid range.
	 */
	public function test_retention_days_clamped_to_valid_range(): void {
		// Test minimum clamp (below 7).
		$this->settings->set_setting( 'log_retention_days', 1 );
		$logger = new ActivityLogger( $this->connection, $this->settings );
		$logger->prune_old_logs();

		$queries = $this->connection->get_queries();
		$delete_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'DELETE FROM' ) !== false ) {
				$delete_query = $query['query'];
				break;
			}
		}

		// Should use minimum of 7 days.
		$expected_cutoff = gmdate( 'Y-m-d', time() - ( 7 * DAY_IN_SECONDS ) );
		$this->assertStringContainsString( $expected_cutoff, $delete_query );
	}

	/**
	 * Test retention days clamped to maximum.
	 */
	public function test_retention_days_clamped_to_maximum(): void {
		$this->settings->set_setting( 'log_retention_days', 365 );
		$logger = new ActivityLogger( $this->connection, $this->settings );

		$this->connection->reset_queries();
		$logger->prune_old_logs();

		$queries = $this->connection->get_queries();
		$delete_query = null;

		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'DELETE FROM' ) !== false ) {
				$delete_query = $query['query'];
				break;
			}
		}

		// Should use maximum of 90 days.
		$expected_cutoff = gmdate( 'Y-m-d', time() - ( 90 * DAY_IN_SECONDS ) );
		$this->assertStringContainsString( $expected_cutoff, $delete_query );
	}

	/**
	 * Helper method to find INSERT query in query list.
	 *
	 * @param array $queries Query list.
	 * @return string|null INSERT query or null.
	 */
	private function find_insert_query( array $queries ): ?string {
		foreach ( $queries as $query ) {
			if ( strpos( $query['query'], 'INSERT INTO' ) !== false ) {
				return $query['query'];
			}
		}
		return null;
	}
}
