<?php
/**
 * Not Found Exception
 *
 * PSR-11 compatible exception for missing container entries.
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
 * Class NotFoundException
 *
 * Exception thrown when a requested service is not found in the container.
 * Compatible with PSR-11 NotFoundExceptionInterface.
 *
 * @since 1.1.0
 */
class NotFoundException extends Exception {

	/**
	 * Create a new NotFoundException.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The identifier that was not found.
	 */
	public function __construct( string $id ) {
		parent::__construct(
			sprintf(
				/* translators: %s: Service identifier */
				__( 'Service "%s" not found in container.', 'wp-admin-health-suite' ),
				$id
			)
		);
	}
}
