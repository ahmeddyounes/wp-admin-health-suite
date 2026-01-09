<?php
/**
 * Admin Menu and Page Registration
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin menu class for handling admin menu registration.
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
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 10 );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		// Add top-level menu 'Admin Health'.
		add_menu_page(
			__( 'Admin Health', 'wp-admin-health-suite' ),
			__( 'Admin Health', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health',
			array( $this, 'render_dashboard_page' ),
			'dashicons-heart',
			80
		);

		// Add Dashboard submenu (replaces the default first submenu).
		add_submenu_page(
			'admin-health',
			__( 'Dashboard', 'wp-admin-health-suite' ),
			__( 'Dashboard', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health',
			array( $this, 'render_dashboard_page' )
		);

		// Add Database Health submenu.
		add_submenu_page(
			'admin-health',
			__( 'Database Health', 'wp-admin-health-suite' ),
			__( 'Database Health', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-database',
			array( $this, 'render_database_health_page' )
		);

		// Add Media Audit submenu.
		add_submenu_page(
			'admin-health',
			__( 'Media Audit', 'wp-admin-health-suite' ),
			__( 'Media Audit', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-media',
			array( $this, 'render_media_audit_page' )
		);

		// Add Performance submenu.
		add_submenu_page(
			'admin-health',
			__( 'Performance', 'wp-admin-health-suite' ),
			__( 'Performance', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-performance',
			array( $this, 'render_performance_page' )
		);

		// Add Settings submenu.
		add_submenu_page(
			'admin-health',
			__( 'Settings', 'wp-admin-health-suite' ),
			__( 'Settings', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add admin body class for styling scope.
	 *
	 * @param string $classes Current body classes.
	 * @return string Modified body classes.
	 */
	public function add_admin_body_class( $classes ) {
		$screen = get_current_screen();

		// Check if we're on one of our admin pages.
		if ( $screen && strpos( $screen->id, 'admin-health' ) !== false ) {
			$classes .= ' wpha-admin-page';
		}

		return $classes;
	}

	/**
	 * Render Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		$this->render_page( 'dashboard' );
	}

	/**
	 * Render Database Health page.
	 *
	 * @return void
	 */
	public function render_database_health_page() {
		$this->render_page( 'database-health' );
	}

	/**
	 * Render Media Audit page.
	 *
	 * @return void
	 */
	public function render_media_audit_page() {
		$this->render_page( 'media-audit' );
	}

	/**
	 * Render Performance page.
	 *
	 * @return void
	 */
	public function render_performance_page() {
		$this->render_page( 'performance' );
	}

	/**
	 * Render Settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->render_page( 'settings' );
	}

	/**
	 * Render a template page.
	 *
	 * @param string $template Template name without extension.
	 * @return void
	 */
	private function render_page( $template ) {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' )
			);
		}

		$template_path = WP_ADMIN_HEALTH_PLUGIN_DIR . 'templates/admin/' . $template . '.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			wp_die(
				esc_html__(
					'Template file not found. Please contact the plugin administrator.',
					'wp-admin-health-suite'
				)
			);
		}
	}
}
