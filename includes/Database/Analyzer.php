<?php
/**
 * Database Analyzer Class
 *
 * Analyzes WordPress database for size, bloat, and optimization opportunities.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database Analyzer class for analyzing database health and statistics.
 *
 * @since 1.0.0
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 */
class Analyzer implements AnalyzerInterface {

	/**
	 * Transient cache expiration time (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'wpha_db_analyzer_';

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Cache for database size.
	 *
	 * @var int|null
	 */
	private $database_size = null;

	/**
	 * Cache for table sizes.
	 *
	 * @var array|null
	 */
	private $table_sizes = null;

	/**
	 * Cache for revisions count.
	 *
	 * @var int|null
	 */
	private $revisions_count = null;

	/**
	 * Cache for auto-drafts count.
	 *
	 * @var int|null
	 */
	private $auto_drafts_count = null;

	/**
	 * Cache for trashed posts count.
	 *
	 * @var int|null
	 */
	private $trashed_posts_count = null;

	/**
	 * Cache for spam comments count.
	 *
	 * @var int|null
	 */
	private $spam_comments_count = null;

	/**
	 * Cache for trashed comments count.
	 *
	 * @var int|null
	 */
	private $trashed_comments_count = null;

	/**
	 * Cache for expired transients count.
	 *
	 * @var int|null
	 */
	private $expired_transients_count = null;

	/**
	 * Cache for orphaned postmeta count.
	 *
	 * @var int|null
	 */
	private $orphaned_postmeta_count = null;

	/**
	 * Cache for orphaned commentmeta count.
	 *
	 * @var int|null
	 */
	private $orphaned_commentmeta_count = null;

	/**
	 * Cache for orphaned termmeta count.
	 *
	 * @var int|null
	 */
	private $orphaned_termmeta_count = null;

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
	 * Get the total database size in bytes.
	 *
	 * Uses transient caching to avoid expensive queries on large databases.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total database size in bytes.
	 */
	public function get_database_size(): int {
		if ( null !== $this->database_size ) {
			return $this->database_size;
		}

		// Try to get from transient cache first.
		$cache_key = self::CACHE_PREFIX . 'database_size';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			$this->database_size = absint( $cached );
			return $this->database_size;
		}

		$query = $this->connection->prepare(
			'SELECT SUM(data_length + index_length) as size
			FROM information_schema.TABLES
			WHERE table_schema = %s',
			DB_NAME
		);

		if ( null === $query ) {
			$this->database_size = 0;
			return $this->database_size;
		}

		$result = $this->connection->get_var( $query );

		$this->database_size = absint( $result );

		// Cache the result.
		set_transient( $cache_key, $this->database_size, self::CACHE_EXPIRATION );

