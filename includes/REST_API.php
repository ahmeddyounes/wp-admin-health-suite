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
class REST_API {

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
	 *
	 * @return void
	 */
	public function register_routes() {
		// Load the REST controller base class.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-rest-controller.php';

		// Load and register activity controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-activity-controller.php';
		$activity_controller = new REST\Activity_Controller();
		$activity_controller->register_routes();

		// Load and register dashboard controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-dashboard-controller.php';
		$dashboard_controller = new REST\Dashboard_Controller();
		$dashboard_controller->register_routes();

		// Load and register database controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-database-controller.php';
		$database_controller = new REST\Database_Controller();
		$database_controller->register_routes();

		// Load and register media controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-media-controller.php';
		$media_controller = new REST\Media_Controller();
		$media_controller->register_routes();

		// Load and register performance controller.
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/class-performance-controller.php';
		$performance_controller = new REST\Performance_Controller();
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
