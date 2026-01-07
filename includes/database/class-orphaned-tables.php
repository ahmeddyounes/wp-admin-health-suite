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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Orphaned Tables class for detecting and managing orphaned database tables.
 */
class Orphaned_Tables {

	/**
	 * Get all tables in the database with the WordPress prefix.
	 *
	 * @return array Array of table names.
	 */
	public function get_all_wp_tables() {
		global $wpdb;

		$prefix = $wpdb->prefix;

		// Get all tables in the database.
		$query = $wpdb->prepare(
			"SELECT TABLE_NAME
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME LIKE %s
			ORDER BY TABLE_NAME ASC",
			DB_NAME,
			$wpdb->esc_like( $prefix ) . '%'
		);

		$tables = $wpdb->get_col( $query );

		return is_array( $tables ) ? $tables : array();
	}

	/**
	 * Get known WordPress core tables.
	 *
	 * @return array Array of core table names.
	 */
	public function get_known_core_tables() {
		global $wpdb;

		$core_tables = array(
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->comments,
			$wpdb->commentmeta,
			$wpdb->terms,
			$wpdb->termmeta,
			$wpdb->term_relationships,
			$wpdb->term_taxonomy,
			$wpdb->users,
			$wpdb->usermeta,
			$wpdb->links,
			$wpdb->options,
		);

		// Add multisite tables if multisite is enabled.
		if ( is_multisite() ) {
			$core_tables[] = $wpdb->blogs;
			$core_tables[] = $wpdb->blogmeta;
			$core_tables[] = $wpdb->site;
			$core_tables[] = $wpdb->sitemeta;
			$core_tables[] = $wpdb->sitecategories;
			$core_tables[] = $wpdb->registration_log;
			$core_tables[] = $wpdb->signups;
		}

		// Filter out any null values.
		$core_tables = array_filter( $core_tables );

		return $core_tables;
	}

	/**
	 * Get tables registered by active plugins.
	 *
	 * Scans active plugins for $wpdb->table patterns and custom table registrations.
	 *
	 * @return array Array of plugin table names.
	 */
	public function get_registered_plugin_tables() {
		global $wpdb;

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

		// Common plugin table patterns.
		$plugin_table_patterns = array(
			// WooCommerce tables.
			$wpdb->prefix . 'wc_',
			$wpdb->prefix . 'woocommerce_',
			// Contact Form 7.
			$wpdb->prefix . 'cf7_',
			$wpdb->prefix . 'contact_form_7_',
			// Yoast SEO.
			$wpdb->prefix . 'yoast_',
			// WP Mail SMTP.
			$wpdb->prefix . 'wpmailsmtp_',
			// Wordfence.
			$wpdb->prefix . 'wfconfig',
			$wpdb->prefix . 'wfblocks',
			$wpdb->prefix . 'wflogins',
			$wpdb->prefix . 'wffilechanges',
			$wpdb->prefix . 'wfissues',
			$wpdb->prefix . 'wfpendingissues',
			$wpdb->prefix . 'wfknownfilelist',
			$wpdb->prefix . 'wfsnipcache',
			$wpdb->prefix . 'wfstatus',
			$wpdb->prefix . 'wftrafficrates',
			$wpdb->prefix . 'wfblocksadv',
			$wpdb->prefix . 'wflivingtraffic',
			$wpdb->prefix . 'wflocs',
			$wpdb->prefix . 'wfls_',
			// Akismet.
			$wpdb->prefix . 'akismet_',
			// bbPress.
			$wpdb->prefix . 'bbpress_',
			$wpdb->prefix . 'bb_',
			// BuddyPress.
			$wpdb->prefix . 'bp_',
			// Easy Digital Downloads.
			$wpdb->prefix . 'edd_',
			// WP Admin Health Suite (our own plugin).
			$wpdb->prefix . 'wpha_',
			// Elementor.
			$wpdb->prefix . 'e_',
			// WP Rocket.
			$wpdb->prefix . 'wpr_',
		);

		// Get all tables in database.
		$all_tables = $this->get_all_wp_tables();

		// Check each table against plugin patterns.
		foreach ( $all_tables as $table ) {
			foreach ( $plugin_table_patterns as $pattern ) {
				// Check if pattern is a prefix or exact match.
				if ( strpos( $table, $pattern ) === 0 || $table === $pattern ) {
					$plugin_tables[] = $table;
					break;
				}
			}
		}

		/**
		 * Filter the list of known plugin tables.
		 *
		 * Allows plugins to register their custom tables to prevent them from being
		 * detected as orphaned.
		 *
		 * @param array $plugin_tables Array of plugin table names.
		 */
		$plugin_tables = apply_filters( 'wpha_registered_plugin_tables', $plugin_tables );

		// Remove duplicates.
		$plugin_tables = array_unique( $plugin_tables );

		return $plugin_tables;
	}

