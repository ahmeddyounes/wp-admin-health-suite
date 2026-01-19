<?php
/**
 * Safe Mode Consistency Tests (Standalone)
 *
 * Integration tests to verify safe mode is consistently enforced across all
 * destructive operations: REST endpoints, cron tasks, and application services.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\SafeMode
 */

namespace WPAdminHealth\Tests\UnitStandalone\SafeMode;

use WPAdminHealth\Application\Database\RunCleanup;
use WPAdminHealth\Application\Media\RunScan;
use WPAdminHealth\Database\Tasks\DatabaseCleanupTask;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Tests\StandaloneTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Safe mode consistency tests.
 *
 * These tests ensure that safe mode is consistently enforced across all
 * destructive operations in the plugin, preventing accidental data loss.
 */
class SafeModeConsistencyTest extends StandaloneTestCase {

	/**
	 * Settings mock.
	 *
	 * @var SettingsInterface&MockObject
	 */
	private $settings;

	/**
	 * Analyzer mock.
	 *
	 * @var AnalyzerInterface&MockObject
	 */
	private $analyzer;

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
	 * Scanner mock.
	 *
	 * @var ScannerInterface&MockObject
	 */
	private $scanner;

	/**
	 * Duplicate detector mock.
	 *
	 * @var DuplicateDetectorInterface&MockObject
	 */
	private $duplicate_detector;

	/**
	 * Large files mock.
	 *
	 * @var LargeFilesInterface&MockObject
	 */
	private $large_files;

	/**
	 * Alt text checker mock.
	 *
	 * @var AltTextCheckerInterface&MockObject
	 */
	private $alt_text_checker;

	/**
	 * Reference finder mock.
	 *
	 * @var ReferenceFinderInterface&MockObject
	 */
	private $reference_finder;

