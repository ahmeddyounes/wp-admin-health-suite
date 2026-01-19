<?php
/**
 * AI Recommendations Engine Class
 *
 * Analyzes all scan results and generates prioritized, actionable recommendations
 * to improve WordPress site health across database, media, performance, and security.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\AI;

use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Database\OrphanedTables;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Recommendations class for generating AI-powered site health recommendations.
 *
 * @since 1.0.0
 */
class Recommendations {

	/**
	 * Base cache key for cached recommendations.
	 *
	 * @var string
	 */
	private const CACHE_KEY_BASE = 'wpha_ai_recommendations';

	/**
	 * Base option key for dismissed recommendations.
	 *
	 * @var string
	 */
	private const DISMISSED_KEY_BASE = 'wpha_ai_dismissed_recommendations';

	/**
	 * Cache expiration time (24 hours).
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Database analyzer instance.
	 *
	 * @var AnalyzerInterface|null
	 */
	private ?AnalyzerInterface $analyzer = null;

	/**
	 * Revisions manager instance.
	 *
	 * @var RevisionsManagerInterface|null
	 */
	private ?RevisionsManagerInterface $revisions_manager = null;

	/**
	 * Transients cleaner instance.
	 *
	 * @var TransientsCleanerInterface|null
	 */
	private ?TransientsCleanerInterface $transients_cleaner = null;

	/**
	 * Orphaned cleaner instance.
	 *
	 * @var OrphanedCleanerInterface|null
	 */
	private ?OrphanedCleanerInterface $orphaned_cleaner = null;

	/**
	 * Trash cleaner instance.
	 *
	 * @var TrashCleanerInterface|null
	 */
	private ?TrashCleanerInterface $trash_cleaner = null;

	/**
	 * Media scanner instance.
	 *
	 * @var ScannerInterface|null
	 */
	private ?ScannerInterface $scanner = null;

	/**
	 * Query monitor instance.
	 *
	 * @var QueryMonitorInterface|null
	 */
	private ?QueryMonitorInterface $query_monitor = null;

	/**
	 * Database connection instance.
	 *
	 * @since 1.3.0
	 * @var ConnectionInterface|null
	 */
	private ?ConnectionInterface $connection = null;

	/**
	 * Cache instance.
	 *
	 * @since 1.3.0
	 * @var CacheInterface|null
	 */
	private ?CacheInterface $cache = null;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Added ConnectionInterface and CacheInterface parameters.
	 *
	 * @param AnalyzerInterface|null         $analyzer          Database analyzer.
	 * @param RevisionsManagerInterface|null $revisions_manager Revisions manager.
	 * @param TransientsCleanerInterface|null $transients_cleaner Transients cleaner.
	 * @param OrphanedCleanerInterface|null  $orphaned_cleaner  Orphaned cleaner.
	 * @param TrashCleanerInterface|null     $trash_cleaner     Trash cleaner.
	 * @param ScannerInterface|null          $scanner           Media scanner.
	 * @param QueryMonitorInterface|null     $query_monitor     Query monitor.
	 * @param ConnectionInterface|null       $connection        Database connection.
	 * @param CacheInterface|null            $cache             Cache instance.
	 */
	public function __construct(
		?AnalyzerInterface $analyzer = null,
		?RevisionsManagerInterface $revisions_manager = null,
		?TransientsCleanerInterface $transients_cleaner = null,
		?OrphanedCleanerInterface $orphaned_cleaner = null,
		?TrashCleanerInterface $trash_cleaner = null,
		?ScannerInterface $scanner = null,
		?QueryMonitorInterface $query_monitor = null,
		?ConnectionInterface $connection = null,
		?CacheInterface $cache = null
	) {
		$this->analyzer           = $analyzer;
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->scanner            = $scanner;
		$this->query_monitor      = $query_monitor;
		$this->connection         = $connection;
		$this->cache              = $cache;
	}

	/**
	 * Get the cache key with multisite blog ID suffix.
	 *
	 * In multisite environments, each blog has its own recommendations
	 * to ensure site-specific recommendations are properly isolated.
	 *
	 * @since 1.4.0
	 *
	 * @return string Cache key with optional blog ID suffix.
	 */
	private function get_cache_key(): string {
		$key = self::CACHE_KEY_BASE;

		if ( is_multisite() ) {
			$key .= '_' . get_current_blog_id();
		}

		return $key;
	}

