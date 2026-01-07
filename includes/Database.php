<?php
/**
 * Database Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database class for handling database operations.
 *
 * @since 1.0.0
 */
class Database {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version Plugin version.
	 */
	public function __construct( $version ) {
		$this->version = $version;

		$this->init_hooks();
	}

	/**
	 * Initialize database hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		/**
		 * Fires after database initialization.
		 *
		 * @since 1.0.0
		 *
		 * @hook wpha_database_init
		 */
		do_action( 'wpha_database_init' );
	}
}
