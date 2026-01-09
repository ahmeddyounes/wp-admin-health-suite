<?php
/**
 * REST Service Provider
 *
 * Registers REST API controllers and services.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\REST\Database_Controller;
use WPAdminHealth\REST\Media_Controller;
use WPAdminHealth\REST\Dashboard_Controller;
use WPAdminHealth\REST\Performance_Controller;
use WPAdminHealth\REST\Activity_Controller;
use WPAdminHealth\REST\Settings_Controller;
use WPAdminHealth\Health_Calculator;

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
class RESTServiceProvider extends Service_Provider {

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
		'rest.settings_controller',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Database Controller.
		$this->container->bind(
			'rest.database_controller',
			function ( $container ) {
				if ( ! class_exists( Database_Controller::class ) ) {
					return null;
				}

				$settings = $container->get( SettingsInterface::class );
				$analyzer = $container->get( AnalyzerInterface::class );

				return $this->create_controller( Database_Controller::class, $settings, $analyzer );
			}
		);

		// Register Media Controller.
		$this->container->bind(
			'rest.media_controller',
			function ( $container ) {
				if ( ! class_exists( Media_Controller::class ) ) {
					return null;
				}

				$settings = $container->get( SettingsInterface::class );
				$scanner  = $container->get( ScannerInterface::class );

				return $this->create_controller( Media_Controller::class, $settings, $scanner );
			}
		);

		// Register Dashboard Controller.
		$this->container->bind(
			'rest.dashboard_controller',
			function ( $container ) {
				if ( ! class_exists( Dashboard_Controller::class ) ) {
					return null;
				}

				$settings          = $container->get( SettingsInterface::class );
				$health_calculator = new Health_Calculator();

				return $this->create_controller( Dashboard_Controller::class, $settings, $health_calculator );
			}
		);

		// Register Performance Controller.
		$this->container->bind(
			'rest.performance_controller',
			function ( $container ) {
				if ( ! class_exists( Performance_Controller::class ) ) {
					return null;
				}

				$settings = $container->get( SettingsInterface::class );

				return $this->create_controller( Performance_Controller::class, $settings );
			}
		);

		// Register Activity Controller.
		$this->container->bind(
			'rest.activity_controller',
			function ( $container ) {
				if ( ! class_exists( Activity_Controller::class ) ) {
					return null;
				}

				$settings = $container->get( SettingsInterface::class );

				return $this->create_controller( Activity_Controller::class, $settings );
			}
		);

		// Register Settings Controller.
		$this->container->bind(
			'rest.settings_controller',
			function ( $container ) {
				if ( ! class_exists( Settings_Controller::class ) ) {
					return null;
				}

				$settings = $container->get( SettingsInterface::class );

				return $this->create_controller( Settings_Controller::class, $settings );
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
			'rest.settings_controller',
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
