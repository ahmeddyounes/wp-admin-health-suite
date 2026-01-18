<?php
/**
 * Unit tests for PluginProfiler class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\PluginProfiler;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for PluginProfiler.
 *
 * @covers \WPAdminHealth\Performance\PluginProfiler
 */
class PluginProfilerTest extends StandaloneTestCase {

	/**
	 * Mock database connection.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * PluginProfiler instance.
	 *
	 * @var PluginProfiler
	 */
	private PluginProfiler $profiler;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->profiler   = new PluginProfiler( $this->connection );
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		// Clear any cached transients
		delete_transient( PluginProfiler::TRANSIENT_KEY );
	}

	/**
	 * Test measure_plugin_impact returns cached results when available.
	 *
	 * @return void
	 */
	public function test_measure_plugin_impact_returns_cached_results(): void {
		$cached_data = array(
			'status'       => 'success',
			'measured_at'  => '2025-01-01 12:00:00',
			'measurements' => array(
				'test-plugin' => array(
					'name'         => 'Test Plugin',
					'queries'      => 5,
					'memory'       => 1024,
					'time'         => 0.05,
					'assets'       => array( 'scripts' => 2, 'styles' => 1, 'total' => 3 ),
					'impact_score' => 20.5,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->measure_plugin_impact();

		$this->assertEquals( $cached_data, $result );
	}

	/**
	 * Test measure_plugin_impact returns error when no active plugins.
	 *
	 * @return void
	 */
	public function test_measure_plugin_impact_returns_error_when_no_active_plugins(): void {
		delete_transient( PluginProfiler::TRANSIENT_KEY );

		// Clear active_plugins option
		global $wp_test_options;
		$wp_test_options['active_plugins'] = array();

		$result = $this->profiler->measure_plugin_impact();

		$this->assertEquals( 'error', $result['status'] );
		$this->assertEquals( 'No active plugins found.', $result['message'] );

		unset( $wp_test_options['active_plugins'] );
	}

	/**
	 * Test get_slowest_plugins returns empty array on error.
	 *
	 * @return void
	 */
	public function test_get_slowest_plugins_returns_empty_on_error(): void {
		delete_transient( PluginProfiler::TRANSIENT_KEY );

		global $wp_test_options;
		$wp_test_options['active_plugins'] = array();

		$result = $this->profiler->get_slowest_plugins();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		unset( $wp_test_options['active_plugins'] );
	}

	/**
	 * Test get_slowest_plugins respects limit.
	 *
	 * @return void
	 */
	public function test_get_slowest_plugins_respects_limit(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'plugin-1' => array(
					'name'         => 'Plugin 1',
					'queries'      => 10,
					'memory'       => 2048,
					'time'         => 0.1,
					'assets'       => array( 'total' => 5 ),
					'impact_score' => 50.0,
				),
				'plugin-2' => array(
					'name'         => 'Plugin 2',
					'queries'      => 5,
					'memory'       => 1024,
					'time'         => 0.05,
					'assets'       => array( 'total' => 2 ),
					'impact_score' => 30.0,
				),
				'plugin-3' => array(
					'name'         => 'Plugin 3',
					'queries'      => 2,
					'memory'       => 512,
					'time'         => 0.02,
					'assets'       => array( 'total' => 1 ),
					'impact_score' => 10.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_slowest_plugins( 2 );

		$this->assertCount( 2, $result );
	}

	/**
	 * Test get_plugin_memory_usage returns empty on error.
	 *
	 * @return void
	 */
	public function test_get_plugin_memory_usage_returns_empty_on_error(): void {
		delete_transient( PluginProfiler::TRANSIENT_KEY );

		global $wp_test_options;
		$wp_test_options['active_plugins'] = array();

		$result = $this->profiler->get_plugin_memory_usage();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		unset( $wp_test_options['active_plugins'] );
	}

	/**
	 * Test get_plugin_memory_usage formats results correctly.
	 *
	 * @return void
	 */
	public function test_get_plugin_memory_usage_formats_results(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'heavy-plugin' => array(
					'name'         => 'Heavy Plugin',
					'queries'      => 10,
					'memory'       => 10485760, // 10 MB
					'time'         => 0.1,
					'assets'       => array( 'total' => 5 ),
					'impact_score' => 50.0,
				),
				'light-plugin' => array(
					'name'         => 'Light Plugin',
					'queries'      => 2,
					'memory'       => 1048576, // 1 MB
					'time'         => 0.02,
					'assets'       => array( 'total' => 1 ),
					'impact_score' => 10.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_plugin_memory_usage();

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'heavy-plugin', $result );
		$this->assertEquals( 'Heavy Plugin', $result['heavy-plugin']['name'] );
		$this->assertEquals( 10485760, $result['heavy-plugin']['memory'] );
		$this->assertArrayHasKey( 'formatted', $result['heavy-plugin'] );
	}

	/**
	 * Test get_plugin_memory_usage sorts by memory descending.
	 *
	 * @return void
	 */
	public function test_get_plugin_memory_usage_sorts_by_memory(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'light-plugin' => array(
					'name'         => 'Light Plugin',
					'memory'       => 1000,
					'queries'      => 1,
					'time'         => 0.01,
					'assets'       => array( 'total' => 0 ),
					'impact_score' => 5.0,
				),
				'heavy-plugin' => array(
					'name'         => 'Heavy Plugin',
					'memory'       => 5000,
					'queries'      => 5,
					'time'         => 0.05,
					'assets'       => array( 'total' => 2 ),
					'impact_score' => 25.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_plugin_memory_usage();
		$keys   = array_keys( $result );

		// Heavy plugin should come first (sorted by memory desc)
		$this->assertEquals( 'heavy-plugin', $keys[0] );
		$this->assertEquals( 'light-plugin', $keys[1] );
	}

	/**
	 * Test get_plugin_query_counts returns empty on error.
	 *
	 * @return void
	 */
	public function test_get_plugin_query_counts_returns_empty_on_error(): void {
		delete_transient( PluginProfiler::TRANSIENT_KEY );

		global $wp_test_options;
		$wp_test_options['active_plugins'] = array();

		$result = $this->profiler->get_plugin_query_counts();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		unset( $wp_test_options['active_plugins'] );
	}

	/**
	 * Test get_plugin_query_counts formats results correctly.
	 *
	 * @return void
	 */
	public function test_get_plugin_query_counts_formats_results(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'query-heavy' => array(
					'name'         => 'Query Heavy',
					'queries'      => 50,
					'memory'       => 2048,
					'time'         => 0.5,
					'assets'       => array( 'total' => 3 ),
					'impact_score' => 80.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_plugin_query_counts();

		$this->assertArrayHasKey( 'query-heavy', $result );
		$this->assertEquals( 'Query Heavy', $result['query-heavy']['name'] );
		$this->assertEquals( 50, $result['query-heavy']['count'] );
		$this->assertEquals( 0.5, $result['query-heavy']['total_time'] );
	}

	/**
	 * Test get_plugin_query_counts sorts by count descending.
	 *
	 * @return void
	 */
	public function test_get_plugin_query_counts_sorts_by_count(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'few-queries' => array(
					'name'         => 'Few Queries',
					'queries'      => 5,
					'memory'       => 1024,
					'time'         => 0.05,
					'assets'       => array( 'total' => 1 ),
					'impact_score' => 10.0,
				),
				'many-queries' => array(
					'name'         => 'Many Queries',
					'queries'      => 100,
					'memory'       => 2048,
					'time'         => 0.5,
					'assets'       => array( 'total' => 2 ),
					'impact_score' => 120.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_plugin_query_counts();
		$keys   = array_keys( $result );

		$this->assertEquals( 'many-queries', $keys[0] );
		$this->assertEquals( 'few-queries', $keys[1] );
	}

	/**
	 * Test get_asset_counts_by_plugin returns empty on error.
	 *
	 * @return void
	 */
	public function test_get_asset_counts_by_plugin_returns_empty_on_error(): void {
		delete_transient( PluginProfiler::TRANSIENT_KEY );

		global $wp_test_options;
		$wp_test_options['active_plugins'] = array();

		$result = $this->profiler->get_asset_counts_by_plugin();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		unset( $wp_test_options['active_plugins'] );
	}

	/**
	 * Test get_asset_counts_by_plugin formats results correctly.
	 *
	 * @return void
	 */
	public function test_get_asset_counts_by_plugin_formats_results(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'asset-heavy' => array(
					'name'         => 'Asset Heavy',
					'queries'      => 5,
					'memory'       => 2048,
					'time'         => 0.1,
					'assets'       => array(
						'scripts' => 10,
						'styles'  => 5,
						'total'   => 15,
					),
					'impact_score' => 100.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_asset_counts_by_plugin();

		$this->assertArrayHasKey( 'asset-heavy', $result );
		$this->assertEquals( 'Asset Heavy', $result['asset-heavy']['name'] );
		$this->assertEquals( 10, $result['asset-heavy']['scripts'] );
		$this->assertEquals( 5, $result['asset-heavy']['styles'] );
		$this->assertEquals( 15, $result['asset-heavy']['total'] );
	}

	/**
	 * Test get_asset_counts_by_plugin sorts by total assets descending.
	 *
	 * @return void
	 */
	public function test_get_asset_counts_by_plugin_sorts_by_total(): void {
		$cached_data = array(
			'status'       => 'success',
			'measurements' => array(
				'few-assets' => array(
					'name'         => 'Few Assets',
					'queries'      => 2,
					'memory'       => 512,
					'time'         => 0.02,
					'assets'       => array( 'scripts' => 1, 'styles' => 1, 'total' => 2 ),
					'impact_score' => 15.0,
				),
				'many-assets' => array(
					'name'         => 'Many Assets',
					'queries'      => 5,
					'memory'       => 1024,
					'time'         => 0.05,
					'assets'       => array( 'scripts' => 8, 'styles' => 4, 'total' => 12 ),
					'impact_score' => 85.0,
				),
			),
		);

		set_transient( PluginProfiler::TRANSIENT_KEY, $cached_data, 3600 );

		$result = $this->profiler->get_asset_counts_by_plugin();
		$keys   = array_keys( $result );

		$this->assertEquals( 'many-assets', $keys[0] );
		$this->assertEquals( 'few-assets', $keys[1] );
	}

	/**
	 * Test clear_cache clears transient.
	 *
	 * @return void
	 */
	public function test_clear_cache_clears_transient(): void {
		set_transient( PluginProfiler::TRANSIENT_KEY, array( 'test' => 'data' ), 3600 );

		$result = $this->profiler->clear_cache();

		$this->assertTrue( $result );
		$this->assertFalse( get_transient( PluginProfiler::TRANSIENT_KEY ) );
	}

	/**
	 * Test transient key constant.
	 *
	 * @return void
	 */
	public function test_transient_key_constant(): void {
		$this->assertEquals( 'wpahs_plugin_profiler_results', PluginProfiler::TRANSIENT_KEY );
	}

	/**
	 * Test max files per plugin constant.
	 *
	 * @return void
	 */
	public function test_max_files_per_plugin_constant(): void {
		$this->assertEquals( 500, PluginProfiler::MAX_FILES_PER_PLUGIN );
	}

	/**
	 * Test transient expiration constant.
	 *
	 * @return void
	 */
	public function test_transient_expiration_constant(): void {
		$this->assertEquals( DAY_IN_SECONDS, PluginProfiler::TRANSIENT_EXPIRATION );
	}
}
