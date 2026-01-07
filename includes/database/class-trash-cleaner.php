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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Trash Cleaner class for managing trashed and spam content.
 */
class Trash_Cleaner {

	/**
	 * Batch size for processing trashed items.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Get trashed posts by post types.
	 *
	 * @param array $post_types Array of post types to query (e.g., ['post', 'page']).
	 *                          If empty, queries all post types.
	 * @return array Array of trashed post objects with ID, post_title, post_type, and post_modified.
	 */
	public function get_trashed_posts( $post_types = array() ) {
		global $wpdb;

		// Sanitize post types.
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			$post_types = array_map( 'sanitize_key', $post_types );
			$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$query = $wpdb->prepare(
				"SELECT ID, post_title, post_type, post_modified
				FROM {$wpdb->posts}
				WHERE post_status = 'trash'
				AND post_type IN ($placeholders)
				ORDER BY post_modified DESC",
				$post_types
			);
		} else {
			$query = "SELECT ID, post_title, post_type, post_modified
				FROM {$wpdb->posts}
				WHERE post_status = 'trash'
				ORDER BY post_modified DESC";
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get the count of trashed posts.
	 *
	 * @return int Number of trashed posts.
	 */
	public function count_trashed_posts() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_status = 'trash'"
		);

		return absint( $count );
	}

	/**
	 * Get the count of spam comments.
	 *
	 * @return int Number of spam comments.
	 */
	public function count_spam_comments() {
		return $this->get_spam_comments_count();
	}

	/**
	 * Get the count of spam comments.
	 *
	 * @return int Number of spam comments.
	 */
	public function get_spam_comments_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments}
			WHERE comment_approved = 'spam'"
		);

		return absint( $count );
	}

	/**
	 * Get the count of trashed comments.
	 *
	 * @return int Number of trashed comments.
	 */
	public function count_trashed_comments() {
		return $this->get_trashed_comments_count();
	}

	/**
	 * Get the count of trashed comments.
	 *
	 * @return int Number of trashed comments.
	 */
	public function get_trashed_comments_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments}
			WHERE comment_approved = 'trash'"
		);

		return absint( $count );
	}

	/**
	 * Delete trashed posts with optional filters.
	 *
	 * @param array $post_types       Array of post types to delete. If empty, deletes all.
	 * @param int   $older_than_days  Only delete posts trashed more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_posts( $post_types = array(), $older_than_days = 0 ) {
		global $wpdb;

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
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE {$where_sql} ORDER BY ID ASC",
				$prepare_args
			);
		} else {
			$query = "SELECT ID FROM {$wpdb->posts} WHERE {$where_sql} ORDER BY ID ASC";
		}

		$post_ids = $wpdb->get_col( $query );

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
	 * @param int $older_than_days Only delete comments marked as spam more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_spam_comments( $older_than_days = 0 ) {
		global $wpdb;

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
			$query = $wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE {$where_sql} ORDER BY comment_ID ASC",
				$prepare_args
			);
		} else {
			$query = "SELECT comment_ID FROM {$wpdb->comments} WHERE {$where_sql} ORDER BY comment_ID ASC";
		}

		$comment_ids = $wpdb->get_col( $query );

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
	 * @param int $older_than_days Only delete comments in trash more than X days ago. 0 = all.
	 * @return array Array with 'deleted' count and 'errors' count.
	 */
	public function delete_trashed_comments( $older_than_days = 0 ) {
		global $wpdb;

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
			$query = $wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE {$where_sql} ORDER BY comment_ID ASC",
				$prepare_args
			);
		} else {
			$query = "SELECT comment_ID FROM {$wpdb->comments} WHERE {$where_sql} ORDER BY comment_ID ASC";
		}

		$comment_ids = $wpdb->get_col( $query );

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
	 * @return array Array with 'posts_deleted', 'comments_deleted', 'posts_errors', 'comments_errors'.
	 */
	public function empty_all_trash() {
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
