<?php
/**
 * Unit tests for AjaxMonitor class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\AjaxMonitor;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for AjaxMonitor.
 *
 * @covers \WPAdminHealth\Performance\AjaxMonitor
 */
class AjaxMonitorTest extends StandaloneTestCase {

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
	 * AjaxMonitor instance.
	 *
	 * @var AjaxMonitor
	 */
	private AjaxMonitor $monitor;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->settings   = $this->createMock( SettingsInterface::class );

		// Default settings mock behavior - disable monitoring to skip hook init.
		$this->settings->method( 'get_setting' )->willReturnCallback(
			function ( $key, $default ) {
				$settings = array(
					'enable_ajax_monitoring'    => false,
					'ajax_log_retention_days'   => 7,
					'ajax_log_max_rows'         => 10000,
				);
				return $settings[ $key ] ?? $default;
			}
		);

		$this->monitor = new AjaxMonitor( $this->connection, $this->settings );
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		delete_transient( AjaxMonitor::PRUNE_TRANSIENT );
	}

	/**
	 * Test log_ajax_request inserts record.
	 *
	 * @return void
	 */
	public function test_log_ajax_request_inserts_record(): void {
		// Default rows_affected is 1, so insert should succeed.
		$this->connection->set_rows_affected( 1 );

		$result = $this->monitor->log_ajax_request( 'test_action', 100.5, 1024, 'administrator' );

		$this->assertTrue( $result );
	}

	/**
	 * Test log_ajax_request returns false on failure.
	 *
	 * @return void
	 */
	public function test_log_ajax_request_returns_false_on_failure(): void {
		// Set an error to make insert fail.
		$this->connection->set_last_error( 'Insert failed' );

		$result = $this->monitor->log_ajax_request( 'test_action', 100.5, 1024, 'administrator' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_ajax_summary returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_ajax_summary_returns_expected_structure(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 100 );
		$this->connection->set_expected_result( '%%SELECT AVG(execution_time)%%', 150.5 );
		$this->connection->set_expected_result( '%%SELECT AVG(memory_used)%%', 2048 );
		$this->connection->set_expected_result(
			'%%ORDER BY execution_time DESC%%LIMIT 1%%',
			array(
				'action'         => 'slow_action',
				'execution_time' => 5000.0,
				'created_at'     => '2025-01-01 12:00:00',
			)
		);
		$this->connection->set_expected_result(
			'%%GROUP BY user_role%%',
			array(
				array( 'user_role' => 'administrator', 'count' => 50 ),
				array( 'user_role' => 'editor', 'count' => 30 ),
			)
		);

		$result = $this->monitor->get_ajax_summary( '24hours' );

		$this->assertArrayHasKey( 'period', $result );
		$this->assertArrayHasKey( 'total_requests', $result );
		$this->assertArrayHasKey( 'requests_per_min', $result );
		$this->assertArrayHasKey( 'avg_response_time', $result );
		$this->assertArrayHasKey( 'avg_memory_bytes', $result );
		$this->assertArrayHasKey( 'avg_memory_human', $result );
		$this->assertArrayHasKey( 'slowest_request', $result );
		$this->assertArrayHasKey( 'by_role', $result );
	}

	/**
	 * Test get_ajax_summary handles different periods.
	 *
	 * @return void
	 */
	public function test_get_ajax_summary_handles_different_periods(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 50 );
		$this->connection->set_expected_result( '%%SELECT AVG(execution_time)%%', 100.0 );
		$this->connection->set_expected_result( '%%SELECT AVG(memory_used)%%', 1024 );
		$this->connection->set_expected_result( '%%ORDER BY execution_time DESC%%', null );
		$this->connection->set_expected_result( '%%GROUP BY user_role%%', array() );

		$periods = array( '1hour', '24hours', '7days', '30days' );

		foreach ( $periods as $period ) {
			$result = $this->monitor->get_ajax_summary( $period );
			$this->assertEquals( $period, $result['period'] );
		}
	}

	/**
	 * Test get_frequent_ajax_actions returns formatted results.
	 *
	 * @return void
	 */
	public function test_get_frequent_ajax_actions_returns_formatted_results(): void {
		$mock_results = array(
			array(
				'action'        => 'heartbeat',
				'request_count' => 500,
				'avg_time'      => 50.0,
				'max_time'      => 200.0,
				'min_time'      => 10.0,
				'avg_memory'    => 1024,
			),
			array(
				'action'        => 'save_post',
				'request_count' => 100,
				'avg_time'      => 150.0,
				'max_time'      => 500.0,
				'min_time'      => 50.0,
				'avg_memory'    => 2048,
			),
		);

		$this->connection->set_expected_result( '%%GROUP BY action%%ORDER BY request_count DESC%%', $mock_results );

		$result = $this->monitor->get_frequent_ajax_actions( '24hours', 10 );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'heartbeat', $result[0]['action'] );
		$this->assertEquals( 500, $result[0]['request_count'] );
		$this->assertEquals( 50.0, $result[0]['avg_time_ms'] );
		$this->assertEquals( 200.0, $result[0]['max_time_ms'] );
		$this->assertEquals( 10.0, $result[0]['min_time_ms'] );
		$this->assertArrayHasKey( 'avg_memory', $result[0] );
	}

	/**
	 * Test get_frequent_ajax_actions returns empty array when no results.
	 *
	 * @return void
	 */
	public function test_get_frequent_ajax_actions_returns_empty_when_no_results(): void {
		$this->connection->set_expected_result( '%%GROUP BY action%%ORDER BY request_count DESC%%', array() );

		$result = $this->monitor->get_frequent_ajax_actions();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_slow_ajax_actions returns formatted results.
	 *
	 * @return void
	 */
	public function test_get_slow_ajax_actions_returns_formatted_results(): void {
		$mock_results = array(
			array(
				'action'     => 'heavy_action',
				'slow_count' => 25,
				'avg_time'   => 2500.0,
				'max_time'   => 5000.0,
			),
		);

		$this->connection->set_expected_result( '%%execution_time >= %%GROUP BY action%%ORDER BY avg_time DESC%%', $mock_results );

		$result = $this->monitor->get_slow_ajax_actions( 1000.0, '24hours', 10 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'heavy_action', $result[0]['action'] );
		$this->assertEquals( 25, $result[0]['slow_count'] );
		$this->assertEquals( 2500.0, $result[0]['avg_time_ms'] );
		$this->assertEquals( 5000.0, $result[0]['max_time_ms'] );
	}

	/**
	 * Test get_slow_ajax_actions returns empty when no results.
	 *
	 * @return void
	 */
	public function test_get_slow_ajax_actions_returns_empty_when_no_results(): void {
		$this->connection->set_expected_result( '%%execution_time >= %%', array() );

		$result = $this->monitor->get_slow_ajax_actions();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_excessive_polling returns results with high frequency.
	 *
	 * @return void
	 */
	public function test_get_excessive_polling_returns_results(): void {
		$mock_results = array(
			array(
				'action'        => 'frequent_poll',
				'request_count' => 120,
				'avg_time'      => 25.0,
			),
		);

		$this->connection->set_expected_result( '%%HAVING request_count >= %%', $mock_results );

		$result = $this->monitor->get_excessive_polling( '1hour', 60 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'frequent_poll', $result[0]['action'] );
		$this->assertEquals( 120, $result[0]['request_count'] );
		$this->assertArrayHasKey( 'requests_per_min', $result[0] );
		$this->assertArrayHasKey( 'optimization_note', $result[0] );
	}

	/**
	 * Test get_excessive_polling detects high frequency.
	 *
	 * @return void
	 */
	public function test_get_excessive_polling_detects_high_frequency(): void {
		$mock_results = array(
			array(
				'action'        => 'rapid_poll',
				'request_count' => 180, // 3 per minute over 1 hour.
				'avg_time'      => 20.0,
			),
		);

		$this->connection->set_expected_result( '%%HAVING request_count >= %%', $mock_results );

		$result = $this->monitor->get_excessive_polling( '1hour', 60 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 3.0, $result[0]['requests_per_min'] );
		$this->assertEquals( 'High polling frequency detected', $result[0]['optimization_note'] );
	}

	/**
	 * Test get_redundant_requests detects patterns.
	 *
	 * @return void
	 */
	public function test_get_redundant_requests_returns_results(): void {
		$base_time    = time() - 1800; // 30 minutes ago.
		$mock_results = array();

		// Simulate redundant requests - 5 of the same action within 60 seconds.
		for ( $i = 0; $i < 5; $i++ ) {
			$mock_results[] = array(
				'action'         => 'redundant_action',
				'user_role'      => 'administrator',
				'created_at'     => gmdate( 'Y-m-d H:i:s', $base_time + ( $i * 10 ) ),
				'execution_time' => 50.0,
			);
		}

		$this->connection->set_expected_result( '%%ORDER BY action, created_at%%', $mock_results );

		$result = $this->monitor->get_redundant_requests( 60, 3 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'redundant_action', $result[0]['action'] );
		$this->assertEquals( 5, $result[0]['occurrences'] );
		$this->assertEquals( 'administrator', $result[0]['user_role'] );
	}

	/**
	 * Test get_redundant_requests returns empty when no patterns.
	 *
	 * @return void
	 */
	public function test_get_redundant_requests_returns_empty_when_no_patterns(): void {
		$this->connection->set_expected_result( '%%ORDER BY action, created_at%%', array() );

		$result = $this->monitor->get_redundant_requests();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test prune_old_logs deletes old entries.
	 *
	 * @return void
	 */
	public function test_prune_old_logs_deletes_old_entries(): void {
		$this->connection->set_expected_result( '%%DELETE FROM%%created_at < %%', 50 );

		$result = $this->monitor->prune_old_logs();

		$this->assertEquals( 50, $result );
	}

	/**
	 * Test prune_old_logs returns zero when no old entries.
	 *
	 * @return void
	 */
	public function test_prune_old_logs_returns_zero_when_no_old_entries(): void {
		$this->connection->set_expected_result( '%%DELETE FROM%%created_at < %%', 0 );

		$result = $this->monitor->prune_old_logs();

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_monitoring_status returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_monitoring_status_returns_expected_structure(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 500 );
		$this->connection->set_expected_result( '%%ORDER BY created_at ASC LIMIT 1%%', '2025-01-01 00:00:00' );

		$status = $this->monitor->get_monitoring_status();

		$this->assertArrayHasKey( 'monitoring_enabled', $status );
		$this->assertArrayHasKey( 'total_requests_logged', $status );
		$this->assertArrayHasKey( 'oldest_log', $status );
		$this->assertArrayHasKey( 'log_ttl_days', $status );
		$this->assertArrayHasKey( 'max_rows', $status );
	}

	/**
	 * Test get_monitoring_status shows correct values.
	 *
	 * @return void
	 */
	public function test_get_monitoring_status_shows_correct_values(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 1000 );
		$this->connection->set_expected_result( '%%ORDER BY created_at ASC LIMIT 1%%', '2025-01-15 10:00:00' );

		$status = $this->monitor->get_monitoring_status();

		$this->assertTrue( $status['monitoring_enabled'] );
		$this->assertEquals( 1000, $status['total_requests_logged'] );
		$this->assertEquals( '2025-01-15 10:00:00', $status['oldest_log'] );
		$this->assertEquals( 7, $status['log_ttl_days'] );
		$this->assertEquals( 10000, $status['max_rows'] );
	}

	/**
	 * Test LOG_TTL constant.
	 *
	 * @return void
	 */
	public function test_log_ttl_constant(): void {
		$this->assertEquals( 604800, AjaxMonitor::LOG_TTL ); // 7 days in seconds.
	}

	/**
	 * Test PRUNE_TRANSIENT constant.
	 *
	 * @return void
	 */
	public function test_prune_transient_constant(): void {
		$this->assertEquals( 'wpha_ajax_log_last_prune', AjaxMonitor::PRUNE_TRANSIENT );
	}

	/**
	 * Test AjaxMonitor constructs without settings.
	 *
	 * @return void
	 */
	public function test_ajax_monitor_constructs_without_settings(): void {
		$monitor = new AjaxMonitor( $this->connection, null );

		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 0 );
		$this->connection->set_expected_result( '%%ORDER BY created_at ASC LIMIT 1%%', null );

		$status = $monitor->get_monitoring_status();

		$this->assertArrayHasKey( 'monitoring_enabled', $status );
	}

	/**
	 * Test get_ajax_summary handles zero requests.
	 *
	 * @return void
	 */
	public function test_get_ajax_summary_handles_zero_requests(): void {
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 0 );
		$this->connection->set_expected_result( '%%SELECT AVG(execution_time)%%', null );
		$this->connection->set_expected_result( '%%SELECT AVG(memory_used)%%', null );
		$this->connection->set_expected_result( '%%ORDER BY execution_time DESC%%LIMIT 1%%', null );
		$this->connection->set_expected_result( '%%GROUP BY user_role%%', array() );

		$result = $this->monitor->get_ajax_summary();

		$this->assertEquals( 0, $result['total_requests'] );
		$this->assertEquals( 0.0, $result['avg_response_time'] );
		$this->assertEquals( 0, $result['avg_memory_bytes'] );
	}

	/**
	 * Test requests_per_min calculation.
	 *
	 * @return void
	 */
	public function test_requests_per_min_calculation(): void {
		// 1440 requests in 24 hours = 1 request per minute.
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 1440 );
		$this->connection->set_expected_result( '%%SELECT AVG(execution_time)%%', 100.0 );
		$this->connection->set_expected_result( '%%SELECT AVG(memory_used)%%', 1024 );
		$this->connection->set_expected_result( '%%ORDER BY execution_time DESC%%', null );
		$this->connection->set_expected_result( '%%GROUP BY user_role%%', array() );

		$result = $this->monitor->get_ajax_summary( '24hours' );

		$this->assertEquals( 1.0, $result['requests_per_min'] );
	}

	/**
	 * Test avg_memory_human formatting.
	 *
	 * @return void
	 */
	public function test_avg_memory_human_formatting(): void {
		// 1048576 bytes = 1 MB.
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 100 );
		$this->connection->set_expected_result( '%%SELECT AVG(execution_time)%%', 100.0 );
		$this->connection->set_expected_result( '%%SELECT AVG(memory_used)%%', 1048576 );
		$this->connection->set_expected_result( '%%ORDER BY execution_time DESC%%', null );
		$this->connection->set_expected_result( '%%GROUP BY user_role%%', array() );

		$result = $this->monitor->get_ajax_summary();

		$this->assertEquals( 1048576, $result['avg_memory_bytes'] );
		$this->assertNotEmpty( $result['avg_memory_human'] );
	}

	/**
	 * Test handle_log_cleanup calls prune methods.
	 *
	 * @return void
	 */
	public function test_handle_log_cleanup_calls_prune_methods(): void {
		$this->connection->set_expected_result( '%%DELETE FROM%%created_at < %%', 10 );
		$this->connection->set_expected_result( '%%SELECT COUNT(*)%%', 5000 ); // Under max_rows.

		// Should not throw.
		$this->monitor->handle_log_cleanup();

		$this->assertTrue( true );
	}
}
