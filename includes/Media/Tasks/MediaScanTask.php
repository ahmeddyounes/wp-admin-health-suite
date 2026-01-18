<?php
/**
 * Media Scan Task
 *
 * Scheduled task for media library scanning and maintenance.
 *
 * @package WPAdminHealth\Media\Tasks
 */

namespace WPAdminHealth\Media\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MediaScanTask
 *
 * Performs scheduled media library scans with locking, incremental processing,
 * and resource-efficient batch operations.
 *
 * @since 1.2.0
 * @since 1.6.0 Added locking, incremental scanning, and timeout handling.
 * @since 1.7.0 Updated to use TaskResult DTO and ProgressStore.
 */
class MediaScanTask extends AbstractScheduledTask {

	/**
	 * Default time limit in seconds for the scan task.
	 *
	 * Leaves buffer for WordPress to complete the cron request gracefully.
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
	 * Default batch size for incremental scanning.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 100;

	/**
	 * Lock transient name.
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'wpha_media_scan_lock';

	/**
	 * Lock duration in seconds (10 minutes).
	 *
	 * @var int
	 */
	const LOCK_DURATION = 600;

	/**
	 * Option key for storing task progress.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION_KEY = 'wpha_media_scan_progress';

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'media_scan';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Media Library Scan';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Scan media library for issues like duplicates, large files, and missing alt text.';

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
	protected string $enabled_option_key = 'enable_scheduled_media_scan';

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
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Media scanner.
	 *
	 * @var ScannerInterface
	 */
	private ScannerInterface $scanner;

	/**
	 * Duplicate detector.
	 *
	 * @var DuplicateDetectorInterface
	 */
	private DuplicateDetectorInterface $duplicate_detector;

	/**
	 * Large files detector.
	 *
	 * @var LargeFilesInterface
	 */
	private LargeFilesInterface $large_files;

	/**
	 * Alt text checker.
	 *
	 * @var AltTextCheckerInterface
	 */
	private AltTextCheckerInterface $alt_text_checker;

	/**
	 * Constructor.
	 *
	 * @param ConnectionInterface        $connection         Database connection.
	 * @param ScannerInterface           $scanner            Media scanner.
	 * @param DuplicateDetectorInterface $duplicate_detector Duplicate detector.
	 * @param LargeFilesInterface        $large_files        Large files detector.
	 * @param AltTextCheckerInterface    $alt_text_checker   Alt text checker.
	 */
	public function __construct(
		ConnectionInterface $connection,
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files,
		AltTextCheckerInterface $alt_text_checker
	) {
		$this->connection         = $connection;
		$this->scanner            = $scanner;
		$this->duplicate_detector = $duplicate_detector;
		$this->large_files        = $large_files;
		$this->alt_text_checker   = $alt_text_checker;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 1.2.0
	 * @since 1.6.0 Added locking, incremental scanning, and timeout handling.
	 */
	public function execute( array $options = array() ): array {
		$this->start_time = microtime( true );
		$this->configure_time_limit( $options );

		$this->log( 'Starting media scan task' );

		// Attempt to acquire lock to prevent concurrent scans.
		if ( ! $this->acquire_lock() ) {
			$this->log( 'Media scan already in progress, skipping', 'warning' );
			return $this->create_result( 0, 0, false );
		}

		// Load any existing progress from a previous interrupted run.
		$progress = $this->load_progress();

		$scan_results = array(
			'duplicates'     => $progress['duplicates'] ?? array(),
			'large_files'    => $progress['large_files'] ?? array(),
			'missing_alt'    => $progress['missing_alt'] ?? array(),
			'total_issues'   => $progress['total_issues'] ?? 0,
			'total_bytes'    => $progress['total_bytes'] ?? 0,
		);

		$completed_tasks = $progress['completed_tasks'] ?? array();
		$subtask_errors  = $progress['errors'] ?? array();
		$was_interrupted = false;
		$settings        = get_option( 'wpha_settings', array() );
		$scan_tasks      = $this->determine_scan_tasks( $settings, $options );

		/**
		 * Fires before media scan begins.
		 *
		 * @since 1.6.0
		 *
		 * @hook wpha_media_scan_before_execute
		 *
		 * @param array $scan_tasks      The list of scan tasks to run.
		 * @param array $completed_tasks Tasks already completed (from resumed progress).
		 * @param array $options         Task options.
		 */
		do_action( 'wpha_media_scan_before_execute', $scan_tasks, $completed_tasks, $options );

		// Execute each scan task that hasn't been completed yet.
		foreach ( $scan_tasks as $task ) {
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
						'duplicates'      => $scan_results['duplicates'],
						'large_files'     => $scan_results['large_files'],
						'missing_alt'     => $scan_results['missing_alt'],
						'total_issues'    => $scan_results['total_issues'],
						'total_bytes'     => $scan_results['total_bytes'],
						'completed_tasks' => $completed_tasks,
						'errors'          => $subtask_errors,
					)
				);
				break;
			}

