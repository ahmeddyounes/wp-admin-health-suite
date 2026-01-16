<?php
/**
 * Trash & Spam Cleaner Class
 *
 * Manages WordPress trashed posts and spam/trashed comments.
 * Provides methods to count, identify, and delete trashed content with age-based filtering.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Trash Cleaner class for managing trashed and spam content.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements TrashCleanerInterface.
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 */
class TrashCleaner implements TrashCleanerInterface {

	/**
	 * Batch size for processing trashed items.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

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
	 * Get trashed posts by post types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types Array of post types to query (e.g., ['post', 'page']).
	 *                          If empty, queries all post types.
	 * @return array Array of trashed post objects with ID, post_title, post_type, and post_modified.
	 */
	public function get_trashed_posts( array $post_types = array() ): array {
		$posts_table = $this->connection->get_posts_table();

		// Sanitize post types.
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			$post_types   = array_map( 'sanitize_key', $post_types );
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$query        = $this->connection->prepare(
				"SELECT ID, post_title, post_type, post_modified
				FROM {$posts_table}
				WHERE post_status = 'trash'
				AND post_type IN ($placeholders)
				ORDER BY post_modified DESC",
				...$post_types
			);

			if ( null === $query ) {
				return array();
			}
		} else {
			$query = "SELECT ID, post_title, post_type, post_modified
				FROM {$posts_table}
				WHERE post_status = 'trash'
				ORDER BY post_modified DESC";
		}

