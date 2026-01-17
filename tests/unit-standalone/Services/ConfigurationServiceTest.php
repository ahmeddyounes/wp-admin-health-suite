<?php
/**
 * Configuration Service Unit Tests (Standalone)
 *
 * Tests for the ConfigurationService including environment detection,
 * runtime overrides, and constant-based configuration.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Services
 */

namespace WPAdminHealth\Tests\UnitStandalone\Services;

use WPAdminHealth\Services\ConfigurationService;
use WPAdminHealth\Contracts\ConfigurationInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Configuration Service test class.
 */
class ConfigurationServiceTest extends StandaloneTestCase {

	/**
	 * ConfigurationService instance.
	 *
	 * @var ConfigurationService
	 */
	protected ConfigurationService $config;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		// Clear any test globals.
		unset( $GLOBALS['wpha_test_wp_environment'] );

		$this->config = new ConfigurationService();
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		unset( $GLOBALS['wpha_test_wp_environment'] );
	}

	/**
	 * Test ConfigurationService implements ConfigurationInterface.
	 */
	public function test_implements_configuration_interface(): void {
		$this->assertInstanceOf( ConfigurationInterface::class, $this->config );
	}

	/**
	 * Test get returns default config values.
	 */
	public function test_get_returns_default_values(): void {
		$this->assertEquals( 50, $this->config->get( 'media.batch_size' ) );
		$this->assertEquals( 100, $this->config->get( 'database.batch_size' ) );
		$this->assertEquals( 0.05, $this->config->get( 'performance.slow_query_threshold' ) );
	}

	/**
	 * Test get with dot notation for nested values.
	 */
	public function test_get_with_dot_notation(): void {
		$this->assertEquals( 1024 * 1024, $this->config->get( 'media.large_file_threshold' ) );
		$this->assertEquals( HOUR_IN_SECONDS, $this->config->get( 'cache.default_ttl' ) );
		$this->assertEquals( 20, $this->config->get( 'api.default_per_page' ) );
	}

	/**
	 * Test get returns default for non-existent key.
	 */
	public function test_get_returns_default_for_missing_key(): void {
		$this->assertNull( $this->config->get( 'nonexistent.key' ) );
		$this->assertEquals( 'custom', $this->config->get( 'missing.key', 'custom' ) );
	}

	/**
	 * Test get returns entire section when using single key.
	 */
	public function test_get_returns_section(): void {
		$media = $this->config->get( 'media' );

		$this->assertIsArray( $media );
		$this->assertArrayHasKey( 'batch_size', $media );
		$this->assertArrayHasKey( 'large_file_threshold', $media );
	}

	/**
	 * Test has returns true for existing keys.
	 */
	public function test_has_returns_true_for_existing_key(): void {
		$this->assertTrue( $this->config->has( 'media.batch_size' ) );
		$this->assertTrue( $this->config->has( 'database' ) );
		$this->assertTrue( $this->config->has( 'performance.slow_query_threshold' ) );
	}

	/**
	 * Test has returns false for non-existent keys.
	 */
	public function test_has_returns_false_for_missing_key(): void {
		$this->assertFalse( $this->config->has( 'nonexistent' ) );
		$this->assertFalse( $this->config->has( 'media.nonexistent' ) );
		$this->assertFalse( $this->config->has( 'a.b.c.d' ) );
	}

	/**
	 * Test all returns complete configuration.
	 */
	public function test_all_returns_complete_config(): void {
		$all = $this->config->all();

		$this->assertIsArray( $all );
		$this->assertArrayHasKey( 'media', $all );
		$this->assertArrayHasKey( 'database', $all );
		$this->assertArrayHasKey( 'performance', $all );
		$this->assertArrayHasKey( 'cache', $all );
		$this->assertArrayHasKey( 'api', $all );
		$this->assertArrayHasKey( 'scheduler', $all );
	}

	/**
	 * Test media returns media configuration.
	 */
	public function test_media_returns_media_config(): void {
		$media = $this->config->media();

		$this->assertIsArray( $media );
		$this->assertEquals( 50, $media['batch_size'] );
		$this->assertEquals( 1024 * 1024, $media['large_file_threshold'] );
		$this->assertEquals( 30, $media['retention_days'] );
	}

	/**
	 * Test database returns database configuration.
	 */
	public function test_database_returns_database_config(): void {
		$database = $this->config->database();

		$this->assertIsArray( $database );
		$this->assertEquals( 100, $database['batch_size'] );
		$this->assertEquals( 30, $database['log_ttl_days'] );
	}

	/**
	 * Test performance returns performance configuration.
	 */
	public function test_performance_returns_performance_config(): void {
		$performance = $this->config->performance();

		$this->assertIsArray( $performance );
		$this->assertEquals( 5 * MINUTE_IN_SECONDS, $performance['cache_ttl'] );
		$this->assertEquals( 0.05, $performance['slow_query_threshold'] );
	}

	/**
	 * Test cache returns cache configuration.
	 */
	public function test_cache_returns_cache_config(): void {
		$cache = $this->config->cache();

		$this->assertIsArray( $cache );
		$this->assertEquals( HOUR_IN_SECONDS, $cache['default_ttl'] );
		$this->assertEquals( DAY_IN_SECONDS, $cache['ai_cache_ttl'] );
	}

	/**
	 * Test set stores runtime override.
	 */
	public function test_set_stores_runtime_override(): void {
		$this->config->set( 'media.batch_size', 200 );

		$this->assertEquals( 200, $this->config->get( 'media.batch_size' ) );
	}

	/**
	 * Test set with custom key.
	 */
	public function test_set_with_custom_key(): void {
		$this->config->set( 'custom.setting', 'value' );

		$this->assertTrue( $this->config->has( 'custom.setting' ) );
		$this->assertEquals( 'value', $this->config->get( 'custom.setting' ) );
	}

	/**
	 * Test runtime override takes precedence over config.
	 */
	public function test_runtime_override_takes_precedence(): void {
		// Original value.
		$this->assertEquals( 50, $this->config->get( 'media.batch_size' ) );

		// Set override.
		$this->config->set( 'media.batch_size', 999 );

		// Override should be returned.
		$this->assertEquals( 999, $this->config->get( 'media.batch_size' ) );
	}

	/**
	 * Test get_environment returns environment.
	 */
	public function test_get_environment_returns_environment(): void {
		$env = $this->config->get_environment();

		$this->assertIsString( $env );
		$this->assertContains( $env, array( 'production', 'staging', 'development', 'local' ) );
	}

	/**
	 * Test is_environment with matching environment.
	 */
	public function test_is_environment_returns_true_for_match(): void {
		$env = $this->config->get_environment();

		$this->assertTrue( $this->config->is_environment( $env ) );
	}

	/**
	 * Test is_environment is case insensitive.
	 */
	public function test_is_environment_is_case_insensitive(): void {
		$env = $this->config->get_environment();

		$this->assertTrue( $this->config->is_environment( strtoupper( $env ) ) );
		$this->assertTrue( $this->config->is_environment( ucfirst( $env ) ) );
	}

	/**
	 * Test is_environment returns false for non-match.
	 */
	public function test_is_environment_returns_false_for_non_match(): void {
		// At least one of these won't match.
		$non_matching = array_diff(
			array( 'production', 'staging', 'development', 'local' ),
			array( $this->config->get_environment() )
		);

		foreach ( $non_matching as $env ) {
			$this->assertFalse( $this->config->is_environment( $env ) );
		}
	}

	/**
	 * Test environment detection via wp_get_environment_type.
	 */
	public function test_environment_detection_via_wp_function(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'development';

		$config = new ConfigurationService();

		$this->assertEquals( 'development', $config->get_environment() );
	}

	/**
	 * Test development environment has shorter cache TTL.
	 */
	public function test_development_environment_has_shorter_cache(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'development';

		$config = new ConfigurationService();

		$this->assertEquals( MINUTE_IN_SECONDS, $config->get( 'cache.default_ttl' ) );
		$this->assertEquals( MINUTE_IN_SECONDS, $config->get( 'performance.cache_ttl' ) );
	}

	/**
	 * Test local environment has very short cache TTL.
	 */
	public function test_local_environment_has_very_short_cache(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'local';

		$config = new ConfigurationService();

		$this->assertEquals( 30, $config->get( 'cache.default_ttl' ) );
		$this->assertEquals( 30, $config->get( 'performance.cache_ttl' ) );
	}

	/**
	 * Test staging environment has slightly lenient slow query threshold.
	 */
	public function test_staging_environment_settings(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'staging';

		$config = new ConfigurationService();

		$this->assertEquals( 0.075, $config->get( 'performance.slow_query_threshold' ) );
	}

	/**
	 * Test production environment uses defaults.
	 */
	public function test_production_environment_uses_defaults(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'production';

		$config = new ConfigurationService();

		$this->assertEquals( HOUR_IN_SECONDS, $config->get( 'cache.default_ttl' ) );
		$this->assertEquals( 0.05, $config->get( 'performance.slow_query_threshold' ) );
	}

	/**
	 * Test invalid environment defaults to production.
	 */
	public function test_invalid_environment_defaults_to_production(): void {
		$GLOBALS['wpha_test_wp_environment'] = 'invalid_env';

		$config = new ConfigurationService();

		$this->assertEquals( 'production', $config->get_environment() );
	}

	/**
	 * Test api configuration section.
	 */
	public function test_api_configuration(): void {
		$this->assertEquals( MINUTE_IN_SECONDS, $this->config->get( 'api.rate_limit_ttl' ) );
		$this->assertEquals( 20, $this->config->get( 'api.default_per_page' ) );
		$this->assertEquals( 100, $this->config->get( 'api.max_per_page' ) );
	}

	/**
	 * Test scheduler configuration section.
	 */
	public function test_scheduler_configuration(): void {
		$this->assertEquals( DAY_IN_SECONDS, $this->config->get( 'scheduler.daily_interval' ) );
		$this->assertEquals( 7 * DAY_IN_SECONDS, $this->config->get( 'scheduler.weekly_interval' ) );
		$this->assertEquals( 30 * DAY_IN_SECONDS, $this->config->get( 'scheduler.monthly_interval' ) );
	}

	/**
	 * Test deeply nested key access.
	 */
	public function test_deeply_nested_key_returns_default(): void {
		// Keys that don't exist at deeper levels.
		$this->assertNull( $this->config->get( 'media.batch_size.nested' ) );
		$this->assertEquals( 'default', $this->config->get( 'media.batch_size.nested', 'default' ) );
	}

	/**
	 * Test set with null value.
	 */
	public function test_set_with_null_value(): void {
		$this->config->set( 'test.null', null );

		$this->assertTrue( $this->config->has( 'test.null' ) );
		$this->assertNull( $this->config->get( 'test.null' ) );
	}

	/**
	 * Test set with array value.
	 */
	public function test_set_with_array_value(): void {
		$array_value = array( 'key1' => 'value1', 'key2' => 'value2' );
		$this->config->set( 'test.array', $array_value );

		$this->assertEquals( $array_value, $this->config->get( 'test.array' ) );
	}

	/**
	 * Test multiple runtime overrides.
	 */
	public function test_multiple_runtime_overrides(): void {
		$this->config->set( 'media.batch_size', 100 );
		$this->config->set( 'database.batch_size', 200 );
		$this->config->set( 'custom.key', 'value' );

		$this->assertEquals( 100, $this->config->get( 'media.batch_size' ) );
		$this->assertEquals( 200, $this->config->get( 'database.batch_size' ) );
		$this->assertEquals( 'value', $this->config->get( 'custom.key' ) );
	}

	/**
	 * Test get with empty string key.
	 */
	public function test_get_with_empty_key(): void {
		$this->assertNull( $this->config->get( '' ) );
		$this->assertEquals( 'default', $this->config->get( '', 'default' ) );
	}

	/**
	 * Test has with empty string key.
	 */
	public function test_has_with_empty_key(): void {
		$this->assertFalse( $this->config->has( '' ) );
	}
}
