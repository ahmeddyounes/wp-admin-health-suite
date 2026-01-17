<?php
/**
 * Database Cleanup Task
 *
 * Scheduled task for database maintenance operations.
 *
 * @package WPAdminHealth\Database\Tasks
 */

namespace WPAdminHealth\Database\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class DatabaseCleanupTask
 *
 * Performs scheduled database cleanup operations with timeout handling,
 * batch processing, error recovery, and progress tracking.
 *
 * @since 1.2.0
 * @since 1.5.0 Added timeout handling, progress tracking, and error recovery.
 */
class DatabaseCleanupTask extends AbstractScheduledTask {

	/**
	 * Default time limit in seconds for the cleanup task.
	 *
	 * Leaves buffer for WordPress to complete the cron request gracefully.
	 * Default PHP max_execution_time is often 30s, so we use 25s.
	 *
	 * @var int
	 */
	const DEFAULT_TIME_LIMIT = 25;

	/**
	 * Safety buffer in seconds to stop before hitting the time limit.
	 *
	 * @var int
	 */
	const TIME_BUFFER = 3;

	/**
	 * Option key for storing task progress.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION_KEY = 'wpha_db_cleanup_progress';

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'database_cleanup';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Database Cleanup';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Clean up revisions, transients, orphaned metadata, trash, spam comments, and auto-drafts.';

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'weekly';

	/**
	 * Enabled option key.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = 'enable_scheduled_db_cleanup';

	/**
	 * Revisions manager.
	 *
	 * @var RevisionsManagerInterface
	 */
	private RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner.
	 *
	 * @var TransientsCleanerInterface
	 */
	private TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner.
	 *
	 * @var OrphanedCleanerInterface
	 */
	private OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Trash cleaner.
	 *
	 * @var TrashCleanerInterface
	 */
	private TrashCleanerInterface $trash_cleaner;

	/**
	 * Database optimizer.
	 *
	 * @var OptimizerInterface
	 */
	private OptimizerInterface $optimizer;

	/**
	 * Start time of the current execution.
	 *
	 * @var float
	 */
	private float $start_time = 0.0;

	/**
	 * Time limit for the current execution in seconds.
	 *
	 * @var int
	 */
	private int $time_limit;

