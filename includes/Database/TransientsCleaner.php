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

use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Transients Cleaner class for managing transients.
 *
 * Handles regular transients stored in the options table and site transients
 * stored in either the options table (single site) or sitemeta table (multisite).
 *
 * @since 1.0.0
 * @since 1.2.0 Implements TransientsCleanerInterface.
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 * @since 1.4.0 Improved multisite compatibility and race condition handling.
 */
class TransientsCleaner implements TransientsCleanerInterface {

	/**
	 * Batch size for processing transients.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Prefix for regular transient timeout options.
	 *
	 * @var string
	 */
	const TRANSIENT_TIMEOUT_PREFIX = '_transient_timeout_';

	/**
	 * Prefix for site transient timeout options.
	 *
	 * @var string
	 */
	const SITE_TRANSIENT_TIMEOUT_PREFIX = '_site_transient_timeout_';

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Check if the current installation is multisite.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True if multisite, false otherwise.
	 */
	private function is_multisite(): bool {
		return is_multisite();
	}

	/**
	 * Get the sitemeta table name for multisite installations.
	 *
	 * @since 1.4.0
	 *
	 * @return string Sitemeta table name.
	 */
	private function get_sitemeta_table(): string {
		global $wpdb;
		return $wpdb->sitemeta;
	}

	/**
	 * Extract transient name from option name.
	 *
	 * Safely removes the transient prefix from option names, handling both
	 * regular and site transients correctly.
	 *
	 * @since 1.4.0
	 *
	 * @param string $option_name The full option name (e.g., '_transient_timeout_my_transient').
	 * @return array{name: string, is_site_transient: bool} Transient name and type.
	 */
	private function extract_transient_name( string $option_name ): array {
		// Check for site transient first (longer prefix).
		if ( strpos( $option_name, self::SITE_TRANSIENT_TIMEOUT_PREFIX ) === 0 ) {
			return array(
				'name'              => substr( $option_name, strlen( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) ),
				'is_site_transient' => true,
			);
		}

		// Check for regular transient.
		if ( strpos( $option_name, self::TRANSIENT_TIMEOUT_PREFIX ) === 0 ) {
			return array(
				'name'              => substr( $option_name, strlen( self::TRANSIENT_TIMEOUT_PREFIX ) ),
				'is_site_transient' => false,
			);
		}

		// Fallback for value options (not timeout options).
		if ( strpos( $option_name, '_site_transient_' ) === 0 ) {
			return array(
				'name'              => substr( $option_name, strlen( '_site_transient_' ) ),
				'is_site_transient' => true,
			);
		}

		if ( strpos( $option_name, '_transient_' ) === 0 ) {
			return array(
				'name'              => substr( $option_name, strlen( '_transient_' ) ),
				'is_site_transient' => false,
			);
		}

		return array(
			'name'              => $option_name,
			'is_site_transient' => false,
		);
	}

