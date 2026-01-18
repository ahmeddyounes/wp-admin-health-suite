<?php
/**
 * Task Observability Service
 *
 * Wires up task lifecycle hooks to ActivityLogger for audit/debug purposes.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class TaskObservabilityService
 *
 * Subscribes to task lifecycle hooks (wpha_task_started, wpha_task_completed,
 * wpha_task_interrupted, wpha_task_failed) and logs events via ActivityLoggerInterface.
 *
 * Also handles periodic pruning of stale progress data.
 *
 * @since 1.8.0
 */
class TaskObservabilityService {

	/**
	 * Scan type prefix for task activity logs.
	 *
	 * @var string
	 */
	public const SCAN_TYPE_PREFIX = 'task_';

	/**
	 * Transient key for throttling progress pruning.
	 *
	 * @var string
	 */
	private const PRUNE_TRANSIENT = 'wpha_progress_last_prune';

	/**
	 * Activity logger instance.
	 *
	 * @var ActivityLoggerInterface
	 */
	private ActivityLoggerInterface $activity_logger;

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings;

	/**
	 * Progress store instance.
	 *
	 * @var ProgressStore
	 */
	private ProgressStore $progress_store;

	/**
	 * Constructor.
	 *
	 * @param ActivityLoggerInterface $activity_logger Activity logger instance.
	 * @param SettingsInterface|null  $settings        Settings instance.
	 * @param ProgressStore|null      $progress_store  Progress store instance.
	 */
	public function __construct(
		ActivityLoggerInterface $activity_logger,
		?SettingsInterface $settings = null,
		?ProgressStore $progress_store = null
	) {
		$this->activity_logger = $activity_logger;
		$this->settings        = $settings;
		$this->progress_store  = $progress_store ?? new ProgressStore();
	}

	/**
	 * Register hooks for task observability.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wpha_task_started', array( $this, 'on_task_started' ), 10, 4 );
		add_action( 'wpha_task_completed', array( $this, 'on_task_completed' ), 10, 4 );
		add_action( 'wpha_task_interrupted', array( $this, 'on_task_interrupted' ), 10, 5 );
		add_action( 'wpha_task_failed', array( $this, 'on_task_failed' ), 10, 5 );

		// Hook into activity log pruning to also prune progress data.
		add_action( 'wpha_activity_log_pruned', array( $this, 'maybe_prune_progress' ) );
	}

	/**
	 * Handle task started event.
	 *
	 * @param string               $task_id    Task identifier.
	 * @param string               $task_name  Human-readable task name.
	 * @param array<string, mixed> $context    Execution context.
	 * @param float                $start_time Microtime when task started.
	 * @return void
	 */
	public function on_task_started( string $task_id, string $task_name, array $context, float $start_time ): void {
		if ( ! $this->activity_logger->table_exists() ) {
			return;
		}

		$this->activity_logger->log(
			$this->get_scan_type( $task_id, 'started' ),
			0,
			0,
			0
		);
	}

	/**
	 * Handle task completed event.
	 *
	 * @param string               $task_id      Task identifier.
	 * @param string               $task_name    Human-readable task name.
	 * @param array<string, mixed> $result       Task result data.
	 * @param float                $elapsed_time Execution time in seconds.
	 * @return void
	 */
	public function on_task_completed( string $task_id, string $task_name, array $result, float $elapsed_time ): void {
		if ( ! $this->activity_logger->table_exists() ) {
			return;
		}

		$items_found   = $result['items_found'] ?? $result['items_cleaned'] ?? 0;
		$items_cleaned = $result['items_cleaned'] ?? 0;
		$bytes_freed   = $result['bytes_freed'] ?? 0;

		$this->activity_logger->log(
			$this->get_scan_type( $task_id, 'completed' ),
			(int) $items_found,
			(int) $items_cleaned,
			(int) $bytes_freed
		);

		// Trigger progress pruning after task completion (throttled).
		$this->maybe_prune_progress();
	}

