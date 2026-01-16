<?php
/**
 * Performance Service Provider
 *
 * Registers performance monitoring services.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\PluginProfilerInterface;
use WPAdminHealth\Performance\QueryMonitor;
use WPAdminHealth\Performance\AutoloadAnalyzer;
use WPAdminHealth\Performance\PluginProfiler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class PerformanceServiceProvider
 *
 * Registers performance monitoring and analysis services.
 *
 * @since 1.1.0
 * @since 1.2.0 Added interface bindings for domain services.
 */
class PerformanceServiceProvider extends ServiceProvider {

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
		AutoloadAnalyzerInterface::class,
		QueryMonitorInterface::class,
		PluginProfilerInterface::class,
		'performance.query_monitor',
		'performance.autoload_analyzer',
		'performance.plugin_profiler',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Query Monitor with interface binding.
		$this->container->singleton(
			QueryMonitorInterface::class,
			function ( $container ) {
				return new QueryMonitor(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'performance.query_monitor', QueryMonitorInterface::class );

		// Register Autoload Analyzer with interface binding.
		$this->container->singleton(
			AutoloadAnalyzerInterface::class,
			function ( $container ) {
				return new AutoloadAnalyzer(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'performance.autoload_analyzer', AutoloadAnalyzerInterface::class );

		// Register Plugin Profiler with interface binding.
		$this->container->singleton(
			PluginProfilerInterface::class,
			function ( $container ) {
				return new PluginProfiler(
					$container->get( ConnectionInterface::class )
				);
			}
		);
		$this->container->alias( 'performance.plugin_profiler', PluginProfilerInterface::class );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Performance services don't need bootstrapping.
	}
}
