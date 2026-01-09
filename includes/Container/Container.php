<?php
/**
 * Service Container
 *
 * PSR-11 compatible dependency injection container.
 *
 * @package WPAdminHealth\Container
 */

namespace WPAdminHealth\Container;

use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Container
 *
 * A lightweight PSR-11 compatible dependency injection container.
 * Supports factory bindings, singletons, auto-wiring via reflection,
 * and service providers for organized service registration.
 *
 * @since 1.1.0
 */
class Container implements Container_Interface {

	/**
	 * Registered bindings.
	 *
	 * @var array<string, array{resolver: callable, singleton: bool}>
	 */
	private array $bindings = array();

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Service aliases.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = array();

	/**
	 * Registered service providers.
	 *
	 * @var array<Service_Provider>
	 */
	private array $providers = array();

	/**
	 * Deferred service providers mapped by their provided services.
	 *
	 * @var array<string, Service_Provider>
	 */
	private array $deferred_providers = array();

	/**
	 * Whether the container has been booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Whether the container is currently booting.
	 *
	 * This flag helps handle deferred providers that are requested during
	 * the boot phase of other providers - they should be booted immediately.
	 *
	 * @var bool
	 */
	private bool $booting = false;

	/**
	 * Stack of services currently being resolved (for circular dependency detection).
	 *
	 * @var array<string>
	 */
	private array $resolution_stack = array();

	/**
	 * Find an entry of the container by its identifier and returns it.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 * @throws NotFoundException No entry was found for this identifier.
	 */
	public function get( string $id ) {
		// Resolve alias if exists.
		$id = $this->get_alias( $id );

		// Check for deferred provider that needs to be registered.
		if ( isset( $this->deferred_providers[ $id ] ) ) {
			$this->register_deferred_provider( $id );
		}

		// Return existing instance if available.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check for binding.
		if ( isset( $this->bindings[ $id ] ) ) {
			return $this->resolve( $id );
		}

		// Try auto-wiring if the class exists.
		if ( class_exists( $id ) ) {
			return $this->auto_wire( $id );
		}

		throw new NotFoundException( $id );
	}

	/**
	 * Check if the container can return an entry for the given identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool True if entry exists, false otherwise.
	 */
	public function has( string $id ): bool {
		$id = $this->get_alias( $id );

		return isset( $this->bindings[ $id ] )
			|| isset( $this->instances[ $id ] )
			|| isset( $this->deferred_providers[ $id ] )
			|| class_exists( $id );
	}

	/**
	 * Bind a service to the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function that creates the service.
	 * @return void
	 */
	public function bind( string $id, callable $resolver ): void {
		// Clear any cached singleton instance.
		unset( $this->instances[ $id ] );

		$this->bindings[ $id ] = array(
			'resolver'  => $resolver,
			'singleton' => false,
		);
	}

	/**
	 * Bind a singleton service to the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $id       Service identifier.
	 * @param callable $resolver Factory function that creates the service.
	 * @return void
	 */
	public function singleton( string $id, callable $resolver ): void {
		// Clear any cached instance.
		unset( $this->instances[ $id ] );

		$this->bindings[ $id ] = array(
			'resolver'  => $resolver,
			'singleton' => true,
		);
	}

