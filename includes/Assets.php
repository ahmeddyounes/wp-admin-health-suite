<?php
/**
 * Assets Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Assets class for handling CSS and JavaScript assets.
 */
class Assets {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 * @param string $plugin_url Plugin URL.
	 */
	public function __construct( $version, $plugin_url ) {
		$this->version     = $version;
		$this->plugin_url  = $plugin_url;
		$this->plugin_path = WP_ADMIN_HEALTH_PLUGIN_DIR;

		$this->init_hooks();
	}

	/**
	 * Initialize assets hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Hook for assets initialization.
		do_action( 'wpha_assets_init' );
	}

	/**
	 * Check if current screen is a plugin admin page.
	 *
	 * @return bool True if on plugin admin page.
	 */
	private function is_plugin_admin_page() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Check if screen ID contains 'admin-health'.
		return strpos( $screen->id, 'admin-health' ) !== false;
	}

	/**
	 * Enqueue admin CSS and JavaScript assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// Only load on plugin admin pages.
		if ( ! $this->is_plugin_admin_page() ) {
			return;
		}

		$this->enqueue_admin_styles();
		$this->enqueue_admin_scripts();
	}

	/**
	 * Enqueue admin CSS files.
	 *
	 * @return void
	 */
	private function enqueue_admin_styles() {
		// Enqueue main admin CSS.
		wp_enqueue_style(
			'wpha-admin-css',
			$this->plugin_url . 'assets/css/admin.css',
			array(),
			$this->get_asset_version( 'assets/css/admin.css' ),
			'all'
		);

		// Enqueue dashboard CSS.
		wp_enqueue_style(
			'wpha-dashboard-css',
			$this->plugin_url . 'assets/css/dashboard.css',
			array(),
			$this->get_asset_version( 'assets/css/dashboard.css' ),
			'all'
		);

		// Enqueue database health CSS.
		wp_enqueue_style(
			'wpha-database-health-css',
			$this->plugin_url . 'assets/css/database-health.css',
			array(),
			$this->get_asset_version( 'assets/css/database-health.css' ),
			'all'
		);

		// Enqueue media audit CSS.
		wp_enqueue_style(
			'wpha-media-audit-css',
			$this->plugin_url . 'assets/css/media-audit.css',
			array(),
			$this->get_asset_version( 'assets/css/media-audit.css' ),
			'all'
		);

		// Enqueue performance CSS.
		wp_enqueue_style(
			'wpha-performance-css',
			$this->plugin_url . 'assets/css/performance.css',
			array(),
			$this->get_asset_version( 'assets/css/performance.css' ),
			'all'
		);
	}

	/**
	 * Enqueue admin JavaScript files.
	 *
	 * @return void
	 */
	private function enqueue_admin_scripts() {
		$screen = get_current_screen();

		// Determine which bundle to load based on current screen
		$bundle_map = array(
			'toplevel_page_admin-health'             => 'dashboard',
			'admin-health_page_admin-health-database' => 'database-health',
			'admin-health_page_admin-health-media'   => 'media-audit',
			'admin-health_page_admin-health-performance' => 'performance',
			'admin-health_page_admin-health-settings' => 'settings',
		);

		// Get the bundle name for current screen
		$bundle_name = isset( $bundle_map[ $screen->id ] ) ? $bundle_map[ $screen->id ] : 'dashboard';

		// Enqueue vendor bundle (shared dependencies)
		$vendor_path = 'assets/js/dist/vendor.bundle.js';
		if ( file_exists( $this->plugin_path . $vendor_path ) ) {
			$vendor_asset = $this->get_asset_data( 'vendor' );
			wp_enqueue_script(
				'wpha-vendor-js',
				$this->plugin_url . $vendor_path,
				$vendor_asset['dependencies'],
				$this->get_asset_version( $vendor_path ),
				true
			);
		}

		// Enqueue page-specific bundle
		$bundle_path      = 'assets/js/dist/' . $bundle_name . '.bundle.js';
		$bundle_asset     = $this->get_asset_data( $bundle_name );
		$bundle_deps      = array_merge( array( 'react', 'react-dom' ), $bundle_asset['dependencies'] );

		// Add vendor bundle as dependency if it exists
		if ( file_exists( $this->plugin_path . 'assets/js/dist/vendor.bundle.js' ) ) {
			$bundle_deps[] = 'wpha-vendor-js';
		}

		wp_enqueue_script(
			'wpha-' . $bundle_name . '-js',
			$this->plugin_url . $bundle_path,
			$bundle_deps,
			$this->get_asset_version( $bundle_path ),
			true
		);

		// Localize script with data.
		$this->localize_admin_script( 'wpha-' . $bundle_name . '-js' );
	}

	/**
	 * Localize admin script with required data.
	 *
	 * @param string $handle Script handle to localize.
	 * @return void
	 */
	private function localize_admin_script( $handle ) {
		wp_localize_script(
			$handle,
			'wpAdminHealthData',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wpha_nonce' ),
				'rest_url'   => rest_url(),
				'plugin_url' => $this->plugin_url,
				'i18n'       => array(
					'loading'       => __( 'Loading...', 'wp-admin-health-suite' ),
					'error'         => __( 'An error occurred.', 'wp-admin-health-suite' ),
					'success'       => __( 'Success!', 'wp-admin-health-suite' ),
					'confirm'       => __( 'Are you sure?', 'wp-admin-health-suite' ),
					'save'          => __( 'Save', 'wp-admin-health-suite' ),
					'cancel'        => __( 'Cancel', 'wp-admin-health-suite' ),
					'delete'        => __( 'Delete', 'wp-admin-health-suite' ),
					'refresh'       => __( 'Refresh', 'wp-admin-health-suite' ),
					'no_data'       => __( 'No data available.', 'wp-admin-health-suite' ),
					'processing'    => __( 'Processing...', 'wp-admin-health-suite' ),
					'analyze'       => __( 'Analyze', 'wp-admin-health-suite' ),
					'clean'         => __( 'Clean', 'wp-admin-health-suite' ),
					'revisions'     => __( 'Post Revisions', 'wp-admin-health-suite' ),
					'transients'    => __( 'Transients', 'wp-admin-health-suite' ),
					'spam'          => __( 'Spam Comments', 'wp-admin-health-suite' ),
					'trash'         => __( 'Trash (Posts & Comments)', 'wp-admin-health-suite' ),
					'orphaned'      => __( 'Orphaned Data', 'wp-admin-health-suite' ),
					'confirmCleanup' => __( 'Are you sure you want to clean this data? This action cannot be undone.', 'wp-admin-health-suite' ),
				),
			)
		);
	}

	/**
	 * Get asset data from webpack-generated asset file.
	 *
	 * @param string $bundle_name Bundle name without extension.
	 * @return array Asset data with dependencies and version.
	 */
	private function get_asset_data( $bundle_name ) {
		$assets_file = $this->plugin_path . 'assets/js/dist/assets.php';

		if ( file_exists( $assets_file ) ) {
			$all_assets = require $assets_file;
			$key        = $bundle_name . '.bundle.js';

			if ( isset( $all_assets[ $key ] ) ) {
				return $all_assets[ $key ];
			}
		}

		// Return default asset data if file doesn't exist.
		return array(
			'dependencies' => array(),
			'version'      => $this->version,
		);
	}

	/**
	 * Get asset version using filemtime in development.
	 *
	 * @param string $relative_path Relative path to asset file from plugin directory.
	 * @return string Version string.
	 */
	private function get_asset_version( $relative_path ) {
		$file_path = $this->plugin_path . $relative_path;

		// Use filemtime for cache busting in development.
		if ( file_exists( $file_path ) && ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return (string) filemtime( $file_path );
		}

		// Use plugin version in production.
		return $this->version;
	}
}
