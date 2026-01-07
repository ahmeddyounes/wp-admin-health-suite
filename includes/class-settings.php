<?php
/**
 * Settings Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles plugin settings using WordPress Settings API.
 */
class Settings {

	/**
	 * Option name for storing plugin settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpha_settings';

	/**
	 * Settings sections.
	 *
	 * @var array
	 */
	private $sections;

	/**
	 * Settings fields.
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_sections();
		$this->define_fields();
		$this->init_hooks();
	}

	/**
	 * Define settings sections.
	 *
	 * @return void
	 */
	private function define_sections() {
		$this->sections = array(
			'general'          => array(
				'title'       => __( 'General Settings', 'wp-admin-health-suite' ),
				'description' => __( 'Configure general plugin behavior.', 'wp-admin-health-suite' ),
			),
			'database_cleanup' => array(
				'title'       => __( 'Database Cleanup', 'wp-admin-health-suite' ),
				'description' => __( 'Configure database cleanup options.', 'wp-admin-health-suite' ),
			),
			'media_audit'      => array(
				'title'       => __( 'Media Audit', 'wp-admin-health-suite' ),
				'description' => __( 'Configure media audit settings.', 'wp-admin-health-suite' ),
			),
			'performance'      => array(
				'title'       => __( 'Performance', 'wp-admin-health-suite' ),
				'description' => __( 'Configure performance monitoring.', 'wp-admin-health-suite' ),
			),
			'scheduling'       => array(
				'title'       => __( 'Scheduling', 'wp-admin-health-suite' ),
				'description' => __( 'Configure automated task scheduling.', 'wp-admin-health-suite' ),
			),
			'advanced'         => array(
				'title'       => __( 'Advanced', 'wp-admin-health-suite' ),
				'description' => __( 'Advanced settings for power users.', 'wp-admin-health-suite' ),
			),
		);
	}

