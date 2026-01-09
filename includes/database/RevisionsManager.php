<?php
/**
 * Revisions Manager Class
 *
 * Manages WordPress post revisions including analysis, deletion, and cleanup operations.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

use WPAdminHealth\Contracts\RevisionsManagerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Revisions Manager class for managing post revisions.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements RevisionsManagerInterface.
 */
class RevisionsManager implements RevisionsManagerInterface {

	/**
	 * Batch size for processing revisions.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Get revisions for a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to get revisions for.
	 * @return array Array of revision objects.
	 */
	public function get_revisions_by_post( int $post_id ): array {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return array();
		}

		$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'DESC' ) );

		return $revisions;
	}

	/**
	 * Get the total count of all post revisions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Total number of revisions.
	 */
	public function get_all_revisions_count(): int {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'revision'
		);

		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Get an estimate of the disk space used by revisions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Estimated bytes used by revisions.
	 */
	public function get_revisions_size_estimate(): int {
		global $wpdb;

		// Get sum of post_content length and other fields for revisions.
		$query = $wpdb->prepare(
			"SELECT SUM(
				LENGTH(post_content) +
				LENGTH(post_title) +
				LENGTH(post_excerpt) +
				LENGTH(post_name)
			) as total_size
			FROM {$wpdb->posts}
			WHERE post_type = %s",
			'revision'
		);

		$content_size = absint( $wpdb->get_var( $query ) );

		// Get associated postmeta size.
		$meta_query = $wpdb->prepare(
			"SELECT SUM(
				LENGTH(meta_key) +
				LENGTH(meta_value)
			) as meta_size
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = %s",
			'revision'
		);

		$meta_size = absint( $wpdb->get_var( $meta_query ) );

		// Add overhead estimate (row overhead, indexes, etc.).
		$overhead_multiplier = 1.5;

		return absint( ( $content_size + $meta_size ) * $overhead_multiplier );
	}

	/**
	 * Delete revisions for a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to delete revisions for.
	 * @param int $keep    Number of most recent revisions to keep (default 0).
	 * @return array Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function delete_revisions_for_post( int $post_id, int $keep = 0 ): array {
		$post_id = absint( $post_id );
		$keep    = absint( $keep );

		if ( ! $post_id ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Get all revisions for this post, ordered by date descending (newest first).
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'order' => 'DESC',
			)
		);

		if ( empty( $revisions ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Skip the most recent revisions if keep is specified.
		if ( $keep > 0 ) {
			$revisions = array_slice( $revisions, $keep, null, true );
		}

		$deleted_count = 0;
		$bytes_freed   = 0;
		$batch_count   = 0;

		// Start batch processing.
		wp_defer_term_counting( true );

		foreach ( $revisions as $revision ) {
			// Estimate size before deletion.
			$size_estimate = $this->estimate_revision_size( $revision->ID );

			// Delete the revision using WordPress function.
			$result = wp_delete_post_revision( $revision->ID );

			if ( $result ) {
				$deleted_count++;
				$bytes_freed += $size_estimate;
				$batch_count++;

				// Process in batches to prevent timeout.
				if ( $batch_count >= self::BATCH_SIZE ) {
					wp_defer_term_counting( false );
					wp_defer_term_counting( true );
					$batch_count = 0;
				}
			}
		}

		// End batch processing.
		wp_defer_term_counting( false );

		// Log to scan history.
		$this->log_deletion(
			'revision_post_cleanup',
			$deleted_count,
			$deleted_count,
			$bytes_freed
		);

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Delete all revisions across all posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $keep Number of most recent revisions to keep per post (default 0).
	 * @return array Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function delete_all_revisions( int $keep = 0 ): array {
		global $wpdb;

		$keep = absint( $keep );

		// Get all posts that have revisions.
		$posts_with_revisions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_parent
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_parent > 0
				ORDER BY post_parent ASC",
				'revision'
			)
		);

		if ( empty( $posts_with_revisions ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		$total_deleted    = 0;
		$total_bytes_freed = 0;

		// Start batch processing.
		wp_defer_term_counting( true );
		$batch_count = 0;

		foreach ( $posts_with_revisions as $post_id ) {
			$result = $this->delete_revisions_for_post_internal( $post_id, $keep, $batch_count );

			$total_deleted     += $result['deleted'];
			$total_bytes_freed += $result['bytes_freed'];
			$batch_count        = $result['batch_count'];

			// Reset batch counter periodically.
			if ( $batch_count >= self::BATCH_SIZE ) {
				wp_defer_term_counting( false );
				wp_defer_term_counting( true );
				$batch_count = 0;
			}
		}

		// End batch processing.
		wp_defer_term_counting( false );

		// Log to scan history.
		$this->log_deletion(
			'revision_bulk_cleanup',
			$total_deleted,
			$total_deleted,
			$total_bytes_freed
		);

		return array(
			'deleted'     => $total_deleted,
			'bytes_freed' => $total_bytes_freed,
		);
	}

	/**
	 * Get posts with the most revisions.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Number of posts to return (default 10).
	 * @return array Array of posts with revision counts.
	 */
	public function get_posts_with_most_revisions( int $limit = 10 ): array {
		global $wpdb;

		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$query = $wpdb->prepare(
			"SELECT
				p.ID as post_id,
				p.post_title,
				p.post_type,
				COUNT(r.ID) as revision_count
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->posts} r ON p.ID = r.post_parent
			WHERE r.post_type = %s
			GROUP BY p.ID
			ORDER BY revision_count DESC
			LIMIT %d",
			'revision',
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * Internal method to delete revisions for a post without separate logging.
	 *
	 * @param int $post_id     The post ID.
	 * @param int $keep_count  Number of revisions to keep.
	 * @param int $batch_count Current batch count.
	 * @return array Array with 'deleted', 'bytes_freed', and 'batch_count'.
	 */
	private function delete_revisions_for_post_internal( $post_id, $keep_count = 0, $batch_count = 0 ) {
		// Get all revisions for this post, ordered by date descending.
		$revisions = wp_get_post_revisions(
			$post_id,
			array(
				'order' => 'DESC',
			)
		);

		if ( empty( $revisions ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'batch_count' => $batch_count,
			);
		}

		// Skip the most recent revisions if keep_count is specified.
		if ( $keep_count > 0 ) {
			$revisions = array_slice( $revisions, $keep_count, null, true );
		}

		$deleted_count = 0;
		$bytes_freed   = 0;

		foreach ( $revisions as $revision ) {
			// Estimate size before deletion.
			$size_estimate = $this->estimate_revision_size( $revision->ID );

			// Delete the revision.
			$result = wp_delete_post_revision( $revision->ID );

			if ( $result ) {
				$deleted_count++;
				$bytes_freed += $size_estimate;
				$batch_count++;
			}
		}

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
			'batch_count' => $batch_count,
		);
	}

	/**
	 * Estimate the size of a single revision.
	 *
	 * @param int $revision_id The revision ID.
	 * @return int Estimated size in bytes.
	 */
	private function estimate_revision_size( $revision_id ) {
		global $wpdb;

		$revision_id = absint( $revision_id );

		// Get revision post data size.
		$post_query = $wpdb->prepare(
			"SELECT
				LENGTH(post_content) +
				LENGTH(post_title) +
				LENGTH(post_excerpt) +
				LENGTH(post_name)
			as size
			FROM {$wpdb->posts}
			WHERE ID = %d",
			$revision_id
		);

		$post_size = absint( $wpdb->get_var( $post_query ) );

		// Get associated postmeta size.
		$meta_query = $wpdb->prepare(
			"SELECT SUM(
				LENGTH(meta_key) +
				LENGTH(meta_value)
			) as meta_size
			FROM {$wpdb->postmeta}
			WHERE post_id = %d",
			$revision_id
		);

		$meta_size = absint( $wpdb->get_var( $meta_query ) );

		// Add overhead estimate.
		$overhead_multiplier = 1.5;

		return absint( ( $post_size + $meta_size ) * $overhead_multiplier );
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
