<?php
/**
 * Service Provider Base Class
 *
 * Abstract base class for service providers.
 *
 * @package WPAdminHealth\Container
 */

namespace WPAdminHealth\Container;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Abstract Class ServiceProvider
 *
 * Base class for all service providers. Service providers are responsible
 * for registering services into the container.
 *
 * @since 1.1.0
 */
abstract class ServiceProvider {

	/**
	 * The container instance.
	 *
	 * @var ContainerInterface
	 */
	protected ContainerInterface $container;

	/**
	 * Services provided by this provider.
	 *
	 * Used for deferred loading optimization.
	 *
	 * @var array<string>
	 */
	protected array $provides = array();

	/**
	 * Whether this provider is deferred.
	 *
	 * Deferred providers are only registered when one of their
	 * services is actually requested.
	 *
	 * @var bool
	 */
	protected bool $deferred = false;

	/**
	 * Create a new service provider.
	 *
	 * @since 1.1.0
	 *
	 * @param ContainerInterface $container The container instance.
	 */
	public function __construct( ContainerInterface $container ) {
		$this->container = $container;
	}

	/**
	 * Register services into the container.
	 *
	 * This method is called when the provider is registered.
	 * Use it to bind services, singletons, and instances.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Bootstrap services after all providers are registered.
	 *
	 * This method is called after all providers have been registered.
	 * Use it for actions that depend on other services being available.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		// Default implementation does nothing.
		// Override in subclasses as needed.
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> List of service identifiers.
	 */
	public function provides(): array {
		return $this->provides;
	}

	/**
	 * Check if this provider is deferred.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if deferred, false otherwise.
	 */
	public function is_deferred(): bool {
		return $this->deferred;
	}

	/**
	 * Helper method to bind a service.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function.
	 * @return void
	 */
	protected function bind( string $id, callable $resolver ): void {
		$this->container->bind( $id, $resolver );
	}

	/**
	 * Helper method to bind a singleton.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function.
	 * @return void
	 */
	protected function singleton( string $id, callable $resolver ): void {
		$this->container->singleton( $id, $resolver );
	}

	/**
	 * Helper method to register an instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance The instance to register.
	 * @return void
	 */
	protected function instance( string $id, $instance ): void {
		$this->container->instance( $id, $instance );
	}

	/**
	 * Helper method to create an alias.
	 *
	 * @since 1.1.0
	 *
	 * @param string $alias    The alias name.
	 * @param string $abstract The abstract service to alias.
	 * @return void
	 */
	protected function alias( string $alias, string $abstract ): void {
		$this->container->alias( $alias, $abstract );
	}
}
