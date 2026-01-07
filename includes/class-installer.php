<?php
/**
 * Installer Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles plugin installation, upgrades, and database setup.
 */
class Installer {

	/**
	 * Option name for storing plugin version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'wpha_version';

	/**
	 * Run installation process.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();
		self::set_version();
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create scan history table.
		$scan_history_table = $prefix . 'wpha_scan_history';
		$sql_scan_history   = "CREATE TABLE {$scan_history_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_type varchar(50) NOT NULL,
			items_found int(11) unsigned NOT NULL DEFAULT 0,
			items_cleaned int(11) unsigned NOT NULL DEFAULT 0,
			bytes_freed bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_type (scan_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_scan_history );

		// Create scheduled tasks table.
		$scheduled_tasks_table = $prefix . 'wpha_scheduled_tasks';
		$sql_scheduled_tasks   = "CREATE TABLE {$scheduled_tasks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			task_type varchar(50) NOT NULL,
			frequency varchar(20) NOT NULL,
			last_run datetime DEFAULT NULL,
			next_run datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			settings longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY task_type (task_type),
			KEY status (status),
			KEY next_run (next_run)
		) {$charset_collate};";

		dbDelta( $sql_scheduled_tasks );

		// Create deleted media table.
		$deleted_media_table = $prefix . 'wpha_deleted_media';
		$sql_deleted_media   = "CREATE TABLE {$deleted_media_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			file_path text NOT NULL,
			metadata longtext NOT NULL,
			deleted_at datetime NOT NULL,
			permanent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY deleted_at (deleted_at),
			KEY permanent_at (permanent_at)
		) {$charset_collate};";

		dbDelta( $sql_deleted_media );

		// Create query log table.
		$query_log_table = $prefix . 'wpha_query_log';
		$sql_query_log   = "CREATE TABLE {$query_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sql text NOT NULL,
			time_ms decimal(10,2) NOT NULL,
			caller varchar(255) NOT NULL,
			component varchar(100) NOT NULL,
			is_duplicate tinyint(1) NOT NULL DEFAULT 0,
			needs_index tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY component (component),
			KEY is_duplicate (is_duplicate),
			KEY needs_index (needs_index),
			KEY created_at (created_at),
			KEY time_ms (time_ms)
		) {$charset_collate};";

		dbDelta( $sql_query_log );
	}

	/**
	 * Set plugin version in options.
	 *
	 * @return void
	 */
	private static function set_version() {
		update_option( self::VERSION_OPTION, WP_ADMIN_HEALTH_VERSION );
	}

	/**
	 * Check if upgrade is needed and run upgrade routine.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$current_version = get_option( self::VERSION_OPTION );

		if ( version_compare( $current_version, WP_ADMIN_HEALTH_VERSION, '<' ) ) {
			self::upgrade( $current_version );
		}
	}

	/**
	 * Run upgrade routines based on version.
	 *
	 * @param string $from_version The version upgrading from.
	 * @return void
	 */
	private static function upgrade( $from_version ) {
		// Recreate tables to ensure they're up to date.
		self::create_tables();

		// Update version.
		self::set_version();

		// Hook for custom upgrade routines.
		do_action( 'wpha_upgraded', $from_version, WP_ADMIN_HEALTH_VERSION );
	}

	/**
	 * Remove all plugin data from database.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		$prefix = $wpdb->prefix;

		// Drop tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_scan_history" );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_scheduled_tasks" );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_deleted_media" );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_query_log" );

		// Delete options.
		delete_option( self::VERSION_OPTION );

		// Hook for custom uninstall routines.
		do_action( 'wpha_uninstalled' );
	}
}
