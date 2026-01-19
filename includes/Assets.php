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
 *
 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_async_defer_attributes' ), 10, 3 );

		/**
		 * Fires after assets initialization.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_assets_init
		 */
		do_action( 'wpha_assets_init' );
	}

	/**
	 * Check if current screen is a plugin admin page.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_admin_styles() {
		$screen = get_current_screen();

		// Enqueue main admin CSS (always load on plugin pages).
		wp_enqueue_style(
			'wpha-admin-css',
			$this->plugin_url . 'assets/css/admin.css',
			array(),
			$this->get_asset_version( 'assets/css/admin.css' ),
			'all'
		);

		// Lazy load page-specific CSS based on current screen.
		$css_map = array(
			'toplevel_page_admin-health'                  => 'dashboard.css',
			'admin-health_page_admin-health-database'     => 'database-health.css',
			'admin-health_page_admin-health-media'        => 'media-audit.css',
			'admin-health_page_admin-health-performance'  => 'performance.css',
		);

		// Only load the CSS for the current page.
		if ( isset( $css_map[ $screen->id ] ) ) {
			$css_file = $css_map[ $screen->id ];
			$handle = 'wpha-' . str_replace( '.css', '', $css_file ) . '-css';

			wp_enqueue_style(
				$handle,
				$this->plugin_url . 'assets/css/' . $css_file,
				array( 'wpha-admin-css' ),
				$this->get_asset_version( 'assets/css/' . $css_file ),
				'all'
			);
		}
	}

	/**
	 * Enqueue admin JavaScript files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enqueue_admin_scripts() {
		$screen = get_current_screen();

		// Determine which bundle to load based on current screen.
		$bundle_map = array(
			'toplevel_page_admin-health'             => 'dashboard',
			'admin-health_page_admin-health-database' => 'database-health',
			'admin-health_page_admin-health-media'   => 'media-audit',
			'admin-health_page_admin-health-performance' => 'performance',
			'admin-health_page_admin-health-settings' => 'settings',
		);

		// Get the bundle name for current screen.
		$bundle_name = isset( $bundle_map[ $screen->id ] ) ? $bundle_map[ $screen->id ] : 'dashboard';

		// Enqueue vendor bundle (shared dependencies).
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

		// Enqueue page-specific bundle.
		$bundle_path      = 'assets/js/dist/' . $bundle_name . '.bundle.js';
		$bundle_asset     = $this->get_asset_data( $bundle_name );
		$bundle_deps      = array_merge( array( 'react', 'react-dom', 'wp-i18n', 'wp-api-fetch' ), $bundle_asset['dependencies'] );

		// Add vendor bundle as dependency if it exists.
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

		// Set script translations for wp.i18n support.
		wp_set_script_translations(
			'wpha-' . $bundle_name . '-js',
			'wp-admin-health-suite',
			$this->plugin_path . 'languages'
		);

		// Localize script with data.
		$this->localize_admin_script( 'wpha-' . $bundle_name . '-js' );
	}

	/**
	 * Localize admin script with required data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $handle Script handle to localize.
	 * @return void
	 */
	private function localize_admin_script( $handle ) {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		wp_localize_script(
			$handle,
			'wpAdminHealthData',
			array(
				'version'        => $this->version,
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'rest_url'       => rest_url(),
				'rest_root'      => rest_url(),
				'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
				'rest_namespace' => 'wpha/v1',
				'screen_id'      => $screen_id,
				'plugin_url'     => $this->plugin_url,
				'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'features'       => $this->get_feature_flags(),
				'i18n'           => array(
					'loading'       => __( 'Loading…', 'wp-admin-health-suite' ),
					'error'         => __( 'An error occurred.', 'wp-admin-health-suite' ),
					'success'       => __( 'Success!', 'wp-admin-health-suite' ),
					'confirm'       => __( 'Are you sure?', 'wp-admin-health-suite' ),
					'save'          => __( 'Save', 'wp-admin-health-suite' ),
					'cancel'        => __( 'Cancel', 'wp-admin-health-suite' ),
					'delete'        => __( 'Delete', 'wp-admin-health-suite' ),
					'refresh'       => __( 'Refresh', 'wp-admin-health-suite' ),
					'no_data'       => __( 'No data available.', 'wp-admin-health-suite' ),
					'processing'    => __( 'Processing…', 'wp-admin-health-suite' ),
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
	 * Get feature flags for the frontend.
	 *
	 * Returns a set of boolean flags indicating which features are enabled.
	 * This allows the frontend to conditionally render UI elements based
	 * on plugin configuration.
	 *
	 * @since 1.5.0
	 *
	 * @return array<string, bool> Feature flags.
	 */
	private function get_feature_flags(): array {
		$settings = get_option( 'wpha_settings', array() );

		return array(
			'restApiEnabled'       => isset( $settings['enable_rest_api'] ) ? (bool) $settings['enable_rest_api'] : true,
			'debugMode'            => isset( $settings['debug_mode'] ) ? (bool) $settings['debug_mode'] : false,
			'safeMode'             => isset( $settings['safe_mode'] ) ? (bool) $settings['safe_mode'] : false,
			'dashboardWidget'      => isset( $settings['enable_dashboard_widget'] ) ? (bool) $settings['enable_dashboard_widget'] : true,
			'adminBarMenu'         => isset( $settings['admin_bar_menu'] ) ? (bool) $settings['admin_bar_menu'] : true,
			'loggingEnabled'       => isset( $settings['enable_logging'] ) ? (bool) $settings['enable_logging'] : false,
			'schedulingEnabled'    => isset( $settings['enable_scheduling'] ) ? (bool) $settings['enable_scheduling'] : true,
			'aiRecommendations'    => isset( $settings['enable_ai_recommendations'] ) ? (bool) $settings['enable_ai_recommendations'] : false,
			'actionSchedulerAvailable' => class_exists( 'ActionScheduler' ),
		);
	}

	/**
	 * Get asset data from webpack-generated asset file.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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

	/**
	 * Add async/defer attributes to script tags for better performance.
	 *
	 * Non-critical scripts are loaded with defer to improve page load time.
	 * This ensures scripts don't block the rendering of the page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag    The script tag HTML.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified script tag.
	 */
	public function add_async_defer_attributes( $tag, $handle, $src ) {
		// Only apply to our plugin scripts.
		if ( strpos( $handle, 'wpha-' ) !== 0 ) {
			return $tag;
		}

		// Don't defer vendor bundle or critical dependencies.
		$no_defer_scripts = array(
			'wpha-vendor-js',
		);

		if ( in_array( $handle, $no_defer_scripts, true ) ) {
			return $tag;
		}

		// Add defer attribute for non-critical scripts.
		if ( strpos( $tag, ' defer' ) === false ) {
			$tag = str_replace( ' src=', ' defer src=', $tag );
		}

		return $tag;
	}
}
