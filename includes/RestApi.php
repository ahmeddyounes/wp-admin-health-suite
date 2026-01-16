<?php
/**
 * REST API Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API class for handling REST API endpoints.
 *
 * @since 1.0.0
 */
class RestApi {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( $version ) {
		$this->version = $version;

		$this->init_hooks();
	}

	/**
	 * Initialize REST API hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		/**
		 * Fires after REST API initialization.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_rest_api_init
		 */
		do_action( 'wpha_rest_api_init' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Updated to use dependency injection from container.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Load the REST controller base class.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/RestController.php';

		// Get the container for dependency injection.
		$container = Plugin::get_instance()->get_container();

		// Load and register activity controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/ActivityController.php';
		$activity_controller = new REST\ActivityController(
			$container->get( \WPAdminHealth\Contracts\SettingsInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ConnectionInterface::class )
		);
		$activity_controller->register_routes();

		// Load and register dashboard controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/DashboardController.php';
		$dashboard_controller = new REST\DashboardController(
			$container->get( \WPAdminHealth\Contracts\SettingsInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ConnectionInterface::class ),
			$container->get( \WPAdminHealth\HealthCalculator::class )
		);
		$dashboard_controller->register_routes();

		// Load and register database controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/DatabaseController.php';
		$database_controller = new REST\DatabaseController(
			$container->get( \WPAdminHealth\Contracts\SettingsInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ConnectionInterface::class ),
			$container->get( \WPAdminHealth\Contracts\AnalyzerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\RevisionsManagerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\TransientsCleanerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\OrphanedCleanerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\TrashCleanerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\OptimizerInterface::class )
		);
		$database_controller->register_routes();

		// Load and register media controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/MediaController.php';
		$media_controller = new REST\MediaController(
			$container->get( \WPAdminHealth\Contracts\SettingsInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ConnectionInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ScannerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\DuplicateDetectorInterface::class ),
			$container->get( \WPAdminHealth\Contracts\LargeFilesInterface::class ),
			$container->get( \WPAdminHealth\Contracts\AltTextCheckerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ReferenceFinderInterface::class ),
			$container->get( \WPAdminHealth\Contracts\SafeDeleteInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ExclusionsInterface::class )
		);
		$media_controller->register_routes();

		// Load and register performance controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/PerformanceController.php';
		$performance_controller = new REST\PerformanceController(
			$container->get( \WPAdminHealth\Contracts\SettingsInterface::class ),
			$container->get( \WPAdminHealth\Contracts\ConnectionInterface::class ),
			$container->get( \WPAdminHealth\Contracts\AutoloadAnalyzerInterface::class ),
			$container->get( \WPAdminHealth\Contracts\QueryMonitorInterface::class ),
			$container->get( \WPAdminHealth\Contracts\PluginProfilerInterface::class )
		);
		$performance_controller->register_routes();

		/**
		 * Fires after core REST routes are registered.
		 *
		 * Allows other controllers to register their routes.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_register_rest_routes
		 */
		do_action( 'wpha_register_rest_routes' );
	}
}
