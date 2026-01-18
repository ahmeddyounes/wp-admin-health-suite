<?php
/**
 * Integration Factory
 *
 * Factory for creating integration instances via the container.
 *
 * @package WPAdminHealth\Integrations
 */

namespace WPAdminHealth\Integrations;

use WPAdminHealth\Contracts\IntegrationFactoryInterface;
use WPAdminHealth\Contracts\IntegrationInterface;
use WPAdminHealth\Container\ContainerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class IntegrationFactory
 *
 * Creates integration instances by resolving them from the container.
 * This enables dependency injection for integrations while maintaining
 * backward compatibility with the existing IntegrationManager API.
 *
 * @since 1.1.0
 */
class IntegrationFactory implements IntegrationFactoryInterface {

	/**
	 * The container instance.
	 *
	 * @var ContainerInterface
	 */
	private ContainerInterface $container;

	/**
	 * Mapping of integration IDs to container service identifiers.
	 *
	 * @var array<string, string>
	 */
	private array $service_map = array();

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param ContainerInterface $container The container instance.
	 */
	public function __construct( ContainerInterface $container ) {
		$this->container = $container;
	}

	/**
	 * Register an integration's container service identifier.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id              Integration identifier (e.g., 'woocommerce').
	 * @param string $service_id      Container service identifier (class name or alias).
	 * @return self
	 */
	public function register( string $id, string $service_id ): self {
		$this->service_map[ $id ] = $service_id;
		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function create( string $id ): ?IntegrationInterface {
		if ( ! $this->can_create( $id ) ) {
			return null;
		}

		$service_id = $this->service_map[ $id ];

		if ( ! $this->container->has( $service_id ) ) {
			return null;
		}

		try {
			$integration = $this->container->get( $service_id );

			if ( $integration instanceof IntegrationInterface ) {
				return $integration;
			}

			return null;
		} catch ( \Throwable $e ) {
			$this->log_error( $id, $e->getMessage() );
			return null;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function can_create( string $id ): bool {
		return isset( $this->service_map[ $id ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_available_ids(): array {
		return array_keys( $this->service_map );
	}

	/**
	 * Get the container service identifier for an integration.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id Integration identifier.
	 * @return string|null The service identifier or null if not registered.
	 */
	public function get_service_id( string $id ): ?string {
		return $this->service_map[ $id ] ?? null;
	}

	/**
	 * Log a factory error.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id      Integration ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( string $id, string $message ): void {
		$log_message = sprintf(
			'WP Admin Health Suite - IntegrationFactory error [%s]: %s',
			$id,
			$message
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $log_message );
		}
	}
}
