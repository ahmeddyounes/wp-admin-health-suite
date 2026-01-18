<?php
/**
 * Application Layer Tests (Standalone)
 *
 * Tests to verify that Application layer classes can be autoloaded
 * and instantiated correctly.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Application
 */

namespace WPAdminHealth\Tests\UnitStandalone\Application;

use WPAdminHealth\Application\Database\RunCleanup;
use WPAdminHealth\Application\Database\RunOptimization;
use WPAdminHealth\Application\Media\RunScan;
use WPAdminHealth\Application\Media\ProcessDuplicates;
use WPAdminHealth\Application\Performance\RunHealthCheck;
use WPAdminHealth\Application\Performance\CollectMetrics;
use WPAdminHealth\Application\AI\GenerateRecommendations;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Application layer autoloading and basic functionality test.
 *
 * Note: RunCleanup has been refactored to require constructor dependencies
 * and is tested separately in Database/RunCleanupTest.php.
 */
class ApplicationLayerTest extends StandaloneTestCase {

	/**
	 * Test that Database\RunCleanup class exists and can be loaded.
	 */
	public function test_run_cleanup_class_exists(): void {
		$this->assertTrue( class_exists( RunCleanup::class ) );
	}

	/**
	 * Test that Database\RunOptimization can be instantiated.
	 */
	public function test_run_optimization_can_be_instantiated(): void {
		$use_case = new RunOptimization();

		$this->assertInstanceOf( RunOptimization::class, $use_case );
	}

	/**
	 * Test that Media\RunScan can be instantiated.
	 */
	public function test_run_scan_can_be_instantiated(): void {
		$use_case = new RunScan();

		$this->assertInstanceOf( RunScan::class, $use_case );
	}

	/**
	 * Test that Media\ProcessDuplicates can be instantiated.
	 */
	public function test_process_duplicates_can_be_instantiated(): void {
		$use_case = new ProcessDuplicates();

		$this->assertInstanceOf( ProcessDuplicates::class, $use_case );
	}

	/**
	 * Test that Performance\RunHealthCheck can be instantiated.
	 */
	public function test_run_health_check_can_be_instantiated(): void {
		$use_case = new RunHealthCheck();

		$this->assertInstanceOf( RunHealthCheck::class, $use_case );
	}

	/**
	 * Test that Performance\CollectMetrics can be instantiated.
	 */
	public function test_collect_metrics_can_be_instantiated(): void {
		$use_case = new CollectMetrics();

		$this->assertInstanceOf( CollectMetrics::class, $use_case );
	}

	/**
	 * Test that AI\GenerateRecommendations can be instantiated.
	 */
	public function test_generate_recommendations_can_be_instantiated(): void {
		$use_case = new GenerateRecommendations();

		$this->assertInstanceOf( GenerateRecommendations::class, $use_case );
	}

	/**
	 * Test that RunCleanup has expected method signatures.
	 *
	 * Note: Full execute tests are in Database/RunCleanupTest.php
	 */
	public function test_run_cleanup_has_execute_method(): void {
		$this->assertTrue( method_exists( RunCleanup::class, 'execute' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'execute_by_type' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'clean_revisions' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'clean_transients' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'clean_spam' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'clean_trash' ) );
		$this->assertTrue( method_exists( RunCleanup::class, 'clean_orphaned' ) );
	}

	/**
	 * Test that RunOptimization execute returns expected structure.
	 */
	public function test_run_optimization_execute_returns_array(): void {
		$use_case = new RunOptimization();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that RunScan execute returns expected structure.
	 */
	public function test_run_scan_execute_returns_array(): void {
		$use_case = new RunScan();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that ProcessDuplicates execute returns expected structure.
	 */
	public function test_process_duplicates_execute_returns_array(): void {
		$use_case = new ProcessDuplicates();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that RunHealthCheck execute returns expected structure.
	 */
	public function test_run_health_check_execute_returns_array(): void {
		$use_case = new RunHealthCheck();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that CollectMetrics execute returns expected structure.
	 */
	public function test_collect_metrics_execute_returns_array(): void {
		$use_case = new CollectMetrics();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that GenerateRecommendations execute returns expected structure.
	 */
	public function test_generate_recommendations_execute_returns_array(): void {
		$use_case = new GenerateRecommendations();
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	/**
	 * Test that RunCleanup has expected constants.
	 */
	public function test_run_cleanup_has_constants(): void {
		$this->assertIsArray( RunCleanup::VALID_TYPES );
		$this->assertContains( 'revisions', RunCleanup::VALID_TYPES );
		$this->assertContains( 'transients', RunCleanup::VALID_TYPES );
		$this->assertContains( 'spam', RunCleanup::VALID_TYPES );
		$this->assertContains( 'trash', RunCleanup::VALID_TYPES );
		$this->assertContains( 'orphaned', RunCleanup::VALID_TYPES );

		$this->assertIsArray( RunCleanup::VALID_ORPHANED_TYPES );
		$this->assertContains( 'postmeta', RunCleanup::VALID_ORPHANED_TYPES );
		$this->assertContains( 'commentmeta', RunCleanup::VALID_ORPHANED_TYPES );
		$this->assertContains( 'termmeta', RunCleanup::VALID_ORPHANED_TYPES );
		$this->assertContains( 'relationships', RunCleanup::VALID_ORPHANED_TYPES );
	}
}
