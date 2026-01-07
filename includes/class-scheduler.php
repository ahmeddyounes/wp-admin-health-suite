<?php
/**
 * Scheduler Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles scheduled cleanup tasks using WP-Cron.
 */
class Scheduler {

	/**
	 * Cron hook prefix.
	 *
	 * @var string
	 */
	const CRON_HOOK_PREFIX = 'wpha_scheduled_cleanup_';

	/**
	 * Valid task frequencies.
	 *
	 * @var array
	 */
	private $valid_frequencies = array( 'daily', 'weekly', 'monthly', 'custom_days' );

	/**
	 * Initialize the scheduler.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Register cron schedules.
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );

		// Hook for running scheduled tasks.
		add_action( self::CRON_HOOK_PREFIX . 'run', array( $this, 'execute_scheduled_task' ), 10, 1 );

		// Check for missed schedules on init.
		add_action( 'init', array( $this, 'check_missed_schedules' ) );
	}

	/**
	 * Register custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function register_custom_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Weekly', 'wp-admin-health-suite' ),
			);
		}

		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Monthly', 'wp-admin-health-suite' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule a cleanup task.
	 *
	 * @param string $type      Task type (e.g., 'transients', 'revisions').
	 * @param string $frequency Frequency (daily, weekly, monthly, custom_days).
	 * @param array  $options   Additional options including email notification settings and custom_days.
	 * @return int|bool Task ID on success, false on failure.
	 */
	public function schedule_cleanup( $type, $frequency, $options = array() ) {
		global $wpdb;

		// Validate frequency.
		if ( ! in_array( $frequency, $this->valid_frequencies, true ) ) {
			return false;
		}

		// Validate custom_days if frequency is custom_days.
		if ( 'custom_days' === $frequency ) {
			if ( empty( $options['custom_days'] ) || ! is_numeric( $options['custom_days'] ) || $options['custom_days'] < 1 ) {
				return false;
			}
		}

		// Calculate next run time.
		$next_run = $this->calculate_next_run( $frequency, $options );

		// Prepare settings.
		$settings = wp_json_encode( $options );

		// Insert into database.
		$table = $wpdb->prefix . 'wpha_scheduled_tasks';
		$result = $wpdb->insert(
			$table,
			array(
				'task_type' => $type,
				'frequency' => $frequency,
				'next_run'  => $next_run,
				'status'    => 'active',
				'settings'  => $settings,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$task_id = $wpdb->insert_id;

		// Schedule WP-Cron event.
		$this->schedule_cron_event( $task_id, $type, $frequency, $options );

		// Log the scheduling.
		$this->log_execution( $task_id, 'scheduled', 'Task scheduled successfully' );

		return $task_id;
	}

	/**
	 * Unschedule a cleanup task.
	 *
	 * @param string $type Task type to unschedule.
	 * @return bool True on success, false on failure.
	 */
	public function unschedule_cleanup( $type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scheduled_tasks';

		// Get all tasks of this type.
		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE task_type = %s AND status = 'active'",
				$type
			)
		);

		if ( empty( $tasks ) ) {
			return false;
		}

		$success = true;

		foreach ( $tasks as $task ) {
			// Unschedule cron event.
			$hook = self::CRON_HOOK_PREFIX . 'run';
			wp_clear_scheduled_hook( $hook, array( $task->id ) );

			// Update status in database.
			$result = $wpdb->update(
				$table,
				array( 'status' => 'inactive' ),
				array( 'id' => $task->id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( ! $result ) {
				$success = false;
			} else {
				$this->log_execution( $task->id, 'unscheduled', 'Task unscheduled successfully' );
			}
		}

		return $success;
	}

	/**
	 * Get all scheduled tasks.
	 *
	 * @param string $status Optional. Filter by status (active, inactive). Default 'active'.
	 * @return array Array of scheduled tasks.
	 */
	public function get_scheduled_tasks( $status = 'active' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scheduled_tasks';

		if ( $status ) {
			$tasks = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY next_run ASC",
					$status
				)
			);
		} else {
			$tasks = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY next_run ASC"
			);
		}

		// Parse settings JSON.
		foreach ( $tasks as $task ) {
			$task->settings = json_decode( $task->settings, true );
		}

