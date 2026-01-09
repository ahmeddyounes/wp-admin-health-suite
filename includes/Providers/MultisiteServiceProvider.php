<?php
/**
 * Multisite Service Provider
 *
 * Registers the Multisite service.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Multisite;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MultisiteServiceProvider
 *
 * Registers multisite-specific services.
 *
 * @since 1.2.0
 */
class MultisiteServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		Multisite::class,
		'multisite',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Multisite as singleton.
		$this->container->singleton(
			Multisite::class,
			function () {
				return new Multisite();
			}
		);
		$this->container->alias( 'multisite', Multisite::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Only initialize if multisite is enabled.
		if ( is_multisite() ) {
			$multisite = $this->container->get( Multisite::class );
			$multisite->init();
		}
	}
}
