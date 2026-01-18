<?php
/**
 * Unit tests for CacheChecker class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\CacheChecker;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for CacheChecker.
 *
 * @covers \WPAdminHealth\Performance\CacheChecker
 */
class CacheCheckerTest extends StandaloneTestCase {

	/**
	 * CacheChecker instance.
	 *
	 * @var CacheChecker
	 */
	private CacheChecker $checker;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->checker = new CacheChecker();
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		// Reset global variables.
		global $_wp_using_ext_object_cache, $wp_object_cache;
		$_wp_using_ext_object_cache = false;
		$wp_object_cache            = null;
	}

	/**
	 * Test is_persistent_cache_available returns false by default.
	 *
	 * @return void
	 */
	public function test_is_persistent_cache_available_returns_false_by_default(): void {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;

		$result = $this->checker->is_persistent_cache_available();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_persistent_cache_available returns true when external cache is in use.
	 *
	 * @return void
	 */
	public function test_is_persistent_cache_available_returns_true_when_ext_cache(): void {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = true;

		$result = $this->checker->is_persistent_cache_available();

		$this->assertTrue( $result );
	}

	/**
	 * Test get_cache_status returns expected structure.
	 *
	 * @return void
	 */
	public function test_get_cache_status_returns_expected_structure(): void {
		$status = $this->checker->get_cache_status();

		$this->assertArrayHasKey( 'persistent_cache_available', $status );
		$this->assertArrayHasKey( 'cache_type', $status );
		$this->assertArrayHasKey( 'cache_backend', $status );
		$this->assertArrayHasKey( 'object_cache_dropin', $status );
		$this->assertArrayHasKey( 'extensions_available', $status );
		$this->assertArrayHasKey( 'hit_rate', $status );
		$this->assertArrayHasKey( 'cache_info', $status );
		$this->assertArrayHasKey( 'object_cache', $status );
		$this->assertArrayHasKey( 'page_cache', $status );
		$this->assertArrayHasKey( 'browser_cache', $status );
		$this->assertArrayHasKey( 'caching_plugins', $status );
	}

	/**
	 * Test get_cache_status reports no persistent cache when none available.
	 *
	 * @return void
	 */
	public function test_get_cache_status_reports_no_persistent_cache(): void {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;

		$status = $this->checker->get_cache_status();

		$this->assertFalse( $status['persistent_cache_available'] );
		$this->assertEquals( 'No persistent cache', $status['cache_type'] );
	}

	/**
	 * Test test_cache_performance returns expected structure.
	 *
	 * @return void
	 */
	public function test_test_cache_performance_returns_expected_structure(): void {
		$results = $this->checker->test_cache_performance();

		$this->assertArrayHasKey( 'test_items', $results );
		$this->assertArrayHasKey( 'set_operations', $results );
		$this->assertArrayHasKey( 'get_operations', $results );
		$this->assertArrayHasKey( 'total_time_ms', $results );
		$this->assertArrayHasKey( 'avg_set_time_ms', $results );
		$this->assertArrayHasKey( 'avg_get_time_ms', $results );
		$this->assertArrayHasKey( 'operations_per_sec', $results );
		$this->assertArrayHasKey( 'hit_rate', $results );
		$this->assertArrayHasKey( 'cache_effective', $results );
	}

	/**
	 * Test test_cache_performance uses correct benchmark items count.
	 *
	 * @return void
	 */
	public function test_test_cache_performance_uses_correct_item_count(): void {
		$results = $this->checker->test_cache_performance();

		$this->assertEquals( CacheChecker::BENCHMARK_ITEMS, $results['test_items'] );
		$this->assertCount( CacheChecker::BENCHMARK_ITEMS, $results['set_operations'] );
		$this->assertCount( CacheChecker::BENCHMARK_ITEMS, $results['get_operations'] );
	}

	/**
	 * Test test_cache_performance calculates statistics correctly.
	 *
	 * @return void
	 */
	public function test_test_cache_performance_calculates_statistics(): void {
		$results = $this->checker->test_cache_performance();

		$this->assertIsFloat( $results['total_time_ms'] );
		$this->assertIsFloat( $results['avg_set_time_ms'] );
		$this->assertIsFloat( $results['avg_get_time_ms'] );
		$this->assertIsFloat( $results['operations_per_sec'] );
		$this->assertIsFloat( $results['hit_rate'] );
		$this->assertIsBool( $results['cache_effective'] );
	}

	/**
	 * Test get_cache_recommendations returns array.
	 *
	 * @return void
	 */
	public function test_get_cache_recommendations_returns_array(): void {
		$recommendations = $this->checker->get_cache_recommendations();

		$this->assertIsArray( $recommendations );
	}

	/**
	 * Test get_cache_recommendations includes warning when no persistent cache.
	 *
	 * @return void
	 */
	public function test_get_cache_recommendations_includes_warning_when_no_cache(): void {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;

		$recommendations = $this->checker->get_cache_recommendations();

		$has_warning = false;
		foreach ( $recommendations as $rec ) {
			if ( 'warning' === $rec['type'] && strpos( $rec['title'], 'No Persistent Object Cache' ) !== false ) {
				$has_warning = true;
				break;
			}
		}

		$this->assertTrue( $has_warning );
	}

	/**
	 * Test get_cache_recommendations includes success when cache available.
	 *
	 * @return void
	 */
	public function test_get_cache_recommendations_includes_success_when_cache_available(): void {
		global $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = true;

		$recommendations = $this->checker->get_cache_recommendations();

		$has_success = false;
		foreach ( $recommendations as $rec ) {
			if ( 'success' === $rec['type'] && strpos( $rec['title'], 'Persistent Object Cache Active' ) !== false ) {
				$has_success = true;
				break;
			}
		}

		$this->assertTrue( $has_success );
	}

	/**
	 * Test recommendation structure is correct.
	 *
	 * @return void
	 */
	public function test_recommendation_structure_is_correct(): void {
		$recommendations = $this->checker->get_cache_recommendations();

		foreach ( $recommendations as $rec ) {
			$this->assertArrayHasKey( 'type', $rec );
			$this->assertArrayHasKey( 'title', $rec );
			$this->assertArrayHasKey( 'message', $rec );
			$this->assertArrayHasKey( 'priority', $rec );
			$this->assertContains( $rec['type'], array( 'success', 'warning', 'info', 'error' ) );
			$this->assertContains( $rec['priority'], array( 'low', 'medium', 'high' ) );
		}
	}

	/**
	 * Test page cache detection structure.
	 *
	 * @return void
	 */
	public function test_page_cache_detection_returns_structure(): void {
		$status = $this->checker->get_cache_status();

		$this->assertArrayHasKey( 'enabled', $status['page_cache'] );
		$this->assertArrayHasKey( 'type', $status['page_cache'] );
		$this->assertArrayHasKey( 'plugin', $status['page_cache'] );
	}

	/**
	 * Test browser cache detection structure.
	 *
	 * @return void
	 */
	public function test_browser_cache_detection_returns_structure(): void {
		$status = $this->checker->get_cache_status();

		$this->assertArrayHasKey( 'configured', $status['browser_cache'] );
		$this->assertArrayHasKey( 'htaccess_rules', $status['browser_cache'] );
		$this->assertArrayHasKey( 'web_config_rules', $status['browser_cache'] );
		$this->assertArrayHasKey( 'plugin_managed', $status['browser_cache'] );
	}

	/**
	 * Test extensions_available returns array.
	 *
	 * @return void
	 */
	public function test_extensions_available_returns_array(): void {
		$status = $this->checker->get_cache_status();

		$this->assertIsArray( $status['extensions_available'] );
	}

	/**
	 * Test caching_plugins returns array.
	 *
	 * @return void
	 */
	public function test_caching_plugins_returns_array(): void {
		$status = $this->checker->get_cache_status();

		$this->assertIsArray( $status['caching_plugins'] );
	}

	/**
	 * Test cache_info returns class name.
	 *
	 * @return void
	 */
	public function test_cache_info_returns_class_name(): void {
		$status = $this->checker->get_cache_status();

		$this->assertArrayHasKey( 'class', $status['cache_info'] );
	}

	/**
	 * Test BENCHMARK_ITEMS constant.
	 *
	 * @return void
	 */
	public function test_benchmark_items_constant(): void {
		$this->assertEquals( 100, CacheChecker::BENCHMARK_ITEMS );
	}

	/**
	 * Test TEST_KEY_PREFIX constant.
	 *
	 * @return void
	 */
	public function test_test_key_prefix_constant(): void {
		$this->assertEquals( 'wpha_cache_test_', CacheChecker::TEST_KEY_PREFIX );
	}

	/**
	 * Test hit rate is null when no cache object.
	 *
	 * @return void
	 */
	public function test_hit_rate_is_null_when_no_cache_object(): void {
		global $wp_object_cache;
		$wp_object_cache = null;

		$status = $this->checker->get_cache_status();

		$this->assertNull( $status['hit_rate'] );
	}

	/**
	 * Test object_cache_dropin checks for file existence.
	 *
	 * @return void
	 */
	public function test_object_cache_dropin_returns_boolean(): void {
		$status = $this->checker->get_cache_status();

		$this->assertIsBool( $status['object_cache_dropin'] );
	}

	/**
	 * Test cache backend detection returns valid type.
	 *
	 * @return void
	 */
	public function test_cache_backend_detection_returns_valid_type(): void {
		$status        = $this->checker->get_cache_status();
		$valid_backends = array( 'redis', 'memcached', 'apcu', 'file', 'none' );

		$this->assertContains( $status['cache_backend'], $valid_backends );
	}

	/**
	 * Test cache type detection returns readable string.
	 *
	 * @return void
	 */
	public function test_cache_type_detection_returns_readable_string(): void {
		$status = $this->checker->get_cache_status();

		$this->assertIsString( $status['cache_type'] );
		$this->assertNotEmpty( $status['cache_type'] );
	}

	/**
	 * Test performance benchmark cleans up test data.
	 *
	 * @return void
	 */
	public function test_performance_benchmark_cleans_up_test_data(): void {
		$this->checker->test_cache_performance();

		// Check that test keys are cleaned up.
		for ( $i = 0; $i < 5; $i++ ) {
			$value = wp_cache_get( CacheChecker::TEST_KEY_PREFIX . $i, 'wpha_benchmark' );
			$this->assertFalse( $value );
		}
	}

	/**
	 * Test operations_per_sec is positive.
	 *
	 * @return void
	 */
	public function test_operations_per_sec_is_positive(): void {
		$results = $this->checker->test_cache_performance();

		$this->assertGreaterThan( 0, $results['operations_per_sec'] );
	}

	/**
	 * Test hit_rate is percentage.
	 *
	 * @return void
	 */
	public function test_hit_rate_is_percentage(): void {
		$results = $this->checker->test_cache_performance();

		$this->assertGreaterThanOrEqual( 0, $results['hit_rate'] );
		$this->assertLessThanOrEqual( 100, $results['hit_rate'] );
	}
}
