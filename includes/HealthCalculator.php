<?php
/**
 * Health Calculator Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Health Calculator class for computing overall site health score.
 *
 * Calculates a comprehensive health score (0-100) based on weighted factors:
 * - Database bloat (25%)
 * - Unused media (20%)
 * - Plugin performance (25%)
 * - Revision count (15%)
 * - Transient bloat (15%)
 *
 * @since 1.0.0
 */
class HealthCalculator {

	/**
	 * Transient key for caching health score.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'wpha_health_score';

	/**
	 * Default transient expiration time (1 hour).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Factor weights for score calculation.
	 *
	 * @var array
	 */
	private $weights = array(
		'database_bloat'     => 0.25,
		'unused_media'       => 0.20,
		'plugin_performance' => 0.25,
		'revision_count'     => 0.15,
		'transient_bloat'    => 0.15,
	);

	/**
	 * Grade thresholds.
	 *
	 * @var array
	 */
	private $grade_thresholds = array(
		'A' => 90,
		'B' => 80,
		'C' => 70,
		'D' => 60,
		'F' => 0,
	);

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Settings instance.
	 *
	 * @since 1.6.0
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.6.0 Added SettingsInterface parameter to honor settings-driven behavior.
	 *
	 * @param ConnectionInterface $connection Database connection.
	 * @param SettingsInterface|null $settings Optional settings instance.
	 */
	public function __construct( ConnectionInterface $connection, ?SettingsInterface $settings = null ) {
		$this->connection = $connection;
		$this->settings   = $settings;
	}

	/**
	 * Get health score cache expiration in seconds.
	 *
	 * Honors the `health_score_cache_duration` setting (1-24 hours) when available.
	 *
	 * @since 1.6.0
	 *
	 * @return int Cache expiration in seconds.
	 */
	private function get_cache_expiration(): int {
		if ( ! $this->settings instanceof SettingsInterface ) {
			return self::CACHE_EXPIRATION;
		}

		$hours = absint( $this->settings->get_setting( 'health_score_cache_duration', 1 ) );
		$hours = max( 1, min( 24, $hours ) );

		return $hours * HOUR_IN_SECONDS;
	}

	/**
	 * Calculate overall site health score.
	 *
	 * Computes a weighted score based on all health factors.
	 * Results are cached based on the configured cache duration to prevent repeated heavy queries.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Whether to bypass cache and recalculate.
	 * @return array {
	 *     Health score data.
	 *
	 *     @type int    $score         Overall health score (0-100).
	 *     @type string $grade         Letter grade (A-F).
	 *     @type array  $factor_scores Individual factor scores.
	 *     @type int    $timestamp     Unix timestamp of calculation.
	 * }
	 *
	 * @example
	 * // Get health calculator from container
	 * $calculator = Plugin::get_instance()->get_container()->get( HealthCalculator::class );
	 * $result = $calculator->calculate_overall_score();
	 * echo "Your site health score is: " . $result['score'] . " (Grade: " . $result['grade'] . ")";
	 *
	 * // Force fresh calculation
	 * $fresh_result = $calculator->calculate_overall_score( true );
	 */
	public function calculate_overall_score( $force_refresh = false ) {
		// Check cache first unless forcing refresh.
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Get individual factor scores.
		$factor_scores = $this->get_factor_scores();

		// Calculate weighted overall score.
		$overall_score = 0;
		foreach ( $factor_scores as $factor => $score ) {
			if ( isset( $this->weights[ $factor ] ) ) {
				$overall_score += $score * $this->weights[ $factor ];
			}
		}

		// Round to nearest integer.
		$overall_score = round( $overall_score );

		// Get grade.
		$grade = $this->get_grade( $overall_score );

		// Build result array.
		$result = array(
			'score'         => $overall_score,
			'grade'         => $grade,
			'factor_scores' => $factor_scores,
			'timestamp'     => time(),
		);

		// Cache the result.
		set_transient( self::CACHE_KEY, $result, $this->get_cache_expiration() );

		return $result;
	}

	/**
	 * Get letter grade for a given score.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score Score value (0-100).
	 * @return string Letter grade (A, B, C, D, or F).
	 */
	public function get_grade( $score ) {
		foreach ( $this->grade_thresholds as $grade => $threshold ) {
			if ( $score >= $threshold ) {
				return $grade;
			}
		}
		return 'F';
	}

