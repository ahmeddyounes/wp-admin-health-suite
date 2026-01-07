<?php
/**
 * Admin Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin class for handling WordPress admin functionality.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version Plugin version.
	 * @param string $plugin_name Plugin name.
	 */
	public function __construct( $version, $plugin_name ) {
		$this->version     = $version;
		$this->plugin_name = $plugin_name;

		$this->init_hooks();
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Load admin menu class on admin side only.
		if ( is_admin() ) {
			$this->load_admin_menu();
		}

		/**
		 * Fires after admin initialization.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_admin_init
		 */
		do_action( 'wpha_admin_init' );
	}

	/**
	 * Load admin menu class.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_admin_menu() {
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'admin/class-admin.php';
		new \WPAdminHealth\Admin\Admin( $this->version, $this->plugin_name );
	}
}