	/**
	 * Handle task interrupted event.
	 *
	 * @param string               $task_id      Task identifier.
	 * @param string               $task_name    Human-readable task name.
	 * @param array<string, mixed> $result       Partial task result data.
	 * @param array<string, mixed> $progress     Saved progress for resumption.
	 * @param float                $elapsed_time Execution time in seconds.
	 * @return void
	 */
	public function on_task_interrupted(
		string $task_id,
		string $task_name,
		array $result,
		array $progress,
		float $elapsed_time
	): void {
		if ( ! $this->activity_logger->table_exists() ) {
			return;
		}

		$items_found   = $result['items_found'] ?? $result['items_cleaned'] ?? 0;
		$items_cleaned = $result['items_cleaned'] ?? 0;
		$bytes_freed   = $result['bytes_freed'] ?? 0;

		$this->activity_logger->log(
			$this->get_scan_type( $task_id, 'interrupted' ),
			(int) $items_found,
			(int) $items_cleaned,
			(int) $bytes_freed
		);
	}

	/**
	 * Handle task failed event.
	 *
	 * @param string               $task_id      Task identifier.
	 * @param string               $task_name    Human-readable task name.
	 * @param string               $error        Error message.
	 * @param array<string, mixed> $context      Additional context.
	 * @param float                $elapsed_time Execution time in seconds.
	 * @return void
	 */
	public function on_task_failed(
		string $task_id,
		string $task_name,
		string $error,
		array $context,
		float $elapsed_time
	): void {
		if ( ! $this->activity_logger->table_exists() ) {
			return;
		}

		$this->activity_logger->log(
			$this->get_scan_type( $task_id, 'failed' ),
			0,
			0,
			0
		);
	}

	/**
	 * Prune stale progress data if threshold is reached.
	 *
	 * Throttled to run at most once per hour.
	 *
	 * @return int Number of entries pruned, or 0 if skipped.
	 */
	public function maybe_prune_progress(): int {
		// Check if we've pruned recently (within the last hour).
		if ( false !== get_transient( self::PRUNE_TRANSIENT ) ) {
			return 0;
		}

		// Set transient to prevent running again for 1 hour.
		set_transient( self::PRUNE_TRANSIENT, time(), HOUR_IN_SECONDS );

		$retention_hours = $this->get_progress_retention_hours();
		$max_age_seconds = $retention_hours * HOUR_IN_SECONDS;

		return $this->progress_store->prune_stale( $max_age_seconds );
	}

	/**
	 * Force prune stale progress data (ignores throttle).
	 *
	 * @return int Number of entries pruned.
	 */
	public function force_prune_progress(): int {
		$retention_hours = $this->get_progress_retention_hours();
		$max_age_seconds = $retention_hours * HOUR_IN_SECONDS;

		return $this->progress_store->prune_stale( $max_age_seconds );
	}

	/**
	 * Get recent task activity logs.
	 *
	 * @param int    $limit   Maximum entries to return.
	 * @param string $task_id Optional task ID to filter by.
	 * @return array Activity log entries.
	 */
	public function get_recent_task_activity( int $limit = 50, string $task_id = '' ): array {
		$type_filter = self::SCAN_TYPE_PREFIX;

		if ( '' !== $task_id ) {
			$type_filter .= $task_id;
		}

		return $this->activity_logger->get_recent( $limit, $type_filter );
	}

	/**
	 * Get progress statistics.
	 *
	 * @return array<string, mixed> Statistics about stored progress data.
	 */
	public function get_progress_statistics(): array {
		return $this->progress_store->get_statistics();
	}

	/**
	 * Get scan type string for a task event.
	 *
	 * @param string $task_id Task identifier.
	 * @param string $event   Event type (started, completed, interrupted, failed).
	 * @return string Scan type string.
	 */
	private function get_scan_type( string $task_id, string $event ): string {
		return self::SCAN_TYPE_PREFIX . $task_id . '_' . $event;
	}

	/**
	 * Get progress retention hours from settings.
	 *
	 * @return int Retention hours (default: 24).
	 */
	private function get_progress_retention_hours(): int {
		$default = 24;

		if ( null !== $this->settings ) {
			$hours = absint( $this->settings->get_setting( 'progress_retention_hours', $default ) );
			// Clamp to valid range (1-168 hours as per AdvancedSettings).
			return max( 1, min( 168, $hours ) );
		}

		return $default;
	}
}
