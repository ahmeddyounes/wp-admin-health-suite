<?php
/**
 * SchedulingService standalone tests.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Scheduler;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Scheduler\SchedulingService;
use WPAdminHealth\Scheduler\Contracts\SchedulerRegistryInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

class SchedulingServiceTest extends StandaloneTestCase {

	/**
	 * Mock settings.
	 *
	 * @var SettingsInterface&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $settings;

	/**
	 * Mock registry.
	 *
	 * @var SchedulerRegistryInterface&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $registry;

	/**
	 * Service under test.
	 *
	 * @var SchedulingService
	 */
	private SchedulingService $service;

	/**
	 * {@inheritdoc}
	 */
	protected function setup_test_environment(): void {
		$this->settings = $this->createMock( SettingsInterface::class );
		$this->registry = $this->createMock( SchedulerRegistryInterface::class );
		$this->service  = new SchedulingService( $this->settings, $this->registry );
	}

	public function test_get_known_task_ids_returns_all_tasks(): void {
		$task_ids = $this->service->get_known_task_ids();

		$this->assertContains( 'database_cleanup', $task_ids );
		$this->assertContains( 'media_scan', $task_ids );
		$this->assertContains( 'performance_check', $task_ids );
		$this->assertCount( 3, $task_ids );
	}

	public function test_get_task_config_returns_config_for_valid_task(): void {
		$config = $this->service->get_task_config( 'database_cleanup' );

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'enabled_key', $config );
		$this->assertArrayHasKey( 'frequency_key', $config );
		$this->assertArrayHasKey( 'default_frequency', $config );
		$this->assertSame( 'enable_scheduled_db_cleanup', $config['enabled_key'] );
		$this->assertSame( 'database_cleanup_frequency', $config['frequency_key'] );
		$this->assertSame( 'weekly', $config['default_frequency'] );
	}

	public function test_get_task_config_returns_null_for_invalid_task(): void {
		$config = $this->service->get_task_config( 'nonexistent_task' );

		$this->assertNull( $config );
	}

	public function test_calculate_next_run_time_uses_setting_when_no_hour_provided(): void {
		$this->settings
			->method( 'get_setting' )
			->willReturnCallback(
				function ( string $key, $default = null ) {
					if ( 'preferred_time' === $key ) {
						return 3;
					}
					return $default;
				}
			);

		$next_run = $this->service->calculate_next_run_time();

		// Should be in the future.
		$this->assertGreaterThan( time(), $next_run );
	}

	public function test_calculate_next_run_time_uses_provided_hour(): void {
		$next_run = $this->service->calculate_next_run_time( 5 );

		// Should be in the future.
		$this->assertGreaterThan( time(), $next_run );

		// Convert to DateTime to check hour.
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$date     = new \DateTime( '@' . $next_run );
		$date->setTimezone( $timezone );

		$this->assertSame( 5, (int) $date->format( 'G' ) );
	}

	public function test_calculate_next_run_time_clamps_hour_to_valid_range(): void {
		// Hour out of range should be clamped.
		$next_run = $this->service->calculate_next_run_time( 25 );
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$date     = new \DateTime( '@' . $next_run );
		$date->setTimezone( $timezone );

		$this->assertSame( 23, (int) $date->format( 'G' ) );

		$next_run_negative = $this->service->calculate_next_run_time( -5 );
		$date_negative     = new \DateTime( '@' . $next_run_negative );
		$date_negative->setTimezone( $timezone );

		$this->assertSame( 0, (int) $date_negative->format( 'G' ) );
	}

	public function test_is_action_scheduler_available_returns_bool(): void {
		$result = $this->service->is_action_scheduler_available();

		$this->assertIsBool( $result );
	}

	/**
	 * Tests for get_status and reconcile require WordPress functions.
	 *
	 * Full testing of get_status() and reconcile() requires WordPress
	 * functions (wp_next_scheduled, _get_cron_array, wp_clear_scheduled_hook)
	 * and should be done in integration tests with WordPress loaded.
	 */
	public function test_interface_contract_is_satisfied(): void {
		$this->assertInstanceOf(
			\WPAdminHealth\Scheduler\Contracts\SchedulingServiceInterface::class,
			$this->service
		);
	}

	public function test_task_config_has_required_keys_for_all_tasks(): void {
		foreach ( $this->service->get_known_task_ids() as $task_id ) {
			$config = $this->service->get_task_config( $task_id );

			$this->assertNotNull( $config, "Config for {$task_id} should not be null" );
			$this->assertArrayHasKey( 'enabled_key', $config );
			$this->assertArrayHasKey( 'frequency_key', $config );
			$this->assertArrayHasKey( 'default_frequency', $config );
		}
	}

	public function test_default_frequencies_are_valid(): void {
		$valid_frequencies = array( 'daily', 'weekly', 'monthly' );

		foreach ( $this->service->get_known_task_ids() as $task_id ) {
			$config = $this->service->get_task_config( $task_id );
			$this->assertContains(
				$config['default_frequency'],
				$valid_frequencies,
				"Default frequency for {$task_id} should be valid"
			);
		}
	}

	public function test_schedule_initial_tasks_skips_all_when_scheduler_disabled(): void {
		$this->settings
			->method( 'get_setting' )
			->willReturnCallback(
				function ( string $key, $default = null ) {
					if ( 'scheduler_enabled' === $key ) {
						return false;
					}
					return $default;
				}
			);

		$result = $this->service->schedule_initial_tasks();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'scheduled', $result );
		$this->assertArrayHasKey( 'skipped', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertEmpty( $result['scheduled'] );
		$this->assertCount( 3, $result['skipped'] );
		$this->assertContains( 'database_cleanup', $result['skipped'] );
		$this->assertContains( 'media_scan', $result['skipped'] );
		$this->assertContains( 'performance_check', $result['skipped'] );
	}

	public function test_schedule_initial_tasks_returns_correct_structure(): void {
		$this->settings
			->method( 'get_setting' )
			->willReturnCallback(
				function ( string $key, $default = null ) {
					if ( 'scheduler_enabled' === $key ) {
						return true;
					}
					if ( 'enable_scheduled_db_cleanup' === $key ) {
						return false; // Disabled.
					}
					if ( 'media_scan_frequency' === $key ) {
						return 'disabled'; // Frequency disabled.
					}
					return $default;
				}
			);

		$result = $this->service->schedule_initial_tasks();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'scheduled', $result );
		$this->assertArrayHasKey( 'skipped', $result );
		$this->assertArrayHasKey( 'errors', $result );

		// database_cleanup is disabled via enabled_key.
		// media_scan is disabled via frequency_key = 'disabled'.
		// Both should be in 'skipped'.
		$this->assertContains( 'database_cleanup', $result['skipped'] );
		$this->assertContains( 'media_scan', $result['skipped'] );
	}

	public function test_schedule_initial_tasks_respects_enabled_settings(): void {
		$called_settings = array();

		$this->settings
			->method( 'get_setting' )
			->willReturnCallback(
				function ( string $key, $default = null ) use ( &$called_settings ) {
					$called_settings[] = $key;

					if ( 'scheduler_enabled' === $key ) {
						return true;
					}
					// Disable all tasks via enabled_key.
					if ( str_starts_with( $key, 'enable_' ) ) {
						return false;
					}
					return $default;
				}
			);

		$result = $this->service->schedule_initial_tasks();

		// All tasks should be skipped since they are disabled.
		$this->assertEmpty( $result['scheduled'] );
		$this->assertCount( 3, $result['skipped'] );

		// Verify enabled_key settings were checked.
		$this->assertContains( 'enable_scheduled_db_cleanup', $called_settings );
		$this->assertContains( 'enable_scheduled_media_scan', $called_settings );
		$this->assertContains( 'enable_scheduled_performance_check', $called_settings );
	}

	public function test_schedule_initial_tasks_respects_frequency_disabled(): void {
		$this->settings
			->method( 'get_setting' )
			->willReturnCallback(
				function ( string $key, $default = null ) {
					if ( 'scheduler_enabled' === $key ) {
						return true;
					}
					// Enable all tasks.
					if ( str_starts_with( $key, 'enable_' ) ) {
						return true;
					}
					// Set all frequencies to disabled.
					if ( str_ends_with( $key, '_frequency' ) ) {
						return 'disabled';
					}
					return $default;
				}
			);

		$result = $this->service->schedule_initial_tasks();

		// All tasks should be skipped since frequency is disabled.
		$this->assertEmpty( $result['scheduled'] );
		$this->assertCount( 3, $result['skipped'] );
	}
}
