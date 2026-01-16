<?php
/**
 * Orphaned Tables Detector Class
 *
 * Detects and manages orphaned database tables that have the WordPress prefix
 * but are not part of core WordPress or active plugins.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Orphaned Tables class for detecting and managing orphaned database tables.
 *
 * @since 1.0.0
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface and CacheInterface.
 */
class OrphanedTables {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Cache service.
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 * @param CacheInterface      $cache      Cache service.
	 */
	public function __construct( ConnectionInterface $connection, CacheInterface $cache ) {
		$this->connection = $connection;
		$this->cache      = $cache;
	}

	/**
	 * Get all tables in the database with the WordPress prefix.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @return array Array of table names.
	 */
	public function get_all_wp_tables() {
		$prefix = $this->connection->get_prefix();

		// Get all tables in the database.
		$query = $this->connection->prepare(
			'SELECT TABLE_NAME
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME LIKE %s
			ORDER BY TABLE_NAME ASC',
			DB_NAME,
			$this->connection->esc_like( $prefix ) . '%'
		);

		if ( null === $query ) {
			return array();
		}

		return $this->connection->get_col( $query );
	}

	/**
	 * Get known WordPress core tables.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb, with caching.
	 *
	 * @return array Array of core table names.
	 */
	public function get_known_core_tables() {
		// Check cache first.
		$cache_key = 'wpha_core_tables';
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$prefix = $this->connection->get_prefix();

		// Use ConnectionInterface methods where available, prefix pattern for others.
		$core_tables = array(
			$this->connection->get_posts_table(),
			$this->connection->get_postmeta_table(),
			$this->connection->get_comments_table(),
			$this->connection->get_commentmeta_table(),
			$this->connection->get_terms_table(),
			$this->connection->get_termmeta_table(),
			$prefix . 'term_relationships',
			$prefix . 'term_taxonomy',
			$prefix . 'users',
			$prefix . 'usermeta',
			$prefix . 'links',
			$this->connection->get_options_table(),
		);

		// Add multisite tables if multisite is enabled.
		if ( is_multisite() ) {
			$core_tables[] = $prefix . 'blogs';
			$core_tables[] = $prefix . 'blogmeta';
			$core_tables[] = $prefix . 'site';
			$core_tables[] = $prefix . 'sitemeta';
			$core_tables[] = $prefix . 'sitecategories';
			$core_tables[] = $prefix . 'registration_log';
			$core_tables[] = $prefix . 'signups';
		}

		// Filter out any null values.
		$core_tables = array_filter( $core_tables );

		// Cache for 5 minutes.
		$this->cache->set( $cache_key, $core_tables, 300 );

		return $core_tables;
	}

