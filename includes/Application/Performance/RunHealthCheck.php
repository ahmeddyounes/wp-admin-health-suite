<?php
/**
 * Run Health Check Use Case
 *
 * Application service for orchestrating performance health check operations.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Performance\CacheChecker;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class RunHealthCheck
 *
 * Orchestrates performance health check operations including cache analysis,
 * plugin profiling, autoload analysis, and query monitoring.
 *
 * This use-case class serves as the application layer between REST controllers
 * and domain services, providing a clean interface for health check operations.
 *
 * @since 1.4.0
 */
class RunHealthCheck {

	/**
	 * Valid check types.
	 *
	 * @var array
	 */
	const VALID_TYPES = array( 'autoload', 'queries', 'plugins', 'cache', 'full' );

	/**
	 * Threshold for large autoloaded options (in bytes).
	 *
	 * @var int
	 */
	const LARGE_AUTOLOAD_THRESHOLD = 500000; // 500KB

	/**
	 * Threshold for critical autoloaded options (in bytes).
	 *
	 * @var int
	 */
	const CRITICAL_AUTOLOAD_THRESHOLD = 1000000; // 1MB

	/**
	 * Threshold for slow plugin load time (in seconds).
	 *
	 * @var float
	 */
	const SLOW_PLUGIN_THRESHOLD = 0.1; // 100ms

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Autoload analyzer instance.
	 *
	 * @var AutoloadAnalyzerInterface
	 */
	private AutoloadAnalyzerInterface $autoload_analyzer;

	/**
	 * Query monitor instance.
	 *
	 * @var QueryMonitorInterface
	 */
	private QueryMonitorInterface $query_monitor;

	/**
	 * Plugin profiler instance.
	 *
	 * @var PluginProfilerInterface
	 */
	private PluginProfilerInterface $plugin_profiler;

