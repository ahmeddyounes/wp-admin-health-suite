<?php
/**
 * Media Service Provider
 *
 * Registers media services: Scanner, Safe Delete.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Media\Scanner;
use WPAdminHealth\Media\Safe_Delete;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MediaServiceProvider
 *
 * Registers media scanning and management services.
 *
 * @since 1.1.0
 */
class MediaServiceProvider extends Service_Provider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		ScannerInterface::class,
		'media.scanner',
		'media.safe_delete',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Media Scanner.
		$this->container->bind(
			ScannerInterface::class,
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );
				$cache      = $container->get( CacheInterface::class );

				// Check if Scanner supports constructor injection.
				if ( class_exists( Scanner::class ) ) {
					$reflection  = new \ReflectionClass( Scanner::class );
					$constructor = $reflection->getConstructor();

					if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
						return new Scanner( $connection, $cache );
					}

					return new Scanner();
				}

				return null;
			}
		);

		$this->container->alias( 'media.scanner', ScannerInterface::class );

		// Register Safe Delete.
		$this->container->bind(
			'media.safe_delete',
			function ( $container ) {
				$connection = $container->get( ConnectionInterface::class );

				// Check if Safe_Delete supports constructor injection.
				if ( class_exists( Safe_Delete::class ) ) {
					$reflection  = new \ReflectionClass( Safe_Delete::class );
					$constructor = $reflection->getConstructor();

					if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
						return new Safe_Delete( $connection );
					}

					return new Safe_Delete();
				}

				return null;
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Media services don't need bootstrapping.
	}
}