	/**
	 * Exclusions mock.
	 *
	 * @var ExclusionsInterface&MockObject
	 */
	private $exclusions;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->settings           = $this->createMock( SettingsInterface::class );
		$this->analyzer           = $this->createMock( AnalyzerInterface::class );
		$this->revisions_manager  = $this->createMock( RevisionsManagerInterface::class );
		$this->transients_cleaner = $this->createMock( TransientsCleanerInterface::class );
		$this->orphaned_cleaner   = $this->createMock( OrphanedCleanerInterface::class );
		$this->trash_cleaner      = $this->createMock( TrashCleanerInterface::class );
		$this->optimizer          = $this->createMock( OptimizerInterface::class );
		$this->scanner            = $this->createMock( ScannerInterface::class );
		$this->duplicate_detector = $this->createMock( DuplicateDetectorInterface::class );
		$this->large_files        = $this->createMock( LargeFilesInterface::class );
		$this->alt_text_checker   = $this->createMock( AltTextCheckerInterface::class );
		$this->reference_finder   = $this->createMock( ReferenceFinderInterface::class );
		$this->exclusions         = $this->createMock( ExclusionsInterface::class );
	}

	// ========================================================================
	// SECTION: RunCleanup Application Service Tests
	// ========================================================================

	/**
	 * Test RunCleanup respects safe mode from settings.
	 *
	 * @dataProvider cleanupTypeProvider
	 * @param string $type Cleanup type.
	 */
	public function test_run_cleanup_respects_safe_mode_from_settings( string $type ): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		// Configure mocks to never call destructive methods.
		$this->assert_no_destructive_operations_called();

		// Configure preview count methods.
		$this->configure_preview_methods();

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => $type ) );

		$this->assertTrue( $result['safe_mode'], "Safe mode flag should be set for {$type}" );
		$this->assertTrue( $result['preview_only'], "Preview only flag should be set for {$type}" );
	}

	/**
	 * Test RunCleanup allows safe_mode override via options (enable safe mode).
	 */
	public function test_run_cleanup_options_can_enable_safe_mode(): void {
		// Settings say safe mode is disabled.
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		// Destructive methods should NOT be called.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		// Preview methods SHOULD be called.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 50 );

		$this->revisions_manager
			->method( 'get_revisions_size_estimate' )
			->willReturn( 10000 );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute(
			array(
				'type'      => 'revisions',
				'safe_mode' => true, // Override to enable safe mode.
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 50, $result['would_delete'] );
	}

	/**
	 * Test RunCleanup allows safe_mode override via options (disable safe mode).
	 */
	public function test_run_cleanup_options_can_disable_safe_mode(): void {
		// Settings say safe mode is enabled.
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		// Destructive methods SHOULD be called.
		$this->revisions_manager
			->expects( $this->once() )
			->method( 'delete_all_revisions' )
			->willReturn( array( 'deleted' => 25, 'bytes_freed' => 12500 ) );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute(
			array(
				'type'      => 'revisions',
				'safe_mode' => false, // Override to disable safe mode.
			)
		);

		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertArrayNotHasKey( 'preview_only', $result );
		$this->assertEquals( 25, $result['deleted'] );
	}

	// ========================================================================
	// SECTION: DatabaseCleanupTask Cron Tests
	// ========================================================================

	/**
	 * Test DatabaseCleanupTask respects safe mode from SettingsInterface.
	 */
	public function test_database_cleanup_task_respects_safe_mode_from_settings(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		// Destructive methods should NOT be called.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		$this->transients_cleaner
			->expects( $this->never() )
			->method( 'delete_expired_transients' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_postmeta' );

		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_trashed_posts' );

		$this->optimizer
			->expects( $this->never() )
			->method( 'optimize_all_tables' );

		// Preview methods SHOULD be called.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 100 );

		$task = new DatabaseCleanupTask(
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->optimizer,
			$this->settings
		);

		$result = $task->execute(
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
	 * Test DatabaseCleanupTask respects safe_mode override in options.
	 */
	public function test_database_cleanup_task_respects_safe_mode_option_override(): void {
		// Settings say safe mode is disabled.
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( false );

		// Destructive methods should NOT be called because options override.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		// Preview methods SHOULD be called.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 75 );

		$task = new DatabaseCleanupTask(
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->optimizer,
			$this->settings
		);

		$result = $task->execute(
			array(
				'clean_revisions' => true,
				'safe_mode'       => true, // Override via options.
				'time_limit'      => 60,
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
	}

	/**
	 * Test DatabaseCleanupTask can operate without SettingsInterface (backwards compatible).
	 */
	public function test_database_cleanup_task_backwards_compatible_without_settings(): void {
		// No SettingsInterface provided - should use fallback.
		$this->revisions_manager
			->method( 'delete_all_revisions' )
			->willReturn( array( 'deleted' => 10, 'bytes_freed' => 5000 ) );

		$task = new DatabaseCleanupTask(
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->optimizer
			// No settings parameter.
		);

		$result = $task->execute(
			array(
				'clean_revisions' => true,
				'time_limit'      => 60,
			)
		);

		// Without settings and no constant, should execute normally.
		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertEquals( 10, $result['items_cleaned'] );
	}

	// ========================================================================
	// SECTION: RunScan Application Service Tests
	// ========================================================================

	/**
	 * Test RunScan respects safe mode from settings.
	 */
	public function test_run_scan_respects_safe_mode_from_settings(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 500 );

		$this->scanner
			->method( 'get_media_summary' )
			->willReturn(
				array(
					'total_count'     => 100,
					'total_size'      => 50000,
					'unused_count'    => 10,
					'unused_size'     => 5000,
					'duplicate_count' => 5,
					'large_count'     => 3,
				)
			);

		$use_case = new RunScan(
			$this->settings,
			$this->scanner,
			$this->duplicate_detector,
			$this->large_files,
			$this->alt_text_checker,
			$this->reference_finder,
			$this->exclusions
		);

		$result = $use_case->execute(
			array(
				'type' => 'summary',
			)
		);

		// Summary scan is read-only, so safe_mode doesn't affect it.
		$this->assertEquals( 'summary', $result['type'] );
		$this->assertEquals( 100, $result['total_count'] );
	}

	/**
	 * Test RunScan allows safe_mode override via options.
	 */
	public function test_run_scan_options_can_enable_safe_mode(): void {
		// Settings say safe mode is disabled.
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 500 );

		$this->duplicate_detector
			->method( 'find_duplicates' )
			->willReturn( array() );

		$this->duplicate_detector
			->method( 'get_potential_savings' )
			->willReturn( 0 );

		$this->duplicate_detector
			->method( 'get_duplicate_groups' )
			->willReturn( array() );

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$use_case = new RunScan(
			$this->settings,
			$this->scanner,
			$this->duplicate_detector,
			$this->large_files,
			$this->alt_text_checker,
			$this->reference_finder,
			$this->exclusions
		);

		$result = $use_case->execute(
			array(
				'type'      => 'duplicates',
				'safe_mode' => true, // Override to enable safe mode.
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
	}

	// ========================================================================
	// SECTION: Preview Data Verification Tests
	// ========================================================================

	/**
	 * Test safe mode returns proper would_delete counts for revisions.
	 */
	public function test_safe_mode_returns_would_delete_for_revisions(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 150 );

		$this->revisions_manager
			->method( 'get_revisions_size_estimate' )
			->willReturn( 75000 );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => 'revisions' ) );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 150, $result['would_delete'] );
		$this->assertEquals( 0, $result['bytes_freed'] );
		$this->assertEquals( 75000, $result['would_free'] );
	}

	/**
	 * Test safe mode returns proper would_delete counts for transients.
	 */
	public function test_safe_mode_returns_would_delete_for_transients(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( '' );

		$this->transients_cleaner
			->method( 'count_transients' )
			->willReturn( 200 );

		$this->transients_cleaner
			->method( 'get_transients_size' )
			->willReturn( 50000 );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => 'transients' ) );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 200, $result['would_delete'] );
		$this->assertEquals( 0, $result['bytes_freed'] );
		$this->assertEquals( 50000, $result['would_free'] );
	}

	/**
	 * Test safe mode returns proper would_delete counts for spam.
	 */
	public function test_safe_mode_returns_would_delete_for_spam(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->analyzer
			->method( 'get_spam_comments_count' )
			->willReturn( 50 );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => 'spam' ) );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 50, $result['would_delete'] );
	}

	/**
	 * Test safe mode returns proper would_delete counts for trash.
	 */
	public function test_safe_mode_returns_would_delete_for_trash(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->analyzer
			->method( 'get_trashed_posts_count' )
			->willReturn( 25 );

		$this->analyzer
			->method( 'get_trashed_comments_count' )
			->willReturn( 15 );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => 'trash' ) );

		$this->assertEquals( 0, $result['posts_deleted'] );
		$this->assertEquals( 25, $result['posts_would_delete'] );
		$this->assertEquals( 0, $result['comments_deleted'] );
		$this->assertEquals( 15, $result['comments_would_delete'] );
	}

	/**
	 * Test safe mode returns proper would_delete counts for orphaned data.
	 */
	public function test_safe_mode_returns_would_delete_for_orphaned(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );

		$this->orphaned_cleaner
			->method( 'find_orphaned_postmeta' )
			->willReturn( array( 1, 2, 3, 4, 5 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_commentmeta' )
			->willReturn( array( 6, 7, 8 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_termmeta' )
			->willReturn( array( 9, 10 ) );

		$this->orphaned_cleaner
			->method( 'find_orphaned_relationships' )
			->willReturn( array( 11 ) );

		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$result = $use_case->execute( array( 'type' => 'orphaned' ) );

		$this->assertEquals( 0, $result['postmeta_deleted'] );
		$this->assertEquals( 5, $result['postmeta_would_delete'] );
		$this->assertEquals( 0, $result['commentmeta_deleted'] );
		$this->assertEquals( 3, $result['commentmeta_would_delete'] );
		$this->assertEquals( 0, $result['termmeta_deleted'] );
		$this->assertEquals( 2, $result['termmeta_would_delete'] );
		$this->assertEquals( 0, $result['relationships_deleted'] );
		$this->assertEquals( 1, $result['relationships_would_delete'] );
	}

	// ========================================================================
	// SECTION: Data Providers
	// ========================================================================

	/**
	 * Provide all cleanup types for testing.
	 *
	 * @return array<array{0: string}>
	 */
	public static function cleanupTypeProvider(): array {
		return array(
			'revisions'  => array( 'revisions' ),
			'transients' => array( 'transients' ),
			'spam'       => array( 'spam' ),
			'trash'      => array( 'trash' ),
			'orphaned'   => array( 'orphaned' ),
		);
	}

	// ========================================================================
	// SECTION: Helper Methods
	// ========================================================================

	/**
	 * Assert that no destructive operations are called.
	 */
	private function assert_no_destructive_operations_called(): void {
		// Revisions manager.
		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		// Transients cleaner.
		$this->transients_cleaner
			->expects( $this->never() )
			->method( 'delete_expired_transients' );

		$this->transients_cleaner
			->expects( $this->never() )
			->method( 'delete_all_transients' );

		// Orphaned cleaner.
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

		// Trash cleaner.
		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_spam_comments' );

		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_trashed_posts' );

		$this->trash_cleaner
			->expects( $this->never() )
			->method( 'delete_trashed_comments' );
	}

	/**
	 * Configure preview methods that should be called in safe mode.
	 */
	private function configure_preview_methods(): void {
		// Revisions manager preview methods.
		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 50 );

		$this->revisions_manager
			->method( 'get_revisions_size_estimate' )
			->willReturn( 25000 );

		// Transients cleaner preview methods.
		$this->transients_cleaner
			->method( 'count_transients' )
			->willReturn( 100 );

		$this->transients_cleaner
			->method( 'get_transients_size' )
			->willReturn( 50000 );

		// Analyzer preview methods.
		$this->analyzer
			->method( 'get_spam_comments_count' )
			->willReturn( 25 );

		$this->analyzer
			->method( 'get_trashed_posts_count' )
			->willReturn( 10 );

		$this->analyzer
			->method( 'get_trashed_comments_count' )
			->willReturn( 5 );

		// Orphaned cleaner preview methods.
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
			->willReturn( array( 7, 8 ) );
	}
}
