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
		// Enqueue core admin JS with dependencies.
		wp_enqueue_script(
			'wpha-admin-js',
			$this->plugin_url . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch', 'wp-i18n', 'wp-components' ),
			$this->get_asset_version( 'assets/js/admin.js' ),
			true
		);

		// Enqueue charts JS with dependencies.
		wp_enqueue_script(
			'wpha-charts-js',
			$this->plugin_url . 'assets/js/charts.js',
			array( 'jquery', 'wpha-admin-js' ),
			$this->get_asset_version( 'assets/js/charts.js' ),
			true
		);

		// Enqueue database health JS with dependencies.
		wp_enqueue_script(
			'wpha-database-health-js',
			$this->plugin_url . 'assets/js/database-health.js',
			array( 'jquery', 'wp-api-fetch', 'wpha-admin-js' ),
			$this->get_asset_version( 'assets/js/database-health.js' ),
			true
		);

		// Enqueue media audit JS with dependencies.
		wp_enqueue_script(
			'wpha-media-audit-js',
			$this->plugin_url . 'assets/js/media-audit.js',
			array( 'jquery', 'wp-api-fetch', 'wpha-admin-js' ),
			$this->get_asset_version( 'assets/js/media-audit.js' ),
			true
		);

		// Enqueue performance JS with dependencies.
		wp_enqueue_script(
			'wpha-performance-js',
			$this->plugin_url . 'assets/js/performance.js',
			array( 'jquery', 'wp-element', 'wp-api-fetch', 'wpha-admin-js' ),
			$this->get_asset_version( 'assets/js/performance.js' ),
			true
		);

		// Localize script with data.
		$this->localize_admin_script();
	}

	/**
	 * Localize admin script with required data.
	 *
	 * @return void
	 */
	private function localize_admin_script() {
		wp_localize_script(
			'wpha-admin-js',
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
