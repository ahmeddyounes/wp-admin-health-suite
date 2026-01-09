<?php
/**
 * Settings Interface
 *
 * Contract for plugin settings management.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SettingsInterface
 *
 * Defines the contract for accessing and managing plugin settings.
 *
 * @since 1.1.0
 */
interface SettingsInterface {

	/**
	 * Get all settings.
	 *
	 * @since 1.1.0
	 *
	 * @return array All settings with defaults applied.
	 */
	public function get_settings(): array;

	/**
	 * Get a specific setting value.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value or default.
	 */
	public function get_setting( string $key, $default = null );

	/**
	 * Get default settings.
	 *
	 * @since 1.1.0
	 *
	 * @return array Default settings values.
	 */
	public function get_default_settings(): array;

	/**
	 * Check if safe mode is enabled.
	 *
	 * When safe mode is enabled, all destructive operations should return
	 * preview data only without actually modifying anything.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if safe mode is enabled.
	 */
	public function is_safe_mode_enabled(): bool;

	/**
	 * Check if debug mode is enabled.
	 *
	 * When debug mode is enabled, extra logging and query time information
	 * should be included in responses.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public function is_debug_mode_enabled(): bool;

	/**
	 * Check if REST API is enabled.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if REST API is enabled.
	 */
	public function is_rest_api_enabled(): bool;

	/**
	 * Get REST API rate limit.
	 *
	 * @since 1.1.0
	 *
	 * @return int Maximum requests per minute.
	 */
	public function get_rest_api_rate_limit(): int;

	/**
	 * Get settings sections.
	 *
	 * @since 1.1.0
	 *
	 * @return array Settings sections.
	 */
	public function get_sections(): array;

	/**
	 * Get settings fields.
	 *
	 * @since 1.1.0
	 *
	 * @return array Settings fields definitions.
	 */
	public function get_fields(): array;
}