	/**
	 * Register an existing instance in the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance The instance to register.
	 * @return void
	 */
	public function instance( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Create an alias for a service.
	 *
	 * @since 1.1.0
	 *
	 * @param string $alias    The alias name.
	 * @param string $abstract The abstract service to alias.
	 * @return void
	 */
	public function alias( string $alias, string $abstract ): void {
		$this->aliases[ $alias ] = $abstract;
	}

	/**
	 * Register a service provider.
	 *
	 * @since 1.1.0
	 *
	 * @param Service_Provider $provider The service provider to register.
	 * @return void
	 * @throws ContainerException If provider registration fails.
	 */
	public function register( Service_Provider $provider ): void {
		// Store the provider.
		$this->providers[] = $provider;

		// Handle deferred providers.
		if ( $provider->is_deferred() ) {
			foreach ( $provider->provides() as $service ) {
				$this->deferred_providers[ $service ] = $provider;
			}
			return;
		}

		// Register the provider immediately with error handling.
		try {
			$provider->register();
		} catch ( \Throwable $e ) {
			$this->log_error(
				get_class( $provider ),
				sprintf( 'Registration failed: %s', $e->getMessage() )
			);
			throw ContainerException::provider_registration_failed( get_class( $provider ), $e );
		}

		// Boot if container is already booted.
		if ( $this->booted ) {
			try {
				$provider->boot();
			} catch ( \Throwable $e ) {
				$this->log_error(
					get_class( $provider ),
					sprintf( 'Boot failed: %s', $e->getMessage() )
				);
				throw ContainerException::provider_boot_failed( get_class( $provider ), $e );
			}
		}
	}

	/**
	 * Boot all registered service providers.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 * @throws ContainerException If provider boot fails.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		// Set booting flag to handle deferred providers requested during boot.
		$this->booting = true;

		try {
			foreach ( $this->providers as $provider ) {
				if ( ! $provider->is_deferred() ) {
					try {
						$provider->boot();
					} catch ( \Throwable $e ) {
						$this->log_error(
							get_class( $provider ),
							sprintf( 'Boot failed: %s', $e->getMessage() )
						);
						throw ContainerException::provider_boot_failed( get_class( $provider ), $e );
					}
				}
			}

			$this->booted = true;
		} finally {
			$this->booting = false;
		}
	}

	/**
	 * Resolve a binding from the container.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed Resolved service.
	 * @throws \RuntimeException If circular dependency detected.
	 * @throws ContainerException If resolver fails.
	 */
	private function resolve( string $id ) {
		// Detect circular dependencies.
		if ( in_array( $id, $this->resolution_stack, true ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: Resolution chain */
					__( 'Circular dependency detected: %s', 'wp-admin-health-suite' ),
					implode( ' -> ', array_merge( $this->resolution_stack, array( $id ) ) )
				)
			);
		}

		$this->resolution_stack[] = $id;

		try {
			$binding  = $this->bindings[ $id ];
			$instance = call_user_func( $binding['resolver'], $this );

			// Cache singleton instances.
			if ( $binding['singleton'] ) {
				$this->instances[ $id ] = $instance;
			}

			return $instance;
		} catch ( \RuntimeException $e ) {
			// Re-throw runtime exceptions (like circular dependency) as-is.
			throw $e;
		} catch ( \Throwable $e ) {
			$this->log_error( $id, sprintf( 'Resolver failed: %s', $e->getMessage() ) );
			throw ContainerException::resolver_failed( $id, $e );
		} finally {
			array_pop( $this->resolution_stack );
		}
	}

	/**
	 * Auto-wire a class by resolving its constructor dependencies.
	 *
	 * @since 1.1.0
	 *
	 * @param string $class_name The class name to instantiate.
	 * @return object The instantiated class.
	 * @throws NotFoundException If a dependency cannot be resolved.
	 * @throws ContainerException If reflection fails.
	 * @throws \RuntimeException If circular dependency detected.
	 */
	private function auto_wire( string $class_name ): object {
		// Detect circular dependencies during auto-wiring.
		if ( in_array( $class_name, $this->resolution_stack, true ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: Resolution chain */
					__( 'Circular dependency detected: %s', 'wp-admin-health-suite' ),
					implode( ' -> ', array_merge( $this->resolution_stack, array( $class_name ) ) )
				)
			);
		}

		$this->resolution_stack[] = $class_name;

		try {
			$reflection = new ReflectionClass( $class_name );
		} catch ( ReflectionException $e ) {
			$this->log_error( $class_name, sprintf( 'Reflection failed: %s', $e->getMessage() ) );
			throw ContainerException::auto_wire_failed( $class_name, $e );
		}

		// Check if class is instantiable.
		if ( ! $reflection->isInstantiable() ) {
			throw new NotFoundException( $class_name );
		}