	/**
	 * Get individual factor scores.
	 *
	 * Calculates scores for each health factor.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Individual factor scores.
	 *
	 *     @type int $database_bloat     Database bloat score (0-100).
	 *     @type int $unused_media       Unused media score (0-100).
	 *     @type int $plugin_performance Plugin performance score (0-100).
	 *     @type int $revision_count     Revision count score (0-100).
	 *     @type int $transient_bloat    Transient bloat score (0-100).
	 * }
	 */
	public function get_factor_scores() {
		return array(
			'database_bloat'     => $this->calculate_database_bloat_score(),
			'unused_media'       => $this->calculate_unused_media_score(),
			'plugin_performance' => $this->calculate_plugin_performance_score(),
			'revision_count'     => $this->calculate_revision_count_score(),
			'transient_bloat'    => $this->calculate_transient_bloat_score(),
		);
	}

	/**
	 * Get actionable recommendations based on health scores.
	 *
	 * Provides specific recommendations for factors scoring below 80.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of recommendation strings.
	 */
	public function get_recommendations() {
		$factor_scores   = $this->get_factor_scores();
		$recommendations = array();

		// Database bloat recommendations.
		if ( $factor_scores['database_bloat'] < 80 ) {
			$recommendations[] = __( 'Consider optimizing your database tables to reduce bloat. Remove post revisions, trashed items, and spam comments.', 'wp-admin-health-suite' );
		}

		// Unused media recommendations.
		if ( $factor_scores['unused_media'] < 80 ) {
			$recommendations[] = __( 'Review and remove unused media files from your media library to free up storage space.', 'wp-admin-health-suite' );
		}

		// Plugin performance recommendations.
		if ( $factor_scores['plugin_performance'] < 80 ) {
			$recommendations[] = __( 'Deactivate or remove unused plugins. Consider replacing poorly performing plugins with better alternatives.', 'wp-admin-health-suite' );
		}

		// Revision count recommendations.
		if ( $factor_scores['revision_count'] < 80 ) {
			$recommendations[] = __( 'Limit the number of post revisions by setting WP_POST_REVISIONS in wp-config.php to reduce database size.', 'wp-admin-health-suite' );
		}

		// Transient bloat recommendations.
		if ( $factor_scores['transient_bloat'] < 80 ) {
			$recommendations[] = __( 'Clean up expired transients from your database to improve performance and reduce database size.', 'wp-admin-health-suite' );
		}

		// If all scores are good.
		if ( empty( $recommendations ) ) {
			$recommendations[] = __( 'Your site health is excellent! Keep up the good maintenance practices.', 'wp-admin-health-suite' );
		}

		return $recommendations;
	}

	/**
	 * Calculate database bloat score.
	 *
	 * Evaluates database bloat based on:
	 * - Trashed posts and pages
	 * - Spam comments
	 * - Post revisions (excessive)
	 * - Orphaned postmeta
	 *
	 * @since 1.0.0
	 *
	 * @return int Score from 0-100 (100 = no bloat, 0 = severe bloat).
	 */
	private function calculate_database_bloat_score() {
		$score       = 100;
		$posts_table = $this->connection->get_posts_table();
		$comments_table = $this->connection->get_comments_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		// Count trashed posts.
		$trashed_posts = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'trash'"
		);
		if ( $trashed_posts > 100 ) {
			$score -= min( 30, ( $trashed_posts - 100 ) / 10 );
		}

