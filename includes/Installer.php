<?php
/**
 * Installer Class
 *
 * EDGE ADAPTER: This class uses static methods called during plugin activation and deactivation.
 * At activation time, the DI container may not be fully initialized yet. The class uses optional
 * service location via Plugin::get_instance()->get_container() with fallback to global $wpdb
 * to ensure database operations work in all contexts (activation, upgrade, uninstall).
 *
 * The service-locator pattern is necessary here because:
 * 1. WordPress activation hooks execute before the plugin's init() method
 * 2. Static methods cannot receive constructor-injected dependencies
 * 3. The Installer must function even if the container is unavailable
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

use WPAdminHealth\Settings\SettingsRegistry;
use WPAdminHealth\Settings\Domain\CoreSettings;
use WPAdminHealth\Settings\Domain\DatabaseSettings;
use WPAdminHealth\Settings\Domain\MediaSettings;
use WPAdminHealth\Settings\Domain\PerformanceSettings;
use WPAdminHealth\Settings\Domain\SchedulingSettings;
use WPAdminHealth\Settings\Domain\AdvancedSettings;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulingServiceInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles plugin installation, upgrades, and database setup.
 *
 * This is an edge adapter that uses optional service location because it operates
 * during WordPress lifecycle events where the DI container may not be available.
 * For normal application code, use ConnectionInterface via constructor injection.
 *
 * @since 1.0.0
 * @since 1.3.0 Added ConnectionInterface support with optional injection.
 */
class Installer {

	/**
	 * Option name for storing plugin version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'wpha_version';

	/**
	 * Database connection.
	 *
	 * @since 1.3.0
	 * @var ConnectionInterface|null
	 */
	private static ?ConnectionInterface $connection = null;

	/**
	 * Scheduling service.
	 *
	 * @since 2.0.0
	 * @var SchedulingServiceInterface|null
	 */
	private static ?SchedulingServiceInterface $scheduling_service = null;

	/**
	 * Set the database connection.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection instance.
	 * @return void
	 */
	public static function set_connection( ConnectionInterface $connection ): void {
		self::$connection = $connection;
	}

	/**
	 * Set the scheduling service.
	 *
	 * @since 2.0.0
	 *
	 * @param SchedulingServiceInterface $scheduling_service Scheduling service instance.
	 * @return void
	 */
	public static function set_scheduling_service( SchedulingServiceInterface $scheduling_service ): void {
		self::$scheduling_service = $scheduling_service;
	}

	/**
	 * Get the database connection.
	 *
	 * @since 1.3.0
	 *
	 * @return ConnectionInterface|null Database connection or null if not set.
	 */
	private static function get_connection(): ?ConnectionInterface {
		if ( null === self::$connection && class_exists( Plugin::class ) ) {
			$container = Plugin::get_instance()->get_container();
			if ( $container->has( ConnectionInterface::class ) ) {
				self::$connection = $container->get( ConnectionInterface::class );
			}
		}

		return self::$connection;
	}

	/**
	 * Get the scheduling service.
	 *
	 * @since 2.0.0
	 *
	 * @return SchedulingServiceInterface|null Scheduling service or null if not available.
	 */
	private static function get_scheduling_service(): ?SchedulingServiceInterface {
		if ( null === self::$scheduling_service && class_exists( Plugin::class ) ) {
			try {
				$container = Plugin::get_instance()->get_container();
				if ( $container && $container->has( SchedulingServiceInterface::class ) ) {
					self::$scheduling_service = $container->get( SchedulingServiceInterface::class );
				}
			} catch ( \Exception $e ) {
				// Container not available during activation, which is expected.
				return null;
			}
		}

		return self::$scheduling_service;
	}

	/**
	 * Run installation process.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function install() {
		// Security: Ensure user has capability to install plugins.
		// This check is for defense-in-depth since WordPress activation hooks
		// should already verify capabilities, but this method could be called directly.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::create_tables();
		$is_fresh_install = self::set_default_settings();
		self::set_version();

		// Schedule initial tasks on fresh install.
		if ( $is_fresh_install ) {
			self::schedule_initial_tasks();
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return void
	 */
	private static function create_tables() {
		$connection = self::get_connection();

		if ( $connection ) {
			$charset_collate = $connection->get_charset_collate();
			$prefix          = $connection->get_prefix();
		} else {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$prefix          = $wpdb->prefix;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create scan history table.
		$scan_history_table = $prefix . 'wpha_scan_history';
		$sql_scan_history   = "CREATE TABLE {$scan_history_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_type varchar(50) NOT NULL,
			items_found int(11) unsigned NOT NULL DEFAULT 0,
			items_cleaned int(11) unsigned NOT NULL DEFAULT 0,
			bytes_freed bigint(20) unsigned NOT NULL DEFAULT 0,
			metadata longtext DEFAULT NULL,
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

		// Create AJAX log table.
		$ajax_log_table = $prefix . 'wpha_ajax_log';
		$sql_ajax_log   = "CREATE TABLE {$ajax_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			action varchar(255) NOT NULL,
			execution_time decimal(10,2) NOT NULL,
			memory_used bigint(20) unsigned NOT NULL DEFAULT 0,
			user_role varchar(50) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY user_role (user_role),
			KEY created_at (created_at),
			KEY execution_time (execution_time)
		) {$charset_collate};";

		dbDelta( $sql_ajax_log );

		// Clear table existence cache after creating/updating tables.
		self::clear_table_checker_cache();
	}

