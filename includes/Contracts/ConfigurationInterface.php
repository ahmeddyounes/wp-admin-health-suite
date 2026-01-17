<?php
/**
 * Configuration Interface
 *
 * Contract for centralized configuration management.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ConfigurationInterface
 *
 * Defines the contract for accessing centralized configuration values.
 * Extracts hardcoded values (thresholds, limits, TTLs) into a single source.
 *
 * @since 1.3.0
 */
interface ConfigurationInterface {

	/**
	 * Get a configuration value.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key     Configuration key using dot notation (e.g., 'media.size_threshold').
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Configuration value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Set a configuration value at runtime.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key   Configuration key using dot notation.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	public function set( string $key, $value ): void;

	/**
	 * Check if a configuration key exists.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Configuration key using dot notation.
	 * @return bool True if key exists, false otherwise.
	 */
	public function has( string $key ): bool;

	/**
	 * Get all configuration values.
	 *
	 * @since 1.3.0
	 *
	 * @return array All configuration values.
	 */
	public function all(): array;

	/**
	 * Get all media-related configuration.
	 *
	 * @since 1.3.0
	 *
	 * @return array Media configuration values.
	 */
	public function media(): array;

	/**
	 * Get all database-related configuration.
	 *
	 * @since 1.3.0
	 *
	 * @return array Database configuration values.
	 */
	public function database(): array;

	/**
	 * Get all performance-related configuration.
	 *
	 * @since 1.3.0
	 *
	 * @return array Performance configuration values.
	 */
	public function performance(): array;

	/**
	 * Get all cache-related configuration.
	 *
	 * @since 1.3.0
	 *
	 * @return array Cache configuration values.
	 */
	public function cache(): array;

	/**
	 * Get the current environment.
	 *
	 * @since 1.3.0
	 *
	 * @return string Environment name (production, staging, development, local).
	 */
	public function get_environment(): string;

	/**
	 * Check if running in a specific environment.
	 *
	 * @since 1.3.0
	 *
	 * @param string $environment Environment to check against.
	 * @return bool True if running in the specified environment.
	 */
	public function is_environment( string $environment ): bool;
}
