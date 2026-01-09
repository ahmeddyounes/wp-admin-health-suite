<?php
/**
 * Container + Cache Integration Tests (Standalone)
 *
 * Tests for integration between the DI container and cache abstraction.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Integration
 */

namespace WPAdminHealth\Tests\UnitStandalone\Integration;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Cache\NullCache;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test service that uses cache.
 */
class Cached_Service {

	/**
	 * Cache instance.
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Call counter for testing.
	 *
	 * @var int
	 */
	public int $compute_calls = 0;

	/**
	 * Constructor.
	 *
	 * @param CacheInterface $cache Cache instance.
	 */
	public function __construct( CacheInterface $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Get expensive data with caching.
	 *
	 * @return string
	 */
	public function get_expensive_data(): string {
		return $this->cache->remember(
			'expensive_data',
			function() {
				++$this->compute_calls;
				return 'computed_' . $this->compute_calls;
			},
			300
		);
	}

	/**
	 * Clear cached data.
	 *
	 * @return bool
	 */
	public function clear_cache(): bool {
		return $this->cache->delete( 'expensive_data' );
	}

	/**
	 * Get cache instance.
	 *
	 * @return CacheInterface
	 */
	public function get_cache(): CacheInterface {
		return $this->cache;
	}
}

/**
 * Container + Cache integration test class.
 */
class ContainerCacheIntegrationTest extends StandaloneTestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->container = new Container();
	}

	/**
	 * Test cache interface can be bound to MemoryCache.
	 */
	public function test_cache_interface_bound_to_memory_cache(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );

		$cache = $this->container->get( CacheInterface::class );

		$this->assertInstanceOf( CacheInterface::class, $cache );
		$this->assertInstanceOf( MemoryCache::class, $cache );
	}

	/**
	 * Test cache interface can be bound to NullCache.
	 */
	public function test_cache_interface_bound_to_null_cache(): void {
		$this->container->singleton( CacheInterface::class, fn() => new NullCache() );

		$cache = $this->container->get( CacheInterface::class );

		$this->assertInstanceOf( CacheInterface::class, $cache );
		$this->assertInstanceOf( NullCache::class, $cache );
	}

	/**
	 * Test service with injected cache dependency.
	 */
	public function test_service_with_cache_dependency(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service = $this->container->get( Cached_Service::class );

		$this->assertInstanceOf( Cached_Service::class, $service );
		$this->assertInstanceOf( MemoryCache::class, $service->get_cache() );
	}

	/**
	 * Test cache prevents recomputation.
	 */
	public function test_cache_prevents_recomputation(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service = $this->container->get( Cached_Service::class );

		// First call computes.
		$result1 = $service->get_expensive_data();
		$this->assertEquals( 'computed_1', $result1 );
		$this->assertEquals( 1, $service->compute_calls );

		// Second call returns cached.
		$result2 = $service->get_expensive_data();
		$this->assertEquals( 'computed_1', $result2 );
		$this->assertEquals( 1, $service->compute_calls );

		// Third call still cached.
		$result3 = $service->get_expensive_data();
		$this->assertEquals( 'computed_1', $result3 );
		$this->assertEquals( 1, $service->compute_calls );
	}

	/**
	 * Test null cache always recomputes.
	 */
	public function test_null_cache_always_recomputes(): void {
		$this->container->singleton( CacheInterface::class, fn() => new NullCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service = $this->container->get( Cached_Service::class );

		// Each call computes because NullCache never stores.
		$result1 = $service->get_expensive_data();
		$this->assertEquals( 'computed_1', $result1 );
		$this->assertEquals( 1, $service->compute_calls );

		$result2 = $service->get_expensive_data();
		$this->assertEquals( 'computed_2', $result2 );
		$this->assertEquals( 2, $service->compute_calls );

		$result3 = $service->get_expensive_data();
		$this->assertEquals( 'computed_3', $result3 );
		$this->assertEquals( 3, $service->compute_calls );
	}

	/**
	 * Test singleton service shares cache state.
	 */
	public function test_singleton_service_shares_cache_state(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		// Get service instance and populate cache.
		$service1 = $this->container->get( Cached_Service::class );
		$service1->get_expensive_data();

		// Get service again - should be same instance.
		$service2 = $this->container->get( Cached_Service::class );

		$this->assertSame( $service1, $service2 );
		$this->assertEquals( 1, $service2->compute_calls );
	}

	/**
	 * Test multiple services share cache backend.
	 */
	public function test_multiple_services_share_cache_backend(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->bind(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		// Get two separate service instances (using bind, not singleton).
		$service1 = $this->container->get( Cached_Service::class );
		$service2 = $this->container->get( Cached_Service::class );

		// Services are different instances.
		$this->assertNotSame( $service1, $service2 );

		// But they share the same cache backend.
		$this->assertSame( $service1->get_cache(), $service2->get_cache() );

		// First service populates cache.
		$service1->get_expensive_data();
		$this->assertEquals( 1, $service1->compute_calls );

		// Second service gets cached data (its own counter stays at 0).
		$result = $service2->get_expensive_data();
		$this->assertEquals( 'computed_1', $result );
		$this->assertEquals( 0, $service2->compute_calls );
	}

	/**
	 * Test cache clearing from service.
	 */
	public function test_cache_clearing_from_service(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service = $this->container->get( Cached_Service::class );

		// Populate cache.
		$service->get_expensive_data();
		$this->assertEquals( 1, $service->compute_calls );

		// Clear cache.
		$service->clear_cache();

		// Next call recomputes.
		$result = $service->get_expensive_data();
		$this->assertEquals( 'computed_2', $result );
		$this->assertEquals( 2, $service->compute_calls );
	}

	/**
	 * Test swapping cache implementation via rebinding.
	 */
	public function test_swap_cache_implementation(): void {
		// Start with MemoryCache.
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );

		$cache1 = $this->container->get( CacheInterface::class );
		$this->assertInstanceOf( MemoryCache::class, $cache1 );

		// Rebind to NullCache.
		$this->container->singleton( CacheInterface::class, fn() => new NullCache() );

		$cache2 = $this->container->get( CacheInterface::class );
		$this->assertInstanceOf( NullCache::class, $cache2 );
		$this->assertNotSame( $cache1, $cache2 );
	}

	/**
	 * Test cache isolation after container flush.
	 */
	public function test_cache_isolation_after_flush(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service1 = $this->container->get( Cached_Service::class );
		$service1->get_expensive_data();
		$this->assertEquals( 1, $service1->compute_calls );

		// Flush container and re-register.
		$this->container->flush();
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		// New service has fresh cache (no data from previous).
		$service2 = $this->container->get( Cached_Service::class );
		$result = $service2->get_expensive_data();
		$this->assertEquals( 'computed_1', $result ); // Starts fresh.
		$this->assertEquals( 1, $service2->compute_calls );

		$this->assertNotSame( $service1, $service2 );
	}

	/**
	 * Test auto-wiring with cache dependency.
	 */
	public function test_auto_wire_with_cache_dependency(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );

		// Auto-wire should resolve CacheInterface dependency.
		$service = $this->container->get( Cached_Service::class );

		$this->assertInstanceOf( Cached_Service::class, $service );
		$this->assertInstanceOf( MemoryCache::class, $service->get_cache() );
	}

	/**
	 * Test service provider registers cache dependency.
	 */
	public function test_service_provider_registers_cache(): void {
		$provider = new class( $this->container ) extends \WPAdminHealth\Container\ServiceProvider {
			public function register(): void {
				$this->singleton( CacheInterface::class, fn() => new MemoryCache() );
				$this->singleton(
					Cached_Service::class,
					fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
				);
			}
		};

		$this->container->register( $provider );

		$service = $this->container->get( Cached_Service::class );
		$this->assertInstanceOf( Cached_Service::class, $service );
		$this->assertInstanceOf( MemoryCache::class, $service->get_cache() );
	}

	/**
	 * Test memory cache stats through container.
	 */
	public function test_memory_cache_stats_through_container(): void {
		$this->container->singleton( CacheInterface::class, fn() => new MemoryCache() );
		$this->container->singleton(
			Cached_Service::class,
			fn( $c ) => new Cached_Service( $c->get( CacheInterface::class ) )
		);

		$service = $this->container->get( Cached_Service::class );

		// First call - cache miss.
		$service->get_expensive_data();

		// Second call - cache hit.
		$service->get_expensive_data();

		/** @var MemoryCache $cache */
		$cache = $this->container->get( CacheInterface::class );
		$stats = $cache->get_stats();

		$this->assertEquals( 1, $stats['misses'] );
		$this->assertEquals( 1, $stats['hits'] );
		$this->assertEquals( 1, $stats['writes'] );
	}
}
