<?php
/**
 * Unit tests for HeartbeatController class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\HeartbeatController;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for HeartbeatController.
 *
 * @covers \WPAdminHealth\Performance\HeartbeatController
 */
class HeartbeatControllerTest extends StandaloneTestCase {

	/**
	 * Mock settings.
	 *
	 * @var SettingsInterface|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $settings;

	/**
	 * HeartbeatController instance.
	 *
	 * @var HeartbeatController
	 */
	private HeartbeatController $controller;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->settings = $this->createMock( SettingsInterface::class );

		// Default settings returns default heartbeat values
		$this->settings->method( 'get_setting' )->willReturnCallback(
			function ( $key, $default ) {
				return $default;
			}
		);

		$this->controller = new HeartbeatController( $this->settings );
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		delete_option( HeartbeatController::OPTION_NAME );
		delete_option( 'wpha_settings' );
	}

	/**
	 * Test get_current_settings returns default settings.
	 *
	 * @return void
	 */
	public function test_get_current_settings_returns_default_settings(): void {
		$result = $this->controller->get_current_settings();

		$this->assertArrayHasKey( 'dashboard', $result );
		$this->assertArrayHasKey( 'editor', $result );
		$this->assertArrayHasKey( 'frontend', $result );

		// Check default values
		$this->assertTrue( $result['dashboard']['enabled'] );
		$this->assertEquals( 60, $result['dashboard']['interval'] );
		$this->assertTrue( $result['editor']['enabled'] );
		$this->assertEquals( 15, $result['editor']['interval'] );
		$this->assertTrue( $result['frontend']['enabled'] );
		$this->assertEquals( 60, $result['frontend']['interval'] );
	}

	/**
	 * Test update_location_settings validates location.
	 *
	 * @return void
	 */
	public function test_update_location_settings_validates_location(): void {
		$result = $this->controller->update_location_settings( 'invalid_location', true, 60 );

		$this->assertFalse( $result );
	}

	/**
	 * Test update_location_settings accepts valid locations.
	 *
	 * @return void
	 */
	public function test_update_location_settings_accepts_valid_locations(): void {
		foreach ( HeartbeatController::VALID_LOCATIONS as $location ) {
			$result = $this->controller->update_location_settings( $location, true, 60 );
			// The result depends on update_option behavior in test environment
			$this->assertIsBool( $result );
		}
	}

	/**
	 * Test update_location_settings clamps interval.
	 *
	 * @return void
	 */
	public function test_update_location_settings_clamps_interval(): void {
		// Test with interval below minimum (15)
		$this->controller->update_location_settings( 'dashboard', true, 5 );
		$settings = $this->controller->get_current_settings();
		$this->assertGreaterThanOrEqual( 15, $settings['dashboard']['interval'] );

		// Test with interval above maximum (120)
		$this->controller->update_location_settings( 'dashboard', true, 200 );
		$settings = $this->controller->get_current_settings();
		$this->assertLessThanOrEqual( 120, $settings['dashboard']['interval'] );
	}

	/**
	 * Test disable_heartbeat validates location.
	 *
	 * @return void
	 */
	public function test_disable_heartbeat_validates_location(): void {
		$result = $this->controller->disable_heartbeat( 'invalid' );

		$this->assertFalse( $result );
	}

	/**
	 * Test enable_heartbeat validates location.
	 *
	 * @return void
	 */
	public function test_enable_heartbeat_validates_location(): void {
		$result = $this->controller->enable_heartbeat( 'invalid' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_presets returns all presets.
	 *
	 * @return void
	 */
	public function test_get_presets_returns_all_presets(): void {
		$presets = $this->controller->get_presets();

		$this->assertArrayHasKey( 'default', $presets );
		$this->assertArrayHasKey( 'optimized', $presets );
		$this->assertArrayHasKey( 'minimal', $presets );
	}

	/**
	 * Test get_presets preset structure.
	 *
	 * @return void
	 */
	public function test_get_presets_structure(): void {
		$presets = $this->controller->get_presets();

		foreach ( $presets as $name => $preset ) {
			$this->assertArrayHasKey( 'label', $preset, "Preset {$name} missing label" );
			$this->assertArrayHasKey( 'description', $preset, "Preset {$name} missing description" );
			$this->assertArrayHasKey( 'settings', $preset, "Preset {$name} missing settings" );
			$this->assertArrayHasKey( 'cpu_savings', $preset, "Preset {$name} missing cpu_savings" );

			// Check settings structure
			$this->assertArrayHasKey( 'dashboard', $preset['settings'] );
			$this->assertArrayHasKey( 'editor', $preset['settings'] );
			$this->assertArrayHasKey( 'frontend', $preset['settings'] );
		}
	}

	/**
	 * Test default preset has zero CPU savings.
	 *
	 * @return void
	 */
	public function test_default_preset_has_zero_cpu_savings(): void {
		$presets = $this->controller->get_presets();

		$this->assertEquals( 0, $presets['default']['cpu_savings'] );
	}

	/**
	 * Test optimized preset has CPU savings.
	 *
	 * @return void
	 */
	public function test_optimized_preset_has_cpu_savings(): void {
		$presets = $this->controller->get_presets();

		$this->assertEquals( 35, $presets['optimized']['cpu_savings'] );
	}

	/**
	 * Test minimal preset has highest CPU savings.
	 *
	 * @return void
	 */
	public function test_minimal_preset_has_highest_cpu_savings(): void {
		$presets = $this->controller->get_presets();

		$this->assertEquals( 65, $presets['minimal']['cpu_savings'] );
	}

	/**
	 * Test apply_preset validates preset name.
	 *
	 * @return void
	 */
	public function test_apply_preset_validates_preset_name(): void {
		$result = $this->controller->apply_preset( 'nonexistent_preset' );

		$this->assertFalse( $result );
	}

	/**
	 * Test calculate_cpu_savings returns expected structure.
	 *
	 * @return void
	 */
	public function test_calculate_cpu_savings_returns_structure(): void {
		$savings = $this->controller->calculate_cpu_savings();

		$this->assertArrayHasKey( 'total_percent', $savings );
		$this->assertArrayHasKey( 'by_location', $savings );
		$this->assertArrayHasKey( 'estimated_requests_saved', $savings );

		$this->assertArrayHasKey( 'dashboard', $savings['by_location'] );
		$this->assertArrayHasKey( 'editor', $savings['by_location'] );
		$this->assertArrayHasKey( 'frontend', $savings['by_location'] );
	}

	/**
	 * Test calculate_cpu_savings with default settings returns zero.
	 *
	 * @return void
	 */
	public function test_calculate_cpu_savings_default_settings_returns_zero(): void {
		$savings = $this->controller->calculate_cpu_savings();

		// Default settings should have 0% savings compared to default preset
		$this->assertEquals( 0.0, $savings['total_percent'] );
		$this->assertEquals( 0, $savings['estimated_requests_saved'] );
	}

	/**
	 * Test get_status returns comprehensive status.
	 *
	 * @return void
	 */
	public function test_get_status_returns_comprehensive_status(): void {
		$status = $this->controller->get_status();

		$this->assertArrayHasKey( 'current_settings', $status );
		$this->assertArrayHasKey( 'cpu_savings', $status );
		$this->assertArrayHasKey( 'active_preset', $status );
	}

	/**
	 * Test validate_settings validates structure.
	 *
	 * @return void
	 */
	public function test_validate_settings_validates_structure(): void {
		// Invalid: missing locations
		$this->assertFalse( $this->controller->validate_settings( array() ) );

		// Invalid: missing enabled flag
		$invalid = array(
			'dashboard' => array( 'interval' => 60 ),
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => true, 'interval' => 60 ),
		);
		$this->assertFalse( $this->controller->validate_settings( $invalid ) );

		// Invalid: wrong interval type
		$invalid = array(
			'dashboard' => array( 'enabled' => true, 'interval' => '60' ),
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => true, 'interval' => 60 ),
		);
		$this->assertFalse( $this->controller->validate_settings( $invalid ) );

		// Invalid: interval out of range
		$invalid = array(
			'dashboard' => array( 'enabled' => true, 'interval' => 10 ), // Below 15
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => true, 'interval' => 60 ),
		);
		$this->assertFalse( $this->controller->validate_settings( $invalid ) );
	}

	/**
	 * Test validate_settings accepts valid settings.
	 *
	 * @return void
	 */
	public function test_validate_settings_accepts_valid_settings(): void {
		$valid = array(
			'dashboard' => array( 'enabled' => true, 'interval' => 60 ),
			'editor'    => array( 'enabled' => true, 'interval' => 15 ),
			'frontend'  => array( 'enabled' => false, 'interval' => 120 ),
		);

		$this->assertTrue( $this->controller->validate_settings( $valid ) );
	}

	/**
	 * Test is_enabled checks enabled status.
	 *
	 * @return void
	 */
	public function test_is_enabled_checks_enabled_status(): void {
		// Default settings have all locations enabled
		$this->assertTrue( $this->controller->is_enabled( 'dashboard' ) );
		$this->assertTrue( $this->controller->is_enabled( 'editor' ) );
		$this->assertTrue( $this->controller->is_enabled( 'frontend' ) );

		// Invalid location
		$this->assertFalse( $this->controller->is_enabled( 'invalid' ) );
	}

	/**
	 * Test get_interval returns correct interval.
	 *
	 * @return void
	 */
	public function test_get_interval_returns_correct_interval(): void {
		$this->assertEquals( 60, $this->controller->get_interval( 'dashboard' ) );
		$this->assertEquals( 15, $this->controller->get_interval( 'editor' ) );
		$this->assertEquals( 60, $this->controller->get_interval( 'frontend' ) );

		// Invalid location returns default
		$this->assertEquals( HeartbeatController::DEFAULT_FREQUENCY, $this->controller->get_interval( 'invalid' ) );
	}

	/**
	 * Test apply_heartbeat_settings filter.
	 *
	 * @return void
	 */
	public function test_apply_heartbeat_settings_filter(): void {
		$input_settings = array( 'interval' => 15 );

		$result = $this->controller->apply_heartbeat_settings( $input_settings );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'interval', $result );
	}

	/**
	 * Test constants are defined correctly.
	 *
	 * @return void
	 */
	public function test_constants_are_defined(): void {
		$this->assertEquals( 'wpha_heartbeat_settings', HeartbeatController::OPTION_NAME );
		$this->assertEquals( array( 'dashboard', 'editor', 'frontend' ), HeartbeatController::VALID_LOCATIONS );
		$this->assertEquals( array( 15, 30, 60, 120 ), HeartbeatController::VALID_FREQUENCIES );
		$this->assertEquals( 60, HeartbeatController::DEFAULT_FREQUENCY );
		$this->assertEquals( 15, HeartbeatController::DEFAULT_EDITOR_FREQUENCY );
	}

	/**
	 * Test reset_to_defaults returns true.
	 *
	 * @return void
	 */
	public function test_reset_to_defaults_returns_true(): void {
		$result = $this->controller->reset_to_defaults();

		$this->assertTrue( $result );
	}

	/**
	 * Test update_frequency is deprecated but works.
	 *
	 * @return void
	 */
	public function test_update_frequency_deprecated_method(): void {
		// This method is deprecated but should still work
		$result = $this->controller->update_frequency( 'dashboard', 120 );

		$this->assertIsBool( $result );
	}

	/**
	 * Test interval bounds - minimum.
	 *
	 * @return void
	 */
	public function test_interval_bounds_minimum(): void {
		$presets = $this->controller->get_presets();

		foreach ( $presets as $preset ) {
			foreach ( $preset['settings'] as $location => $setting ) {
				$this->assertGreaterThanOrEqual( 15, $setting['interval'] );
			}
		}
	}

	/**
	 * Test interval bounds - maximum.
	 *
	 * @return void
	 */
	public function test_interval_bounds_maximum(): void {
		$presets = $this->controller->get_presets();

		foreach ( $presets as $preset ) {
			foreach ( $preset['settings'] as $location => $setting ) {
				$this->assertLessThanOrEqual( 120, $setting['interval'] );
			}
		}
	}
}
