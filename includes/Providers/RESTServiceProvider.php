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
use WPAdminHealth\REST\DatabaseController;
use WPAdminHealth\REST\MediaController;
use WPAdminHealth\REST\DashboardController;
use WPAdminHealth\REST\PerformanceController;
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
		'rest.database_controller',
		'rest.media_controller',
		'rest.dashboard_controller',
		'rest.performance_controller',
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
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Database Controller with all dependencies.
		$this->container->bind(
			'rest.database_controller',
			function ( $container ) {
				if ( ! class_exists( DatabaseController::class ) ) {
					return null;
				}

				return new DatabaseController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$container->get( OptimizerInterface::class )
				);
			}
		);

		// Register Media Controller with all dependencies.
		$this->container->bind(
			'rest.media_controller',
			function ( $container ) {
				if ( ! class_exists( MediaController::class ) ) {
					return null;
				}

				return new MediaController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( ScannerInterface::class ),
					$container->get( DuplicateDetectorInterface::class ),
					$container->get( LargeFilesInterface::class ),
					$container->get( AltTextCheckerInterface::class ),
					$container->get( ReferenceFinderInterface::class ),
					$container->get( SafeDeleteInterface::class ),
					$container->get( ExclusionsInterface::class )
				);
			}
		);

		// Register Dashboard Controller.
		$this->container->bind(
			'rest.dashboard_controller',
			function ( $container ) {
				if ( ! class_exists( DashboardController::class ) ) {
					return null;
				}

				return new DashboardController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( HealthCalculator::class )
				);
			}
		);

		// Register Performance Controller with all dependencies.
		$this->container->bind(
			'rest.performance_controller',
			function ( $container ) {
				if ( ! class_exists( PerformanceController::class ) ) {
					return null;
				}

				return new PerformanceController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class ),
					$container->get( QueryMonitorInterface::class ),
					$container->get( PluginProfilerInterface::class )
				);
			}
		);

		// Register Activity Controller.
		$this->container->bind(
			'rest.activity_controller',
			function ( $container ) {
				if ( ! class_exists( ActivityController::class ) ) {
					return null;
				}

				return new ActivityController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);

		// Register Database Table Analysis Controller.
		$this->container->bind(
			'rest.database.table_analysis_controller',
			function ( $container ) {
				if ( ! class_exists( TableAnalysisController::class ) ) {
					return null;
				}

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

		// Register Database Optimization Controller.
		$this->container->bind(
			'rest.database.optimization_controller',
			function ( $container ) {
				if ( ! class_exists( OptimizationController::class ) ) {
					return null;
				}

				return new OptimizationController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( OptimizerInterface::class )
				);
			}
		);

		// Register Database Cleanup Controller.
		$this->container->bind(
			'rest.database.cleanup_controller',
			function ( $container ) {
				if ( ! class_exists( CleanupController::class ) ) {
					return null;
				}

				return new CleanupController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class )
				);
			}
		);

		// Register Performance Stats Controller.
		$this->container->bind(
			'rest.performance.stats_controller',
			function ( $container ) {
				if ( ! class_exists( PerformanceStatsController::class ) ) {
					return null;
				}

				return new PerformanceStatsController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);

		// Register Performance Query Analysis Controller.
		$this->container->bind(
			'rest.performance.query_analysis_controller',
			function ( $container ) {
				if ( ! class_exists( QueryAnalysisController::class ) ) {
					return null;
				}

				return new QueryAnalysisController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( QueryMonitorInterface::class )
				);
			}
		);

		// Register Performance Plugin Profiler Controller.
		$this->container->bind(
			'rest.performance.plugin_profiler_controller',
			function ( $container ) {
				if ( ! class_exists( PluginProfilerController::class ) ) {
					return null;
				}

				return new PluginProfilerController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( PluginProfilerInterface::class )
				);
			}
		);

		// Register Performance Cache Controller.
		$this->container->bind(
			'rest.performance.cache_controller',
			function ( $container ) {
				if ( ! class_exists( CacheController::class ) ) {
					return null;
				}

				return new CacheController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);

		// Register Performance Autoload Controller.
		$this->container->bind(
			'rest.performance.autoload_controller',
			function ( $container ) {
				if ( ! class_exists( AutoloadController::class ) ) {
					return null;
				}

				return new AutoloadController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AutoloadAnalyzerInterface::class )
				);
			}
		);

		// Register Performance Heartbeat Controller.
		$this->container->bind(
			'rest.performance.heartbeat_controller',
			function ( $container ) {
				if ( ! class_exists( HeartbeatController::class ) ) {
					return null;
				}

				return new HeartbeatController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);

		// Register Media Scan Controller.
		$this->container->bind(
			'rest.media.scan_controller',
			function ( $container ) {
				if ( ! class_exists( MediaScanController::class ) ) {
					return null;
				}

				return new MediaScanController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( ScannerInterface::class )
				);
			}
		);

		// Register Media Analysis Controller.
		$this->container->bind(
			'rest.media.analysis_controller',
			function ( $container ) {
				if ( ! class_exists( MediaAnalysisController::class ) ) {
					return null;
				}

				return new MediaAnalysisController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( ScannerInterface::class ),
					$container->get( DuplicateDetectorInterface::class ),
					$container->get( LargeFilesInterface::class )
				);
			}
		);

		// Register Media Alt Text Controller.
		$this->container->bind(
			'rest.media.alt_text_controller',
			function ( $container ) {
				if ( ! class_exists( MediaAltTextController::class ) ) {
					return null;
				}

				return new MediaAltTextController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( AltTextCheckerInterface::class )
				);
			}
		);

		// Register Media Cleanup Controller.
		$this->container->bind(
			'rest.media.cleanup_controller',
			function ( $container ) {
				if ( ! class_exists( MediaCleanupController::class ) ) {
					return null;
				}

				return new MediaCleanupController(
					$container->get( SettingsInterface::class ),
					$container->get( ConnectionInterface::class ),
					$container->get( SafeDeleteInterface::class ),
					$container->get( ExclusionsInterface::class )
				);
			}
		);
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
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$controllers = array(
			'rest.database_controller',
			'rest.media_controller',
			'rest.dashboard_controller',
			'rest.performance_controller',
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
		);

		foreach ( $controllers as $controller_id ) {
			try {
				$controller = $this->container->get( $controller_id );

				if ( $controller && method_exists( $controller, 'register_routes' ) ) {
					$controller->register_routes();
				}
			} catch ( \Exception $e ) {
				// Log error but don't break other controllers.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'WP Admin Health Suite: Failed to register %s: %s', $controller_id, $e->getMessage() ) );
				}
			}
		}
	}
}
