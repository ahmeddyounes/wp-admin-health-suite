<?php
/**
 * One-Click Fix System Class
 *
 * Provides safe, auto-executable fixes for common WordPress issues with previews,
 * batch execution, progress tracking, and rollback support.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\AI;

use WPAdminHealth\Database\Transients_Cleaner;
use WPAdminHealth\Database\Trash_Cleaner;
use WPAdminHealth\Database\Revisions_Manager;
use WPAdminHealth\Database\Optimizer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * One_Click_Fix class for executing safe, automated fixes.
 */
class One_Click_Fix {

	/**
	 * Transient key for storing rollback data.
	 *
	 * @var string
	 */
	private $rollback_key = 'wpha_fix_rollback_';

	/**
	 * Rollback data expiration time (24 hours).
	 *
	 * @var int
	 */
	private $rollback_expiration = DAY_IN_SECONDS;

	/**
	 * Transients cleaner instance.
	 *
	 * @var Transients_Cleaner
	 */
	private $transients_cleaner;

	/**
	 * Trash cleaner instance.
	 *
	 * @var Trash_Cleaner
	 */
	private $trash_cleaner;

	/**
	 * Revisions manager instance.
	 *
	 * @var Revisions_Manager
	 */
	private $revisions_manager;

	/**
	 * Optimizer instance.
	 *
	 * @var Optimizer
	 */
	private $optimizer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->transients_cleaner = new Transients_Cleaner();
		$this->trash_cleaner      = new Trash_Cleaner();
		$this->revisions_manager  = new Revisions_Manager();
		$this->optimizer          = new Optimizer();
	}

	/**
	 * Get all safe fixes that can be auto-executed.
	 *
	 * @return array Array of safe fix definitions.
	 */
	public function get_safe_fixes() {
		$fixes = array();

		// Fix: Clear expired transients.
		$expired_count = $this->transients_cleaner->count_expired_transients();
		if ( $expired_count > 0 ) {
			$fixes[] = array(
				'id'          => 'clear_expired_transients',
				'title'       => 'Clear Expired Transients',
				'description' => sprintf(
					'Remove %d expired transient(s) from the database.',
					$expired_count
				),
				'category'    => 'database',
				'risk_level'  => 'low',
				'impact'      => 'low',
				'safe'        => true,
				'estimated_time' => '< 1 minute',
				'affected_items' => $expired_count,
			);
		}

		// Fix: Delete spam comments older than 30 days.
		$spam_count = $this->get_old_spam_comments_count( 30 );
		if ( $spam_count > 0 ) {
			$fixes[] = array(
				'id'          => 'delete_old_spam_comments',
				'title'       => 'Delete Old Spam Comments',
				'description' => sprintf(
					'Permanently delete %d spam comment(s) older than 30 days.',
					$spam_count
				),
				'category'    => 'database',
				'risk_level'  => 'low',
				'impact'      => 'low',
				'safe'        => true,
				'estimated_time' => '< 1 minute',
				'affected_items' => $spam_count,
			);
		}

		// Fix: Clean post revisions keeping 3 most recent.
		$revisions_count = $this->revisions_manager->get_all_revisions_count();
		$excess_revisions = $this->get_excess_revisions_count( 3 );
		if ( $excess_revisions > 0 ) {
			$fixes[] = array(
				'id'          => 'clean_post_revisions',
				'title'       => 'Clean Post Revisions',
				'description' => sprintf(
					'Remove %d old revision(s), keeping the 3 most recent per post.',
					$excess_revisions
				),
				'category'    => 'database',
				'risk_level'  => 'low',
				'impact'      => 'medium',
				'safe'        => true,
				'estimated_time' => '< 2 minutes',
				'affected_items' => $excess_revisions,
			);
		}

		// Fix: Optimize tables with overhead.
		$tables_needing_optimization = $this->optimizer->get_tables_needing_optimization();
		if ( ! empty( $tables_needing_optimization ) ) {
			$total_overhead = array_sum( array_column( $tables_needing_optimization, 'overhead' ) );
			$fixes[] = array(
				'id'          => 'optimize_tables',
				'title'       => 'Optimize Database Tables',
				'description' => sprintf(
					'Optimize %d table(s) to reclaim %s of wasted space.',
					count( $tables_needing_optimization ),
					size_format( $total_overhead )
				),
				'category'    => 'database',
				'risk_level'  => 'low',
				'impact'      => 'medium',
				'safe'        => true,
				'estimated_time' => '< 3 minutes',
				'affected_items' => count( $tables_needing_optimization ),
			);
		}

		return $fixes;
	}

	/**
	 * Get a preview of what will be affected by a specific fix.
	 *
	 * @param string $recommendation_id The fix ID.
	 * @return array|null Preview data with affected items and impact estimate.
	 */
	public function get_fix_preview( $recommendation_id ) {
		switch ( $recommendation_id ) {
			case 'clear_expired_transients':
				return $this->preview_expired_transients();

			case 'delete_old_spam_comments':
				return $this->preview_old_spam_comments();

			case 'clean_post_revisions':
				return $this->preview_post_revisions();

			case 'optimize_tables':
				return $this->preview_table_optimization();

			default:
				return null;
		}
	}

	/**
	 * Execute a specific safe fix.
	 *
	 * @param string $recommendation_id The fix ID.
	 * @return array|null Execution result with success status, items affected, and messages.
	 */
	public function execute_fix( $recommendation_id ) {
		// Validate that this is a safe fix.
		$safe_fixes = $this->get_safe_fixes();
		$is_safe    = false;

		foreach ( $safe_fixes as $fix ) {
			if ( $fix['id'] === $recommendation_id && $fix['safe'] === true ) {
				$is_safe = true;
				break;
			}
		}

		if ( ! $is_safe ) {
			return array(
				'success' => false,
				'message' => 'Fix is not available or not safe for auto-execution.',
			);
		}

		// Execute the appropriate fix.
		switch ( $recommendation_id ) {
			case 'clear_expired_transients':
				return $this->execute_clear_expired_transients();

			case 'delete_old_spam_comments':
				return $this->execute_delete_old_spam_comments();

			case 'clean_post_revisions':
				return $this->execute_clean_post_revisions();

			case 'optimize_tables':
				return $this->execute_optimize_tables();

			default:
				return array(
					'success' => false,
					'message' => 'Unknown fix ID.',
				);
		}
	}

	/**
	 * Execute all safe fixes in batch with progress tracking.
	 *
	 * @return array Batch execution results with progress for each fix.
	 */
	public function execute_all_safe() {
		$safe_fixes = $this->get_safe_fixes();
		$results    = array(
			'total_fixes'      => count( $safe_fixes ),
			'successful'       => 0,
			'failed'           => 0,
			'fixes_executed'   => array(),
			'total_items'      => 0,
			'total_bytes_freed' => 0,
		);

		foreach ( $safe_fixes as $index => $fix ) {
			$progress = array(
				'current' => $index + 1,
				'total'   => count( $safe_fixes ),
				'percent' => round( ( ( $index + 1 ) / count( $safe_fixes ) ) * 100 ),
			);

			$result = $this->execute_fix( $fix['id'] );

			$fix_result = array(
				'id'       => $fix['id'],
				'title'    => $fix['title'],
				'progress' => $progress,
				'result'   => $result,
			);

			if ( $result && $result['success'] ) {
				$results['successful']++;
				if ( isset( $result['items_affected'] ) ) {
					$results['total_items'] += $result['items_affected'];
				}
				if ( isset( $result['bytes_freed'] ) ) {
					$results['total_bytes_freed'] += $result['bytes_freed'];
				}
			} else {
				$results['failed']++;
			}

			$results['fixes_executed'][] = $fix_result;
		}

		// Log batch execution to activity.
		$this->log_activity(
			'batch_fixes_execution',
			array(
				'total_fixes'       => $results['total_fixes'],
				'successful'        => $results['successful'],
				'failed'            => $results['failed'],
				'total_items'       => $results['total_items'],
				'total_bytes_freed' => $results['total_bytes_freed'],
			)
		);

		return $results;
	}

	/**
	 * Preview expired transients that will be cleared.
	 *
	 * @return array Preview data.
	 */
	private function preview_expired_transients() {
		$expired_transients = $this->transients_cleaner->get_expired_transients();
		$count              = count( $expired_transients );

		return array(
			'fix_id'       => 'clear_expired_transients',
			'affected_items' => $count,
			'description'  => sprintf(
				'Will remove %d expired transient(s) that are no longer needed.',
				$count
			),
			'impact'       => array(
				'type'    => 'Database cleanup',
				'details' => 'Removes outdated cache entries from the options table.',
				'risk'    => 'None - expired transients are safe to delete.',
			),
			'estimated_time' => '< 1 minute',
			'sample_items' => array_slice( $expired_transients, 0, 10 ),
		);
	}

	/**
	 * Preview old spam comments that will be deleted.
	 *
	 * @return array Preview data.
	 */
	private function preview_old_spam_comments() {
		$count = $this->get_old_spam_comments_count( 30 );

		return array(
			'fix_id'       => 'delete_old_spam_comments',
			'affected_items' => $count,
			'description'  => sprintf(
				'Will permanently delete %d spam comment(s) older than 30 days.',
				$count
			),
			'impact'       => array(
				'type'    => 'Database cleanup',
				'details' => 'Permanently removes old spam comments from the database.',
				'risk'    => 'Low - only affects spam comments older than 30 days.',
			),
			'estimated_time' => '< 1 minute',
			'note'         => 'This action cannot be undone. Spam comments will be permanently deleted.',
		);
	}

	/**
	 * Preview post revisions that will be cleaned.
	 *
	 * @return array Preview data.
	 */
	private function preview_post_revisions() {
		$total_count      = $this->revisions_manager->get_all_revisions_count();
		$excess_count     = $this->get_excess_revisions_count( 3 );
		$estimated_bytes  = $this->revisions_manager->get_revisions_size_estimate();

		return array(
			'fix_id'       => 'clean_post_revisions',
			'affected_items' => $excess_count,
			'description'  => sprintf(
				'Will remove %d old revision(s), keeping the 3 most recent per post.',
				$excess_count
			),
			'impact'       => array(
				'type'    => 'Database cleanup',
				'details' => sprintf(
					'Out of %d total revisions, %d will be removed. Each post will keep its 3 most recent revisions.',
					$total_count,
					$excess_count
				),
				'risk'    => 'Low - keeps recent revisions for rollback capability.',
				'estimated_space_freed' => size_format( $estimated_bytes * ( $excess_count / max( $total_count, 1 ) ) ),
			),
			'estimated_time' => '< 2 minutes',
			'note'         => 'The 3 most recent revisions per post will be preserved.',
		);
	}

	/**
	 * Preview table optimization.
	 *
	 * @return array Preview data.
	 */
	private function preview_table_optimization() {
		$tables = $this->optimizer->get_tables_needing_optimization();
		$total_overhead = array_sum( array_column( $tables, 'overhead' ) );

		return array(
			'fix_id'       => 'optimize_tables',
			'affected_items' => count( $tables ),
			'description'  => sprintf(
				'Will optimize %d database table(s) to reclaim wasted space.',
				count( $tables )
			),
			'impact'       => array(
				'type'    => 'Database optimization',
				'details' => sprintf(
					'Reclaims approximately %s of fragmented/wasted space.',
					size_format( $total_overhead )
				),
				'risk'    => 'None - optimization is a safe maintenance operation.',
			),
			'estimated_time' => '< 3 minutes',
			'tables'       => array_slice(
				array_map(
					function ( $table ) {
						return array(
							'name'     => $table['name'],
							'overhead' => size_format( $table['overhead'] ),
						);
					},
					$tables
				),
				0,
				10
			),
		);
	}

	/**
	 * Execute clearing expired transients.
	 *
	 * @return array Execution result.
	 */
	private function execute_clear_expired_transients() {
		$result = $this->transients_cleaner->delete_expired_transients();

		$this->log_activity(
			'clear_expired_transients',
			array(
				'items_affected' => $result['deleted'],
				'bytes_freed'    => $result['bytes_freed'],
			)
		);

		return array(
			'success'        => true,
			'fix_id'         => 'clear_expired_transients',
			'items_affected' => $result['deleted'],
			'bytes_freed'    => $result['bytes_freed'],
			'message'        => sprintf(
				'Successfully cleared %d expired transient(s), freeing %s.',
				$result['deleted'],
				size_format( $result['bytes_freed'] )
			),
		);
	}

	/**
	 * Execute deleting old spam comments.
	 *
	 * @return array Execution result.
	 */
	private function execute_delete_old_spam_comments() {
		// Store rollback info (limited data for 24 hours).
		$this->store_rollback_info(
			'delete_old_spam_comments',
			array(
				'note' => 'Spam comments deleted - permanent action, no rollback available.',
			)
		);

		$result = $this->trash_cleaner->delete_spam_comments( 30 );

		$this->log_activity(
			'delete_old_spam_comments',
			array(
				'items_affected' => $result['deleted'],
				'errors'         => $result['errors'],
			)
		);

		return array(
			'success'        => true,
			'fix_id'         => 'delete_old_spam_comments',
			'items_affected' => $result['deleted'],
			'errors'         => $result['errors'],
			'message'        => sprintf(
				'Successfully deleted %d spam comment(s) older than 30 days.',
				$result['deleted']
			),
		);
	}

	/**
	 * Execute cleaning post revisions.
	 *
	 * @return array Execution result.
	 */
	private function execute_clean_post_revisions() {
		// Store rollback info.
		$this->store_rollback_info(
			'clean_post_revisions',
			array(
				'keep_count' => 3,
				'note'       => 'Revisions deleted - cannot be restored, but 3 most recent kept per post.',
			)
		);

		$result = $this->revisions_manager->delete_all_revisions( 3 );

		$this->log_activity(
			'clean_post_revisions',
			array(
				'items_affected' => $result['deleted'],
				'bytes_freed'    => $result['bytes_freed'],
				'keep_count'     => 3,
			)
		);

		return array(
			'success'        => true,
			'fix_id'         => 'clean_post_revisions',
			'items_affected' => $result['deleted'],
			'bytes_freed'    => $result['bytes_freed'],
			'message'        => sprintf(
				'Successfully cleaned %d revision(s), freeing %s. Kept 3 most recent per post.',
				$result['deleted'],
				size_format( $result['bytes_freed'] )
			),
		);
	}

	/**
	 * Execute optimizing database tables.
	 *
	 * @return array Execution result.
	 */
	private function execute_optimize_tables() {
		$results = $this->optimizer->optimize_all_tables();

		$total_reduced = 0;
		$tables_optimized = 0;

		foreach ( $results as $result ) {
			if ( isset( $result['size_reduced'] ) ) {
				$total_reduced += $result['size_reduced'];
				$tables_optimized++;
			}
		}

		$this->log_activity(
			'optimize_tables',
			array(
				'tables_optimized' => $tables_optimized,
				'bytes_freed'      => $total_reduced,
			)
		);

		return array(
			'success'        => true,
			'fix_id'         => 'optimize_tables',
			'items_affected' => $tables_optimized,
			'bytes_freed'    => $total_reduced,
			'message'        => sprintf(
				'Successfully optimized %d table(s), reclaiming %s.',
				$tables_optimized,
				size_format( $total_reduced )
			),
			'details'        => $results,
		);
	}

	/**
	 * Get count of spam comments older than specified days.
	 *
	 * @param int $days Number of days.
	 * @return int Count of old spam comments.
	 */
	private function get_old_spam_comments_count( $days ) {
		global $wpdb;

		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments}
				WHERE comment_approved = 'spam'
				AND comment_date < %s",
				$date_threshold
			)
		);

		return absint( $count );
	}

	/**
	 * Get count of excess revisions (beyond the keep limit).
	 *
	 * @param int $keep_per_post Number to keep per post.
	 * @return int Count of excess revisions.
	 */
	private function get_excess_revisions_count( $keep_per_post ) {
		global $wpdb;

		// Get all posts with revisions.
		$posts_with_revisions = $wpdb->get_results(
			"SELECT post_parent, COUNT(*) as revision_count
			FROM {$wpdb->posts}
			WHERE post_type = 'revision'
			AND post_parent > 0
			GROUP BY post_parent
			HAVING revision_count > {$keep_per_post}",
			ARRAY_A
		);

		if ( empty( $posts_with_revisions ) ) {
			return 0;
		}

		$excess_count = 0;
		foreach ( $posts_with_revisions as $post ) {
			$excess_count += ( $post['revision_count'] - $keep_per_post );
		}

		return $excess_count;
	}

	/**
	 * Store rollback information for a fix.
	 *
	 * @param string $fix_id   The fix ID.
	 * @param array  $rollback_data Data needed for potential rollback.
	 * @return bool True on success.
	 */
	private function store_rollback_info( $fix_id, $rollback_data ) {
		$key = $this->rollback_key . $fix_id;

		$data = array(
			'fix_id'     => $fix_id,
			'timestamp'  => current_time( 'mysql' ),
			'data'       => $rollback_data,
		);

		return set_transient( $key, $data, $this->rollback_expiration );
	}

	/**
	 * Log activity to scan history.
	 *
	 * @param string $action_type The type of action performed.
	 * @param array  $data        Additional data to log.
	 * @return bool True on success, false on failure.
	 */
	private function log_activity( $action_type, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpha_scan_history';

		$items_affected = isset( $data['items_affected'] ) ? absint( $data['items_affected'] ) : 0;
		$bytes_freed    = isset( $data['bytes_freed'] ) ? absint( $data['bytes_freed'] ) : 0;

		$result = $wpdb->insert(
			$table_name,
			array(
				'scan_type'     => sanitize_text_field( 'one_click_fix_' . $action_type ),
				'items_found'   => $items_affected,
				'items_cleaned' => $items_affected,
				'bytes_freed'   => $bytes_freed,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);

		return false !== $result;
	}
}