		return $this->database_size;
	}

	/**
	 * Get sizes of all database tables.
	 *
	 * Uses transient caching to avoid expensive queries on large databases.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of table names and their sizes in bytes.
	 */
	public function get_table_sizes(): array {
		if ( null !== $this->table_sizes ) {
			return $this->table_sizes;
		}

		// Try to get from transient cache first.
		$cache_key = self::CACHE_PREFIX . 'table_sizes';
		$cached = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			$this->table_sizes = $cached;
			return $this->table_sizes;
		}

		$query = $this->connection->prepare(
			"SELECT table_name as 'table',
			(data_length + index_length) as size
			FROM information_schema.TABLES
			WHERE table_schema = %s
			ORDER BY (data_length + index_length) DESC",
			DB_NAME
		);

		if ( null === $query ) {
			$this->table_sizes = array();
			return $this->table_sizes;
		}

		$results = $this->connection->get_results( $query );

		$this->table_sizes = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$this->table_sizes[ $row->table ] = absint( $row->size );
			}
		}

		// Cache the result.
		set_transient( $cache_key, $this->table_sizes, self::CACHE_EXPIRATION );

		return $this->table_sizes;
	}

	/**
	 * Get the total database size in bytes.
	 *
	 * Alias for get_database_size() to match expected interface.
	 *
	 * @since 1.2.0
	 *
	 * @return int Total database size in bytes.
	 */
	public function get_total_database_size(): int {
		return $this->get_database_size();
	}

	/**
	 * Get the count of database tables.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of database tables.
	 */
	public function get_table_count(): int {
		return count( $this->get_table_sizes() );
	}

	/**
	 * Get the total database overhead (wasted space) in bytes.
	 *
	 * Overhead is the space that could be reclaimed by optimizing tables.
	 *
	 * @since 1.2.0
	 *
	 * @return int Total overhead in bytes.
	 */
	public function get_total_overhead(): int {
		$cache_key = self::CACHE_PREFIX . 'total_overhead';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return absint( $cached );
		}

		$query = $this->connection->prepare(
			'SELECT SUM(data_free) as overhead
			FROM information_schema.TABLES
			WHERE table_schema = %s',
			DB_NAME
		);

		if ( null === $query ) {
			return 0;
		}

		$result   = $this->connection->get_var( $query );
		$overhead = absint( $result );

		set_transient( $cache_key, $overhead, self::CACHE_EXPIRATION );

		return $overhead;
	}

	/**
	 * Get the count of post revisions.
	 *
 * @since 1.0.0
 *
	 * @return int Number of post revisions.
	 */
	public function get_revisions_count(): int {
		if ( null !== $this->revisions_count ) {
			return $this->revisions_count;
		}

		$posts_table = $this->connection->get_posts_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = %s",
			'revision'
		);

		if ( null === $query ) {
			$this->revisions_count = 0;
			return $this->revisions_count;
		}

		$this->revisions_count = absint( $this->connection->get_var( $query ) );

		return $this->revisions_count;
	}

	/**
	 * Get the count of auto-draft posts.
	 *
 * @since 1.0.0
 *
	 * @return int Number of auto-draft posts.
	 */
	public function get_auto_drafts_count(): int {
		if ( null !== $this->auto_drafts_count ) {
			return $this->auto_drafts_count;
		}

		$posts_table = $this->connection->get_posts_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = %s",
			'auto-draft'
		);

		if ( null === $query ) {
			$this->auto_drafts_count = 0;
			return $this->auto_drafts_count;
		}

		$this->auto_drafts_count = absint( $this->connection->get_var( $query ) );

		return $this->auto_drafts_count;
	}

	/**
	 * Get the count of trashed posts.
	 *
 * @since 1.0.0
 *
	 * @return int Number of trashed posts.
	 */
	public function get_trashed_posts_count(): int {
		if ( null !== $this->trashed_posts_count ) {
			return $this->trashed_posts_count;
		}

		$posts_table = $this->connection->get_posts_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_status = %s",
			'trash'
		);

		if ( null === $query ) {
			$this->trashed_posts_count = 0;
			return $this->trashed_posts_count;
		}

		$this->trashed_posts_count = absint( $this->connection->get_var( $query ) );

		return $this->trashed_posts_count;
	}

	/**
	 * Get the count of spam comments.
	 *
 * @since 1.0.0
 *
	 * @return int Number of spam comments.
	 */
	public function get_spam_comments_count(): int {
		if ( null !== $this->spam_comments_count ) {
			return $this->spam_comments_count;
		}

		$comments_table = $this->connection->get_comments_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$comments_table} WHERE comment_approved = %s",
			'spam'
		);

		if ( null === $query ) {
			$this->spam_comments_count = 0;
			return $this->spam_comments_count;
		}

		$this->spam_comments_count = absint( $this->connection->get_var( $query ) );

		return $this->spam_comments_count;
	}

	/**
	 * Get the count of trashed comments.
	 *
 * @since 1.0.0
 *
	 * @return int Number of trashed comments.
	 */
	public function get_trashed_comments_count(): int {
		if ( null !== $this->trashed_comments_count ) {
			return $this->trashed_comments_count;
		}

		$comments_table = $this->connection->get_comments_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$comments_table} WHERE comment_approved = %s",
			'trash'
		);

		if ( null === $query ) {
			$this->trashed_comments_count = 0;
			return $this->trashed_comments_count;
		}

		$this->trashed_comments_count = absint( $this->connection->get_var( $query ) );

		return $this->trashed_comments_count;
	}

	/**
	 * Get the count of expired transients.
	 *
 * @since 1.0.0
 *
	 * @return int Number of expired transients.
	 */
	public function get_expired_transients_count(): int {
		if ( null !== $this->expired_transients_count ) {
			return $this->expired_transients_count;
		}

		$options_table = $this->connection->get_options_table();
		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table}
			WHERE option_name LIKE %s
			AND option_value < %d",
			$this->connection->esc_like( '_transient_timeout_' ) . '%',
			time()
		);

		if ( null === $query ) {
			$this->expired_transients_count = 0;
			return $this->expired_transients_count;
		}

		$this->expired_transients_count = absint( $this->connection->get_var( $query ) );

		return $this->expired_transients_count;
	}

	/**
	 * Get the count of orphaned postmeta.
	 *
 * @since 1.0.0
 *
	 * @return int Number of orphaned postmeta records.
	 */
	public function get_orphaned_postmeta_count(): int {
		if ( null !== $this->orphaned_postmeta_count ) {
			return $this->orphaned_postmeta_count;
		}

		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		// Query uses connection table methods which are safe - no prepare() needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->orphaned_postmeta_count = absint(
			$this->connection->get_var(
				"SELECT COUNT(*) FROM {$postmeta_table} pm
			LEFT JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
			)
		);

		return $this->orphaned_postmeta_count;
	}

	/**
	 * Get the count of orphaned commentmeta.
	 *
 * @since 1.0.0
 *
	 * @return int Number of orphaned commentmeta records.
	 */
	public function get_orphaned_commentmeta_count(): int {
		if ( null !== $this->orphaned_commentmeta_count ) {
			return $this->orphaned_commentmeta_count;
		}

		$commentmeta_table = $this->connection->get_commentmeta_table();
		$comments_table    = $this->connection->get_comments_table();

		// Query uses connection table methods which are safe - no prepare() needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->orphaned_commentmeta_count = absint(
			$this->connection->get_var(
				"SELECT COUNT(*) FROM {$commentmeta_table} cm
			LEFT JOIN {$comments_table} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL"
			)
		);

		return $this->orphaned_commentmeta_count;
	}

	/**
	 * Get the count of orphaned termmeta.
	 *
 * @since 1.0.0
 *
	 * @return int Number of orphaned termmeta records.
	 */
	public function get_orphaned_termmeta_count(): int {
		if ( null !== $this->orphaned_termmeta_count ) {
			return $this->orphaned_termmeta_count;
		}

		$termmeta_table = $this->connection->get_termmeta_table();
		$terms_table    = $this->connection->get_terms_table();

		// Query uses connection table methods which are safe - no prepare() needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->orphaned_termmeta_count = absint(
			$this->connection->get_var(
				"SELECT COUNT(*) FROM {$termmeta_table} tm
			LEFT JOIN {$terms_table} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL"
			)
		);

		return $this->orphaned_termmeta_count;
	}

	/**
	 * Get a summary of all bloat counts.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> Array of bloat type => count pairs.
	 */
	public function get_bloat_summary(): array {
		return array(
			'revisions'           => $this->get_revisions_count(),
			'auto_drafts'         => $this->get_auto_drafts_count(),
			'trashed_posts'       => $this->get_trashed_posts_count(),
			'spam_comments'       => $this->get_spam_comments_count(),
			'trashed_comments'    => $this->get_trashed_comments_count(),
			'expired_transients'  => $this->get_expired_transients_count(),
			'orphaned_postmeta'   => $this->get_orphaned_postmeta_count(),
			'orphaned_commentmeta' => $this->get_orphaned_commentmeta_count(),
			'orphaned_termmeta'   => $this->get_orphaned_termmeta_count(),
		);
	}

	/**
	 * Get the estimated reclaimable space in bytes.
	 *
	 * Estimates based on average row sizes for different bloat types.
	 *
	 * @since 1.1.0
	 *
	 * @return int Estimated reclaimable space in bytes.
	 */
	public function get_estimated_reclaimable_space(): int {
		// Average row sizes (rough estimates based on typical WordPress data).
		$row_sizes = array(
			'revisions'           => 2048,  // Posts table row with content.
			'auto_drafts'         => 1024,  // Smaller posts.
			'trashed_posts'       => 2048,  // Posts with content.
			'spam_comments'       => 512,   // Comments table row.
			'trashed_comments'    => 512,   // Comments table row.
			'expired_transients'  => 256,   // Options table row.
			'orphaned_postmeta'   => 128,   // Postmeta table row.
			'orphaned_commentmeta' => 128,  // Commentmeta table row.
			'orphaned_termmeta'   => 128,   // Termmeta table row.
		);

		$bloat_summary = $this->get_bloat_summary();
		$total_space   = 0;

		foreach ( $bloat_summary as $type => $count ) {
			if ( isset( $row_sizes[ $type ] ) ) {
				$total_space += $count * $row_sizes[ $type ];
			}
		}

		return $total_space;
	}
}
