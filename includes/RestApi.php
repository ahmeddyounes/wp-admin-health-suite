<?php
/**
 * REST API Class (Legacy Compatibility Wrapper)
 *
 * @package WPAdminHealth
 *
 * @deprecated 1.3.0 This class is deprecated and will be removed in a future release.
 *                   REST API routes are now registered via {@see \WPAdminHealth\Providers\RESTServiceProvider}.
 *                   The `wpha_rest_api_init` and `wpha_register_rest_routes` hooks fired by this class
 *                   are no longer triggered at runtime. Use the WordPress `rest_api_init` action instead.
 */

namespace WPAdminHealth;

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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API class for handling REST API endpoints.
 *
 * This class is a thin compatibility wrapper that delegates to the container-based
 * route registration. It no longer uses manual require_once statements.
 *
 * @since 1.0.0
 * @since 1.4.0 Converted to thin wrapper using container-based controller resolution.
 * @deprecated 1.3.0 Use {@see \WPAdminHealth\Providers\RESTServiceProvider} instead.
 *                   This class is no longer instantiated by the plugin.
 */
class RestApi {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Controller class-string identifiers for registration.
	 *
	 * @since 1.4.0
	 * @var array<string>
	 */
	private static $controller_classes = array(
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

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @deprecated 1.3.0 Use RESTServiceProvider instead.
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( $version ) {
		_doing_it_wrong(
			__METHOD__,
			esc_html__(
				'The RestApi class is deprecated. REST routes are now registered via RESTServiceProvider.',
				'wp-admin-health-suite'
			),
			'1.3.0'
		);

		$this->version = $version;

		$this->init_hooks();
	}

	/**
	 * Initialize REST API hooks.
	 *
	 * @since 1.0.0
	 * @deprecated 1.3.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		/**
		 * Fires after REST API initialization.
		 *
		 * @since 1.0.0
		 * @deprecated 1.3.0 Use WordPress `rest_api_init` action instead.
		 *
		 * @hook wpha_rest_api_init
		 */
		do_action_deprecated(
			'wpha_rest_api_init',
			array(),
			'1.3.0',
			'rest_api_init',
			__( 'The wpha_rest_api_init hook is deprecated. Use the WordPress rest_api_init action instead.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Register REST API routes.
	 *
	 * Routes are now registered by resolving controllers from the container.
	 * No manual require_once statements are used.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Updated to use dependency injection from container.
	 * @since 1.4.0 Refactored to use container-based controller resolution without require_once.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get the container for dependency injection.
		$container = Plugin::get_instance()->get_container();

		// Register all controllers by resolving them from the container.
		foreach ( self::$controller_classes as $controller_class ) {
			try {
				// Resolve controller from container (autoloading handles class loading).
				$controller = $container->get( $controller_class );

				if ( $controller && method_exists( $controller, 'register_routes' ) ) {
					$controller->register_routes();
				}
			} catch ( \Exception $e ) {
				// Log error but don't break other controllers.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'WP Admin Health Suite (RestApi): Failed to register %s: %s',
							$controller_class,
							$e->getMessage()
						)
					);
				}
			}
		}

		/**
		 * Fires after core REST routes are registered.
		 *
		 * Allows other controllers to register their routes.
		 *
		 * @since 1.0.0
		 * @deprecated 1.3.0 Use WordPress `rest_api_init` action instead.
		 *
		 * @hook wpha_register_rest_routes
		 */
		do_action_deprecated(
			'wpha_register_rest_routes',
			array(),
			'1.3.0',
			'rest_api_init',
			__( 'The wpha_register_rest_routes hook is deprecated. Use the WordPress rest_api_init action instead.', 'wp-admin-health-suite' )
		);
	}
}
