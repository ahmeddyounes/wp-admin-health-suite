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
	 * @return void
	 */
	private function init_hooks() {
		// Load admin menu class on admin side only.
		if ( is_admin() ) {
			$this->load_admin_menu();
		}

		// Hook for admin initialization.
		do_action( 'wpha_admin_init' );
	}

	/**
	 * Load admin menu class.
	 *
	 * @return void
	 */
	private function load_admin_menu() {
		require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'admin/class-admin.php';
		new \WPAdminHealth\Admin\Admin( $this->version, $this->plugin_name );
	}
}
