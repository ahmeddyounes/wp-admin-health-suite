<?php
/**
 * Core Settings
 *
 * General plugin settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CoreSettings
 *
 * Manages general plugin settings.
 *
 * @since 1.2.0
 */
class CoreSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'general';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'General Settings', 'wp-admin-health-suite' ),
			'description' => __( 'Configure general plugin behavior.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		return array(
			'health_score_cache_duration' => array(
				'section'     => 'general',
				'title'       => __( 'Health Score Cache Duration (hours)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 1,
				'sanitize'    => 'integer',
				'description' => __( 'How long to cache health score calculations (1-24 hours).', 'wp-admin-health-suite' ),
				'min'         => 1,
				'max'         => 24,
			),
			'enable_dashboard_widget'     => array(
				'section'  => 'general',
				'title'    => __( 'Enable Dashboard Widget', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'admin_bar_menu'              => array(
				'section'  => 'general',
				'title'    => __( 'Show Admin Bar Menu', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'notification_email'          => array(
				'section'     => 'general',
				'title'       => __( 'Notification Email', 'wp-admin-health-suite' ),
				'type'        => 'email',
				'default'     => '',
				'sanitize'    => 'email',
				'description' => __( 'Email address for health notifications.', 'wp-admin-health-suite' ),
			),
			'enable_logging'              => array(
				'section'     => 'general',
				'title'       => __( 'Enable Logging', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable system logging for monitoring and debugging.', 'wp-admin-health-suite' ),
			),
			'log_retention_days'          => array(
				'section'     => 'general',
				'title'       => __( 'Log Retention Days', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 7,
				'sanitize'    => 'integer',
				'description' => __( 'Days to retain logs before automatic cleanup (7-90).', 'wp-admin-health-suite' ),
				'min'         => 7,
				'max'         => 90,
			),
			'delete_data_on_uninstall'    => array(
				'section'     => 'general',
				'title'       => __( 'Delete Data on Uninstall', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Remove all plugin data when uninstalling.', 'wp-admin-health-suite' ),
			),
			'health_score_threshold'      => array(
				'section'     => 'general',
				'title'       => __( 'Health Score Threshold', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 70,
				'sanitize'    => 'integer',
				'description' => __( 'Minimum health score before warnings (0-100).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 100,
			),
		);
	}
}
