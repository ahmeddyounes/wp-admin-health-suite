<?php
/**
 * Database Analyzer Class
 *
 * Analyzes WordPress database for size, bloat, and optimization opportunities.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database Analyzer class for analyzing database health and statistics.
 */
class Analyzer {

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
	 * Get the total database size in bytes.
	 *
	 * @return int Total database size in bytes.
	 */
	public function get_database_size() {
		if ( null !== $this->database_size ) {
			return $this->database_size;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT SUM(data_length + index_length) as size
			FROM information_schema.TABLES
			WHERE table_schema = %s",
			DB_NAME
		);

		$result = $wpdb->get_var( $query );

		$this->database_size = absint( $result );

		return $this->database_size;
	}

	/**
	 * Get sizes of all database tables.
	 *
	 * @return array Array of table names and their sizes in bytes.
	 */
	public function get_table_sizes() {
		if ( null !== $this->table_sizes ) {
			return $this->table_sizes;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT table_name as 'table',
			(data_length + index_length) as size
			FROM information_schema.TABLES
			WHERE table_schema = %s
			ORDER BY (data_length + index_length) DESC",
			DB_NAME
		);

		$results = $wpdb->get_results( $query );

		$this->table_sizes = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$this->table_sizes[ $row->table ] = absint( $row->size );
			}
		}

		return $this->table_sizes;
	}

	/**
	 * Get the count of post revisions.
	 *
	 * @return int Number of post revisions.
	 */
	public function get_revisions_count() {
		if ( null !== $this->revisions_count ) {
			return $this->revisions_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'revision'
		);

		$this->revisions_count = absint( $wpdb->get_var( $query ) );

		return $this->revisions_count;
	}

	/**
	 * Get the count of auto-draft posts.
	 *
	 * @return int Number of auto-draft posts.
	 */
	public function get_auto_drafts_count() {
		if ( null !== $this->auto_drafts_count ) {
			return $this->auto_drafts_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
			'auto-draft'
		);

		$this->auto_drafts_count = absint( $wpdb->get_var( $query ) );

		return $this->auto_drafts_count;
	}

	/**
	 * Get the count of trashed posts.
	 *
	 * @return int Number of trashed posts.
	 */
	public function get_trashed_posts_count() {
		if ( null !== $this->trashed_posts_count ) {
			return $this->trashed_posts_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
			'trash'
		);

		$this->trashed_posts_count = absint( $wpdb->get_var( $query ) );

		return $this->trashed_posts_count;
	}

	/**
	 * Get the count of spam comments.
	 *
	 * @return int Number of spam comments.
	 */
	public function get_spam_comments_count() {
		if ( null !== $this->spam_comments_count ) {
			return $this->spam_comments_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
			'spam'
		);

		$this->spam_comments_count = absint( $wpdb->get_var( $query ) );

		return $this->spam_comments_count;
	}

	/**
	 * Get the count of trashed comments.
	 *
	 * @return int Number of trashed comments.
	 */
	public function get_trashed_comments_count() {
		if ( null !== $this->trashed_comments_count ) {
			return $this->trashed_comments_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
			'trash'
		);

		$this->trashed_comments_count = absint( $wpdb->get_var( $query ) );

		return $this->trashed_comments_count;
	}

	/**
	 * Get the count of expired transients.
	 *
	 * @return int Number of expired transients.
	 */
	public function get_expired_transients_count() {
		if ( null !== $this->expired_transients_count ) {
			return $this->expired_transients_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name LIKE %s
			AND option_value < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			time()
		);

		$this->expired_transients_count = absint( $wpdb->get_var( $query ) );

		return $this->expired_transients_count;
	}

	/**
	 * Get the count of orphaned postmeta.
	 *
	 * @return int Number of orphaned postmeta records.
	 */
	public function get_orphaned_postmeta_count() {
		if ( null !== $this->orphaned_postmeta_count ) {
			return $this->orphaned_postmeta_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);

		$this->orphaned_postmeta_count = absint( $wpdb->get_var( $query ) );

		return $this->orphaned_postmeta_count;
	}

	/**
	 * Get the count of orphaned commentmeta.
	 *
	 * @return int Number of orphaned commentmeta records.
	 */
	public function get_orphaned_commentmeta_count() {
		if ( null !== $this->orphaned_commentmeta_count ) {
			return $this->orphaned_commentmeta_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL"
		);

		$this->orphaned_commentmeta_count = absint( $wpdb->get_var( $query ) );

		return $this->orphaned_commentmeta_count;
	}

	/**
	 * Get the count of orphaned termmeta.
	 *
	 * @return int Number of orphaned termmeta records.
	 */
	public function get_orphaned_termmeta_count() {
		if ( null !== $this->orphaned_termmeta_count ) {
			return $this->orphaned_termmeta_count;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->termmeta} tm
			LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL"
		);

		$this->orphaned_termmeta_count = absint( $wpdb->get_var( $query ) );

		return $this->orphaned_termmeta_count;
	}
}
