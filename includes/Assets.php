<?php
/**
 * Assets Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Assets class for handling CSS and JavaScript assets.
 */
class Assets {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Constructor.
	 *
	 * @param string $version Plugin version.
	 * @param string $plugin_url Plugin URL.
	 */
	public function __construct( $version, $plugin_url ) {
		$this->version    = $version;
		$this->plugin_url = $plugin_url;

		$this->init_hooks();
	}

	/**
	 * Initialize assets hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Hook for assets initialization.
		do_action( 'wpha_assets_init' );
	}
}
