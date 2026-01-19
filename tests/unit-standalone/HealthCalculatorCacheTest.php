<?php
/**
 * HealthCalculator Cache Behavior Tests (Standalone)
 *
 * Tests for HealthCalculator caching using CacheInterface with settings-driven TTL.
 *
 * @package WPAdminHealth\Tests\UnitStandalone
 */

namespace WPAdminHealth\Tests\UnitStandalone;

use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\HealthCalculator;
use WPAdminHealth\Support\CacheKeys;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\Mocks\MockSettings;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Testable HealthCalculator that avoids WordPress plugin dependencies.
 *
 * Overrides get_factor_scores() completely to avoid calling the private
 * calculate_plugin_performance_score() method which requires WordPress.
 */
class TestableHealthCalculator extends HealthCalculator {

	/**
	 * Override get_factor_scores to avoid WordPress plugin dependency.
	 *
	 * Returns factor scores with a fixed plugin_performance value,
	 * computing other scores via the parent's protected/public methods.
	 *
	 * @return array<string, int>
	 */
	public function get_factor_scores(): array {
		// Compute all scores except plugin_performance by calling parent methods.
		// Note: We can't call parent::get_factor_scores() because it calls
		// calculate_plugin_performance_score() which is private and requires WordPress.
		return array(
			'database_bloat'     => $this->calculate_database_bloat_score(),
			'unused_media'       => $this->calculate_unused_media_score(),
			'plugin_performance' => 85, // Fixed value to avoid WordPress dependency.
			'revision_count'     => $this->calculate_revision_count_score(),
			'transient_bloat'    => $this->calculate_transient_bloat_score(),
		);
	}

	/**
	 * Calculate database bloat score by calling parent's protected method.
	 *
	 * @return int
	 */
	private function calculate_database_bloat_score(): int {
		// Use reflection to call the private parent method.
		$reflection = new \ReflectionMethod( HealthCalculator::class, 'calculate_database_bloat_score' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $this );
	}

	/**
	 * Calculate unused media score by calling parent's protected method.
	 *
	 * @return int
	 */
	private function calculate_unused_media_score(): int {
		$reflection = new \ReflectionMethod( HealthCalculator::class, 'calculate_unused_media_score' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $this );
	}

	/**
	 * Calculate revision count score by calling parent's protected method.
	 *
	 * @return int
	 */
	private function calculate_revision_count_score(): int {
		$reflection = new \ReflectionMethod( HealthCalculator::class, 'calculate_revision_count_score' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $this );
	}

	/**
	 * Calculate transient bloat score by calling parent's protected method.
	 *
	 * @return int
	 */
	private function calculate_transient_bloat_score(): int {
		$reflection = new \ReflectionMethod( HealthCalculator::class, 'calculate_transient_bloat_score' );
		$reflection->setAccessible( true );
		return $reflection->invoke( $this );
	}
}

/**
 * HealthCalculator cache behavior test class.
 *
 * Tests cache hit/miss behavior, TTL selection, and force refresh bypass.
 */
class HealthCalculatorCacheTest extends StandaloneTestCase {

	/**
	 * Mock connection.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * Memory cache.
	 *
	 * @var MemoryCache
	 */
	private MemoryCache $cache;

	/**
	 * Mock settings.
	 *
	 * @var MockSettings
	 */
	private MockSettings $settings;

	/**
	 * HealthCalculator under test.
	 *
	 * @var TestableHealthCalculator
	 */
	private TestableHealthCalculator $calculator;

