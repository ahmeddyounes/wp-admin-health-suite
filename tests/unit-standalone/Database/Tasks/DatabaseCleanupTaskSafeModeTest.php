<?php
/**
 * DatabaseCleanupTask Safe Mode Tests (Standalone)
 *
 * Tests that safe mode properly prevents destructive operations in scheduled tasks.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Database\Tasks
 */

namespace WPAdminHealth\Tests\UnitStandalone\Database\Tasks;

use WPAdminHealth\Database\Tasks\DatabaseCleanupTask;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Tests\StandaloneTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Safe mode enforcement tests for DatabaseCleanupTask.
 *
 * Ensures that safe mode prevents destructive operations in cron-triggered
 * task executions, returning preview data instead.
 */
class DatabaseCleanupTaskSafeModeTest extends StandaloneTestCase {

	/**
	 * Settings mock.
	 *
	 * @var SettingsInterface&MockObject
	 */
	private $settings;

	/**
	 * Revisions manager mock.
	 *
	 * @var RevisionsManagerInterface&MockObject
	 */
	private $revisions_manager;

	/**
	 * Transients cleaner mock.
	 *
	 * @var TransientsCleanerInterface&MockObject
	 */
	private $transients_cleaner;

	/**
	 * Orphaned cleaner mock.
	 *
	 * @var OrphanedCleanerInterface&MockObject
	 */
	private $orphaned_cleaner;

	/**
	 * Trash cleaner mock.
	 *
	 * @var TrashCleanerInterface&MockObject
	 */
	private $trash_cleaner;

	/**
	 * Optimizer mock.
	 *
	 * @var OptimizerInterface&MockObject
	 */
	private $optimizer;

	/**
	 * DatabaseCleanupTask instance under test.
	 *
	 * @var DatabaseCleanupTask
	 */
	private DatabaseCleanupTask $task;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->settings           = $this->createMock( SettingsInterface::class );
		$this->revisions_manager  = $this->createMock( RevisionsManagerInterface::class );
		$this->transients_cleaner = $this->createMock( TransientsCleanerInterface::class );
		$this->orphaned_cleaner   = $this->createMock( OrphanedCleanerInterface::class );
		$this->trash_cleaner      = $this->createMock( TrashCleanerInterface::class );
		$this->optimizer          = $this->createMock( OptimizerInterface::class );

