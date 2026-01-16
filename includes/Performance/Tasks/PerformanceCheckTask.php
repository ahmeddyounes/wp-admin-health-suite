<?php
/**
 * Performance Check Task
 *
 * Scheduled task for performance monitoring and analysis.
 *
 * @package WPAdminHealth\Performance\Tasks
 */

namespace WPAdminHealth\Performance\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class PerformanceCheckTask
 *
 * Performs scheduled performance analysis.
 *
 * @since 1.2.0
 */
class PerformanceCheckTask extends AbstractScheduledTask {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'performance_check';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Performance Check';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Analyze autoload options, slow queries, and plugin performance.';

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'daily';

	/**
	 * Enabled option key.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = 'enable_scheduled_performance_check';

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
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Added ConnectionInterface parameter.
	 *
	 * @param AutoloadAnalyzerInterface $autoload_analyzer Autoload analyzer.
	 * @param QueryMonitorInterface     $query_monitor     Query monitor.
	 * @param PluginProfilerInterface   $plugin_profiler   Plugin profiler.
	 * @param ConnectionInterface       $connection        Database connection.
	 */
	public function __construct(
		AutoloadAnalyzerInterface $autoload_analyzer,
		QueryMonitorInterface $query_monitor,
		PluginProfilerInterface $plugin_profiler,
		ConnectionInterface $connection
	) {
		$this->autoload_analyzer = $autoload_analyzer;
		$this->query_monitor     = $query_monitor;
		$this->plugin_profiler   = $plugin_profiler;
		$this->connection        = $connection;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( array $options = array() ): array {
		$this->log( 'Starting performance check task' );

		$settings      = get_option( 'wpha_settings', array() );
		$check_results = array(
			'autoload'     => array(),
			'queries'      => array(),
			'plugins'      => array(),
			'total_issues' => 0,
			'score'        => 100,
		);

		// Analyze autoload options.
		if ( ! empty( $settings['check_autoload'] ) || ! empty( $options['check_autoload'] ) ) {
			$autoload_size   = $this->autoload_analyzer->get_autoload_size();
			$large_autoloads = $this->autoload_analyzer->find_large_autoloads();
			$autoload_analysis = array(
				'total_size'          => $autoload_size['total_size'] ?? 0,
				'count'               => $autoload_size['count'] ?? 0,
				'large_options'       => $large_autoloads,
				'large_options_count' => count( $large_autoloads ),
			);
			$check_results['autoload'] = $autoload_analysis;

			// Count issues.
			$autoload_issues = $autoload_analysis['large_options_count'];
			$check_results['total_issues'] += $autoload_issues;

			// Deduct from score based on autoload size.
			$total_autoload_size = $autoload_analysis['total_size'];
			if ( $total_autoload_size > 1000000 ) { // > 1MB.
				$check_results['score'] -= 20;
			} elseif ( $total_autoload_size > 500000 ) { // > 500KB.
				$check_results['score'] -= 10;
			}

			$this->log( sprintf( 'Autoload analysis: %d large options found', $autoload_issues ) );
		}

		// Analyze slow queries.
		if ( ! empty( $settings['monitor_queries'] ) || ! empty( $options['monitor_queries'] ) ) {
			// Get threshold from settings, default 100ms.
			$threshold_ms = (float) ( $settings['slow_query_threshold_ms'] ?? 100 );
			$query_analysis = $this->query_monitor->capture_slow_queries( $threshold_ms );
			$check_results['queries'] = $query_analysis;

			// Count slow queries as issues.
			$slow_query_count = count( $query_analysis );
			$check_results['total_issues'] += $slow_query_count;

			// Deduct from score based on slow queries.
			if ( $slow_query_count > 20 ) {
				$check_results['score'] -= 30;
			} elseif ( $slow_query_count > 10 ) {
				$check_results['score'] -= 20;
			} elseif ( $slow_query_count > 5 ) {
				$check_results['score'] -= 10;
			}

			$this->log( sprintf( 'Query analysis: %d slow queries found', $slow_query_count ) );
		}

		// Profile plugins.
		if ( ! empty( $settings['profile_plugins'] ) || ! empty( $options['profile_plugins'] ) ) {
			$plugin_analysis = $this->plugin_profiler->measure_plugin_impact();
			$check_results['plugins'] = $plugin_analysis;

			// Count slow plugins as issues (load_time > 100ms).
			$slow_plugins = array_filter(
				$plugin_analysis,
				/**
				 * Filter callback for slow plugins.
				 *
				 * @param array{load_time: float, memory: int, queries: int, impact_score: float} $plugin Plugin data.
				 * @return bool True if plugin is slow.
				 */
				static function ( array $plugin ): bool {
					return $plugin['load_time'] > 0.1;
				}
			);
			$slow_plugin_count = count( $slow_plugins );
			$check_results['total_issues'] += $slow_plugin_count;

			// Deduct from score based on slow plugins.
			if ( $slow_plugin_count > 5 ) {
				$check_results['score'] -= 20;
			} elseif ( $slow_plugin_count > 2 ) {
				$check_results['score'] -= 10;
			}

			$this->log( sprintf( 'Plugin analysis: %d slow plugins found', $slow_plugin_count ) );
		}

		// Ensure score doesn't go below 0.
		$check_results['score'] = max( 0, $check_results['score'] );

		// Store performance results.
		$this->store_performance_results( $check_results );

		$this->log(
			sprintf(
				'Performance check completed. Score: %d, Issues: %d',
				$check_results['score'],
				$check_results['total_issues']
			)
		);

		return $this->create_result(
			$check_results['total_issues'],
			0, // No bytes freed for performance checks.
			true
		);
	}

	/**
	 * Store performance check results.
	 *
	 * @param array $results Check results.
	 * @return void
	 */
	private function store_performance_results( array $results ): void {
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
						'autoload_size'      => $results['autoload']['total_size'] ?? 0,
						'slow_queries_count' => count( $results['queries'] ),
						'slow_plugins_count' => count(
							array_filter(
								$results['plugins'],
								/**
								 * Filter callback for slow plugins.
								 *
								 * @param array{load_time: float, memory: int, queries: int, impact_score: float} $p Plugin data.
								 * @return bool True if plugin is slow.
								 */
								static function ( array $p ): bool {
									return $p['load_time'] > 0.1;
								}
							)
						),
					)
				),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		/**
		 * Fires when performance check results are stored.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_performance_check_completed
		 *
		 * @param array $results The check results.
		 */
		do_action( 'wpha_performance_check_completed', $results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'check_autoload'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Analyze autoload options', 'wp-admin-health-suite' ),
			),
			'monitor_queries'  => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Monitor slow queries', 'wp-admin-health-suite' ),
			),
			'profile_plugins'  => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Profile plugin performance', 'wp-admin-health-suite' ),
			),
		);
	}
}
