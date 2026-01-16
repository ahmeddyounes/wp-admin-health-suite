<?php
/**
 * Multisite Support Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles multisite-specific functionality.
 *
 * @since 1.0.0
 */
class Multisite {

	/**
	 * Network settings option name.
	 *
	 * @var string
	 */
	const NETWORK_SETTINGS_OPTION = 'wpha_network_settings';

	/**
	 * Initialize multisite hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_multisite() ) {
			return;
		}

		// Network admin hooks.
		add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_network_settings' ) );
		add_action( 'network_admin_edit_wpha_update_network_settings', array( $this, 'save_network_settings' ) );
	}

	/**
	 * Check if plugin is network activated.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if network activated.
	 */
	public static function is_network_activated() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( WP_ADMIN_HEALTH_PLUGIN_BASENAME );
	}

	/**
	 * Add network admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_network_admin_menu() {
		add_menu_page(
			__( 'Admin Health Network', 'wp-admin-health-suite' ),
			__( 'Admin Health', 'wp-admin-health-suite' ),
			'manage_network_options',
			'admin-health-network',
			array( $this, 'render_network_dashboard' ),
			'dashicons-heart',
			80
		);

		add_submenu_page(
			'admin-health-network',
			__( 'Network Dashboard', 'wp-admin-health-suite' ),
			__( 'Dashboard', 'wp-admin-health-suite' ),
			'manage_network_options',
			'admin-health-network',
			array( $this, 'render_network_dashboard' )
		);

		add_submenu_page(
			'admin-health-network',
			__( 'Network Settings', 'wp-admin-health-suite' ),
			__( 'Settings', 'wp-admin-health-suite' ),
			'manage_network_options',
			'admin-health-network-settings',
			array( $this, 'render_network_settings' )
		);

		add_submenu_page(
			'admin-health-network',
			__( 'Network Database Health', 'wp-admin-health-suite' ),
			__( 'Database Health', 'wp-admin-health-suite' ),
			'manage_network_options',
			'admin-health-network-database',
			array( $this, 'render_network_database' )
		);
	}

	/**
	 * Register network settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_network_settings() {
		if ( ! is_network_admin() ) {
			return;
		}

		register_setting(
			'wpha_network_settings_group',
			self::NETWORK_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_network_settings' ),
			)
		);
	}

	/**
	 * Get network settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Network settings.
	 */
	public function get_network_settings() {
		$defaults = $this->get_default_network_settings();
		$settings = get_site_option( self::NETWORK_SETTINGS_OPTION, array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get default network settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Default network settings.
	 */
	public function get_default_network_settings() {
		return array(
			'enable_network_wide'      => false,
			'shared_scan_results'      => false,
			'network_scan_mode'        => 'current_site',
			'allow_site_override'      => true,
			'network_admin_only_scans' => true,
		);
	}

	/**
	 * Get a specific network setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public function get_network_setting( $key, $default = null ) {
		$settings = $this->get_network_settings();

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Sanitize network settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_network_settings( $input ) {
		$sanitized = array();

		$sanitized['enable_network_wide']      = ! empty( $input['enable_network_wide'] );
		$sanitized['shared_scan_results']      = ! empty( $input['shared_scan_results'] );
		$sanitized['allow_site_override']      = ! empty( $input['allow_site_override'] );
		$sanitized['network_admin_only_scans'] = ! empty( $input['network_admin_only_scans'] );

		$valid_scan_modes = array( 'current_site', 'network_wide' );
		$sanitized['network_scan_mode'] = in_array( $input['network_scan_mode'] ?? '', $valid_scan_modes, true )
			? $input['network_scan_mode']
			: 'current_site';

		return $sanitized;
	}

	/**
	 * Save network settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save_network_settings() {
		check_admin_referer( 'wpha_network_settings_update' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_network_settings().
		$settings = isset( $_POST['wpha_network_settings'] ) ? wp_unslash( $_POST['wpha_network_settings'] ) : array();
		$sanitized = $this->sanitize_network_settings( $settings );

		update_site_option( self::NETWORK_SETTINGS_OPTION, $sanitized );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'admin-health-network-settings',
					'updated' => 'true',
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render network dashboard page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_network_dashboard() {
		$this->render_network_page( 'network-dashboard' );
	}

	/**
	 * Render network settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_network_settings() {
		$this->render_network_page( 'network-settings' );
	}

	/**
	 * Render network database page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_network_database() {
		$this->render_network_page( 'network-database' );
	}

	/**
	 * Render a network admin template page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Template name without extension.
	 * @return void
	 */
	private function render_network_page( $template ) {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' )
			);
		}

		$template_path = WP_ADMIN_HEALTH_PLUGIN_DIR . 'templates/network/' . $template . '.php';

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

	/**
	 * Get all sites in the network.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of site objects.
	 */
	public function get_network_sites() {
		if ( ! is_multisite() ) {
			return array();
		}

		return get_sites(
			array(
				'number' => 999,
			)
		);
	}

	/**
	 * Check if network-wide scans are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if network-wide scans are enabled.
	 */
	public function is_network_wide_enabled() {
		return (bool) $this->get_network_setting( 'enable_network_wide', false );
	}

	/**
	 * Check if shared scan results are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if shared scan results are enabled.
	 */
	public function is_shared_results_enabled() {
		return (bool) $this->get_network_setting( 'shared_scan_results', false );
	}

	/**
	 * Check if site can override network settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if site can override.
	 */
	public function can_site_override() {
		return (bool) $this->get_network_setting( 'allow_site_override', true );
	}

	/**
	 * Check if current user can run network-wide scans.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can run network scans.
	 */
	public function can_run_network_scans() {
		if ( ! is_multisite() ) {
			return true;
		}

		$network_admin_only = $this->get_network_setting( 'network_admin_only_scans', true );

		if ( $network_admin_only ) {
			return is_super_admin();
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Get network scan mode.
	 *
	 * @since 1.0.0
	 *
	 * @return string Scan mode: 'current_site' or 'network_wide'.
	 */
	public function get_network_scan_mode() {
		return $this->get_network_setting( 'network_scan_mode', 'current_site' );
	}
}
