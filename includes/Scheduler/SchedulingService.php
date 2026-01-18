<?php
/**
 * Scheduling Service
 *
 * Single-authority service for managing task schedules across WP-Cron
 * and Action Scheduler. Provides methods to schedule, reschedule,
 * unschedule, and reconcile task schedules based on plugin settings.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulingServiceInterface;
use WPAdminHealth\Scheduler\Contracts\SchedulerRegistryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SchedulingService
 *
 * Centralized scheduling service for managing task schedules.
 *
 * @since 2.0.0
 */
class SchedulingService implements SchedulingServiceInterface {

	/**
	 * Action Scheduler group name.
	 *
	 * @var string
	 */
	public const ACTION_SCHEDULER_GROUP = 'wpha_scheduling';

	/**
	 * Task hook prefix.
	 *
	 * @var string
	 */
	public const TASK_HOOK_PREFIX = 'wpha_';

	/**
	 * Task configuration mapping.
	 *
	 * Maps task IDs to their settings keys and defaults.
	 *
	 * @var array<string, array{enabled_key: string, frequency_key: string, default_frequency: string}>
	 */
	private const TASK_CONFIG = array(
		'database_cleanup'  => array(
			'enabled_key'       => 'enable_scheduled_db_cleanup',
			'frequency_key'     => 'database_cleanup_frequency',
			'default_frequency' => 'weekly',
		),
		'media_scan'        => array(
			'enabled_key'       => 'enable_scheduled_media_scan',
			'frequency_key'     => 'media_scan_frequency',
			'default_frequency' => 'weekly',
		),
		'performance_check' => array(
			'enabled_key'       => 'enable_scheduled_performance_check',
			'frequency_key'     => 'performance_check_frequency',
			'default_frequency' => 'daily',
		),
	);

	/**
	 * Settings interface.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Scheduler registry.
	 *
	 * @var SchedulerRegistryInterface|null
	 */
	private ?SchedulerRegistryInterface $registry;

