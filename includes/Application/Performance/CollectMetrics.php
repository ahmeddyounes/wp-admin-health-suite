<?php
/**
 * Collect Metrics Use Case
 *
 * Application service for orchestrating performance metrics collection.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Performance\CacheChecker;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CollectMetrics
 *
 * Orchestrates performance metrics collection for both UI and scheduled tasks.
 *
 * @since 1.4.0
 */
class CollectMetrics {

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Autoload analyzer.
	 *
	 * @var AutoloadAnalyzerInterface
	 */
	private AutoloadAnalyzerInterface $autoload_analyzer;

	/**
	 * Query monitor.
	 *
	 * @var QueryMonitorInterface
	 */
	private QueryMonitorInterface $query_monitor;

	/**
	 * Plugin profiler.
	 *
	 * @var PluginProfilerInterface
	 */
	private PluginProfilerInterface $plugin_profiler;

	/**
	 * Cache checker.
	 *
	 * @var CacheChecker
	 */
	private CacheChecker $cache_checker;

	/**
	 * Optional activity logger.
	 *
	 * @var ActivityLoggerInterface|null
	 */
	private ?ActivityLoggerInterface $activity_logger;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param SettingsInterface            $settings          Settings instance.
	 * @param AutoloadAnalyzerInterface    $autoload_analyzer Autoload analyzer.
	 * @param QueryMonitorInterface        $query_monitor     Query monitor.
	 * @param PluginProfilerInterface      $plugin_profiler   Plugin profiler.
	 * @param CacheChecker                 $cache_checker     Cache checker.
	 * @param ActivityLoggerInterface|null $activity_logger   Optional activity logger.
	 */
	public function __construct(
		SettingsInterface $settings,
		AutoloadAnalyzerInterface $autoload_analyzer,
		QueryMonitorInterface $query_monitor,
		PluginProfilerInterface $plugin_profiler,
		CacheChecker $cache_checker,
		?ActivityLoggerInterface $activity_logger = null
	) {
		$this->settings          = $settings;
		$this->autoload_analyzer = $autoload_analyzer;
		$this->query_monitor     = $query_monitor;
		$this->plugin_profiler   = $plugin_profiler;
		$this->cache_checker     = $cache_checker;
		$this->activity_logger   = $activity_logger;
	}

	/**
	 * Execute the metrics collection operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Collection options.
	 *                       - safe_mode: bool - Override safe mode setting.
	 *                       - threshold_ms: float - Slow query threshold in milliseconds.
	 * @return array Collected metrics data.
	 */
	public function execute( array $options = array() ): array {
		$safe_mode    = $this->determine_safe_mode( $options );
		$threshold_ms = (float) ( $options['threshold_ms'] ?? $this->settings->get_setting( 'slow_query_threshold_ms', 100 ) );

		$result = array(
			'success'   => true,
			'message'   => 'Metrics collected.',
			'timestamp' => current_time( 'mysql' ),
			'metrics'   => array(
				'autoload' => $this->collect_autoload_metrics(),
				'queries'  => $this->collect_query_metrics( $threshold_ms, $safe_mode ),
				'plugins'  => $this->collect_plugin_metrics( $safe_mode ),
				'cache'    => $this->collect_cache_metrics(),
			),
		);

		if ( $safe_mode ) {
			$result['safe_mode']    = true;
			$result['preview_only'] = true;
		}

		if ( ! $safe_mode ) {
			$this->log_activity( $result );
		}

		return $result;
	}

	/**
	 * Determine safe mode from options or settings.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Execution options.
	 * @return bool Whether to run in safe mode.
	 */
	private function determine_safe_mode( array $options ): bool {
		if ( isset( $options['safe_mode'] ) ) {
			return (bool) $options['safe_mode'];
		}

		return $this->settings->is_safe_mode_enabled();
	}

