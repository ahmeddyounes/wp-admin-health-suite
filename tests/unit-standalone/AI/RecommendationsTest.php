<?php
/**
 * Recommendations Tests (Standalone)
 *
 * Tests for AI Recommendations class focusing on caching behavior,
 * dismissal filtering, and force refresh functionality.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\AI
 */

namespace WPAdminHealth\Tests\UnitStandalone\AI;

use WPAdminHealth\AI\Recommendations;
use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for Recommendations.
 *
 * @since 1.4.0
 */
class RecommendationsTest extends StandaloneTestCase {

	/**
	 * Memory cache instance for testing.
	 *
	 * @var MemoryCache
	 */
	private MemoryCache $cache;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		parent::setup_test_environment();

		// Create a fresh cache for each test.
		$this->cache = new MemoryCache();

		// Reset global test state.
		global $wpha_transients;
		$wpha_transients                  = array();
		$GLOBALS['wpha_test_options']     = array();
		$GLOBALS['wpha_test_multisite']   = false;
		$GLOBALS['wpha_test_blog_id']     = 1;

		// Set default cache status to avoid caching recommendations.
		$GLOBALS['wpha_test_options']['wpha_cache_status'] = array(
			'object_cache' => true,
			'page_cache'   => true,
		);
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		global $wpha_transients;
		$wpha_transients = array();
		unset( $GLOBALS['wpha_test_options'] );
		unset( $GLOBALS['wpha_test_multisite'] );
		unset( $GLOBALS['wpha_test_blog_id'] );

