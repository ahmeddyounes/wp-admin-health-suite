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
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Revisions Manager class for managing post revisions.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements RevisionsManagerInterface.
 * @since 1.3.0 Added constructor dependency injection for ConnectionInterface.
 * @since 1.4.0 Added unpublished post protection and recovery hooks.
 */
class RevisionsManager implements RevisionsManagerInterface {

	/**
	 * Batch size for processing revisions.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Post statuses considered "unpublished" and protected by default.
	 *
	 * @var array<string>
	 */
	const PROTECTED_STATUSES = array( 'draft', 'pending', 'auto-draft', 'future' );

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
		$posts_table = $this->connection->get_posts_table();

		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = %s",
			'revision'
		);

		if ( null === $query ) {
			return 0;
		}

		return absint( $this->connection->get_var( $query ) );
	}

	/**
	 * Get an estimate of the disk space used by revisions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Estimated bytes used by revisions.
	 */
	public function get_revisions_size_estimate(): int {
		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		// Get sum of post_content length and other fields for revisions.
		$query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(post_content) +
				LENGTH(post_title) +
				LENGTH(post_excerpt) +
				LENGTH(post_name)
			) as total_size
			FROM {$posts_table}
			WHERE post_type = %s",
			'revision'
		);

		if ( null === $query ) {
			return 0;
		}

		$content_size = absint( $this->connection->get_var( $query ) );

		// Get associated postmeta size.
		$meta_query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(meta_key) +
				LENGTH(meta_value)
			) as meta_size
			FROM {$postmeta_table} pm
			INNER JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.post_type = %s",
			'revision'
		);

		if ( null === $meta_query ) {
			return absint( $content_size * 1.5 );
		}

		$meta_size = absint( $this->connection->get_var( $meta_query ) );

		// Add overhead estimate (row overhead, indexes, etc.).
		$overhead_multiplier = 1.5;

		return absint( ( $content_size + $meta_size ) * $overhead_multiplier );
	}

	/**
	 * Check if a post status is considered unpublished/protected.
	 *
	 * @since 1.4.0
	 *
	 * @param string $post_status The post status to check.
	 * @return bool True if the status is protected.
	 */
	private function is_protected_status( string $post_status ): bool {
		/**
		 * Filter the list of post statuses that are protected from revision deletion.
		 *
		 * @since 1.4.0
		 *
		 * @param array<string> $statuses List of protected post statuses.
		 */
		$protected_statuses = apply_filters( 'wpha_protected_revision_statuses', self::PROTECTED_STATUSES );

		return in_array( $post_status, $protected_statuses, true );
	}

	/**
	 * Delete revisions for a specific post.
	 *
	 * By default, this method protects revisions of unpublished posts (drafts, pending, etc.)
	 * to prevent accidental data loss. Use the $force parameter to override this protection.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added unpublished post protection and recovery hooks.
	 *
	 * @param int  $post_id The post ID to delete revisions for.
	 * @param int  $keep    Number of most recent revisions to keep (default 0).
	 * @param bool $force   Force deletion even for unpublished posts (default false).
	 * @return array Array with 'deleted' count, 'bytes_freed' estimate, and 'skipped' reason if applicable.
	 */
	public function delete_revisions_for_post( int $post_id, int $keep = 0, bool $force = false ): array {
		$post_id = absint( $post_id );
		$keep    = absint( $keep );

		if ( ! $post_id ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'skipped'     => 'invalid_post_id',
			);
		}

		// Verify the parent post exists.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'skipped'     => 'post_not_found',
			);
		}

		// Protect revisions of unpublished posts unless forced.
		if ( ! $force && $this->is_protected_status( $post->post_status ) ) {
			/**
			 * Fires when revision deletion is skipped for an unpublished post.
			 *
			 * @since 1.4.0
			 *
			 * @param int    $post_id     The post ID.
			 * @param string $post_status The post status.
			 */
			do_action( 'wpha_revision_deletion_skipped_unpublished', $post_id, $post->post_status );

			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'skipped'     => 'unpublished_post',
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

		if ( empty( $revisions ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		/**
		 * Fires before revisions are deleted for a post.
		 *
		 * This hook can be used to backup revision data before deletion.
		 *
		 * @since 1.4.0
		 *
		 * @param int   $post_id   The post ID.
		 * @param array $revisions Array of revision objects to be deleted.
		 * @param int   $keep      Number of revisions being kept.
		 */
		do_action( 'wpha_before_revisions_delete', $post_id, $revisions, $keep );

		$deleted_count  = 0;
		$bytes_freed    = 0;
		$deleted_ids    = array();

		// Start batch processing - defer term counting for the entire operation.
		wp_defer_term_counting( true );

		foreach ( $revisions as $revision ) {
			// Estimate size before deletion.
			$size_estimate = $this->estimate_revision_size( $revision->ID );

			/**
			 * Fires before a single revision is deleted.
			 *
			 * @since 1.4.0
			 *
			 * @param \WP_Post $revision      The revision post object.
			 * @param int      $size_estimate Estimated size in bytes.
			 */
			do_action( 'wpha_before_revision_delete', $revision, $size_estimate );

			// Delete the revision using WordPress function.
			$result = wp_delete_post_revision( $revision->ID );

			if ( $result ) {
				$deleted_count++;
				$bytes_freed    += $size_estimate;
				$deleted_ids[]   = $revision->ID;
			}
		}

		// End batch processing.
		wp_defer_term_counting( false );

		/**
		 * Fires after revisions have been deleted for a post.
		 *
		 * @since 1.4.0
		 *
		 * @param int   $post_id       The post ID.
		 * @param array $deleted_ids   Array of deleted revision IDs.
		 * @param int   $deleted_count Number of revisions deleted.
		 * @param int   $bytes_freed   Estimated bytes freed.
		 */
		do_action( 'wpha_after_revisions_delete', $post_id, $deleted_ids, $deleted_count, $bytes_freed );

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
	 * By default, this method protects revisions of unpublished posts (drafts, pending, etc.)
	 * to prevent accidental data loss. Use the $force parameter to override this protection.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added unpublished post protection and recovery hooks.
	 *
	 * @param int  $keep  Number of most recent revisions to keep per post (default 0).
	 * @param bool $force Force deletion even for unpublished posts (default false).
	 * @return array Array with 'deleted' count, 'bytes_freed' estimate, and 'skipped_posts' count.
	 */
	public function delete_all_revisions( int $keep = 0, bool $force = false ): array {
		$keep = absint( $keep );

		$posts_table = $this->connection->get_posts_table();

		// Get all posts that have revisions.
		$query = $this->connection->prepare(
			"SELECT DISTINCT post_parent
			FROM {$posts_table}
			WHERE post_type = %s
			AND post_parent > 0
			ORDER BY post_parent ASC",
			'revision'
		);

		if ( null === $query ) {
			return array(
				'deleted'       => 0,
				'bytes_freed'   => 0,
				'skipped_posts' => 0,
			);
		}

		$posts_with_revisions = $this->connection->get_col( $query );

		if ( empty( $posts_with_revisions ) ) {
			return array(
				'deleted'       => 0,
				'bytes_freed'   => 0,
				'skipped_posts' => 0,
			);
		}

		/**
		 * Fires before bulk revision deletion begins.
		 *
		 * @since 1.4.0
		 *
		 * @param array $posts_with_revisions Array of post IDs with revisions.
		 * @param int   $keep                 Number of revisions to keep per post.
		 * @param bool  $force                Whether deletion is forced for unpublished posts.
		 */
		do_action( 'wpha_before_bulk_revisions_delete', $posts_with_revisions, $keep, $force );

		$total_deleted     = 0;
		$total_bytes_freed = 0;
		$skipped_posts     = 0;

		// Start batch processing - defer for the entire bulk operation.
		wp_defer_term_counting( true );

		foreach ( $posts_with_revisions as $post_id ) {
			$result = $this->delete_revisions_for_post_internal( (int) $post_id, $keep, $force );

			$total_deleted     += $result['deleted'];
			$total_bytes_freed += $result['bytes_freed'];

			if ( isset( $result['skipped'] ) ) {
				$skipped_posts++;
			}
		}

		// End batch processing.
		wp_defer_term_counting( false );

		/**
		 * Fires after bulk revision deletion completes.
		 *
		 * @since 1.4.0
		 *
		 * @param int $total_deleted     Total revisions deleted.
		 * @param int $total_bytes_freed Estimated bytes freed.
		 * @param int $skipped_posts     Number of posts skipped (unpublished).
		 */
		do_action( 'wpha_after_bulk_revisions_delete', $total_deleted, $total_bytes_freed, $skipped_posts );

		// Log to scan history.
		$this->log_deletion(
			'revision_bulk_cleanup',
			$total_deleted,
			$total_deleted,
			$total_bytes_freed
		);

		return array(
			'deleted'       => $total_deleted,
			'bytes_freed'   => $total_bytes_freed,
			'skipped_posts' => $skipped_posts,
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
		$limit = absint( $limit );
		if ( $limit < 1 ) {
			$limit = 10;
		}

		$posts_table = $this->connection->get_posts_table();

		$query = $this->connection->prepare(
			"SELECT
				p.ID as post_id,
				p.post_title,
				p.post_type,
				COUNT(r.ID) as revision_count
			FROM {$posts_table} p
			INNER JOIN {$posts_table} r ON p.ID = r.post_parent
			WHERE r.post_type = %s
			GROUP BY p.ID
			ORDER BY revision_count DESC
			LIMIT %d",
			'revision',
			$limit
		);

		if ( null === $query ) {
			return array();
		}

		return $this->connection->get_results( $query, 'ARRAY_A' );
	}

	/**
	 * Internal method to delete revisions for a post without separate logging.
	 *
	 * @since 1.0.0
	 * @since 1.4.0 Added $force parameter and unpublished post protection.
	 *
	 * @param int  $post_id    The post ID.
	 * @param int  $keep_count Number of revisions to keep.
	 * @param bool $force      Force deletion even for unpublished posts.
	 * @return array Array with 'deleted', 'bytes_freed', and optionally 'skipped'.
	 */
	private function delete_revisions_for_post_internal( int $post_id, int $keep_count = 0, bool $force = false ): array {
		// Verify the parent post exists and check its status.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'skipped'     => 'post_not_found',
			);
		}

		// Protect revisions of unpublished posts unless forced.
		if ( ! $force && $this->is_protected_status( $post->post_status ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
				'skipped'     => 'unpublished_post',
			);
		}

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
			);
		}

		// Skip the most recent revisions if keep_count is specified.
		if ( $keep_count > 0 ) {
			$revisions = array_slice( $revisions, $keep_count, null, true );
		}

		if ( empty( $revisions ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
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
			}
		}

		return array(
			'deleted'     => $deleted_count,
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Estimate the size of a single revision.
	 *
	 * @param int $revision_id The revision ID.
	 * @return int Estimated size in bytes.
	 */
	private function estimate_revision_size( $revision_id ) {
		$revision_id = absint( $revision_id );

		$posts_table    = $this->connection->get_posts_table();
		$postmeta_table = $this->connection->get_postmeta_table();

		// Get revision post data size.
		$post_query = $this->connection->prepare(
			"SELECT
				LENGTH(post_content) +
				LENGTH(post_title) +
				LENGTH(post_excerpt) +
				LENGTH(post_name)
			as size
			FROM {$posts_table}
			WHERE ID = %d",
			$revision_id
		);

		$post_size = 0;
		if ( null !== $post_query ) {
			$post_size = absint( $this->connection->get_var( $post_query ) );
		}

		// Get associated postmeta size.
		$meta_query = $this->connection->prepare(
			"SELECT SUM(
				LENGTH(meta_key) +
				LENGTH(meta_value)
			) as meta_size
			FROM {$postmeta_table}
			WHERE post_id = %d",
			$revision_id
		);

		$meta_size = 0;
		if ( null !== $meta_query ) {
			$meta_size = absint( $this->connection->get_var( $meta_query ) );
		}

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