		try {
			$constructor = $reflection->getConstructor();

			// No constructor means no dependencies.
			if ( null === $constructor ) {
				return new $class_name();
			}

			$parameters   = $constructor->getParameters();
			$dependencies = array();

			foreach ( $parameters as $parameter ) {
				$dependency = $this->resolve_parameter( $parameter );
				if ( null !== $dependency ) {
					$dependencies[] = $dependency;
				} elseif ( $parameter->isDefaultValueAvailable() ) {
					$dependencies[] = $parameter->getDefaultValue();
				} else {
					throw new NotFoundException(
						sprintf(
							/* translators: 1: Parameter name, 2: Class name */
							__( 'Cannot resolve parameter $%1$s for class %2$s', 'wp-admin-health-suite' ),
							$parameter->getName(),
							$class_name
						)
					);
				}
			}

			return $reflection->newInstanceArgs( $dependencies );
		} catch ( \RuntimeException $e ) {
			// Re-throw runtime exceptions (like circular dependency) as-is.
			throw $e;
		} catch ( ReflectionException $e ) {
			$this->log_error( $class_name, sprintf( 'Instantiation failed: %s', $e->getMessage() ) );
			throw ContainerException::auto_wire_failed( $class_name, $e );
		} finally {
			array_pop( $this->resolution_stack );
		}
	}

	/**
	 * Resolve a constructor parameter.
	 *
	 * @since 1.1.0
	 *
	 * @param ReflectionParameter $parameter The parameter to resolve.
	 * @return mixed|null The resolved value or null if not resolvable.
	 */
	private function resolve_parameter( ReflectionParameter $parameter ) {
		$type = $parameter->getType();

		// No type hint, cannot auto-wire.
		if ( null === $type || ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
			return null;
		}

		$type_name = $type->getName();

		// Try to resolve from container.
		if ( $this->has( $type_name ) ) {
			return $this->get( $type_name );
		}

		// Allow nullable parameters.
		if ( $type->allowsNull() ) {
			return null;
		}

		return null;
	}

	/**
	 * Get the actual service identifier, resolving aliases.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The service identifier or alias.
	 * @return string The resolved service identifier.
	 * @throws \RuntimeException If circular alias is detected.
	 */
	private function get_alias( string $id ): string {
		$seen        = array();
		$original_id = $id;

		while ( isset( $this->aliases[ $id ] ) ) {
			if ( isset( $seen[ $id ] ) ) {
				throw new \RuntimeException(
					sprintf(
						/* translators: 1: Original alias, 2: Circular chain */
						__( 'Circular alias detected for "%1$s": %2$s', 'wp-admin-health-suite' ),
						$original_id,
						implode( ' -> ', array_keys( $seen ) ) . ' -> ' . $id
					)
				);
			}
			$seen[ $id ] = true;
			$id = $this->aliases[ $id ];
		}
		return $id;
	}

	/**
	 * Register a deferred provider when its service is requested.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The service identifier.
	 * @return void
	 * @throws ContainerException If provider registration or boot fails.
	 */
	private function register_deferred_provider( string $id ): void {
		$provider = $this->deferred_providers[ $id ];

		// Cache provides() result before iteration to handle generators
		// and prevent issues if provides() modifies state during registration.
		$provided_services = $provider->provides();

		// Remove all deferred entries for this provider BEFORE registration.
		// This prevents re-registration if register() or boot() throws.
		foreach ( $provided_services as $service ) {
			unset( $this->deferred_providers[ $service ] );
		}

		// Register the provider with error handling.
		try {
			$provider->register();
		} catch ( \Throwable $e ) {
			$this->log_error(
				get_class( $provider ),
				sprintf( 'Deferred registration failed: %s', $e->getMessage() )
			);
			throw ContainerException::provider_registration_failed( get_class( $provider ), $e );
		}

		// Boot if container is booted or currently booting.
		// This ensures deferred providers requested during the boot phase
		// of other providers are properly initialized.
		if ( $this->booted || $this->booting ) {
			try {
				$provider->boot();
			} catch ( \Throwable $e ) {
				$this->log_error(
					get_class( $provider ),
					sprintf( 'Deferred boot failed: %s', $e->getMessage() )
				);
				throw ContainerException::provider_boot_failed( get_class( $provider ), $e );
			}
		}
	}

	/**
	 * Flush all bindings and instances.
	 *
	 * Useful for testing.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->bindings          = array();
		$this->instances         = array();
		$this->aliases           = array();
		$this->providers         = array();
		$this->deferred_providers = array();
		$this->booted            = false;
		$this->resolution_stack  = array();
	}

	/**
	 * Get all registered bindings.
	 *
	 * Useful for debugging.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> List of binding identifiers.
	 */
	public function get_bindings(): array {
		return array_keys( $this->bindings );
	}

	/**
	 * Get all resolved instances.
	 *
	 * Useful for debugging.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> List of instance identifiers.
	 */
	public function get_instances(): array {
		return array_keys( $this->instances );
	}

	/**
	 * Check if the container has been booted.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if booted, false otherwise.
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * Make method - syntactic sugar for get().
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Service identifier.
	 * @return mixed The resolved service.
	 */
	public function make( string $id ) {
		return $this->get( $id );
	}

	/**
	 * Log a container error.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id      Service or provider identifier.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( string $id, string $message ): void {
		$log_message = sprintf(
			'WP Admin Health Suite - Container error [%s]: %s',
			$id,
			$message
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );
		}
	}
}