	/**
	 * Define settings fields.
	 *
	 * @return void
	 */
	private function define_fields() {
		$this->fields = array(
			// General settings.
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
			'enable_dashboard_widget' => array(
				'section'  => 'general',
				'title'    => __( 'Enable Dashboard Widget', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'admin_bar_menu'          => array(
				'section'  => 'general',
				'title'    => __( 'Show Admin Bar Menu', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'notification_email'      => array(
				'section'     => 'general',
				'title'       => __( 'Notification Email', 'wp-admin-health-suite' ),
				'type'        => 'email',
				'default'     => '',
				'sanitize'    => 'email',
				'description' => __( 'Email address for health notifications.', 'wp-admin-health-suite' ),
			),
			'enable_logging'          => array(
				'section'     => 'general',
				'title'       => __( 'Enable Logging', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable system logging for monitoring and debugging.', 'wp-admin-health-suite' ),
			),
			'log_retention_days'      => array(
				'section'     => 'general',
				'title'       => __( 'Log Retention Days', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 7,
				'sanitize'    => 'integer',
				'description' => __( 'Days to retain logs before automatic cleanup (7-90).', 'wp-admin-health-suite' ),
				'min'         => 7,
				'max'         => 90,
			),
			'delete_data_on_uninstall' => array(
				'section'     => 'general',
				'title'       => __( 'Delete Data on Uninstall', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Remove all plugin data when uninstalling.', 'wp-admin-health-suite' ),
			),
			'health_score_threshold'  => array(
				'section'     => 'general',
				'title'       => __( 'Health Score Threshold', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 70,
				'sanitize'    => 'integer',
				'description' => __( 'Minimum health score before warnings (0-100).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 100,
			),

			// Database cleanup settings.
			'cleanup_revisions'         => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Post Revisions', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_auto_drafts'       => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Auto-Drafts', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_trashed_posts'     => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Trashed Posts', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_spam_comments'     => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Spam Comments', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_trashed_comments'  => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Trashed Comments', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_expired_transients' => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Expired Transients', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'cleanup_orphaned_metadata' => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Orphaned Metadata', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'revisions_to_keep'          => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Revisions to Keep', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Number of revisions to keep per post (0-50, 0 = delete all).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 50,
			),
			'auto_clean_spam_days'       => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Auto Clean Spam Comments (days)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Auto-delete spam comments older than X days (0-365, 0 = disabled).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 365,
			),
			'auto_clean_trash_days'      => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Auto Clean Trash (days)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Auto-delete trashed content older than X days (0-365, 0 = disabled).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 365,
			),
			'excluded_transient_prefixes' => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Excluded Transient Prefixes', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'textarea',
				'description' => __( 'Transient prefixes to exclude from cleanup (one per line).', 'wp-admin-health-suite' ),
			),
			'optimize_tables_weekly'     => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Optimize Tables Weekly', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Automatically optimize database tables weekly.', 'wp-admin-health-suite' ),
			),
			'orphaned_cleanup_enabled'   => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Enable Orphaned Cleanup', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Automatically clean orphaned metadata during scheduled tasks.', 'wp-admin-health-suite' ),
			),

			// Media audit settings.
			'scan_unused_media'         => array(
				'section'  => 'media_audit',
				'title'    => __( 'Scan for Unused Media', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'media_retention_days'      => array(
				'section'     => 'media_audit',
				'title'       => __( 'Media Retention Days', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 30,
				'sanitize'    => 'integer',
				'description' => __( 'Days to retain deleted media before permanent removal.', 'wp-admin-health-suite' ),
				'min'         => 1,
				'max'         => 365,
			),
			'exclude_media_types'       => array(
				'section'     => 'media_audit',
				'title'       => __( 'Exclude Media Types', 'wp-admin-health-suite' ),
				'type'        => 'text',
				'default'     => '',
				'sanitize'    => 'text',
				'description' => __( 'Comma-separated list of mime types to exclude (e.g., image/svg+xml, application/pdf).', 'wp-admin-health-suite' ),
			),
			'unused_media_scan_depth'   => array(
				'section'     => 'media_audit',
				'title'       => __( 'Unused Media Scan Depth', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'posts_only',
				'sanitize'    => 'select',
				'options'     => array(
					'posts_only'  => __( 'Posts Only', 'wp-admin-health-suite' ),
					'all_content' => __( 'All Content', 'wp-admin-health-suite' ),
					'deep_scan'   => __( 'Deep Scan', 'wp-admin-health-suite' ),
				),
				'description' => __( 'Determines how thoroughly to scan for media usage. Posts Only checks posts/pages, All Content includes custom post types, Deep Scan checks all database tables.', 'wp-admin-health-suite' ),
			),
			'large_file_threshold_kb'   => array(
				'section'     => 'media_audit',
				'title'       => __( 'Large File Threshold (KB)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 1000,
				'sanitize'    => 'integer',
				'description' => __( 'Files larger than this size will be flagged as large files.', 'wp-admin-health-suite' ),
				'min'         => 100,
				'max'         => 5000,
			),
			'duplicate_detection_method' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Duplicate Detection Method', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'hash',
				'sanitize'    => 'select',
				'options'     => array(
					'hash'     => __( 'Hash', 'wp-admin-health-suite' ),
					'filename' => __( 'Filename', 'wp-admin-health-suite' ),
					'both'     => __( 'Both', 'wp-admin-health-suite' ),
				),
				'description' => __( 'Method used to detect duplicate media files. Hash compares file contents, Filename compares names, Both uses both methods.', 'wp-admin-health-suite' ),
			),
			'excluded_media_ids'        => array(
				'section'     => 'media_audit',
				'title'       => __( 'Excluded Media IDs', 'wp-admin-health-suite' ),
				'type'        => 'text',
				'default'     => '',
				'sanitize'    => 'text',
				'description' => __( 'Comma-separated list of media IDs to exclude from audits (e.g., 123, 456, 789).', 'wp-admin-health-suite' ),
			),
			'media_trash_retention_days' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Media Trash Retention Days', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 30,
				'sanitize'    => 'integer',
				'description' => __( 'Number of days to keep media in trash before permanent deletion.', 'wp-admin-health-suite' ),
				'min'         => 7,
				'max'         => 90,
			),
			'scan_acf_fields'           => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan ACF Fields', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include Advanced Custom Fields (ACF) when scanning for media usage.', 'wp-admin-health-suite' ),
			),
			'scan_elementor'            => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan Elementor', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include Elementor page builder content when scanning for media usage.', 'wp-admin-health-suite' ),
			),
			'scan_woocommerce'          => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan WooCommerce', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include WooCommerce product images and galleries when scanning for media usage.', 'wp-admin-health-suite' ),
			),