			/**
			 * Fires before a scan subtask is executed.
			 *
			 * @since 1.6.0
			 *
			 * @hook wpha_media_scan_before_subtask
			 *
			 * @param string $task    The subtask identifier.
			 * @param array  $options Task options.
			 */
			do_action( 'wpha_media_scan_before_subtask', $task, $options );

			$result = $this->execute_subtask_with_recovery( $task, $options, $settings );

			if ( isset( $result['error'] ) ) {
				$subtask_errors[ $task ] = $result['error'];
				$this->log( sprintf( 'Subtask %s failed: %s', $task, $result['error'] ), 'error' );
			} else {
				// Merge results based on task type.
				$this->merge_subtask_results( $scan_results, $task, $result );
				$completed_tasks[] = $task;
			}

			/**
			 * Fires after a scan subtask is executed.
			 *
			 * @since 1.6.0
			 *
			 * @hook wpha_media_scan_after_subtask
			 *
			 * @param string $task    The subtask identifier.
			 * @param array  $result  The subtask result.
			 * @param array  $options Task options.
			 */
			do_action( 'wpha_media_scan_after_subtask', $task, $result, $options );

			// Refresh the lock to prevent expiry during long scans.
			$this->refresh_lock();
		}

		// Clear progress if task completed fully.
		if ( ! $was_interrupted ) {
			$this->clear_progress();
		}

		// Release lock.
		$this->release_lock();

		// Store scan results if we completed at least some work.
		if ( ! empty( $completed_tasks ) || ! $was_interrupted ) {
			$this->store_scan_results( $scan_results );
		}

		$elapsed_time = microtime( true ) - $this->start_time;

		/**
		 * Fires after media scan completes.
		 *
		 * @since 1.6.0
		 *
		 * @hook wpha_media_scan_after_execute
		 *
		 * @param array $scan_results    The scan results.
		 * @param array $subtask_errors  Any errors that occurred.
		 * @param bool  $was_interrupted Whether the task was interrupted due to time limit.
		 * @param float $elapsed_time    Total execution time in seconds.
		 */
		do_action( 'wpha_media_scan_after_execute', $scan_results, $subtask_errors, $was_interrupted, $elapsed_time );

		$this->log(
			sprintf(
				'Media scan %s. Issues: %d, Bytes: %d, Time: %.2fs, Errors: %d',
				$was_interrupted ? 'interrupted (will resume)' : 'completed',
				$scan_results['total_issues'],
				$scan_results['total_bytes'],
				$elapsed_time,
				count( $subtask_errors )
			)
		);

		$result                    = $this->create_result( $scan_results['total_issues'], $scan_results['total_bytes'], empty( $subtask_errors ) );
		$result['was_interrupted'] = $was_interrupted;
		$result['errors']          = $subtask_errors;
		$result['elapsed_time']    = $elapsed_time;

		return $result;
	}

	/**
	 * Determine which scan tasks to run based on settings and options.
	 *
	 * @since 1.6.0
	 *
	 * @param array $settings Plugin settings.
	 * @param array $options  Task options.
	 * @return array List of task identifiers.
	 */
	private function determine_scan_tasks( array $settings, array $options ): array {
		$scan_tasks = array();

		// Full summary scan is always run first.
		$scan_tasks[] = 'summary';

		if ( ! empty( $settings['detect_duplicates'] ) || ! empty( $options['detect_duplicates'] ) ) {
			$scan_tasks[] = 'duplicates';
		}
		if ( ! empty( $settings['detect_large_files'] ) || ! empty( $options['detect_large_files'] ) ) {
			$scan_tasks[] = 'large_files';
		}
		if ( ! empty( $settings['check_alt_text'] ) || ! empty( $options['check_alt_text'] ) ) {
			$scan_tasks[] = 'alt_text';
		}

		return $scan_tasks;
	}

	/**
	 * Acquire a lock to prevent concurrent scans.
	 *
	 * @since 1.6.0
	 *
	 * @return bool True if lock acquired, false if already locked.
	 */
	private function acquire_lock(): bool {
		$existing_lock = get_transient( self::LOCK_TRANSIENT );

		if ( false !== $existing_lock ) {
			// Check if the lock is stale (older than lock duration).
			$lock_time = (int) $existing_lock;
			if ( time() - $lock_time < self::LOCK_DURATION ) {
				return false;
			}
			// Lock is stale, we can take it.
			$this->log( 'Stale lock detected, acquiring new lock' );
		}

		// Set lock with current timestamp.
		return set_transient( self::LOCK_TRANSIENT, time(), self::LOCK_DURATION );
	}

	/**
	 * Refresh the lock to extend its duration.
	 *
	 * @since 1.6.0
	 *
	 * @return bool True on success.
	 */
	private function refresh_lock(): bool {
		return set_transient( self::LOCK_TRANSIENT, time(), self::LOCK_DURATION );
	}

	/**
	 * Release the lock.
	 *
	 * @since 1.6.0
	 *
	 * @return bool True on success.
	 */
	private function release_lock(): bool {
		return delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Check if a scan is currently locked/running.
	 *
	 * @since 1.6.0
	 *
	 * @return bool True if a scan is running.
	 */
	public function is_scan_running(): bool {
		$lock = get_transient( self::LOCK_TRANSIENT );

		if ( false === $lock ) {
			return false;
		}

		// Check if lock is not stale.
		return ( time() - (int) $lock ) < self::LOCK_DURATION;
	}

	/**
	 * Force release the lock (for admin use).
	 *
	 * @since 1.6.0
	 *
	 * @return bool True on success.
	 */
	public function force_release_lock(): bool {
		$this->log( 'Lock forcefully released' );
		return $this->release_lock();
	}

	/**
	 * Execute a subtask with error recovery.
	 *
	 * @since 1.6.0
	 *
	 * @param string $task     Task name.
	 * @param array  $options  Task options.
	 * @param array  $settings Plugin settings.
	 * @return array Result with task-specific data and optionally 'error' key.
	 */
	private function execute_subtask_with_recovery( string $task, array $options, array $settings ): array {
		return $this->execute_with_recovery(
			function () use ( $task, $options, $settings ): array {
				return $this->execute_subtask( $task, $options, $settings );
			},
			array(),
			sprintf( 'subtask %s', $task )
		);
	}

	/**
	 * Execute a specific scan subtask.
	 *
	 * @since 1.6.0
	 *
	 * @param string $task     Task name.
	 * @param array  $options  Task options.
	 * @param array  $settings Plugin settings.
	 * @return array Result with task-specific data.
	 */
	private function execute_subtask( string $task, array $options, array $settings ): array {
		$result = array();

		switch ( $task ) {
			case 'summary':
				$full_scan = $this->scanner->get_media_summary();
				$result['summary'] = $full_scan;
				$this->log( sprintf( 'Full scan completed. Total items: %d', $full_scan['total_count'] ?? 0 ) );
				break;

			case 'duplicates':
				$duplicates = $this->duplicate_detector->find_duplicates();
				$result['duplicates'] = $duplicates;
				$result['count']      = count( $duplicates );
				$this->log( sprintf( 'Found %d duplicate files', $result['count'] ) );
				break;

			case 'large_files':
				$threshold_kb = $options['large_file_threshold_kb'] ?? ( $settings['large_file_threshold_kb'] ?? 1000 );
				$large        = $this->large_files->find_large_files( $threshold_kb );
				$total_bytes  = 0;

				foreach ( $large as $file ) {
					$total_bytes += $file['size'] ?? ( $file['current_size'] ?? 0 );
				}

				$result['large_files'] = $large;
				$result['count']       = count( $large );
				$result['total_bytes'] = $total_bytes;
				$this->log( sprintf( 'Found %d large files', $result['count'] ) );
				break;

			case 'alt_text':
				$limit       = $options['alt_text_limit'] ?? 100;
				$missing_alt = $this->alt_text_checker->find_missing_alt_text( $limit );
				$result['missing_alt'] = $missing_alt;
				$result['count']       = count( $missing_alt );
				$this->log( sprintf( 'Found %d images missing alt text', $result['count'] ) );
				break;
		}

		return $result;
	}

	/**
	 * Merge subtask results into the main scan results.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $scan_results Main scan results (modified by reference).
	 * @param string $task         Task name.
	 * @param array  $result       Subtask result.
	 * @return void
	 */
	private function merge_subtask_results( array &$scan_results, string $task, array $result ): void {
		switch ( $task ) {
			case 'duplicates':
				$scan_results['duplicates']     = $result['duplicates'] ?? array();
				$scan_results['total_issues']  += $result['count'] ?? 0;
				break;

			case 'large_files':
				$scan_results['large_files']    = $result['large_files'] ?? array();
				$scan_results['total_issues']  += $result['count'] ?? 0;
				$scan_results['total_bytes']   += $result['total_bytes'] ?? 0;
				break;

			case 'alt_text':
				$scan_results['missing_alt']    = $result['missing_alt'] ?? array();
				$scan_results['total_issues']  += $result['count'] ?? 0;
				break;
		}
	}

	/**
	 * Store scan results for later review.
	 *
	 * @since 1.2.0
	 *
	 * @param array $results Scan results.
	 * @return void
	 */
	private function store_scan_results( array $results ): void {
		$table = $this->connection->get_prefix() . 'wpha_scan_history';

		// If the table doesn't exist yet, don't fail the scan task.
		$table_check_query = $this->connection->prepare(
			'SHOW TABLES LIKE %s',
			$this->connection->esc_like( $table )
		);

		if ( null === $table_check_query ) {
			$this->log( 'Failed to prepare table check query; skipping persistence.' );
			return;
		}

		$table_exists = $this->connection->get_var( $table_check_query );

		if ( $table_exists !== $table ) {
			$this->log( 'Scan history table missing; skipping persistence.' );
			return;
		}

		$this->connection->insert(
			$table,
			array(
				'scan_type'     => 'media',
				'items_found'   => $results['total_issues'],
				'items_cleaned' => 0,
				'bytes_freed'   => 0,
				'metadata'      => wp_json_encode(
					array(
						'duplicates_count'  => count( $results['duplicates'] ),
						'large_files_count' => count( $results['large_files'] ),
						'missing_alt_count' => count( $results['missing_alt'] ),
						'total_bytes'       => $results['total_bytes'],
					)
				),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		/**
		 * Fires when media scan results are stored.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_media_scan_completed
		 *
		 * @param array $results The scan results.
		 */
		do_action( 'wpha_media_scan_completed', $results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'detect_duplicates'      => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Detect duplicate files', 'wp-admin-health-suite' ),
			),
			'detect_large_files'     => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Detect large files', 'wp-admin-health-suite' ),
			),
			'large_file_threshold_kb' => array(
				'type'        => 'integer',
				'default'     => 1000, // 1000KB = ~1MB.
				'min'         => 100,  // 100KB.
				'max'         => 5000, // 5000KB = ~5MB.
				'description' => __( 'Large file threshold in KB', 'wp-admin-health-suite' ),
			),
			'check_alt_text'         => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Check for missing alt text', 'wp-admin-health-suite' ),
			),
		);
	}
}
