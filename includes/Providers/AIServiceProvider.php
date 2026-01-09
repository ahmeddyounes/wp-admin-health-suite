<?php
/**
 * AI Service Provider
 *
 * Registers AI-related services including recommendations and one-click fixes.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\AI\OneClickFix;
use WPAdminHealth\AI\Recommendations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AIServiceProvider
 *
 * Registers AI recommendation and automation services.
 *
 * @since 1.2.0
 */
class AIServiceProvider extends ServiceProvider {

	/**
	 * Whether this provider should be deferred.
	 *
	 * @var bool
	 */
	protected bool $deferred = true;

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		'ai.one_click_fix',
		'ai.recommendations',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register One Click Fix with all dependencies.
		$this->container->bind(
			'ai.one_click_fix',
			function ( $container ) {
				if ( ! class_exists( OneClickFix::class ) ) {
					return null;
				}

				return new OneClickFix(
					$container->get( TransientsCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( OptimizerInterface::class )
				);
			}
		);

		// Register Recommendations with all dependencies.
		$this->container->bind(
			'ai.recommendations',
			function ( $container ) {
				if ( ! class_exists( Recommendations::class ) ) {
					return null;
				}

				return new Recommendations(
					$container->get( AnalyzerInterface::class ),
					$container->get( RevisionsManagerInterface::class ),
					$container->get( TransientsCleanerInterface::class ),
					$container->get( OrphanedCleanerInterface::class ),
					$container->get( TrashCleanerInterface::class ),
					$container->get( ScannerInterface::class ),
					$container->get( QueryMonitorInterface::class )
				);
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// AI services don't need bootstrapping.
	}
}