		return $tasks;
	}

	/**
	 * Run a scheduled task.
	 *
	 * @param int $task_id Task ID to run.
	 * @return bool True on success, false on failure.
	 */
	public function run_scheduled_task( $task_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scheduled_tasks';

		// Get task details.
		$task = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$task_id
			)
		);

		if ( ! $task || 'active' !== $task->status ) {
			return false;
		}

		$settings = json_decode( $task->settings, true );

		// Execute the cleanup based on task type.
		$result = $this->execute_cleanup( $task->task_type, $settings );

		// Update last_run and next_run.
		$next_run = $this->calculate_next_run( $task->frequency, $settings );
		$wpdb->update(
			$table,
			array(
				'last_run' => current_time( 'mysql' ),
				'next_run' => $next_run,
			),
			array( 'id' => $task_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Send email notification if enabled.
		if ( ! empty( $settings['email_notification'] ) && $settings['email_notification'] ) {
			$this->send_completion_email( $task, $result );
		}

		// Log the execution.
		$log_message = sprintf(
			'Task completed. Items cleaned: %d, Bytes freed: %d',
			$result['items_cleaned'] ?? 0,
			$result['bytes_freed'] ?? 0
		);
		$this->log_execution( $task_id, 'completed', $log_message );

		// Reschedule the cron event.
		$this->schedule_cron_event( $task_id, $task->task_type, $task->frequency, $settings );

		return true;
	}

	/**
	 * Execute the scheduled task (called by WP-Cron).
	 *
	 * @param int $task_id Task ID.
	 * @return void
	 */
	public function execute_scheduled_task( $task_id ) {
		$this->run_scheduled_task( $task_id );
	}

	/**
	 * Check for missed schedules and run them.
	 *
	 * @return void
	 */
	public function check_missed_schedules() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scheduled_tasks';

		// Get tasks that should have run but haven't.
		$missed_tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'active' AND next_run < %s AND (last_run IS NULL OR last_run < next_run)",
				current_time( 'mysql' )
			)
		);

		foreach ( $missed_tasks as $task ) {
			// Run the missed task.
			$this->run_scheduled_task( $task->id );
			$this->log_execution( $task->id, 'recovered', 'Missed schedule recovered and executed' );
		}
	}

	/**
	 * Calculate next run time based on frequency.
	 *
	 * @param string $frequency Frequency type.
	 * @param array  $options   Additional options.
	 * @return string Next run datetime in MySQL format.
	 */
	private function calculate_next_run( $frequency, $options = array() ) {
		$current_time = current_time( 'timestamp' );

		switch ( $frequency ) {
			case 'daily':
				$next_time = $current_time + DAY_IN_SECONDS;
				break;

			case 'weekly':
				$next_time = $current_time + WEEK_IN_SECONDS;
				break;

			case 'monthly':
				$next_time = $current_time + ( 30 * DAY_IN_SECONDS );
				break;

			case 'custom_days':
				$days = ! empty( $options['custom_days'] ) ? (int) $options['custom_days'] : 1;
				$next_time = $current_time + ( $days * DAY_IN_SECONDS );
				break;

			default:
				$next_time = $current_time + DAY_IN_SECONDS;
				break;
		}

		return gmdate( 'Y-m-d H:i:s', $next_time );
	}

	/**
	 * Schedule WP-Cron event for a task.
	 *
	 * @param int    $task_id   Task ID.
	 * @param string $type      Task type.
	 * @param string $frequency Frequency.
	 * @param array  $options   Options.
	 * @return void
	 */
	private function schedule_cron_event( $task_id, $type, $frequency, $options ) {
		$hook = self::CRON_HOOK_PREFIX . 'run';

		// Clear any existing schedule for this task.
		wp_clear_scheduled_hook( $hook, array( $task_id ) );

		// Determine recurrence.
		$recurrence = $frequency;
		if ( 'custom_days' === $frequency ) {
			// For custom days, we'll use a single event and reschedule after execution.
			$days = ! empty( $options['custom_days'] ) ? (int) $options['custom_days'] : 1;
			$next_time = time() + ( $days * DAY_IN_SECONDS );
			wp_schedule_single_event( $next_time, $hook, array( $task_id ) );
			return;
		}

		// Map frequency to WP-Cron recurrence.
		$recurrence_map = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);

		if ( isset( $recurrence_map[ $frequency ] ) ) {
			$recurrence = $recurrence_map[ $frequency ];
		}

		// Schedule recurring event.
		$next_time = $this->calculate_next_run_timestamp( $frequency, $options );
		wp_schedule_event( $next_time, $recurrence, $hook, array( $task_id ) );
	}

	/**
	 * Calculate next run timestamp.
	 *
	 * @param string $frequency Frequency type.
	 * @param array  $options   Additional options.
	 * @return int Timestamp for next run.
	 */
	private function calculate_next_run_timestamp( $frequency, $options = array() ) {
		$current_time = time();

		switch ( $frequency ) {
			case 'daily':
				return $current_time + DAY_IN_SECONDS;

			case 'weekly':
				return $current_time + WEEK_IN_SECONDS;

			case 'monthly':
				return $current_time + ( 30 * DAY_IN_SECONDS );

			case 'custom_days':
				$days = ! empty( $options['custom_days'] ) ? (int) $options['custom_days'] : 1;
				return $current_time + ( $days * DAY_IN_SECONDS );

			default:
				return $current_time + DAY_IN_SECONDS;
		}
	}

	/**
	 * Execute cleanup based on task type.
	 *
	 * @param string $task_type Task type.
	 * @param array  $settings  Task settings.
	 * @return array Result with items_cleaned and bytes_freed.
	 */
	private function execute_cleanup( $task_type, $settings ) {
		// This is a placeholder. The actual cleanup logic would be implemented
		// based on the task type (e.g., transients, revisions, etc.).
		$result = array(
			'items_cleaned' => 0,
			'bytes_freed'   => 0,
		);

		// Hook for custom cleanup execution.
		$result = apply_filters( 'wpha_execute_cleanup', $result, $task_type, $settings );

		// Store result in scan history.
		$this->store_scan_history( $task_type, $result );

		return $result;
	}

	/**
	 * Store scan history.
	 *
	 * @param string $task_type Task type.
	 * @param array  $result    Cleanup result.
	 * @return void
	 */
	private function store_scan_history( $task_type, $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scan_history';

		$wpdb->insert(
			$table,
			array(
				'scan_type'      => $task_type,
				'items_found'    => $result['items_cleaned'] ?? 0,
				'items_cleaned'  => $result['items_cleaned'] ?? 0,
				'bytes_freed'    => $result['bytes_freed'] ?? 0,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}

	/**
	 * Send completion email notification.
	 *
	 * @param object $task   Task object.
	 * @param array  $result Cleanup result.
	 * @return bool True on success, false on failure.
	 */
	private function send_completion_email( $task, $result ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: Site name, 2: Task type */
			__( '[%1$s] Scheduled Cleanup Completed: %2$s', 'wp-admin-health-suite' ),
			$site_name,
			$task->task_type
		);

		$message = sprintf(
			/* translators: 1: Task type, 2: Items cleaned, 3: Bytes freed, 4: Execution time */
			__( "A scheduled cleanup task has been completed.\n\nTask Type: %1\$s\nItems Cleaned: %2\$d\nBytes Freed: %3\$s\nExecution Time: %4\$s\n\nThis is an automated notification from WP Admin Health Suite.", 'wp-admin-health-suite' ),
			$task->task_type,
			$result['items_cleaned'] ?? 0,
			size_format( $result['bytes_freed'] ?? 0 ),
			current_time( 'mysql' )
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $admin_email, $subject, $message, $headers );
	}

	/**
	 * Log execution details.
	 *
	 * @param int    $task_id Task ID.
	 * @param string $action  Action performed.
	 * @param string $message Log message.
	 * @return void
	 */
	private function log_execution( $task_id, $action, $message ) {
		// Use WordPress error log for execution logging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[WP Admin Health Suite] Task #%d - %s: %s',
					$task_id,
					$action,
					$message
				)
			);
		}

		// Hook for custom logging.
		do_action( 'wpha_scheduler_log', $task_id, $action, $message );
	}
}
