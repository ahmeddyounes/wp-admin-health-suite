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

use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Database\Revisions_Manager;
use WPAdminHealth\Database\Transients_Cleaner;
use WPAdminHealth\Database\Orphaned_Cleaner;
use WPAdminHealth\Database\Orphaned_Tables;
use WPAdminHealth\Database\Trash_Cleaner;
use WPAdminHealth\Media\Scanner;
use WPAdminHealth\Performance\Query_Monitor;
use WPAdminHealth\Performance\Plugin_Profiler;
use WPAdminHealth\Performance\Cache_Checker;
use WPAdminHealth\Performance\Heartbeat_Controller;

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
	 * Transient key for cached recommendations.
	 *
	 * @var string
	 */
	private $cache_key = 'wpha_ai_recommendations';

	/**
	 * Transient key for dismissed recommendations.
	 *
	 * @var string
	 */
	private $dismissed_key = 'wpha_ai_dismissed_recommendations';

	/**
	 * Cache expiration time (24 hours).
	 *
	 * @var int
	 */
	private $cache_expiration = DAY_IN_SECONDS;

	/**
	 * Generate recommendations based on all scan results.
	 *
 * @since 1.0.0
 *
	 * @param bool $force_refresh Force regeneration of recommendations.
	 * @return array Array of recommendation objects.
	 */
	public function generate_recommendations( $force_refresh = false ) {
		// Check cache if not forcing refresh.
		if ( ! $force_refresh ) {
			$cached = get_transient( $this->cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
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
		set_transient( $this->cache_key, $recommendations, $this->cache_expiration );

		return $this->filter_dismissed( $recommendations );
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
					$score += 1;
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
						$score += 1;
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
						$score += 1;
						break;
					case 'high':
						$score -= 1;
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
 * @since 1.0.0
 *
	 * @param string $recommendation_id Recommendation ID to dismiss.
	 * @return bool True on success, false on failure.
	 */
	public function dismiss_recommendation( $recommendation_id ) {
		$dismissed = get_option( $this->dismissed_key, array() );

		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		if ( in_array( $recommendation_id, $dismissed, true ) ) {
			return true; // Already dismissed.
		}

		$dismissed[] = $recommendation_id;
		return update_option( $this->dismissed_key, $dismissed );
	}

	/**
	 * Filter out dismissed recommendations.
	 *
	 * @param array $recommendations Array of recommendations.
	 * @return array Filtered recommendations.
	 */
	private function filter_dismissed( $recommendations ) {
		$dismissed = get_option( $this->dismissed_key, array() );

		if ( ! is_array( $dismissed ) || empty( $dismissed ) ) {
			return $recommendations;
		}

		return array_filter(
			$recommendations,
			function ( $recommendation ) use ( $dismissed ) {
				return ! in_array( $recommendation['id'], $dismissed, true );
			}
		);
	}

	/**
	 * Get database statistics from analyzer.
	 *
	 * @return array Database statistics.
	 */
	private function get_database_stats() {
		try {
			$analyzer = new Analyzer();
			$revisions = new Revisions_Manager();
			$transients = new Transients_Cleaner();
			$orphaned = new Orphaned_Cleaner();
			$orphaned_tables = new Orphaned_Tables();
			$trash = new Trash_Cleaner();

			return array(
				'total_size' => $analyzer->get_total_database_size(),
				'table_count' => $analyzer->get_table_count(),
				'overhead' => $analyzer->get_total_overhead(),
				'revisions_count' => $revisions->get_total_revisions_count(),
				'transients_count' => $transients->count_transients(),
				'expired_transients_count' => $transients->count_expired_transients(),
				'orphaned_postmeta' => $orphaned->get_orphaned_postmeta_count(),
				'orphaned_termmeta' => $orphaned->get_orphaned_termmeta_count(),
				'orphaned_commentmeta' => $orphaned->get_orphaned_commentmeta_count(),
				'orphaned_tables' => $orphaned_tables->get_all_wp_tables(),
				'spam_comments' => $trash->count_spam_comments(),
				'trashed_posts' => $trash->count_trashed_posts(),
				'trashed_comments' => $trash->count_trashed_comments(),
			);
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Get media statistics from scanner.
	 *
	 * @return array Media statistics.
	 */
	private function get_media_stats() {
		try {
			$scanner = new Scanner();
			$scan_results = get_transient( 'wp_admin_health_media_scan_results' );

			if ( false === $scan_results ) {
				$scan_results = $scanner->scan_all_media();
			}

			return $scan_results;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Get performance statistics.
	 *
	 * @return array Performance statistics.
	 */
	private function get_performance_stats() {
		try {
			$stats = array();

			// Query monitor stats.
			if ( class_exists( 'WPAdminHealth\Performance\Query_Monitor' ) ) {
				$query_monitor = new Query_Monitor();
				$stats['slow_queries'] = get_option( 'wpha_slow_queries', array() );
			}

			// Cache checker stats.
			if ( class_exists( 'WPAdminHealth\Performance\Cache_Checker' ) ) {
				$cache_checker = new Cache_Checker();
				$stats['cache_status'] = get_option( 'wpha_cache_status', array() );
			}

			// Plugin profiler stats.
			if ( class_exists( 'WPAdminHealth\Performance\Plugin_Profiler' ) ) {
				$stats['plugin_performance'] = get_option( 'wpha_plugin_performance', array() );
			}

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