	/**
	 * Get the dismissed option key with multisite blog ID suffix.
	 *
	 * In multisite environments, each blog has its own dismissed recommendations
	 * to ensure site-specific dismissals are properly isolated.
	 *
	 * @since 1.4.0
	 *
	 * @return string Option key with optional blog ID suffix.
	 */
	private function get_dismissed_key(): string {
		$key = self::DISMISSED_KEY_BASE;

		if ( is_multisite() ) {
			$key .= '_' . get_current_blog_id();
		}

		return $key;
	}

	/**
	 * Generate recommendations based on all scan results.
	 *
	 * Uses CacheInterface for caching recommendations with multisite-aware keys.
	 * Falls back to WordPress transients if CacheInterface is not available.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Migrated to CacheInterface with multisite support.
	 *
	 * @param bool $force_refresh Force regeneration of recommendations.
	 * @return array Array of recommendation objects.
	 */
	public function generate_recommendations( $force_refresh = false ) {
		$cache_key = $this->get_cache_key();

		// Check cache if not forcing refresh.
		if ( ! $force_refresh ) {
			$cached = $this->get_from_cache( $cache_key );
			if ( null !== $cached && is_array( $cached ) ) {
				return $this->filter_dismissed( $cached );
			}
		}

		// Gather all scan results.
		$database_stats = $this->get_database_stats();
		$media_stats = $this->get_media_stats();
		$performance_stats = $this->get_performance_stats();

		// Generate recommendations from each category.
		$recommendations = array();
		$recommendations = array_merge( $recommendations, $this->analyze_database( $database_stats ) );
		$recommendations = array_merge( $recommendations, $this->analyze_media( $media_stats ) );
		$recommendations = array_merge( $recommendations, $this->analyze_performance( $performance_stats ) );

		// Prioritize all recommendations.
		$recommendations = $this->prioritize_issues( $recommendations );

		// Cache the recommendations.
		$this->set_to_cache( $cache_key, $recommendations, self::CACHE_EXPIRATION );

		return $this->filter_dismissed( $recommendations );
	}

	/**
	 * Get a value from the cache.
	 *
	 * Uses CacheInterface if available, falls back to WordPress transients.
	 *
	 * @since 1.4.0
	 *
	 * @param string $key Cache key.
	 * @return mixed|null Cached value or null if not found.
	 */
	private function get_from_cache( string $key ) {
		if ( null !== $this->cache ) {
			$value = $this->cache->get( $key );
			// CacheInterface returns null for missing keys (default).
			return $value;
		}

		// Fallback to transients.
		$value = get_transient( $key );
		return false !== $value ? $value : null;
	}

	/**
	 * Set a value in the cache.
	 *
	 * Uses CacheInterface if available, falls back to WordPress transients.
	 *
	 * @since 1.4.0
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool True on success, false on failure.
	 */
	private function set_to_cache( string $key, $value, int $ttl = 0 ): bool {
		if ( null !== $this->cache ) {
			return $this->cache->set( $key, $value, $ttl );
		}

		// Fallback to transients.
		return set_transient( $key, $value, $ttl );
	}

