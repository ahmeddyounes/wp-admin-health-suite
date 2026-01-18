<?php
/**
 * GenerateRecommendations Use-case Tests (Standalone)
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Application\AI
 */

namespace WPAdminHealth\Tests\UnitStandalone\Application\AI;

use WPAdminHealth\Application\AI\GenerateRecommendations;
use WPAdminHealth\AI\Recommendations;
use WPAdminHealth\Tests\StandaloneTestCase;

class GenerateRecommendationsUseCaseTest extends StandaloneTestCase {

	public function test_execute_force_refresh_affects_cache_behavior(): void {
		global $wpha_transients;
		$wpha_transients = array();

		// Make Recommendations deterministic and empty on refresh.
		$GLOBALS['wpha_test_options']                         = array();
		$GLOBALS['wpha_test_options']['wpha_cache_status'] = array(
			'object_cache' => true,
			'page_cache'   => true,
		);

		set_transient( 'wpha_ai_recommendations', array( array( 'id' => 'cached' ) ), DAY_IN_SECONDS );

		$use_case = new GenerateRecommendations( new Recommendations() );

		$result_cached = $use_case->execute( array( 'force_refresh' => false ) );
		$this->assertTrue( $result_cached['success'] );
		$this->assertSame( 'cached', $result_cached['data']['recommendations'][0]['id'] );

		$result_forced = $use_case->execute( array( 'force_refresh' => true ) );
		$this->assertTrue( $result_forced['success'] );
		$this->assertSame( array(), $result_forced['data']['recommendations'] );
	}

	public function test_execute_returns_stable_schema(): void {
		$use_case = new GenerateRecommendations( new Recommendations() );
		$result   = $use_case->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'message', $result );

		$this->assertIsArray( $result['data'] );
		$this->assertArrayHasKey( 'recommendations', $result['data'] );
		$this->assertIsArray( $result['data']['recommendations'] );
	}

	public function test_execute_handles_empty_recommendations_gracefully(): void {
		global $wpha_transients;
		$wpha_transients = array();

		// Ensure Recommendations generates an empty list.
		$GLOBALS['wpha_test_options']                         = array();
		$GLOBALS['wpha_test_options']['wpha_cache_status'] = array(
			'object_cache' => true,
			'page_cache'   => true,
		);

		$use_case = new GenerateRecommendations( new Recommendations() );
		$result   = $use_case->execute( array( 'force_refresh' => true ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( array(), $result['data']['recommendations'] );
		$this->assertSame( 'No recommendations available.', $result['message'] );
	}
}