	/**
	 * Cache checker instance.
	 *
	 * @var CacheChecker
	 */
	private CacheChecker $cache_checker;

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Activity logger instance.
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
	 * @param AutoloadAnalyzerInterface    $autoload_analyzer Autoload analyzer instance.
	 * @param QueryMonitorInterface        $query_monitor     Query monitor instance.
	 * @param PluginProfilerInterface      $plugin_profiler   Plugin profiler instance.
	 * @param CacheChecker                 $cache_checker     Cache checker instance.
	 * @param ConnectionInterface          $connection        Database connection instance.
	 * @param ActivityLoggerInterface|null $activity_logger   Optional activity logger instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		AutoloadAnalyzerInterface $autoload_analyzer,
		QueryMonitorInterface $query_monitor,
		PluginProfilerInterface $plugin_profiler,
		CacheChecker $cache_checker,
		ConnectionInterface $connection,
		?ActivityLoggerInterface $activity_logger = null
	) {
		$this->settings          = $settings;
		$this->autoload_analyzer = $autoload_analyzer;
		$this->query_monitor     = $query_monitor;
		$this->plugin_profiler   = $plugin_profiler;
		$this->cache_checker     = $cache_checker;
		$this->connection        = $connection;
		$this->activity_logger   = $activity_logger;
	}

	/**
	 * Execute the health check operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Health check options.
	 *                       - type: string - One of 'autoload', 'queries', 'plugins', 'cache', 'full'.
	 *                       - check_autoload: bool - Whether to check autoload options.
	 *                       - monitor_queries: bool - Whether to monitor queries.
	 *                       - profile_plugins: bool - Whether to profile plugins.
	 *                       - check_cache: bool - Whether to check cache status.
	 *                       - threshold_ms: float - Slow query threshold in milliseconds.
	 * @return array Result of the health check operation.
	 */
	public function execute( array $options = array() ): array {
		$type = $options['type'] ?? 'full';

		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid check type specified.',
				'code'    => 'invalid_type',
			);
		}

		// Execute check based on type.
		switch ( $type ) {
			case 'autoload':
				$results = $this->check_autoload( $options );
				break;

			case 'queries':
				$results = $this->check_queries( $options );
				break;

			case 'plugins':
				$results = $this->check_plugins( $options );
				break;

			case 'cache':
				$results = $this->check_cache( $options );
				break;

			case 'full':
			default:
				$results = $this->run_full_check( $options );
				break;
		}

		// Add metadata.
		$results['type']      = $type;
		$results['timestamp'] = current_time( 'mysql' );

		// Log activity.
		$this->log_health_check_activity( $type, $results );

		return $results;
	}

	/**
	 * Run a full performance health check.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Check options.
	 * @return array Check results with score and issues.
	 */
	public function run_full_check( array $options = array() ): array {
		$all_settings = $this->settings->get_settings();

		$check_results = array(
			'autoload'     => array(),
			'queries'      => array(),
			'plugins'      => array(),
			'cache'        => array(),
			'total_issues' => 0,
			'score'        => 100,
		);

		// Analyze autoload options.
		$check_autoload = $options['check_autoload'] ?? ( $all_settings['check_autoload'] ?? true );
		if ( $check_autoload ) {
			$check_results['autoload'] = $this->check_autoload( $options );
			$check_results['total_issues'] += $check_results['autoload']['issue_count'] ?? 0;
			$check_results['score'] -= $check_results['autoload']['score_deduction'] ?? 0;
		}

		// Analyze slow queries.
		$monitor_queries = $options['monitor_queries'] ?? ( $all_settings['monitor_queries'] ?? true );
		if ( $monitor_queries ) {
			$check_results['queries'] = $this->check_queries( $options );
			$check_results['total_issues'] += $check_results['queries']['issue_count'] ?? 0;
			$check_results['score'] -= $check_results['queries']['score_deduction'] ?? 0;
		}

		// Profile plugins.
		$profile_plugins = $options['profile_plugins'] ?? ( $all_settings['profile_plugins'] ?? false );
		if ( $profile_plugins ) {
			$check_results['plugins'] = $this->check_plugins( $options );
			$check_results['total_issues'] += $check_results['plugins']['issue_count'] ?? 0;
			$check_results['score'] -= $check_results['plugins']['score_deduction'] ?? 0;
		}

		// Check cache status.
		$check_cache = $options['check_cache'] ?? ( $all_settings['check_cache'] ?? true );
		if ( $check_cache ) {
			$check_results['cache'] = $this->check_cache( $options );
			$check_results['total_issues'] += $check_results['cache']['issue_count'] ?? 0;
			$check_results['score'] -= $check_results['cache']['score_deduction'] ?? 0;
		}

		// Ensure score doesn't go below 0.
		$check_results['score'] = max( 0, $check_results['score'] );

		// Determine grade.
		$check_results['grade'] = $this->calculate_grade( $check_results['score'] );

		// Store results in scan history.
		$this->store_check_results( $check_results );

		return $check_results;
	}

	/**
	 * Check autoload options for performance issues.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Check options.
	 * @return array Autoload analysis results.
	 */
	public function check_autoload( array $options = array() ): array {
		$autoload_size   = $this->autoload_analyzer->get_autoload_size();
		$large_autoloads = $this->autoload_analyzer->find_large_autoloads();

		$total_size      = $autoload_size['total_size'] ?? 0;
		$count           = $autoload_size['count'] ?? 0;
		$issue_count     = count( $large_autoloads );
		$score_deduction = 0;

		// Calculate score deduction based on autoload size.
		if ( $total_size > self::CRITICAL_AUTOLOAD_THRESHOLD ) {
			$score_deduction = 20;
		} elseif ( $total_size > self::LARGE_AUTOLOAD_THRESHOLD ) {
			$score_deduction = 10;
		}

		return array(
			'total_size'          => $total_size,
			'total_size_kb'       => round( $total_size / 1024, 2 ),
			'total_size_mb'       => round( $total_size / ( 1024 * 1024 ), 2 ),
			'count'               => $count,
			'large_options'       => $large_autoloads,
			'large_options_count' => $issue_count,
			'issue_count'         => $issue_count,
			'score_deduction'     => $score_deduction,
			'recommendations'     => $this->autoload_analyzer->recommend_autoload_changes(),
		);
	}

	/**
	 * Check database queries for performance issues.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Check options.
	 * @return array Query analysis results.
	 */
	public function check_queries( array $options = array() ): array {
		$all_settings = $this->settings->get_settings();

		// Get threshold from options or settings, default 100ms.
		$threshold_ms = (float) ( $options['threshold_ms'] ?? $all_settings['slow_query_threshold_ms'] ?? 100 );

		$slow_queries      = $this->query_monitor->capture_slow_queries( $threshold_ms );
		$monitoring_status = $this->query_monitor->get_monitoring_status();
		$query_summary     = $this->query_monitor->get_query_summary( 7 );

		$slow_query_count = count( $slow_queries );
		$score_deduction  = 0;

		// Calculate score deduction based on slow queries.
		if ( $slow_query_count > 20 ) {
			$score_deduction = 30;
		} elseif ( $slow_query_count > 10 ) {
			$score_deduction = 20;
		} elseif ( $slow_query_count > 5 ) {
			$score_deduction = 10;
		}

		return array(
			'slow_queries'       => $slow_queries,
			'slow_query_count'   => $slow_query_count,
			'threshold_ms'       => $threshold_ms,
			'monitoring_status'  => $monitoring_status,
			'summary'            => $query_summary,
			'issue_count'        => $slow_query_count,
			'score_deduction'    => $score_deduction,
			'savequeries_active' => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		);
	}

	/**
	 * Check plugin performance.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Check options.
	 * @return array Plugin profiling results.
	 */
	public function check_plugins( array $options = array() ): array {
		$plugin_analysis = $this->plugin_profiler->measure_plugin_impact();

		$measurements = array();
		$note         = '';

		if ( isset( $plugin_analysis['status'] ) && 'success' === $plugin_analysis['status'] ) {
			$measurements = $plugin_analysis['measurements'] ?? array();
			$note         = $plugin_analysis['note'] ?? '';
		} elseif ( isset( $plugin_analysis['message'] ) ) {
			$note = $plugin_analysis['message'];
		}

		// Count slow plugins (load_time > 100ms).
		$slow_plugins = array_filter(
			$measurements,
			/**
			 * Filter callback for slow plugins.
			 *
			 * @param array $plugin Plugin data.
			 * @return bool True if plugin is slow.
			 */
			static function ( array $plugin ): bool {
				return ( $plugin['time'] ?? 0 ) > self::SLOW_PLUGIN_THRESHOLD;
			}
		);

		$slow_plugin_count = count( $slow_plugins );
		$score_deduction   = 0;

		// Calculate score deduction based on slow plugins.
		if ( $slow_plugin_count > 5 ) {
			$score_deduction = 20;
		} elseif ( $slow_plugin_count > 2 ) {
			$score_deduction = 10;
		}

		// Get slowest plugins.
		$slowest_plugins = $this->plugin_profiler->get_slowest_plugins( 10 );

		return array(
			'measurements'      => $measurements,
			'slow_plugins'      => $slow_plugins,
			'slow_plugin_count' => $slow_plugin_count,
			'slowest_plugins'   => $slowest_plugins,
			'issue_count'       => $slow_plugin_count,
			'score_deduction'   => $score_deduction,
			'note'              => $note,
		);
	}

	/**
	 * Check cache status.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Check options.
	 * @return array Cache status results.
	 */
	public function check_cache( array $options = array() ): array {
		$cache_status        = $this->cache_checker->get_cache_status();
		$cache_recommendations = $this->cache_checker->get_cache_recommendations();

		$issue_count     = 0;
		$score_deduction = 0;

		// Deduct points if no object cache.
		if ( empty( $cache_status['persistent_cache_available'] ) ) {
			$issue_count    += 1;
			$score_deduction = 15;
		}

		// Check hit rate if available.
		$hit_rate = $cache_status['hit_rate'] ?? null;
		if ( null !== $hit_rate && $hit_rate < 70 ) {
			$issue_count    += 1;
			$score_deduction += 5;
		}

		// Check OPcache.
		$opcache_enabled = function_exists( 'opcache_get_status' ) && opcache_get_status( false );
		if ( ! $opcache_enabled ) {
			$issue_count    += 1;
			$score_deduction += 5;
		}

		return array(
			'object_cache_enabled' => ! empty( $cache_status['persistent_cache_available'] ),
			'cache_type'           => $cache_status['cache_type'] ?? 'none',
			'cache_backend'        => $cache_status['cache_backend'] ?? 'none',
			'hit_rate'             => $hit_rate,
			'opcache_enabled'      => (bool) $opcache_enabled,
			'page_cache'           => $cache_status['page_cache'] ?? array(),
			'browser_cache'        => $cache_status['browser_cache'] ?? array(),
			'caching_plugins'      => $cache_status['caching_plugins'] ?? array(),
			'recommendations'      => $cache_recommendations,
			'issue_count'          => $issue_count,
			'score_deduction'      => $score_deduction,
		);
	}

	/**
	 * Get performance score without running full check.
	 *
	 * Returns a quick performance score based on key metrics.
	 *
	 * @since 1.4.0
	 *
	 * @return array Score data with grade.
	 */
	public function get_quick_score(): array {
		$score = 100;

		// Check plugin count.
		$plugin_count = count( get_option( 'active_plugins', array() ) );
		if ( $plugin_count > 30 ) {
			$score -= 20;
		} elseif ( $plugin_count > 20 ) {
			$score -= 10;
		} elseif ( $plugin_count > 10 ) {
			$score -= 5;
		}

		// Check autoload size.
		$autoload_size = $this->autoload_analyzer->get_autoload_size();
		$total_size    = $autoload_size['total_size'] ?? 0;
		$autoload_mb   = $total_size / 1024 / 1024;
		if ( $autoload_mb > 1 ) {
			$score -= 15;
		} elseif ( $autoload_mb > 0.5 ) {
			$score -= 10;
		}

		// Check object cache.
		if ( ! wp_using_ext_object_cache() ) {
			$score -= 15;
		}

		// Check query count.
		$db_query_count = $this->connection->get_num_queries();
		if ( $db_query_count > 100 ) {
			$score -= 10;
		} elseif ( $db_query_count > 50 ) {
			$score -= 5;
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'score'         => $score,
			'grade'         => $this->calculate_grade( $score ),
			'plugin_count'  => $plugin_count,
			'autoload_size' => $total_size,
			'query_count'   => $db_query_count,
			'object_cache'  => wp_using_ext_object_cache(),
		);
	}

	/**
	 * Calculate grade from score.
	 *
	 * @since 1.4.0
	 *
	 * @param int $score Performance score (0-100).
	 * @return string Grade letter (A-F).
	 */
	private function calculate_grade( int $score ): string {
		if ( $score >= 90 ) {
			return 'A';
		} elseif ( $score >= 80 ) {
			return 'B';
		} elseif ( $score >= 70 ) {
			return 'C';
		} elseif ( $score >= 60 ) {
			return 'D';
		}
		return 'F';
	}

	/**
	 * Store performance check results.
	 *
	 * @since 1.4.0
	 *
	 * @param array $results Check results.
	 * @return void
	 */
	private function store_check_results( array $results ): void {
		$table = $this->connection->get_prefix() . 'wpha_scan_history';

		$this->connection->insert(
			$table,
			array(
				'scan_type'     => 'performance',
				'items_found'   => $results['total_issues'],
				'items_cleaned' => 0,
				'bytes_freed'   => 0,
				'metadata'      => wp_json_encode(
					array(
						'score'              => $results['score'],
						'grade'              => $results['grade'] ?? '',
						'autoload_size'      => $results['autoload']['total_size'] ?? 0,
						'slow_queries_count' => $results['queries']['slow_query_count'] ?? 0,
						'slow_plugins_count' => $results['plugins']['slow_plugin_count'] ?? 0,
						'object_cache'       => $results['cache']['object_cache_enabled'] ?? false,
					)
				),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		/**
		 * Fires when performance check results are stored.
		 *
		 * @since 1.4.0
		 *
		 * @hook wpha_performance_check_completed
		 *
		 * @param array $results The check results.
		 */
		do_action( 'wpha_performance_check_completed', $results );
	}

	/**
	 * Log health check activity.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type   Check type.
	 * @param array  $result Check result.
	 * @return void
	 */
	private function log_health_check_activity( string $type, array $result ): void {
		if ( null === $this->activity_logger ) {
			return;
		}

		$this->activity_logger->log(
			'performance_check',
			array(
				'type'         => $type,
				'score'        => $result['score'] ?? null,
				'total_issues' => $result['total_issues'] ?? 0,
			)
		);
	}
}
