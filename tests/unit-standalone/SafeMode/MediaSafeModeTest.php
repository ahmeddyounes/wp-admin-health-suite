<?php
/**
 * Media Safe Mode Tests (Standalone)
 *
 * Tests that safe mode properly prevents destructive operations
 * in media cleanup operations.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\SafeMode
 */

namespace WPAdminHealth\Tests\UnitStandalone\SafeMode;

use WPAdminHealth\Application\Media\RunScan;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Tests\StandaloneTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Media safe mode tests.
 *
 * These tests ensure that safe mode is properly enforced in media operations,
 * preventing accidental deletion of media files.
 */
class MediaSafeModeTest extends StandaloneTestCase {

	/**
	 * Settings mock.
	 *
	 * @var SettingsInterface&MockObject
	 */
	private $settings;

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
	 * Activity logger mock.
	 *
	 * @var ActivityLoggerInterface&MockObject
	 */
	private $activity_logger;

	/**
	 * RunScan instance under test.
	 *
	 * @var RunScan
	 */
	private RunScan $use_case;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->settings           = $this->createMock( SettingsInterface::class );
		$this->scanner            = $this->createMock( ScannerInterface::class );
		$this->duplicate_detector = $this->createMock( DuplicateDetectorInterface::class );
		$this->large_files        = $this->createMock( LargeFilesInterface::class );
		$this->alt_text_checker   = $this->createMock( AltTextCheckerInterface::class );
		$this->reference_finder   = $this->createMock( ReferenceFinderInterface::class );
		$this->exclusions         = $this->createMock( ExclusionsInterface::class );
		$this->activity_logger    = $this->createMock( ActivityLoggerInterface::class );

