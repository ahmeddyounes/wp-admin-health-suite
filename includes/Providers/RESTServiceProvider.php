<?php
/**
 * REST Service Provider
 *
 * Registers REST API controllers and services.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Application\Media\RunScan;
use WPAdminHealth\Application\Performance\RunHealthCheck;
use WPAdminHealth\Application\Performance\CollectMetrics;
use WPAdminHealth\Application\Performance\GetQueryAnalysis;
use WPAdminHealth\Application\Performance\GetPluginImpact;
use WPAdminHealth\Application\Performance\GetAutoloadAnalysis;
use WPAdminHealth\Application\Performance\UpdateAutoloadOption;
use WPAdminHealth\Application\Performance\GetCacheStatus;
use WPAdminHealth\Application\Performance\GetRecommendations;
use WPAdminHealth\Application\Dashboard\GetHealthScore;
use WPAdminHealth\Application\Database\RunCleanup;
use WPAdminHealth\Application\Database\RunOptimization;
use WPAdminHealth\Performance\CacheChecker;
use WPAdminHealth\REST\DashboardController;
use WPAdminHealth\REST\ActivityController;
use WPAdminHealth\REST\Database\TableAnalysisController;
use WPAdminHealth\REST\Database\OptimizationController;
use WPAdminHealth\REST\Database\CleanupController;
use WPAdminHealth\REST\Performance\PerformanceStatsController;
use WPAdminHealth\REST\Performance\QueryAnalysisController;
use WPAdminHealth\REST\Performance\PluginProfilerController;
use WPAdminHealth\REST\Performance\CacheController;
use WPAdminHealth\REST\Performance\AutoloadController;
use WPAdminHealth\REST\Performance\HeartbeatController;
use WPAdminHealth\REST\Media\MediaScanController;
use WPAdminHealth\REST\Media\MediaAnalysisController;
use WPAdminHealth\REST\Media\MediaAltTextController;
use WPAdminHealth\REST\Media\MediaCleanupController;
use WPAdminHealth\HealthCalculator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class RESTServiceProvider
 *
 * Registers REST API controllers with their dependencies.
 *
 * @since 1.1.0
 */
class RESTServiceProvider extends ServiceProvider {

