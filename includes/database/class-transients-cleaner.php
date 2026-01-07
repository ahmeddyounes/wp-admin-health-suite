<?php
/**
 * Transients Cleaner Class
 *
 * Manages WordPress transients including analysis, deletion, and cleanup operations.
 * Handles both options table transients and external object cache.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Transients Cleaner class for managing transients.
 */
class Transients_Cleaner {

	/**
	 * Batch size for processing transients.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Get the total count of all transients.
	 *
	 * @return int Total number of transients.
	 */
	public function get_all_transients_count() {
		// If using external object cache, we can't count transients.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		global $wpdb;

		// Count both regular and site transients.
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' ) . '%',
			$wpdb->esc_like( '_site_transient_' ) . '%'
		);

		// Divide by 2 because each transient has a value and a timeout option.
		$count = absint( $wpdb->get_var( $query ) );

		return intval( $count / 2 );
	}

	/**
	 * Get expired transients.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array Array of expired transient names.
	 */
	public function get_expired_transients( $exclude_patterns = array() ) {
		// If using external object cache, we can't get expired transients.
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		global $wpdb;

		// Get expired transients.
		$query = $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			WHERE (option_name LIKE %s OR option_name LIKE %s)
			AND option_value < %d
			ORDER BY option_name ASC",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
			time()
		);

		$results = $wpdb->get_col( $query );

		if ( empty( $results ) ) {
			return array();
		}

		$transients = array();

		foreach ( $results as $option_name ) {
			// Extract the transient name from the timeout option name.
			$transient_name = str_replace(
				array( '_transient_timeout_', '_site_transient_timeout_' ),
				'',
				$option_name
			);

			// Check if this transient should be excluded.
			if ( $this->should_exclude_transient( $transient_name, $exclude_patterns ) ) {
				continue;
			}

			$transients[] = $transient_name;
		}

		return $transients;
	}

	/**
	 * Get an estimate of the disk space used by transients.
	 *
	 * @return int Estimated bytes used by transients.
	 */
	public function get_transients_size() {
		// If using external object cache, we can't calculate size.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		global $wpdb;

		// Get sum of option_name and option_value lengths for all transients.
		$query = $wpdb->prepare(
			"SELECT SUM(
				LENGTH(option_name) +
				LENGTH(option_value)
			) as total_size
			FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_' ) . '%',
			$wpdb->esc_like( '_site_transient_' ) . '%'
		);

		$size = absint( $wpdb->get_var( $query ) );

		// Add overhead estimate (row overhead, indexes, etc.).
		$overhead_multiplier = 1.5;

		return absint( $size * $overhead_multiplier );
	}

	/**
	 * Delete expired transients.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function delete_expired_transients( $exclude_patterns = array() ) {
		// If using external object cache, we can't delete transients.
		if ( wp_using_ext_object_cache() ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Get expired transients.
		$expired_transients = $this->get_expired_transients( $exclude_patterns );

		if ( empty( $expired_transients ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		$deleted_count = 0;
		$bytes_freed   = 0;

		// Process in batches to prevent timeout.
		$batches = array_chunk( $expired_transients, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$result = $this->delete_transients_batch( $batch );
			$deleted_count += $result['deleted'];
			$bytes_freed   += $result['bytes_freed'];
		}

		// Log to scan history.
		$this->log_deletion(
			'transient_expired_cleanup',
			count( $expired_transients ),
			$deleted_count,
			$bytes_freed
		);

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Delete all transients with optional exclusion patterns.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function delete_all_transients( $exclude_patterns = array() ) {
		// If using external object cache, we can't delete transients.
		if ( wp_using_ext_object_cache() ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		global $wpdb;

		// Get all transient timeout option names.
		$query = $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s
			ORDER BY option_name ASC",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_' ) . '%'
		);

		$results = $wpdb->get_col( $query );

		if ( empty( $results ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		$transients = array();

		foreach ( $results as $option_name ) {
			// Extract the transient name from the timeout option name.
			$transient_name = str_replace(
				array( '_transient_timeout_', '_site_transient_timeout_' ),
				'',
				$option_name
			);

			// Check if this transient should be excluded.
			if ( $this->should_exclude_transient( $transient_name, $exclude_patterns ) ) {
				continue;
			}

			$transients[] = $transient_name;
		}

		if ( empty( $transients ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		$deleted_count = 0;
		$bytes_freed   = 0;

		// Process in batches to prevent timeout.
		$batches = array_chunk( $transients, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$result = $this->delete_transients_batch( $batch );
			$deleted_count += $result['deleted'];
			$bytes_freed   += $result['bytes_freed'];
		}

		// Log to scan history.
		$this->log_deletion(
			'transient_all_cleanup',
			count( $transients ),
			$deleted_count,
			$bytes_freed
		);

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Get transients by prefix.
	 *
	 * @param string $prefix The prefix to search for.
	 * @return array Array of transient names matching the prefix.
	 */
	public function get_transient_by_prefix( $prefix ) {
		// If using external object cache, we can't get transients by prefix.
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		global $wpdb;

		$prefix = sanitize_text_field( $prefix );

		// Search for transients with the given prefix.
		$query = $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			WHERE (option_name LIKE %s OR option_name LIKE %s)
			AND (option_name LIKE %s OR option_name LIKE %s)
			ORDER BY option_name ASC",
			$wpdb->esc_like( '_transient_' ) . '%',
			$wpdb->esc_like( '_site_transient_' ) . '%',
			$wpdb->esc_like( '_transient_' . $prefix ) . '%',
			$wpdb->esc_like( '_site_transient_' . $prefix ) . '%'
		);

		$results = $wpdb->get_col( $query );

		if ( empty( $results ) ) {
			return array();
		}

		$transients = array();

		foreach ( $results as $option_name ) {
			// Extract the transient name from the option name.
			$transient_name = str_replace(
				array( '_transient_', '_site_transient_', '_transient_timeout_', '_site_transient_timeout_' ),
				'',
				$option_name
			);

			// Only add if it starts with the prefix and it's not a duplicate.
			if ( strpos( $transient_name, $prefix ) === 0 && ! in_array( $transient_name, $transients, true ) ) {
				$transients[] = $transient_name;
			}
		}

		return $transients;
	}

	/**
	 * Check if a transient should be excluded based on patterns.
	 *
	 * @param string $transient_name    The transient name.
	 * @param array  $exclude_patterns  Array of prefixes to exclude.
	 * @return bool True if should be excluded, false otherwise.
	 */
	private function should_exclude_transient( $transient_name, $exclude_patterns = array() ) {
		if ( empty( $exclude_patterns ) || ! is_array( $exclude_patterns ) ) {
			return false;
		}

		foreach ( $exclude_patterns as $pattern ) {
			if ( strpos( $transient_name, $pattern ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a batch of transients.
	 *
	 * @param array $transient_names Array of transient names to delete.
	 * @return array Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	private function delete_transients_batch( $transient_names ) {
		global $wpdb;

		$deleted_count = 0;
		$bytes_freed   = 0;

		foreach ( $transient_names as $transient_name ) {
			// Estimate size before deletion.
			$size_estimate = $this->estimate_transient_size( $transient_name );

			// Check if it's a site transient or regular transient.
			$is_site_transient = false;

			// Try to get the timeout value to determine if it's a site transient.
			$timeout_option = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
					WHERE option_name = %s
					LIMIT 1",
					'_site_transient_timeout_' . $transient_name
				)
			);

			if ( $timeout_option ) {
				$is_site_transient = true;
			}

			// Delete the transient using WordPress functions.
			if ( $is_site_transient ) {
				$result = delete_site_transient( $transient_name );
			} else {
				$result = delete_transient( $transient_name );
			}

			if ( $result ) {
				$deleted_count++;
				$bytes_freed += $size_estimate;
			}
		}

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Estimate the size of a single transient.
	 *
	 * @param string $transient_name The transient name.
	 * @return int Estimated size in bytes.
	 */
	private function estimate_transient_size( $transient_name ) {
		global $wpdb;

		// Get sizes for both regular and site transient options.
		$query = $wpdb->prepare(
			"SELECT SUM(
				LENGTH(option_name) +
				LENGTH(option_value)
			) as size
			FROM {$wpdb->options}
			WHERE option_name IN (%s, %s, %s, %s)",
			'_transient_' . $transient_name,
			'_transient_timeout_' . $transient_name,
			'_site_transient_' . $transient_name,
			'_site_transient_timeout_' . $transient_name
		);

		$size = absint( $wpdb->get_var( $query ) );

		// Add overhead estimate.
		$overhead_multiplier = 1.5;

		return absint( $size * $overhead_multiplier );
	}

	/**
	 * Log deletion to scan history table.
	 *
	 * @param string $scan_type     The type of scan/cleanup.
	 * @param int    $items_found   Number of items found.
	 * @param int    $items_cleaned Number of items cleaned.
	 * @param int    $bytes_freed   Bytes freed.
	 * @return bool True on success, false on failure.
	 */
	private function log_deletion( $scan_type, $items_found, $items_cleaned, $bytes_freed ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpha_scan_history';

		$result = $wpdb->insert(
			$table_name,
			array(
				'scan_type'     => sanitize_text_field( $scan_type ),
				'items_found'   => absint( $items_found ),
				'items_cleaned' => absint( $items_cleaned ),
				'bytes_freed'   => absint( $bytes_freed ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);

		return false !== $result;
	}
}