	/**
	 * Get the total count of all transients.
	 *
	 * Counts both regular transients and site transients. On multisite,
	 * site transients are stored in the sitemeta table.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added multisite support for site transients.
	 *
	 * @return int Total number of transients.
	 */
	public function count_transients(): int {
		// If using external object cache, we can't count transients.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		$options_table = $this->connection->get_options_table();
		$count         = 0;

		// Count regular transients (always in options table).
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table}
			WHERE option_name LIKE %s",
			$this->connection->esc_like( '_transient_' ) . '%'
		);

		if ( null !== $query ) {
			$count += absint( $this->connection->get_var( $query ) );
		}

		// Count site transients.
		if ( $this->is_multisite() ) {
			// On multisite, site transients are in sitemeta table.
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT COUNT(*) FROM {$sitemeta_table}
				WHERE meta_key LIKE %s",
				$this->connection->esc_like( '_site_transient_' ) . '%'
			);

			if ( null !== $query ) {
				$count += absint( $this->connection->get_var( $query ) );
			}
		} else {
			// On single site, site transients are in options table.
			$query = $this->connection->prepare(
				"SELECT COUNT(*) FROM {$options_table}
				WHERE option_name LIKE %s",
				$this->connection->esc_like( '_site_transient_' ) . '%'
			);

			if ( null !== $query ) {
				$count += absint( $this->connection->get_var( $query ) );
			}
		}

		// Divide by 2 because each transient has a value and a timeout option.
		return intval( $count / 2 );
	}

	/**
	 * Get expired transients.
	 *
	 * Returns expired transients from both the options table (regular transients)
	 * and sitemeta table (site transients on multisite).
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Returns detailed array per interface, added multisite support.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array<array{name: string, size: int, expired_at: string, is_site_transient: bool}> Array of expired transient data.
	 */
	public function get_expired_transients( array $exclude_patterns = array() ): array {
		// If using external object cache, we can't get expired transients.
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		$transients    = array();
		$current_time  = time();
		$options_table = $this->connection->get_options_table();

		// Get expired regular transients from options table.
		$query = $this->connection->prepare(
			"SELECT option_name, option_value FROM {$options_table}
			WHERE option_name LIKE %s
			AND option_value < %d
			ORDER BY option_name ASC",
			$this->connection->esc_like( self::TRANSIENT_TIMEOUT_PREFIX ) . '%',
			$current_time
		);

		if ( null !== $query ) {
			$results = $this->connection->get_results( $query, 'ARRAY_A' );

			foreach ( $results as $row ) {
				$extracted = $this->extract_transient_name( $row['option_name'] );

				// Check if this transient should be excluded.
				if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
					continue;
				}

				$transients[] = array(
					'name'              => $extracted['name'],
					'size'              => $this->estimate_transient_size( $extracted['name'] ),
					'expired_at'        => gmdate( 'Y-m-d H:i:s', (int) $row['option_value'] ),
					'is_site_transient' => false,
				);
			}
		}

		// Get expired site transients.
		if ( $this->is_multisite() ) {
			// On multisite, site transients are in sitemeta table.
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT meta_key, meta_value FROM {$sitemeta_table}
				WHERE meta_key LIKE %s
				AND meta_value < %d
				ORDER BY meta_key ASC",
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%',
				$current_time
			);

			if ( null !== $query ) {
				$results = $this->connection->get_results( $query, 'ARRAY_A' );

				foreach ( $results as $row ) {
					$extracted = $this->extract_transient_name( $row['meta_key'] );

					if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
						continue;
					}

					$transients[] = array(
						'name'              => $extracted['name'],
						'size'              => $this->estimate_site_transient_size_multisite( $extracted['name'] ),
						'expired_at'        => gmdate( 'Y-m-d H:i:s', (int) $row['meta_value'] ),
						'is_site_transient' => true,
					);
				}
			}
		} else {
			// On single site, site transients are in options table.
			$query = $this->connection->prepare(
				"SELECT option_name, option_value FROM {$options_table}
				WHERE option_name LIKE %s
				AND option_value < %d
				ORDER BY option_name ASC",
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%',
				$current_time
			);

			if ( null !== $query ) {
				$results = $this->connection->get_results( $query, 'ARRAY_A' );

				foreach ( $results as $row ) {
					$extracted = $this->extract_transient_name( $row['option_name'] );

					if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
						continue;
					}

					$transients[] = array(
						'name'              => $extracted['name'],
						'size'              => $this->estimate_transient_size( $extracted['name'] ),
						'expired_at'        => gmdate( 'Y-m-d H:i:s', (int) $row['option_value'] ),
						'is_site_transient' => true,
					);
				}
			}
		}

		return $transients;
	}


	/**
	 * Get the count of expired transients.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of expired transients.
	 */
	public function count_expired_transients(): int {
		$expired_transients = $this->get_expired_transients();
		return count( $expired_transients );
	}

	/**
	 * Get an estimate of the disk space used by transients.
	 *
	 * Calculates size for both options table (regular transients and site transients
	 * on single site) and sitemeta table (site transients on multisite).
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added multisite support for site transients.
	 *
	 * @return int Estimated bytes used by transients.
	 */
	public function get_transients_size(): int {
		// If using external object cache, we can't calculate size.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		$options_table       = $this->connection->get_options_table();
		$size                = 0;
		$overhead_multiplier = 1.5;

		// Get sum of regular transients from options table.
		$query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(option_name) +
				LENGTH(option_value)
			) as total_size
			FROM {$options_table}
			WHERE option_name LIKE %s",
			$this->connection->esc_like( '_transient_' ) . '%'
		);

		if ( null !== $query ) {
			$size += absint( $this->connection->get_var( $query ) );
		}

		// Get size of site transients.
		if ( $this->is_multisite() ) {
			// On multisite, site transients are in sitemeta table.
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT SUM(
					LENGTH(meta_key) +
					LENGTH(meta_value)
				) as total_size
				FROM {$sitemeta_table}
				WHERE meta_key LIKE %s",
				$this->connection->esc_like( '_site_transient_' ) . '%'
			);

			if ( null !== $query ) {
				$size += absint( $this->connection->get_var( $query ) );
			}
		} else {
			// On single site, site transients are in options table.
			$query = $this->connection->prepare(
				"SELECT SUM(
					LENGTH(option_name) +
					LENGTH(option_value)
				) as total_size
				FROM {$options_table}
				WHERE option_name LIKE %s",
				$this->connection->esc_like( '_site_transient_' ) . '%'
			);

			if ( null !== $query ) {
				$size += absint( $this->connection->get_var( $query ) );
			}
		}

		// Add overhead estimate (row overhead, indexes, etc.).
		return absint( $size * $overhead_multiplier );
	}

	/**
	 * Delete expired transients.
	 *
	 * Uses WordPress functions (delete_transient/delete_site_transient) for proper
	 * cache invalidation and multisite handling. Processes in batches to prevent timeout.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Improved to work with new get_expired_transients() format.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array{deleted: int, bytes_freed: int} Deletion result.
	 */
	public function delete_expired_transients( array $exclude_patterns = array() ): array {
		// If using external object cache, we can't delete transients.
		if ( wp_using_ext_object_cache() ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Get expired transients (returns array of transient data with name, size, etc.).
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
			$result         = $this->delete_transients_batch( $batch );
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
	 * Deletes both regular transients and site transients from all relevant tables.
	 * On multisite, site transients are stored in sitemeta table.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added multisite support.
	 *
	 * @param array $exclude_patterns Array of prefixes to exclude (e.g., ['wpha_', 'wc_']).
	 * @return array{deleted: int, bytes_freed: int} Deletion result.
	 */
	public function delete_all_transients( array $exclude_patterns = array() ): array {
		// If using external object cache, we can't delete transients.
		if ( wp_using_ext_object_cache() ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		$transients    = array();
		$options_table = $this->connection->get_options_table();

		// Get all regular transient timeout option names from options table.
		$query = $this->connection->prepare(
			"SELECT option_name FROM {$options_table}
			WHERE option_name LIKE %s
			ORDER BY option_name ASC",
			$this->connection->esc_like( self::TRANSIENT_TIMEOUT_PREFIX ) . '%'
		);

		if ( null !== $query ) {
			$results = $this->connection->get_col( $query );

			foreach ( $results as $option_name ) {
				$extracted = $this->extract_transient_name( $option_name );

				if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
					continue;
				}

				$transients[] = array(
					'name'              => $extracted['name'],
					'size'              => $this->estimate_transient_size( $extracted['name'] ),
					'is_site_transient' => false,
				);
			}
		}

		// Get site transients.
		if ( $this->is_multisite() ) {
			// On multisite, site transients are in sitemeta table.
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT meta_key FROM {$sitemeta_table}
				WHERE meta_key LIKE %s
				ORDER BY meta_key ASC",
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%'
			);

			if ( null !== $query ) {
				$results = $this->connection->get_col( $query );

				foreach ( $results as $meta_key ) {
					$extracted = $this->extract_transient_name( $meta_key );

					if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
						continue;
					}

					$transients[] = array(
						'name'              => $extracted['name'],
						'size'              => $this->estimate_site_transient_size_multisite( $extracted['name'] ),
						'is_site_transient' => true,
					);
				}
			}
		} else {
			// On single site, site transients are in options table.
			$query = $this->connection->prepare(
				"SELECT option_name FROM {$options_table}
				WHERE option_name LIKE %s
				ORDER BY option_name ASC",
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%'
			);

			if ( null !== $query ) {
				$results = $this->connection->get_col( $query );

				foreach ( $results as $option_name ) {
					$extracted = $this->extract_transient_name( $option_name );

					if ( $this->should_exclude_transient( $extracted['name'], $exclude_patterns ) ) {
						continue;
					}

					$transients[] = array(
						'name'              => $extracted['name'],
						'size'              => $this->estimate_transient_size( $extracted['name'] ),
						'is_site_transient' => true,
					);
				}
			}
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
			$result         = $this->delete_transients_batch( $batch );
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
	 * Returns transients matching the given prefix from both options table
	 * and sitemeta table (on multisite). Only returns transient value entries
	 * (not timeout entries) to avoid duplicates.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Returns detailed array per interface, added multisite support.
	 *
	 * @param string $prefix The prefix to search for.
	 * @return array<array{name: string, size: int, expires_at: string|null, is_site_transient: bool}> Array of matching transients.
	 */
	public function get_transient_by_prefix( string $prefix ): array {
		// If using external object cache, we can't get transients by prefix.
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		$options_table = $this->connection->get_options_table();
		$prefix        = sanitize_text_field( $prefix );
		$transients    = array();
		$seen_names    = array();

		// Search for regular transients with the given prefix (value entries only).
		$query = $this->connection->prepare(
			"SELECT option_name FROM {$options_table}
			WHERE option_name LIKE %s
			AND option_name NOT LIKE %s
			ORDER BY option_name ASC",
			$this->connection->esc_like( '_transient_' . $prefix ) . '%',
			$this->connection->esc_like( '_transient_timeout_' ) . '%'
		);

		if ( null !== $query ) {
			$results = $this->connection->get_col( $query );

			foreach ( $results as $option_name ) {
				$extracted      = $this->extract_transient_name( $option_name );
				$transient_name = $extracted['name'];

				// Skip if we've already seen this transient or it doesn't match prefix.
				if ( isset( $seen_names[ $transient_name ] ) || strpos( $transient_name, $prefix ) !== 0 ) {
					continue;
				}

				$seen_names[ $transient_name ] = true;
				$expires_at                    = $this->get_transient_expiration( $transient_name, false );

				$transients[] = array(
					'name'              => $transient_name,
					'size'              => $this->estimate_transient_size( $transient_name ),
					'expires_at'        => $expires_at,
					'is_site_transient' => false,
				);
			}
		}

		// Search for site transients.
		if ( $this->is_multisite() ) {
			// On multisite, site transients are in sitemeta table.
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT meta_key FROM {$sitemeta_table}
				WHERE meta_key LIKE %s
				AND meta_key NOT LIKE %s
				ORDER BY meta_key ASC",
				$this->connection->esc_like( '_site_transient_' . $prefix ) . '%',
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%'
			);

			if ( null !== $query ) {
				$results = $this->connection->get_col( $query );

				foreach ( $results as $meta_key ) {
					$extracted      = $this->extract_transient_name( $meta_key );
					$transient_name = $extracted['name'];

					if ( isset( $seen_names[ $transient_name ] ) || strpos( $transient_name, $prefix ) !== 0 ) {
						continue;
					}

					$seen_names[ $transient_name ] = true;
					$expires_at                    = $this->get_transient_expiration( $transient_name, true );

					$transients[] = array(
						'name'              => $transient_name,
						'size'              => $this->estimate_site_transient_size_multisite( $transient_name ),
						'expires_at'        => $expires_at,
						'is_site_transient' => true,
					);
				}
			}
		} else {
			// On single site, site transients are in options table.
			$query = $this->connection->prepare(
				"SELECT option_name FROM {$options_table}
				WHERE option_name LIKE %s
				AND option_name NOT LIKE %s
				ORDER BY option_name ASC",
				$this->connection->esc_like( '_site_transient_' . $prefix ) . '%',
				$this->connection->esc_like( self::SITE_TRANSIENT_TIMEOUT_PREFIX ) . '%'
			);

			if ( null !== $query ) {
				$results = $this->connection->get_col( $query );

				foreach ( $results as $option_name ) {
					$extracted      = $this->extract_transient_name( $option_name );
					$transient_name = $extracted['name'];

					if ( isset( $seen_names[ $transient_name ] ) || strpos( $transient_name, $prefix ) !== 0 ) {
						continue;
					}

					$seen_names[ $transient_name ] = true;
					$expires_at                    = $this->get_transient_expiration( $transient_name, true );

					$transients[] = array(
						'name'              => $transient_name,
						'size'              => $this->estimate_transient_size( $transient_name ),
						'expires_at'        => $expires_at,
						'is_site_transient' => true,
					);
				}
			}
		}

		return $transients;
	}

	/**
	 * Get the expiration timestamp for a transient.
	 *
	 * @since 1.4.0
	 *
	 * @param string $transient_name   The transient name.
	 * @param bool   $is_site_transient Whether this is a site transient.
	 * @return string|null Expiration date in Y-m-d H:i:s format, or null if no expiration.
	 */
	private function get_transient_expiration( string $transient_name, bool $is_site_transient ): ?string {
		if ( $is_site_transient && $this->is_multisite() ) {
			$sitemeta_table = $this->get_sitemeta_table();
			$query          = $this->connection->prepare(
				"SELECT meta_value FROM {$sitemeta_table}
				WHERE meta_key = %s
				LIMIT 1",
				self::SITE_TRANSIENT_TIMEOUT_PREFIX . $transient_name
			);
		} else {
			$options_table = $this->connection->get_options_table();
			$timeout_key   = $is_site_transient
				? self::SITE_TRANSIENT_TIMEOUT_PREFIX . $transient_name
				: self::TRANSIENT_TIMEOUT_PREFIX . $transient_name;

			$query = $this->connection->prepare(
				"SELECT option_value FROM {$options_table}
				WHERE option_name = %s
				LIMIT 1",
				$timeout_key
			);
		}

		if ( null === $query ) {
			return null;
		}

		$timeout = $this->connection->get_var( $query );

		if ( null === $timeout || '' === $timeout ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', (int) $timeout );
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
	 * Uses WordPress functions (delete_transient/delete_site_transient) for proper
	 * cache invalidation. This approach handles race conditions gracefully - if another
	 * process deletes the transient first, the WordPress function returns false and
	 * we simply don't count it as deleted.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Updated to accept transient data arrays with is_site_transient flag.
	 *
	 * @param array<array{name: string, size: int, is_site_transient: bool}> $transient_data Array of transient data to delete.
	 * @return array{deleted: int, bytes_freed: int} Deletion result.
	 */
	private function delete_transients_batch( array $transient_data ): array {
		$deleted_count = 0;
		$bytes_freed   = 0;

		foreach ( $transient_data as $transient ) {
			$transient_name    = $transient['name'];
			$is_site_transient = $transient['is_site_transient'] ?? false;
			$size_estimate     = $transient['size'] ?? 0;

			// Delete the transient using WordPress functions.
			// These functions handle the deletion atomically and return false if
			// the transient doesn't exist (e.g., was deleted by another process).
			if ( $is_site_transient ) {
				$result = delete_site_transient( $transient_name );
			} else {
				$result = delete_transient( $transient_name );
			}

			if ( $result ) {
				++$deleted_count;
				$bytes_freed += $size_estimate;
			}
		}

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Estimate the size of a single transient from the options table.
	 *
	 * Used for regular transients and site transients on single-site installations.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Updated docblock, now only checks options table.
	 *
	 * @param string $transient_name The transient name.
	 * @return int Estimated size in bytes.
	 */
	private function estimate_transient_size( string $transient_name ): int {
		$options_table       = $this->connection->get_options_table();
		$overhead_multiplier = 1.5;

		// Get sizes for regular and site transient options in options table.
		$query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(option_name) +
				LENGTH(option_value)
			) as size
			FROM {$options_table}
			WHERE option_name IN (%s, %s, %s, %s)",
			'_transient_' . $transient_name,
			self::TRANSIENT_TIMEOUT_PREFIX . $transient_name,
			'_site_transient_' . $transient_name,
			self::SITE_TRANSIENT_TIMEOUT_PREFIX . $transient_name
		);

		if ( null === $query ) {
			return 0;
		}

		$size = absint( $this->connection->get_var( $query ) );

		return absint( $size * $overhead_multiplier );
	}

	/**
	 * Estimate the size of a site transient from the sitemeta table (multisite only).
	 *
	 * @since 1.4.0
	 *
	 * @param string $transient_name The transient name.
	 * @return int Estimated size in bytes.
	 */
	private function estimate_site_transient_size_multisite( string $transient_name ): int {
		if ( ! $this->is_multisite() ) {
			return $this->estimate_transient_size( $transient_name );
		}

		$sitemeta_table      = $this->get_sitemeta_table();
		$overhead_multiplier = 1.5;

		$query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(meta_key) +
				LENGTH(meta_value)
			) as size
			FROM {$sitemeta_table}
			WHERE meta_key IN (%s, %s)",
			'_site_transient_' . $transient_name,
			self::SITE_TRANSIENT_TIMEOUT_PREFIX . $transient_name
		);

		if ( null === $query ) {
			return 0;
		}

		$size = absint( $this->connection->get_var( $query ) );

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
		$table_name = $this->connection->get_prefix() . 'wpha_scan_history';

		$result = $this->connection->insert(
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