	/**
	 * Whether this provider should be deferred.
	 *
	 * @var bool
	 */
	protected bool $deferred = true;

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		// Class-string identifiers (primary).
		DashboardController::class,
		ActivityController::class,
		TableAnalysisController::class,
		OptimizationController::class,
		CleanupController::class,
		PerformanceStatsController::class,
		QueryAnalysisController::class,
		PluginProfilerController::class,
		CacheController::class,
		AutoloadController::class,
		HeartbeatController::class,
		MediaScanController::class,
		MediaAnalysisController::class,
		MediaAltTextController::class,
		MediaCleanupController::class,
		// Application layer services.
		RunScan::class,
		RunHealthCheck::class,
		CollectMetrics::class,
		GetQueryAnalysis::class,
		GetPluginImpact::class,
		GetAutoloadAnalysis::class,
		UpdateAutoloadOption::class,
		GetCacheStatus::class,
		GetRecommendations::class,
		GetHealthScore::class,
		RunCleanup::class,
		RunOptimization::class,
		// String aliases (backward compatibility).
		'rest.dashboard_controller',
		'rest.activity_controller',
		'rest.database.table_analysis_controller',
		'rest.database.optimization_controller',
		'rest.database.cleanup_controller',
		'rest.performance.stats_controller',
		'rest.performance.query_analysis_controller',
		'rest.performance.plugin_profiler_controller',
		'rest.performance.cache_controller',
		'rest.performance.autoload_controller',
		'rest.performance.heartbeat_controller',
		'rest.media.scan_controller',
		'rest.media.analysis_controller',
		'rest.media.alt_text_controller',
		'rest.media.cleanup_controller',
		'application.media.run_scan',
		'application.performance.run_health_check',
		'application.performance.collect_metrics',
		'application.database.run_cleanup',
		'application.database.run_optimization',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Dashboard Controller with class-string ID.
		$this->container->bind(
			DashboardController::class,
			function ( $container ) {
				return new DashboardController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( HealthCalculator::class ),
					$container->get( TableCheckerInterface::class ),
					$container->get( GetHealthScore::class )
				);
			}
		);
		$this->container->alias( 'rest.dashboard_controller', DashboardController::class );

		// Register Activity Controller with class-string ID.
		$this->container->bind(
			ActivityController::class,
			function ( $container ) {
				return new ActivityController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( TableCheckerInterface::class )
				);
			}
		);
		$this->container->alias( 'rest.activity_controller', ActivityController::class );

		// Register Database Table Analysis Controller with class-string ID.
		$this->container->bind(
			TableAnalysisController::class,
			function ( $container ) {
				return new TableAnalysisController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class )
				);
			}
		);
		$this->container->alias( 'rest.database.table_analysis_controller', TableAnalysisController::class );

		// Register Database Optimization Controller with class-string ID.
		$this->container->bind(
			OptimizationController::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new OptimizationController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( OptimizerInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'rest.database.optimization_controller', OptimizationController::class );

		// Register Database Cleanup Controller with class-string ID.
		$this->container->bind(
			CleanupController::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new CleanupController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'rest.database.cleanup_controller', CleanupController::class );

		// Register Performance Stats Controller with class-string ID.
		$this->container->bind(
			PerformanceStatsController::class,
			function ( $container ) {
				return new PerformanceStatsController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( RunHealthCheck::class ),
					$container->get( GetRecommendations::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.stats_controller', PerformanceStatsController::class );

		// Register Performance Query Analysis Controller with class-string ID.
		$this->container->bind(
			QueryAnalysisController::class,
			function ( $container ) {
				return new QueryAnalysisController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( GetQueryAnalysis::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.query_analysis_controller', QueryAnalysisController::class );

		// Register Performance Plugin Profiler Controller with class-string ID.
		$this->container->bind(
			PluginProfilerController::class,
			function ( $container ) {
				return new PluginProfilerController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( GetPluginImpact::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.plugin_profiler_controller', PluginProfilerController::class );

		// Register Performance Cache Controller with class-string ID.
		$this->container->bind(
			CacheController::class,
			function ( $container ) {
				return new CacheController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( GetCacheStatus::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.cache_controller', CacheController::class );

		// Register Performance Autoload Controller with class-string ID.
		$this->container->bind(
			AutoloadController::class,
			function ( $container ) {
				return new AutoloadController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class ),
					$container->get( GetAutoloadAnalysis::class ),
					$container->get( UpdateAutoloadOption::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.autoload_controller', AutoloadController::class );

		// Register Performance Heartbeat Controller with class-string ID.
		$this->container->bind(
			HeartbeatController::class,
			function ( $container ) {
				return new HeartbeatController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'rest.performance.heartbeat_controller', HeartbeatController::class );

		// Register RunScan Application Service.
		$this->container->bind(
			RunScan::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new RunScan(
					$container->get( SettingsInterface::class ),
					$container->get( ScannerInterface::class ),
					$container->get( DuplicateDetectorInterface::class ),
					$container->get( LargeFilesInterface::class ),
					$container->get( AltTextCheckerInterface::class ),
					$container->get( ReferenceFinderInterface::class ),
					$container->get( ExclusionsInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'application.media.run_scan', RunScan::class );

		// Register RunHealthCheck Application Service.
		$this->container->bind(
			RunHealthCheck::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new RunHealthCheck(
					$container->get( SettingsInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class ),
					$container->get( QueryMonitorInterface::class ),
					$container->get( PluginProfilerInterface::class ),
					$container->get( CacheChecker::class ),
					$container->get( ConnectionInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'application.performance.run_health_check', RunHealthCheck::class );

		// Register CollectMetrics Application Service.
		$this->container->bind(
			CollectMetrics::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new CollectMetrics(
					$container->get( SettingsInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class ),
					$container->get( QueryMonitorInterface::class ),
					$container->get( PluginProfilerInterface::class ),
					$container->get( CacheChecker::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'application.performance.collect_metrics', CollectMetrics::class );

		// Register RunCleanup Application Service.
		$this->container->bind(
			RunCleanup::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new RunCleanup(
					$container->get( SettingsInterface::class ),
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'application.database.run_cleanup', RunCleanup::class );

		// Register GetQueryAnalysis Application Service.
		$this->container->bind(
			GetQueryAnalysis::class,
			function ( $container ) {
				return new GetQueryAnalysis(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( QueryMonitorInterface::class )
				);
			}
		);

		// Register GetPluginImpact Application Service.
		$this->container->bind(
			GetPluginImpact::class,
			function ( $container ) {
				return new GetPluginImpact(
					$container->get( SettingsInterface::class ),
					$container->get( PluginProfilerInterface::class )
				);
			}
		);

		// Register GetAutoloadAnalysis Application Service.
		$this->container->bind(
			GetAutoloadAnalysis::class,
			function ( $container ) {
				return new GetAutoloadAnalysis(
					$container->get( ConnectionInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class )
				);
			}
		);

		// Register UpdateAutoloadOption Application Service.
		$this->container->bind(
			UpdateAutoloadOption::class,
			function ( $container ) {
				return new UpdateAutoloadOption( $container->get( ConnectionInterface::class ) );
			}
		);

		// Register GetCacheStatus Application Service.
		$this->container->bind(
			GetCacheStatus::class,
			function ( $container ) {
				return new GetCacheStatus();
			}
		);

		// Register GetRecommendations Application Service.
		$this->container->bind(
			GetRecommendations::class,
			function ( $container ) {
				return new GetRecommendations( $container->get( RunHealthCheck::class ) );
			}
		);

		// Register GetHealthScore Application Service.
		$this->container->bind(
			GetHealthScore::class,
			function ( $container ) {
				return new GetHealthScore( $container->get( HealthCalculator::class ) );
			}
		);

		// Register RunOptimization Application Service.
		$this->container->bind(
			RunOptimization::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new RunOptimization(
					$container->get( SettingsInterface::class ),
					$container->get( OptimizerInterface::class ),
					$container->get( ConnectionInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'application.database.run_optimization', RunOptimization::class );

		// Register Media Scan Controller with class-string ID.
		$this->container->bind(
			MediaScanController::class,
			function ( $container ) {
				return new MediaScanController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( RunScan::class )
				);
			}
		);
		$this->container->alias( 'rest.media.scan_controller', MediaScanController::class );

		// Register Media Analysis Controller with class-string ID.
		$this->container->bind(
			MediaAnalysisController::class,
			function ( $container ) {
				return new MediaAnalysisController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( ScannerInterface::class ),
					$container->get( DuplicateDetectorInterface::class ),
					$container->get( LargeFilesInterface::class )
				);
			}
		);
		$this->container->alias( 'rest.media.analysis_controller', MediaAnalysisController::class );

		// Register Media Alt Text Controller with class-string ID.
		$this->container->bind(
			MediaAltTextController::class,
			function ( $container ) {
				return new MediaAltTextController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AltTextCheckerInterface::class )
				);
			}
		);
		$this->container->alias( 'rest.media.alt_text_controller', MediaAltTextController::class );

		// Register Media Cleanup Controller with class-string ID.
		$this->container->bind(
			MediaCleanupController::class,
			function ( $container ) {
				// Try to get ActivityLoggerInterface, but don't fail if not available.
				$activity_logger = null;
				try {
					if ( $container->has( ActivityLoggerInterface::class ) ) {
						$activity_logger = $container->get( ActivityLoggerInterface::class );
					}
				} catch ( \Exception $e ) {
					// Activity logger is optional.
				}

				return new MediaCleanupController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( SafeDeleteInterface::class ),
					$container->get( ExclusionsInterface::class ),
					$activity_logger
				);
			}
		);
		$this->container->alias( 'rest.media.cleanup_controller', MediaCleanupController::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.1.0
	 * @since 1.4.0 Updated to use class-string identifiers.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$controllers = array(
			// General controllers.
			DashboardController::class,
			ActivityController::class,
			// Database specialized controllers.
			TableAnalysisController::class,
			OptimizationController::class,
			CleanupController::class,
			// Performance specialized controllers.
			PerformanceStatsController::class,
			QueryAnalysisController::class,
			PluginProfilerController::class,
			CacheController::class,
			AutoloadController::class,
			HeartbeatController::class,
			// Media specialized controllers.
			MediaScanController::class,
			MediaAnalysisController::class,
			MediaAltTextController::class,
			MediaCleanupController::class,
		);

		foreach ( $controllers as $controller_class ) {
			try {
				$controller = $this->container->get( $controller_class );

				if ( $controller && method_exists( $controller, 'register_routes' ) ) {
					$controller->register_routes();
				}
			} catch ( \Exception $e ) {
				// Log error but don't break other controllers.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'WP Admin Health Suite: Failed to register %s: %s', $controller_class, $e->getMessage() ) );
				}
			}
		}
	}
}
