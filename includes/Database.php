<?php
/**
 * Database Class (Legacy)
 *
 * @package WPAdminHealth
 *
 * @deprecated 1.3.0 This class is deprecated and will be removed in a future release.
 *                   Database operations are now handled via {@see \WPAdminHealth\Providers\DatabaseServiceProvider}
 *                   and related service classes in the `Database` namespace.
 *                   The `wpha_database_init` hook fired by this class is no longer triggered at runtime.
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
 * @deprecated 1.3.0 Use {@see \WPAdminHealth\Providers\DatabaseServiceProvider} and related
 *                   service classes instead. This class is no longer instantiated by the plugin.
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
