<?php
/**
 * RunCleanup Use Case Tests (Standalone)
 *
 * Tests for the Database RunCleanup application service.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Application\Database
 */

namespace WPAdminHealth\Tests\UnitStandalone\Application\Database;

use WPAdminHealth\Application\Database\RunCleanup;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Exceptions\ValidationException;
use WPAdminHealth\Tests\StandaloneTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * RunCleanup use case tests.
 */
class RunCleanupTest extends StandaloneTestCase {

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
	 * Activity logger mock.
	 *
	 * @var ActivityLoggerInterface&MockObject
	 */
	private $activity_logger;

	/**
	 * RunCleanup instance under test.
	 *
	 * @var RunCleanup
	 */
	private RunCleanup $use_case;

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
		$this->activity_logger    = $this->createMock( ActivityLoggerInterface::class );

		$this->use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner,
			$this->activity_logger
		);
	}

	/**
	 * Test that RunCleanup can be instantiated with all dependencies.
	 */
	public function test_can_be_instantiated(): void {
		$this->assertInstanceOf( RunCleanup::class, $this->use_case );
	}

	/**
	 * Test that RunCleanup can be instantiated without activity logger.
	 */
	public function test_can_be_instantiated_without_activity_logger(): void {
		$use_case = new RunCleanup(
			$this->settings,
			$this->analyzer,
			$this->revisions_manager,
			$this->transients_cleaner,
			$this->orphaned_cleaner,
			$this->trash_cleaner
		);

		$this->assertInstanceOf( RunCleanup::class, $use_case );
	}

	/**
	 * Test execute throws ValidationException for invalid type.
	 */
	public function test_execute_throws_exception_for_invalid_type(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'Invalid parameter: type' );

		$this->use_case->execute( array( 'type' => 'invalid' ) );
	}

	/**
	 * Test execute throws ValidationException when no type provided.
	 */
	public function test_execute_throws_exception_when_no_type_provided(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'Invalid parameter: type' );

		$this->use_case->execute();
	}

	/**
	 * Test ValidationException contains proper context.
	 */
	public function test_validation_exception_has_proper_context(): void {
		try {
			$this->use_case->execute( array( 'type' => 'bad_type' ) );
			$this->fail( 'Expected ValidationException to be thrown' );
		} catch ( ValidationException $e ) {
			$this->assertEquals( 'validation_invalid_param', $e->getCode() );
			$this->assertEquals( 400, $e->get_http_status() );
			$context = $e->get_context();
			$this->assertEquals( 'type', $context['param'] );
		}
	}

	/**
	 * Test revisions cleanup in normal mode.
	 */
	public function test_clean_revisions_normal_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->expects( $this->once() )
			->method( 'delete_all_revisions' )
			->with( 5 )
			->willReturn( array( 'deleted' => 10, 'bytes_freed' => 5000 ) );

		$this->activity_logger
			->expects( $this->once() )
			->method( 'log_database_cleanup' )
			->with( 'revisions', $this->anything() );

		$result = $this->use_case->execute(
			array(
				'type'    => 'revisions',
				'options' => array( 'keep_per_post' => 5 ),
			)
		);

		$this->assertEquals( 'revisions', $result['type'] );
		$this->assertEquals( 10, $result['deleted'] );
		$this->assertEquals( 5000, $result['bytes_freed'] );
		$this->assertArrayNotHasKey( 'safe_mode', $result );
	}

	/**
	 * Test revisions cleanup in safe mode.
	 */
	public function test_clean_revisions_safe_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->expects( $this->never() )
			->method( 'delete_all_revisions' );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 50 );

		$this->revisions_manager
			->method( 'get_revisions_size_estimate' )
			->willReturn( 10000 );

		$this->activity_logger
			->expects( $this->never() )
			->method( 'log_database_cleanup' );

		$result = $this->use_case->execute(
			array(
				'type' => 'revisions',
			)
		);

		$this->assertEquals( 'revisions', $result['type'] );
		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 50, $result['would_delete'] );
		$this->assertEquals( 10000, $result['would_free'] );
		$this->assertTrue( $result['safe_mode'] );
		$this->assertTrue( $result['preview_only'] );
	}

	/**
	 * Test transients cleanup in normal mode.
	 */
	public function test_clean_transients_normal_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( '' );

		$this->transients_cleaner
			->expects( $this->once() )
			->method( 'delete_expired_transients' )
			->with( array() )
			->willReturn( array( 'deleted' => 15, 'bytes_freed' => 2000 ) );

		$result = $this->use_case->execute(
			array(
				'type'    => 'transients',
				'options' => array( 'expired_only' => true ),
			)
		);

		$this->assertEquals( 'transients', $result['type'] );
		$this->assertEquals( 15, $result['deleted'] );
		$this->assertEquals( 2000, $result['bytes_freed'] );
		$this->assertTrue( $result['expired_only'] );
	}

	/**
	 * Test transients cleanup all transients.
	 */
	public function test_clean_transients_all(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( '' );

		$this->transients_cleaner
			->expects( $this->once() )
			->method( 'delete_all_transients' )
			->with( array() )
			->willReturn( array( 'deleted' => 25, 'bytes_freed' => 3000 ) );

		$result = $this->use_case->execute(
			array(
				'type'    => 'transients',
				'options' => array( 'expired_only' => false ),
			)
		);

		$this->assertEquals( 25, $result['deleted'] );
		$this->assertFalse( $result['expired_only'] );
	}

	/**
	 * Test spam cleanup in normal mode.
	 */
	public function test_clean_spam_normal_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->trash_cleaner
			->expects( $this->once() )
			->method( 'delete_spam_comments' )
			->with( 30 )
			->willReturn( array( 'deleted' => 5, 'errors' => array() ) );

		$result = $this->use_case->execute(
			array(
				'type'    => 'spam',
				'options' => array( 'older_than_days' => 30 ),
			)
		);

		$this->assertEquals( 'spam', $result['type'] );
		$this->assertEquals( 5, $result['deleted'] );
		$this->assertEquals( 30, $result['older_than_days'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test trash cleanup in normal mode.
	 */
	public function test_clean_trash_normal_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->trash_cleaner
			->expects( $this->once() )
			->method( 'delete_trashed_posts' )
			->with( array( 'post' ), 7 )
			->willReturn( array( 'deleted' => 3, 'errors' => array() ) );

		$this->trash_cleaner
			->expects( $this->once() )
			->method( 'delete_trashed_comments' )
			->with( 7 )
			->willReturn( array( 'deleted' => 2, 'errors' => array() ) );

		$result = $this->use_case->execute(
			array(
				'type'    => 'trash',
				'options' => array(
					'older_than_days' => 7,
					'post_types'      => array( 'post' ),
				),
			)
		);

		$this->assertEquals( 'trash', $result['type'] );
		$this->assertEquals( 3, $result['posts_deleted'] );
		$this->assertEquals( 2, $result['comments_deleted'] );
		$this->assertEquals( 7, $result['older_than_days'] );
		$this->assertEquals( array( 'post' ), $result['post_types'] );
	}

	/**
	 * Test orphaned cleanup in normal mode.
	 */
	public function test_clean_orphaned_normal_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_postmeta' )
			->willReturn( 10 );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_commentmeta' )
			->willReturn( 5 );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_termmeta' )
			->willReturn( 3 );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_relationships' )
			->willReturn( 2 );

		$result = $this->use_case->execute(
			array(
				'type' => 'orphaned',
			)
		);

		$this->assertEquals( 'orphaned', $result['type'] );
		$this->assertEquals( 10, $result['postmeta_deleted'] );
		$this->assertEquals( 5, $result['commentmeta_deleted'] );
		$this->assertEquals( 3, $result['termmeta_deleted'] );
		$this->assertEquals( 2, $result['relationships_deleted'] );
	}

	/**
	 * Test orphaned cleanup with specific types.
	 */
	public function test_clean_orphaned_specific_types(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_postmeta' )
			->willReturn( 10 );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_commentmeta' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_termmeta' );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_relationships' );

		$result = $this->use_case->execute(
			array(
				'type'    => 'orphaned',
				'options' => array( 'types' => array( 'postmeta' ) ),
			)
		);

		$this->assertEquals( 10, $result['postmeta_deleted'] );
		$this->assertArrayNotHasKey( 'commentmeta_deleted', $result );
	}

	/**
	 * Test safe mode can be overridden via options.
	 */
	public function test_safe_mode_override_via_options(): void {
		// Settings say safe mode is enabled.
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		// But options override it to false.
		$this->revisions_manager
			->expects( $this->once() )
			->method( 'delete_all_revisions' )
			->willReturn( array( 'deleted' => 5, 'bytes_freed' => 1000 ) );

		$result = $this->use_case->execute(
			array(
				'type'      => 'revisions',
				'safe_mode' => false,
			)
		);

		$this->assertArrayNotHasKey( 'safe_mode', $result );
		$this->assertEquals( 5, $result['deleted'] );
	}

	/**
	 * Test execute_by_type convenience method.
	 */
	public function test_execute_by_type(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->method( 'delete_all_revisions' )
			->willReturn( array( 'deleted' => 5, 'bytes_freed' => 1000 ) );

		$result = $this->use_case->execute_by_type(
			'revisions',
			array( 'keep_per_post' => 3 )
		);

		$this->assertEquals( 'revisions', $result['type'] );
		$this->assertEquals( 5, $result['deleted'] );
	}

	/**
	 * Test is_safe_mode_enabled method.
	 */
	public function test_is_safe_mode_enabled(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );

		$this->assertTrue( $this->use_case->is_safe_mode_enabled() );
	}

	/**
	 * Test activity logging is not called in safe mode.
	 */
	public function test_no_activity_logging_in_safe_mode(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( true );
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->method( 'get_all_revisions_count' )
			->willReturn( 10 );

		$this->revisions_manager
			->method( 'get_revisions_size_estimate' )
			->willReturn( 5000 );

		$this->activity_logger
			->expects( $this->never() )
			->method( 'log_database_cleanup' );

		$this->use_case->execute( array( 'type' => 'revisions' ) );
	}

	/**
	 * Test direct clean methods work without going through execute.
	 */
	public function test_clean_revisions_direct(): void {
		$this->settings->method( 'get_setting' )->willReturn( 0 );

		$this->revisions_manager
			->method( 'delete_all_revisions' )
			->willReturn( array( 'deleted' => 8, 'bytes_freed' => 4000 ) );

		$result = $this->use_case->clean_revisions( array( 'keep_per_post' => 2 ), false );

		$this->assertEquals( 8, $result['deleted'] );
		$this->assertEquals( 4000, $result['bytes_freed'] );
		$this->assertEquals( 2, $result['keep_per_post'] );
	}

	/**
	 * Test constants are properly defined.
	 */
	public function test_constants_defined(): void {
		$this->assertEquals(
			array( 'revisions', 'transients', 'spam', 'trash', 'orphaned' ),
			RunCleanup::VALID_TYPES
		);

		$this->assertEquals(
			array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' ),
			RunCleanup::VALID_ORPHANED_TYPES
		);
	}

	/**
	 * Test orphaned cleanup filters invalid types.
	 */
	public function test_clean_orphaned_filters_invalid_types(): void {
		$this->settings->method( 'is_safe_mode_enabled' )->willReturn( false );

		$this->orphaned_cleaner
			->expects( $this->once() )
			->method( 'delete_orphaned_postmeta' )
			->willReturn( 5 );

		$this->orphaned_cleaner
			->expects( $this->never() )
			->method( 'delete_orphaned_commentmeta' );

		// Pass invalid types along with valid one.
		$result = $this->use_case->execute(
			array(
				'type'    => 'orphaned',
				'options' => array( 'types' => array( 'postmeta', 'invalid_type', 'another_invalid' ) ),
			)
		);

		$this->assertEquals( 5, $result['postmeta_deleted'] );
		$this->assertArrayNotHasKey( 'invalid_type_deleted', $result );
	}
}
