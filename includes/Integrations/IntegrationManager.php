<?php
/**
 * Integration Manager
 *
 * Centralized registration and management of integrations.
 *
 * @package WPAdminHealth\Integrations
 */

namespace WPAdminHealth\Integrations;

use WPAdminHealth\Contracts\IntegrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class IntegrationManager
 *
 * Manages all third-party plugin integrations.
 * Handles registration, discovery, dependency resolution, and capability queries.
 *
 * @since 1.1.0
 */
class IntegrationManager {

	/**
	 * Registered integrations.
	 *
	 * @var array<string, IntegrationInterface>
	 */
	private array $integrations = array();

	/**
	 * Whether integrations have been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Whether integrations have been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Capability index for fast lookups.
	 *
	 * @var array<string, array<string>>
	 */
	private array $capability_index = array();

	/**
	 * Integration class map for auto-discovery.
	 *
	 * @var array<string, string>
	 */
	private array $builtin_integrations = array(
		'woocommerce'   => 'WPAdminHealth\\Integrations\\WooCommerce',
		'elementor'     => 'WPAdminHealth\\Integrations\\Elementor',
		'acf'           => 'WPAdminHealth\\Integrations\\Acf',
		'multilingual'  => 'WPAdminHealth\\Integrations\\Multilingual',
	);

	/**
	 * Register an integration.
	 *
	 * If an integration with the same ID is already registered, it will be replaced.
	 *
	 * @since 1.1.0
	 *
	 * @param IntegrationInterface $integration Integration instance.
	 * @return self
	 */
	public function register( IntegrationInterface $integration ): self {
		$id = $integration->get_id();

		if ( isset( $this->integrations[ $id ] ) ) {
			// Deactivate and deindex existing integration before replacing.
			$existing = $this->integrations[ $id ];

			if ( $existing->is_initialized() ) {
				$existing->deactivate();
			}

			$this->remove_capability_index_entries( $id );
		}

		$this->integrations[ $id ] = $integration;
		$this->index_capabilities( $integration );

		return $this;
	}

	/**
	 * Discover and register built-in integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public function discover(): self {
		if ( $this->loaded ) {
			return $this;
		}

		/**
		 * Fires before built-in integrations are discovered.
		 *
		 * @since 1.1.0
		 */
		do_action( 'wpha_before_integration_discovery' );

		// Register built-in integrations.
		foreach ( $this->builtin_integrations as $id => $class ) {
			if ( class_exists( $class ) && ! isset( $this->integrations[ $id ] ) ) {
				try {
					$integration = new $class();
					if ( $integration instanceof IntegrationInterface ) {
						$this->register( $integration );
					}
				} catch ( \Throwable $e ) {
					$this->log_error( $id, $e->getMessage() );
				}
			}
		}

		/**
		 * Fires to allow third-party integrations to register.
		 *
		 * @since 1.1.0
		 *
		 * @param IntegrationManager $manager The integration manager instance.
		 */
		do_action( 'wpha_register_integrations', $this );

		$this->loaded = true;

		/**
		 * Fires after all integrations have been registered.
		 *
		 * @since 1.1.0
		 *
		 * @param IntegrationManager $manager The integration manager instance.
		 */
		do_action( 'wpha_integrations_loaded', $this );

