<?php
/**
 * Domain Settings Interface
 *
 * Contract for domain-specific settings classes.
 *
 * @package WPAdminHealth\Settings\Contracts
 */

namespace WPAdminHealth\Settings\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface DomainSettingsInterface
 *
 * Defines the contract for domain-specific settings classes.
 *
 * @since 1.2.0
 */
interface DomainSettingsInterface {

	/**
	 * Get the domain identifier.
	 *
	 * @return string Domain identifier (e.g., 'core', 'database', 'media').
	 */
	public function get_domain(): string;

	/**
	 * Get the section definition for this domain.
	 *
	 * @return array Section definition with 'title' and 'description' keys.
	 */
	public function get_section(): array;

	/**
	 * Get all field definitions for this domain.
	 *
	 * @return array Associative array of field definitions.
	 */
	public function get_fields(): array;

	/**
	 * Get default values for all fields.
	 *
	 * @return array Associative array of field defaults.
	 */
	public function get_defaults(): array;

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public function get( string $key, $default = null );
}
