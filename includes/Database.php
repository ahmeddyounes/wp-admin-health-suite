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
	 * @param string $version Plugin version.
	 */
	public function __construct( $version ) {
		$this->version = $version;

		$this->init_hooks();
	}

	/**
	 * Initialize database hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Hook for database initialization.
		do_action( 'wpha_database_init' );
	}
}