		// Count spam comments.
		$spam_comments = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$comments_table} WHERE comment_approved = 'spam'"
		);
		if ( $spam_comments > 50 ) {
			$score -= min( 25, ( $spam_comments - 50 ) / 5 );
		}

		// Count orphaned postmeta.
		$orphaned_meta = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$postmeta_table} pm
			LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);
		if ( $orphaned_meta > 0 ) {
			$score -= min( 20, $orphaned_meta / 10 );
		}

		// Count auto-draft posts.
		$auto_drafts = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = 'auto-draft'"
		);
		if ( $auto_drafts > 20 ) {
			$score -= min( 15, ( $auto_drafts - 20 ) / 5 );
		}

		return max( 0, round( $score ) );
	}

	/**
	 * Calculate unused media score.
	 *
	 * Evaluates media library health based on:
	 * - Total number of media files
	 * - Percentage of unattached media
	 *
	 * @since 1.0.0
	 *
	 * @return int Score from 0-100 (100 = all media used, 0 = mostly unused).
	 */
	private function calculate_unused_media_score() {
		$score       = 100;
		$posts_table = $this->connection->get_posts_table();

		// Count total attachments.
		$total_media = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = 'attachment'"
		);

		if ( $total_media > 0 ) {
			// Count unattached media.
			$unattached_media = $this->connection->get_var(
				"SELECT COUNT(*) FROM {$posts_table}
				WHERE post_type = 'attachment' AND post_parent = 0"
			);

			$unattached_percentage = ( $unattached_media / $total_media ) * 100;

			// Deduct points based on unattached percentage.
			if ( $unattached_percentage > 20 ) {
				$score -= min( 50, ( $unattached_percentage - 20 ) * 1.5 );
			}

			// Deduct points for excessive media count.
			if ( $total_media > 1000 && $unattached_media > 100 ) {
				$score -= min( 30, ( $unattached_media - 100 ) / 20 );
			}
		}

		return max( 0, round( $score ) );
	}

	/**
	 * Calculate plugin performance score.
	 *
	 * Evaluates plugin health based on:
	 * - Total number of active plugins
	 * - Presence of inactive plugins
	 *
	 * @since 1.0.0
	 *
	 * @return int Score from 0-100 (100 = optimal plugins, 0 = too many plugins).
	 */
	private function calculate_plugin_performance_score() {
		$score = 100;

		// Get all plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$total_plugins    = count( $all_plugins );
		$active_count     = count( $active_plugins );
		$inactive_count   = $total_plugins - $active_count;

		// Deduct for too many active plugins.
		if ( $active_count > 20 ) {
			$score -= min( 40, ( $active_count - 20 ) * 2 );
		}

		// Deduct for too many inactive plugins.
		if ( $inactive_count > 5 ) {
			$score -= min( 30, ( $inactive_count - 5 ) * 3 );
		}

		// Deduct for excessive total plugins.
		if ( $total_plugins > 30 ) {
			$score -= min( 20, ( $total_plugins - 30 ) );
		}

		return max( 0, round( $score ) );
	}

	/**
	 * Calculate revision count score.
	 *
	 * Evaluates post revision health based on:
	 * - Average revisions per post
	 * - Total revision count
	 *
	 * @since 1.0.0
	 *
	 * @return int Score from 0-100 (100 = minimal revisions, 0 = excessive revisions).
	 */
	private function calculate_revision_count_score() {
		$score       = 100;
		$posts_table = $this->connection->get_posts_table();

		// Count total revisions.
		$total_revisions = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = 'revision'"
		);

		// Count posts with revisions.
		$posts_with_content = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_type IN ('post', 'page')
			AND post_status NOT IN ('trash', 'auto-draft')"
		);

		if ( $posts_with_content > 0 ) {
			$avg_revisions = $total_revisions / $posts_with_content;

			// Deduct based on average revisions per post.
			if ( $avg_revisions > 5 ) {
				$score -= min( 50, ( $avg_revisions - 5 ) * 5 );
			}
		}

		// Deduct for absolute revision count.
		if ( $total_revisions > 500 ) {
			$score -= min( 30, ( $total_revisions - 500 ) / 50 );
		}

		return max( 0, round( $score ) );
	}

	/**
	 * Calculate transient bloat score.
	 *
	 * Evaluates transient health based on:
	 * - Total number of transients
	 * - Number of expired transients
	 *
	 * @since 1.0.0
	 *
	 * @return int Score from 0-100 (100 = minimal transients, 0 = excessive transients).
	 */
	private function calculate_transient_bloat_score() {
		$score         = 100;
		$options_table = $this->connection->get_options_table();

		// Count total transients (including timeouts).
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table}
			WHERE option_name LIKE %s",
			$this->connection->esc_like( '_transient_' ) . '%'
		);
		$total_transients = $query ? $this->connection->get_var( $query ) : 0;

		// Count expired transients.
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table} t1
			INNER JOIN {$options_table} t2 ON t2.option_name = REPLACE(t1.option_name, '_transient_timeout_', '_transient_')
			WHERE t1.option_name LIKE %s
			AND t1.option_value < %d",
			$this->connection->esc_like( '_transient_timeout_' ) . '%',
			time()
		);
		$expired_transients = $query ? $this->connection->get_var( $query ) : 0;

		// Deduct for too many transients.
		if ( $total_transients > 200 ) {
			$score -= min( 40, ( $total_transients - 200 ) / 20 );
		}

		// Deduct for expired transients.
		if ( $expired_transients > 50 ) {
			$score -= min( 40, ( $expired_transients - 50 ) / 10 );
		}

		return max( 0, round( $score ) );
	}

	/**
	 * Clear the cached health score.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache() {
		return delete_transient( self::CACHE_KEY );
	}
}