	/**
	 * Constructor.
	 *
	 * @param SettingsInterface               $settings Settings interface.
	 * @param SchedulerRegistryInterface|null $registry Optional scheduler registry.
	 */
	public function __construct(
		SettingsInterface $settings,
		?SchedulerRegistryInterface $registry = null
	) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	/**
	 * {@inheritdoc}
	 */
	public function schedule( string $task_id, string $frequency, ?int $next_run = null ): bool {
		if ( 'disabled' === $frequency ) {
			return $this->unschedule( $task_id );
		}

		$interval = $this->get_interval_seconds( $frequency );
		if ( false === $interval ) {
			return false;
		}

		$hook = $this->get_task_hook( $task_id );

		if ( null === $next_run ) {
			$next_run = $this->calculate_next_run_time();
		}

		// Unschedule any existing schedules first.
		$this->clear_existing_schedules( $hook );

		if ( $this->is_action_scheduler_available() ) {
			as_schedule_recurring_action( $next_run, $interval, $hook, array(), self::ACTION_SCHEDULER_GROUP );
		} else {
			$schedule_name = $this->get_cron_schedule_name( $frequency );
			wp_schedule_event( $next_run, $schedule_name, $hook );
		}

		/**
		 * Fires after a task is scheduled.
		 *
		 * @since 2.0.0
		 *
		 * @hook wpha_task_scheduled
		 *
		 * @param string $task_id   Task identifier.
		 * @param string $frequency Scheduling frequency.
		 * @param int    $next_run  Next run timestamp.
		 */
		do_action( 'wpha_task_scheduled', $task_id, $frequency, $next_run );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reschedule( string $task_id, string $frequency, ?int $next_run = null ): bool {
		$this->unschedule( $task_id );

		if ( 'disabled' === $frequency ) {
			return true;
		}

		return $this->schedule( $task_id, $frequency, $next_run );
	}

	/**
	 * {@inheritdoc}
	 */
	public function unschedule( string $task_id ): bool {
		$hook = $this->get_task_hook( $task_id );
		$this->clear_existing_schedules( $hook );

		/**
		 * Fires after a task is unscheduled.
		 *
		 * @since 2.0.0
		 *
		 * @hook wpha_task_unscheduled
		 *
		 * @param string $task_id Task identifier.
		 */
		do_action( 'wpha_task_unscheduled', $task_id );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function unschedule_all(): int {
		$count = 0;

		foreach ( array_keys( self::TASK_CONFIG ) as $task_id ) {
			if ( $this->unschedule( $task_id ) ) {
				++$count;
			}
		}

		/**
		 * Fires after all tasks are unscheduled.
		 *
		 * @since 2.0.0
		 *
		 * @hook wpha_all_tasks_unscheduled
		 *
		 * @param int $count Number of tasks unscheduled.
		 */
		do_action( 'wpha_all_tasks_unscheduled', $count );

		return $count;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_next_run( string $task_id ): ?int {
		$hook = $this->get_task_hook( $task_id );

		if ( $this->is_action_scheduler_available() && function_exists( 'as_next_scheduled_action' ) ) {
			$next = as_next_scheduled_action( $hook, array(), self::ACTION_SCHEDULER_GROUP );
			if ( false !== $next ) {
				return (int) $next;
			}
		}

		$next = wp_next_scheduled( $hook );
		return false === $next ? null : (int) $next;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_scheduled( string $task_id ): bool {
		return null !== $this->get_next_run( $task_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_frequency( string $task_id ): ?string {
		$hook = $this->get_task_hook( $task_id );

		// Check WP-Cron first.
		$crons = _get_cron_array();
		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $cron ) {
				if ( isset( $cron[ $hook ] ) ) {
					foreach ( $cron[ $hook ] as $hash => $data ) {
						if ( isset( $data['schedule'] ) ) {
							return $this->normalize_frequency( $data['schedule'] );
						}
					}
				}
			}
		}

		// Check Action Scheduler.
		if ( $this->is_action_scheduler_available() && function_exists( 'as_get_scheduled_actions' ) ) {
			$actions = as_get_scheduled_actions(
				array(
					'hook'   => $hook,
					'status' => \ActionScheduler_Store::STATUS_PENDING,
					'group'  => self::ACTION_SCHEDULER_GROUP,
				),
				'ids'
			);

			if ( ! empty( $actions ) ) {
				// Action Scheduler doesn't store frequency directly.
				// We need to look at the interval between scheduled actions or check settings.
				$config = self::TASK_CONFIG[ $task_id ] ?? null;
				if ( $config ) {
					return $this->settings->get_setting(
						$config['frequency_key'],
						$config['default_frequency']
					);
				}
			}
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile(): array {
		$result = array(
			'scheduled'   => array(),
			'unscheduled' => array(),
			'rescheduled' => array(),
			'unchanged'   => array(),
			'errors'      => array(),
		);

		// Check if scheduler is enabled globally.
		$scheduler_enabled = (bool) $this->settings->get_setting( 'scheduler_enabled', true );

		if ( ! $scheduler_enabled ) {
			// Unschedule all tasks.
			foreach ( array_keys( self::TASK_CONFIG ) as $task_id ) {
				if ( $this->is_scheduled( $task_id ) ) {
					if ( $this->unschedule( $task_id ) ) {
						$result['unscheduled'][] = $task_id;
					} else {
						$result['errors'][ $task_id ] = 'Failed to unschedule';
					}
				}
			}
			return $result;
		}

		$next_run = $this->calculate_next_run_time();

		foreach ( self::TASK_CONFIG as $task_id => $config ) {
			try {
				$this->reconcile_task( $task_id, $config, $next_run, $result );
			} catch ( \Throwable $e ) {
				$result['errors'][ $task_id ] = $e->getMessage();
			}
		}

		/**
		 * Fires after schedule reconciliation is complete.
		 *
		 * @since 2.0.0
		 *
		 * @hook wpha_schedules_reconciled
		 *
		 * @param array $result Reconciliation result.
		 */
		do_action( 'wpha_schedules_reconciled', $result );

		return $result;
	}

	/**
	 * Reconcile a single task's schedule.
	 *
	 * @param string $task_id  Task identifier.
	 * @param array  $config   Task configuration.
	 * @param int    $next_run Next run timestamp.
	 * @param array  $result   Result array (passed by reference).
	 * @return void
	 */
	private function reconcile_task( string $task_id, array $config, int $next_run, array &$result ): void {
		$task_enabled     = (bool) $this->settings->get_setting( $config['enabled_key'], true );
		$desired_frequency = $this->settings->get_setting(
			$config['frequency_key'],
			$config['default_frequency']
		);

		$is_scheduled     = $this->is_scheduled( $task_id );
		$current_frequency = $this->get_frequency( $task_id );

		// Task should not be scheduled.
		if ( ! $task_enabled || 'disabled' === $desired_frequency ) {
			if ( $is_scheduled ) {
				if ( $this->unschedule( $task_id ) ) {
					$result['unscheduled'][] = $task_id;
				} else {
					$result['errors'][ $task_id ] = 'Failed to unschedule';
				}
			} else {
				$result['unchanged'][] = $task_id;
			}
			return;
		}

		// Task should be scheduled.
		if ( ! $is_scheduled ) {
			if ( $this->schedule( $task_id, $desired_frequency, $next_run ) ) {
				$result['scheduled'][] = $task_id;
			} else {
				$result['errors'][ $task_id ] = 'Failed to schedule';
			}
			return;
		}

		// Task is scheduled - check if frequency changed.
		if ( $current_frequency !== $desired_frequency ) {
			if ( $this->reschedule( $task_id, $desired_frequency, $next_run ) ) {
				$result['rescheduled'][] = $task_id;
			} else {
				$result['errors'][ $task_id ] = 'Failed to reschedule';
			}
			return;
		}

		$result['unchanged'][] = $task_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_status(): array {
		$status = array();

		foreach ( self::TASK_CONFIG as $task_id => $config ) {
			$status[ $task_id ] = array(
				'scheduled'             => $this->is_scheduled( $task_id ),
				'next_run'              => $this->get_next_run( $task_id ),
				'frequency'             => $this->get_frequency( $task_id ),
				'enabled_in_settings'   => (bool) $this->settings->get_setting( $config['enabled_key'], true ),
				'frequency_in_settings' => $this->settings->get_setting(
					$config['frequency_key'],
					$config['default_frequency']
				),
			);
		}

		return $status;
	}

	/**
	 * {@inheritdoc}
	 */
	public function calculate_next_run_time( ?int $preferred_hour = null ): int {
		if ( null === $preferred_hour ) {
			$preferred_hour = (int) $this->settings->get_setting( 'preferred_time', 2 );
		}

		$preferred_hour = min( 23, max( 0, $preferred_hour ) );

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$preferred = $now->setTime( $preferred_hour, 0, 0 );

		// If preferred time has passed today, schedule for tomorrow.
		if ( $preferred->getTimestamp() <= $now->getTimestamp() ) {
			$preferred = $preferred->modify( '+1 day' );
		}

		return $preferred->getTimestamp();
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * Get the WordPress hook name for a task.
	 *
	 * @param string $task_id Task identifier.
	 * @return string Hook name.
	 */
	private function get_task_hook( string $task_id ): string {
		// Use registry method if available for consistency.
		if ( $this->registry instanceof SchedulerRegistryInterface ) {
			return $this->registry->get_task_hook( $task_id );
		}

		return self::TASK_HOOK_PREFIX . $task_id;
	}

	/**
	 * Clear existing schedules for a hook.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	private function clear_existing_schedules( string $hook ): void {
		// Clear from Action Scheduler.
		if ( $this->is_action_scheduler_available() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), self::ACTION_SCHEDULER_GROUP );
		}

		// Clear from WP-Cron.
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Get interval in seconds for a frequency.
	 *
	 * @param string $frequency Frequency name.
	 * @return int|false Interval in seconds or false if invalid.
	 */
	private function get_interval_seconds( string $frequency ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return $intervals[ $frequency ] ?? false;
	}

	/**
	 * Get WP-Cron schedule name for a frequency.
	 *
	 * @param string $frequency Frequency.
	 * @return string Schedule name.
	 */
	private function get_cron_schedule_name( string $frequency ): string {
		$schedules = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);

		return $schedules[ $frequency ] ?? 'daily';
	}

	/**
	 * Normalize a WP-Cron schedule name to a frequency string.
	 *
	 * @param string $schedule WP-Cron schedule name.
	 * @return string Normalized frequency.
	 */
	private function normalize_frequency( string $schedule ): string {
		$map = array(
			'daily'      => 'daily',
			'weekly'     => 'weekly',
			'monthly'    => 'monthly',
			'hourly'     => 'daily',
			'twicedaily' => 'daily',
		);

		return $map[ $schedule ] ?? $schedule;
	}

	/**
	 * Get all known task IDs.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string> Task IDs.
	 */
	public function get_known_task_ids(): array {
		return array_keys( self::TASK_CONFIG );
	}

	/**
	 * Get task configuration.
	 *
	 * @since 2.0.0
	 *
	 * @param string $task_id Task identifier.
	 * @return array|null Task configuration or null if not found.
	 */
	public function get_task_config( string $task_id ): ?array {
		return self::TASK_CONFIG[ $task_id ] ?? null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function schedule_initial_tasks(): array {
		$result = array(
			'scheduled' => array(),
			'skipped'   => array(),
			'errors'    => array(),
		);

		// Check if scheduler is enabled globally.
		$scheduler_enabled = (bool) $this->settings->get_setting( 'scheduler_enabled', true );

		if ( ! $scheduler_enabled ) {
			// All tasks should be skipped if scheduler is disabled.
			foreach ( array_keys( self::TASK_CONFIG ) as $task_id ) {
				$result['skipped'][] = $task_id;
			}
			return $result;
		}

		$next_run = $this->calculate_next_run_time();

		foreach ( self::TASK_CONFIG as $task_id => $config ) {
			try {
				$task_enabled = (bool) $this->settings->get_setting( $config['enabled_key'], true );
				$frequency    = $this->settings->get_setting(
					$config['frequency_key'],
					$config['default_frequency']
				);

				// Skip if task is disabled or frequency is disabled.
				if ( ! $task_enabled || 'disabled' === $frequency ) {
					$result['skipped'][] = $task_id;
					continue;
				}

				if ( $this->schedule( $task_id, $frequency, $next_run ) ) {
					$result['scheduled'][] = $task_id;
				} else {
					$result['errors'][ $task_id ] = 'Failed to schedule';
				}
			} catch ( \Throwable $e ) {
				$result['errors'][ $task_id ] = $e->getMessage();
			}
		}

		/**
		 * Fires after initial tasks have been scheduled.
		 *
		 * @since 2.0.0
		 *
		 * @hook wpha_initial_tasks_scheduled
		 *
		 * @param array $result Scheduling result.
		 */
		do_action( 'wpha_initial_tasks_scheduled', $result );

		return $result;
	}
}
