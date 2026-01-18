<?php
/**
 * CollectMetrics Use Case Tests (Standalone)
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Application\Performance
 */

namespace WPAdminHealth\Tests\UnitStandalone\Application\Performance;

use WPAdminHealth\Application\Performance\CollectMetrics;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Performance\CacheChecker;
use WPAdminHealth\Tests\StandaloneTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * CollectMetrics use case tests.
 */
class CollectMetricsTest extends StandaloneTestCase {

	/** @var SettingsInterface&MockObject */
	private $settings;

	/** @var AutoloadAnalyzerInterface&MockObject */
	private $autoload_analyzer;

	/** @var QueryMonitorInterface&MockObject */
	private $query_monitor;

	/** @var PluginProfilerInterface&MockObject */
	private $plugin_profiler;

	/** @var CacheChecker&MockObject */
	private $cache_checker;

	/** @var ActivityLoggerInterface&MockObject */
	private $activity_logger;

	/** @var CollectMetrics */
	private CollectMetrics $use_case;

	protected function setup_test_environment(): void {
		$this->settings          = $this->createMock( SettingsInterface::class );
		$this->autoload_analyzer = $this->createMock( AutoloadAnalyzerInterface::class );
		$this->query_monitor     = $this->createMock( QueryMonitorInterface::class );
		$this->plugin_profiler   = $this->createMock( PluginProfilerInterface::class );
		$this->cache_checker     = $this->createMock( CacheChecker::class );
		$this->activity_logger   = $this->createMock( ActivityLoggerInterface::class );

		$this->use_case = new CollectMetrics(
			$this->settings,
			$this->autoload_analyzer,
			$this->query_monitor,
			$this->plugin_profiler,
			$this->cache_checker,
			$this->activity_logger
		);
	}

	public function test_execute_returns_stable_schema(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 100 );

		$this->autoload_analyzer->method( 'get_autoload_size' )->willReturn(
			array(
				'total_size'     => 1234,
				'count'          => 10,
				'formatted_size' => '1.21 KB',
			)
		);
		$this->autoload_analyzer->method( 'find_large_autoloads' )->willReturn(
			array(
				array( 'name' => 'opt1', 'size' => 60000, 'formatted_size' => '58.6 KB' ),
			)
		);
		$this->autoload_analyzer->method( 'recommend_autoload_changes' )->willReturn( array() );

		$this->query_monitor->method( 'get_query_summary' )->willReturn(
			array(
				'total_queries' => 50,
				'slow_queries'  => 2,
				'avg_time'      => 0.01,
				'total_time'    => 0.5,
			)
		);
		$this->query_monitor->method( 'get_monitoring_status' )->willReturn(
			array(
				'enabled'      => true,
				'threshold_ms' => 100.0,
				'log_count'    => 0,
				'table_exists' => true,
			)
		);
		$this->query_monitor->method( 'get_queries_by_caller' )->willReturn( array() );
		$this->query_monitor->method( 'capture_slow_queries' )->willReturn( array() );

		$this->plugin_profiler->method( 'measure_plugin_impact' )->willReturn(
			array(
				'status'       => 'success',
				'measurements' => array(),
				'note'         => '',
			)
		);
		$this->plugin_profiler->method( 'get_slowest_plugins' )->willReturn( array() );

		$this->cache_checker->method( 'get_cache_status' )->willReturn(
			array(
				'persistent_cache_available' => false,
				'cache_type'                => 'none',
				'cache_backend'             => 'none',
				'hit_rate'                  => null,
			)
		);
		$this->cache_checker->method( 'get_cache_recommendations' )->willReturn( array() );

		$this->activity_logger
			->expects( $this->once() )
			->method( 'log_performance_check' )
			->with( 'collect_metrics', $this->isType( 'array' ) );

		$result = $this->use_case->execute();

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'timestamp', $result );
		$this->assertArrayHasKey( 'metrics', $result );

		$this->assertArrayHasKey( 'autoload', $result['metrics'] );
		$this->assertArrayHasKey( 'queries', $result['metrics'] );
		$this->assertArrayHasKey( 'plugins', $result['metrics'] );
		$this->assertArrayHasKey( 'cache', $result['metrics'] );

		$this->assertSame( 1234, $result['metrics']['autoload']['total_size'] );
		$this->assertSame( 1, $result['metrics']['autoload']['large_options_count'] );
	}

	public function test_execute_safe_mode_skips_heavy_work_and_logging(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 100 );

		$this->autoload_analyzer->method( 'get_autoload_size' )->willReturn( array() );
		$this->autoload_analyzer->method( 'find_large_autoloads' )->willReturn( array() );
		$this->autoload_analyzer->method( 'recommend_autoload_changes' )->willReturn( array() );

		$this->query_monitor->method( 'get_query_summary' )->willReturn( array() );
		$this->query_monitor->method( 'get_monitoring_status' )->willReturn( array() );
		$this->query_monitor->method( 'get_queries_by_caller' )->willReturn( array() );
		$this->query_monitor->expects( $this->never() )->method( 'capture_slow_queries' );

		$this->plugin_profiler->expects( $this->never() )->method( 'measure_plugin_impact' );
		$this->plugin_profiler->expects( $this->never() )->method( 'get_slowest_plugins' );

		$this->cache_checker->method( 'get_cache_status' )->willReturn( array() );
		$this->cache_checker->method( 'get_cache_recommendations' )->willReturn( array() );

		$this->activity_logger->expects( $this->never() )->method( 'log_performance_check' );

		$result = $this->use_case->execute();

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertTrue( $result['metrics']['plugins']['skipped'] );
		$this->assertTrue( $result['metrics']['queries']['slow_queries_skipped'] );
		$this->assertSame( array(), $result['metrics']['queries']['slow_queries'] );
	}
}