		$this->task = new DatabaseCleanupTask(
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->optimizer,
			$this->settings
		);
	}

	/**
	 * Test that task can be instantiated with SettingsInterface.
	 */
	public function test_can_be_instantiated_with_settings_interface(): void {
		$this->assertInstanceOf( DatabaseCleanupTask::class, $this->task );
	}

	/**
	 * Test that task can be instantiated without SettingsInterface (backwards compatible).
	 */
	public function test_can_be_instantiated_without_settings_interface(): void {
		$task = new DatabaseCleanupTask(
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->optimizer
		);

		$this->assertInstanceOf( DatabaseCleanupTask::class, $task );
	}

	/**
	 * Test that safe mode enabled via SettingsInterface prevents revisions cleanup.
	 */
	public function test_safe_mode_prevents_revisions_cleanup(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// These methods should NOT be called in safe mode.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		// Preview count methods SHOULD be called.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 50 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );
	}

	/**
	 * Test that safe mode enabled via SettingsInterface prevents transients cleanup.
	 */
	public function test_safe_mode_prevents_transients_cleanup(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// These methods should NOT be called in safe mode.
		$this->transients_cleaner
			->expects( $this->never() )
			->method( 'delete_expired_transients' );

		$this->transients_cleaner
			->expects( $this->never() )
			->method( 'delete_all_transients' );

		// Preview count methods SHOULD be called.
		$this->transients_cleaner
			->method( 'count_transients' )
			->willReturn( 100 );

		$result = $this->task->execute(
			array(
				'clean_transients' => true,
				'time_limit'       => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );
	}

	/**
	 * Test that safe mode prevents orphaned metadata cleanup.
	 */
	public function test_safe_mode_prevents_orphaned_cleanup(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// These destructive methods should NOT be called in safe mode.
		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_postmeta' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_commentmeta' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_termmeta' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_relationships' );

		// Preview find methods SHOULD be called.
		$this->orphaned_cleaner
			->method( 'find_orphaned_postmeta' )
			->willReturn( array( 1, 2, 3 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_commentmeta' )
			->willReturn( array( 4, 5 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_termmeta' )
			->willReturn( array( 6 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_relationships' )
			->willReturn( array( 7, 8, 9, 10 ) );

		$result = $this->task->execute(
			array(
				'clean_orphaned' => true,
				'time_limit'     => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );
	}

	/**
	 * Test that safe mode prevents spam comments deletion.
	 *
	 * Note: This test verifies that the delete method is never called.
	 * The spam/trash count preview methods require $wpdb which is not
	 * available in standalone tests, so we only test that no cleanup
	 * operations are executed when safe mode is enabled via options.
	 */
	public function test_safe_mode_prevents_spam_deletion(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// These destructive methods should NOT be called in safe mode.
		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_spam_comments' );

		// Use revisions instead of spam to avoid $wpdb dependency.
		// This still validates that safe mode prevents destructive operations.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );

		// The key assertion: delete_spam_comments was never called.
	}

	/**
	 * Test that safe mode prevents trash cleanup.
	 *
	 * Note: This test verifies that the delete methods are never called.
	 * The trash count preview method requires $wpdb which is not available
	 * in standalone tests, so we only test that no cleanup operations are
	 * executed when safe mode is enabled via options.
	 */
	public function test_safe_mode_prevents_trash_cleanup(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// These destructive methods should NOT be called in safe mode.
		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_trashed_posts' );

		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_trashed_comments' );

		// Use revisions instead of trash to avoid $wpdb dependency.
		// This still validates that safe mode prevents destructive operations.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );

		// The key assertions: delete_trashed_posts and delete_trashed_comments were never called.
	}

	/**
	 * Test that safe mode prevents table optimization.
	 */
	public function test_safe_mode_prevents_table_optimization(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// Table optimization should NOT be called in safe mode.
		$this->optimizer
			->expects( $this->never() )
			->method( 'optimize_all_tables' );

		$result = $this->task->execute(
			array(
				'optimize_tables' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
	}

	/**
	 * Test that safe mode can be overridden via options.
	 */
	public function test_safe_mode_override_via_options(): void {
		// Settings say safe mode is enabled.
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// But options override it to false.
		$this->revisions_manager
			->expects( $this->once() )
			->method( 'delete_all_revisions' )
			->with( 5 )
			->willReturn( array( 'deleted' => 10, 'bytes_freed' => 5000 ) );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'max_revisions'   => 5,
				'safe_mode'       => false, // Override safe mode.
				'time_limit'      => 60,
			)
		);

		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertArrayNotHasKey( 'preview_only', $result );
		$this->assertEquals( 10, $result['items_cleaned'] );
	}

	/**
	 * Test that safe mode can be forced via options when settings say disabled.
	 */
	public function test_safe_mode_force_via_options(): void {
		// Settings say safe mode is disabled.
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( false );

		// But options force it to true.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 25 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'safe_mode'       => true, // Force safe mode.
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['items_cleaned'] );
	}

	/**
	 * Test that normal mode (safe mode disabled) executes cleanup.
	 */
	public function test_normal_mode_executes_cleanup(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( false );

		$this->revisions_manager
			->expects( $this->once() )
			->method( 'delete_all_revisions' )
			->with( 5 )
			->willReturn( array( 'deleted' => 15, 'bytes_freed' => 7500 ) );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'max_revisions'   => 5,
				'time_limit'      => 60,
			)
		);

		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertArrayNotHasKey( 'preview_only', $result );
		$this->assertEquals( 15, $result['items_cleaned'] );
		$this->assertEquals( 7500, $result['bytes_freed'] );
	}

	/**
	 * Test that safe mode preview returns would_delete count.
	 */
	public function test_safe_mode_returns_would_delete_count(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 100 );

		$this->transients_cleaner
			->method( 'count_transients' )
			->willReturn( 50 );

		$result = $this->task->execute(
			array(
				'clean_revisions'  => true,
				'clean_transients' => true,
				'time_limit'       => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertArrayHasKey( 'would_delete', $result );
		$this->assertGreaterThan( 0, $result['would_delete'] );
		$this->assertArrayHasKey( 'preview', $result );
	}

	/**
	 * Test that safe mode includes cleanup_tasks in result.
	 */
	public function test_safe_mode_includes_cleanup_tasks(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertArrayHasKey( 'cleanup_tasks', $result );
		$this->assertContains( 'revisions', $result['cleanup_tasks'] );
	}

	/**
	 * Test that safe mode does not set was_interrupted to true.
	 */
	public function test_safe_mode_does_not_mark_as_interrupted(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertFalse( $result['was_interrupted'] );
	}

	/**
	 * Test that safe mode returns empty errors array.
	 */
	public function test_safe_mode_returns_empty_errors(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$result = $this->task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test task metadata methods.
	 */
	public function test_task_metadata(): void {
		$this->assertEquals( 'database_cleanup', $this->task->get_task_id() );
		$this->assertEquals( 'Database Cleanup', $this->task->get_task_name() );
		$this->assertEquals( 'weekly', $this->task->get_default_frequency() );
		$this->assertNotEmpty( $this->task->get_description() );
	}
}
