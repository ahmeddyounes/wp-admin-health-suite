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
	 * Test that Database\RunOptimization class exists and can be loaded.
	 */
	public function test_run_optimization_class_exists(): void {
		$this->assertTrue( class_exists( RunOptimization::class ) );
	}

	/**
	 * Test that Media\RunScan class exists and can be loaded.
	 */
	public function test_run_scan_class_exists(): void {
		$this->assertTrue( class_exists( RunScan::class ) );
	}

	/**
	 * Test that Media\ProcessDuplicates can be instantiated.
	 */
	public function test_process_duplicates_can_be_instantiated(): void {
		$use_case = new ProcessDuplicates();

		$this->assertInstanceOf( ProcessDuplicates::class, $use_case );
	}

	/**
	 * Test that Performance\RunHealthCheck class exists and can be loaded.
	 */
	public function test_run_health_check_class_exists(): void {
		$this->assertTrue( class_exists( RunHealthCheck::class ) );
	}

	/**
	 * Test that Performance\CollectMetrics class exists and can be loaded.
	 */
	public function test_collect_metrics_class_exists(): void {
		$this->assertTrue( class_exists( CollectMetrics::class ) );
	}

	/**
	 * Test that AI\GenerateRecommendations can be instantiated.
	 */
	public function test_generate_recommendations_can_be_instantiated(): void {
		$use_case = new GenerateRecommendations( new \WPAdminHealth\AI\Recommendations() );

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
	 * Test that RunOptimization has expected method signatures.
	 */
	public function test_run_optimization_has_execute_method(): void {
		$this->assertTrue( method_exists( RunOptimization::class, 'execute' ) );
	}

	/**
	 * Test that RunScan has expected method signatures.
	 */
	public function test_run_scan_has_execute_method(): void {
		$this->assertTrue( method_exists( RunScan::class, 'execute' ) );
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
	 * Test that RunHealthCheck has expected method signatures.
	 */
	public function test_run_health_check_has_execute_method(): void {
		$this->assertTrue( method_exists( RunHealthCheck::class, 'execute' ) );
	}

	/**
	 * Test that CollectMetrics has expected method signatures.
	 */
	public function test_collect_metrics_has_execute_method(): void {
		$this->assertTrue( method_exists( CollectMetrics::class, 'execute' ) );
	}

	/**
	 * Test that GenerateRecommendations execute returns expected structure.
	 */
	public function test_generate_recommendations_execute_returns_array(): void {
		$use_case = new GenerateRecommendations( new \WPAdminHealth\AI\Recommendations() );
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