	/**
	 * Delete a value from the cache.
	 *
	 * Uses CacheInterface if available, falls back to WordPress transients.
	 *
	 * @since 1.4.0
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	private function delete_from_cache( string $key ): bool {
		if ( null !== $this->cache ) {
			return $this->cache->delete( $key );
		}

		// Fallback to transients.
		return delete_transient( $key );
	}

	/**
	 * Clear the recommendations cache.
	 *
	 * Clears the cached recommendations, forcing regeneration on next request.
	 * Useful when underlying data has changed significantly.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache(): bool {
		return $this->delete_from_cache( $this->get_cache_key() );
	}

	/**
	 * Prioritize issues based on impact, ease of fix, and risk level.
	 *
	 * @since 1.0.0
	 *
	 * @param array $recommendations Array of recommendations to prioritize.
	 * @return array Sorted array of recommendations with priority scores.
	 */
	public function prioritize_issues( $recommendations ) {
		// Calculate priority score for each recommendation.
		foreach ( $recommendations as &$recommendation ) {
			$score = 0;

			// Impact score (1-4 points).
			switch ( $recommendation['impact_estimate'] ) {
				case 'critical':
					$score += 4;
					break;
				case 'high':
					$score += 3;
					break;
				case 'medium':
					$score += 2;
					break;
				case 'low':
					++$score;
					break;
			}

			// Ease of fix (1-3 points).
			if ( isset( $recommendation['ease_of_fix'] ) ) {
				switch ( $recommendation['ease_of_fix'] ) {
					case 'easy':
						$score += 3;
						break;
					case 'medium':
						$score += 2;
						break;
					case 'hard':
						++$score;
						break;
				}
			}

			// Risk level adjustment (subtract for high risk).
			if ( isset( $recommendation['risk_level'] ) ) {
				switch ( $recommendation['risk_level'] ) {
					case 'low':
						$score += 3;
						break;
					case 'medium':
						++$score;
						break;
					case 'high':
						--$score;
						break;
				}
			}

			// Normalize to 1-10 scale.
			$recommendation['priority'] = max( 1, min( 10, $score ) );
		}

		// Sort by priority (highest first).
		usort(
			$recommendations,
			function ( $a, $b ) {
				return $b['priority'] - $a['priority'];
			}
		);

		return $recommendations;
	}

	/**
	 * Get actionable steps for a specific recommendation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $recommendation_id Recommendation ID.
	 * @return array|null Actionable steps or null if not found.
	 */
	public function get_actionable_steps( $recommendation_id ) {
		$recommendations = $this->generate_recommendations();

		foreach ( $recommendations as $recommendation ) {
			if ( $recommendation['id'] === $recommendation_id ) {
				if ( ! isset( $recommendation['steps'] ) ) {
					return array();
				}
				return $recommendation['steps'];
			}
		}

		return null;
	}

	/**
	 * Dismiss a recommendation.
	 *
	 * Dismissed recommendations are stored in WordPress options (persistent)
	 * rather than cache (temporary) to ensure dismissals persist across
	 * cache clears.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added multisite support via dynamic option key.
	 *
	 * @param string $recommendation_id Recommendation ID to dismiss.
	 * @return bool True on success, false on failure.
	 */
	public function dismiss_recommendation( $recommendation_id ) {
		$dismissed_key = $this->get_dismissed_key();
		$dismissed     = get_option( $dismissed_key, array() );

		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		if ( in_array( $recommendation_id, $dismissed, true ) ) {
			return true; // Already dismissed.
		}

		$dismissed[] = $recommendation_id;
		return update_option( $dismissed_key, $dismissed );
	}

	/**
	 * Restore a dismissed recommendation.
	 *
	 * Removes a recommendation from the dismissed list, allowing it to
	 * appear again in the recommendations list.
	 *
	 * @since 1.4.0
	 *
	 * @param string $recommendation_id Recommendation ID to restore.
	 * @return bool True on success, false on failure.
	 */
	public function restore_recommendation( string $recommendation_id ): bool {
		$dismissed_key = $this->get_dismissed_key();
		$dismissed     = get_option( $dismissed_key, array() );

		if ( ! is_array( $dismissed ) ) {
			return true; // Nothing to restore from.
		}

		$key = array_search( $recommendation_id, $dismissed, true );
		if ( false === $key ) {
			return true; // Not dismissed.
		}

		unset( $dismissed[ $key ] );
		$dismissed = array_values( $dismissed ); // Re-index array.

		return update_option( $dismissed_key, $dismissed );
	}

	/**
	 * Get the list of dismissed recommendation IDs.
	 *
	 * @since 1.4.0
	 *
	 * @return array List of dismissed recommendation IDs.
	 */
	public function get_dismissed_ids(): array {
		$dismissed = get_option( $this->get_dismissed_key(), array() );

		if ( ! is_array( $dismissed ) ) {
			return array();
		}

		return $dismissed;
	}