	/**
	 * Find orphaned tables.
	 *
	 * Identifies tables that have the WordPress prefix but are not in the known
	 * core or plugin tables list.
	 *
	 * @return array Array of orphaned table information including name, size, and row count.
	 */
	public function find_orphaned_tables() {
		global $wpdb;

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
				$orphaned_info[] = $info;
			}
		}

		return $orphaned_info;
	}

	/**
	 * Get detailed information about a table.
	 *
	 * @param string $table_name The table name.
	 * @return array|null Array with table information or null on failure.
	 */
	private function get_table_info( $table_name ) {
		global $wpdb;

		// Get table size and row count.
		$query = $wpdb->prepare(
			"SELECT
				TABLE_NAME as name,
				TABLE_ROWS as row_count,
				(DATA_LENGTH + INDEX_LENGTH) as size_bytes,
				DATA_LENGTH,
				INDEX_LENGTH,
				CREATE_TIME as created_at,
				UPDATE_TIME as updated_at
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME = %s",
			DB_NAME,
			$table_name
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $result ) {
			return null;
		}

		// Add a confirmation hash for safe deletion.
		$result['confirmation_hash'] = $this->generate_confirmation_hash( $table_name );

		return $result;
	}

	/**
	 * Generate a confirmation hash for a table.
	 *
	 * @param string $table_name The table name.
	 * @return string The confirmation hash.
	 */
	private function generate_confirmation_hash( $table_name ) {
		// Use a combination of table name and a salt for security.
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpha_salt';
		return substr( md5( $table_name . $salt ), 0, 8 );
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
	 * @param string $table_name      The table name to delete.
	 * @param string $confirmation_hash The confirmation hash.
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function delete_orphaned_table( $table_name, $confirmation_hash ) {
		global $wpdb;

		// Verify the confirmation hash.
		if ( ! $this->verify_confirmation_hash( $table_name, $confirmation_hash ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid confirmation hash. Cannot delete table.', 'wp-admin-health-suite' ),
			);
		}

		// Verify table has WordPress prefix.
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Table does not have WordPress prefix. Cannot delete.', 'wp-admin-health-suite' ),
			);
		}

		// Double-check table is not a core table.
		$core_tables = $this->get_known_core_tables();
		if ( in_array( $table_name, $core_tables, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot delete core WordPress table.', 'wp-admin-health-suite' ),
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

		// Sanitize table name (allow only alphanumeric, underscores).
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid table name format.', 'wp-admin-health-suite' ),
			);
		}

		// Drop the table.
		// Using backticks and direct query since $wpdb->prepare doesn't support table names.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: database error message */
					__( 'Failed to delete table: %s', 'wp-admin-health-suite' ),
					$wpdb->last_error
				),
			);
		}

		// Log the deletion.
		$this->log_table_deletion( $table_name, $table_info );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: table name */
				__( 'Table %s has been successfully deleted.', 'wp-admin-health-suite' ),
				$table_name
			),
			'size_freed' => absint( $table_info['size_bytes'] ),
		);
	}

	/**
	 * Log table deletion to scan history.
	 *
	 * @param string $table_name The table name.
	 * @param array  $table_info Table information.
	 * @return bool True on success, false on failure.
	 */
	private function log_table_deletion( $table_name, $table_info ) {
		global $wpdb;

		$history_table = $wpdb->prefix . 'wpha_scan_history';

		$result = $wpdb->insert(
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
