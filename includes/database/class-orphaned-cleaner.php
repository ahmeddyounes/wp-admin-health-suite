<?php
/**
 * Orphaned Data Cleaner Class
 *
 * Identifies and removes orphaned metadata and relationships from WordPress database.
 * Handles postmeta, commentmeta, termmeta, and term relationships with no valid parent records.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Orphaned Cleaner class for managing orphaned database records.
 *
 * @since 1.0.0
 */
class Orphaned_Cleaner {

	/**
	 * Batch size for processing orphaned data.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 1000;

	/**
	 * Find orphaned postmeta records.
	 *
	 * Identifies postmeta rows where the post_id does not exist in the posts table.
	 *
 * @since 1.0.0
 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_postmeta() {
		global $wpdb;

		$query = "SELECT pm.meta_id
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			ORDER BY pm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned commentmeta records.
	 *
	 * Identifies commentmeta rows where the comment_id does not exist in the comments table.
	 *
 * @since 1.0.0
 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_commentmeta() {
		global $wpdb;

		$query = "SELECT cm.meta_id
			FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
			WHERE c.comment_ID IS NULL
			ORDER BY cm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned termmeta records.
	 *
	 * Identifies termmeta rows where the term_id does not exist in the terms table.
	 *
 * @since 1.0.0
 *
	 * @return array Array of orphaned meta_ids.
	 */
	public function find_orphaned_termmeta() {
		global $wpdb;

		$query = "SELECT tm.meta_id
			FROM {$wpdb->termmeta} tm
			LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
			WHERE t.term_id IS NULL
			ORDER BY tm.meta_id ASC";

		$results = $wpdb->get_col( $query );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Find orphaned term relationships.
	 *
	 * Identifies term_relationships rows where the object_id does not exist in the posts table.
	 *
 * @since 1.0.0
 *
	 * @return array Array of orphaned relationship data (object_id and term_taxonomy_id pairs).
	 */
	public function find_orphaned_relationships() {
		global $wpdb;

		$query = "SELECT tr.object_id, tr.term_taxonomy_id
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			WHERE p.ID IS NULL
			ORDER BY tr.object_id ASC, tr.term_taxonomy_id ASC";

		$results = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Delete orphaned postmeta records.
	 *
 * @since 1.0.0
 *
	 * @param bool $dry_run If true, only returns the count without deleting.
	 * @return int Number of records deleted (or would be deleted if dry_run is true).
	 */
	public function delete_orphaned_postmeta( $dry_run = false ) {
		global $wpdb;

		// Find orphaned records.
		$orphaned_ids = $this->find_orphaned_postmeta();

		if ( empty( $orphaned_ids ) ) {
			return 0;
		}

		$total_count = count( $orphaned_ids );

		// If dry run, just return the count.
		if ( $dry_run ) {
			return $total_count;
		}

		// Process in batches.
		$deleted_count = 0;
		$batches       = array_chunk( $orphaned_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%d' ) );
			$query        = "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($placeholders)";
			$prepared     = $wpdb->prepare( $query, $batch );
			$result       = $wpdb->query( $prepared );

			if ( $result !== false ) {
				$deleted_count += $result;
			}
		}

		return $deleted_count;
	}

	/**
	 * Delete orphaned commentmeta records.
	 *
 * @since 1.0.0
 *
	 * @param bool $dry_run If true, only returns the count without deleting.
	 * @return int Number of records deleted (or would be deleted if dry_run is true).
	 */
	public function delete_orphaned_commentmeta( $dry_run = false ) {
		global $wpdb;

		// Find orphaned records.
		$orphaned_ids = $this->find_orphaned_commentmeta();

		if ( empty( $orphaned_ids ) ) {
			return 0;
		}

		$total_count = count( $orphaned_ids );

		// If dry run, just return the count.
		if ( $dry_run ) {
			return $total_count;
		}

		// Process in batches.
		$deleted_count = 0;
		$batches       = array_chunk( $orphaned_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%d' ) );
			$query        = "DELETE FROM {$wpdb->commentmeta} WHERE meta_id IN ($placeholders)";
			$prepared     = $wpdb->prepare( $query, $batch );
			$result       = $wpdb->query( $prepared );

			if ( $result !== false ) {
				$deleted_count += $result;
			}
		}

		return $deleted_count;
	}

	/**
	 * Delete orphaned termmeta records.
	 *
 * @since 1.0.0
 *
	 * @param bool $dry_run If true, only returns the count without deleting.
	 * @return int Number of records deleted (or would be deleted if dry_run is true).
	 */
	public function delete_orphaned_termmeta( $dry_run = false ) {
		global $wpdb;

		// Find orphaned records.
		$orphaned_ids = $this->find_orphaned_termmeta();

		if ( empty( $orphaned_ids ) ) {
			return 0;
		}

		$total_count = count( $orphaned_ids );

		// If dry run, just return the count.
		if ( $dry_run ) {
			return $total_count;
		}

		// Process in batches.
		$deleted_count = 0;
		$batches       = array_chunk( $orphaned_ids, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%d' ) );
			$query        = "DELETE FROM {$wpdb->termmeta} WHERE meta_id IN ($placeholders)";
			$prepared     = $wpdb->prepare( $query, $batch );
			$result       = $wpdb->query( $prepared );

			if ( $result !== false ) {
				$deleted_count += $result;
			}
		}

		return $deleted_count;
	}

	/**
	 * Delete orphaned term relationships.
	 *
 * @since 1.0.0
 *
	 * @param bool $dry_run If true, only returns the count without deleting.
	 * @return int Number of records deleted (or would be deleted if dry_run is true).
	 */
	public function delete_orphaned_relationships( $dry_run = false ) {
		global $wpdb;

		// Find orphaned records.
		$orphaned_relationships = $this->find_orphaned_relationships();

		if ( empty( $orphaned_relationships ) ) {
			return 0;
		}

		$total_count = count( $orphaned_relationships );

		// If dry run, just return the count.
		if ( $dry_run ) {
			return $total_count;
		}

		// Process in batches.
		$deleted_count = 0;
		$batches       = array_chunk( $orphaned_relationships, self::BATCH_SIZE );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $relationship ) {
				$result = $wpdb->delete(
					$wpdb->term_relationships,
					array(
						'object_id'        => $relationship['object_id'],
						'term_taxonomy_id' => $relationship['term_taxonomy_id'],
					),
					array( '%d', '%d' )
				);

				if ( $result !== false ) {
					$deleted_count += $result;
				}
			}
		}

		return $deleted_count;
	}
}
