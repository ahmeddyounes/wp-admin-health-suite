<?php
/**
 * Advanced Settings
 *
 * Advanced plugin settings including REST API, debug, and security.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AdvancedSettings
 *
 * Manages advanced plugin settings.
 *
 * @since 1.2.0
 */
class AdvancedSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'advanced';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'Advanced', 'wp-admin-health-suite' ),
			'description' => __( 'Advanced settings for power users.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		return array(
			'enable_rest_api'     => array(
				'section'     => 'advanced',
				'title'       => __( 'Enable REST API', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable REST API endpoints for external integrations.', 'wp-admin-health-suite' ),
			),
			'rest_api_rate_limit' => array(
				'section'     => 'advanced',
				'title'       => __( 'REST API Rate Limit (requests/minute)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 60,
				'sanitize'    => 'integer',
				'description' => __( 'Maximum number of API requests allowed per minute (10-120).', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 120,
			),
			'debug_mode'          => array(
				'section'     => 'advanced',
				'title'       => __( 'Debug Mode', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable detailed logging and visible query times for debugging.', 'wp-admin-health-suite' ),
			),
			'custom_css'          => array(
				'section'     => 'advanced',
				'title'       => __( 'Custom Admin CSS', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'css',
				'description' => __( 'Custom CSS to apply to admin pages.', 'wp-admin-health-suite' ),
			),
			'safe_mode'           => array(
				'section'     => 'advanced',
				'title'       => __( 'Safe Mode', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'When enabled, all delete/clean endpoints return preview only without modifying data.', 'wp-admin-health-suite' ),
			),
			'batch_size'          => array(
				'section'     => 'advanced',
				'title'       => __( 'Batch Processing Size', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 100,
				'sanitize'    => 'integer',
				'description' => __( 'Number of items to process in each batch.', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 1000,
			),
			'delete_data_on_uninstall' => array(
				'section'     => 'advanced',
				'title'       => __( 'Delete Data on Uninstall', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'When enabled, all plugin data (tables, options, transients) will be permanently deleted when the plugin is uninstalled. Leave disabled to preserve data for later reinstallation.', 'wp-admin-health-suite' ),
			),
		);
	}
}
