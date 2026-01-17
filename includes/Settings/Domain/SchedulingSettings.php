<?php
/**
 * Scheduling Settings
 *
 * Task scheduling settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SchedulingSettings
 *
 * Manages task scheduling settings.
 *
 * @since 1.2.0
 */
class SchedulingSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'scheduling';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'Scheduling', 'wp-admin-health-suite' ),
			'description' => __( 'Configure automated task scheduling.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		$timezone_label = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		if ( '' === $timezone_label ) {
			$timezone_label = 'UTC';
		}

		return array(
			'scheduler_enabled'             => array(
				'section'     => 'scheduling',
				'title'       => __( 'Enable Scheduler', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable automated task scheduling using Action Scheduler.', 'wp-admin-health-suite' ),
			),
			'enable_scheduled_db_cleanup'   => array(
				'section'     => 'scheduling',
				'title'       => __( 'Enable Database Cleanup Task', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable scheduled database cleanup (revisions, transients, etc.).', 'wp-admin-health-suite' ),
			),
			'enable_scheduled_media_scan'   => array(
				'section'     => 'scheduling',
				'title'       => __( 'Enable Media Scan Task', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable scheduled media library scanning.', 'wp-admin-health-suite' ),
			),
			'enable_scheduled_performance_check' => array(
				'section'     => 'scheduling',
				'title'       => __( 'Enable Performance Check Task', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable scheduled performance monitoring.', 'wp-admin-health-suite' ),
			),
			'database_cleanup_frequency'    => array(
				'section'     => 'scheduling',
				'title'       => __( 'Database Cleanup Frequency', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'weekly',
				'sanitize'    => 'select',
				'description' => __( 'How often to run automated database cleanup tasks.', 'wp-admin-health-suite' ),
				'options'     => array(
					'daily'    => __( 'Daily', 'wp-admin-health-suite' ),
					'weekly'   => __( 'Weekly', 'wp-admin-health-suite' ),
					'monthly'  => __( 'Monthly', 'wp-admin-health-suite' ),
					'disabled' => __( 'Disabled', 'wp-admin-health-suite' ),
				),
			),
			'media_scan_frequency'        => array(
				'section'     => 'scheduling',
				'title'       => __( 'Media Scan Frequency', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'weekly',
				'sanitize'    => 'select',
				'description' => __( 'How often to scan for unused media files.', 'wp-admin-health-suite' ),
				'options'     => array(
					'weekly'   => __( 'Weekly', 'wp-admin-health-suite' ),
					'monthly'  => __( 'Monthly', 'wp-admin-health-suite' ),
					'disabled' => __( 'Disabled', 'wp-admin-health-suite' ),
				),
			),
			'performance_check_frequency' => array(
				'section'     => 'scheduling',
				'title'       => __( 'Performance Check Frequency', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'daily',
				'sanitize'    => 'select',
				'description' => __( 'How often to run performance health checks.', 'wp-admin-health-suite' ),
				'options'     => array(
					'daily'  => __( 'Daily', 'wp-admin-health-suite' ),
					'weekly' => __( 'Weekly', 'wp-admin-health-suite' ),
				),
			),
			'preferred_time'              => array(
				'section'     => 'scheduling',
				'title'       => __( 'Preferred Time', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 2,
				'sanitize'    => 'integer',
				'min'         => 0,
				'max'         => 23,
				'description' => sprintf(
					/* translators: %s: Site timezone string (e.g., "America/New_York" or "+02:00"). */
					__( 'Preferred hour (0-23) to run scheduled tasks in the site timezone (%s).', 'wp-admin-health-suite' ),
					$timezone_label
				),
				'options'     => array(
					0  => __( '12:00 AM', 'wp-admin-health-suite' ),
					1  => __( '1:00 AM', 'wp-admin-health-suite' ),
					2  => __( '2:00 AM', 'wp-admin-health-suite' ),
					3  => __( '3:00 AM', 'wp-admin-health-suite' ),
					4  => __( '4:00 AM', 'wp-admin-health-suite' ),
					5  => __( '5:00 AM', 'wp-admin-health-suite' ),
					6  => __( '6:00 AM', 'wp-admin-health-suite' ),
					7  => __( '7:00 AM', 'wp-admin-health-suite' ),
					8  => __( '8:00 AM', 'wp-admin-health-suite' ),
					9  => __( '9:00 AM', 'wp-admin-health-suite' ),
					10 => __( '10:00 AM', 'wp-admin-health-suite' ),
					11 => __( '11:00 AM', 'wp-admin-health-suite' ),
					12 => __( '12:00 PM', 'wp-admin-health-suite' ),
					13 => __( '1:00 PM', 'wp-admin-health-suite' ),
					14 => __( '2:00 PM', 'wp-admin-health-suite' ),
					15 => __( '3:00 PM', 'wp-admin-health-suite' ),
					16 => __( '4:00 PM', 'wp-admin-health-suite' ),
					17 => __( '5:00 PM', 'wp-admin-health-suite' ),
					18 => __( '6:00 PM', 'wp-admin-health-suite' ),
					19 => __( '7:00 PM', 'wp-admin-health-suite' ),
					20 => __( '8:00 PM', 'wp-admin-health-suite' ),
					21 => __( '9:00 PM', 'wp-admin-health-suite' ),
					22 => __( '10:00 PM', 'wp-admin-health-suite' ),
					23 => __( '11:00 PM', 'wp-admin-health-suite' ),
				),
			),
			'notification_on_completion'  => array(
				'section'     => 'scheduling',
				'title'       => __( 'Notification on Completion', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Send email notification when scheduled tasks complete.', 'wp-admin-health-suite' ),
			),
		);
	}
}
