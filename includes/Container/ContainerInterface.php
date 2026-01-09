<?php
/**
 * Container Interface
 *
 * PSR-11 compatible service container interface.
 *
 * @package WPAdminHealth\Container
 */

namespace WPAdminHealth\Container;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface ContainerInterface
 *
 * Defines the contract for the dependency injection container.
 * Compatible with PSR-11 ContainerInterface.
 *
 * @since 1.1.0
 */
interface ContainerInterface {

	/**
	 * Find an entry of the container by its identifier and returns it.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 * @throws NotFoundException No entry was found for this identifier.
	 */
	public function get( string $id );

	/**
	 * Check if the container can return an entry for the given identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool True if entry exists, false otherwise.
	 */
	public function has( string $id ): bool;

	/**
	 * Bind a service to the container.
	 *
	 * Creates a new instance each time the service is resolved.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function that creates the service.
	 * @return void
	 */
	public function bind( string $id, callable $resolver ): void;

	/**
	 * Bind a singleton service to the container.
	 *
	 * Creates only one instance and returns the same instance on subsequent calls.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function that creates the service.
	 * @return void
	 */
	public function singleton( string $id, callable $resolver ): void;

	/**
	 * Register an existing instance in the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance The instance to register.
	 * @return void
	 */
	public function instance( string $id, $instance ): void;

	/**
	 * Create an alias for a service.
	 *
	 * @since 1.1.0
	 *
	 * @param string $alias    The alias name.
	 * @param string $abstract The abstract service to alias.
	 * @return void
	 */
	public function alias( string $alias, string $abstract ): void;

	/**
	 * Register a service provider.
	 *
	 * @since 1.1.0
	 *
	 * @param ServiceProvider $provider The service provider to register.
	 * @return void
	 */
	public function register( ServiceProvider $provider ): void;

	/**
	 * Boot all registered service providers.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function boot(): void;
}