	/**
	 * Collect autoload metrics.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private function collect_autoload_metrics(): array {
		try {
			$autoload_size   = $this->autoload_analyzer->get_autoload_size();
			$large_autoloads = $this->autoload_analyzer->find_large_autoloads();

			return array(
				'total_size'          => (int) ( $autoload_size['total_size'] ?? 0 ),
				'count'               => (int) ( $autoload_size['count'] ?? 0 ),
				'formatted_size'      => (string) ( $autoload_size['formatted_size'] ?? '' ),
				'large_options'       => $large_autoloads,
				'large_options_count' => count( $large_autoloads ),
				'recommendations'     => $this->autoload_analyzer->recommend_autoload_changes(),
				'error'               => null,
			);
		} catch ( \Throwable $e ) {
			return array(
				'total_size'          => 0,
				'count'               => 0,
				'formatted_size'      => '',
				'large_options'       => array(),
				'large_options_count' => 0,
				'recommendations'     => array(),
				'error'               => $e->getMessage(),
			);
		}
	}

	/**
	 * Collect query metrics.
	 *
	 * @since 1.4.0
	 *
	 * @param float $threshold_ms Slow query threshold.
	 * @param bool  $safe_mode    Whether safe mode is enabled.
	 * @return array
	 */
	private function collect_query_metrics( float $threshold_ms, bool $safe_mode ): array {
		try {
			$summary            = $this->query_monitor->get_query_summary( 7 );
			$monitoring_status  = $this->query_monitor->get_monitoring_status();
			$queries_by_caller  = $this->query_monitor->get_queries_by_caller( 20, 7 );
			$slow_queries       = array();
			$slow_query_count   = 0;
			$slow_queries_skipped = false;

			if ( ! $safe_mode ) {
				$slow_queries     = $this->query_monitor->capture_slow_queries( $threshold_ms );
				$slow_query_count = count( $slow_queries );
			} else {
				$slow_queries_skipped = true;
			}

			return array(
				'threshold_ms'        => $threshold_ms,
				'slow_queries'        => $slow_queries,
				'slow_query_count'    => $slow_query_count,
				'slow_queries_skipped'=> $slow_queries_skipped,
				'summary'             => $summary,
				'by_caller'           => $queries_by_caller,
				'monitoring_status'   => $monitoring_status,
				'savequeries_active'  => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
				'error'               => null,
			);
		} catch ( \Throwable $e ) {
			return array(
				'threshold_ms'         => $threshold_ms,
				'slow_queries'         => array(),
				'slow_query_count'     => 0,
				'slow_queries_skipped' => $safe_mode,
				'summary'              => array(),
				'by_caller'            => array(),
				'monitoring_status'    => array(),
				'savequeries_active'   => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
				'error'                => $e->getMessage(),
			);
		}
	}

	/**
	 * Collect plugin metrics.
	 *
	 * @since 1.4.0
	 *
	 * @param bool $safe_mode Whether safe mode is enabled.
	 * @return array
	 */
	private function collect_plugin_metrics( bool $safe_mode ): array {
		if ( $safe_mode ) {
			return array(
				'skipped'        => true,
				'note'           => 'Plugin profiling skipped in safe mode.',
				'measurements'   => array(),
				'slowest_plugins'=> array(),
				'error'          => null,
			);
		}

		try {
			$analysis = $this->plugin_profiler->measure_plugin_impact();
			return array(
				'skipped'         => false,
				'note'            => (string) ( $analysis['note'] ?? $analysis['message'] ?? '' ),
				'measurements'    => $analysis['measurements'] ?? array(),
				'slowest_plugins' => $this->plugin_profiler->get_slowest_plugins( 10 ),
				'error'           => null,
			);
		} catch ( \Throwable $e ) {
			return array(
				'skipped'         => false,
				'note'            => '',
				'measurements'    => array(),
				'slowest_plugins' => array(),
				'error'           => $e->getMessage(),
			);
		}
	}

	/**
	 * Collect cache metrics.
	 *
	 * @since 1.4.0
	 *
	 * @return array
	 */
	private function collect_cache_metrics(): array {
		try {
			$status          = $this->cache_checker->get_cache_status();
			$recommendations = $this->cache_checker->get_cache_recommendations();

			$opcache_enabled = function_exists( 'opcache_get_status' ) && opcache_get_status( false );

			return array(
				'object_cache_enabled' => ! empty( $status['persistent_cache_available'] ),
				'cache_type'           => $status['cache_type'] ?? 'none',
				'cache_backend'        => $status['cache_backend'] ?? 'none',
				'hit_rate'             => $status['hit_rate'] ?? null,
				'opcache_enabled'      => (bool) $opcache_enabled,
				'page_cache'           => $status['page_cache'] ?? array(),
				'browser_cache'        => $status['browser_cache'] ?? array(),
				'caching_plugins'      => $status['caching_plugins'] ?? array(),
				'recommendations'      => $recommendations,
				'error'                => null,
			);
		} catch ( \Throwable $e ) {
			return array(
				'object_cache_enabled' => false,
				'cache_type'           => 'none',
				'cache_backend'        => 'none',
				'hit_rate'             => null,
				'opcache_enabled'      => false,
				'page_cache'           => array(),
				'browser_cache'        => array(),
				'caching_plugins'      => array(),
				'recommendations'      => array(),
				'error'                => $e->getMessage(),
			);
		}
	}

	/**
	 * Log metrics collection activity.
	 *
	 * @since 1.4.0
	 *
	 * @param array $result Collected result.
	 * @return void
	 */
	private function log_activity( array $result ): void {
		if ( null === $this->activity_logger ) {
			return;
		}

		$this->activity_logger->log_performance_check( 'collect_metrics', $result );
	}
}