	/**
	 * Setup test environment before each test.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->cache      = new MemoryCache();
		$this->settings   = new MockSettings();
		$this->calculator = new TestableHealthCalculator( $this->connection, $this->cache, $this->settings );

		// Set up default query results for health score calculations.
		$this->setup_default_query_results();
	}

	/**
	 * Set up default query results for database queries.
	 */
	private function setup_default_query_results(): void {
		// Trashed posts count.
		$this->connection->set_expected_result( "%%post_status = 'trash'%%", 10 );

		// Spam comments count.
		$this->connection->set_expected_result( "%%comment_approved = 'spam'%%", 5 );

		// Orphaned postmeta count.
		$this->connection->set_expected_result( '%%LEFT JOIN%%p.ID IS NULL%%', 0 );

		// Auto-drafts count.
		$this->connection->set_expected_result( "%%post_status = 'auto-draft'%%", 5 );

		// Attachments count (total media).
		$this->connection->set_expected_result( "%%post_type = 'attachment'%%", 100 );

		// Unattached media count.
		$this->connection->set_expected_result( "%%post_type = 'attachment' AND post_parent = 0%%", 20 );

		// Revisions count.
		$this->connection->set_expected_result( "%%post_type = 'revision'%%", 50 );

		// Posts with content count.
		$this->connection->set_expected_result( "%%post_type IN ('post', 'page')%%", 25 );

		// Transients count.
		$this->connection->set_expected_result( "%%_transient_%%", 100 );

		// Expired transients count.
		$this->connection->set_expected_result( "%%_transient_timeout_%%", 10 );
	}

	/**
	 * Cleanup test environment after each test.
	 */
	protected function cleanup_test_environment(): void {
		$this->cache->flush();
		$this->connection->reset();
		$this->settings->reset();
	}

	// =========================================================================
	// Cache Key Tests
	// =========================================================================

