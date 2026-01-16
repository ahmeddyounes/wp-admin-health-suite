<?php
/**
 * Media Service Provider
 *
 * Registers media services: Scanner, Safe Delete.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\SafeDeleteInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Media\Scanner;
use WPAdminHealth\Media\SafeDelete;
use WPAdminHealth\Media\DuplicateDetector;
use WPAdminHealth\Media\LargeFiles;
use WPAdminHealth\Media\AltTextChecker;
use WPAdminHealth\Media\ReferenceFinder;
use WPAdminHealth\Media\Exclusions;

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
 * @since 1.2.0 Added interface bindings for domain services.
 */
class MediaServiceProvider extends ServiceProvider {

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		ScannerInterface::class,
		DuplicateDetectorInterface::class,
		LargeFilesInterface::class,
		AltTextCheckerInterface::class,
		ReferenceFinderInterface::class,
		SafeDeleteInterface::class,
		ExclusionsInterface::class,
		'media.scanner',
		'media.duplicate_detector',
		'media.large_files',
		'media.alt_text_checker',
		'media.reference_finder',
		'media.safe_delete',
		'media.exclusions',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Exclusions first - no dependencies, required by other media services.
		$this->container->singleton(
			ExclusionsInterface::class,
			function () {
				return new Exclusions();
			}
		);
		$this->container->alias( 'media.exclusions', ExclusionsInterface::class );

		// Register Reference Finder with ConnectionInterface injection.
		$this->container->singleton(
			ReferenceFinderInterface::class,
			function ( $container ) {
				return new ReferenceFinder(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'media.reference_finder', ReferenceFinderInterface::class );

		// Register Safe Delete with ConnectionInterface injection.
		$this->container->singleton(
			SafeDeleteInterface::class,
			function ( $container ) {
				return new SafeDelete(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'media.safe_delete', SafeDeleteInterface::class );

		// Register Media Scanner with ExclusionsInterface and ConnectionInterface injection.
		$this->container->singleton(
			ScannerInterface::class,
			function ( $container ) {
				return new Scanner(
					$container->get( ExclusionsInterface::class ),
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'media.scanner', ScannerInterface::class );

		// Register Duplicate Detector with ConnectionInterface and ExclusionsInterface injection.
		$this->container->singleton(
			DuplicateDetectorInterface::class,
			function ( $container ) {
				return new DuplicateDetector(
					$container->get( ConnectionInterface::class ),
					$container->get( ExclusionsInterface::class )
				);
			}
		);
		$this->container->alias( 'media.duplicate_detector', DuplicateDetectorInterface::class );

		// Register Large Files with ConnectionInterface and ExclusionsInterface injection.
		$this->container->singleton(
			LargeFilesInterface::class,
			function ( $container ) {
				return new LargeFiles(
					$container->get( ConnectionInterface::class ),
					$container->get( ExclusionsInterface::class )
				);
			}
		);
		$this->container->alias( 'media.large_files', LargeFilesInterface::class );

		// Register Alt Text Checker with ConnectionInterface and ExclusionsInterface injection.
		$this->container->singleton(
			AltTextCheckerInterface::class,
			function ( $container ) {
				return new AltTextChecker(
					$container->get( ConnectionInterface::class ),
					$container->get( ExclusionsInterface::class )
				);
			}
		);
		$this->container->alias( 'media.alt_text_checker', AltTextCheckerInterface::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Media services don't need bootstrapping.
	}
}
