<?php
/**
 * Abstract Scheduled Task
 *
 * Base class for scheduled task implementations.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Scheduler\Contracts\SchedulableInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AbstractScheduledTask
 *
 * Provides common functionality for scheduled tasks.
 *
 * @since 1.2.0
 */
abstract class AbstractScheduledTask implements SchedulableInterface {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id;

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name;

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description;

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'daily';

	/**
	 * Settings option key for enabled status.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = '';

	/**
	 * {@inheritdoc}
	 */
	public function get_task_id(): string {
		return $this->task_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_task_name(): string {
		return $this->task_name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_frequency(): string {
		return $this->default_frequency;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(): bool {
		if ( empty( $this->enabled_option_key ) ) {
			return true;
		}

		$settings = get_option( 'wpha_settings', array() );
		return ! empty( $settings[ $this->enabled_option_key ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array();
	}

	/**
	 * Create a standard result array.
	 *
	 * @param int  $items_cleaned Number of items cleaned.
	 * @param int  $bytes_freed   Bytes freed.
	 * @param bool $success       Whether execution was successful.
	 * @return array Result array.
	 */
	protected function create_result( int $items_cleaned = 0, int $bytes_freed = 0, bool $success = true ): array {
		return array(
			'items_cleaned' => $items_cleaned,
			'bytes_freed'   => $bytes_freed,
			'success'       => $success,
			'task_id'       => $this->task_id,
			'executed_at'   => current_time( 'mysql' ),
		);
	}

	/**
	 * Log task execution.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (info, warning, error).
	 * @return void
	 */
	protected function log( string $message, string $level = 'info' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[WP Admin Health Suite] [%s] Task %s: %s',
					strtoupper( $level ),
					$this->task_id,
					$message
				)
			);
		}

		/**
		 * Fires when a task logs a message.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_task_log
		 *
		 * @param string $task_id The task identifier.
		 * @param string $message The log message.
		 * @param string $level   The log level.
		 */
		do_action( 'wpha_task_log', $this->task_id, $message, $level );
	}
}
