<?php
/**
 * Integrations Bootstrap Tests
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WPAdminHealth\Plugin;
use WPAdminHealth\Tests\Mocks\TestIntegration;

/**
 * Test integrations are initialized through the normal bootstrap path.
 */
class IntegrationsBootstrapTest extends TestCase {
	/**
	 * Ensure integrations init runs and integrations can affect runtime behavior.
	 */
	public function test_integrations_initialized_via_normal_bootstrap(): void {
		$callback = array( $this, 'register_test_integration' );
		add_action( 'wpha_register_integrations', $callback );

		Plugin::reset();
		do_action( 'plugins_loaded' );

		$this->assertTrue( (bool) apply_filters( 'wpha_media_is_attachment_used', false, 123 ) );

		remove_action( 'wpha_register_integrations', $callback );
	}

	/**
	 * @param object $manager Integration manager.
	 */
	public function register_test_integration( $manager ): void {
		$manager->register( new TestIntegration() );
	}
}