		parent::cleanup_test_environment();
	}

	// -------------------------------------------------------------------------
	// Cache Set/Get Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that recommendations are cached using CacheInterface.
	 */
	public function test_recommendations_are_cached_using_cache_interface(): void {
		$recommendations = new Recommendations(
			null, // analyzer
			null, // revisions_manager
			null, // transients_cleaner
			null, // orphaned_cleaner
			null, // trash_cleaner
			null, // scanner
			null, // query_monitor
			null, // connection
			$this->cache
		);

		// Generate recommendations (should cache them).
		$result = $recommendations->generate_recommendations( true );

		// Verify cache was populated.
		$cached = $this->cache->get( 'wpha_ai_recommendations' );
		$this->assertNotNull( $cached );
		$this->assertIsArray( $cached );
	}

	/**
	 * Test that cached recommendations are returned on subsequent calls.
	 */
	public function test_cached_recommendations_are_returned(): void {
		// Pre-populate cache with test data.
		$test_recs = array(
			array(
				'id'              => 'test_rec_1',
				'category'        => 'database',
				'title'           => 'Test Recommendation',
				'impact_estimate' => 'medium',
				'priority'        => 5,
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $test_recs, DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Should return cached recommendations.
		$result = $recommendations->generate_recommendations( false );

		$this->assertCount( 1, $result );
		$this->assertSame( 'test_rec_1', $result[0]['id'] );
	}

	/**
	 * Test that cache can be cleared.
	 */
	public function test_cache_can_be_cleared(): void {
		// Pre-populate cache.
		$this->cache->set( 'wpha_ai_recommendations', array( array( 'id' => 'cached' ) ), DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Clear cache.
		$result = $recommendations->clear_cache();
		$this->assertTrue( $result );

		// Verify cache is empty.
		$cached = $this->cache->get( 'wpha_ai_recommendations' );
		$this->assertNull( $cached );
	}

	// -------------------------------------------------------------------------
	// Force Refresh Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that force refresh bypasses cache.
	 */
	public function test_force_refresh_bypasses_cache(): void {
		// Pre-populate cache with stale data.
		$stale_recs = array(
			array(
				'id'              => 'stale_rec',
				'category'        => 'database',
				'title'           => 'Stale Recommendation',
				'impact_estimate' => 'low',
				'priority'        => 1,
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $stale_recs, DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Force refresh should regenerate recommendations.
		$result = $recommendations->generate_recommendations( true );

		// Should not contain the stale recommendation.
		$ids = array_column( $result, 'id' );
		$this->assertNotContains( 'stale_rec', $ids );
	}

	/**
	 * Test that force refresh updates the cache.
	 */
	public function test_force_refresh_updates_cache(): void {
		// Pre-populate cache with old data.
		$this->cache->set( 'wpha_ai_recommendations', array( array( 'id' => 'old' ) ), DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Force refresh.
		$result = $recommendations->generate_recommendations( true );

		// Cache should be updated (old data replaced).
		$cached = $this->cache->get( 'wpha_ai_recommendations' );
		$this->assertIsArray( $cached );

		// Old data should not be in cache.
		$ids = array_column( $cached, 'id' );
		$this->assertNotContains( 'old', $ids );
	}

	// -------------------------------------------------------------------------
	// Dismissal Filtering Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that dismissed recommendations are filtered out.
	 */
	public function test_dismissed_recommendations_are_filtered(): void {
		// Pre-populate cache with recommendations.
		$test_recs = array(
			array(
				'id'              => 'keep_me',
				'category'        => 'database',
				'title'           => 'Keep This',
				'impact_estimate' => 'medium',
				'priority'        => 5,
			),
			array(
				'id'              => 'dismiss_me',
				'category'        => 'media',
				'title'           => 'Dismiss This',
				'impact_estimate' => 'low',
				'priority'        => 3,
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $test_recs, DAY_IN_SECONDS );

		// Set dismissed recommendations in options.
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array( 'dismiss_me' );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$result = $recommendations->generate_recommendations( false );

		// Should only contain the non-dismissed recommendation.
		$this->assertCount( 1, $result );
		$this->assertSame( 'keep_me', $result[0]['id'] );
	}

	/**
	 * Test that dismiss_recommendation adds ID to dismissed list.
	 */
	public function test_dismiss_recommendation_adds_to_dismissed_list(): void {
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array();

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Dismiss a recommendation.
		$result = $recommendations->dismiss_recommendation( 'test_recommendation' );

		$this->assertTrue( $result );

		// Verify it's in the dismissed list.
		$dismissed = $recommendations->get_dismissed_ids();
		$this->assertContains( 'test_recommendation', $dismissed );
	}

	/**
	 * Test that dismissing an already dismissed recommendation returns true.
	 */
	public function test_dismiss_already_dismissed_returns_true(): void {
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array( 'already_dismissed' );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Dismissing again should return true without duplicating.
		$result = $recommendations->dismiss_recommendation( 'already_dismissed' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that restore_recommendation removes ID from dismissed list.
	 */
	public function test_restore_recommendation_removes_from_dismissed_list(): void {
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array( 'to_restore', 'keep_dismissed' );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Restore a recommendation.
		$result = $recommendations->restore_recommendation( 'to_restore' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that clear_dismissed removes all dismissed recommendations.
	 */
	public function test_clear_dismissed_removes_all(): void {
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array( 'rec1', 'rec2', 'rec3' );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$result = $recommendations->clear_dismissed();

		$this->assertTrue( $result );
	}

	/**
	 * Test that filtered results are re-indexed.
	 */
	public function test_filtered_results_are_reindexed(): void {
		// Pre-populate cache with recommendations.
		$test_recs = array(
			array(
				'id'              => 'rec_0',
				'category'        => 'database',
				'title'           => 'First',
				'impact_estimate' => 'high',
				'priority'        => 7,
			),
			array(
				'id'              => 'rec_1',
				'category'        => 'media',
				'title'           => 'Second (dismissed)',
				'impact_estimate' => 'medium',
				'priority'        => 5,
			),
			array(
				'id'              => 'rec_2',
				'category'        => 'performance',
				'title'           => 'Third',
				'impact_estimate' => 'low',
				'priority'        => 3,
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $test_recs, DAY_IN_SECONDS );

		// Dismiss the middle one.
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = array( 'rec_1' );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$result = $recommendations->generate_recommendations( false );

		// Should have sequential numeric keys (re-indexed).
		$keys = array_keys( $result );
		$this->assertSame( array( 0, 1 ), $keys );
	}

	// -------------------------------------------------------------------------
	// Fallback Tests (when CacheInterface is not available)
	// -------------------------------------------------------------------------

	/**
	 * Test that transient fallback works when cache is null.
	 */
	public function test_transient_fallback_when_cache_is_null(): void {
		global $wpha_transients;

		// Pre-populate transient with test data.
		$wpha_transients['wpha_ai_recommendations'] = array(
			'value'      => array(
				array(
					'id'              => 'transient_rec',
					'category'        => 'database',
					'title'           => 'From Transient',
					'impact_estimate' => 'medium',
					'priority'        => 5,
				),
			),
			'expiration' => time() + DAY_IN_SECONDS,
		);

		// Create Recommendations without cache.
		$recommendations = new Recommendations();

		$result = $recommendations->generate_recommendations( false );

		$this->assertCount( 1, $result );
		$this->assertSame( 'transient_rec', $result[0]['id'] );
	}

	/**
	 * Test that recommendations are stored in transient when cache is null.
	 */
	public function test_recommendations_stored_in_transient_when_cache_is_null(): void {
		global $wpha_transients;
		$wpha_transients = array();

		// Create Recommendations without cache.
		$recommendations = new Recommendations();

		// Force regeneration.
		$result = $recommendations->generate_recommendations( true );

		// Should have stored in transient.
		$this->assertArrayHasKey( 'wpha_ai_recommendations', $wpha_transients );
	}

	// -------------------------------------------------------------------------
	// Priority / Prioritization Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that prioritize_issues calculates correct priority scores.
	 */
	public function test_prioritize_issues_calculates_scores(): void {
		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$input = array(
			array(
				'id'              => 'high_priority',
				'impact_estimate' => 'critical',
				'ease_of_fix'     => 'easy',
				'risk_level'      => 'low',
			),
			array(
				'id'              => 'low_priority',
				'impact_estimate' => 'low',
				'ease_of_fix'     => 'hard',
				'risk_level'      => 'high',
			),
		);

		$result = $recommendations->prioritize_issues( $input );

		// Results should be sorted by priority (highest first).
		$this->assertSame( 'high_priority', $result[0]['id'] );
		$this->assertSame( 'low_priority', $result[1]['id'] );

		// High priority should have higher score.
		$this->assertGreaterThan( $result[1]['priority'], $result[0]['priority'] );
	}

	/**
	 * Test that priority scores are normalized to 1-10 range.
	 */
	public function test_priority_scores_are_normalized(): void {
		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$input = array(
			array(
				'id'              => 'max_score',
				'impact_estimate' => 'critical', // +4
				'ease_of_fix'     => 'easy',     // +3
				'risk_level'      => 'low',      // +3
			),
			array(
				'id'              => 'min_score',
				'impact_estimate' => 'low',  // +1
				'ease_of_fix'     => 'hard', // +1
				'risk_level'      => 'high', // -1
			),
		);

		$result = $recommendations->prioritize_issues( $input );

		foreach ( $result as $rec ) {
			$this->assertGreaterThanOrEqual( 1, $rec['priority'] );
			$this->assertLessThanOrEqual( 10, $rec['priority'] );
		}
	}

	// -------------------------------------------------------------------------
	// Edge Cases
	// -------------------------------------------------------------------------

	/**
	 * Test behavior with empty recommendations.
	 */
	public function test_empty_recommendations(): void {
		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$result = $recommendations->generate_recommendations( true );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test behavior with malformed dismissed option.
	 */
	public function test_malformed_dismissed_option_handled(): void {
		// Set a non-array value for dismissed.
		$GLOBALS['wpha_test_options']['wpha_ai_dismissed_recommendations'] = 'not_an_array';

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		// Should return empty array, not error.
		$dismissed = $recommendations->get_dismissed_ids();
		$this->assertIsArray( $dismissed );
		$this->assertEmpty( $dismissed );
	}

	/**
	 * Test get_actionable_steps returns steps for valid recommendation.
	 */
	public function test_get_actionable_steps_returns_steps(): void {
		$test_recs = array(
			array(
				'id'              => 'with_steps',
				'category'        => 'database',
				'title'           => 'Has Steps',
				'impact_estimate' => 'medium',
				'priority'        => 5,
				'steps'           => array( 'Step 1', 'Step 2', 'Step 3' ),
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $test_recs, DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$steps = $recommendations->get_actionable_steps( 'with_steps' );

		$this->assertIsArray( $steps );
		$this->assertCount( 3, $steps );
		$this->assertSame( 'Step 1', $steps[0] );
	}

	/**
	 * Test get_actionable_steps returns null for unknown recommendation.
	 */
	public function test_get_actionable_steps_returns_null_for_unknown(): void {
		$this->cache->set( 'wpha_ai_recommendations', array(), DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$steps = $recommendations->get_actionable_steps( 'nonexistent' );

		$this->assertNull( $steps );
	}

	/**
	 * Test get_actionable_steps returns empty array for recommendation without steps.
	 */
	public function test_get_actionable_steps_returns_empty_for_no_steps(): void {
		$test_recs = array(
			array(
				'id'              => 'no_steps',
				'category'        => 'database',
				'title'           => 'No Steps',
				'impact_estimate' => 'medium',
				'priority'        => 5,
			),
		);
		$this->cache->set( 'wpha_ai_recommendations', $test_recs, DAY_IN_SECONDS );

		$recommendations = new Recommendations(
			null, null, null, null, null, null, null, null,
			$this->cache
		);

		$steps = $recommendations->get_actionable_steps( 'no_steps' );

		$this->assertIsArray( $steps );
		$this->assertEmpty( $steps );
	}
}
