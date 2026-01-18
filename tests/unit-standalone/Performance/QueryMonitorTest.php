<?php
/**
 * Unit tests for QueryMonitor class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\QueryMonitor;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for QueryMonitor.
 *
 * @covers \WPAdminHealth\Performance\QueryMonitor
 */
class QueryMonitorTest extends StandaloneTestCase {

	/**
	 * Mock database connection.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Mock settings.
	 *
	 * @var SettingsInterface|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $settings;

	/**
	 * QueryMonitor instance.
	 *
	 * @var QueryMonitor
	 */
	private QueryMonitor $monitor;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->settings   = $this->createMock( SettingsInterface::class );

		// Default settings mock behavior
		$this->settings->method( 'get_setting' )->willReturnCallback(
			function ( $key, $default ) {
				$settings = array(
					'enable_query_monitoring' => false,
					'query_logging_enabled'   => false,
					'slow_query_threshold_ms' => 50,
				);
				return $settings[ $key ] ?? $default;
			}
		);

		$this->monitor = new QueryMonitor( $this->connection, $this->settings );
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
	}

	/**
	 * Test capture_slow_queries returns empty array when no queries.
	 *
	 * @return void
	 */
	public function test_capture_slow_queries_returns_empty_when_no_queries(): void {
		$result = $this->monitor->capture_slow_queries( 50.0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_query_summary returns correct statistics.
	 *
	 * @return void
	 */
	public function test_get_query_summary_returns_correct_statistics(): void {
		// Mock total queries
		$this->connection->set_expected_result(
			'%%SELECT COUNT(*) FROM wp_wpha_query_log WHERE created_at >= %%',
			100
		);

		// Mock average time
		$this->connection->set_expected_result(
			'%%SELECT AVG(time_ms) FROM wp_wpha_query_log WHERE created_at >= %%',
			75.5
		);

		// Mock duplicate count
		$this->connection->set_expected_result(
			'%%SELECT COUNT(*) FROM wp_wpha_query_log WHERE is_duplicate = 1 AND created_at >= %%',
			10
		);

		// Mock needs index count
		$this->connection->set_expected_result(
			'%%SELECT COUNT(*) FROM wp_wpha_query_log WHERE needs_index = 1 AND created_at >= %%',
			5
		);

		// Mock slowest query
		$this->connection->set_expected_result(
			'%%SELECT sql, time_ms, caller FROM wp_wpha_query_log WHERE created_at >= %% ORDER BY time_ms DESC LIMIT 1%%',
			array(
				'sql'     => 'SELECT * FROM large_table',
				'time_ms' => 500.5,
				'caller'  => 'test_function',
			)
		);

		// Mock by component
		$this->connection->set_expected_result(
			'%%SELECT component, COUNT(*) as count, AVG(time_ms) as avg_time%%GROUP BY component%%',
			array(
				array( 'component' => 'plugin:test', 'count' => 50, 'avg_time' => 100.0 ),
				array( 'component' => 'core', 'count' => 30, 'avg_time' => 25.0 ),
			)
		);

		$result = $this->monitor->get_query_summary( 7 );

		$this->assertEquals( 7, $result['period_days'] );
		$this->assertEquals( 100, $result['total_queries'] );
		$this->assertEquals( 75.5, $result['avg_time_ms'] );
		$this->assertEquals( 10, $result['duplicate_count'] );
		$this->assertEquals( 5, $result['needs_index_count'] );
		$this->assertArrayHasKey( 'slowest_query', $result );
		$this->assertArrayHasKey( 'by_component', $result );
	}

	/**
	 * Test get_queries_by_caller formats results correctly.
	 *
	 * @return void
	 */
	public function test_get_queries_by_caller_formats_results(): void {
		$mock_results = array(
			array(
				'caller'            => 'WP_Query->get_posts()',
				'component'         => 'core',
				'query_count'       => 50,
				'avg_time'          => 25.5,
				'max_time'          => 150.0,
				'duplicate_count'   => 5,
				'needs_index_count' => 2,
			),
		);

		$this->connection->set_expected_result(
			'%%SELECT%%caller%%component%%COUNT(*)%%AVG(time_ms)%%MAX(time_ms)%%GROUP BY caller, component%%ORDER BY query_count DESC%%',
			$mock_results
		);

		$result = $this->monitor->get_queries_by_caller( 20, 7 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'WP_Query->get_posts()', $result[0]['caller'] );
		$this->assertEquals( 'core', $result[0]['component'] );
		$this->assertEquals( 50, $result[0]['query_count'] );
		$this->assertEquals( 25.5, $result[0]['avg_time_ms'] );
		$this->assertEquals( 150.0, $result[0]['max_time_ms'] );
		$this->assertEquals( 5, $result[0]['duplicate_count'] );
		$this->assertEquals( 2, $result[0]['needs_index_count'] );
	}

	/**
	 * Test get_queries_by_caller returns empty array when no results.
	 *
	 * @return void
	 */
	public function test_get_queries_by_caller_returns_empty_when_no_results(): void {
		$this->connection->set_expected_result(
			'%%SELECT%%caller%%component%%COUNT(*)%%',
			array()
		);

		$result = $this->monitor->get_queries_by_caller();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test export_query_log returns CSV format.
	 *
	 * @return void
	 */
	public function test_export_query_log_returns_csv_format(): void {
		$mock_results = array(
			array(
				'sql'          => 'SELECT * FROM wp_posts',
				'time_ms'      => 50.5,
				'caller'       => 'test_caller',
				'component'    => 'core',
				'is_duplicate' => 0,
				'needs_index'  => 0,
				'created_at'   => '2025-01-01 12:00:00',
			),
		);

		$this->connection->set_expected_result(
			'%%SELECT%%sql%%time_ms%%caller%%component%%is_duplicate%%needs_index%%created_at%%FROM wp_wpha_query_log%%',
			$mock_results
		);

		$result = $this->monitor->export_query_log( 7, 'csv' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'SQL', $result );
		$this->assertStringContainsString( 'Time (ms)', $result );
		$this->assertStringContainsString( 'SELECT * FROM wp_posts', $result );
	}

	/**
	 * Test export_query_log returns JSON format.
	 *
	 * @return void
	 */
	public function test_export_query_log_returns_json_format(): void {
		$mock_results = array(
			array(
				'sql'          => 'SELECT * FROM wp_posts',
				'time_ms'      => 50.5,
				'caller'       => 'test_caller',
				'component'    => 'core',
				'is_duplicate' => 0,
				'needs_index'  => 0,
				'created_at'   => '2025-01-01 12:00:00',
			),
		);

		$this->connection->set_expected_result(
			'%%SELECT%%sql%%time_ms%%caller%%component%%is_duplicate%%needs_index%%created_at%%',
			$mock_results
		);

		$result = $this->monitor->export_query_log( 7, 'json' );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'SELECT * FROM wp_posts', $result[0]['sql'] );
	}

	/**
	 * Test prune_old_logs deletes old entries.
	 *
	 * @return void
	 */
	public function test_prune_old_logs_deletes_old_entries(): void {
		$this->connection->set_expected_result(
			'%%DELETE FROM wp_wpha_query_log WHERE created_at < %%',
			100
		);

		$result = $this->monitor->prune_old_logs();

		$this->assertEquals( 100, $result );
	}

	/**
	 * Test prune_old_logs returns zero when prepare fails.
	 *
	 * @return void
	 */
	public function test_prune_old_logs_returns_zero_when_no_old_logs(): void {
		$this->connection->set_expected_result(
			'%%DELETE FROM wp_wpha_query_log WHERE created_at < %%',
			0
		);

		$result = $this->monitor->prune_old_logs();

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test is_query_monitor_active checks for Query Monitor plugin.
	 *
	 * @return void
	 */
	public function test_is_query_monitor_active_returns_false_by_default(): void {
		$result = $this->monitor->is_query_monitor_active();

		// QueryMonitor class doesn't exist in test environment
		$this->assertFalse( $result );
	}

	/**
	 * Test get_monitoring_status returns status array.
	 *
	 * @return void
	 */
	public function test_get_monitoring_status_returns_status_array(): void {
		$result = $this->monitor->get_monitoring_status();

		$this->assertArrayHasKey( 'query_monitor_active', $result );
		$this->assertArrayHasKey( 'savequeries_enabled', $result );
		$this->assertArrayHasKey( 'monitoring_enabled', $result );
		$this->assertArrayHasKey( 'settings', $result );
		$this->assertArrayHasKey( 'default_threshold', $result );
		$this->assertArrayHasKey( 'log_ttl_days', $result );
	}

	/**
	 * Test get_monitoring_status includes settings.
	 *
	 * @return void
	 */
	public function test_get_monitoring_status_includes_settings(): void {
		$result = $this->monitor->get_monitoring_status();

		$this->assertArrayHasKey( 'enable_query_monitoring', $result['settings'] );
		$this->assertArrayHasKey( 'query_logging_enabled', $result['settings'] );
		$this->assertArrayHasKey( 'monitoring_effective', $result['settings'] );
		$this->assertArrayHasKey( 'slow_query_threshold_ms', $result['settings'] );
	}

	/**
	 * Test constants are defined correctly.
	 *
	 * @return void
	 */
	public function test_constants_are_defined(): void {
		$this->assertEquals( 50.0, QueryMonitor::DEFAULT_THRESHOLD );
		$this->assertEquals( 604800, QueryMonitor::LOG_TTL ); // 7 days
		$this->assertEquals( 10000, QueryMonitor::MAX_LOG_ROWS );
		$this->assertEquals( 10, QueryMonitor::MAX_EXPLAIN_PER_REQUEST );
		$this->assertEquals( 'wpha_query_log_last_prune', QueryMonitor::PRUNE_TRANSIENT );
	}

	/**
	 * Test QueryMonitor constructs without settings.
	 *
	 * @return void
	 */
	public function test_query_monitor_constructs_without_settings(): void {
		$monitor = new QueryMonitor( $this->connection, null );

		$status = $monitor->get_monitoring_status();

		$this->assertArrayHasKey( 'settings', $status );
	}

	/**
	 * Test get_query_summary handles zero queries.
	 *
	 * @return void
	 */
	public function test_get_query_summary_handles_zero_queries(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 0 );
		$this->connection->set_expected_result( '%%SELECT AVG(time_ms)%%', null );
		$this->connection->set_expected_result( '%%is_duplicate = 1%%', 0 );
		$this->connection->set_expected_result( '%%needs_index = 1%%', 0 );
		$this->connection->set_expected_result( '%%ORDER BY time_ms DESC LIMIT 1%%', null );
		$this->connection->set_expected_result( '%%GROUP BY component%%', array() );

		$result = $this->monitor->get_query_summary();

		$this->assertEquals( 0, $result['total_queries'] );
		$this->assertEquals( 0.0, $result['avg_time_ms'] );
	}

	/**
	 * Test log TTL in days calculation.
	 *
	 * @return void
	 */
	public function test_log_ttl_days_calculation(): void {
		$status = $this->monitor->get_monitoring_status();

		$this->assertEquals( 7, $status['log_ttl_days'] );
	}
}
