<?php
/**
 * Settings Registry Interface
 *
 * Contract for the settings registry that aggregates domain settings.
 *
 * @package WPAdminHealth\Settings\Contracts
 */

namespace WPAdminHealth\Settings\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface SettingsRegistryInterface
 *
 * Defines the contract for the settings registry.
 *
 * @since 1.2.0
 */
interface SettingsRegistryInterface {

	/**
	 * Register a domain settings class.
	 *
	 * @param DomainSettingsInterface $domain Domain settings instance.
	 * @return void
	 */
	public function register( DomainSettingsInterface $domain ): void;

	/**
	 * Get a domain settings instance by identifier.
	 *
	 * @param string $domain Domain identifier.
	 * @return DomainSettingsInterface|null Domain settings or null if not found.
	 */
	public function get_domain( string $domain ): ?DomainSettingsInterface;

	/**
	 * Get all registered domain settings.
	 *
	 * @return array<string, DomainSettingsInterface> Array of domain settings.
	 */
	public function get_domains(): array;

	/**
	 * Get all settings across all domains.
	 *
	 * @return array Merged settings from all domains.
	 */
	public function get_all_settings(): array;

	/**
	 * Get all section definitions.
	 *
	 * @return array Array of section definitions.
	 */
	public function get_all_sections(): array;

	/**
	 * Get all field definitions.
	 *
	 * @return array Array of field definitions.
	 */
	public function get_all_fields(): array;
}
