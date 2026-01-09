<?php
/**
 * Performance Service Provider
 *
 * Registers performance monitoring services.
 *
 * @package WPAdminHealth\Providers
 */

namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Performance\Query_Monitor;
use WPAdminHealth\Performance\Memory_Monitor;
use WPAdminHealth\Performance\Slow_Query_Analyzer;

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
 */
class PerformanceServiceProvider extends Service_Provider {

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
		'performance.query_monitor',
		'performance.memory_monitor',
		'performance.slow_query_analyzer',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register Query Monitor.
		$this->container->bind(
			'performance.query_monitor',
			function ( $container ) {
				if ( ! class_exists( Query_Monitor::class ) ) {
					return null;
				}

				$connection = $container->get( ConnectionInterface::class );

				$reflection  = new \ReflectionClass( Query_Monitor::class );
				$constructor = $reflection->getConstructor();

				if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
					return new Query_Monitor( $connection );
				}

				return new Query_Monitor();
			}
		);

		// Register Memory Monitor.
		$this->container->bind(
			'performance.memory_monitor',
			function () {
				if ( ! class_exists( Memory_Monitor::class ) ) {
					return null;
				}

				return new Memory_Monitor();
			}
		);

		// Register Slow Query Analyzer.
		$this->container->bind(
			'performance.slow_query_analyzer',
			function ( $container ) {
				if ( ! class_exists( Slow_Query_Analyzer::class ) ) {
					return null;
				}

				$connection = $container->get( ConnectionInterface::class );
				$cache      = $container->get( CacheInterface::class );

				$reflection  = new \ReflectionClass( Slow_Query_Analyzer::class );
				$constructor = $reflection->getConstructor();

				if ( $constructor && $constructor->getNumberOfParameters() > 0 ) {
					return new Slow_Query_Analyzer( $connection, $cache );
				}

				return new Slow_Query_Analyzer();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Performance services don't need bootstrapping.
	}
}