			// Performance settings.
			'enable_query_monitoring'      => array(
				'section'  => 'performance',
				'title'    => __( 'Enable Query Monitoring', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'slow_query_threshold'         => array(
				'section'     => 'performance',
				'title'       => __( 'Slow Query Threshold (ms)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 500,
				'sanitize'    => 'integer',
				'description' => __( 'Queries taking longer than this will be flagged.', 'wp-admin-health-suite' ),
				'min'         => 1,
				'max'         => 10000,
			),
			'enable_ajax_monitoring'       => array(
				'section'  => 'performance',
				'title'    => __( 'Enable AJAX Monitoring', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'heartbeat_admin_frequency'    => array(
				'section'     => 'performance',
				'title'       => __( 'Heartbeat Admin Frequency (seconds)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 60,
				'sanitize'    => 'integer',
				'description' => __( 'How often the WordPress Heartbeat API runs in the admin area (15-120 seconds).', 'wp-admin-health-suite' ),
				'min'         => 15,
				'max'         => 120,
			),
			'heartbeat_editor_frequency'   => array(
				'section'     => 'performance',
				'title'       => __( 'Heartbeat Editor Frequency (seconds)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 15,
				'sanitize'    => 'integer',
				'description' => __( 'How often the WordPress Heartbeat API runs in the post editor (15-120 seconds).', 'wp-admin-health-suite' ),
				'min'         => 15,
				'max'         => 120,
			),
			'heartbeat_frontend'           => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Heartbeat on Frontend', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Allow the WordPress Heartbeat API to run on the frontend.', 'wp-admin-health-suite' ),
			),
			'query_logging_enabled'        => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Query Logging', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Log database queries for performance analysis.', 'wp-admin-health-suite' ),
			),
			'slow_query_threshold_ms'      => array(
				'section'     => 'performance',
				'title'       => __( 'Slow Query Threshold (ms)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 100,
				'sanitize'    => 'integer',
				'description' => __( 'Queries slower than this threshold will be flagged as slow (10-500 ms).', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 500,
			),
			'plugin_profiling_enabled'     => array(
				'section'     => 'performance',
				'title'       => __( 'Enable Plugin Profiling', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Profile plugin execution time and performance impact.', 'wp-admin-health-suite' ),
			),

			// Scheduling settings.
			'scheduler_enabled'              => array(
				'section'     => 'scheduling',
				'title'       => __( 'Enable Scheduler', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable automated task scheduling using Action Scheduler.', 'wp-admin-health-suite' ),
			),
			'database_cleanup_frequency'     => array(
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
			'media_scan_frequency'           => array(
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
			'performance_check_frequency'    => array(
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
			'preferred_time'                 => array(
				'section'     => 'scheduling',
				'title'       => __( 'Preferred Time', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 2,
				'sanitize'    => 'integer',
				'description' => __( 'Preferred hour (0-23) to run scheduled tasks.', 'wp-admin-health-suite' ),
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
			'notification_on_completion'     => array(
				'section'     => 'scheduling',
				'title'       => __( 'Notification on Completion', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Send email notification when scheduled tasks complete.', 'wp-admin-health-suite' ),
			),

			// Advanced settings.
			'enable_rest_api'           => array(
				'section'     => 'advanced',
				'title'       => __( 'Enable REST API', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable REST API endpoints for external integrations.', 'wp-admin-health-suite' ),
			),
			'rest_api_rate_limit'       => array(
				'section'     => 'advanced',
				'title'       => __( 'REST API Rate Limit (requests/minute)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 60,
				'sanitize'    => 'integer',
				'description' => __( 'Maximum number of API requests allowed per minute (10-120).', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 120,
			),
			'debug_mode'                => array(
				'section'     => 'advanced',
				'title'       => __( 'Debug Mode', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable detailed logging and visible query times for debugging.', 'wp-admin-health-suite' ),
			),
			'custom_css'                => array(
				'section'     => 'advanced',
				'title'       => __( 'Custom Admin CSS', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'textarea',
				'description' => __( 'Custom CSS to apply to admin pages. Use this to customize the plugin appearance.', 'wp-admin-health-suite' ),
			),
			'safe_mode'                 => array(
				'section'     => 'advanced',
				'title'       => __( 'Safe Mode', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'When enabled, all delete/clean endpoints return preview only without modifying data.', 'wp-admin-health-suite' ),
			),
			'batch_size'                => array(
				'section'     => 'advanced',
				'title'       => __( 'Batch Processing Size', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 100,
				'sanitize'    => 'integer',
				'description' => __( 'Number of items to process in each batch.', 'wp-admin-health-suite' ),
				'min'         => 10,
				'max'         => 1000,
			),
		);
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wpha_export_settings', array( $this, 'export_settings' ) );
		add_action( 'admin_post_wpha_import_settings', array( $this, 'import_settings' ) );
		add_action( 'admin_post_wpha_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'admin_post_wpha_reset_section', array( $this, 'reset_section' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'handle_scheduling_update' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'output_custom_css' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Register the main settings option.
		register_setting(
			'wpha_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		// Add settings sections.
		foreach ( $this->sections as $section_id => $section ) {
			add_settings_section(
				'wpha_section_' . $section_id,
				$section['title'],
				function() use ( $section ) {
					if ( ! empty( $section['description'] ) ) {
						echo '<p>' . esc_html( $section['description'] ) . '</p>';
					}
				},
				'wpha_settings'
			);
		}

		// Add settings fields.
		foreach ( $this->fields as $field_id => $field ) {
			add_settings_field(
				'wpha_field_' . $field_id,
				$field['title'],
				array( $this, 'render_field' ),
				'wpha_settings',
				'wpha_section_' . $field['section'],
				array(
					'id'    => $field_id,
					'field' => $field,
				)
			);
		}
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( $args ) {
		$field_id = $args['id'];
		$field    = $args['field'];
		$settings = $this->get_settings();
		$value    = isset( $settings[ $field_id ] ) ? $settings[ $field_id ] : $field['default'];

		$name = self::OPTION_NAME . '[' . $field_id . ']';
		$id   = 'wpha_' . $field_id;

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" id="%s" name="%s" value="1" %s />',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, true, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					isset( $field['min'] ) ? esc_attr( $field['min'] ) : '',
					isset( $field['max'] ) ? esc_attr( $field['max'] ) : ''
				);
				break;

			case 'text':
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'select':
				printf(
					'<select id="%s" name="%s">',
					esc_attr( $id ),
					esc_attr( $name )
				);
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="5" class="large-text">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( $field['description'] )
			);
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		foreach ( $this->fields as $field_id => $field ) {
			$value = isset( $input[ $field_id ] ) ? $input[ $field_id ] : $field['default'];

			switch ( $field['sanitize'] ) {
				case 'boolean':
					$sanitized[ $field_id ] = (bool) $value;
					break;

				case 'integer':
					$sanitized[ $field_id ] = absint( $value );
					if ( isset( $field['min'] ) && $sanitized[ $field_id ] < $field['min'] ) {
						$sanitized[ $field_id ] = $field['min'];
					}
					if ( isset( $field['max'] ) && $sanitized[ $field_id ] > $field['max'] ) {
						$sanitized[ $field_id ] = $field['max'];
					}
					break;

				case 'text':
					$sanitized[ $field_id ] = sanitize_text_field( $value );
					break;

				case 'email':
					$sanitized[ $field_id ] = sanitize_email( $value );
					// Validate email format - if invalid, use default.
					if ( ! empty( $sanitized[ $field_id ] ) && ! is_email( $sanitized[ $field_id ] ) ) {
						$sanitized[ $field_id ] = $field['default'];
					}
					break;

				case 'select':
					if ( isset( $field['options'] ) && array_key_exists( $value, $field['options'] ) ) {
						$sanitized[ $field_id ] = $value;
					} else {
						$sanitized[ $field_id ] = $field['default'];
					}
					break;

				case 'textarea':
					// Special handling for custom_css field.
					if ( 'custom_css' === $field_id ) {
						$sanitized[ $field_id ] = $this->sanitize_css( $value );
					} else {
						$sanitized[ $field_id ] = sanitize_textarea_field( $value );
					}
					break;

				default:
					$sanitized[ $field_id ] = $value;
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $this->get_default_settings() );
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		$settings = $this->get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		if ( null !== $default ) {
			return $default;
		}
		return isset( $this->fields[ $key ]['default'] ) ? $this->fields[ $key ]['default'] : null;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		$defaults = array();
		foreach ( $this->fields as $field_id => $field ) {
			$defaults[ $field_id ] = $field['default'];
		}
		return $defaults;
	}

	/**
	 * Export settings as JSON.
	 *
	 * @return void
	 */
	public function export_settings() {
		// Check user permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' ) );
		}

		check_admin_referer( 'wpha_export_settings' );

		$settings = $this->get_settings();

		// Prepare JSON export.
		$export = array(
			'version'   => WP_ADMIN_HEALTH_VERSION,
			'timestamp' => current_time( 'mysql' ),
			'settings'  => $settings,
		);

		$json = wp_json_encode( $export, JSON_PRETTY_PRINT );

		// Set headers for download.
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpha-settings-' . gmdate( 'Y-m-d-His' ) . '.json' );
		header( 'Expires: 0' );

		echo $json;
		exit;
	}

	/**
	 * Import settings from JSON.
	 *
	 * @return void
	 */
	public function import_settings() {
		// Check user permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' ) );
		}

		check_admin_referer( 'wpha_import_settings' );

		// Check if file was uploaded.
		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No file uploaded.', 'wp-admin-health-suite' ) );
		}

		// Read and decode JSON.
		$json = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['settings'] ) ) {
			wp_die( esc_html__( 'Invalid settings file.', 'wp-admin-health-suite' ) );
		}

		// Sanitize and update settings.
		$sanitized = $this->sanitize_settings( $data['settings'] );
		update_option( self::OPTION_NAME, $sanitized );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'admin-health-settings',
					'message' => 'imported',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reset settings to defaults.
	 *
	 * @return void
	 */
	public function reset_settings() {
		// Check user permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' ) );
		}

		check_admin_referer( 'wpha_reset_settings' );

		// Reset to default settings.
		update_option( self::OPTION_NAME, $this->get_default_settings() );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'admin-health-settings',
					'message' => 'reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reset settings for a specific section to defaults.
	 *
	 * @return void
	 */
	public function reset_section() {
		// Check user permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-admin-health-suite' ) );
		}

		check_admin_referer( 'wpha_reset_section' );

		// Get the section to reset.
		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';

		// Validate section exists.
		if ( empty( $section ) || ! isset( $this->sections[ $section ] ) ) {
			wp_die( esc_html__( 'Invalid section.', 'wp-admin-health-suite' ) );
		}

		// Get current settings.
		$current_settings = $this->get_settings();
		$default_settings = $this->get_default_settings();

		// Reset only fields in this section.
		foreach ( $this->fields as $field_id => $field ) {
			if ( $field['section'] === $section ) {
				$current_settings[ $field_id ] = $default_settings[ $field_id ];
			}
		}

		// Update settings.
		update_option( self::OPTION_NAME, $current_settings );

		// Get redirect URL.
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : admin_url( 'admin.php?page=admin-health-settings' );

		// Add success message.
		$redirect = add_query_arg( 'message', 'reset', $redirect );

		// Redirect back with success message.
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle scheduling settings updates.
	 *
	 * This method is called when settings are updated. It creates or updates
	 * Action Scheduler tasks based on the new settings.
	 *
	 * @param array $old_value Previous settings values.
	 * @param array $new_value New settings values.
	 * @return void
	 */
	public function handle_scheduling_update( $old_value, $new_value ) {
		// Only proceed if scheduler is enabled.
		if ( empty( $new_value['scheduler_enabled'] ) ) {
			// If scheduler is disabled, unschedule all tasks.
			$this->unschedule_all_tasks();
			return;
		}

		// Get preferred time.
		$preferred_hour = isset( $new_value['preferred_time'] ) ? absint( $new_value['preferred_time'] ) : 2;

		// Calculate next run time based on preferred hour.
		$next_run = $this->calculate_next_run_time( $preferred_hour );

		// Schedule database cleanup.
		$this->schedule_task(
			'wpha_database_cleanup',
			$new_value['database_cleanup_frequency'] ?? 'weekly',
			$next_run
		);

		// Schedule media scan.
		$this->schedule_task(
			'wpha_media_scan',
			$new_value['media_scan_frequency'] ?? 'weekly',
			$next_run
		);

		// Schedule performance check.
		$this->schedule_task(
			'wpha_performance_check',
			$new_value['performance_check_frequency'] ?? 'daily',
			$next_run
		);
	}

	/**
	 * Schedule a task using Action Scheduler or WP-Cron.
	 *
	 * @param string $hook Hook name for the scheduled action.
	 * @param string $frequency Frequency (daily/weekly/monthly/disabled).
	 * @param int    $next_run Timestamp for next run.
	 * @return void
	 */
	private function schedule_task( $hook, $frequency, $next_run ) {
		// If disabled, unschedule and return.
		if ( 'disabled' === $frequency ) {
			$this->unschedule_task( $hook );
			return;
		}

		// Calculate interval in seconds.
		$interval = $this->get_interval_seconds( $frequency );
		if ( ! $interval ) {
			return;
		}

		// Try to use Action Scheduler first.
		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_unschedule_all_actions' ) ) {
			// Unschedule old actions.
			as_unschedule_all_actions( $hook, array(), 'wpha_scheduling' );

			// Schedule new recurring action.
			as_schedule_recurring_action(
				$next_run,
				$interval,
				$hook,
				array(),
				'wpha_scheduling'
			);
		} else {
			// Fallback to WP-Cron.
			// Unschedule existing events.
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}

			// Schedule new event.
			wp_schedule_event( $next_run, $this->get_cron_schedule_name( $frequency ), $hook );
		}
	}

	/**
	 * Unschedule a specific task.
	 *
	 * @param string $hook Hook name for the scheduled action.
	 * @return void
	 */
	private function unschedule_task( $hook ) {
		// Try Action Scheduler first.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), 'wpha_scheduling' );
		}

		// Also clear WP-Cron.
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	/**
	 * Unschedule all scheduled tasks.
	 *
	 * @return void
	 */
	private function unschedule_all_tasks() {
		$hooks = array(
			'wpha_database_cleanup',
			'wpha_media_scan',
			'wpha_performance_check',
		);

		foreach ( $hooks as $hook ) {
			$this->unschedule_task( $hook );
		}
	}

	/**
	 * Calculate next run time based on preferred hour.
	 *
	 * @param int $preferred_hour Preferred hour (0-23).
	 * @return int Timestamp for next run.
	 */
	private function calculate_next_run_time( $preferred_hour ) {
		$now          = current_time( 'timestamp' );
		$today        = strtotime( 'today', $now );
		$preferred    = $today + ( $preferred_hour * HOUR_IN_SECONDS );

		// If preferred time has passed today, schedule for tomorrow.
		if ( $preferred <= $now ) {
			$preferred = strtotime( '+1 day', $preferred );
		}

		return $preferred;
	}

	/**
	 * Get interval in seconds for a frequency.
	 *
	 * @param string $frequency Frequency (daily/weekly/monthly).
	 * @return int|false Interval in seconds or false if invalid.
	 */
	private function get_interval_seconds( $frequency ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return isset( $intervals[ $frequency ] ) ? $intervals[ $frequency ] : false;
	}

	/**
	 * Get WP-Cron schedule name for a frequency.
	 *
	 * @param string $frequency Frequency (daily/weekly/monthly).
	 * @return string WP-Cron schedule name.
	 */
	private function get_cron_schedule_name( $frequency ) {
		$schedules = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);

		return isset( $schedules[ $frequency ] ) ? $schedules[ $frequency ] : 'daily';
	}

	/**
	 * Send notification on task completion.
	 *
	 * @param string $task_name Name of the completed task.
	 * @param array  $result Task result data.
	 * @return void
	 */
	public function send_completion_notification( $task_name, $result = array() ) {
		// Check if notifications are enabled.
		$settings = $this->get_settings();
		if ( empty( $settings['notification_on_completion'] ) ) {
			return;
		}

		// Get notification email.
		$email = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		if ( ! is_email( $email ) ) {
			return;
		}

		// Prepare email content.
		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: 1: Site name, 2: Task name */
			__( '[%1$s] Scheduled Task Completed: %2$s', 'wp-admin-health-suite' ),
			$site_name,
			$task_name
		);

		$message = sprintf(
			/* translators: 1: Task name, 2: Site URL */
			__( 'The scheduled task "%1$s" has completed on %2$s.', 'wp-admin-health-suite' ),
			$task_name,
			home_url()
		);

		$message .= "\n\n";

		// Add result details if available.
		if ( ! empty( $result ) ) {
			$message .= __( 'Task Results:', 'wp-admin-health-suite' ) . "\n";
			foreach ( $result as $key => $value ) {
				$message .= sprintf( "- %s: %s\n", ucfirst( str_replace( '_', ' ', $key ) ), $value );
			}
			$message .= "\n";
		}

		$message .= sprintf(
			/* translators: Dashboard URL */
			__( 'View details: %s', 'wp-admin-health-suite' ),
			admin_url( 'admin.php?page=admin-health-dashboard' )
		);

		// Send email.
		wp_mail( $email, $subject, $message );
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		return $this->sections;
	}

	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Sanitize CSS input.
	 *
	 * @param string $css CSS code to sanitize.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_css( $css ) {
		// Strip tags but preserve CSS content.
		$css = wp_strip_all_tags( $css );

		// Remove any potentially dangerous content.
		// This is a basic sanitization - in production, consider using wp_kses() with CSS rules.
		$css = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $css );
		$css = preg_replace( '/javascript:/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/import\s+/i', '', $css );

		return trim( $css );
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * @return bool
	 */
	public function is_safe_mode_enabled() {
		return (bool) $this->get_setting( 'safe_mode', false );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode_enabled() {
		return (bool) $this->get_setting( 'debug_mode', false );
	}

	/**
	 * Check if REST API is enabled.
	 *
	 * @return bool
	 */
	public function is_rest_api_enabled() {
		return (bool) $this->get_setting( 'enable_rest_api', true );
	}

	/**
	 * Get REST API rate limit.
	 *
	 * @return int Requests per minute.
	 */
	public function get_rest_api_rate_limit() {
		return absint( $this->get_setting( 'rest_api_rate_limit', 60 ) );
	}

	/**
	 * Output custom CSS in admin head.
	 *
	 * @return void
	 */
	public function output_custom_css() {
		$custom_css = $this->get_setting( 'custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			echo "\n<style type=\"text/css\" id=\"wpha-custom-css\">\n";
			// CSS is already sanitized in sanitize_css(), output directly.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $custom_css;
			echo "\n</style>\n";
		}
	}
}
