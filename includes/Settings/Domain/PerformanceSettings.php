<?php
/**
 * Performance Settings
 *
 * Performance monitoring settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class PerformanceSettings
 *
 * Manages performance monitoring settings.
 *
 * @since 1.2.0
 */
class PerformanceSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'performance';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'Performance', 'wp-admin-health-suite' ),
			'description' => __( 'Configure performance monitoring.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		return array(
			'check_autoload'             => array(
				'section'     => 'performance',
				'title'       => __( 'Analyze Autoload Options', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Analyze autoloaded options during performance checks.', 'wp-admin-health-suite' ),
			),
			'monitor_queries'            => array(
				'section'     => 'performance',
				'title'       => __( 'Monitor Slow Queries', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Monitor for slow database queries during performance checks.', 'wp-admin-health-suite' ),
			),
			'profile_plugins'            => array(
				'section'     => 'performance',
				'title'       => __( 'Profile Plugin Performance', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Profile plugin load times during performance checks.', 'wp-admin-health-suite' ),
			),
			'enable_query_monitoring'    => array(
				'section'  => 'performance',
				'title'    => __( 'Enable Query Monitoring', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'enable_ajax_monitoring'     => array(
				'section'  => 'performance',
				'title'    => __( 'Enable AJAX Monitoring', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'heartbeat_admin_frequency'  => array(
				'section'     => 'performance',
				'title'       => __( 'Heartbeat Admin Frequency (seconds)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 60,
				'sanitize'    => 'integer',
				'description' => __( 'How often the WordPress Heartbeat API runs in the admin area.', 'wp-admin-health-suite' ),
				'min'         => 15,
				'max'         => 120,
			),
			'heartbeat_editor_frequency' => array(
				'section'     => 'performance',
				'title'       => __( 'Heartbeat Editor Frequency (seconds)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 15,
				'sanitize'    => 'integer',
				'description' => __( 'How often the WordPress Heartbeat API runs in the post editor.', 'wp-admin-health-suite' ),
				'min'         => 15,
				'max'         => 120,
			),
			'heartbeat_frontend'         => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Heartbeat on Frontend', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Allow the WordPress Heartbeat API to run on the frontend.', 'wp-admin-health-suite' ),
			),
			'query_logging_enabled'      => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Query Logging', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Log database queries for performance analysis.', 'wp-admin-health-suite' ),
			),
			'slow_query_threshold_ms'    => array(
				'section'     => 'performance',
				'title'       => __( 'Slow Query Threshold (ms)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 100,
				'sanitize'    => 'integer',
				'description' => __( 'Queries slower than this threshold will be flagged as slow.', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 500,
			),
			'plugin_profiling_enabled'   => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Plugin Profiling', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Profile plugin execution time and performance impact.', 'wp-admin-health-suite' ),
			),
		);
	}
}
