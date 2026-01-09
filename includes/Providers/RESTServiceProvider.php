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

				$settings = $container->get( SettingsInterface::class );

				return $this->create_controller( ActivityController::class, $settings );
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

	/**
	 * Create a controller with dependency injection support.
	 *
	 * @since 1.1.0
	 *
	 * @param string $class Controller class name.
	 * @param mixed  ...$dependencies Dependencies to inject.
	 * @return object|null Controller instance or null.
	 */
	private function create_controller( string $class, ...$dependencies ) {
		if ( ! class_exists( $class ) ) {
			return null;
		}

		$reflection  = new \ReflectionClass( $class );
		$constructor = $reflection->getConstructor();

		// If controller has no constructor or no parameters, create without injection.
		if ( ! $constructor || 0 === $constructor->getNumberOfParameters() ) {
			return new $class();
		}

		// Filter out null dependencies.
		$dependencies = array_filter( $dependencies, fn( $dep ) => null !== $dep );

		// Try to create with dependencies.
		try {
			return $reflection->newInstanceArgs( $dependencies );
		} catch ( \ReflectionException $e ) {
			// Fallback to no-arg constructor.
			return new $class();
		}
	}
}