	/**
	 * Constructor.
	 *
	 * @param RevisionsManagerInterface  $revisions_manager  Revisions manager.
	 * @param TransientsCleanerInterface $transients_cleaner Transients cleaner.
	 * @param OrphanedCleanerInterface   $orphaned_cleaner   Orphaned cleaner.
	 * @param TrashCleanerInterface      $trash_cleaner      Trash cleaner.
	 * @param OptimizerInterface         $optimizer          Database optimizer.
	 */
	public function __construct(
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		OptimizerInterface $optimizer
	) {
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->optimizer          = $optimizer;
		$this->time_limit         = self::DEFAULT_TIME_LIMIT;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 1.2.0
	 * @since 1.5.0 Added timeout handling, progress tracking, and error recovery.
	 */
	public function execute( array $options = array() ): array {
		$this->start_time = microtime( true );
		$this->configure_time_limit( $options );

		$this->log( 'Starting database cleanup task' );

		// Load any existing progress from a previous interrupted run.
		$progress = $this->load_progress();

		$total_items       = $progress['total_items'] ?? 0;
		$total_bytes       = $progress['total_bytes'] ?? 0;
		$completed_tasks   = $progress['completed_tasks'] ?? array();
		$subtask_errors    = $progress['errors'] ?? array();
		$settings          = get_option( 'wpha_settings', array() );
		$cleanup_tasks     = $this->determine_cleanup_tasks( $settings, $options );
		$was_interrupted   = false;

		/**
		 * Fires before database cleanup begins.
		 *
		 * @since 1.5.0
		 *
		 * @hook wpha_db_cleanup_before_execute
		 *
		 * @param array $cleanup_tasks  The list of cleanup tasks to run.
		 * @param array $completed_tasks Tasks already completed (from resumed progress).
		 * @param array $options        Task options.
		 */
		do_action( 'wpha_db_cleanup_before_execute', $cleanup_tasks, $completed_tasks, $options );

		// Execute each cleanup task that hasn't been completed yet.
		foreach ( $cleanup_tasks as $task ) {
			// Skip tasks already completed in a previous run.
			if ( in_array( $task, $completed_tasks, true ) ) {
				$this->log( sprintf( 'Skipping already completed task: %s', $task ) );
				continue;
			}

			// Check if we're running low on time.
			if ( $this->is_time_limit_approaching() ) {
				$this->log( 'Time limit approaching, saving progress for continuation' );
				$was_interrupted = true;
				$this->save_progress(
					array(
						'total_items'     => $total_items,
						'total_bytes'     => $total_bytes,
						'completed_tasks' => $completed_tasks,
						'errors'          => $subtask_errors,
						'interrupted_at'  => current_time( 'mysql' ),
					)
				);
				break;
			}

			/**
			 * Fires before a subtask is executed.
			 *
			 * @since 1.5.0
			 *
			 * @hook wpha_db_cleanup_before_subtask
			 *
			 * @param string $task    The subtask identifier.
			 * @param array  $options Task options.
			 */
			do_action( 'wpha_db_cleanup_before_subtask', $task, $options );

			$result = $this->execute_subtask_with_recovery( $task, $options );

			if ( isset( $result['error'] ) ) {
				$subtask_errors[ $task ] = $result['error'];
				$this->log( sprintf( 'Subtask %s failed: %s', $task, $result['error'] ), 'error' );
			} else {
				$total_items += $result['items'];
				$total_bytes += $result['bytes'];
				$completed_tasks[] = $task;
			}

			/**
			 * Fires after a subtask is executed.
			 *
			 * @since 1.5.0
			 *
			 * @hook wpha_db_cleanup_after_subtask
			 *
			 * @param string $task    The subtask identifier.
			 * @param array  $result  The subtask result.
			 * @param array  $options Task options.
			 */
			do_action( 'wpha_db_cleanup_after_subtask', $task, $result, $options );
		}

		// Optionally optimize tables (only if not interrupted and all tasks completed).
		if ( ! $was_interrupted && empty( $subtask_errors ) ) {
			if ( ! empty( $settings['optimize_tables_weekly'] ) || ! empty( $options['optimize_tables'] ) ) {
				if ( ! $this->is_time_limit_approaching() ) {
					try {
						$this->optimizer->optimize_all_tables();
						$this->log( 'Database tables optimized' );
					} catch ( \Throwable $e ) {
						$subtask_errors['optimize'] = $e->getMessage();
						$this->log( sprintf( 'Table optimization failed: %s', $e->getMessage() ), 'error' );
					}
				} else {
					$this->log( 'Skipping table optimization due to time limit' );
					$was_interrupted = true;
				}
			}
		}

		// Clear progress if task completed fully.
		if ( ! $was_interrupted ) {
			$this->clear_progress();
		}

		$elapsed_time = microtime( true ) - $this->start_time;

		/**
		 * Fires after database cleanup completes.
		 *
		 * @since 1.5.0
		 *
		 * @hook wpha_db_cleanup_after_execute
		 *
		 * @param int   $total_items     Total items cleaned.
		 * @param int   $total_bytes     Total bytes freed.
		 * @param array $subtask_errors  Any errors that occurred.
		 * @param bool  $was_interrupted Whether the task was interrupted due to time limit.
		 * @param float $elapsed_time    Total execution time in seconds.
		 */
		do_action( 'wpha_db_cleanup_after_execute', $total_items, $total_bytes, $subtask_errors, $was_interrupted, $elapsed_time );

		$this->log(
			sprintf(
				'Database cleanup %s. Items: %d, Bytes: %d, Time: %.2fs, Errors: %d',
				$was_interrupted ? 'interrupted (will resume)' : 'completed',
				$total_items,
				$total_bytes,
				$elapsed_time,
				count( $subtask_errors )
			)
		);

		$result = $this->create_result( $total_items, $total_bytes, empty( $subtask_errors ) );
		$result['was_interrupted'] = $was_interrupted;
		$result['errors']          = $subtask_errors;
		$result['elapsed_time']    = $elapsed_time;

		return $result;
	}

	/**
	 * Determine which cleanup tasks to run based on settings and options.
	 *
	 * @since 1.5.0
	 *
	 * @param array $settings Plugin settings.
	 * @param array $options  Task options.
	 * @return array List of task identifiers.
	 */
	private function determine_cleanup_tasks( array $settings, array $options ): array {
		$cleanup_tasks = array();

		if ( ! empty( $settings['cleanup_revisions'] ) || ! empty( $options['clean_revisions'] ) ) {
			$cleanup_tasks[] = 'revisions';
		}
		if ( ! empty( $settings['cleanup_expired_transients'] ) || ! empty( $options['clean_transients'] ) ) {
			$cleanup_tasks[] = 'transients';
		}
		if (
			! empty( $settings['orphaned_cleanup_enabled'] )
			|| ! empty( $settings['cleanup_orphaned_metadata'] )
			|| ! empty( $options['clean_orphaned'] )
		) {
			$cleanup_tasks[] = 'orphaned';
		}

		$trash_retention_days = absint( $settings['auto_clean_trash_days'] ?? 0 );
		$spam_retention_days  = absint( $settings['auto_clean_spam_days'] ?? 0 );

		$trash_cleanup_enabled = (
			( ! empty( $settings['cleanup_trashed_posts'] ) || ! empty( $settings['cleanup_trashed_comments'] ) )
			&& $trash_retention_days > 0
		);

		$spam_cleanup_enabled = ( ! empty( $settings['cleanup_spam_comments'] ) && $spam_retention_days > 0 );

		if ( $spam_cleanup_enabled || ! empty( $options['clean_spam'] ) ) {
			$cleanup_tasks[] = 'spam';
		}

		if ( $trash_cleanup_enabled || ! empty( $options['clean_trash'] ) ) {
			$cleanup_tasks[] = 'trash';
		}

		if ( ! empty( $settings['cleanup_auto_drafts'] ) || ! empty( $options['clean_auto_drafts'] ) ) {
			$cleanup_tasks[] = 'auto_drafts';
		}

		return $cleanup_tasks;
	}

	/**
	 * Configure the time limit based on PHP settings and options.
	 *
	 * @since 1.5.0
	 *
	 * @param array $options Task options.
	 * @return void
	 */
	private function configure_time_limit( array $options ): void {
		// Allow overriding via options (useful for manual runs or testing).
		if ( isset( $options['time_limit'] ) && is_int( $options['time_limit'] ) ) {
			$this->time_limit = $options['time_limit'];
			return;
		}

		// Try to determine the PHP max_execution_time.
		$max_execution_time = (int) ini_get( 'max_execution_time' );

		// If max_execution_time is 0 (unlimited) or not set, use our default.
		if ( $max_execution_time <= 0 ) {
			$this->time_limit = self::DEFAULT_TIME_LIMIT;
			return;
		}

		// Use the smaller of PHP's limit (minus buffer) or our default.
		$this->time_limit = min(
			$max_execution_time - self::TIME_BUFFER,
			self::DEFAULT_TIME_LIMIT
		);

		// Ensure we have at least some time to work.
		$this->time_limit = max( $this->time_limit, 5 );
	}

	/**
	 * Check if the time limit is approaching.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True if we should stop processing.
	 */
	private function is_time_limit_approaching(): bool {
		$elapsed = microtime( true ) - $this->start_time;
		return $elapsed >= ( $this->time_limit - self::TIME_BUFFER );
	}

	/**
	 * Get the remaining time in seconds.
	 *
	 * @since 1.5.0
	 *
	 * @return float Remaining time in seconds.
	 */
	private function get_remaining_time(): float {
		$elapsed = microtime( true ) - $this->start_time;
		return max( 0, $this->time_limit - $elapsed - self::TIME_BUFFER );
	}

	/**
	 * Execute a subtask with error recovery.
	 *
	 * Wraps subtask execution in a try-catch block to prevent individual
	 * failures from stopping the entire cleanup process.
	 *
	 * @since 1.5.0
	 *
	 * @param string $task    Task name.
	 * @param array  $options Task options.
	 * @return array Result with 'items', 'bytes', and optionally 'error' keys.
	 */
	private function execute_subtask_with_recovery( string $task, array $options ): array {
		try {
			return $this->execute_subtask( $task, $options );
		} catch ( \Throwable $e ) {
			$this->log(
				sprintf(
					'Exception in subtask %s: %s in %s:%d',
					$task,
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				),
				'error'
			);

			return array(
				'items' => 0,
				'bytes' => 0,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Load saved progress from a previous interrupted run.
	 *
	 * @since 1.5.0
	 *
	 * @return array Progress data or empty array.
	 */
	private function load_progress(): array {
		$progress = get_option( self::PROGRESS_OPTION_KEY, array() );

		if ( ! empty( $progress ) && is_array( $progress ) ) {
			$this->log( 'Resuming from saved progress' );
		}

		return is_array( $progress ) ? $progress : array();
	}

	/**
	 * Save progress for later continuation.
	 *
	 * @since 1.5.0
	 *
	 * @param array $progress Progress data to save.
	 * @return bool True on success, false on failure.
	 */
	private function save_progress( array $progress ): bool {
		return update_option( self::PROGRESS_OPTION_KEY, $progress, false );
	}

	/**
	 * Clear saved progress.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True on success, false on failure.
	 */
	private function clear_progress(): bool {
		return delete_option( self::PROGRESS_OPTION_KEY );
	}

	/**
	 * Get the current progress for external monitoring.
	 *
	 * @since 1.5.0
	 *
	 * @return array Current progress data.
	 */
	public function get_progress(): array {
		return $this->load_progress();
	}

	/**
	 * Check if a previous run was interrupted and needs resuming.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True if there's saved progress to resume.
	 */
	public function has_pending_progress(): bool {
		$progress = get_option( self::PROGRESS_OPTION_KEY, array() );
		return ! empty( $progress );
	}

	/**
	 * Force clear any saved progress (useful for admin reset).
	 *
	 * @since 1.5.0
	 *
	 * @return bool True on success.
	 */
	public function reset_progress(): bool {
		$this->log( 'Progress manually reset' );
		return $this->clear_progress();
	}

	/**
	 * Execute a specific cleanup subtask.
	 *
	 * @param string $task    Task name.
	 * @param array  $options Task options.
	 * @return array Result with 'items' and 'bytes' keys.
	 */
	private function execute_subtask( string $task, array $options ): array {
		$result = array(
			'items' => 0,
			'bytes' => 0,
		);

		switch ( $task ) {
			case 'revisions':
				$settings      = get_option( 'wpha_settings', array() );
				$max_revisions = $options['max_revisions'] ?? ( $settings['revisions_to_keep'] ?? 5 );
				$cleaned       = $this->revisions_manager->delete_all_revisions( $max_revisions );
				$result['items'] = $cleaned['deleted'] ?? 0;
				$result['bytes'] = $cleaned['bytes_freed'] ?? 0;
				$this->log( sprintf( 'Cleaned %d revisions', $result['items'] ) );
				break;

			case 'transients':
				$settings = get_option( 'wpha_settings', array() );
				$excluded = isset( $settings['excluded_transient_prefixes'] ) ? (string) $settings['excluded_transient_prefixes'] : '';
				$excluded = str_replace( array( "\r\n", "\r" ), "\n", $excluded );

				$exclude_patterns = array_filter( array_map( 'trim', explode( "\n", $excluded ) ) );
				$cleaned          = $this->transients_cleaner->delete_expired_transients( $exclude_patterns );
				$result['items'] = $cleaned['deleted'] ?? 0;
				$result['bytes'] = $cleaned['bytes_freed'] ?? 0;
				$this->log( sprintf( 'Cleaned %d transients', $result['items'] ) );
				break;

			case 'orphaned':
				// Clean all orphaned data types.
				$postmeta_count      = $this->orphaned_cleaner->delete_orphaned_postmeta();
				$commentmeta_count   = $this->orphaned_cleaner->delete_orphaned_commentmeta();
				$termmeta_count      = $this->orphaned_cleaner->delete_orphaned_termmeta();
				$relationships_count = $this->orphaned_cleaner->delete_orphaned_relationships();
				$result['items'] = $postmeta_count + $commentmeta_count + $termmeta_count + $relationships_count;
				$result['bytes'] = 0; // Orphaned cleaner doesn't track bytes.
				$this->log( sprintf( 'Cleaned %d orphaned items', $result['items'] ) );
				break;

			case 'spam':
				$settings        = get_option( 'wpha_settings', array() );
				$older_than_days = absint( $settings['auto_clean_spam_days'] ?? 0 );

				if ( ! empty( $options['clean_spam'] ) && isset( $options['older_than_days'] ) ) {
					$older_than_days = absint( $options['older_than_days'] );
				}

				if ( empty( $options['clean_spam'] ) && $older_than_days <= 0 ) {
					$this->log( 'Skipping spam cleanup (retention disabled)' );
					break;
				}

				$cleaned = $this->trash_cleaner->delete_spam_comments( $older_than_days );
				$result['items'] = $cleaned['deleted'] ?? 0;
				$result['bytes'] = 0; // Trash cleaner doesn't track bytes.
				$this->log( sprintf( 'Cleaned %d spam comments', $result['items'] ) );
				break;

			case 'trash':
				$settings = get_option( 'wpha_settings', array() );

				$older_than_days = absint( $settings['auto_clean_trash_days'] ?? 0 );
				$clean_posts     = ! empty( $settings['cleanup_trashed_posts'] );
				$clean_comments  = ! empty( $settings['cleanup_trashed_comments'] );

				// For manual/task overrides, allow emptying all trash and/or specifying a threshold.
				if ( ! empty( $options['clean_trash'] ) ) {
					$older_than_days = isset( $options['older_than_days'] ) ? absint( $options['older_than_days'] ) : 0;
					$clean_posts     = true;
					$clean_comments  = true;
				}

				if ( $older_than_days <= 0 && empty( $options['clean_trash'] ) ) {
					$this->log( 'Skipping trash cleanup (retention disabled)' );
					break;
				}

				$deleted = 0;

				if ( $clean_posts ) {
					$posts_result = $this->trash_cleaner->delete_trashed_posts( array(), $older_than_days );
					$deleted     += $posts_result['deleted'] ?? 0;
				}

				if ( $clean_comments ) {
					$comments_result = $this->trash_cleaner->delete_trashed_comments( $older_than_days );
					$deleted        += $comments_result['deleted'] ?? 0;
				}

				$result['items'] = $deleted;
				$result['bytes'] = 0; // Trash cleaner doesn't track bytes.
				$this->log( sprintf( 'Cleaned %d trash items', $result['items'] ) );
				break;

			case 'auto_drafts':
				$older_than_days = 7;

				if ( ! empty( $options['clean_auto_drafts'] ) && isset( $options['older_than_days'] ) ) {
					$older_than_days = absint( $options['older_than_days'] );
				}

				if ( $older_than_days <= 0 ) {
					$older_than_days = 0;
				}

				$before = gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );

				$post_ids = get_posts(
					array(
						'post_type'      => 'any',
						'post_status'    => 'auto-draft',
						'posts_per_page' => 200,
						'fields'         => 'ids',
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'date_query'     => array(
							array(
								'column' => 'post_modified_gmt',
								'before' => $before,
							),
						),
					)
				);

				if ( empty( $post_ids ) ) {
					$this->log( 'No auto-drafts to clean' );
					break;
				}

				$deleted = 0;
				foreach ( $post_ids as $post_id ) {
					if ( wp_delete_post( (int) $post_id, true ) ) {
						++$deleted;
					}
				}

				$result['items'] = $deleted;
				$result['bytes'] = 0;
				$this->log( sprintf( 'Cleaned %d auto-drafts', $result['items'] ) );
				break;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'clean_revisions'    => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean post revisions', 'wp-admin-health-suite' ),
			),
			'max_revisions'      => array(
				'type'        => 'integer',
				'default'     => 5,
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'Maximum revisions to keep per post', 'wp-admin-health-suite' ),
			),
			'clean_transients'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean expired transients', 'wp-admin-health-suite' ),
			),
			'clean_orphaned'     => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean orphaned metadata', 'wp-admin-health-suite' ),
			),
			'clean_trash'        => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Empty trash', 'wp-admin-health-suite' ),
			),
			'clean_spam'         => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Delete spam comments', 'wp-admin-health-suite' ),
			),
			'clean_auto_drafts'  => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Delete auto-drafts', 'wp-admin-health-suite' ),
			),
			'older_than_days'    => array(
				'type'        => 'integer',
				'default'     => 0,
				'min'         => 0,
				'max'         => 365,
				'description' => __( 'Age threshold in days for trash/spam/auto-drafts when manually running the task (0 = all).', 'wp-admin-health-suite' ),
			),
			'optimize_tables'    => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Optimize database tables after cleanup', 'wp-admin-health-suite' ),
			),
		);
	}
}