		return $this;
	}

	/**
	 * Initialize all available integrations.
	 *
	 * Respects dependencies and priority order.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public function init(): self {
		if ( $this->initialized ) {
			return $this;
		}

		if ( ! $this->loaded ) {
			$this->discover();
		}

		// Sort by priority and resolve dependencies.
		try {
			$sorted = $this->resolve_dependencies();
		} catch ( \RuntimeException $e ) {
			$this->log_error( 'dependency_resolution', $e->getMessage() );
			$sorted = $this->sort_by_priority( array_values( $this->integrations ) );
		}

		foreach ( $sorted as $integration ) {
			if ( $integration->is_available() ) {
				if ( ! $this->dependencies_met( $integration ) ) {
					continue;
				}

				try {
					$integration->init();
				} catch ( \Throwable $e ) {
					$this->log_error( $integration->get_id(), $e->getMessage() );
				}
			}
		}

		$this->initialized = true;

		return $this;
	}

	/**
	 * Deactivate all integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public function deactivate_all(): self {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_initialized() ) {
				$integration->deactivate();
			}
		}

		$this->initialized = false;

		return $this;
	}

	/**
	 * Get an integration by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Integration ID.
	 * @return IntegrationInterface|null Integration instance or null if not found.
	 */
	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Check if an integration is registered.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Integration ID.
	 * @return bool True if registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->integrations[ $id ] );
	}

	/**
	 * Get all registered integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, IntegrationInterface> All integrations.
	 */
	public function all(): array {
		return $this->integrations;
	}

	/**
	 * Get all available (active) integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, IntegrationInterface> Available integrations.
	 */
	public function get_available(): array {
		return array_filter(
			$this->integrations,
			fn( IntegrationInterface $integration ) => $integration->is_available()
		);
	}

	/**
	 * Get all initialized integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, IntegrationInterface> Initialized integrations.
	 */
	public function get_initialized(): array {
		return array_filter(
			$this->integrations,
			fn( IntegrationInterface $integration ) => $integration->is_initialized()
		);
	}

	/**
	 * Get integrations by capability.
	 *
	 * @since 1.1.0
	 *
	 * @param string $capability Capability identifier.
	 * @return array<string, IntegrationInterface> Integrations with the capability.
	 */
	public function get_by_capability( string $capability ): array {
		if ( ! $this->loaded ) {
			$this->discover();
		}

		if ( ! isset( $this->capability_index[ $capability ] ) ) {
			return array();
		}

		$integrations = array();

		foreach ( $this->capability_index[ $capability ] as $id ) {
			if ( isset( $this->integrations[ $id ] ) ) {
				$integration = $this->integrations[ $id ];

				// Only return integrations that can actually service the capability.
				if ( ! $integration->is_available() || ! $integration->is_compatible() ) {
					continue;
				}

				$integrations[ $id ] = $integration;
			}
		}

		return $integrations;
	}

	/**
	 * Check if any integration has a capability.
	 *
	 * @since 1.1.0
	 *
	 * @param string $capability Capability identifier.
	 * @return bool True if any available, compatible integration has the capability.
	 */
	public function has_capability( string $capability ): bool {
		return ! empty( $this->get_by_capability( $capability ) );
	}

	/**
	 * Get all cleanup data from all initialized integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, int>> Cleanup data keyed by integration ID.
	 */
	public function get_all_cleanup_data(): array {
		$data = array();

		foreach ( $this->get_initialized() as $id => $integration ) {
			if ( method_exists( $integration, 'get_cleanup_data' ) ) {
				$cleanup_data = $integration->get_cleanup_data();
				if ( ! empty( $cleanup_data ) ) {
					$data[ $id ] = $cleanup_data;
				}
			}
		}

		return $data;
	}

	/**
	 * Get all performance insights from all initialized integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<array{integration: string, type: string, category: string, title: string, description: string, action: string, severity: string}> All insights.
	 */
	public function get_all_performance_insights(): array {
		$insights = array();

		foreach ( $this->get_initialized() as $id => $integration ) {
			if ( method_exists( $integration, 'get_performance_insights' ) ) {
				$integration_insights = $integration->get_performance_insights();
				foreach ( $integration_insights as $insight ) {
					$insight['integration'] = $id;
					$insights[]             = $insight;
				}
			}
		}

		// Sort by severity (high, medium, low).
		$severity_order = array(
			'high'   => 0,
			'medium' => 1,
			'low'    => 2,
		);

		usort(
			$insights,
			function ( $a, $b ) use ( $severity_order ) {
				$a_severity = $severity_order[ $a['severity'] ?? 'low' ] ?? 2;
				$b_severity = $severity_order[ $b['severity'] ?? 'low' ] ?? 2;
				return $a_severity <=> $b_severity;
			}
		);

		return $insights;
	}

	/**
	 * Get integration info for display.
	 *
	 * @since 1.1.0
	 *
	 * @return array<array{id: string, name: string, available: bool, compatible: bool, initialized: bool, version: string|null, min_version: string, capabilities: array<string>}> Integration info.
	 */
	public function get_integration_info(): array {
		$info = array();

		foreach ( $this->integrations as $id => $integration ) {
			$info[] = array(
				'id'           => $id,
				'name'         => $integration->get_name(),
				'available'    => $integration->is_available(),
				'compatible'   => $integration->is_compatible(),
				'initialized'  => $integration->is_initialized(),
				'version'      => $integration->get_current_version(),
				'min_version'  => $integration->get_min_version(),
				'capabilities' => $integration->get_capabilities(),
			);
		}

		return $info;
	}

	/**
	 * Count integrations.
	 *
	 * @since 1.1.0
	 *
	 * @return int Number of registered integrations.
	 */
	public function count(): int {
		return count( $this->integrations );
	}

	/**
	 * Resolve integration dependencies.
	 *
	 * Uses topological sort to ensure dependencies are loaded first.
	 *
	 * @since 1.1.0
	 *
	 * @return array<IntegrationInterface> Sorted integrations.
	 * @throws \RuntimeException If circular dependency is detected.
	 */
	private function resolve_dependencies(): array {
		$sorted    = array();
		$visiting  = array();
		$visited   = array();
		$priority  = array();

		// Build priority map.
		foreach ( $this->integrations as $id => $integration ) {
			$priority[ $id ] = $integration->get_priority();
		}

		// Sort by priority first.
		$ids = array_keys( $this->integrations );
		usort(
			$ids,
			fn( $a, $b ) => ( $priority[ $a ] ?? 10 ) <=> ( $priority[ $b ] ?? 10 )
		);

		// Topological sort with dependency resolution.
		foreach ( $ids as $id ) {
			if ( ! isset( $visited[ $id ] ) ) {
				$this->visit_dependency( $id, $visiting, $visited, $sorted );
			}
		}

		return $sorted;
	}

	/**
	 * Visit a node in dependency resolution (DFS).
	 *
	 * @since 1.1.0
	 *
	 * @param string                     $id       Integration ID.
	 * @param array<string, bool>        $visiting Currently visiting nodes.
	 * @param array<string, bool>        $visited  Already visited nodes.
	 * @param array<IntegrationInterface> $sorted   Sorted result array.
	 * @return void
	 * @throws \RuntimeException If circular dependency is detected.
	 */
	private function visit_dependency( string $id, array &$visiting, array &$visited, array &$sorted ): void {
		if ( isset( $visiting[ $id ] ) ) {
			throw new \RuntimeException(
				sprintf( 'Circular dependency detected in integration: %s', $id )
			);
		}

		if ( isset( $visited[ $id ] ) ) {
			return;
		}

		if ( ! isset( $this->integrations[ $id ] ) ) {
			return;
		}

		$visiting[ $id ] = true;
		$integration     = $this->integrations[ $id ];

		foreach ( $integration->get_dependencies() as $dependency_id ) {
			$this->visit_dependency( $dependency_id, $visiting, $visited, $sorted );
		}

		unset( $visiting[ $id ] );
		$visited[ $id ] = true;
		$sorted[]       = $integration;
	}

	/**
	 * Index integration capabilities for fast lookups.
	 *
	 * @since 1.1.0
	 *
	 * @param IntegrationInterface $integration Integration to index.
	 * @return void
	 */
	private function index_capabilities( IntegrationInterface $integration ): void {
		$id = $integration->get_id();

		foreach ( $integration->get_capabilities() as $capability ) {
			if ( ! isset( $this->capability_index[ $capability ] ) ) {
				$this->capability_index[ $capability ] = array();
			}

			if ( ! in_array( $id, $this->capability_index[ $capability ], true ) ) {
				$this->capability_index[ $capability ][] = $id;
			}
		}
	}

	/**
	 * Remove any capability index entries for an integration ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string $integration_id Integration ID.
	 * @return void
	 */
	private function remove_capability_index_entries( string $integration_id ): void {
		foreach ( $this->capability_index as $capability => $integration_ids ) {
			$this->capability_index[ $capability ] = array_values(
				array_filter(
					$integration_ids,
					fn( string $id ) => $id !== $integration_id
				)
			);

			if ( empty( $this->capability_index[ $capability ] ) ) {
				unset( $this->capability_index[ $capability ] );
			}
		}
	}

	/**
	 * Sort integrations by priority.
	 *
	 * @since 1.1.0
	 *
	 * @param array<IntegrationInterface> $integrations Integrations to sort.
	 * @return array<IntegrationInterface> Sorted integrations.
	 */
	private function sort_by_priority( array $integrations ): array {
		usort(
			$integrations,
			fn( IntegrationInterface $a, IntegrationInterface $b ) => $a->get_priority() <=> $b->get_priority()
		);

		return $integrations;
	}

	/**
	 * Check if an integration's dependencies are met.
	 *
	 * Ensures dependency integrations exist, are available, compatible, and initialized.
	 *
	 * @since 1.1.0
	 *
	 * @param IntegrationInterface $integration Integration instance.
	 * @return bool True if dependencies are met.
	 */
	private function dependencies_met( IntegrationInterface $integration ): bool {
		foreach ( $integration->get_dependencies() as $dependency_id ) {
			$dependency = $this->integrations[ $dependency_id ] ?? null;

			if ( null === $dependency ) {
				$this->log_error(
					$integration->get_id(),
					sprintf( 'Missing integration dependency "%s".', $dependency_id )
				);
				return false;
			}

			if ( ! $dependency->is_available() || ! $dependency->is_compatible() || ! $dependency->is_initialized() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Log an integration error.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id      Integration ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( string $id, string $message ): void {
		$log_message = sprintf(
			'WP Admin Health Suite - Integration error [%s]: %s',
			$id,
			$message
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );
		}
	}

	/**
	 * Reset the manager state.
	 *
	 * Useful for testing.
	 *
	 * @since 1.1.0
	 *
	 * @return self
	 */
	public function reset(): self {
		$this->deactivate_all();
		$this->integrations     = array();
		$this->capability_index = array();
		$this->loaded           = false;
		$this->initialized      = false;

		return $this;
	}

	/**
	 * Register a custom built-in integration class.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id    Integration ID.
	 * @param string $class Fully qualified class name.
	 * @return self
	 */
	public function register_builtin_class( string $id, string $class ): self {
		$this->builtin_integrations[ $id ] = $class;
		return $this;
	}
}
