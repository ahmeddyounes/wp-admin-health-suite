<?php
/**
 * Installer Service Provider
 *
 * Registers the Installer service and handles upgrades.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Installer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class InstallerServiceProvider
 *
 * Registers installation and upgrade services.
 *
 * @since 1.2.0
 */
class InstallerServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		Installer::class,
		'installer',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Installer uses static methods, register the class reference.
		$this->container->instance( Installer::class, Installer::class );
		$this->container->alias( 'installer', Installer::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Check for upgrades during boot.
		Installer::maybe_upgrade();

		// Register multisite hooks for installing on new sites.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', array( $this, 'on_new_site_created' ), 10, 2 );
		}
	}

	/**
	 * Handle new site creation in multisite.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Site $new_site New site object.
	 * @param array    $args     Arguments for the initialization.
	 * @return void
	 */
	public function on_new_site_created( $new_site, $args ): void {
		Installer::install_on_new_site( $new_site->blog_id );
	}
}
