<?php
/**
 * Page Renderer Service
 *
 * Handles WordPress admin page rendering for the plugin.
 *
 * @package WPAdminHealth\Admin
 */

namespace WPAdminHealth\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Page Renderer class for handling admin page template rendering.
 *
 * This class is responsible only for rendering admin page templates.
 * Menu registration is handled by MenuRegistrar.
 *
 * @since 1.4.0
 * @since 1.5.0 Added SettingsViewModel support for settings page.
 */
class PageRenderer {

	/**
	 * Template directory path.
	 *
	 * @var string
	 */
	private string $template_dir;

	/**
	 * Required capability for viewing pages.
	 *
	 * @var string
	 */
	private string $capability;

	/**
	 * Settings view model for settings page rendering.
	 *
	 * @since 1.5.0
	 * @var SettingsViewModel|null
	 */
	private ?SettingsViewModel $settings_view_model;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 * @since 1.5.0 Added optional SettingsViewModel parameter.
	 *
	 * @param string                 $template_dir        Template directory path.
	 * @param string                 $capability          Required capability for viewing pages.
	 * @param SettingsViewModel|null $settings_view_model Optional settings view model.
	 */
	public function __construct(
		string $template_dir,
		string $capability = 'manage_options',
		?SettingsViewModel $settings_view_model = null
	) {
		$this->template_dir        = trailingslashit( $template_dir );
		$this->capability          = $capability;
		$this->settings_view_model = $settings_view_model;
	}

	/**
	 * Render Dashboard page.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->render( 'dashboard' );
	}

	/**
	 * Render Database Health page.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function render_database_health(): void {
		$this->render( 'database-health' );
	}

	/**
	 * Render Media Audit page.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function render_media_audit(): void {
		$this->render( 'media-audit' );
	}

	/**
	 * Render Performance page.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function render_performance(): void {
		$this->render( 'performance' );
	}

	/**
	 * Render Settings page.
	 *
	 * Passes the SettingsViewModel to the template if available, eliminating
	 * the need for service location (facade instantiation) in the template.
	 *
	 * @since 1.4.0
	 * @since 1.5.0 Added SettingsViewModel injection to template.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$data = array();

		if ( null !== $this->settings_view_model ) {
			$data['settings_obj'] = $this->settings_view_model;
		}

		$this->render( 'settings', $data );
	}

	/**
	 * Render a template page.
	 *
	 * @since 1.4.0
	 *
	 * @param string $template Template name without extension.
	 * @param array  $data     Optional data to pass to the template.
	 * @return void
	 */
	public function render( string $template, array $data = array() ): void {
		// Check user capabilities.
		if ( ! current_user_can( $this->capability ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' )
			);
		}

		$template_path = $this->template_dir . $template . '.php';

		if ( file_exists( $template_path ) ) {
			// Extract data for template use.
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data, EXTR_SKIP );
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
	 * Get the template directory path.
	 *
	 * @since 1.4.0
	 *
	 * @return string Template directory path.
	 */
	public function get_template_dir(): string {
		return $this->template_dir;
	}
}
