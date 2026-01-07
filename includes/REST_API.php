<?php
/**
 * REST API Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API class for handling REST API endpoints.
 */
class REST_API {

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
	 * Initialize REST API hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Hook for REST API initialization.
		do_action( 'wpha_rest_api_init' );
	}
}
