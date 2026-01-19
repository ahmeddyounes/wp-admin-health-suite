<?php
/**
 * Cache Keys Registry
 *
 * Centralized definitions for all cache keys used throughout the plugin.
 * Provides consistent naming conventions and TTL values.
 *
 * @package WPAdminHealth\Support
 */

namespace WPAdminHealth\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class CacheKeys
 *
 * Central registry for cache key definitions.
 * All cache keys should be defined here to ensure consistency
 * and prevent key collisions across the plugin.
 *
 * Key naming convention:
 * - Format: {domain}_{operation}[_{qualifier}]
 * - Examples: db_analyzer_database_size, perf_autoload_size
 *
 * @since 1.3.0
 */
class CacheKeys {

	/**
	 * Default cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 300;

	/**
	 * Short cache TTL in seconds (1 minute).
	 *
	 * @var int
	 */
	public const SHORT_TTL = 60;

	/**
	 * Long cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	public const LONG_TTL = 3600;

	// =========================================================================
	// Database Analyzer Keys
	// =========================================================================

	/**
	 * Cache key for total database size.
	 *
	 * @var string
	 */
	public const DB_ANALYZER_DATABASE_SIZE = 'db_analyzer_database_size';

	/**
	 * Cache key for table sizes.
	 *
	 * @var string
	 */
	public const DB_ANALYZER_TABLE_SIZES = 'db_analyzer_table_sizes';

	/**
	 * Cache key for total database overhead.
	 *
	 * @var string
	 */
	public const DB_ANALYZER_TOTAL_OVERHEAD = 'db_analyzer_total_overhead';

	// =========================================================================
	// Database Orphaned Tables Keys
	// =========================================================================

	/**
	 * Cache key for orphaned tables list.
	 *
	 * @var string
	 */
	public const DB_ORPHANED_TABLES = 'db_orphaned_tables';

	// =========================================================================
	// Performance Keys
	// =========================================================================

	/**
	 * Cache key for autoload size.
	 *
	 * @var string
	 */
	public const PERF_AUTOLOAD_SIZE = 'perf_autoload_size';

	/**
	 * Cache key for autoload analysis.
	 *
	 * @var string
	 */
	public const PERF_AUTOLOAD_ANALYSIS = 'perf_autoload_analysis';

	/**
	 * Cache key for performance health check results.
	 *
	 * @var string
	 */
	public const PERF_HEALTH_CHECK = 'perf_health_check';

	/**
	 * Cache key for plugin profiler results.
	 *
	 * @var string
	 */
	public const PERF_PLUGIN_PROFILE = 'perf_plugin_profile';

	// =========================================================================
	// Media Keys
	// =========================================================================

	/**
	 * Cache key for media scan results.
	 *
	 * @var string
	 */
	public const MEDIA_SCAN_RESULTS = 'media_scan_results';

	/**
	 * Cache key for duplicate media hashes.
	 *
	 * @var string
	 */
	public const MEDIA_DUPLICATE_HASHES = 'media_duplicate_hashes';

	// =========================================================================
	// Key Registry
	// =========================================================================

	/**
	 * Get all registered cache keys.
	 *
	 * Useful for debugging, cache warming, or bulk invalidation.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> Array of constant name => key value pairs.
	 */
	public static function get_all_keys(): array {
		return array(
			'DB_ANALYZER_DATABASE_SIZE'   => self::DB_ANALYZER_DATABASE_SIZE,
			'DB_ANALYZER_TABLE_SIZES'     => self::DB_ANALYZER_TABLE_SIZES,
			'DB_ANALYZER_TOTAL_OVERHEAD'  => self::DB_ANALYZER_TOTAL_OVERHEAD,
			'DB_ORPHANED_TABLES'          => self::DB_ORPHANED_TABLES,
			'PERF_AUTOLOAD_SIZE'          => self::PERF_AUTOLOAD_SIZE,
			'PERF_AUTOLOAD_ANALYSIS'      => self::PERF_AUTOLOAD_ANALYSIS,
			'PERF_HEALTH_CHECK'           => self::PERF_HEALTH_CHECK,
			'PERF_PLUGIN_PROFILE'         => self::PERF_PLUGIN_PROFILE,
			'MEDIA_SCAN_RESULTS'          => self::MEDIA_SCAN_RESULTS,
			'MEDIA_DUPLICATE_HASHES'      => self::MEDIA_DUPLICATE_HASHES,
		);
	}

	/**
	 * Get keys matching a prefix.
	 *
	 * @since 1.3.0
	 *
	 * @param string $prefix The prefix to match (e.g., 'db_', 'perf_').
	 * @return array<string> Array of matching key values.
	 */
	public static function get_keys_by_prefix( string $prefix ): array {
		$all_keys     = self::get_all_keys();
		$matched_keys = array();

		foreach ( $all_keys as $key ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				$matched_keys[] = $key;
			}
		}

		return $matched_keys;
	}

	/**
	 * Get the default TTL for a cache key.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key The cache key constant value.
	 * @return int TTL in seconds.
	 */
	public static function get_ttl( string $key ): int {
		$ttl_map = array(
			// Database analyzer keys use default TTL.
			self::DB_ANALYZER_DATABASE_SIZE  => self::DEFAULT_TTL,
			self::DB_ANALYZER_TABLE_SIZES    => self::DEFAULT_TTL,
			self::DB_ANALYZER_TOTAL_OVERHEAD => self::DEFAULT_TTL,
			self::DB_ORPHANED_TABLES         => self::LONG_TTL,

			// Performance keys.
			self::PERF_AUTOLOAD_SIZE         => self::DEFAULT_TTL,
			self::PERF_AUTOLOAD_ANALYSIS     => self::DEFAULT_TTL,
			self::PERF_HEALTH_CHECK          => self::DEFAULT_TTL,
			self::PERF_PLUGIN_PROFILE        => self::LONG_TTL,

			// Media keys use longer TTL.
			self::MEDIA_SCAN_RESULTS         => self::LONG_TTL,
			self::MEDIA_DUPLICATE_HASHES     => self::LONG_TTL,
		);

		return $ttl_map[ $key ] ?? self::DEFAULT_TTL;
	}

	/**
	 * Build a qualified cache key with optional suffix.
	 *
	 * Useful for creating dynamic keys based on runtime values.
	 *
	 * @since 1.3.0
	 *
	 * @param string $base_key The base cache key constant.
	 * @param string $suffix   Optional suffix to append.
	 * @return string The qualified cache key.
	 */
	public static function with_suffix( string $base_key, string $suffix ): string {
		return $base_key . '_' . $suffix;
	}

	/**
	 * Check if a key is a valid registered cache key.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key The cache key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_key( string $key ): bool {
		return in_array( $key, self::get_all_keys(), true );
	}
}