	/**
	 * Test calculate_overall_score uses correct cache key.
	 */
	public function test_calculate_overall_score_uses_correct_cache_key(): void {
		$this->calculator->calculate_overall_score();

		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Health score should be cached with correct key'
		);
	}

	/**
	 * Test clear_cache removes cached value.
	 */
	public function test_clear_cache_removes_cached_value(): void {
		// Populate cache.
		$this->calculator->calculate_overall_score();
		$this->assertTrue( $this->cache->has( CacheKeys::HEALTH_SCORE ) );

		// Clear cache.
		$result = $this->calculator->clear_cache();

		$this->assertTrue( $result );
		$this->assertFalse( $this->cache->has( CacheKeys::HEALTH_SCORE ) );
	}

	// =========================================================================
	// Cache Hit/Miss Behavior Tests
	// =========================================================================

	/**
	 * Test calculate_overall_score returns cached value on cache hit.
	 */
	public function test_calculate_overall_score_cache_hit(): void {
		// Pre-populate cache with a known value.
		$cached_value = array(
			'score'         => 95,
			'grade'         => 'A',
			'factor_scores' => array(
				'database_bloat'     => 100,
				'unused_media'       => 90,
				'plugin_performance' => 95,
				'revision_count'     => 90,
				'transient_bloat'    => 100,
			),
			'timestamp'     => time(),
		);
		$this->cache->set( CacheKeys::HEALTH_SCORE, $cached_value, 3600 );

		// Reset query count.
		$this->connection->reset_queries();

		// Call should return cached value without database queries.
		$result = $this->calculator->calculate_overall_score();

		$this->assertEquals( 95, $result['score'] );
		$this->assertEquals( 'A', $result['grade'] );
		$this->assertEmpty(
			$this->connection->get_queries(),
			'No database queries should be made on cache hit'
		);
	}

	/**
	 * Test calculate_overall_score queries database on cache miss.
	 */
	public function test_calculate_overall_score_cache_miss(): void {
		// Ensure cache is empty.
		$this->assertFalse( $this->cache->has( CacheKeys::HEALTH_SCORE ) );

		// Call should query database.
		$result = $this->calculator->calculate_overall_score();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'score', $result );
		$this->assertArrayHasKey( 'grade', $result );
		$this->assertNotEmpty(
			$this->connection->get_queries(),
			'Database queries should be made on cache miss'
		);
	}

	/**
	 * Test calculate_overall_score caches result after computation.
	 */
	public function test_calculate_overall_score_caches_result(): void {
		// First call populates cache.
		$result = $this->calculator->calculate_overall_score();

		// Verify value is now cached.
		$this->assertTrue( $this->cache->has( CacheKeys::HEALTH_SCORE ) );

		$cached = $this->cache->get( CacheKeys::HEALTH_SCORE );
		$this->assertEquals( $result['score'], $cached['score'] );
		$this->assertEquals( $result['grade'], $cached['grade'] );
	}

	// =========================================================================
	// Force Refresh Bypass Tests
	// =========================================================================

	/**
	 * Test force_refresh=true bypasses cache.
	 */
	public function test_force_refresh_bypasses_cache(): void {
		// Pre-populate cache with an old value.
		$old_cached_value = array(
			'score'         => 50,
			'grade'         => 'D',
			'factor_scores' => array(
				'database_bloat'     => 50,
				'unused_media'       => 50,
				'plugin_performance' => 50,
				'revision_count'     => 50,
				'transient_bloat'    => 50,
			),
			'timestamp'     => time() - 3600,
		);
		$this->cache->set( CacheKeys::HEALTH_SCORE, $old_cached_value, 7200 );

		// Reset query count.
		$this->connection->reset_queries();

		// Call with force_refresh should NOT return cached value.
		$result = $this->calculator->calculate_overall_score( true );

		// Should have made database queries.
		$this->assertNotEmpty(
			$this->connection->get_queries(),
			'Database queries should be made when force_refresh is true'
		);

		// Result should be different from the cached value.
		$this->assertNotEquals( 50, $result['score'] );
	}

	/**
	 * Test force_refresh updates cached value.
	 */
	public function test_force_refresh_updates_cache(): void {
		// Pre-populate cache with an old value.
		$old_cached_value = array(
			'score'         => 50,
			'grade'         => 'D',
			'factor_scores' => array(),
			'timestamp'     => time() - 3600,
		);
		$this->cache->set( CacheKeys::HEALTH_SCORE, $old_cached_value, 7200 );

		// Force refresh.
		$result = $this->calculator->calculate_overall_score( true );

		// Cache should be updated with new value.
		$cached = $this->cache->get( CacheKeys::HEALTH_SCORE );
		$this->assertEquals( $result['score'], $cached['score'] );
		$this->assertNotEquals( 50, $cached['score'] );
	}

	/**
	 * Test force_refresh=false uses cached value.
	 */
	public function test_no_force_refresh_uses_cache(): void {
		// Pre-populate cache.
		$cached_value = array(
			'score'         => 75,
			'grade'         => 'C',
			'factor_scores' => array(),
			'timestamp'     => time(),
		);
		$this->cache->set( CacheKeys::HEALTH_SCORE, $cached_value, 3600 );

		// Reset query count.
		$this->connection->reset_queries();

		// Call without force_refresh should use cached value.
		$result = $this->calculator->calculate_overall_score( false );

		$this->assertEquals( 75, $result['score'] );
		$this->assertEmpty(
			$this->connection->get_queries(),
			'No queries should be made when using cached value'
		);
	}

	// =========================================================================
	// TTL Selection Tests
	// =========================================================================

	/**
	 * Test default TTL when no settings provided.
	 */
	public function test_default_ttl_without_settings(): void {
		// Create calculator without settings.
		$calculator = new TestableHealthCalculator( $this->connection, $this->cache, null );

		// Set up time provider.
		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		// Calculate score to populate cache.
		$calculator->calculate_overall_score();

		// Advance time just before default expiration (1 hour).
		$current_time += HOUR_IN_SECONDS - 1;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should still be cached.
		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should still be cached just before expiration'
		);

		// Advance time past expiration.
		$current_time += 2;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should be expired.
		$this->assertFalse(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should be expired after TTL'
		);
	}

	/**
	 * Test TTL respects health_score_cache_duration setting.
	 */
	public function test_ttl_respects_settings(): void {
		// Set cache duration to 2 hours.
		$this->settings->set_setting( 'health_score_cache_duration', 2 );

		// Set up time provider.
		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		// Calculate score to populate cache.
		$this->calculator->calculate_overall_score();

		// Advance time to 1.5 hours (should still be valid with 2 hour TTL).
		$current_time += (int) ( 1.5 * HOUR_IN_SECONDS );
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should still be cached.
		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should still be cached before 2-hour expiration'
		);

		// Advance time past 2-hour expiration.
		$current_time += HOUR_IN_SECONDS;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should be expired.
		$this->assertFalse(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should be expired after 2-hour TTL'
		);
	}

	/**
	 * Test TTL minimum is enforced (1 hour).
	 */
	public function test_ttl_minimum_enforced(): void {
		// Set cache duration to 0 (should be clamped to 1).
		$this->settings->set_setting( 'health_score_cache_duration', 0 );

		// Set up time provider.
		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		// Calculate score to populate cache.
		$this->calculator->calculate_overall_score();

		// Advance time to 30 minutes (should still be valid with minimum 1 hour TTL).
		$current_time += (int) ( 0.5 * HOUR_IN_SECONDS );
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should still be cached.
		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should still be cached with minimum 1-hour TTL'
		);

		// Advance time past 1-hour mark.
		$current_time += HOUR_IN_SECONDS;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should be expired.
		$this->assertFalse(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should be expired after minimum TTL'
		);
	}

	/**
	 * Test TTL maximum is enforced (24 hours).
	 */
	public function test_ttl_maximum_enforced(): void {
		// Set cache duration to 48 hours (should be clamped to 24).
		$this->settings->set_setting( 'health_score_cache_duration', 48 );

		// Set up time provider.
		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		// Calculate score to populate cache.
		$this->calculator->calculate_overall_score();

		// Advance time to 23 hours (should still be valid with max 24 hour TTL).
		$current_time += 23 * HOUR_IN_SECONDS;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should still be cached.
		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should still be cached before 24-hour maximum'
		);

		// Advance time past 24-hour mark.
		$current_time += 2 * HOUR_IN_SECONDS;
		$this->cache->set_time_provider( fn() => $current_time );

		// Value should be expired.
		$this->assertFalse(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			'Value should be expired after 24-hour maximum TTL'
		);
	}

	/**
	 * Test different cache durations (data provider pattern).
	 *
	 * @dataProvider cacheDurationProvider
	 *
	 * @param int $setting_value  The setting value in hours.
	 * @param int $expected_hours The expected TTL in hours (after clamping).
	 */
	public function test_various_cache_durations( int $setting_value, int $expected_hours ): void {
		$this->settings->set_setting( 'health_score_cache_duration', $setting_value );

		$current_time = 1000000;
		$this->cache->set_time_provider( fn() => $current_time );

		$this->calculator->calculate_overall_score();

		// Advance time just before expected expiration.
		$current_time += ( $expected_hours * HOUR_IN_SECONDS ) - 1;
		$this->cache->set_time_provider( fn() => $current_time );

		$this->assertTrue(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			"Value should still be cached just before {$expected_hours}-hour expiration"
		);

		// Advance time past expiration.
		$current_time += 2;
		$this->cache->set_time_provider( fn() => $current_time );

		$this->assertFalse(
			$this->cache->has( CacheKeys::HEALTH_SCORE ),
			"Value should be expired after {$expected_hours}-hour TTL"
		);
	}

	/**
	 * Data provider for cache duration tests.
	 *
	 * @return array<string, array{int, int}>
	 */
	public static function cacheDurationProvider(): array {
		return array(
			'1 hour setting'     => array( 1, 1 ),
			'6 hours setting'    => array( 6, 6 ),
			'12 hours setting'   => array( 12, 12 ),
			'24 hours setting'   => array( 24, 24 ),
			'negative becomes absolute' => array( -5, 5 ),  // absint(-5) = 5.
			'above max clamped'  => array( 100, 24 ), // Clamped to maximum 24.
		);
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test cache returns null instead of false for missing key.
	 */
	public function test_cache_handles_null_correctly(): void {
		// Ensure cache is empty.
		$this->cache->flush();

		// Get should return null for missing key.
		$this->assertNull( $this->cache->get( CacheKeys::HEALTH_SCORE ) );

		// calculate_overall_score should work correctly with null cache value.
		$result = $this->calculator->calculate_overall_score();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'score', $result );
	}

	/**
	 * Test cached value structure is preserved.
	 */
	public function test_cached_value_structure(): void {
		$this->calculator->calculate_overall_score();

		$cached = $this->cache->get( CacheKeys::HEALTH_SCORE );

		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'score', $cached );
		$this->assertArrayHasKey( 'grade', $cached );
		$this->assertArrayHasKey( 'factor_scores', $cached );
		$this->assertArrayHasKey( 'timestamp', $cached );

		$this->assertIsNumeric( $cached['score'] );
		$this->assertIsString( $cached['grade'] );
		$this->assertIsArray( $cached['factor_scores'] );
		$this->assertIsInt( $cached['timestamp'] );
	}
}