		return $this->connection->get_results( $query, 'ARRAY_A' );
	}

	/**
	 * Get the count of trashed posts.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of trashed posts.
	 */
	public function count_trashed_posts(): int {
		$posts_table = $this->connection->get_posts_table();

		$count = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$posts_table}
			WHERE post_status = 'trash'"
		);

		return absint( $count );
	}

	/**
	 * Get the count of spam comments.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of spam comments.
	 */
	public function count_spam_comments(): int {
		$comments_table = $this->connection->get_comments_table();

		$count = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$comments_table}
			WHERE comment_approved = 'spam'"
		);

		return absint( $count );
	}

	/**
	 * Get the count of trashed comments.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of trashed comments.
	 */
	public function count_trashed_comments(): int {
		$comments_table = $this->connection->get_comments_table();

		$count = $this->connection->get_var(
			"SELECT COUNT(*) FROM {$comments_table}
			WHERE comment_approved = 'trash'"
		);

		return absint( $count );
	}

	/**
	 * Delete trashed posts with optional filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_types       Array of post types to delete. If empty, deletes all.
	 * @param int   $older_than_days  Only delete posts trashed more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_posts( array $post_types = array(), int $older_than_days = 0 ): array {
		$posts_table = $this->connection->get_posts_table();

		// Build the query to get trashed posts.
		$where_clauses = array( "post_status = 'trash'" );
		$prepare_args  = array();

		// Filter by post types.
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			$post_types = array_map( 'sanitize_key', $post_types );
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$where_clauses[] = "post_type IN ($placeholders)";
			$prepare_args = array_merge( $prepare_args, $post_types );
		}

		// Filter by age.
		if ( $older_than_days > 0 ) {
			$where_clauses[] = 'post_modified < %s';
			$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );
			$prepare_args[] = $date_threshold;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		if ( ! empty( $prepare_args ) ) {
			$query = $this->connection->prepare(
				"SELECT ID FROM {$posts_table} WHERE {$where_sql} ORDER BY ID ASC",
				...$prepare_args
			);

			if ( null === $query ) {
				return array(
					'deleted' => 0,
					'errors'  => 0,
				);
			}
		} else {
			$query = "SELECT ID FROM {$posts_table} WHERE {$where_sql} ORDER BY ID ASC";
		}

		$post_ids = $this->connection->get_col( $query );

		if ( empty( $post_ids ) ) {
			return array(
				'deleted' => 0,
				'errors'  => 0,
			);
		}

		$deleted_count = 0;
		$error_count   = 0;

		// Process in batches to prevent timeout.
		$batches = array_chunk( $post_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $post_id ) {
				// Use wp_delete_post with force_delete = true for permanent deletion.
				$result = wp_delete_post( $post_id, true );

				if ( $result ) {
					$deleted_count++;
				} else {
					$error_count++;
				}
			}
		}

		return array(
			'deleted' => $deleted_count,
			'errors'  => $error_count,
		);
	}

	/**
	 * Delete spam comments with optional age filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $older_than_days Only delete comments marked as spam more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_spam_comments( int $older_than_days = 0 ): array {
		$comments_table = $this->connection->get_comments_table();

		// Build the query to get spam comments.
		$where_clauses = array( "comment_approved = 'spam'" );
		$prepare_args  = array();

		// Filter by age.
		if ( $older_than_days > 0 ) {
			$where_clauses[] = 'comment_date < %s';
			$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );
			$prepare_args[] = $date_threshold;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		if ( ! empty( $prepare_args ) ) {
			$query = $this->connection->prepare(
				"SELECT comment_ID FROM {$comments_table} WHERE {$where_sql} ORDER BY comment_ID ASC",
				...$prepare_args
			);

			if ( null === $query ) {
				return array(
					'deleted' => 0,
					'errors'  => 0,
				);
			}
		} else {
			$query = "SELECT comment_ID FROM {$comments_table} WHERE {$where_sql} ORDER BY comment_ID ASC";
		}

		$comment_ids = $this->connection->get_col( $query );

		if ( empty( $comment_ids ) ) {
			return array(
				'deleted' => 0,
				'errors'  => 0,
			);
		}

		$deleted_count = 0;
		$error_count   = 0;

		// Process in batches to prevent timeout.
		$batches = array_chunk( $comment_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $comment_id ) {
				// Use wp_delete_comment with force_delete = true for permanent deletion.
				$result = wp_delete_comment( $comment_id, true );

				if ( $result ) {
					$deleted_count++;
				} else {
					$error_count++;
				}
			}
		}

		return array(
			'deleted' => $deleted_count,
			'errors'  => $error_count,
		);
	}

	/**
	 * Delete trashed comments with optional age filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $older_than_days Only delete comments in trash more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_comments( int $older_than_days = 0 ): array {
		$comments_table = $this->connection->get_comments_table();

		// Build the query to get trashed comments.
		$where_clauses = array( "comment_approved = 'trash'" );
		$prepare_args  = array();

		// Filter by age.
		if ( $older_than_days > 0 ) {
			$where_clauses[] = 'comment_date < %s';
			$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );
			$prepare_args[] = $date_threshold;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		if ( ! empty( $prepare_args ) ) {
			$query = $this->connection->prepare(
				"SELECT comment_ID FROM {$comments_table} WHERE {$where_sql} ORDER BY comment_ID ASC",
				...$prepare_args
			);

			if ( null === $query ) {
				return array(
					'deleted' => 0,
					'errors'  => 0,
				);
			}
		} else {
			$query = "SELECT comment_ID FROM {$comments_table} WHERE {$where_sql} ORDER BY comment_ID ASC";
		}

		$comment_ids = $this->connection->get_col( $query );

		if ( empty( $comment_ids ) ) {
			return array(
				'deleted' => 0,
				'errors'  => 0,
			);
		}

		$deleted_count = 0;
		$error_count   = 0;

		// Process in batches to prevent timeout.
		$batches = array_chunk( $comment_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $comment_id ) {
				// Use wp_delete_comment with force_delete = true for permanent deletion.
				$result = wp_delete_comment( $comment_id, true );

				if ( $result ) {
					$deleted_count++;
				} else {
					$error_count++;
				}
			}
		}

		return array(
			'deleted' => $deleted_count,
			'errors'  => $error_count,
		);
	}

	/**
	 * Empty all trash (posts and comments) across all post types.
	 *
	 * This is a convenience method that combines deletion of all trashed posts
	 * and trashed comments without age restrictions.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array with 'posts_deleted', 'comments_deleted', 'posts_errors', 'comments_errors'.
	 */
	public function empty_all_trash(): array {
		// Delete all trashed posts (all post types, no age filter).
		$posts_result = $this->delete_trashed_posts( array(), 0 );

		// Delete all trashed comments (no age filter).
		$comments_result = $this->delete_trashed_comments( 0 );

		return array(
			'posts_deleted'     => $posts_result['deleted'],
			'posts_errors'      => $posts_result['errors'],
			'comments_deleted'  => $comments_result['deleted'],
			'comments_errors'   => $comments_result['errors'],
		);
	}
}