	/**
	 * Clear all dismissed recommendations.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_dismissed(): bool {
		return delete_option( $this->get_dismissed_key() );
	}

	/**
	 * Filter out dismissed recommendations.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Uses multisite-aware option key.
	 *
	 * @param array $recommendations Array of recommendations.
	 * @return array Filtered recommendations (re-indexed).
	 */
	private function filter_dismissed( $recommendations ) {
		$dismissed = $this->get_dismissed_ids();

		if ( empty( $dismissed ) ) {
			return $recommendations;
		}

		$filtered = array_filter(
			$recommendations,
			function ( $recommendation ) use ( $dismissed ) {
				return ! in_array( $recommendation['id'], $dismissed, true );
			}
		);

		// Re-index the array to ensure clean JSON output.
		return array_values( $filtered );
	}

	/**
	 * Get database statistics from analyzer.
	 *
	 * @return array Database statistics.
	 */
	private function get_database_stats() {
		try {
			$stats = array();

			// Get analyzer stats if available.
			if ( null !== $this->analyzer ) {
				$stats['total_size']  = $this->analyzer->get_total_database_size();
				$stats['table_count'] = $this->analyzer->get_table_count();
				$stats['overhead']    = $this->analyzer->get_total_overhead();
			}

			// Get revision stats if available.
			if ( null !== $this->revisions_manager ) {
				$stats['revisions_count'] = $this->revisions_manager->get_all_revisions_count();
			}

			// Get transient stats if available.
			if ( null !== $this->transients_cleaner ) {
				$stats['transients_count']         = $this->transients_cleaner->count_transients();
				$stats['expired_transients_count'] = $this->transients_cleaner->count_expired_transients();
			}

			// Get orphaned metadata stats if available.
			if ( null !== $this->orphaned_cleaner ) {
				$stats['orphaned_postmeta']    = $this->orphaned_cleaner->count_orphaned_postmeta();
				$stats['orphaned_termmeta']    = $this->orphaned_cleaner->count_orphaned_termmeta();
				$stats['orphaned_commentmeta'] = $this->orphaned_cleaner->count_orphaned_commentmeta();
			}

			// Get orphaned tables.
			if ( null !== $this->connection && null !== $this->cache ) {
				$orphaned_tables          = new OrphanedTables( $this->connection, $this->cache );
				$stats['orphaned_tables'] = $orphaned_tables->find_orphaned_tables();
			}

			// Get trash stats if available.
			if ( null !== $this->trash_cleaner ) {
				$stats['spam_comments']     = $this->trash_cleaner->count_spam_comments();
				$stats['trashed_posts']     = $this->trash_cleaner->count_trashed_posts();
				$stats['trashed_comments']  = $this->trash_cleaner->count_trashed_comments();
			}

			return $stats;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Get media statistics from scanner.
	 *
	 * Returns normalized media statistics with consistent keys for recommendation analysis.
	 * Checks cached results first, then falls back to interface methods.
	 *
	 * @return array Media statistics with keys: unused_count, duplicate_count,
	 *               large_files_count, missing_alt_count, total_count, total_size.
	 */
	private function get_media_stats() {
		try {
			// Check for cached full scan results first (from MediaScanTask).
			$scan_results = get_transient( 'wp_admin_health_media_scan_results' );

			if ( false !== $scan_results && is_array( $scan_results ) ) {
				// Normalize key names for compatibility.
				return $this->normalize_media_stats( $scan_results );
			}

			// Use injected scanner if available via interface methods.
			if ( null !== $this->scanner ) {
				$summary = $this->scanner->get_media_summary();

				// Normalize the summary keys to match what analyze_media() expects.
				return $this->normalize_media_stats( $summary );
			}

			return array();
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Normalize media statistics keys for consistent recommendation analysis.
	 *
	 * Handles different key names from various sources (cached results, interface methods).
	 *
	 * @since 1.5.0
	 *
	 * @param array $stats Raw media statistics.
	 * @return array Normalized statistics with consistent keys.
	 */
	private function normalize_media_stats( array $stats ): array {
		$normalized = array(
			'total_count'       => $stats['total_count'] ?? 0,
			'total_size'        => $stats['total_size'] ?? 0,
			'unused_count'      => $stats['unused_count'] ?? 0,
			'duplicate_count'   => $stats['duplicate_count'] ?? 0,
			// Handle both key variations: large_files_count (from scan_all_media) and large_count (from get_media_summary).
			'large_files_count' => $stats['large_files_count'] ?? $stats['large_count'] ?? 0,
			// missing_alt_count may not be available from get_media_summary().
			'missing_alt_count' => $stats['missing_alt_count'] ?? 0,
		);

		return $normalized;
	}

	/**
	 * Get performance statistics.
	 *
	 * @return array Performance statistics.
	 */
	private function get_performance_stats() {
		try {
			$stats = array();

			// Query monitor stats - use injected instance or cached option.
			if ( null !== $this->query_monitor ) {
				$stats['slow_queries'] = get_option( 'wpha_slow_queries', array() );
			}

			// Cache checker stats from stored option.
			$stats['cache_status'] = get_option( 'wpha_cache_status', array() );

			// Plugin profiler stats from stored option.
			$stats['plugin_performance'] = get_option( 'wpha_plugin_performance', array() );

			return $stats;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Analyze database stats and generate recommendations.
	 *
	 * @param array $stats Database statistics.
	 * @return array Array of recommendations.
	 */
	private function analyze_database( $stats ) {
		$recommendations = array();

		// Revisions cleanup.
		if ( isset( $stats['revisions_count'] ) && $stats['revisions_count'] > 100 ) {
			$severity = $stats['revisions_count'] > 1000 ? 'high' : 'medium';
			$recommendations[] = array(
				'id' => 'db_revisions_cleanup',
				'category' => 'database',
				'title' => 'Clean up post revisions',
				'description' => sprintf(
					'Your database contains %d post revisions. Cleaning these up will reduce database size and improve performance.',
					$stats['revisions_count']
				),
				'impact_estimate' => $severity,
				'ease_of_fix' => 'easy',
				'risk_level' => 'low',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'revisions',
					'endpoint' => '/wpha/v1/database/clean',
				),
				'steps' => array(
					'Navigate to Database Health section',
					'Click "Clean Revisions" button',
					'Confirm the cleanup action',
					'Review the results',
				),
			);
		}

		// Expired transients cleanup.
		if ( isset( $stats['expired_transients_count'] ) && $stats['expired_transients_count'] > 50 ) {
			$recommendations[] = array(
				'id' => 'db_expired_transients',
				'category' => 'database',
				'title' => 'Remove expired transients',
				'description' => sprintf(
					'Found %d expired transients. These are taking up space unnecessarily.',
					$stats['expired_transients_count']
				),
				'impact_estimate' => 'low',
				'ease_of_fix' => 'easy',
				'risk_level' => 'low',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'transients',
					'endpoint' => '/wpha/v1/database/clean',
				),
				'steps' => array(
					'Navigate to Database Health section',
					'Click "Clean Transients" button',
					'Confirm the cleanup action',
				),
			);
		}

		// Database overhead optimization.
		if ( isset( $stats['overhead'] ) && $stats['overhead'] > 1048576 ) { // 1MB
			$overhead_mb = round( $stats['overhead'] / 1048576, 2 );
			$recommendations[] = array(
				'id' => 'db_optimize_tables',
				'category' => 'database',
				'title' => 'Optimize database tables',
				'description' => sprintf(
					'Your database has %s MB of overhead. Optimizing tables will reclaim this space.',
					$overhead_mb
				),
				'impact_estimate' => 'medium',
				'ease_of_fix' => 'easy',
				'risk_level' => 'low',
				'action_type' => 'optimize',
				'action_params' => array(
					'endpoint' => '/wpha/v1/database/optimize',
				),
				'steps' => array(
					'Navigate to Database Health section',
					'Click "Optimize Tables" button',
					'Wait for optimization to complete',
				),
			);
		}

		// Orphaned metadata cleanup.
		$orphaned_total = ( $stats['orphaned_postmeta'] ?? 0 ) +
							( $stats['orphaned_termmeta'] ?? 0 ) +
							( $stats['orphaned_commentmeta'] ?? 0 );

		if ( $orphaned_total > 50 ) {
			$recommendations[] = array(
				'id' => 'db_orphaned_metadata',
				'category' => 'database',
				'title' => 'Clean orphaned metadata',
				'description' => sprintf(
					'Found %d orphaned metadata entries (postmeta, termmeta, commentmeta) that are no longer needed.',
					$orphaned_total
				),
				'impact_estimate' => 'medium',
				'ease_of_fix' => 'easy',
				'risk_level' => 'low',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'orphaned',
					'endpoint' => '/wpha/v1/database/clean',
				),
				'steps' => array(
					'Navigate to Database Health section',
					'Click "Clean Orphaned Data" button',
					'Review the items to be cleaned',
					'Confirm the cleanup action',
				),
			);
		}

		// Spam and trash cleanup.
		$trash_total = ( $stats['spam_comments'] ?? 0 ) +
						( $stats['trashed_posts'] ?? 0 ) +
						( $stats['trashed_comments'] ?? 0 );

		if ( $trash_total > 20 ) {
			$recommendations[] = array(
				'id' => 'db_trash_cleanup',
				'category' => 'database',
				'title' => 'Empty trash and spam',
				'description' => sprintf(
					'You have %d items in trash and spam. Permanently deleting these will free up database space.',
					$trash_total
				),
				'impact_estimate' => 'low',
				'ease_of_fix' => 'easy',
				'risk_level' => 'medium',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'trash',
					'endpoint' => '/wpha/v1/database/clean',
				),
				'steps' => array(
					'Navigate to Database Health section',
					'Click "Empty Trash" button',
					'Confirm the permanent deletion',
				),
			);
		}

		return $recommendations;
	}

	/**
	 * Analyze media stats and generate recommendations.
	 *
	 * @param array $stats Media statistics.
	 * @return array Array of recommendations.
	 */
	private function analyze_media( $stats ) {
		$recommendations = array();

		// Unused media cleanup.
		if ( isset( $stats['unused_count'] ) && $stats['unused_count'] > 10 ) {
			$severity = $stats['unused_count'] > 100 ? 'high' : 'medium';
			$recommendations[] = array(
				'id' => 'media_unused_files',
				'category' => 'media',
				'title' => 'Remove unused media files',
				'description' => sprintf(
					'Found %d media files that are not being used anywhere on your site.',
					$stats['unused_count']
				),
				'impact_estimate' => $severity,
				'ease_of_fix' => 'medium',
				'risk_level' => 'medium',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'unused_media',
					'endpoint' => '/wpha/v1/media/clean',
				),
				'steps' => array(
					'Navigate to Media Health section',
					'Review the list of unused media',
					'Select files to delete or bulk select all',
					'Confirm deletion',
					'Consider creating a backup first',
				),
			);
		}

		// Duplicate files cleanup.
		if ( isset( $stats['duplicate_count'] ) && $stats['duplicate_count'] > 5 ) {
			$recommendations[] = array(
				'id' => 'media_duplicates',
				'category' => 'media',
				'title' => 'Remove duplicate media files',
				'description' => sprintf(
					'Found %d duplicate media files. Removing duplicates will free up storage space.',
					$stats['duplicate_count']
				),
				'impact_estimate' => 'medium',
				'ease_of_fix' => 'medium',
				'risk_level' => 'medium',
				'action_type' => 'cleanup',
				'action_params' => array(
					'type' => 'duplicates',
					'endpoint' => '/wpha/v1/media/clean',
				),
				'steps' => array(
					'Navigate to Media Health section',
					'Review duplicate file groups',
					'Select which copies to keep',
					'Delete the duplicates',
				),
			);
		}

		// Large files optimization.
		if ( isset( $stats['large_files_count'] ) && $stats['large_files_count'] > 10 ) {
			$recommendations[] = array(
				'id' => 'media_large_files',
				'category' => 'media',
				'title' => 'Optimize large image files',
				'description' => sprintf(
					'Found %d large image files. Optimizing these will improve page load times.',
					$stats['large_files_count']
				),
				'impact_estimate' => 'high',
				'ease_of_fix' => 'medium',
				'risk_level' => 'low',
				'action_type' => 'optimize',
				'action_params' => array(
					'type' => 'large_files',
					'endpoint' => '/wpha/v1/media/optimize',
				),
				'steps' => array(
					'Install an image optimization plugin',
					'Bulk optimize large images',
					'Enable lazy loading for images',
					'Consider using WebP format',
				),
			);
		}

		// Missing alt text.
		if ( isset( $stats['missing_alt_count'] ) && $stats['missing_alt_count'] > 5 ) {
			$severity = $stats['missing_alt_count'] > 50 ? 'medium' : 'low';
			$recommendations[] = array(
				'id' => 'media_missing_alt',
				'category' => 'security',
				'title' => 'Add alt text to images',
				'description' => sprintf(
					'Found %d images without alt text. Adding alt text improves accessibility and SEO.',
					$stats['missing_alt_count']
				),
				'impact_estimate' => $severity,
				'ease_of_fix' => 'hard',
				'risk_level' => 'low',
				'action_type' => 'manual',
				'action_params' => array(
					'type' => 'alt_text',
					'endpoint' => '/wpha/v1/media/list',
				),
				'steps' => array(
					'Navigate to Media Library',
					'Filter images without alt text',
					'Edit each image and add descriptive alt text',
					'Consider using AI tools to generate alt text',
				),
			);
		}

		return $recommendations;
	}

	/**
	 * Analyze performance stats and generate recommendations.
	 *
	 * @param array $stats Performance statistics.
	 * @return array Array of recommendations.
	 */
	private function analyze_performance( $stats ) {
		$recommendations = array();

		// Slow queries.
		if ( isset( $stats['slow_queries'] ) && is_array( $stats['slow_queries'] ) && count( $stats['slow_queries'] ) > 5 ) {
			$recommendations[] = array(
				'id' => 'perf_slow_queries',
				'category' => 'performance',
				'title' => 'Optimize slow database queries',
				'description' => sprintf(
					'Detected %d slow database queries that are affecting site performance.',
					count( $stats['slow_queries'] )
				),
				'impact_estimate' => 'high',
				'ease_of_fix' => 'hard',
				'risk_level' => 'low',
				'action_type' => 'manual',
				'action_params' => array(
					'endpoint' => '/wpha/v1/performance/queries',
				),
				'steps' => array(
					'Review the list of slow queries',
					'Identify plugins or themes causing slow queries',
					'Add database indexes if needed',
					'Consider query caching solutions',
					'Contact plugin authors for optimization',
				),
			);
		}

		// Cache not enabled.
		if ( isset( $stats['cache_status'] ) && is_array( $stats['cache_status'] ) ) {
			if ( empty( $stats['cache_status']['object_cache'] ) || empty( $stats['cache_status']['page_cache'] ) ) {
				$recommendations[] = array(
					'id' => 'perf_enable_caching',
					'category' => 'performance',
					'title' => 'Enable caching',
					'description' => 'Caching is not fully enabled. Enabling object and page caching will significantly improve performance.',
					'impact_estimate' => 'critical',
					'ease_of_fix' => 'medium',
					'risk_level' => 'low',
					'action_type' => 'manual',
					'action_params' => array(
						'endpoint' => '/wpha/v1/performance/cache',
					),
					'steps' => array(
						'Install a caching plugin (e.g., WP Super Cache, W3 Total Cache)',
						'Enable page caching',
						'Configure object caching (Redis or Memcached)',
						'Enable browser caching',
						'Test your site after enabling caching',
					),
				);
			}
		}

		// Heavy plugins.
		if ( isset( $stats['plugin_performance'] ) && is_array( $stats['plugin_performance'] ) ) {
			$heavy_plugins = array_filter(
				$stats['plugin_performance'],
				function ( $plugin ) {
					return isset( $plugin['load_time'] ) && $plugin['load_time'] > 0.5;
				}
			);

			if ( count( $heavy_plugins ) > 0 ) {
				$recommendations[] = array(
					'id' => 'perf_heavy_plugins',
					'category' => 'performance',
					'title' => 'Review resource-heavy plugins',
					'description' => sprintf(
						'Found %d plugins with high resource usage. Consider alternatives or optimization.',
						count( $heavy_plugins )
					),
					'impact_estimate' => 'high',
					'ease_of_fix' => 'medium',
					'risk_level' => 'medium',
					'action_type' => 'manual',
					'action_params' => array(
						'endpoint' => '/wpha/v1/performance/plugins',
					),
					'steps' => array(
						'Review the list of resource-heavy plugins',
						'Deactivate plugins that are not essential',
						'Look for lighter alternatives',
						'Contact plugin authors about performance',
						'Consider combining multiple plugins into one',
					),
				);
			}
		}

		return $recommendations;
	}
}
