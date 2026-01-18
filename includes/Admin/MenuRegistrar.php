<?php
/**
 * Menu Registrar Service
 *
 * Handles WordPress admin menu registration for the plugin.
 *
 * @package WPAdminHealth\Admin
 */

namespace WPAdminHealth\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Menu Registrar class for handling admin menu registration.
 *
 * This class is responsible only for registering menus with WordPress.
 * Page rendering is handled by PageRenderer.
 *
 * @since 1.4.0
 */
class MenuRegistrar {

	/**
	 * Page renderer instance.
	 *
	 * @var PageRenderer
	 */
	private PageRenderer $page_renderer;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param PageRenderer $page_renderer Page renderer instance.
	 */
	public function __construct( PageRenderer $page_renderer ) {
		$this->page_renderer = $page_renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 10 );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		// Add top-level menu 'Admin Health'.
		add_menu_page(
			__( 'Admin Health', 'wp-admin-health-suite' ),
			__( 'Admin Health', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health',
			array( $this->page_renderer, 'render_dashboard' ),
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
			array( $this->page_renderer, 'render_dashboard' )
		);

		// Add Database Health submenu.
		add_submenu_page(
			'admin-health',
			__( 'Database Health', 'wp-admin-health-suite' ),
			__( 'Database Health', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-database',
			array( $this->page_renderer, 'render_database_health' )
		);

		// Add Media Audit submenu.
		add_submenu_page(
			'admin-health',
			__( 'Media Audit', 'wp-admin-health-suite' ),
			__( 'Media Audit', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-media',
			array( $this->page_renderer, 'render_media_audit' )
		);

		// Add Performance submenu.
		add_submenu_page(
			'admin-health',
			__( 'Performance', 'wp-admin-health-suite' ),
			__( 'Performance', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-performance',
			array( $this->page_renderer, 'render_performance' )
		);

		// Add Settings submenu.
		add_submenu_page(
			'admin-health',
			__( 'Settings', 'wp-admin-health-suite' ),
			__( 'Settings', 'wp-admin-health-suite' ),
			'manage_options',
			'admin-health-settings',
			array( $this->page_renderer, 'render_settings' )
		);
	}

	/**
	 * Add admin body class for styling scope.
	 *
	 * @since 1.4.0
	 *
	 * @param string $classes Current body classes.
	 * @return string Modified body classes.
	 */
	public function add_admin_body_class( string $classes ): string {
		$screen = get_current_screen();

		// Check if we're on one of our admin pages.
		if ( $screen && strpos( $screen->id, 'admin-health' ) !== false ) {
			$classes .= ' wpha-admin-page';
		}

		return $classes;
	}
}
