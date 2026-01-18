<?php
/**
 * Integration Factory Interface
 *
 * Contract for creating integration instances via the container.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface IntegrationFactoryInterface
 *
 * Defines the contract for creating integration instances.
 * Allows the IntegrationManager to resolve integrations from the container
 * instead of using direct instantiation.
 *
 * @since 1.1.0
 */
interface IntegrationFactoryInterface {

	/**
	 * Create an integration instance by its identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Integration identifier (e.g., 'woocommerce', 'elementor').
	 * @return IntegrationInterface|null The integration instance or null if not available.
	 */
	public function create( string $id ): ?IntegrationInterface;

	/**
	 * Check if the factory can create an integration with the given identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Integration identifier.
	 * @return bool True if the integration can be created.
	 */
	public function can_create( string $id ): bool;

	/**
	 * Get all available integration identifiers this factory can create.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> List of integration identifiers.
	 */
	public function get_available_ids(): array;
}