	/**
	 * Clear the TableChecker cache after table creation/modification.
	 *
	 * This ensures that table existence checks return accurate results
	 * after install or upgrade operations.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	private static function clear_table_checker_cache(): void {
		// Try to get TableChecker from container and clear its cache.
		if ( class_exists( Plugin::class ) ) {
			try {
				$container = Plugin::get_instance()->get_container();
				if ( $container && $container->has( TableCheckerInterface::class ) ) {
					$table_checker = $container->get( TableCheckerInterface::class );
					$table_checker->clear_cache();
				}
			} catch ( \Exception $e ) {
				// TableChecker not available during activation, which is expected.
				// The in-memory cache will be empty for a fresh request anyway.
			}
		}

		/**
		 * Fires after plugin tables have been created or updated.
		 *
		 * Use this hook to invalidate any table-related caches.
		 *
		 * @since 1.4.0
		 *
		 * @hook wpha_tables_updated
		 */
		do_action( 'wpha_tables_updated' );
	}

	/**
	 * Set default settings on installation.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Migrated to use SettingsRegistry with domain settings.
	 *
	 * @return bool True if this is a fresh install (settings were created), false if settings already existed.
	 */
	private static function set_default_settings(): bool {
		// Only set defaults if settings don't exist yet.
		if ( false !== get_option( SettingsRegistry::OPTION_NAME ) ) {
			return false;
		}

		$registry = new SettingsRegistry();
		$registry->register( new CoreSettings() );
		$registry->register( new DatabaseSettings() );
		$registry->register( new MediaSettings() );
		$registry->register( new PerformanceSettings() );
		$registry->register( new SchedulingSettings() );
		$registry->register( new AdvancedSettings() );

		update_option( SettingsRegistry::OPTION_NAME, $registry->get_default_settings() );

		return true;
	}

	/**
	 * Set plugin version in options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function set_version() {
		update_option( self::VERSION_OPTION, WP_ADMIN_HEALTH_VERSION );
	}

	/**
	 * Schedule initial tasks on fresh install.
	 *
	 * Since update_option_{$option} hook doesn't fire when an option is first
	 * created (only when an existing option is updated), we need to manually
	 * schedule initial cron tasks based on default settings.
	 *
	 * This method delegates to SchedulingService when available, falling back
	 * to direct scheduling if the DI container isn't ready during activation.
	 *
	 * @since 1.2.0
	 * @since 2.0.0 Delegates to SchedulingService when available.
	 *
	 * @return void
	 */
	private static function schedule_initial_tasks(): void {
		// Ensure our custom schedules are available during activation/fresh install.
		self::register_custom_cron_schedules();

		// Try to use SchedulingService if available.
		$scheduling_service = self::get_scheduling_service();
		if ( null !== $scheduling_service ) {
			$scheduling_service->schedule_initial_tasks();
			return;
		}

		// Fallback: Direct scheduling if SchedulingService isn't available.
		// This can happen during activation when the container isn't fully initialized.
		self::schedule_initial_tasks_fallback();
	}

	/**
	 * Register custom cron schedules.
	 *
	 * Ensures weekly and monthly schedules are available during plugin activation.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private static function register_custom_cron_schedules(): void {
		add_filter(
			'cron_schedules',
			function ( array $schedules ): array {
				if ( ! isset( $schedules['weekly'] ) ) {
					$schedules['weekly'] = array(
						'interval' => WEEK_IN_SECONDS,
						'display'  => __( 'Once Weekly', 'wp-admin-health-suite' ),
					);
				}

				if ( ! isset( $schedules['monthly'] ) ) {
					$schedules['monthly'] = array(
						'interval' => 30 * DAY_IN_SECONDS,
						'display'  => __( 'Once Monthly', 'wp-admin-health-suite' ),
					);
				}

				return $schedules;
			}
		);
	}

	/**
	 * Fallback method for scheduling initial tasks directly.
	 *
	 * Used when SchedulingService is not available (e.g., during activation
	 * before the container is initialized).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private static function schedule_initial_tasks_fallback(): void {
		$settings = get_option( SettingsRegistry::OPTION_NAME, array() );

		// Only schedule if scheduler is enabled (default is true).
		if ( empty( $settings['scheduler_enabled'] ) ) {
			return;
		}

		$preferred_hour = isset( $settings['preferred_time'] ) ? absint( $settings['preferred_time'] ) : 2;
		$next_run       = self::calculate_next_run_time( $preferred_hour );

		// Task configuration: hook => ( setting_key, default_frequency ).
		$tasks = array(
			'wpha_database_cleanup'  => array(
				'enabled_key'   => 'enable_scheduled_db_cleanup',
				'frequency_key' => 'database_cleanup_frequency',
				'default_freq'  => 'weekly',
			),
			'wpha_media_scan'        => array(
				'enabled_key'   => 'enable_scheduled_media_scan',
				'frequency_key' => 'media_scan_frequency',
				'default_freq'  => 'weekly',
			),
			'wpha_performance_check' => array(
				'enabled_key'   => 'enable_scheduled_performance_check',
				'frequency_key' => 'performance_check_frequency',
				'default_freq'  => 'daily',
			),
		);

		foreach ( $tasks as $hook => $config ) {
			// Check if task is enabled (defaults are all true).
			$is_enabled = isset( $settings[ $config['enabled_key'] ] )
				? (bool) $settings[ $config['enabled_key'] ]
				: true;

			if ( ! $is_enabled ) {
				continue;
			}

			$frequency = $settings[ $config['frequency_key'] ] ?? $config['default_freq'];

			if ( 'disabled' === $frequency ) {
				continue;
			}

			self::schedule_single_task( $hook, $frequency, $next_run );
		}
	}

	/**
	 * Schedule a single cron task.
	 *
	 * @since 1.2.0
	 *
	 * @param string $hook      Cron hook name.
	 * @param string $frequency Schedule frequency (daily, weekly, monthly).
	 * @param int    $next_run  Timestamp for next run.
	 * @return void
	 */
	private static function schedule_single_task( string $hook, string $frequency, int $next_run ): void {
		// Use Action Scheduler if available, otherwise fall back to WP-Cron.
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$interval = self::get_interval_seconds( $frequency );
			if ( $interval ) {
				as_schedule_recurring_action( $next_run, $interval, $hook, array(), 'wpha_scheduling' );
			}
		} else {
			wp_schedule_event( $next_run, $frequency, $hook );
		}
	}

	/**
	 * Calculate the next run time based on preferred hour.
	 *
	 * @since 1.2.0
	 *
	 * @param int $preferred_hour Preferred hour (0-23).
	 * @return int Unix timestamp for next run.
	 */
	private static function calculate_next_run_time( int $preferred_hour ): int {
		$preferred_hour = min( 23, max( 0, $preferred_hour ) );

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$preferred = $now->setTime( $preferred_hour, 0, 0 );

		// If preferred time has passed today, schedule for tomorrow.
		if ( $preferred->getTimestamp() <= $now->getTimestamp() ) {
			$preferred = $preferred->modify( '+1 day' );
		}

		return $preferred->getTimestamp();
	}

	/**
	 * Get interval in seconds for a frequency.
	 *
	 * @since 1.2.0
	 *
	 * @param string $frequency Frequency name.
	 * @return int|false Interval in seconds, or false if invalid.
	 */
	private static function get_interval_seconds( string $frequency ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return $intervals[ $frequency ] ?? false;
	}

	/**
	 * Check if upgrade is needed and run upgrade routine.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @param string $from_version The version upgrading from.
	 * @return void
	 */
	private static function upgrade( $from_version ) {
		// Recreate tables to ensure they're up to date.
		self::create_tables();

		// Update version.
		self::set_version();

		/**
		 * Fires after plugin upgrade is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_upgraded
		 *
		 * @param {string} $from_version The version being upgraded from.
		 * @param {string} WP_ADMIN_HEALTH_VERSION The version being upgraded to.
		 */
		do_action( 'wpha_upgraded', $from_version, WP_ADMIN_HEALTH_VERSION );
	}

	/**
	 * Install plugin on a newly created site in a multisite network.
	 *
	 * @since 1.0.0
	 *
	 * @param int $blog_id The blog ID of the new site.
	 * @return void
	 */
	public static function install_on_new_site( $blog_id ) {
		if ( ! is_multisite() ) {
			return;
		}

		// Check if plugin is network activated.
		if ( ! \WPAdminHealth\Multisite::is_network_activated() ) {
			return;
		}

		// Switch to the new site and install.
		switch_to_blog( $blog_id );
		self::install();
		restore_current_blog();
	}

	/**
	 * Remove all plugin data from database.
	 *
	 * Data deletion only occurs if the 'delete_data_on_uninstall' setting is enabled.
	 * This prevents accidental data loss during uninstallation.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added safety check requiring explicit setting to delete data.
	 *
	 * @return void
	 */
	public static function uninstall() {
		// Security: Ensure user has capability to delete plugins.
		// This check is for defense-in-depth since WordPress uninstall hooks
		// should already verify capabilities, but this method could be called directly.
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		// Safety check: Only delete data if explicitly enabled in settings.
		// This prevents accidental data loss during uninstallation.
		$settings = get_option( SettingsRegistry::OPTION_NAME, array() );
		if ( empty( $settings['delete_data_on_uninstall'] ) ) {
			// Just clear scheduled events but preserve data for potential reinstallation.
			self::clear_scheduled_cron_events();
			return;
		}

		if ( is_multisite() ) {
			// Network-wide uninstall requires network admin capability.
			if ( ! current_user_can( 'manage_network' ) ) {
				return;
			}

			// Uninstall from all sites.
			$sites = get_sites( array( 'number' => 999 ) );
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				self::uninstall_single_site();
				restore_current_blog();
			}

			// Delete network options.
			delete_site_option( \WPAdminHealth\Multisite::NETWORK_SETTINGS_OPTION );
		} else {
			self::uninstall_single_site();
		}

		/**
		 * Fires after plugin uninstall is complete.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_uninstalled
		 */
		do_action( 'wpha_uninstalled' );
	}

	/**
	 * Uninstall plugin from a single site.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return void
	 */
	private static function uninstall_single_site() {
		$connection = self::get_connection();

		if ( $connection ) {
			$prefix = $connection->get_prefix();
		} else {
			global $wpdb;
			$prefix = $wpdb->prefix;
		}

		// Clear scheduled cron events.
		self::clear_scheduled_cron_events();

		// Clear transients (including task locks).
		self::clear_plugin_transients();

		// Drop tables.
		if ( $connection ) {
			$connection->query( "DROP TABLE IF EXISTS `{$prefix}wpha_scan_history`" );
			$connection->query( "DROP TABLE IF EXISTS `{$prefix}wpha_scheduled_tasks`" );
			$connection->query( "DROP TABLE IF EXISTS `{$prefix}wpha_deleted_media`" );
			$connection->query( "DROP TABLE IF EXISTS `{$prefix}wpha_query_log`" );
			$connection->query( "DROP TABLE IF EXISTS `{$prefix}wpha_ajax_log`" );
		} else {
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_scan_history" );
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_scheduled_tasks" );
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_deleted_media" );
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_query_log" );
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wpha_ajax_log" );
		}

		// Delete options.
		delete_option( self::VERSION_OPTION );
		delete_option( SettingsRegistry::OPTION_NAME );
	}

	/**
	 * Clear all scheduled cron events created by the plugin.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private static function clear_scheduled_cron_events() {
		// List of cron hooks registered by the plugin.
		$cron_hooks = array(
			'wpha_database_cleanup',
			'wpha_media_scan',
			'wpha_performance_check',
			'wpha_scheduled_task',
			'wpha_cleanup_deleted_media',
			'wpha_cleanup_query_log',
		);

		foreach ( $cron_hooks as $hook ) {
			// Get the timestamp of the next scheduled run.
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}

			// Clear all scheduled events for this hook (in case of multiple schedules).
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Clear all plugin transients.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return void
	 */
	private static function clear_plugin_transients() {
		$connection = self::get_connection();

		if ( $connection ) {
			$options_table = $connection->get_options_table();

			// Delete transients with our prefix.
			$query = $connection->prepare(
				"DELETE FROM `{$options_table}` WHERE option_name LIKE %s OR option_name LIKE %s",
				$connection->esc_like( '_transient_wpha_' ) . '%',
				$connection->esc_like( '_transient_timeout_wpha_' ) . '%'
			);
			if ( $query ) {
				$connection->query( $query );
			}

			// Also delete site transients in multisite.
			if ( is_multisite() ) {
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
						$wpdb->esc_like( '_site_transient_wpha_' ) . '%',
						$wpdb->esc_like( '_site_transient_timeout_wpha_' ) . '%'
					)
				);
			}
		} else {
			global $wpdb;

			// Delete transients with our prefix.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_wpha_' ) . '%',
					$wpdb->esc_like( '_transient_timeout_wpha_' ) . '%'
				)
			);

			// Also delete site transients in multisite.
			if ( is_multisite() ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
						$wpdb->esc_like( '_site_transient_wpha_' ) . '%',
						$wpdb->esc_like( '_site_transient_timeout_wpha_' ) . '%'
					)
				);
			}
		}
	}
}
