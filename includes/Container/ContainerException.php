<?php
/**
 * Container Exception
 *
 * PSR-11 compatible exception for container errors.
 *
 * @package WPAdminHealth\Container
 */

namespace WPAdminHealth\Container;

use Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ContainerException
 *
 * Exception thrown when an error occurs within the container.
 * Compatible with PSR-11 ContainerExceptionInterface.
 *
 * @since 1.1.0
 */
class ContainerException extends Exception {

	/**
	 * Create a new ContainerException.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $message  The exception message.
	 * @param int        $code     The exception code (default 0).
	 * @param \Throwable $previous The previous exception for chaining (optional).
	 */
	public function __construct( string $message, int $code = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Create an exception for provider registration failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $provider_class The provider class name.
	 * @param \Throwable $previous       The original exception.
	 * @return self
	 */
	public static function provider_registration_failed( string $provider_class, \Throwable $previous ): self {
		return new self(
			sprintf(
				/* translators: 1: Provider class name, 2: Error message */
				__( 'Failed to register service provider "%1$s": %2$s', 'wp-admin-health-suite' ),
				$provider_class,
				$previous->getMessage()
			),
			0,
			$previous
		);
	}

	/**
	 * Create an exception for provider boot failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $provider_class The provider class name.
	 * @param \Throwable $previous       The original exception.
	 * @return self
	 */
	public static function provider_boot_failed( string $provider_class, \Throwable $previous ): self {
		return new self(
			sprintf(
				/* translators: 1: Provider class name, 2: Error message */
				__( 'Failed to boot service provider "%1$s": %2$s', 'wp-admin-health-suite' ),
				$provider_class,
				$previous->getMessage()
			),
			0,
			$previous
		);
	}

	/**
	 * Create an exception for resolver failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $service_id The service identifier.
	 * @param \Throwable $previous   The original exception.
	 * @return self
	 */
	public static function resolver_failed( string $service_id, \Throwable $previous ): self {
		return new self(
			sprintf(
				/* translators: 1: Service ID, 2: Error message */
				__( 'Failed to resolve service "%1$s": %2$s', 'wp-admin-health-suite' ),
				$service_id,
				$previous->getMessage()
			),
			0,
			$previous
		);
	}

	/**
	 * Create an exception for auto-wiring failure.
	 *
	 * @since 1.1.0
	 *
	 * @param string     $class_name The class name that failed.
	 * @param \Throwable $previous   The original exception.
	 * @return self
	 */
	public static function auto_wire_failed( string $class_name, \Throwable $previous ): self {
		return new self(
			sprintf(
				/* translators: 1: Class name, 2: Error message */
				__( 'Failed to auto-wire class "%1$s": %2$s', 'wp-admin-health-suite' ),
				$class_name,
				$previous->getMessage()
			),
			0,
			$previous
		);
	}
}
