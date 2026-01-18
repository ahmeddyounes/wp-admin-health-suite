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
use WPAdminHealth\Contracts\SettingsInterface;
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
 * @since 1.7.0 Updated to use TaskResult DTO and ProgressStore.
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
	 * {@inheritdoc}
	 *
	 * @var int
	 */
	protected int $default_time_limit = self::DEFAULT_TIME_LIMIT;

	/**
	 * {@inheritdoc}
	 *
	 * @var int
	 */
	protected int $time_buffer = self::TIME_BUFFER;

	/**
	 * {@inheritdoc}
	 *
	 * @var string
	 */
	protected string $progress_option_key = self::PROGRESS_OPTION_KEY;

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
	 * Settings interface.
	 *
	 * @since 1.8.0
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings = null;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.8.0 Added optional SettingsInterface dependency for safe mode support.
	 *
	 * @param RevisionsManagerInterface  $revisions_manager  Revisions manager.
	 * @param TransientsCleanerInterface $transients_cleaner Transients cleaner.
	 * @param OrphanedCleanerInterface   $orphaned_cleaner   Orphaned cleaner.
	 * @param TrashCleanerInterface      $trash_cleaner      Trash cleaner.
	 * @param OptimizerInterface         $optimizer          Database optimizer.
	 * @param SettingsInterface|null     $settings           Settings interface (optional).
	 */
	public function __construct(
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		OptimizerInterface $optimizer,
		?SettingsInterface $settings = null
	) {
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->optimizer          = $optimizer;
		$this->settings           = $settings;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 1.2.0
	 * @since 1.5.0 Added timeout handling, progress tracking, and error recovery.
	 * @since 1.8.0 Added safe mode support - returns preview data without executing destructive operations.
	 */
	public function execute( array $options = array() ): array {
		$this->start_time = microtime( true );
		$this->configure_time_limit( $options );

		$this->log( 'Starting database cleanup task' );

		// Check if safe mode is enabled (blocks all destructive operations).
		if ( $this->is_safe_mode_enabled( $options ) ) {
			$this->log( 'Safe mode enabled - returning preview data only (no destructive operations)' );
			return $this->create_safe_mode_preview( $options );
		}

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
				$this->save_interrupted_progress(
					array(
						'total_items'     => $total_items,
						'total_bytes'     => $total_bytes,
						'completed_tasks' => $completed_tasks,
						'errors'          => $subtask_errors,
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
		return $this->execute_with_recovery(
			function () use ( $task, $options ): array {
				return $this->execute_subtask( $task, $options );
			},
			array(
				'items' => 0,
				'bytes' => 0,
			),
			sprintf( 'subtask %s', $task )
		);
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
	 * Check if safe mode is enabled.
	 *
	 * Safe mode can be enabled via:
	 * 1. WPHA_SAFE_MODE constant in wp-config.php
	 * 2. 'safe_mode' setting in plugin settings
	 * 3. 'safe_mode' option passed directly to execute()
	 *
	 * @since 1.8.0
	 *
	 * @param array $options Task options (may contain 'safe_mode' override).
	 * @return bool True if safe mode is enabled.
	 */
	private function is_safe_mode_enabled( array $options ): bool {
		// Option override takes precedence.
		if ( isset( $options['safe_mode'] ) ) {
			return (bool) $options['safe_mode'];
		}

		// Use SettingsInterface if available.
		if ( null !== $this->settings ) {
			return $this->settings->is_safe_mode_enabled();
		}

		// Fall back to checking constant and option directly.
		if ( defined( 'WPHA_SAFE_MODE' ) ) {
			return (bool) WPHA_SAFE_MODE;
		}

		$settings = get_option( 'wpha_settings', array() );
		return ! empty( $settings['safe_mode'] );
	}

	/**
	 * Create a safe mode preview result.
	 *
	 * Returns preview data showing what would be cleaned without actually
	 * executing any destructive operations.
	 *
	 * @since 1.8.0
	 *
	 * @param array $options Task options.
	 * @return array Preview result with 'would_delete' and 'would_free' counts.
	 */
	private function create_safe_mode_preview( array $options ): array {
		$settings      = get_option( 'wpha_settings', array() );
		$cleanup_tasks = $this->determine_cleanup_tasks( $settings, $options );

		$preview = array(
			'revisions_count'  => 0,
			'transients_count' => 0,
			'orphaned_count'   => 0,
			'spam_count'       => 0,
			'trash_count'      => 0,
			'auto_drafts_count' => 0,
		);

		// Collect counts for what would be cleaned.
		foreach ( $cleanup_tasks as $task ) {
			switch ( $task ) {
				case 'revisions':
					$preview['revisions_count'] = $this->revisions_manager->get_all_revisions_count();
					break;

				case 'transients':
					$preview['transients_count'] = $this->transients_cleaner->count_transients();
					break;

				case 'orphaned':
					$preview['orphaned_count'] = count( $this->orphaned_cleaner->find_orphaned_postmeta() )
						+ count( $this->orphaned_cleaner->find_orphaned_commentmeta() )
						+ count( $this->orphaned_cleaner->find_orphaned_termmeta() )
						+ count( $this->orphaned_cleaner->find_orphaned_relationships() );
					break;

				case 'spam':
					$preview['spam_count'] = $this->get_spam_count();
					break;

				case 'trash':
					$preview['trash_count'] = $this->get_trash_count();
					break;

				case 'auto_drafts':
					$preview['auto_drafts_count'] = $this->get_auto_drafts_count( $options );
					break;
			}
		}

		$total_would_delete = array_sum( $preview );

		$elapsed_time = microtime( true ) - $this->start_time;

		$result                   = $this->create_result( 0, 0, true );
		$result['safe_mode']      = true;
		$result['preview_only']   = true;
		$result['would_delete']   = $total_would_delete;
		$result['preview']        = $preview;
		$result['cleanup_tasks']  = $cleanup_tasks;
		$result['elapsed_time']   = $elapsed_time;
		$result['was_interrupted'] = false;
		$result['errors']         = array();

		return $result;
	}

	/**
	 * Get count of spam comments.
	 *
	 * @since 1.8.0
	 *
	 * @return int Number of spam comments.
	 */
	private function get_spam_count(): int {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		return absint( $count );
	}

	/**
	 * Get count of trashed items (posts and comments).
	 *
	 * @since 1.8.0
	 *
	 * @return int Number of trashed items.
	 */
	private function get_trash_count(): int {
		global $wpdb;
		$posts_count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$comments_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
		return absint( $posts_count ) + absint( $comments_count );
	}

	/**
	 * Get count of auto-drafts.
	 *
	 * @since 1.8.0
	 *
	 * @param array $options Task options.
	 * @return int Number of auto-drafts.
	 */
	private function get_auto_drafts_count( array $options ): int {
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
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => $before,
					),
				),
			)
		);

		return count( $post_ids );
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
