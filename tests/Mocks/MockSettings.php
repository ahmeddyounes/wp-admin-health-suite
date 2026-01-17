<?php
/**
 * Mock Settings Implementation
 *
 * Mock implementation of SettingsInterface for testing.
 *
 * @package WPAdminHealth\Tests\Mocks
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Contracts\SettingsInterface;

/**
 * Class MockSettings
 *
 * Provides a mock implementation of SettingsInterface for unit testing.
 */
class MockSettings implements SettingsInterface {

	/**
	 * Settings storage.
	 *
	 * @var array
	 */
	private array $settings = array();

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private array $defaults = array(
		'safe_mode_enabled'      => false,
		'debug_mode_enabled'     => false,
		'rest_api_enabled'       => true,
		'rest_api_rate_limit'    => 60,
		'log_retention_days'     => 30,
		'activity_log_max_rows'  => 10000,
	);

	/**
	 * Set a specific setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return void
	 */
	public function set_setting( string $key, $value ): void {
		$this->settings[ $key ] = $value;
	}

	/**
	 * Set multiple settings at once.
	 *
	 * @param array $settings Key-value pairs of settings.
	 * @return void
	 */
	public function set_settings( array $settings ): void {
		$this->settings = array_merge( $this->settings, $settings );
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->settings = array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings(): array {
		return array_merge( $this->defaults, $this->settings );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_setting( string $key, $default = null ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		if ( isset( $this->defaults[ $key ] ) ) {
			return $this->defaults[ $key ];
		}

		return $default;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_default_settings(): array {
		return $this->defaults;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_safe_mode_enabled(): bool {
		return (bool) $this->get_setting( 'safe_mode_enabled', false );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_debug_mode_enabled(): bool {
		return (bool) $this->get_setting( 'debug_mode_enabled', false );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_rest_api_enabled(): bool {
		return (bool) $this->get_setting( 'rest_api_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_rest_api_rate_limit(): int {
		return (int) $this->get_setting( 'rest_api_rate_limit', 60 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_sections(): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {
		return array();
	}
}