		$this->use_case = new RunScan(
			$this->settings,
			$this->scanner,
			$this->duplicate_detector,
			$this->large_files,
			$this->alt_text_checker,
			$this->reference_finder,
			$this->exclusions,
			$this->activity_logger
		);
	}

	/**
	 * Test RunScan can be instantiated with all dependencies.
	 */
	public function test_can_be_instantiated(): void {
		$this->assertInstanceOf( RunScan::class, $this->use_case );
	}

	/**
	 * Test RunScan can be instantiated without activity logger.
	 */
	public function test_can_be_instantiated_without_activity_logger(): void {
		$use_case = new RunScan(
			$this->settings,
			$this->scanner,
			$this->duplicate_detector,
			$this->large_files,
			$this->alt_text_checker,
			$this->reference_finder,
			$this->exclusions
		);

		$this->assertInstanceOf( RunScan::class, $use_case );
	}

	/**
	 * Test is_safe_mode_enabled returns value from settings.
	 */
	public function test_is_safe_mode_enabled_returns_settings_value(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->assertTrue( $this->use_case->is_safe_mode_enabled() );
	}

	/**
	 * Test safe mode can be enabled via options override.
	 */
	public function test_safe_mode_enabled_via_options(): void {
		// Settings say safe mode is disabled.
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( false );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

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

		// Activity logger should NOT be called in safe mode.
		$this->activity_logger
			->expects( $this->never() )
			->method( 'log_media_operation' );

		$result = $this->use_case->execute(
			array(
				'type'      => 'duplicates',
				'safe_mode' => true, // Override via options.
			)
		);

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
	}

	/**
	 * Test safe mode can be disabled via options override.
	 */
	public function test_safe_mode_disabled_via_options(): void {
		// Settings say safe mode is enabled.
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

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

		// Activity logger SHOULD be called when not in safe mode.
		$this->activity_logger
			->expects( $this->once() )
			->method( 'log_media_operation' )
			->with( 'scan_duplicates', $this->anything() );

		$result = $this->use_case->execute(
			array(
				'type'      => 'duplicates',
				'safe_mode' => false, // Override via options.
			)
		);

		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertArrayNotHasKey( 'preview_only', $result );
	}

	/**
	 * Test duplicate scan works in safe mode.
	 */
	public function test_duplicate_scan_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

		$duplicate_groups = array(
			'hash1' => array( 1, 2, 3 ),
			'hash2' => array( 4, 5 ),
		);

		$this->duplicate_detector
			->method( 'find_duplicates' )
			->willReturn( $duplicate_groups );

		$this->duplicate_detector
			->method( 'get_potential_savings' )
			->willReturn( 50000 );

		$this->duplicate_detector
			->method( 'get_duplicate_groups' )
			->willReturn( $duplicate_groups );

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$result = $this->use_case->execute( array( 'type' => 'duplicates' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 2, $result['groups_count'] );
		$this->assertEquals( 5, $result['total_items'] );
		$this->assertEquals( 50000, $result['potential_savings'] );
	}

	/**
	 * Test large files scan works in safe mode.
	 */
	public function test_large_files_scan_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

		$large_files = array(
			array( 'id' => 1, 'size' => 1000000 ),
			array( 'id' => 2, 'size' => 2000000 ),
			array( 'id' => 3, 'size' => 1500000 ),
		);

		$this->large_files
			->method( 'find_large_files' )
			->willReturn( $large_files );

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$result = $this->use_case->execute( array( 'type' => 'large_files' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 3, $result['count'] );
		$this->assertEquals( 4500000, $result['total_size'] );
	}

	/**
	 * Test alt text scan works in safe mode.
	 */
	public function test_alt_text_scan_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

		$this->alt_text_checker
			->method( 'get_alt_text_coverage' )
			->willReturn(
				array(
					'total'   => 100,
					'with'    => 75,
					'without' => 25,
					'percent' => 75.0,
				)
			);

		$this->alt_text_checker
			->method( 'find_missing_alt_text' )
			->willReturn(
				array(
					array( 'id' => 1 ),
					array( 'id' => 2 ),
				)
			);

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$result = $this->use_case->execute( array( 'type' => 'alt_text' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 2, $result['missing_count'] );
		$this->assertEquals( 75.0, $result['coverage']['percent'] );
	}

	/**
	 * Test unused media scan works in safe mode.
	 */
	public function test_unused_media_scan_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

		$this->scanner
			->method( 'scan_unused_media' )
			->willReturn(
				array(
					'unused'   => array( 1, 2, 3, 4, 5 ),
					'scanned'  => 100,
					'total'    => 100,
					'has_more' => false,
				)
			);

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$result = $this->use_case->execute( array( 'type' => 'unused' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 5, $result['unused_count'] );
		$this->assertEquals( 100, $result['scanned'] );
	}

	/**
	 * Test full scan works in safe mode.
	 */
	public function test_full_scan_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

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

		$this->duplicate_detector
			->method( 'find_duplicates' )
			->willReturn( array() );

		$this->duplicate_detector
			->method( 'get_potential_savings' )
			->willReturn( 0 );

		$this->duplicate_detector
			->method( 'get_duplicate_groups' )
			->willReturn( array() );

		$this->large_files
			->method( 'find_large_files' )
			->willReturn( array() );

		$this->alt_text_checker
			->method( 'get_alt_text_coverage' )
			->willReturn(
				array(
					'total'   => 100,
					'with'    => 100,
					'without' => 0,
					'percent' => 100.0,
				)
			);

		$this->alt_text_checker
			->method( 'find_missing_alt_text' )
			->willReturn( array() );

		$this->exclusions
			->method( 'filter_excluded' )
			->willReturnArgument( 0 );

		$result = $this->use_case->execute( array( 'type' => 'full' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
		$this->assertEquals( 'full', $result['type'] );
		$this->assertArrayHasKey( 'summary', $result );
		$this->assertArrayHasKey( 'duplicates', $result );
		$this->assertArrayHasKey( 'large_files', $result );
		$this->assertArrayHasKey( 'alt_text', $result );
	}

	/**
	 * Test activity logging is skipped in safe mode.
	 */
	public function test_activity_logging_skipped_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

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

		// Activity logger should NOT be called in safe mode.
		$this->activity_logger
			->expects( $this->never() )
			->method( 'log_media_operation' );

		$this->use_case->execute( array( 'type' => 'duplicates' ) );
	}

	/**
	 * Test activity logging happens when not in safe mode.
	 */
	public function test_activity_logging_happens_when_not_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( false );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

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

		// Activity logger SHOULD be called when not in safe mode.
		$this->activity_logger
			->expects( $this->once() )
			->method( 'log_media_operation' )
			->with( 'scan_duplicates', $this->anything() );

		$this->use_case->execute( array( 'type' => 'duplicates' ) );
	}

	/**
	 * Test exclusions are applied in safe mode.
	 */
	public function test_exclusions_applied_in_safe_mode(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->settings
			->method( 'get_setting' )
			->willReturn( 500 );

		$all_large_files = array(
			array( 'id' => 1, 'size' => 1000000 ),
			array( 'id' => 2, 'size' => 2000000 ),
			array( 'id' => 3, 'size' => 1500000 ),
		);

		$this->large_files
			->method( 'find_large_files' )
			->willReturn( $all_large_files );

		// Simulate exclusion of file ID 2.
		$this->exclusions
			->method( 'filter_excluded' )
			->willReturn( array( 1, 3 ) );

		$result = $this->use_case->execute( array( 'type' => 'large_files' ) );

		$this->assertTrue( $result['safe_mode'] );
		$this->assertEquals( 2, $result['count'] );
		$this->assertEquals( 1, $result['excluded_count'] );
	}

	/**
	 * Test scan time is tracked.
	 */
	public function test_scan_time_is_tracked(): void {
		$this->settings
			->method( 'is_safe_mode_enabled' )
			->willReturn( true );

		$this->scanner
			->method( 'get_media_summary' )
			->willReturn(
				array(
					'total_count'     => 100,
					'total_size'      => 50000,
					'unused_count'    => 0,
					'unused_size'     => 0,
					'duplicate_count' => 0,
					'large_count'     => 0,
				)
			);

		$result = $this->use_case->execute( array( 'type' => 'summary' ) );

		$this->assertArrayHasKey( 'scan_time_ms', $result );
		$this->assertIsFloat( $result['scan_time_ms'] );
		$this->assertGreaterThanOrEqual( 0, $result['scan_time_ms'] );
	}
}