	/**
	 * Get tables registered by active plugins.
	 *
	 * Identifies tables that belong to currently ACTIVE plugins only.
	 * Tables from deactivated plugins will NOT be included here, allowing
	 * them to be correctly identified as orphaned.
	 *
	 * Detection methods:
	 * 1. Check active plugin files for table name declarations
	 * 2. Match tables against patterns for known active plugins
	 * 3. Allow plugins to register their tables via filter
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 * @since 1.5.0 Only returns tables for ACTIVE plugins; deactivated plugin tables are now orphaned.
	 *
	 * @return array Array of plugin table names owned by active plugins.
	 */
	public function get_registered_plugin_tables() {
		$prefix = $this->connection->get_prefix();

		$plugin_tables = array();

		// Get all active plugins.
		$active_plugins = get_option( 'active_plugins', array() );

		// Add network-activated plugins if multisite.
		if ( is_multisite() ) {
			$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network_plugins ) ) {
				$active_plugins = array_merge( $active_plugins, array_keys( $network_plugins ) );
			}
		}

		// Map of plugin slugs to their table patterns.
		// Only tables matching patterns for ACTIVE plugins will be protected.
		$plugin_table_mapping = array(
			// WooCommerce tables.
			'woocommerce/woocommerce.php'              => array( $prefix . 'wc_', $prefix . 'woocommerce_' ),
			// Contact Form 7.
			'contact-form-7/wp-contact-form-7.php'     => array( $prefix . 'cf7_', $prefix . 'contact_form_7_' ),
			// Yoast SEO.
			'wordpress-seo/wp-seo.php'                 => array( $prefix . 'yoast_' ),
			'wordpress-seo-premium/wp-seo-premium.php' => array( $prefix . 'yoast_' ),
			// WP Mail SMTP.
			'wp-mail-smtp/wp_mail_smtp.php'            => array( $prefix . 'wpmailsmtp_' ),
			'wp-mail-smtp-pro/wp_mail_smtp.php'        => array( $prefix . 'wpmailsmtp_' ),
			// Wordfence.
			'wordfence/wordfence.php'                  => array(
				$prefix . 'wfconfig',
				$prefix . 'wfblocks',
				$prefix . 'wflogins',
				$prefix . 'wffilechanges',
				$prefix . 'wfissues',
				$prefix . 'wfpendingissues',
				$prefix . 'wfknownfilelist',
				$prefix . 'wfsnipcache',
				$prefix . 'wfstatus',
				$prefix . 'wftrafficrates',
				$prefix . 'wfblocksadv',
				$prefix . 'wflivingtraffic',
				$prefix . 'wflocs',
				$prefix . 'wfls_',
			),
			// Akismet.
			'akismet/akismet.php'                      => array( $prefix . 'akismet_' ),
			// bbPress.
			'bbpress/bbpress.php'                      => array( $prefix . 'bbpress_', $prefix . 'bb_' ),
			// BuddyPress.
			'buddypress/bp-loader.php'                 => array( $prefix . 'bp_' ),
			// Easy Digital Downloads.
			'easy-digital-downloads/easy-digital-downloads.php' => array( $prefix . 'edd_' ),
			// Elementor.
			'elementor/elementor.php'                  => array( $prefix . 'e_' ),
			'elementor-pro/elementor-pro.php'          => array( $prefix . 'e_' ),
			// WP Rocket.
			'wp-rocket/wp-rocket.php'                  => array( $prefix . 'wpr_' ),
			// Gravity Forms.
			'gravityforms/gravityforms.php'            => array( $prefix . 'gf_', $prefix . 'rg_' ),
			// WPForms.
			'wpforms-lite/wpforms.php'                 => array( $prefix . 'wpforms_' ),
			'wpforms/wpforms.php'                      => array( $prefix . 'wpforms_' ),
			// Jetpack.
			'jetpack/jetpack.php'                      => array( $prefix . 'jetpack_' ),
			// TablePress.
			'tablepress/tablepress.php'                => array( $prefix . 'tablepress_' ),
			// UpdraftPlus.
			'updraftplus/updraftplus.php'              => array( $prefix . 'updraft_' ),
			// All-in-One WP Migration.
			'all-in-one-wp-migration/all-in-one-wp-migration.php' => array( $prefix . 'aiowpm_' ),
			// Ninja Forms.
			'ninja-forms/ninja-forms.php'              => array( $prefix . 'nf_', $prefix . 'nf3_' ),
			// Redirection.
			'redirection/redirection.php'              => array( $prefix . 'redirection_' ),
			// WP Statistics.
			'wp-statistics/wp-statistics.php'          => array( $prefix . 'statistics_' ),
			// Action Scheduler (used by WooCommerce and others).
			'action-scheduler/action-scheduler.php'    => array( $prefix . 'actionscheduler_' ),
		);

		// WP Admin Health Suite (our own plugin) - always protected.
		$plugin_tables[] = $prefix . 'wpha_scan_history';

		// Get all tables in database.
		$all_tables = $this->get_all_wp_tables();

		// Collect patterns from ACTIVE plugins only.
		$active_patterns = array();
		foreach ( $active_plugins as $plugin_file ) {
			if ( isset( $plugin_table_mapping[ $plugin_file ] ) ) {
				$active_patterns = array_merge( $active_patterns, $plugin_table_mapping[ $plugin_file ] );
			}
		}

		// Check Action Scheduler tables if any WooCommerce-dependent plugin is active.
		// Action Scheduler is often bundled, not standalone.
		$action_scheduler_users = array(
			'woocommerce/woocommerce.php',
			'woocommerce-subscriptions/woocommerce-subscriptions.php',
			'automatewoo/automatewoo.php',
		);
		foreach ( $action_scheduler_users as $as_user ) {
			if ( in_array( $as_user, $active_plugins, true ) ) {
				$active_patterns[] = $prefix . 'actionscheduler_';
				break;
			}
		}

		// Check each table against ACTIVE plugin patterns only.
		foreach ( $all_tables as $table ) {
			foreach ( $active_patterns as $pattern ) {
				// Check if pattern is a prefix match.
				if ( strpos( $table, $pattern ) === 0 ) {
					$plugin_tables[] = $table;
					break;
				}
			}
		}

		/**
		 * Filters the list of known plugin tables.
		 *
		 * IMPORTANT: This filter should only be used to register tables
		 * that belong to ACTIVE plugins. Do not add tables from deactivated
		 * plugins - those should be flagged as orphaned for cleanup.
		 *
		 * Plugins can use this to register their custom tables:
		 * ```php
		 * add_filter( 'wpha_registered_plugin_tables', function( $tables ) {
		 *     global $wpdb;
		 *     $tables[] = $wpdb->prefix . 'my_plugin_table';
		 *     return $tables;
		 * } );
		 * ```
		 *
		 * @since 1.0.0
		 * @since 1.5.0 Now only intended for active plugin tables.
		 *
		 * @hook wpha_registered_plugin_tables
		 *
		 * @param {array} $plugin_tables Array of active plugin table names.
		 *
		 * @return array Modified array of plugin table names.
		 */
		$plugin_tables = apply_filters( 'wpha_registered_plugin_tables', $plugin_tables );

		// Remove duplicates.
		$plugin_tables = array_unique( $plugin_tables );

		return $plugin_tables;
	}

	/**
	 * Get tables that may be shared between multiple plugins.
	 *
	 * Some tables are used by multiple plugins (e.g., Action Scheduler).
	 * These require extra caution before deletion.
	 *
	 * @since 1.5.0
	 *
	 * @return array Array of shared table patterns.
	 */
	public function get_shared_table_patterns() {
		$prefix = $this->connection->get_prefix();

		$shared_patterns = array(
			// Action Scheduler is used by WooCommerce, WooCommerce Subscriptions, etc.
			$prefix . 'actionscheduler_',
			// WP-Cron alternatives.
			$prefix . 'wpcron_',
		);

		/**
		 * Filters the list of shared table patterns.
		 *
		 * Shared tables are those that may be used by multiple plugins.
		 * These tables are flagged with a warning during orphan detection.
		 *
		 * @since 1.5.0
		 *
		 * @hook wpha_shared_table_patterns
		 *
		 * @param {array} $shared_patterns Array of shared table prefix patterns.
		 *
		 * @return array Modified array of shared table patterns.
		 */
		return apply_filters( 'wpha_shared_table_patterns', $shared_patterns );
	}

	/**
	 * Check if a table matches shared table patterns.
	 *
	 * @since 1.5.0
	 *
	 * @param string $table_name Table name to check.
	 * @return bool True if table may be shared between plugins.
	 */
	private function is_potentially_shared_table( $table_name ) {
		$shared_patterns = $this->get_shared_table_patterns();

		foreach ( $shared_patterns as $pattern ) {
			if ( strpos( $table_name, $pattern ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find orphaned tables.
	 *
	 * Identifies tables that have the WordPress prefix but are not in the known
	 * core or active plugin tables list.
	 *
	 * A table is considered orphaned if:
	 * - It has the WordPress prefix
	 * - It is NOT a WordPress core table
	 * - It is NOT registered by an ACTIVE plugin
	 *
	 * This means tables from deactivated plugins WILL be flagged as orphaned.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Now correctly identifies tables from deactivated plugins as orphaned.
	 *
	 * @return array Array of orphaned table information including name, size, row count,
	 *               and metadata about potential owners and shared table warnings.
	 */
	public function find_orphaned_tables() {
		$all_tables    = $this->get_all_wp_tables();
		$core_tables   = $this->get_known_core_tables();
		$plugin_tables = $this->get_registered_plugin_tables();

		// Combine core and plugin tables.
		$known_tables = array_merge( $core_tables, $plugin_tables );

		// Find tables that are not in the known list.
		$orphaned_tables = array_diff( $all_tables, $known_tables );

		if ( empty( $orphaned_tables ) ) {
			return array();
		}

		// Get detailed information about each orphaned table.
		$orphaned_info = array();

		foreach ( $orphaned_tables as $table ) {
			$info = $this->get_table_info( $table );
			if ( $info ) {
				// Add shared table warning if applicable.
				$info['is_shared_table'] = $this->is_potentially_shared_table( $table );
				if ( $info['is_shared_table'] ) {
					$info['warning'] = __( 'This table may be shared by multiple plugins. Verify no active plugins depend on it before deletion.', 'wp-admin-health-suite' );
				}

				// Try to identify potential owner plugin based on table prefix patterns.
				$info['potential_owner'] = $this->identify_potential_owner( $table );

				$orphaned_info[] = $info;
			}
		}

		/**
		 * Filters the list of detected orphaned tables.
		 *
		 * Allows modification of the orphaned tables list before it's returned.
		 * Use this to add custom logic for identifying false positives.
		 *
		 * @since 1.5.0
		 *
		 * @hook wpha_orphaned_tables
		 *
		 * @param {array} $orphaned_info Array of orphaned table information.
		 *
		 * @return array Modified array of orphaned tables.
		 */
		return apply_filters( 'wpha_orphaned_tables', $orphaned_info );
	}

	/**
	 * Identify the potential owner plugin of an orphaned table.
	 *
	 * Uses table name patterns to guess which plugin created the table.
	 * This helps users understand where orphaned tables came from.
	 *
	 * @since 1.5.0
	 *
	 * @param string $table_name The table name.
	 * @return string|null Plugin name or null if unknown.
	 */
	private function identify_potential_owner( $table_name ) {
		$prefix = $this->connection->get_prefix();

		// Map of table patterns to human-readable plugin names.
		$pattern_to_plugin = array(
			$prefix . 'wc_'              => 'WooCommerce',
			$prefix . 'woocommerce_'     => 'WooCommerce',
			$prefix . 'cf7_'             => 'Contact Form 7',
			$prefix . 'contact_form_7_'  => 'Contact Form 7',
			$prefix . 'yoast_'           => 'Yoast SEO',
			$prefix . 'wpmailsmtp_'      => 'WP Mail SMTP',
			$prefix . 'wfconfig'         => 'Wordfence',
			$prefix . 'wfblocks'         => 'Wordfence',
			$prefix . 'wflogins'         => 'Wordfence',
			$prefix . 'wffilechanges'    => 'Wordfence',
			$prefix . 'wfissues'         => 'Wordfence',
			$prefix . 'wfpendingissues'  => 'Wordfence',
			$prefix . 'wfknownfilelist'  => 'Wordfence',
			$prefix . 'wfsnipcache'      => 'Wordfence',
			$prefix . 'wfstatus'         => 'Wordfence',
			$prefix . 'wftrafficrates'   => 'Wordfence',
			$prefix . 'wfblocksadv'      => 'Wordfence',
			$prefix . 'wflivingtraffic'  => 'Wordfence',
			$prefix . 'wflocs'           => 'Wordfence',
			$prefix . 'wfls_'            => 'Wordfence',
			$prefix . 'akismet_'         => 'Akismet',
			$prefix . 'bbpress_'         => 'bbPress',
			$prefix . 'bb_'              => 'bbPress',
			$prefix . 'bp_'              => 'BuddyPress',
			$prefix . 'edd_'             => 'Easy Digital Downloads',
			$prefix . 'e_'               => 'Elementor',
			$prefix . 'wpr_'             => 'WP Rocket',
			$prefix . 'gf_'              => 'Gravity Forms',
			$prefix . 'rg_'              => 'Gravity Forms',
			$prefix . 'wpforms_'         => 'WPForms',
			$prefix . 'jetpack_'         => 'Jetpack',
			$prefix . 'tablepress_'      => 'TablePress',
			$prefix . 'updraft_'         => 'UpdraftPlus',
			$prefix . 'aiowpm_'          => 'All-in-One WP Migration',
			$prefix . 'nf_'              => 'Ninja Forms',
			$prefix . 'nf3_'             => 'Ninja Forms',
			$prefix . 'redirection_'     => 'Redirection',
			$prefix . 'statistics_'      => 'WP Statistics',
			$prefix . 'actionscheduler_' => 'Action Scheduler (WooCommerce or related)',
			$prefix . 'mailpoet_'        => 'MailPoet',
			$prefix . 'mailpoet3_'       => 'MailPoet',
			$prefix . 'icl_'             => 'WPML',
			$prefix . 'litespeed_'       => 'LiteSpeed Cache',
		);

		foreach ( $pattern_to_plugin as $pattern => $plugin_name ) {
			if ( strpos( $table_name, $pattern ) === 0 ) {
				return $plugin_name;
			}
		}

		/**
		 * Filters the identified potential owner of an orphaned table.
		 *
		 * Plugins can use this to identify tables they created.
		 *
		 * @since 1.5.0
		 *
		 * @hook wpha_table_potential_owner
		 *
		 * @param {string|null} $owner      Identified owner or null.
		 * @param {string}      $table_name The table name.
		 *
		 * @return string|null Plugin name or null if unknown.
		 */
		return apply_filters( 'wpha_table_potential_owner', null, $table_name );
	}

	/**
	 * Get detailed information about a table.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $table_name The table name.
	 * @return array|null Array with table information or null on failure.
	 */
	private function get_table_info( $table_name ) {
		// Get table size and row count.
		$query = $this->connection->prepare(
			'SELECT
				TABLE_NAME as name,
				TABLE_ROWS as row_count,
				(DATA_LENGTH + INDEX_LENGTH) as size_bytes,
				DATA_LENGTH,
				INDEX_LENGTH,
				CREATE_TIME as created_at,
				UPDATE_TIME as updated_at
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s',
			DB_NAME,
			$table_name
		);

		if ( null === $query ) {
			return null;
		}

		$result = $this->connection->get_row( $query, 'ARRAY_A' );

		if ( ! $result ) {
			return null;
		}

		// Add a confirmation hash for safe deletion.
		$result['confirmation_hash'] = $this->generate_confirmation_hash( $table_name );

		return $result;
	}

	/**
	 * Check if a table is orphaned (no known owner).
	 *
	 * Re-verifies orphaned status to prevent TOCTOU race conditions.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $table_name The table name to check.
	 * @return bool True if table is orphaned, false otherwise.
	 */
	private function is_table_orphaned( $table_name ) {
		// Verify table exists.
		$query = $this->connection->prepare(
			'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			DB_NAME,
			$table_name
		);

		if ( null === $query ) {
			return false;
		}

		$exists = $this->connection->get_var( $query );

		if ( ! $exists ) {
			return false; // Table doesn't exist.
		}

		// Verify not a core table.
		$core_tables = $this->get_known_core_tables();
		if ( in_array( $table_name, $core_tables, true ) ) {
			return false;
		}

		// Verify not a registered plugin table.
		$plugin_tables = $this->get_registered_plugin_tables();
		if ( in_array( $table_name, $plugin_tables, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Acquire a deletion lock for a table.
	 *
	 * Prevents concurrent deletion attempts on the same table.
	 * Uses atomic option insertion to prevent TOCTOU race conditions.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $table_name The table name.
	 * @return bool True if lock acquired, false if already locked.
	 */
	private function acquire_deletion_lock( $table_name ) {
		$lock_key   = 'wpha_table_delete_lock_' . md5( $table_name );
		$lock_value = uniqid( 'lock_', true );
		$expiry     = time() + 30; // Lock expires in 30 seconds.

		$options_table = $this->connection->get_options_table();

		// Use INSERT IGNORE for atomic lock acquisition.
		// This is atomic - only succeeds if the key doesn't exist.
		$query = $this->connection->prepare(
			"INSERT IGNORE INTO {$options_table} (option_name, option_value, autoload)
			VALUES (%s, %s, 'no')",
			'_transient_' . $lock_key,
			$lock_value . ':' . $expiry
		);

		if ( null === $query ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->connection->query( $query );

		// If insert failed (row exists), check if the existing lock has expired.
		if ( 0 === $result ) {
			$existing = get_transient( $lock_key );
			if ( $existing ) {
				$parts = explode( ':', $existing );
				if ( count( $parts ) === 2 && (int) $parts[1] < time() ) {
					// Lock expired - delete and retry.
					delete_transient( $lock_key );
					return $this->acquire_deletion_lock( $table_name );
				}
			}
			return false;
		}

		return true;
	}

	/**
	 * Release a deletion lock for a table.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table_name The table name.
	 * @return void
	 */
	private function release_deletion_lock( $table_name ) {
		$lock_key = 'wpha_table_delete_lock_' . md5( $table_name );
		delete_transient( $lock_key );
	}

	/**
	 * Generate a confirmation hash for a table.
	 *
	 * Uses HMAC-SHA256 for cryptographically secure hash generation.
	 * The hash prevents unauthorized table deletion by requiring a valid token.
	 *
	 * @param string $table_name The table name.
	 * @return string The confirmation hash (32 hex characters).
	 */
	private function generate_confirmation_hash( $table_name ) {
		// Use HMAC-SHA256 with AUTH_KEY as the secret key for proper security.
		// HMAC prevents length extension attacks and is cryptographically secure.
		$secret_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpha_default_key_' . ABSPATH;
		$hash       = hash_hmac( 'sha256', $table_name, $secret_key );

		// Return first 32 characters (128 bits) - sufficient for CSRF-like protection.
		return substr( $hash, 0, 32 );
	}

	/**
	 * Verify a confirmation hash for a table.
	 *
	 * @param string $table_name      The table name.
	 * @param string $confirmation_hash The hash to verify.
	 * @return bool True if hash is valid, false otherwise.
	 */
	private function verify_confirmation_hash( $table_name, $confirmation_hash ) {
		$expected_hash = $this->generate_confirmation_hash( $table_name );
		return hash_equals( $expected_hash, $confirmation_hash );
	}

	/**
	 * Delete an orphaned table.
	 *
	 * NEVER auto-deletes - requires explicit confirmation with table name hash.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $table_name      The table name to delete.
	 * @param string $confirmation_hash The confirmation hash.
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function delete_orphaned_table( $table_name, $confirmation_hash ) {
		// Verify the confirmation hash.
		if ( ! $this->verify_confirmation_hash( $table_name, $confirmation_hash ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid confirmation hash. Cannot delete table.', 'wp-admin-health-suite' ),
			);
		}

		// Verify table has WordPress prefix.
		$prefix = $this->connection->get_prefix();
		if ( strpos( $table_name, $prefix ) !== 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Table does not have WordPress prefix. Cannot delete.', 'wp-admin-health-suite' ),
			);
		}

		// Sanitize table name (allow only alphanumeric, underscores).
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid table name format.', 'wp-admin-health-suite' ),
			);
		}

		// Acquire deletion lock to prevent concurrent deletion attempts.
		if ( ! $this->acquire_deletion_lock( $table_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Another deletion is in progress for this table. Please try again.', 'wp-admin-health-suite' ),
			);
		}

		try {
			// Re-verify table is still orphaned (TOCTOU protection).
			// This prevents race conditions where a plugin claims the table
			// between the initial check and the deletion.
			if ( ! $this->is_table_orphaned( $table_name ) ) {
				return array(
					'success' => false,
					'message' => __( 'Table is no longer orphaned (may have been claimed by a plugin). Cannot delete.', 'wp-admin-health-suite' ),
				);
			}

			// Get table info before deletion for logging.
			$table_info = $this->get_table_info( $table_name );
			if ( ! $table_info ) {
				return array(
					'success' => false,
					'message' => __( 'Table does not exist.', 'wp-admin-health-suite' ),
				);
			}

			// Drop the table.
			// Using backticks and direct query since prepare doesn't support table names.
			// Additional escaping with esc_sql() for security.
			$escaped_table = esc_sql( $table_name );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $this->connection->query( "DROP TABLE IF EXISTS `{$escaped_table}`" );

			if ( false === $result ) {
				// Log the full error details securely for debugging.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[WP Admin Health Suite] Table deletion failed for "%s": %s',
						$table_name,
						$this->connection->get_last_error()
					)
				);

				// Return a generic message to avoid information disclosure.
				return array(
					'success' => false,
					'message' => __( 'Failed to delete table due to a database error. Please check the error logs for details.', 'wp-admin-health-suite' ),
				);
			}

			// Log the deletion.
			$this->log_table_deletion( $table_name, $table_info );

			return array(
				'success'    => true,
				'message'    => sprintf(
					/* translators: %s: table name */
					__( 'Table %s has been successfully deleted.', 'wp-admin-health-suite' ),
					$table_name
				),
				'size_freed' => absint( $table_info['size_bytes'] ),
			);
		} finally {
			// Always release the lock, even if an error occurred.
			$this->release_deletion_lock( $table_name );
		}
	}

	/**
	 * Log table deletion to scan history.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $table_name The table name.
	 * @param array  $table_info Table information.
	 * @return bool True on success, false on failure.
	 */
	private function log_table_deletion( $table_name, $table_info ) {
		$history_table = $this->connection->get_prefix() . 'wpha_scan_history';

		$result = $this->connection->insert(
			$history_table,
			array(
				'scan_type'     => 'orphaned_table_deletion',
				'items_found'   => 1,
				'items_cleaned' => 1,
				'bytes_freed'   => absint( $table_info['size_bytes'] ),
				'details'       => wp_json_encode(
					array(
						'table_name' => $table_name,
						'row_count'  => absint( $table_info['row_count'] ),
						'size_bytes' => absint( $table_info['size_bytes'] ),
					)
				),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return false !== $result;
	}
}
